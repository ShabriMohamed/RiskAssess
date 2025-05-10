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

// Get current date in Y-m-d format
$today = date('Y-m-d');
$current_time = date('H:i:s');

// Get appointment statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'scheduled' AND (appointment_date > ? OR (appointment_date = ? AND start_time > ?)) THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'no-show' THEN 1 ELSE 0 END) as no_show
    FROM appointments 
    WHERE counsellor_id = ?
");
$stmt->bind_param("sssi", $today, $today, $current_time, $counsellor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get today's appointments
$stmt = $conn->prepare("
    SELECT a.*, u.name as client_name, up.profile_photo 
    FROM appointments a
    JOIN users u ON a.client_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE a.counsellor_id = ? AND a.appointment_date = ? AND a.status = 'scheduled'
    ORDER BY a.start_time ASC
");
$stmt->bind_param("is", $counsellor_id, $today);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming appointments (excluding today)
$stmt = $conn->prepare("
    SELECT a.*, u.name as client_name, up.profile_photo 
    FROM appointments a
    JOIN users u ON a.client_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE a.counsellor_id = ? AND a.appointment_date > ? AND a.status = 'scheduled'
    ORDER BY a.appointment_date ASC, a.start_time ASC
    LIMIT 5
");
$stmt->bind_param("is", $counsellor_id, $today);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="appointments-container">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Total</div>
                        <div class="stat-card-value"><?php echo $stats['total']; ?></div>
                        <div class="stat-card-desc">Appointments</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-success">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Upcoming</div>
                        <div class="stat-card-value"><?php echo $stats['upcoming']; ?></div>
                        <div class="stat-card-desc">Appointments</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Completed</div>
                        <div class="stat-card-value"><?php echo $stats['completed']; ?></div>
                        <div class="stat-card-desc">Sessions</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-warning">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Cancelled</div>
                        <div class="stat-card-value"><?php echo $stats['cancelled']; ?></div>
                        <div class="stat-card-desc">Appointments</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Today's Appointments -->
        <div class="col-lg-6 mb-4">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5><i class="fas fa-calendar-day me-2"></i>Today's Appointments</h5>
                    <span class="date-badge"><?php echo date('F j, Y'); ?></span>
                </div>
                <div class="content-card-body">
                    <?php if (empty($today_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar empty-state-icon"></i>
                            <h4>No appointments today</h4>
                            <p>You don't have any scheduled appointments for today.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($today_appointments as $appointment): ?>
                                <?php 
                                    $start_time = date('g:i A', strtotime($appointment['start_time']));
                                    $end_time = date('g:i A', strtotime($appointment['end_time']));
                                    $current_time_obj = new DateTime();
                                    $appointment_start = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
                                    $appointment_end = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time']);
                                    
                                    $status = 'upcoming';
                                    if ($current_time_obj > $appointment_end) {
                                        $status = 'past';
                                    } elseif ($current_time_obj >= $appointment_start && $current_time_obj <= $appointment_end) {
                                        $status = 'current';
                                    }
                                ?>
                                <div class="timeline-item <?php echo $status; ?>">
                                    <div class="timeline-time">
                                        <div class="time"><?php echo $start_time; ?> - <?php echo $end_time; ?></div>
                                        <?php if ($status === 'current'): ?>
                                            <div class="status-badge current">In Progress</div>
                                        <?php elseif ($status === 'upcoming'): ?>
                                            <div class="status-badge upcoming">Upcoming</div>
                                        <?php else: ?>
                                            <div class="status-badge past">Past</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-client">
                                            <img src="<?php echo !empty($appointment['profile_photo']) ? 'uploads/profiles/' . $appointment['profile_photo'] : 'assets/img/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($appointment['client_name']); ?>" class="client-avatar">
                                            <div class="client-info">
                                                <div class="client-name"><?php echo htmlspecialchars($appointment['client_name']); ?></div>
                                                <div class="appointment-id">Appointment #<?php echo $appointment['id']; ?></div>
                                            </div>
                                        </div>
                                        <div class="timeline-actions">
                                            <?php if ($status === 'current' || $status === 'upcoming'): ?>
                                                <button class="btn btn-sm btn-outline-primary me-2" onclick="startSession(<?php echo $appointment['id']; ?>, <?php echo $appointment['client_id']; ?>)">
                                                    <i class="fas fa-video me-1"></i> Start Session
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary me-2" onclick="viewClient(<?php echo $appointment['client_id']; ?>)">
                                                    <i class="fas fa-user me-1"></i> View Client
                                                </button>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $appointment['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $appointment['id']; ?>">
                                                        <li><a class="dropdown-item" href="#" onclick="markCompleted(<?php echo $appointment['id']; ?>)"><i class="fas fa-check-circle me-2 text-success"></i> Mark as Completed</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="markNoShow(<?php echo $appointment['id']; ?>)"><i class="fas fa-user-slash me-2 text-warning"></i> Mark as No-Show</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="addNotes(<?php echo $appointment['id']; ?>, '<?php echo addslashes($appointment['notes'] ?? ''); ?>')"><i class="fas fa-sticky-note me-2 text-info"></i> Add Notes</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)"><i class="fas fa-times-circle me-2"></i> Cancel Appointment</a></li>
                                                    </ul>
                                                </div>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary me-2" onclick="viewClient(<?php echo $appointment['client_id']; ?>)">
                                                    <i class="fas fa-user me-1"></i> View Client
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="addNotes(<?php echo $appointment['id']; ?>, '<?php echo addslashes($appointment['notes'] ?? ''); ?>')">
                                                    <i class="fas fa-sticky-note me-1"></i> Add Notes
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Appointments -->
        <div class="col-lg-6 mb-4">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Upcoming Appointments</h5>
                    <a href="#" class="btn btn-sm btn-primary" id="viewAllBtn">View All</a>
                </div>
                <div class="content-card-body">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-plus empty-state-icon"></i>
                            <h4>No upcoming appointments</h4>
                            <p>You don't have any scheduled appointments for the future.</p>
                        </div>
                    <?php else: ?>
                        <div class="upcoming-list">
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-date">
                                        <div class="date-day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></div>
                                        <div class="date-month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></div>
                                    </div>
                                    <div class="upcoming-details">
                                        <div class="upcoming-client">
                                            <img src="<?php echo !empty($appointment['profile_photo']) ? 'uploads/profiles/' . $appointment['profile_photo'] : 'assets/img/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($appointment['client_name']); ?>" class="client-avatar-sm">
                                            <span class="client-name"><?php echo htmlspecialchars($appointment['client_name']); ?></span>
                                        </div>
                                        <div class="upcoming-time">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('g:i A', strtotime($appointment['start_time'])); ?> - <?php echo date('g:i A', strtotime($appointment['end_time'])); ?>
                                        </div>
                                    </div>
                                    <div class="upcoming-actions">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-eye"></i>
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
    
    <!-- All Appointments Section (Initially Hidden) -->
    <div class="content-card mb-4" id="allAppointmentsSection" style="display: none;">
        <div class="content-card-header">
            <h5><i class="fas fa-list me-2"></i>All Appointments</h5>
            <div class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search...">
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                        <li><a class="dropdown-item filter-option" data-filter="all" href="#">All Appointments</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item filter-option" data-filter="scheduled" href="#">Scheduled</a></li>
                        <li><a class="dropdown-item filter-option" data-filter="completed" href="#">Completed</a></li>
                        <li><a class="dropdown-item filter-option" data-filter="cancelled" href="#">Cancelled</a></li>
                        <li><a class="dropdown-item filter-option" data-filter="no-show" href="#">No-Show</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-sort me-1"></i> Sort
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdown">
                        <li><a class="dropdown-item sort-option" data-sort="date-asc" href="#">Date (Oldest First)</a></li>
                        <li><a class="dropdown-item sort-option" data-sort="date-desc" href="#">Date (Newest First)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item sort-option" data-sort="client-asc" href="#">Client Name (A-Z)</a></li>
                        <li><a class="dropdown-item sort-option" data-sort="client-desc" href="#">Client Name (Z-A)</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="content-card-body">
            <div class="table-responsive">
                <table class="table table-hover appointment-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentsTableBody">
                        <!-- Will be populated via AJAX -->
                        <tr>
                            <td colspan="6" class="text-center">Loading appointments...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="showing-entries">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span> entries</div>
                <div class="pagination-container">
                    <ul class="pagination pagination-sm" id="appointmentsPagination">
                        <!-- Will be populated via JavaScript -->
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- View Appointment Modal -->
<div class="modal fade" id="viewAppointmentModal" tabindex="-1" aria-labelledby="viewAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAppointmentModalLabel">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3" id="appointmentLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading appointment details...</p>
                </div>
                <div id="appointmentDetails" style="display: none;">
                    <div class="appointment-header">
                        <div class="appointment-status" id="appointmentStatusBadge"></div>
                        <div class="appointment-id">Appointment #<span id="appointmentId"></span></div>
                    </div>
                    <div class="appointment-client mb-4">
                        <img src="" alt="Client" id="appointmentClientAvatar" class="client-avatar-lg">
                        <div class="client-details">
                            <h4 id="appointmentClientName"></h4>
                            <div id="appointmentClientEmail" class="client-email"></div>
                            <div id="appointmentClientPhone" class="client-phone"></div>
                        </div>
                    </div>
                    <div class="appointment-info-grid">
                        <div class="appointment-info-item">
                            <div class="info-label">Date</div>
                            <div class="info-value" id="appointmentDate"></div>
                        </div>
                        <div class="appointment-info-item">
                            <div class="info-label">Time</div>
                            <div class="info-value" id="appointmentTime"></div>
                        </div>
                        <div class="appointment-info-item">
                            <div class="info-label">Duration</div>
                            <div class="info-value" id="appointmentDuration"></div>
                        </div>
                        <div class="appointment-info-item">
                            <div class="info-label">Created</div>
                            <div class="info-value" id="appointmentCreated"></div>
                        </div>
                    </div>
                    <div class="appointment-notes mt-4">
                        <div class="info-label">Notes</div>
                        <div class="notes-content" id="appointmentNotes"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div class="dropdown" id="appointmentActionsDropdown" style="display: none;">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" id="startSessionBtn"><i class="fas fa-video me-2"></i> Start Session</a></li>
                        <li><a class="dropdown-item" href="#" id="markCompletedBtn"><i class="fas fa-check-circle me-2 text-success"></i> Mark as Completed</a></li>
                        <li><a class="dropdown-item" href="#" id="markNoShowBtn"><i class="fas fa-user-slash me-2 text-warning"></i> Mark as No-Show</a></li>
                        <li><a class="dropdown-item" href="#" id="addNotesBtn"><i class="fas fa-sticky-note me-2 text-info"></i> Add Notes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" id="cancelAppointmentBtn"><i class="fas fa-times-circle me-2"></i> Cancel Appointment</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notesModalLabel">Appointment Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="notesForm">
                    <input type="hidden" id="notesAppointmentId">
                    <div class="mb-3">
                        <label for="appointmentNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notesTextarea" rows="5" placeholder="Enter notes about this appointment..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveNotesBtn">Save Notes</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Appointment Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelModalLabel">Cancel Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="cancelForm">
                    <input type="hidden" id="cancelAppointmentId">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Are you sure you want to cancel this appointment? This action cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancelReason" rows="3" placeholder="Please provide a reason for cancellation..."></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notifyClient">
                        <label class="form-check-label" for="notifyClient">
                            Notify client about cancellation
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Cancel Appointment</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Appointments Container */
    .appointments-container {
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
    .bg-info { background: linear-gradient(135deg, #3d5a80, #2d4a70); }
    .bg-warning { background: linear-gradient(135deg, #ff9f1c, #f1932b); }
    
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
    
    .date-badge {
        background-color: #edf2f7;
        color: #4a5568;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .content-card-body {
        padding: 1.5rem;
    }
    
    /* Timeline */
    .timeline {
        position: relative;
    }
    
    .timeline-item {
        display: flex;
        margin-bottom: 1.5rem;
        padding: 1rem;
        border-radius: 12px;
        background-color: #f8fafd;
        transition: all 0.3s ease;
        border-left: 4px solid #cbd5e0;
    }
    
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    
    .timeline-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }
    
    .timeline-item.current {
        background-color: #ebf8ff;
        border-left-color: #4f8cff;
    }
    
    .timeline-item.upcoming {
        background-color: #f0fff4;
        border-left-color: #2ec4b6;
    }
    
    .timeline-item.past {
        background-color: #f7fafc;
        border-left-color: #a0aec0;
    }
    
    .timeline-time {
        min-width: 120px;
        margin-right: 1.5rem;
    }
    
    .timeline-time .time {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.5rem;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 500;
        text-align: center;
    }
    
    .status-badge.current {
        background-color: #ebf8ff;
        color: #4f8cff;
    }
    
    .status-badge.upcoming {
        background-color: #f0fff4;
        color: #2ec4b6;
    }
    
    .status-badge.past {
        background-color: #f7fafc;
        color: #a0aec0;
    }
    
    .timeline-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .timeline-client {
        display: flex;
        align-items: center;
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
    
    .client-avatar-lg {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .client-avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 0.75rem;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .client-info {
        flex: 1;
    }
    
    .client-name {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.25rem;
    }
    
    .appointment-id {
        font-size: 0.85rem;
        color: #718096;
    }
    
    .timeline-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    /* Upcoming List */
    .upcoming-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .upcoming-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-radius: 12px;
        background-color: #f8fafd;
        transition: all 0.3s ease;
    }
    
    .upcoming-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        background-color: #f0f7ff;
    }
    
    .upcoming-date {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #4f8cff, #3a75e0);
        color: white;
        border-radius: 12px;
        margin-right: 1rem;
        box-shadow: 0 4px 12px rgba(79, 140, 255, 0.2);
    }
    
    .date-day {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .date-month {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .upcoming-details {
        flex: 1;
    }
    
    .upcoming-client {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .upcoming-time {
        font-size: 0.9rem;
        color: #718096;
    }
    
    .upcoming-actions {
        margin-left: auto;
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
    
    /* Appointment Table */
    .appointment-table th {
        font-weight: 600;
        color: #2d3748;
        border-top: none;
        border-bottom: 2px solid #edf2f7;
    }
    
    .appointment-table td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #edf2f7;
    }
    
    .appointment-table tr:hover {
        background-color: #f7fafc;
    }
    
    .status-pill {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-pill.scheduled {
        background-color: #ebf8ff;
        color: #4f8cff;
    }
    
    .status-pill.completed {
        background-color: #f0fff4;
        color: #2ec4b6;
    }
    
    .status-pill.cancelled {
        background-color: #fff5f5;
        color: #e53e3e;
    }
    
    .status-pill.no-show {
        background-color: #fffaf0;
        color: #dd6b20;
    }
    
    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: flex-end;
    }
    
    .pagination .page-link {
        color: #4f8cff;
        border-color: #edf2f7;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .pagination .page-item.active .page-link {
        background-color: #4f8cff;
        border-color: #4f8cff;
    }
    
    .showing-entries {
        font-size: 0.9rem;
        color: #718096;
    }
    
    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 25px 50px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #4f8cff, #3a75e0);
        color: white;
        padding: 1.25rem 1.5rem;
        border-bottom: none;
    }
    
    .modal-title {
        font-weight: 600;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid #edf2f7;
    }
    
    .btn-close {
        color: white;
        filter: brightness(0) invert(1);
        opacity: 0.8;
        transition: all 0.2s;
    }
    
    .btn-close:hover {
        opacity: 1;
        transform: rotate(90deg);
    }
    
    /* Appointment Details */
    .appointment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .appointment-status {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .appointment-status.scheduled {
        background-color: #ebf8ff;
        color: #4f8cff;
    }
    
    .appointment-status.completed {
        background-color: #f0fff4;
        color: #2ec4b6;
    }
    
    .appointment-status.cancelled {
        background-color: #fff5f5;
        color: #e53e3e;
    }
    
    .appointment-status.no-show {
        background-color: #fffaf0;
        color: #dd6b20;
    }
    
    .appointment-client {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        background-color: #f8fafd;
        border-radius: 12px;
    }
    
    .client-details {
        margin-left: 1.5rem;
    }
    
    .client-details h4 {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #2d3748;
    }
    
    .client-email, .client-phone {
        font-size: 0.9rem;
        color: #718096;
        display: flex;
        align-items: center;
        margin-bottom: 0.25rem;
    }
    
    .client-email i, .client-phone i {
        margin-right: 0.5rem;
        color: #4f8cff;
    }
    
    .appointment-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .appointment-info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 0.85rem;
        font-weight: 500;
        color: #718096;
        margin-bottom: 0.5rem;
    }
    
    .info-value {
        font-size: 1rem;
        color: #2d3748;
        font-weight: 500;
    }
    
    .appointment-notes {
        padding: 1.5rem;
        background-color: #f8fafd;
        border-radius: 12px;
    }
    
    .notes-content {
        margin-top: 0.75rem;
        white-space: pre-line;
        color: #4a5568;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .appointment-info-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
    }
    
    @media (max-width: 768px) {
        .timeline-item {
            flex-direction: column;
        }
        
        .timeline-time {
            margin-right: 0;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .upcoming-item {
            flex-wrap: wrap;
        }
        
        .upcoming-actions {
            margin-left: 0;
            margin-top: 1rem;
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }
        
        .appointment-client {
            flex-direction: column;
            text-align: center;
        }
        
        .client-details {
            margin-left: 0;
            margin-top: 1rem;
        }
    }
    
    @media (max-width: 576px) {
        .content-card-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .content-card-header .date-badge,
        .content-card-header .btn {
            margin-top: 0.75rem;
        }
        
        .timeline-content {
            gap: 0.75rem;
        }
        
        .timeline-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .timeline-actions .btn {
            width: 100%;
        }
    }
</style>

   <script>
    $(document).ready(function() {
        let currentFilter = 'all';
        let currentSort = 'date-desc';
        let currentPage = 1;
        let itemsPerPage = 10;
        let totalItems = 0;
        let allAppointments = [];
        
        // View All Appointments Button
        $('#viewAllBtn').click(function(e) {
            e.preventDefault();
            $('#allAppointmentsSection').slideDown();
            loadAllAppointments();
            
            // Scroll to the section
            $('html, body').animate({
                scrollTop: $('#allAppointmentsSection').offset().top - 20
            }, 500);
        });
        
        // Load All Appointments
        function loadAllAppointments() {
            $.ajax({
                url: 'processes/counsellor_get_appointments.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        allAppointments = response.appointments;
                        totalItems = allAppointments.length;
                        $('#totalCount').text(totalItems);
                        
                        // Apply filter and sort
                        filterAndSortAppointments();
                    } else {
                        console.error('Error loading appointments:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }
        
        // Filter and Sort Appointments
        function filterAndSortAppointments() {
            let filteredAppointments = allAppointments;
            
            // Apply filter
            if (currentFilter !== 'all') {
                filteredAppointments = allAppointments.filter(function(appointment) {
                    return appointment.status === currentFilter;
                });
            }
            
            // Apply sort
            filteredAppointments.sort(function(a, b) {
                switch (currentSort) {
                    case 'date-asc':
                        return new Date(a.appointment_date + ' ' + a.start_time) - new Date(b.appointment_date + ' ' + b.start_time);
                    case 'date-desc':
                        return new Date(b.appointment_date + ' ' + b.start_time) - new Date(a.appointment_date + ' ' + a.start_time);
                    case 'client-asc':
                        return a.client_name.localeCompare(b.client_name);
                    case 'client-desc':
                        return b.client_name.localeCompare(a.client_name);
                    default:
                        return new Date(b.appointment_date + ' ' + b.start_time) - new Date(a.appointment_date + ' ' + a.start_time);
                }
            });
            
            // Apply search if needed
            const searchTerm = $('#searchInput').val().toLowerCase();
            if (searchTerm) {
                filteredAppointments = filteredAppointments.filter(function(appointment) {
                    return appointment.client_name.toLowerCase().includes(searchTerm) || 
                           appointment.id.toString().includes(searchTerm) ||
                           appointment.appointment_date.includes(searchTerm);
                });
            }
            
            // Update total count
            totalItems = filteredAppointments.length;
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
            const currentItems = filteredAppointments.slice(startIndex, endIndex);
            
            // Render appointments
            renderAppointments(currentItems);
            
            // Render pagination
            renderPagination(totalPages);
        }
        
        // Render Appointments
        function renderAppointments(appointments) {
            const tableBody = $('#appointmentsTableBody');
            tableBody.empty();
            
            if (appointments.length === 0) {
                tableBody.html('<tr><td colspan="6" class="text-center py-4">No appointments found</td></tr>');
                return;
            }
            
            appointments.forEach(function(appointment) {
                const appointmentDate = new Date(appointment.appointment_date);
                const formattedDate = appointmentDate.toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                
                const startTime = formatTime(appointment.start_time);
                const endTime = formatTime(appointment.end_time);
                
                let statusClass = '';
                switch (appointment.status) {
                    case 'scheduled':
                        statusClass = 'scheduled';
                        break;
                    case 'completed':
                        statusClass = 'completed';
                        break;
                    case 'cancelled':
                        statusClass = 'cancelled';
                        break;
                    case 'no-show':
                        statusClass = 'no-show';
                        break;
                }
                
                const row = `
                    <tr>
                        <td>#${appointment.id}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="${appointment.profile_photo ? 'uploads/profiles/' + appointment.profile_photo : 'assets/img/default-profile.png'}" alt="${appointment.client_name}" class="client-avatar-sm me-2">
                                <span>${appointment.client_name}</span>
                            </div>
                        </td>
                        <td>${formattedDate}</td>
                        <td>${startTime} - ${endTime}</td>
                        <td><span class="status-pill ${statusClass}">${capitalizeFirstLetter(appointment.status)}</span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewAppointment(${appointment.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                ${appointment.status === 'scheduled' ? `
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="startSession(${appointment.id}, ${appointment.client_id})"><i class="fas fa-video me-2"></i> Start Session</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="markCompleted(${appointment.id})"><i class="fas fa-check-circle me-2 text-success"></i> Mark as Completed</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="markNoShow(${appointment.id})"><i class="fas fa-user-slash me-2 text-warning"></i> Mark as No-Show</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="addNotes(${appointment.id}, '${appointment.notes ? addslashes(appointment.notes) : ''}')"><i class="fas fa-sticky-note me-2 text-info"></i> Add Notes</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="cancelAppointment(${appointment.id})"><i class="fas fa-times-circle me-2"></i> Cancel Appointment</a></li>
                                    </ul>
                                </div>
                                ` : `
                                <button class="btn btn-sm btn-outline-info" onclick="addNotes(${appointment.id}, '${appointment.notes ? addslashes(appointment.notes) : ''}')">
                                    <i class="fas fa-sticky-note"></i>
                                </button>
                                `}
                            </div>
                        </td>
                    </tr>
                `;
                
                tableBody.append(row);
            });
        }
        
        // Render Pagination
        function renderPagination(totalPages) {
            const pagination = $('#appointmentsPagination');
            pagination.empty();
            
            if (totalPages <= 1) {
                return;
            }
            
            // Previous button
            pagination.append(`
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `);
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, startPage + 4);
            
            for (let i = startPage; i <= endPage; i++) {
                pagination.append(`
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `);
            }
            
            // Next button
            pagination.append(`
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `);
        }
        
        // Save Notes
        $('#saveNotesBtn').click(function() {
            const appointmentId = $('#notesAppointmentId').val();
            const notes = $('#notesTextarea').val();
            
            $.ajax({
                url: 'processes/counsellor_update_appointment.php',
                type: 'POST',
                data: {
                    appointment_id: appointmentId,
                    action: 'update_notes',
                    notes: notes
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#notesModal').modal('hide');
                        
                        // Update appointment in the list
                        const index = allAppointments.findIndex(a => a.id == appointmentId);
                        if (index !== -1) {
                            allAppointments[index].notes = notes;
                            filterAndSortAppointments();
                        }
                        
                        // Show success message
                        showAlert('Notes updated successfully', 'success');
                    } else {
                        showAlert('Error updating notes: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Error updating notes', 'danger');
                    console.error('AJAX error:', error);
                }
            });
        });
        
        // Confirm Cancel Appointment
        $('#confirmCancelBtn').click(function() {
            const appointmentId = $('#cancelAppointmentId').val();
            const reason = $('#cancelReason').val();
            const notifyClient = $('#notifyClient').is(':checked');
            
            $.ajax({
                url: 'processes/counsellor_update_appointment.php',
                type: 'POST',
                data: {
                    appointment_id: appointmentId,
                    action: 'cancel',
                    reason: reason,
                    notify_client: notifyClient ? 1 : 0
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#cancelModal').modal('hide');
                        
                        // Update appointment in the list
                        const index = allAppointments.findIndex(a => a.id == appointmentId);
                        if (index !== -1) {
                            allAppointments[index].status = 'cancelled';
                            allAppointments[index].notes = allAppointments[index].notes + '\n\nCancellation reason: ' + reason;
                            filterAndSortAppointments();
                        }
                        
                        // Show success message
                        showAlert('Appointment cancelled successfully', 'success');
                        
                        // Reload page to update statistics
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('Error cancelling appointment: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Error cancelling appointment', 'danger');
                    console.error('AJAX error:', error);
                }
            });
        });
        
        // Modal event handlers for appointment details
        $('#viewAppointmentModal').on('hidden.bs.modal', function() {
            $('#appointmentDetails').hide();
            $('#appointmentLoading').show();
            $('#appointmentActionsDropdown').hide();
        });
        
        // Action buttons in modal
        $('#startSessionBtn').click(function() {
            const appointmentId = $(this).data('appointment-id');
            const clientId = $(this).data('client-id');
            $('#viewAppointmentModal').modal('hide');
            startSession(appointmentId, clientId);
        });
        
        $('#markCompletedBtn').click(function() {
            const appointmentId = $(this).data('appointment-id');
            $('#viewAppointmentModal').modal('hide');
            markCompleted(appointmentId);
        });
        
        $('#markNoShowBtn').click(function() {
            const appointmentId = $(this).data('appointment-id');
            $('#viewAppointmentModal').modal('hide');
            markNoShow(appointmentId);
        });
        
        $('#addNotesBtn').click(function() {
            const appointmentId = $(this).data('appointment-id');
            const notes = $(this).data('notes');
            $('#viewAppointmentModal').modal('hide');
            addNotes(appointmentId, notes);
        });
        
        $('#cancelAppointmentBtn').click(function() {
            const appointmentId = $(this).data('appointment-id');
            $('#viewAppointmentModal').modal('hide');
            cancelAppointment(appointmentId);
        });
        
        // Pagination click handler
        $(document).on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && page !== currentPage) {
                currentPage = page;
                filterAndSortAppointments();
            }
        });
        
        // Filter click handler
        $('.filter-option').click(function(e) {
            e.preventDefault();
            currentFilter = $(this).data('filter');
            currentPage = 1;
            $('#filterDropdown').text($(this).text());
            filterAndSortAppointments();
        });
        
        // Sort click handler
        $('.sort-option').click(function(e) {
            e.preventDefault();
            currentSort = $(this).data('sort');
            currentPage = 1;
            $('#sortDropdown').text($(this).text());
            filterAndSortAppointments();
        });
        
        // Search input handler
        $('#searchInput').on('input', function() {
            currentPage = 1;
            filterAndSortAppointments();
        });
        
        // Helper Functions
        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Insert alert at the top of the appointments container
            $('.appointments-container').prepend(alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
        
        function addslashes(string) {
            return string.replace(/\\/g, '\\\\').replace(/\'/g, '\\\'').replace(/\"/g, '\\"').replace(/\0/g, '\\0');
        }
        
        function formatTime(timeString) {
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }
        
        function capitalizeFirstLetter(string) {
            if (!string) return '';
            return string.charAt(0).toUpperCase() + string.slice(1).replace(/-/g, ' ');
        }
    });
    
    // Global functions for appointment actions
    function viewAppointment(appointmentId) {
        // Show modal with loading state
        $('#appointmentLoading').show();
        $('#appointmentDetails').hide();
        $('#appointmentActionsDropdown').hide();
        $('#viewAppointmentModal').modal('show');
        
        // Fetch appointment details
        $.ajax({
            url: 'processes/counsellor_get_appointment.php',
            type: 'GET',
            data: {
                appointment_id: appointmentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const appointment = response.appointment;
                    
                    // Populate appointment details
                    $('#appointmentId').text(appointment.id);
                    
                    // Set status badge
                    let statusText = capitalizeFirstLetter(appointment.status);
                    $('#appointmentStatusBadge').text(statusText).attr('class', 'appointment-status ' + appointment.status);
                    
                    // Client info
                    $('#appointmentClientName').text(appointment.client_name);
                    $('#appointmentClientEmail').html(`<i class="fas fa-envelope"></i> ${appointment.client_email || 'Not provided'}`);
                    $('#appointmentClientPhone').html(`<i class="fas fa-phone"></i> ${appointment.client_phone || 'Not provided'}`);
                    $('#appointmentClientAvatar').attr('src', appointment.client_photo ? 'uploads/profiles/' + appointment.client_photo : 'assets/img/default-profile.png');
                    
                    // Appointment details
                    const appointmentDate = new Date(appointment.appointment_date);
                    const formattedDate = appointmentDate.toLocaleDateString('en-US', {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    
                    $('#appointmentDate').text(formattedDate);
                    $('#appointmentTime').text(`${formatTime(appointment.start_time)} - ${formatTime(appointment.end_time)}`);
                    
                    // Calculate duration
                    const startTime = new Date(`2000-01-01T${appointment.start_time}`);
                    const endTime = new Date(`2000-01-01T${appointment.end_time}`);
                    const durationMs = endTime - startTime;
                    const durationMinutes = Math.floor(durationMs / 60000);
                    $('#appointmentDuration').text(`${durationMinutes} minutes`);
                    
                    // Created date
                    const createdDate = new Date(appointment.created_at);
                    $('#appointmentCreated').text(createdDate.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }));
                    
                    // Notes
                    $('#appointmentNotes').text(appointment.notes || 'No notes available');
                    
                    // Show actions dropdown for scheduled appointments
                    if (appointment.status === 'scheduled') {
                        $('#appointmentActionsDropdown').show();
                        
                        // Set data attributes for action buttons
                        $('#startSessionBtn').data('appointment-id', appointment.id).data('client-id', appointment.client_id);
                        $('#markCompletedBtn').data('appointment-id', appointment.id);
                        $('#markNoShowBtn').data('appointment-id', appointment.id);
                        $('#addNotesBtn').data('appointment-id', appointment.id).data('notes', appointment.notes || '');
                        $('#cancelAppointmentBtn').data('appointment-id', appointment.id);
                    }
                    
                    // Hide loading, show content
                    $('#appointmentLoading').hide();
                    $('#appointmentDetails').fadeIn(300);
                } else {
                    console.error('Error loading appointment:', response.message);
                    $('#viewAppointmentModal').modal('hide');
                    showAlert('Error loading appointment details', 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                $('#viewAppointmentModal').modal('hide');
                showAlert('Error loading appointment details', 'danger');
            }
        });
    }
    
    function startSession(appointmentId, clientId) {
    
        alert(`Starting session for appointment #${appointmentId} with client #${clientId}`);
        
    }
    
    function viewClient(clientId) {
        // Load client profile in modal
        $.ajax({
            url: 'processes/counsellor_get_client.php',
            type: 'GET',
            data: {
                client_id: clientId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Redirect to client profile page or show modal
                    $('#dashboardDynamicContent').fadeOut(120, function() {
                        $('#dashboardDynamicContent').load('counsellor_clients.php', function() {
                            $(this).fadeIn(120, function() {
                                if (typeof viewClientProfile === 'function') {
                                    viewClientProfile(clientId);
                                }
                            });
                            
                            // Update active nav link
                            $('.sidebar .nav-link').removeClass('active');
                            $('.sidebar .nav-link[data-page="counsellor_clients.php"]').addClass('active');
                        });
                    });
                } else {
                    showAlert('Error loading client profile: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showAlert('Error loading client profile', 'danger');
                console.error('AJAX error:', error);
            }
        });
    }
    
    function markCompleted(appointmentId) {
        if (confirm('Are you sure you want to mark this appointment as completed?')) {
            $.ajax({
                url: 'processes/counsellor_update_appointment.php',
                type: 'POST',
                data: {
                    appointment_id: appointmentId,
                    action: 'complete'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Appointment marked as completed', 'success');
                        
                        // Reload page to update statistics and lists
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('Error updating appointment: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Error updating appointment', 'danger');
                    console.error('AJAX error:', error);
                }
            });
        }
    }
    
    function markNoShow(appointmentId) {
        if (confirm('Are you sure you want to mark this appointment as no-show?')) {
            $.ajax({
                url: 'processes/counsellor_update_appointment.php',
                type: 'POST',
                data: {
                    appointment_id: appointmentId,
                    action: 'no_show'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Appointment marked as no-show', 'success');
                        
                        // Reload page to update statistics and lists
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('Error updating appointment: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Error updating appointment', 'danger');
                    console.error('AJAX error:', error);
                }
            });
        }
    }
    
    function addNotes(appointmentId, notes) {
        // Show notes modal
        $('#notesAppointmentId').val(appointmentId);
        $('#notesTextarea').val(notes);
        $('#notesModal').modal('show');
    }
    
    function cancelAppointment(appointmentId) {
        // Show cancel modal
        $('#cancelAppointmentId').val(appointmentId);
        $('#cancelReason').val('');
        $('#notifyClient').prop('checked', true);
        $('#cancelModal').modal('show');
    }
    
    function formatTime(timeString) {
        if (!timeString) return '';
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }
    
    function capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1).replace(/-/g, ' ');
    }
    
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Insert alert at the top of the appointments container
        $('.appointments-container').prepend(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
    
    // Make the viewAppointment function available globally
    window.viewAppointment = viewAppointment;
</script>
</body>
</html>