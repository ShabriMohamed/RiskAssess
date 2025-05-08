<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}
require_once 'config.php';

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';

// Get user's appointments statistics
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_appointments,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
        SUM(CASE WHEN status = 'no-show' THEN 1 ELSE 0 END) as no_show_appointments
    FROM appointments
    WHERE client_id = $user_id
");

$stats = $stats_query->fetch_assoc();

// Get next upcoming appointment
$next_appointment_query = $conn->query("
    SELECT a.id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes,
           c.id as counsellor_id, u.name as counsellor_name, c.profile_photo
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE a.client_id = $user_id AND a.status = 'scheduled'
    AND (a.appointment_date > CURDATE() OR 
        (a.appointment_date = CURDATE() AND a.start_time > CURTIME()))
    ORDER BY a.appointment_date ASC, a.start_time ASC
    LIMIT 1
");

$next_appointment = $next_appointment_query->fetch_assoc();

// Get recent appointments
$recent_appointments_query = $conn->query("
    SELECT a.id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes,
           c.id as counsellor_id, u.name as counsellor_name, c.profile_photo
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE a.client_id = $user_id
    ORDER BY a.appointment_date DESC, a.start_time DESC
    LIMIT 5
");

$recent_appointments = [];
if ($recent_appointments_query) {
    while ($row = $recent_appointments_query->fetch_assoc()) {
        $recent_appointments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - RiskAssess</title>
    
    <!-- Core CSS Libraries (preloaded from parent) -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css"></noscript>
    
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --primary-light: #e6f0ff;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --border: #eee;
            --shadow-sm: 0 2px 10px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
        }
        
        .appointments-container {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-title i {
            color: var(--primary);
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-sm);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.info { border-left-color: var(--info); }
        
        .stat-title {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .stat-card.primary .stat-value { color: var(--primary); }
        .stat-card.success .stat-value { color: var(--success); }
        .stat-card.warning .stat-value { color: var(--warning); }
        .stat-card.danger .stat-value { color: var(--danger); }
        .stat-card.info .stat-value { color: var(--info); }
        
        /* Next Appointment Card */
        .next-appointment {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .next-appointment-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.25rem;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .next-appointment-body {
            padding: 1.5rem;
        }
        
        .appointment-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .appointment-counselor {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            min-width: 250px;
        }
        
        .counselor-photo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow-sm);
        }
        
        .counselor-info {
            flex: 1;
        }
        
        .counselor-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .appointment-datetime {
            flex: 1;
            min-width: 250px;
        }
        
        .datetime-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .datetime-item i {
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        .datetime-label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .appointment-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        /* Tab Navigation */
        .appointment-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        
        .appointment-tab {
            padding: 0.75rem 1.25rem;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
            color: var(--gray);
        }
        
        .appointment-tab:hover {
            color: var(--primary);
        }
        
        .appointment-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Appointment List */
        .appointment-list {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .appointment-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        
        .appointment-item:last-child {
            border-bottom: none;
        }
        
        .appointment-item:hover {
            background: var(--light);
        }
        
        .appointment-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            min-width: 100px;
            text-align: center;
        }
        
        .status-scheduled {
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }
        
        .status-completed {
            background-color: #e6f9e6;
            color: #1e7e34;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #b02a37;
        }
        
        .status-no-show {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .appointment-info {
            flex: 1;
            min-width: 250px;
        }
        
        .appointment-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .appointment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .appointment-meta i {
            color: var(--primary);
            margin-right: 0.25rem;
        }
        
        .appointment-actions-compact {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Calendar View */
        .calendar-container {
            height: 600px;
            background: white;
            border-radius: var(--radius-md);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .calendar-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius-md);
            z-index: 1;
        }
        
        .calendar-placeholder.hidden {
            display: none;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 3px solid rgba(0, 123, 255, 0.1);
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Modal Styling */
        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .appointment-detail-row {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .appointment-detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            color: var(--dark);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .empty-state p {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }
        
        /* Loading Overlay */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            backdrop-filter: blur(5px);
            transition: opacity 0.3s ease;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .appointment-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .appointment-status {
                align-self: flex-start;
            }
            
            .appointment-actions-compact {
                align-self: flex-end;
                margin-top: -2.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .appointment-tabs {
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 0.5rem;
            }
            
            .appointment-actions {
                flex-direction: column;
            }
            
            .appointment-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div id="loadingOverlay" style="display:none;">
        <div class="spinner"></div>
        <div id="loadingMessage">Processing...</div>
    </div>

    <div class="appointments-container">
        <div class="page-header">
            <h2 class="page-title"><i class="fas fa-calendar-check"></i> My Appointments</h2>
            <button class="btn btn-primary" id="bookNewAppointmentBtn">
                <i class="fas fa-plus-circle me-2"></i> Book New Appointment
            </button>
        </div>
        
        <!-- Statistics Row -->
        <div class="stats-row">
            <div class="stat-card primary">
                <div class="stat-title">Total Appointments</div>
                <div class="stat-value"><?php echo $stats['total_appointments']; ?></div>
                <div class="stat-description">All time</div>
            </div>
            <div class="stat-card success">
                <div class="stat-title">Upcoming</div>
                <div class="stat-value"><?php echo $stats['upcoming_appointments']; ?></div>
                <div class="stat-description">Scheduled sessions</div>
            </div>
            <div class="stat-card info">
                <div class="stat-title">Completed</div>
                <div class="stat-value"><?php echo $stats['completed_appointments']; ?></div>
                <div class="stat-description">Past sessions</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-title">Cancelled</div>
                <div class="stat-value"><?php echo $stats['cancelled_appointments']; ?></div>
                <div class="stat-description">Including no-shows</div>
            </div>
        </div>
        
        <!-- Next Appointment Card -->
        <?php if ($next_appointment): ?>
            <div class="next-appointment">
                <div class="next-appointment-header">
                    <i class="fas fa-calendar-day me-2"></i> Your Next Appointment
                </div>
                <div class="next-appointment-body">
                    <div class="appointment-details">
                        <div class="appointment-counselor">
                            <img src="<?php echo !empty($next_appointment['profile_photo']) ? 'uploads/counsellors/' . $next_appointment['profile_photo'] : 'assets/img/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($next_appointment['counsellor_name']); ?>" class="counselor-photo">
                            <div class="counselor-info">
                                <div class="counselor-name"><?php echo htmlspecialchars($next_appointment['counsellor_name']); ?></div>
                                <div class="counselor-role">Counselor</div>
                            </div>
                        </div>
                        <div class="appointment-datetime">
                            <div class="datetime-item">
                                <i class="fas fa-calendar"></i>
                                <span class="datetime-label">Date:</span>
                                <span>
                                    <?php 
                                        $date = new DateTime($next_appointment['appointment_date']);
                                        echo $date->format('l, F j, Y'); 
                                    ?>
                                </span>
                            </div>
                            <div class="datetime-item">
                                <i class="fas fa-clock"></i>
                                <span class="datetime-label">Time:</span>
                                <span>
                                    <?php 
                                        $start = new DateTime($next_appointment['start_time']);
                                        $end = new DateTime($next_appointment['end_time']);
                                        echo $start->format('g:i A') . ' - ' . $end->format('g:i A'); 
                                    ?>
                                </span>
                            </div>
                            <div class="datetime-item">
                                <i class="fas fa-hourglass-half"></i>
                                <span class="datetime-label">Countdown:</span>
                                <span id="appointmentCountdown" data-date="<?php echo $next_appointment['appointment_date']; ?>" data-time="<?php echo $next_appointment['start_time']; ?>">
                                    Calculating...
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="appointment-actions">
                        <button class="btn btn-primary view-appointment-btn" data-id="<?php echo $next_appointment['id']; ?>">
                            <i class="fas fa-eye me-2"></i> View Details
                        </button>
                        <button class="btn btn-outline-danger cancel-appointment-btn" data-id="<?php echo $next_appointment['id']; ?>">
                            <i class="fas fa-times-circle me-2"></i> Cancel Appointment
                        </button>
                        <button class="btn btn-outline-secondary reschedule-appointment-btn" data-id="<?php echo $next_appointment['id']; ?>">
                            <i class="fas fa-sync-alt me-2"></i> Reschedule
                        </button>
                        <button class="btn btn-outline-primary add-to-calendar-btn" 
                            data-title="Counseling with <?php echo htmlspecialchars($next_appointment['counsellor_name']); ?>"
                            data-start="<?php echo $next_appointment['appointment_date'] . 'T' . $next_appointment['start_time']; ?>"
                            data-end="<?php echo $next_appointment['appointment_date'] . 'T' . $next_appointment['end_time']; ?>"
                            data-location="RiskAssess Counseling Center">
                            <i class="fas fa-calendar-plus me-2"></i> Add to Calendar
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state mb-4">
                <i class="fas fa-calendar-alt"></i>
                <h3>No Upcoming Appointments</h3>
                <p>You don't have any scheduled appointments. Book a session with one of our counselors.</p>
                <button class="btn btn-primary" id="emptyStateBookBtn">
                    <i class="fas fa-plus-circle me-2"></i> Book an Appointment
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Appointment Tabs -->
        <div class="appointment-tabs">
            <div class="appointment-tab active" data-view="list">
                <i class="fas fa-list me-2"></i> List View
            </div>
            <div class="appointment-tab" data-view="calendar">
                <i class="fas fa-calendar-alt me-2"></i> Calendar View
            </div>
            <div class="appointment-tab" data-view="history">
                <i class="fas fa-history me-2"></i> Appointment History
            </div>
        </div>
        
        <!-- List View -->
        <div class="appointment-view" id="listView">
            <?php if (empty($recent_appointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Appointments Found</h3>
                    <p>You haven't scheduled any appointments yet.</p>
                </div>
            <?php else: ?>
                <div class="appointment-list">
                    <?php foreach ($recent_appointments as $appointment): ?>
                        <?php
                            $statusClass = '';
                            switch($appointment['status']) {
                                case 'scheduled': $statusClass = 'status-scheduled'; break;
                                case 'completed': $statusClass = 'status-completed'; break;
                                case 'cancelled': $statusClass = 'status-cancelled'; break;
                                case 'no-show': $statusClass = 'status-no-show'; break;
                            }
                            
                            $date = new DateTime($appointment['appointment_date']);
                            $start = new DateTime($appointment['start_time']);
                            $end = new DateTime($appointment['end_time']);
                            
                            $isPast = ($date->format('Y-m-d') < date('Y-m-d')) || 
                                     ($date->format('Y-m-d') == date('Y-m-d') && $end->format('H:i:s') < date('H:i:s'));
                        ?>
                        <div class="appointment-item">
                            <div class="appointment-status <?php echo $statusClass; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </div>
                            <div class="appointment-info">
                                <div class="appointment-title">Session with <?php echo htmlspecialchars($appointment['counsellor_name']); ?></div>
                                <div class="appointment-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo $date->format('M j, Y'); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo $start->format('g:i A') . ' - ' . $end->format('g:i A'); ?></span>
                                </div>
                            </div>
                            <div class="appointment-actions-compact">
                                <button class="btn btn-sm btn-outline-primary view-appointment-btn" data-id="<?php echo $appointment['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($appointment['status'] === 'scheduled' && !$isPast): ?>
                                    <button class="btn btn-sm btn-outline-danger cancel-appointment-btn" data-id="<?php echo $appointment['id']; ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary reschedule-appointment-btn" data-id="<?php echo $appointment['id']; ?>">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <button class="btn btn-outline-primary" id="loadMoreBtn">
                        <i class="fas fa-chevron-down me-2"></i> Load More
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Calendar View -->
        <div class="appointment-view" id="calendarView" style="display:none;">
            <div class="calendar-container">
                <div class="calendar-placeholder">
                    <div class="spinner"></div>
                    <p class="mt-3">Loading calendar...</p>
                </div>
                <div id="appointmentCalendar"></div>
            </div>
        </div>
        
        <!-- History View -->
        <div class="appointment-view" id="historyView" style="display:none;">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="historySearch" placeholder="Search appointments...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 justify-content-md-end mt-2 mt-md-0">
                        <select class="form-select" id="historyStatusFilter">
                            <option value="">All Statuses</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no-show">No-Show</option>
                        </select>
                        <select class="form-select" id="historyDateFilter">
                            <option value="">All Time</option>
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 3 Months</option>
                            <option value="180">Last 6 Months</option>
                            <option value="365">Last Year</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div id="historyList" class="appointment-list">
                <div class="text-center py-4">
                    <div class="spinner"></div>
                    <p class="mt-3">Loading appointment history...</p>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <button class="btn btn-outline-primary" id="loadMoreHistoryBtn">
                    <i class="fas fa-chevron-down me-2"></i> Load More
                </button>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div class="modal fade" id="appointmentDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="text-center">
                                <img id="modalCounselorPhoto" src="" alt="Counselor" class="counselor-photo mb-2" style="width:100px;height:100px;">
                                <h5 id="modalCounselorName" class="mb-1"></h5>
                                <div class="text-muted">Counselor</div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="appointment-detail-row">
                                <div class="detail-label">Appointment ID</div>
                                <div class="detail-value" id="modalAppointmentId"></div>
                            </div>
                            <div class="appointment-detail-row">
                                <div class="detail-label">Date & Time</div>
                                <div class="detail-value" id="modalDateTime"></div>
                            </div>
                            <div class="appointment-detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span id="modalStatus" class="appointment-status"></span>
                                </div>
                            </div>
                            <div class="appointment-detail-row">
                                <div class="detail-label">Notes</div>
                                <div class="detail-value" id="modalNotes"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="modalFooter">
                    <!-- Dynamic buttons will be added here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div class="modal fade" id="cancelAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cancelAppointmentId">
                    <p>Are you sure you want to cancel this appointment?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Cancellations made less than 24 hours before the appointment may be subject to a cancellation fee.
                    </div>
                    <div class="mb-3">
                        <label for="cancellationReason" class="form-label">Reason for cancellation (optional)</label>
                        <textarea id="cancellationReason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelBtn">
                        <i class="fas fa-times-circle me-2"></i> Cancel Appointment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    
    <script>
    $(document).ready(function() {
        console.log("My Appointments page loaded");
        
        // Global variables
        let calendar = null;
        let historyPage = 1;
        let historyHasMore = true;
        let historyFilters = {
            search: '',
            status: '',
            date: ''
        };
        
        // Initialize the page
        initPage();
        
        function initPage() {
            // Initialize countdown for next appointment
            initCountdown();
            
            // Attach event handlers
            attachEventHandlers();
        }
        
        // Initialize countdown timer
        function initCountdown() {
            const countdownEl = $('#appointmentCountdown');
            if (countdownEl.length === 0) return;
            
            const appointmentDate = countdownEl.data('date');
            const appointmentTime = countdownEl.data('time');
            
            if (!appointmentDate || !appointmentTime) return;
            
            const appointmentDateTime = new Date(`${appointmentDate}T${appointmentTime}`);
            
            function updateCountdown() {
                const now = new Date();
                const diff = appointmentDateTime - now;
                
                if (diff <= 0) {
                    countdownEl.html('<span class="text-success">Happening now!</span>');
                    return;
                }
                
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                
                let countdownText = '';
                
                if (days > 0) {
                    countdownText += `${days} day${days > 1 ? 's' : ''} `;
                }
                
                countdownText += `${hours} hour${hours !== 1 ? 's' : ''} ${minutes} minute${minutes !== 1 ? 's' : ''}`;
                
                countdownEl.text(countdownText);
            }
            
            // Update immediately and then every minute
            updateCountdown();
            setInterval(updateCountdown, 60000);
        }
        
        // Attach event handlers
        function attachEventHandlers() {
            // Tab navigation
            $('.appointment-tab').click(function() {
                const view = $(this).data('view');
                $('.appointment-tab').removeClass('active');
                $(this).addClass('active');
                
                $('.appointment-view').hide();
                
                if (view === 'list') {
                    $('#listView').show();
                } else if (view === 'calendar') {
                    $('#calendarView').show();
                    initializeCalendar();
                } else if (view === 'history') {
                    $('#historyView').show();
                    loadAppointmentHistory();
                }
            });
            
            // Book new appointment buttons
            $('#bookNewAppointmentBtn, #emptyStateBookBtn').click(function() {
                navigateToBookAppointment();
            });
            
            // View appointment details
            $(document).on('click', '.view-appointment-btn', function() {
                const appointmentId = $(this).data('id');
                loadAppointmentDetails(appointmentId);
            });
            
            // Cancel appointment
            $(document).on('click', '.cancel-appointment-btn', function() {
                const appointmentId = $(this).data('id');
                $('#cancelAppointmentId').val(appointmentId);
                $('#cancellationReason').val('');
                $('#cancelAppointmentModal').modal('show');
            });
            
            // Confirm cancel appointment
            $('#confirmCancelBtn').click(function() {
                const appointmentId = $('#cancelAppointmentId').val();
                const reason = $('#cancellationReason').val();
                
                if (!appointmentId) return;
                
                cancelAppointment(appointmentId, reason);
            });
            
            // Reschedule appointment
            $(document).on('click', '.reschedule-appointment-btn', function() {
                const appointmentId = $(this).data('id');
                // Implement reschedule functionality
                alert('Reschedule functionality will be implemented in the next phase.');
            });
            
            // Add to calendar
            $(document).on('click', '.add-to-calendar-btn', function() {
                const title = $(this).data('title');
                const start = $(this).data('start');
                const end = $(this).data('end');
                const location = $(this).data('location');
                
                addToCalendar(title, start, end, location);
            });
            
            // Load more appointments
            $('#loadMoreBtn').click(function() {
                // Implement load more functionality
                alert('Load more functionality will be implemented in the next phase.');
            });
            
            // History filters
            $('#historySearch, #historyStatusFilter, #historyDateFilter').on('input change', function() {
                historyFilters.search = $('#historySearch').val();
                historyFilters.status = $('#historyStatusFilter').val();
                historyFilters.date = $('#historyDateFilter').val();
                
                historyPage = 1;
                loadAppointmentHistory(true);
            });
            
            // Load more history
            $('#loadMoreHistoryBtn').click(function() {
                historyPage++;
                loadAppointmentHistory(false);
            });
        }
        
        // Initialize calendar
        function initializeCalendar() {
            if (calendar) {
                calendar.refetchEvents();
                $('.calendar-placeholder').addClass('hidden');
                return;
            }
            
            const calendarEl = document.getElementById('appointmentCalendar');
            if (!calendarEl) return;
            
            try {
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    height: '100%',
                    events: function(info, successCallback, failureCallback) {
                        $.ajax({
                            url: 'processes/user_appointments_events.php',
                            type: 'GET',
                            data: {
                                start: info.startStr,
                                end: info.endStr
                            },
                            success: function(result) {
                                successCallback(result);
                                $('.calendar-placeholder').addClass('hidden');
                            },
                            error: function(xhr, status, error) {
                                console.error("Error loading calendar events:", error);
                                failureCallback({ message: 'Error loading events' });
                                showAlert('Error loading calendar events. Please try again.', 'danger');
                            }
                        });
                    },
                    eventClick: function(info) {
                        loadAppointmentDetails(info.event.id);
                    },
                    eventClassNames: function(arg) {
                        return [`appointment-status-${arg.event.extendedProps.status}`];
                    }
                });
                
                calendar.render();
            } catch (error) {
                console.error("Error initializing calendar:", error);
                showAlert('Error initializing calendar. Please refresh the page.', 'danger');
            }
        }
        
        // Load appointment history
        function loadAppointmentHistory(reset = false) {
            if (reset) {
                $('#historyList').html('<div class="text-center py-4"><div class="spinner"></div><p class="mt-3">Loading appointment history...</p></div>');
                historyHasMore = true;
            }
            
            if (!historyHasMore && !reset) {
                return;
            }
            
            $.ajax({
                url: 'processes/user_appointments_history.php',
                type: 'GET',
                data: {
                    page: historyPage,
                    search: historyFilters.search,
                    status: historyFilters.status,
                    date: historyFilters.date
                },
                dataType: 'json',
                success: function(response) {
                    if (reset) {
                        $('#historyList').empty();
                    }
                    
                    if (response.appointments.length === 0 && historyPage === 1) {
                        $('#historyList').html(`
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3>No Appointment History</h3>
                                <p>You don't have any past appointments matching your filters.</p>
                            </div>
                        `);
                        $('#loadMoreHistoryBtn').hide();
                        return;
                    }
                    
                    response.appointments.forEach(function(appointment) {
                        const statusClass = getStatusClass(appointment.status);
                        const date = new Date(appointment.appointment_date);
                        const start = new Date(`${appointment.appointment_date}T${appointment.start_time}`);
                        const end = new Date(`${appointment.appointment_date}T${appointment.end_time}`);
                        
                        const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        const formattedStart = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                        const formattedEnd = end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                        
                        $('#historyList').append(`
                            <div class="appointment-item">
                                <div class="appointment-status ${statusClass}">
                                    ${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}
                                </div>
                                <div class="appointment-info">
                                    <div class="appointment-title">Session with ${appointment.counsellor_name}</div>
                                    <div class="appointment-meta">
                                        <span><i class="fas fa-calendar"></i> ${formattedDate}</span>
                                        <span><i class="fas fa-clock"></i> ${formattedStart} - ${formattedEnd}</span>
                                    </div>
                                </div>
                                <div class="appointment-actions-compact">
                                    <button class="btn btn-sm btn-outline-primary view-appointment-btn" data-id="${appointment.id}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        `);
                    });
                    
                    historyHasMore = response.has_more;
                    
                    if (historyHasMore) {
                        $('#loadMoreHistoryBtn').show();
                    } else {
                        $('#loadMoreHistoryBtn').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error loading appointment history:", error);
                    showAlert('Error loading appointment history. Please try again.', 'danger');
                }
            });
        }
        
        // Load appointment details
        function loadAppointmentDetails(appointmentId) {
            showLoading('Loading appointment details...');
            
            $.ajax({
                url: 'processes/user_appointments_get.php',
                type: 'GET',
                data: { id: appointmentId },
                dataType: 'json',
                success: function(appointment) {
                    hideLoading();
                    
                    if (!appointment) {
                        showAlert('Appointment not found', 'danger');
                        return;
                    }
                    
                    // Format date and time
                    const date = new Date(appointment.appointment_date);
                    const start = new Date(`${appointment.appointment_date}T${appointment.start_time}`);
                    const end = new Date(`${appointment.appointment_date}T${appointment.end_time}`);
                    
                    const formattedDate = date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                    const formattedStart = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    const formattedEnd = end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    
                    // Update modal content
                    $('#modalCounselorPhoto').attr('src', appointment.profile_photo ? `uploads/counsellors/${appointment.profile_photo}` : 'assets/img/default-profile.png');
                    $('#modalCounselorName').text(appointment.counsellor_name);
                    $('#modalAppointmentId').text(`#${appointment.id}`);
                    $('#modalDateTime').text(`${formattedDate} at ${formattedStart} - ${formattedEnd}`);
                    $('#modalStatus').text(appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1));
                    $('#modalStatus').attr('class', `appointment-status ${getStatusClass(appointment.status)}`);
                    $('#modalNotes').text(appointment.notes || 'No notes provided');
                    
                    // Update modal footer buttons
                    let footerButtons = `
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    `;
                    
                    const isPast = (date < new Date()) || 
                                  (date.toDateString() === new Date().toDateString() && end < new Date());
                    
                    if (appointment.status === 'scheduled' && !isPast) {
                        footerButtons += `
                            <button type="button" class="btn btn-outline-danger cancel-appointment-btn" data-id="${appointment.id}" data-bs-dismiss="modal">
                                <i class="fas fa-times-circle me-2"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-outline-secondary reschedule-appointment-btn" data-id="${appointment.id}" data-bs-dismiss="modal">
                                <i class="fas fa-sync-alt me-2"></i> Reschedule
                            </button>
                            <button type="button" class="btn btn-primary add-to-calendar-btn" 
                                data-title="Counseling with ${appointment.counsellor_name}"
                                data-start="${appointment.appointment_date}T${appointment.start_time}"
                                data-end="${appointment.appointment_date}T${appointment.end_time}"
                                data-location="RiskAssess Counseling Center">
                                <i class="fas fa-calendar-plus me-2"></i> Add to Calendar
                            </button>
                        `;
                    }
                    
                    $('#modalFooter').html(footerButtons);
                    
                    // Show modal
                    $('#appointmentDetailsModal').modal('show');
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error("Error loading appointment details:", error);
                    showAlert('Error loading appointment details. Please try again.', 'danger');
                }
            });
        }
        
        // Cancel appointment
        function cancelAppointment(appointmentId, reason) {
            showLoading('Cancelling appointment...');
            
            $.ajax({
                url: 'processes/user_appointments_cancel.php',
                type: 'POST',
                data: {
                    id: appointmentId,
                    reason: reason
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.success) {
                        $('#cancelAppointmentModal').modal('hide');
                        showAlert(response.message, 'success');
                        
                        // Refresh the page to show updated appointment status
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(response.message || 'Error cancelling appointment', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error("Error cancelling appointment:", error);
                    showAlert('Server error. Please try again later.', 'danger');
                }
            });
        }
        
        // Add to calendar
        function addToCalendar(title, start, end, location) {
            // Format dates for ics file
            const formatDate = (dateString) => {
                const date = new Date(dateString);
                return date.toISOString().replace(/-|:|\.\d+/g, '');
            };
            
            const startFormatted = formatDate(start);
            const endFormatted = formatDate(end);
            
            // Create ics content
            const icsContent = 
                'BEGIN:VCALENDAR\n' +
                'VERSION:2.0\n' +
                'CALSCALE:GREGORIAN\n' +
                'PRODID:-//RiskAssess//Counseling Appointments//EN\n' +
                'METHOD:PUBLISH\n' +
                'BEGIN:VEVENT\n' +
                'SUMMARY:' + title + '\n' +
                'DTSTART:' + startFormatted + '\n' +
                'DTEND:' + endFormatted + '\n' +
                'LOCATION:' + location + '\n' +
                'DESCRIPTION:Your counseling appointment with RiskAssess\n' +
                'STATUS:CONFIRMED\n' +
                'SEQUENCE:0\n' +
                'BEGIN:VALARM\n' +
                'TRIGGER:-PT30M\n' +
                'ACTION:DISPLAY\n' +
                'DESCRIPTION:Reminder\n' +
                'END:VALARM\n' +
                'END:VEVENT\n' +
                'END:VCALENDAR';
            
            // Create download link
            const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'counseling_appointment.ics');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Navigate to book appointment page
        function navigateToBookAppointment() {
            $('.sidebar .nav-link').removeClass('active');
            $('.sidebar .nav-link[data-page="book_appointment.php"]').addClass('active');
            $('#dashboardDynamicContent').fadeOut(120, function() {
                $('#dashboardDynamicContent').load('book_appointment.php', function() {
                    $(this).fadeIn(120);
                });
            });
        }
        
        // Helper functions
        function getStatusClass(status) {
            switch(status) {
                case 'scheduled': return 'status-scheduled';
                case 'completed': return 'status-completed';
                case 'cancelled': return 'status-cancelled';
                case 'no-show': return 'status-no-show';
                default: return '';
            }
        }
        
        function showLoading(message) {
            $('#loadingMessage').text(message || 'Loading...');
            $('#loadingOverlay').fadeIn(200);
        }
        
        function hideLoading() {
            $('#loadingOverlay').fadeOut(200);
        }
        
        function showAlert(message, type) {
            // Remove any existing alerts
            $('.alert-floating').remove();
            
            const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3 alert-floating" role="alert" style="z-index:9999;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            $('body').append(alertHtml);
            setTimeout(() => { $('.alert-floating').alert('close'); }, 3500);
        }
    });
    </script>
</body>
</html>
