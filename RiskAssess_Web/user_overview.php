<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}
require_once 'config.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');

// Get user information
$stmt = $conn->prepare("
    SELECT u.*, up.date_of_birth, up.profile_photo
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get upcoming appointments
$stmt = $conn->prepare("
    SELECT a.*, c.id as counsellor_id, u.name as counsellor_name, c.profile_photo
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE a.client_id = ? AND a.status = 'scheduled'
    AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.start_time >= ?))
    ORDER BY a.appointment_date ASC, a.start_time ASC
    LIMIT 3
");
$stmt->bind_param("isss", $user_id, $today, $today, $current_time);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get past appointments count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM appointments
    WHERE client_id = ? AND (status = 'completed' OR (status = 'scheduled' AND (appointment_date < ? OR (appointment_date = ? AND end_time < ?))))
");
$stmt->bind_param("isss", $user_id, $today, $today, $current_time);
$stmt->execute();
$past_appointments_count = $stmt->get_result()->fetch_assoc()['count'];

// Get cancelled appointments count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM appointments
    WHERE client_id = ? AND status = 'cancelled'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cancelled_appointments_count = $stmt->get_result()->fetch_assoc()['count'];

// Get unread messages count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM messages
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_messages_count = $stmt->get_result()->fetch_assoc()['count'];

