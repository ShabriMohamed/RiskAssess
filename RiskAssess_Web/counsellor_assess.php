<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}
require_once 'config.php';

$counsellor_user_id = $_SESSION['user_id'];

// Get counsellor ID
$stmt = $conn->prepare("SELECT id FROM counsellors WHERE user_id = ?");
$stmt->bind_param("i", $counsellor_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Error: Counsellor profile not found.";
    exit;
}
$counsellor_id = $result->fetch_assoc()['id'];

// Get assessment statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_assessments,
        SUM(CASE WHEN risk_level = 'High' THEN 1 ELSE 0 END) as high_risk,
        SUM(CASE WHEN risk_level = 'Moderate' THEN 1 ELSE 0 END) as moderate_risk,
        SUM(CASE WHEN risk_level = 'Low' THEN 1 ELSE 0 END) as low_risk,
        COUNT(DISTINCT client_id) as assessed_clients
    FROM risk_assessments 
    WHERE counsellor_id = ?
");
$stmt->bind_param("i", $counsellor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent assessments
$stmt = $conn->prepare("
    SELECT ra.*, u.name as client_name, u.email as client_email, up.profile_photo 
    FROM risk_assessments ra
    JOIN users u ON ra.client_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE ra.counsellor_id = ?
    ORDER BY ra.assessment_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $counsellor_id);
$stmt->execute();
$recent_assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get high risk clients
$stmt = $conn->prepare("
    SELECT 
        ra.client_id, 
        u.name as client_name, 
        u.email as client_email,
        up.profile_photo,
        MAX(ra.assessment_date) as last_assessment,
        MAX(ra.risk_level) as risk_level,
        MAX(ra.risk_score) as risk_score
    FROM risk_assessments ra
    JOIN users u ON ra.client_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE ra.counsellor_id = ? AND ra.risk_level = 'High'
    GROUP BY ra.client_id, u.name, u.email, up.profile_photo
    ORDER BY MAX(ra.assessment_date) DESC
    LIMIT 5
");
$stmt->bind_param("i", $counsellor_id);
$stmt->execute();
$high_risk_clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="assess-container">
    <div id="alertContainer"></div>
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-primary">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Total</div>
                        <div class="stat-card-value"><?php echo $stats['total_assessments'] ?? 0; ?></div>
                        <div class="stat-card-desc">Assessments</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">High Risk</div>
                        <div class="stat-card-value"><?php echo $stats['high_risk'] ?? 0; ?></div>
                        <div class="stat-card-desc">Clients</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-warning">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Moderate Risk</div>
                        <div class="stat-card-value"><?php echo $stats['moderate_risk'] ?? 0; ?></div>
                        <div class="stat-card-desc">Clients</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Low Risk</div>
                        <div class="stat-card-value"><?php echo $stats['low_risk'] ?? 0; ?></div>
                        <div class="stat-card-desc">Clients</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Assessments -->
        <div class="col-lg-6 mb-4">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5><i class="fas fa-history me-2"></i>Recent Assessments</h5>
                    <button class="btn btn-sm btn-primary" id="viewAllAssessmentsBtn">
                        <i class="fas fa-list me-1"></i> View All Assessments
                    </button>
                </div>
                <div class="content-card-body">
                    <?php if (empty($recent_assessments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list empty-state-icon"></i>
                            <h4>No assessments yet</h4>
                            <p>Your clients haven't completed any risk assessments.</p>
                        </div>
                    <?php else: ?>
                        <div class="assessment-list">
                            <?php foreach ($recent_assessments as $assessment): ?>
                                <div class="assessment-item">
                                    <div class="assessment-client">
                                        <img src="<?php echo !empty($assessment['profile_photo']) ? 'uploads/profiles/' . $assessment['profile_photo'] : 'assets/img/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($assessment['client_name']); ?>" class="client-avatar">
                                        <div class="client-info">
                                            <div class="client-name"><?php echo htmlspecialchars($assessment['client_name']); ?></div>
                                            <div class="assessment-date"><?php echo date('M j, Y', strtotime($assessment['assessment_date'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="assessment-risk">
                                        <div class="risk-badge risk-<?php echo strtolower($assessment['risk_level']); ?>">
                                            <?php echo $assessment['risk_level']; ?> Risk
                                        </div>
                                        
                                    </div>
                                    <div class="assessment-actions">
                                        <button class="btn btn-sm btn-outline-primary view-assessment-btn" data-id="<?php echo $assessment['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success download-report-btn" data-id="<?php echo $assessment['id']; ?>">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- High Risk Clients -->
        <div class="col-lg-6 mb-4">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>High Risk Clients</h5>
                    <a href="#" class="btn btn-sm btn-outline-danger" id="viewHighRiskBtn">
                        <i class="fas fa-user-shield me-1"></i> View All High Risk
                    </a>
                </div>
                <div class="content-card-body">
                    <?php if (empty($high_risk_clients)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle empty-state-icon text-success"></i>
                            <h4>No high risk clients</h4>
                            <p>None of your clients are currently classified as high risk.</p>
                        </div>
                    <?php else: ?>
                        <div class="client-list">
                            <?php foreach ($high_risk_clients as $client): ?>
                                <div class="client-item high-risk">
                                    <div class="client-avatar">
                                        <img src="<?php echo !empty($client['profile_photo']) ? 'uploads/profiles/' . $client['profile_photo'] : 'assets/img/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($client['client_name']); ?>">
                                        <div class="risk-indicator"></div>
                                    </div>
                                    <div class="client-details">
                                        <div class="client-name"><?php echo htmlspecialchars($client['client_name']); ?></div>
                                        <div class="client-info">
                                            <span class="info-item"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($client['client_email']); ?></span>
                                        </div>
                                        <div class="risk-info">
                                            <span class="risk-badge risk-high">High Risk</span>
                                            <span class="assessment-date">Last assessed: <?php echo date('M j, Y', strtotime($client['last_assessment'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="client-actions">
    <button class="btn btn-sm btn-outline-primary view-client-assessments-btn" data-id="<?php echo $client['client_id']; ?>">
        <i class="fas fa-clipboard-list"></i>
    </button>
    <button class="btn btn-sm btn-outline-danger schedule-intervention-btn" data-id="<?php echo $client['client_id']; ?>" data-name="<?php echo htmlspecialchars($client['client_name']); ?>">
        <i class="fas fa-user-md"></i>
    </button>
</div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Risk Distribution Chart -->
    <div class="content-card mb-4">
        <div class="content-card-header">
            <h5><i class="fas fa-chart-pie me-2"></i>Risk Distribution Analysis</h5>
            <div class="chart-controls">
                <select class="form-select form-select-sm" id="chartPeriodSelect">
                    <option value="all">All Time</option>
                    <option value="month" selected>Last 30 Days</option>
                    <option value="quarter">Last 90 Days</option>
                    <option value="year">Last Year</option>
                </select>
            </div>
        </div>
        <div class="content-card-body">
            <div class="row">
                <div class="col-lg-8">
                    <div class="chart-container">
                        <canvas id="riskDistributionChart" height="300"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="risk-metrics">
                        <h6 class="metrics-title">Key Risk Metrics</h6>
                        
                        <div class="metric-item">
                            <div class="metric-label">High Risk Rate</div>
                            <div class="metric-value" id="highRiskRate">
                                <?php 
                                    $total = $stats['total_assessments'] ?: 1; // Avoid division by zero
                                    echo round(($stats['high_risk'] / $total) * 100);
                                ?>%
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-danger" style="width: <?php echo ($stats['high_risk'] / $total) * 100; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="metric-item">
                            <div class="metric-label">Moderate Risk Rate</div>
                            <div class="metric-value" id="moderateRiskRate">
                                <?php echo round(($stats['moderate_risk'] / $total) * 100); ?>%
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo ($stats['moderate_risk'] / $total) * 100; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="metric-item">
                            <div class="metric-label">Low Risk Rate</div>
                            <div class="metric-value" id="lowRiskRate">
                                <?php echo round(($stats['low_risk'] / $total) * 100); ?>%
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($stats['low_risk'] / $total) * 100; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="metric-item">
                            <div class="metric-label">Average Risk Score</div>
                            <div class="metric-value" id="avgRiskScore">--</div>
                        </div>
                        
                        <div class="metric-note mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Risk metrics are calculated based on the selected time period.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- All Assessments Section (Initially Hidden) -->
    <div class="content-card mb-4" id="allAssessmentsSection" style="display: none;">
        <div class="content-card-header">
            <h5><i class="fas fa-clipboard-list me-2"></i>All Risk Assessments</h5>
            <div class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search clients...">
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                        <li><a class="dropdown-item filter-option" data-filter="all" href="#">All Risk Levels</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item filter-option" data-filter="high" href="#">High Risk</a></li>
                        <li><a class="dropdown-item filter-option" data-filter="moderate" href="#">Moderate Risk</a></li>
                        <li><a class="dropdown-item filter-option" data-filter="low" href="#">Low Risk</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-sort me-1"></i> Sort
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdown">
                        <li><a class="dropdown-item sort-option" data-sort="date-desc" href="#">Date (Newest First)</a></li>
                        <li><a class="dropdown-item sort-option" data-sort="date-asc" href="#">Date (Oldest First)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item sort-option" data-sort="risk-desc" href="#">Risk Score (High to Low)</a></li>
                        <li><a class="dropdown-item sort-option" data-sort="risk-asc" href="#">Risk Score (Low to High)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item sort-option" data-sort="name-asc" href="#">Client Name (A-Z)</a></li>
                        <li><a class="dropdown-item sort-option" data-sort="name-desc" href="#">Client Name (Z-A)</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="content-card-body">
            <div class="table-responsive">
                <table class="table table-hover assessment-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Assessment Date</th>
                            <th>Risk Level</th>
                            <th>Risk Score</th>
                            <th>Key Factors</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="assessmentsTableBody">
                        <!-- Will be populated via AJAX -->
                        <tr>
                            <td colspan="6" class="text-center">Loading assessments...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="showing-entries">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span> assessments</div>
                <div class="pagination-container">
                    <ul class="pagination pagination-sm" id="assessmentsPagination">
                        <!-- Will be populated via JavaScript -->
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assessment Details Modal -->
<div class="modal fade" id="assessmentDetailsModal" tabindex="-1" aria-labelledby="assessmentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assessmentDetailsModalLabel">Assessment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3" id="assessmentLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading assessment details...</p>
                </div>
                <div id="assessmentContent" style="display: none;">
                    <!-- Client Info Section -->
                    <div class="client-profile-header mb-4">
                        <div class="client-profile-avatar">
                            <img src="" alt="Client" id="clientAvatar" class="profile-avatar">
                        </div>
                        <div class="client-profile-info">
                            <h4 id="clientName" class="mb-1"></h4>
                            <div class="client-profile-details">
                                <span id="clientEmail"><i class="fas fa-envelope me-2"></i></span>
                                <span id="clientPhone"><i class="fas fa-phone me-2"></i></span>
                                <span id="assessmentDate"><i class="fas fa-calendar-day me-2"></i></span>
                            </div>
                        </div>
                        <div class="client-risk-summary">
                            <div class="risk-badge-large" id="riskBadge"></div>
                            <div class="risk-score-large" id="riskScore"></div>
                        </div>
                    </div>
                    
                    <!-- Risk Assessment Summary -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="assessment-summary-card">
                                <h5 class="card-title"><i class="fas fa-chart-bar me-2"></i>Risk Assessment Summary</h5>
                                <div class="summary-item">
                                    <div class="summary-label">Risk Level:</div>
                                    <div class="summary-value" id="summaryRiskLevel"></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Risk Score:</div>
                                    <div class="summary-value" id="summaryRiskScore"></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Assessment Date:</div>
                                    <div class="summary-value" id="summaryDate"></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Previous Assessment:</div>
                                    <div class="summary-value" id="summaryPreviousAssessment"></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Risk Trend:</div>
                                    <div class="summary-value" id="summaryRiskTrend"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="key-factors-card">
                                <h5 class="card-title"><i class="fas fa-exclamation-circle me-2"></i>Key Risk Factors</h5>
                                <ul class="key-factors-list" id="keyFactorsList">
                                    <!-- Will be populated via JavaScript -->
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assessment Responses -->
                    <div class="assessment-responses-section">
                        <h5 class="section-title"><i class="fas fa-clipboard-list me-2"></i>Assessment Responses</h5>
                        <div class="accordion" id="responsesAccordion">
                            <!-- Will be populated via JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Recommendations -->
                    <div class="recommendations-section mt-4">
                        <h5 class="section-title"><i class="fas fa-hand-holding-medical me-2"></i>Recommendations</h5>
                        <div class="recommendations-content" id="recommendationsContent">
                            <!-- Will be populated via JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Counsellor Notes -->
                    <div class="notes-section mt-4">
                        <h5 class="section-title"><i class="fas fa-sticky-note me-2"></i>Counsellor Notes</h5>
                        <div class="form-floating">
                            <textarea class="form-control" id="counsellorNotes" style="height: 120px;"></textarea>
                            <label for="counsellorNotes">Add your notes about this assessment</label>
                        </div>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-primary btn-sm" id="saveNotesBtn">
                                <i class="fas fa-save me-1"></i> Save Notes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="downloadReportBtn">
                    <i class="fas fa-file-pdf me-1"></i> Download Report
                </button>
                <button type="button" class="btn btn-primary" id="scheduleSessionBtn">
                    <i class="fas fa-calendar-plus me-1"></i> Schedule Session
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Intervention Modal -->
<div class="modal fade" id="scheduleInterventionModal" tabindex="-1" aria-labelledby="scheduleInterventionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleInterventionModalLabel">Schedule Intervention</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>High Risk Client</strong>
                    <p class="mb-0">This client has been identified as high risk for substance abuse. Immediate intervention is recommended.</p>
                </div>
                
                <form id="interventionForm">
                    <input type="hidden" id="interventionClientId" name="client_id">
                    
                    
                    <div class="mb-3">
                        <label for="interventionDate" class="form-label">Intervention Date</label>
                        <input type="date" class="form-control" id="interventionDate" name="intervention_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="interventionTime" class="form-label">Time</label>
                        <input type="time" class="form-control" id="interventionTime" name="intervention_time" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="interventionDuration" class="form-label">Duration</label>
                        <select class="form-select" id="interventionDuration" name="intervention_duration" required>
                            <option value="30">30 minutes</option>
                            <option value="45">45 minutes</option>
                            <option value="60" selected>1 hour</option>
                            <option value="90">1.5 hours</option>
                            <option value="120">2 hours</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="interventionType" class="form-label">Intervention Type</label>
                        <select class="form-select" id="interventionType" name="intervention_type" required>
                            <option value="individual">Individual Counseling</option>
                            <option value="crisis">Crisis Intervention</option>
                            <option value="family">Family Counseling</option>
                            <option value="referral">Medical Referral</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="interventionNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="interventionNotes" name="intervention_notes" rows="3"></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notifyClient" name="notify_client" checked>
                        <label class="form-check-label" for="notifyClient">
                            Notify client about this intervention
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="scheduleInterventionBtn">
                    <i class="fas fa-calendar-plus me-1"></i> Schedule Intervention
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Assessment Container */
    .assess-container {
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Stat Cards */
    .stat-card {
        background: linear-gradient(145deg, #ffffff, #f5f7ff);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        overflow: hidden;
        height: 100%;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        border: 1px solid rgba(228, 233, 242, 0.7);
    }
    
    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 35px rgba(79, 140, 255, 0.1);
    }
    
    .stat-card-body {
        padding: 1.5rem;
        display: flex;
        align-items: center;
    }
    
    .stat-card-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1.25rem;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }
    
    .bg-primary { background: linear-gradient(135deg, #4f8cff, #3a75e0); }
    .bg-success { background: linear-gradient(135deg, #2ec4b6, #25a89e); }
    .bg-warning { background: linear-gradient(135deg, #fbbf24, #d97706); }
    .bg-danger { background: linear-gradient(135deg, #f87171, #dc2626); }
    
    .stat-card-info {
        flex: 1;
    }
    
    .stat-card-title {
        font-size: 0.9rem;
        color: #718096;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card-value {
        font-size: 2.25rem;
        font-weight: 700;
        color: #2d3748;
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    
    .stat-card-desc {
        font-size: 0.9rem;
        color: #718096;
    }
    
    /* Content Cards */
    .content-card {
        background-color: #fff;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        overflow: hidden;
        border: 1px solid rgba(228, 233, 242, 0.7);
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }
    
    .content-card:hover {
        box-shadow: 0 15px 35px rgba(79, 140, 255, 0.1);
    }
    
    .content-card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .content-card-header h5 {
        margin: 0;
        font-weight: 600;
        color: #2d3748;
        display: flex;
        align-items: center;
        font-size: 1.1rem;
    }
    
    .content-card-header h5 i {
        color: #4f8cff;
        margin-right: 0.75rem;
    }
    
    .content-card-body {
        padding: 1.5rem;
    }
    
    /* Assessment List */
    .assessment-list {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    
    .assessment-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-radius: 12px;
        background-color: #f8fafd;
        transition: all 0.3s ease;
    }
    
    .assessment-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }
    
    .assessment-client {
        display: flex;
        align-items: center;
        flex: 1;
    }
    
    .client-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 1rem;
        border: 2px solid white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .client-info {
        flex: 1;
    }
    
    .client-name {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.25rem;
    }
    
    .assessment-date {
        font-size: 0.85rem;
        color: #718096;
    }
    
    .assessment-risk {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin: 0 1.5rem;
    }
    
    .risk-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .risk-high {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    .risk-moderate {
        background-color: #fef3c7;
        color: #d97706;
    }
    
    .risk-low {
        background-color: #d1fae5;
        color: #059669;
    }
    
    .risk-score {
        font-size: 0.85rem;
        color: #4a5568;
        font-weight: 500;
    }
    
    .assessment-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    /* Client List */
    .client-list {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    
    .client-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-radius: 12px;
        background-color: #f8fafd;
        transition: all 0.3s ease;
    }
    
    .client-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }
    
    .client-item.high-risk {
        background-color: #fef2f2;
        border-left: 4px solid #dc2626;
    }
    
    .client-avatar {
        position: relative;
        margin-right: 1rem;
    }
    
    .client-avatar img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .risk-indicator {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background-color: #dc2626;
        border: 2px solid white;
    }
    
    .client-details {
        flex: 1;
        min-width: 0;
    }
    
    .risk-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 0.5rem;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 2rem 1rem;
    }
    
    .empty-state-icon {
        font-size: 3rem;
        color: #cbd5e0;
        margin-bottom: 1rem;
    }
    
    .empty-state h4 {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #2d3748;
    }
    
    .empty-state p {
        color: #718096;
        margin-bottom: 0;
    }
    
    /* Chart Container */
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    .chart-controls {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    /* Risk Metrics */
    .risk-metrics {
        background-color: #f8fafd;
        border-radius: 12px;
        padding: 1.5rem;
        height: 100%;
    }
    
    .metrics-title {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .metric-item {
        margin-bottom: 1.25rem;
    }
    
    .metric-label {
        font-size: 0.9rem;
        color: #4a5568;
        margin-bottom: 0.5rem;
    }
    
    .metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 0.5rem;
    }
    
    .metric-note {
        color: #718096;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
    }
    
    /* Assessment Table */
    .assessment-table th {
        font-weight: 600;
        color: #2d3748;
        border-top: none;
        border-bottom: 2px solid #edf2f7;
    }
    
    .assessment-table td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #edf2f7;
    }
    
    .assessment-table tr:hover {
        background-color: #f7fafc;
    }
    
    .assessment-table .client-cell {
        display: flex;
        align-items: center;
    }
    
    .assessment-table .client-cell img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 0.75rem;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .assessment-table .client-name-table {
        font-weight: 500;
        color: #2d3748;
    }
    
    .key-factors-cell {
        max-width: 250px;
    }
    
    .key-factor-badge {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: 50px;
        font-size: 0.75rem;
        margin-right: 0.25rem;
        margin-bottom: 0.25rem;
        background-color: #e2e8f0;
        color: #4a5568;
    }
    
    /* Response Items */
    .response-item {
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .response-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .response-question {
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 0.5rem;
    }
    
    .response-answer {
        color: #2d3748;
    }
    
    /* Modal Styles */
    .client-profile-header {
        display: flex;
        align-items: center;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .client-profile-avatar {
        margin-right: 1.5rem;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .client-profile-info {
        flex: 1;
    }
    
    .client-profile-details {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        color: #4a5568;
        font-size: 0.95rem;
    }
    
    .client-risk-summary {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-left: 1.5rem;
    }
    
    .risk-badge-large {
        padding: 0.5rem 1.25rem;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .risk-score-large {
        font-size: 1.75rem;
        font-weight: 700;
        color: #2d3748;
    }
    
    .assessment-summary-card, .key-factors-card {
        background-color: #f8fafd;
        border-radius: 12px;
        padding: 1.5rem;
        height: 100%;
    }
    
    .card-title {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
    }
    
    .card-title i {
        color: #4f8cff;
        margin-right: 0.5rem;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    
    .summary-label {
        font-weight: 500;
        color: #4a5568;
    }
    
    .summary-value {
        font-weight: 600;
        color: #2d3748;
    }
    
    .key-factors-list {
        list-style: none;
        padding-left: 0;
        margin-bottom: 0;
    }
    
    .key-factors-list li {
        display: flex;
        align-items: flex-start;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .key-factors-list li:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .key-factors-list li i {
        color: #dc2626;
        margin-right: 0.75rem;
        margin-top: 0.25rem;
    }
    
    .section-title {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
    }
    
    .section-title i {
        color: #4f8cff;
        margin-right: 0.5rem;
    }
    
    .recommendations-content {
        background-color: #f0f9ff;
        border-radius: 12px;
        padding: 1.5rem;
        border-left: 4px solid #3b82f6;
    }

    .key-factor-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 50px;
    font-size: 0.75rem;
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
    background-color: #e2e8f0;
    color: #4a5568;
}

</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    console.log("Script loaded successfully");
    
    let currentFilter = 'all';
    let currentSort = 'date-desc';
    let currentPage = 1;
    let itemsPerPage = 10;
    let totalItems = 0;
    let allAssessments = [];
    let currentAssessmentId = null;
    
    // Initialize Chart
    try {
        const ctx = document.getElementById('riskDistributionChart');
        if (ctx) {
            console.log("Canvas found, initializing chart");
            let riskDistributionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Last Week', 'This Week'],
                    datasets: [
                        {
                            label: 'High Risk',
                            data: [1, 1],
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Moderate Risk',
                            data: [0, 0],
                            borderColor: '#d97706',
                            backgroundColor: 'rgba(217, 119, 6, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Low Risk',
                            data: [0, 0],
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5, 150, 105, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
            console.log("Chart initialized");
        } else {
            console.error("Canvas element not found");
        }
    } catch (error) {
        console.error("Chart initialization error:", error);
    }
    
    // View All Assessments Button
    $('#viewAllAssessmentsBtn').click(function(e) {
        e.preventDefault();
        console.log("View All Assessments clicked");
        $('#allAssessmentsSection').slideDown();
        loadAllAssessments();
    });
    
    // View High Risk Button
    $('#viewHighRiskBtn').click(function(e) {
        e.preventDefault();
        console.log("View High Risk clicked");
        $('#allAssessmentsSection').slideDown();
        currentFilter = 'high';
        $('#filterDropdown').text('High Risk');
        loadAllAssessments();
    });
    
    // View Assessment Button
    $(document).on('click', '.view-assessment-btn', function() {
        const assessmentId = $(this).data('id');
        console.log("View Assessment clicked for ID:", assessmentId);
        viewAssessmentDetails(assessmentId);
    });
    
    // Download Report Button
    $(document).on('click', '.download-report-btn', function() {
        const assessmentId = $(this).data('id');
        console.log("Download Report clicked for ID:", assessmentId);
        window.location.href = `processes/counsellor_generate_report.php?assessment_id=${assessmentId}`;
    });
    
    // View Client Assessments Button
    $(document).on('click', '.view-client-assessments-btn', function() {
        const clientId = $(this).data('id');
        console.log("View Client Assessments clicked for client ID:", clientId);
        viewClientAssessments(clientId);
    });
    
    // Schedule Intervention Button
    $(document).on('click', '.schedule-intervention-btn', function() {
        const clientId = $(this).data('id');
        const clientName = $(this).data('name');
        console.log("Schedule Intervention clicked for client:", clientName, "ID:", clientId);
        scheduleIntervention(clientId, clientName);
    });
    
    // Load All Assessments
    function loadAllAssessments() {
        console.log("Loading all assessments...");
        $.ajax({
            url: 'processes/counsellor_get_assessments.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log("Assessments response:", response);
                if (response.success) {
                    allAssessments = response.assessments;
                    totalItems = allAssessments.length;
                    $('#totalCount').text(totalItems);
                    
                    // Apply filter and sort
                    filterAndSortAssessments();
                } else {
                    console.error('Error loading assessments:', response.message);
                    showAlert('danger', 'Error loading assessments: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                showAlert('danger', 'Failed to load assessments. Please try again.');
            }
        });
    }
    
    // Filter and Sort Assessments
    function filterAndSortAssessments() {
        console.log("Filtering assessments with filter:", currentFilter);
        let filteredAssessments = allAssessments;
        
        // Apply filter
        if (currentFilter !== 'all') {
            filteredAssessments = allAssessments.filter(function(assessment) {
                return assessment.risk_level.toLowerCase() === currentFilter;
            });
        }
        
        // Update total count
        totalItems = filteredAssessments.length;
        $('#totalCount').text(totalItems);
        
        // Calculate pagination
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        if (currentPage > totalPages && totalPages > 0) {
            currentPage = totalPages;
        }
        
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
        
        // Update showing count
        $('#showingCount').text(totalItems === 0 ? 0 : `${startIndex + 1}-${endIndex}`);
        
        // Get current page items
        const currentItems = filteredAssessments.slice(startIndex, endIndex);
        
        // Render assessments
        renderAssessments(currentItems);
    }
    
    // Render Assessments
    function renderAssessments(assessments) {
        console.log("Rendering", assessments.length, "assessments");
        const tableBody = $('#assessmentsTableBody');
        tableBody.empty();
        
        if (assessments.length === 0) {
            tableBody.html('<tr><td colspan="6" class="text-center py-4">No assessments found</td></tr>');
            return;
        }
        
        assessments.forEach(function(assessment) {
            const assessmentDate = new Date(assessment.assessment_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            
            // Parse key factors
            let keyFactors = [];
            if (assessment.key_factors) {
                try {
                    keyFactors = JSON.parse(assessment.key_factors);
                } catch (e) {
                    console.error('Error parsing key factors:', e);
                }
            }
            
            // Create key factor badges
            let keyFactorsHtml = '';
            if (keyFactors && keyFactors.length > 0) {
                keyFactors.slice(0, 3).forEach(function(factor) {
                    keyFactorsHtml += `<span class="key-factor-badge">${factor}</span>`;
                });
                
                if (keyFactors.length > 3) {
                    keyFactorsHtml += `<span class="key-factor-badge">+${keyFactors.length - 3} more</span>`;
                }
            } else {
                keyFactorsHtml = '<span class="text-muted">None identified</span>';
            }
            
            const row = `
                <tr>
                    <td>
                        <div class="client-cell">
                            <img src="${assessment.profile_photo ? 'uploads/profiles/' + assessment.profile_photo : 'assets/img/default-profile.png'}" alt="${assessment.client_name}">
                            <div class="client-name-table">${assessment.client_name}</div>
                        </div>
                    </td>
                    <td>${assessmentDate}</td>
                    <td>
                        <span class="risk-badge risk-${assessment.risk_level.toLowerCase()}">${assessment.risk_level}</span>
                    </td>
                    <td>${Math.round(assessment.risk_score)}%</td>
                    <td class="key-factors-cell">${keyFactorsHtml}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary view-assessment-btn" data-id="${assessment.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success download-report-btn" data-id="${assessment.id}">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger schedule-intervention-btn" data-id="${assessment.client_id}" data-name="${assessment.client_name}">
                                <i class="fas fa-user-md"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            
            tableBody.append(row);
        });
    }
    
    // View Client Assessments
    function viewClientAssessments(clientId) {
        console.log("Viewing assessments for client ID:", clientId);
        $('#allAssessmentsSection').slideDown();
        currentFilter = 'all';
        $('#filterDropdown').text('All Risk Levels');
        $('#searchInput').val('');
        loadAllAssessments();
        
        // After loading, filter by client
        setTimeout(function() {
            const clientName = allAssessments.find(a => a.client_id == clientId)?.client_name || '';
            $('#searchInput').val(clientName);
            filterAndSortAssessments();
        }, 1000);
    }
    
    // Schedule Intervention
    function scheduleIntervention(clientId, clientName) {
        console.log("Scheduling intervention for client:", clientName, "ID:", clientId);
        $('#interventionClientId').val(clientId);
        $('#scheduleInterventionModalLabel').text(`Schedule Intervention for ${clientName}`);
        
        // Set default date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        $('#interventionDate').val(tomorrow.toISOString().split('T')[0]);
        
        // Set default time to 10:00 AM
        $('#interventionTime').val('10:00');
        
        $('#scheduleInterventionModal').modal('show');
    }
    
    // Schedule Intervention Submit
    $('#scheduleInterventionBtn').click(function() {
        const form = $('#interventionForm')[0];
        if (form.checkValidity()) {
            const formData = new FormData(form);
            
            console.log("Submitting intervention form...");
            $.ajax({
                url: 'processes/counsellor_schedule_intervention.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    console.log("Intervention response:", response);
                    if (response.success) {
                        $('#scheduleInterventionModal').modal('hide');
                        showAlert('success', 'Intervention scheduled successfully!');
                    } else {
                        showAlert('danger', 'Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    showAlert('danger', 'An error occurred while scheduling the intervention.');
                }
            });
        } else {
            form.reportValidity();
        }
    });
    
    // Helper function to show alerts
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#alertContainer').html(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
});
</script>