// Get recent activity
$stmt = $conn->prepare("
    SELECT action, created_at, details
    FROM audit_log
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="overview-container">
    <!-- Stats Cards Row -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Upcoming</div>
                        <div class="stat-card-value"><?php echo count($upcoming_appointments); ?></div>
                        <div class="stat-card-desc">Appointments</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-success">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Past</div>
                        <div class="stat-card-value"><?php echo $past_appointments_count; ?></div>
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
                        <div class="stat-card-value"><?php echo $cancelled_appointments_count; ?></div>
                        <div class="stat-card-desc">Appointments</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon bg-info">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-title">Unread</div>
                        <div class="stat-card-value"><?php echo $unread_messages_count; ?></div>
                        <div class="stat-card-desc">Messages</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Upcoming Appointments Column -->
        <div class="col-lg-8 mb-4">
            <div class="content-card">
                <div class="content-card-header">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Upcoming Appointments</h5>
                    <a href="#" class="btn btn-sm btn-primary" onclick="loadPage('book_appointment.php')">Book New</a>
                </div>
                <div class="content-card-body">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="empty-state">
                            <img src="assets/img/empty-calendar.svg" alt="No appointments" class="empty-state-img">
                            <h4>No upcoming appointments</h4>
                            <p>You don't have any scheduled appointments. Book a session with one of our counsellors.</p>
                            <button class="btn btn-primary" onclick="loadPage('book_appointment.php')">
                                <i class="fas fa-plus-circle me-2"></i>Book Appointment
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-left">
                                    <div class="appointment-date">
                                        <div class="appointment-day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></div>
                                        <div class="appointment-month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></div>
                                    </div>
                                    <div class="appointment-time">
                                        <i class="far fa-clock me-1"></i>
                                        <?php 
                                            echo date('g:i A', strtotime($appointment['start_time'])) . ' - ' . 
                                                 date('g:i A', strtotime($appointment['end_time'])); 
                                        ?>
                                    </div>
                                </div>
                                <div class="appointment-center">
                                    <div class="appointment-counsellor">
                                        <img src="<?php echo !empty($appointment['profile_photo']) ? 'uploads/counsellors/' . $appointment['profile_photo'] : 'assets/img/default-profile.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($appointment['counsellor_name']); ?>" 
                                             class="appointment-avatar">
                                        <div>
                                            <div class="appointment-name"><?php echo htmlspecialchars($appointment['counsellor_name']); ?></div>
                                            <div class="appointment-status">
                                                <span class="badge bg-success">Confirmed</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="appointment-right">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="startChat(<?php echo $appointment['counsellor_id']; ?>)">
                                        <i class="fas fa-comment-dots"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="#" class="btn btn-link" onclick="loadPage('user_appointments.php')">View All Appointments</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        

            <!-- Recent Activity Card -->
            <div class="content-card">
                <div class="content-card-header">
                    <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                </div>
                <div class="content-card-body">
                    <?php if (empty($recent_activity)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-history fa-2x mb-2"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                            $icon = 'fa-circle';
                                            if (strpos($activity['action'], 'appointment') !== false) {
                                                $icon = 'fa-calendar-check';
                                            } elseif (strpos($activity['action'], 'profile') !== false || strpos($activity['action'], 'information') !== false) {
                                                $icon = 'fa-user-edit';
                                            } elseif (strpos($activity['action'], 'message') !== false) {
                                                $icon = 'fa-envelope';
                                            } elseif (strpos($activity['action'], 'password') !== false) {
                                                $icon = 'fa-key';
                                            }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text"><?php echo htmlspecialchars($activity['action']); ?></div>
                                        <div class="activity-time"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Dashboard Overview Styles */
    .overview-container {
        animation: fadeIn 0.5s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Stat Cards */
    .stat-card {
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        overflow: hidden;
        height: 100%;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
    
    .stat-card-body {
        padding: 1.25rem;
        display: flex;
        align-items: center;
    }
    
    .stat-card-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        color: white;
        font-size: 1.5rem;
    }
    
    .bg-primary { background-color: #007bff; }
    .bg-success { background-color: #28a745; }
    .bg-warning { background-color: #ffc107; }
    .bg-info { background-color: #17a2b8; }
    
    .stat-card-info {
        flex: 1;
    }
    
    .stat-card-title {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    
    .stat-card-value {
        font-size: 1.75rem;
        font-weight: 600;
        color: #343a40;
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    
    .stat-card-desc {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    /* Content Cards */
    .content-card {
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        overflow: hidden;
        height: 100%;
    }
    
    .content-card-header {
        padding: 1.25rem;
        border-bottom: 1px solid #f1f1f1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .content-card-header h5 {
        margin: 0;
        font-weight: 600;
        color: #343a40;
        display: flex;
        align-items: center;
    }
    
    .content-card-header h5 i {
        color: #007bff;
    }
    
    .content-card-body {
        padding: 1.25rem;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 2rem 1rem;
    }
    
    .empty-state-img {
        width: 120px;
        margin-bottom: 1rem;
        opacity: 0.7;
    }
    
    .empty-state h4 {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #343a40;
    }
    
    .empty-state p {
        color: #6c757d;
        margin-bottom: 1.5rem;
    }
    
    /* Appointment Cards */
    .appointment-card {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-radius: 10px;
        background-color: #f8f9fa;
        margin-bottom: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .appointment-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    
    .appointment-left {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-right: 1rem;
        min-width: 80px;
    }
    
    .appointment-date {
        text-align: center;
        background-color: #007bff;
        color: white;
        padding: 0.5rem;
        border-radius: 8px;
        width: 60px;
        margin-bottom: 0.5rem;
    }
    
    .appointment-day {
        font-size: 1.25rem;
        font-weight: 600;
        line-height: 1;
    }
    
    .appointment-month {
        font-size: 0.85rem;
        text-transform: uppercase;
    }
    
    .appointment-time {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .appointment-center {
        flex: 1;
    }
    
    .appointment-counsellor {
        display: flex;
        align-items: center;
    }
    
    .appointment-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 0.75rem;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .appointment-name {
        font-weight: 500;
        margin-bottom: 0.25rem;
        color: #343a40;
    }
    
    .appointment-status {
        font-size: 0.85rem;
    }
    
    .appointment-right {
        display: flex;
    }
    
    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background-color: #f8f9fa;
        border-radius: 10px;
        text-decoration: none;
        color: #343a40;
        transition: transform 0.2s, background-color 0.2s;
        position: relative;
    }
    
    .quick-action-btn:hover {
        transform: translateY(-3px);
        background-color: #e9ecef;
        color: #007bff;
    }
    
    .quick-action-btn i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: #007bff;
    }
    
    .quick-action-btn .badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
    }
    
    /* Activity Timeline */
    .activity-timeline {
        position: relative;
    }
    
    .activity-timeline::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 15px;
        width: 2px;
        background-color: #e9ecef;
    }
    
    .activity-item {
        display: flex;
        margin-bottom: 1.25rem;
        position: relative;
    }
    
    .activity-item:last-child {
        margin-bottom: 0;
    }
    
    .activity-icon {
        width: 32px;
        height: 32px;
        background-color: #007bff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.85rem;
        margin-right: 1rem;
        z-index: 1;
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-text {
        margin-bottom: 0.25rem;
        color: #343a40;
    }
    
    .activity-time {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stat-card-body {
            padding: 1rem;
        }
        
        .appointment-card {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .appointment-left {
            flex-direction: row;
            margin-right: 0;
            margin-bottom: 0.75rem;
            width: 100%;
            justify-content: flex-start;
        }
        
        .appointment-date {
            margin-bottom: 0;
            margin-right: 1rem;
        }
        
        .appointment-center {
            margin-bottom: 0.75rem;
            width: 100%;
        }
        
        .appointment-right {
            width: 100%;
            justify-content: flex-end;
        }
    }
    
    @media (max-width: 576px) {
        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    // Function to load pages
    function loadPage(page) {
        $('#dashboardDynamicContent').fadeOut(120, function() {
            $('#dashboardDynamicContent').load(page, function() {
                $(this).fadeIn(120);
                
                // Update active nav link
                $('.sidebar .nav-link').removeClass('active');
                $('.sidebar .nav-link[data-page="' + page + '"]').addClass('active');
            });
        });
    }
    
    // Function to start chat with counselor
    function startChat(counselorId) {
        loadPage('messages.php');
        // Add code to open chat with specific counselor
        setTimeout(function() {
            if (typeof selectCounselor === 'function') {
                selectCounselor(counselorId);
            }
        }, 500);
    }
    
    // Function to cancel appointment
    function cancelAppointment(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            $.ajax({
                url: 'processes/cancel_appointment.php',
                type: 'POST',
                data: {
                    appointment_id: appointmentId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Appointment cancelled successfully');
                        loadPage('user_overview.php');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    }
</script>
</body>
</html>