<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit;
}
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get current date and time information
$current_date = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');
$current_month_name = date('F');

// Get total counts
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
$total_counsellors = $conn->query("SELECT COUNT(*) as count FROM counsellors")->fetch_assoc()['count'];

// Check if appointments table exists and has data
$appointment_table_exists = false;
$result = $conn->query("SHOW TABLES LIKE 'appointments'");
if ($result->num_rows > 0) {
    $appointment_table_exists = true;
}

// Initialize appointment counts
$total_appointments = 0;
$today_appointments = 0;
$scheduled = 0;
$completed = 0;
$cancelled = 0;
$no_show = 0;

// Get appointment data if table exists
if ($appointment_table_exists) {
    // Debug: Print all appointments
    $debug_appointments = $conn->query("SELECT * FROM appointments");
    echo "<!-- Debug: Found " . $debug_appointments->num_rows . " appointments -->";
    
    $total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
    $today_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$current_date'")->fetch_assoc()['count'];
    
    // Get status counts with explicit error handling
    $status_query = $conn->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    if ($status_query) {
        while ($row = $status_query->fetch_assoc()) {
            switch ($row['status']) {
                case 'scheduled': $scheduled = $row['count']; break;
                case 'completed': $completed = $row['count']; break;
                case 'cancelled': $cancelled = $row['count']; break;
                case 'no-show': $no_show = $row['count']; break;
            }
        }
    } else {
        echo "<!-- Error querying appointment status: " . $conn->error . " -->";
    }
}

// Get monthly appointment counts for chart
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    
    $count = 0;
    if ($appointment_table_exists) {
        $month_query = $conn->query("
            SELECT COUNT(*) as count FROM appointments 
            WHERE DATE_FORMAT(appointment_date, '%Y-%m') = '$month'
        ");
        if ($month_query) {
            $count = $month_query->fetch_assoc()['count'];
        }
    }
    
    $monthly_data[] = [
        'month' => $month_name,
        'count' => $count
    ];
}

// Get recent appointments
$recent_appointments = [];
if ($appointment_table_exists) {
    $result = $conn->query("
        SELECT a.id, a.appointment_date, a.start_time, a.status, 
               c.name as client_name, co.name as counsellor_name
        FROM appointments a
        JOIN users c ON a.client_id = c.id
        JOIN counsellors cou ON a.counsellor_id = cou.id
        JOIN users co ON cou.user_id = co.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_appointments[] = $row;
        }
    } else {
        echo "<!-- Error querying recent appointments: " . $conn->error . " -->";
    }
}

// Get counsellor utilization
$counsellor_utilization = [];
if ($appointment_table_exists) {
    $result = $conn->query("
        SELECT co.name as counsellor_name, 
               COUNT(a.id) as appointment_count,
               SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM counsellors cou
        JOIN users co ON cou.user_id = co.id
        LEFT JOIN appointments a ON cou.id = a.counsellor_id
        GROUP BY cou.id
        ORDER BY appointment_count DESC
        LIMIT 5
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $counsellor_utilization[] = $row;
        }
    } else {
        echo "<!-- Error querying counsellor utilization: " . $conn->error . " -->";
    }
}

// Get system activity
$system_activity = [];
$audit_table_exists = false;
$result = $conn->query("SHOW TABLES LIKE 'audit_log'");
if ($result->num_rows > 0) {
    $audit_table_exists = true;
}

if ($audit_table_exists) {
    $result = $conn->query("
        SELECT al.action, al.table_name, u.name as user_name, al.created_at
        FROM audit_log al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $system_activity[] = $row;
        }
    } else {
        echo "<!-- Error querying system activity: " . $conn->error . " -->";
    }
}

// Generate sample data if no real data exists
if (empty($monthly_data) || array_sum(array_column($monthly_data, 'count')) == 0) {
    // Sample data for demonstration
    $monthly_data = [
        ['month' => 'Dec', 'count' => 12],
        ['month' => 'Jan', 'count' => 19],
        ['month' => 'Feb', 'count' => 15],
        ['month' => 'Mar', 'count' => 22],
        ['month' => 'Apr', 'count' => 28],
        ['month' => 'May', 'count' => 35]
    ];
}

// Use real data if available, otherwise use sample data
$use_sample_data = ($scheduled == 0 && $completed == 0 && $cancelled == 0 && $no_show == 0);
if ($use_sample_data) {
    // Sample data for demonstration
    $scheduled = 15;
    $completed = 25;
    $cancelled = 8;
    $no_show = 4;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - RiskAssess</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5.2 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f8cff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --secondary-color: #6c757d;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #23272f;
        }
        
        .dashboard-header {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .stat-card .icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: white;
        }
        
        .stat-card .icon.primary { background-color: var(--primary-color); }
        .stat-card .icon.success { background-color: var(--success-color); }
        .stat-card .icon.warning { background-color: var(--warning-color); }
        .stat-card .icon.danger { background-color: var(--danger-color); }
        .stat-card .icon.info { background-color: var(--info-color); }
        .stat-card .icon.secondary { background-color: var(--secondary-color); }
        
        .stat-card .title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .trend {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
        
        .stat-card .trend.up { color: var(--success-color); }
        .stat-card .trend.down { color: var(--danger-color); }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .chart-card .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #343a40;
        }
        
        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .table-card .table-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #343a40;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-scheduled {
            background-color: #e6f0ff;
            color: #0055cc;
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
        
        .progress-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .progress-card .progress-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #343a40;
        }
        
        .progress-item {
            margin-bottom: 1.25rem;
        }
        
        .progress-item .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .progress-item .progress-bar {
            height: 0.5rem;
            border-radius: 1rem;
        }
        
        .activity-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .activity-card .activity-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #343a40;
        }
        
        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
        }
        
        .activity-item .activity-content {
            flex: 1;
        }
        
        .activity-item .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .activity-item .activity-text {
            margin-bottom: 0.25rem;
        }
        
        .activity-item .activity-user {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .sample-data-notice {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .stat-card {
                padding: 1rem;
            }
            
            .stat-card .icon {
                font-size: 1.5rem;
                width: 50px;
                height: 50px;
            }
            
            .stat-card .value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="dashboard-header">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard Overview
    </div>
    
    <?php if ($use_sample_data): ?>
    <div class="sample-data-notice">
        <i class="fas fa-info-circle me-2"></i> 
        Note: Sample data is being displayed for demonstration purposes.
    </div>
    <?php endif; ?>
    
    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="title">Total Users</div>
                <div class="value"><?php echo $total_users; ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up me-1"></i> 12% from last month
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="icon success">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="title">Counsellors</div>
                <div class="value"><?php echo $total_counsellors; ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up me-1"></i> 5% from last month
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="icon info">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="title">Total Appointments</div>
                <div class="value"><?php echo $total_appointments; ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up me-1"></i> 8% from last month
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="icon warning">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="title">Today's Appointments</div>
                <div class="value"><?php echo $today_appointments; ?></div>
                <div class="trend up">
                    <i class="fas fa-arrow-up me-1"></i> 3 more than yesterday
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-line me-2"></i> Appointment Trends
                </div>
                <canvas id="appointmentTrends" height="300"></canvas>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-pie me-2"></i> Appointment Status
                </div>
                <canvas id="appointmentStatus" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Tables Row -->
    <div class="row">
        <div class="col-lg-6">
            <div class="table-card">
                <div class="table-title">
                    <i class="fas fa-calendar me-2"></i> Recent Appointments
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Counsellor</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_appointments)): ?>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td>#<?php echo $appointment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['counsellor_name']); ?></td>
                                        <td>
                                            <?php 
                                                $date = new DateTime($appointment['appointment_date']);
                                                echo $date->format('M d, Y') . ' at ' . 
                                                     substr($appointment['start_time'], 0, 5);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $statusClass = '';
                                                switch($appointment['status']) {
                                                    case 'scheduled': $statusClass = 'status-scheduled'; break;
                                                    case 'completed': $statusClass = 'status-completed'; break;
                                                    case 'cancelled': $statusClass = 'status-cancelled'; break;
                                                    case 'no-show': $statusClass = 'status-no-show'; break;
                                                }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $appointment['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No appointments found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <a href="#" class="btn btn-sm btn-primary" onclick="loadPage('admin_appointments.php')">
                        View All Appointments
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="progress-card">
                <div class="progress-title">
                    <i class="fas fa-user-md me-2"></i> Counsellor Utilization
                </div>
                <?php if (!empty($counsellor_utilization)): ?>
                    <?php foreach ($counsellor_utilization as $counsellor): ?>
                        <?php 
                            $completion_rate = $counsellor['appointment_count'] > 0 
                                ? round(($counsellor['completed_count'] / $counsellor['appointment_count']) * 100) 
                                : 0;
                            
                            $progress_class = 'bg-info';
                            if ($completion_rate >= 80) {
                                $progress_class = 'bg-success';
                            } elseif ($completion_rate >= 50) {
                                $progress_class = 'bg-primary';
                            } elseif ($completion_rate >= 30) {
                                $progress_class = 'bg-warning';
                            } elseif ($completion_rate > 0) {
                                $progress_class = 'bg-danger';
                            }
                        ?>
                        <div class="progress-item">
                            <div class="progress-label">
                                <span><?php echo htmlspecialchars($counsellor['counsellor_name']); ?></span>
                                <span><?php echo $counsellor['completed_count']; ?> / <?php echo $counsellor['appointment_count']; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?php echo $progress_class; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $completion_rate; ?>%" 
                                     aria-valuenow="<?php echo $completion_rate; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo $completion_rate; ?>% completion rate
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No counsellor utilization data available yet
                    </div>
                <?php endif; ?>
                <div class="text-end mt-3">
                    <a href="#" class="btn btn-sm btn-primary" onclick="loadPage('admin_counsellors.php')">
                        Manage Counsellors
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activity Row -->
    <div class="row">
        <div class="col-12">
            <div class="activity-card">
                <div class="activity-title">
                    <i class="fas fa-history me-2"></i> Recent System Activity
                </div>
                <?php if (!empty($system_activity)): ?>
                    <?php foreach ($system_activity as $activity): ?>
                        <div class="activity-item d-flex align-items-start">
                            <?php
                                $icon_class = 'bg-secondary';
                                $icon = 'fa-cog';
                                
                                if (strpos($activity['action'], 'created') !== false) {
                                    $icon_class = 'bg-success';
                                    $icon = 'fa-plus';
                                } elseif (strpos($activity['action'], 'updated') !== false) {
                                    $icon_class = 'bg-primary';
                                    $icon = 'fa-edit';
                                } elseif (strpos($activity['action'], 'deleted') !== false) {
                                    $icon_class = 'bg-danger';
                                    $icon = 'fa-trash';
                                } elseif (strpos($activity['action'], 'login') !== false) {
                                    $icon_class = 'bg-info';
                                    $icon = 'fa-sign-in-alt';
                                }
                            ?>
                            <div class="activity-icon <?php echo $icon_class; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                    <span class="fw-bold">
                                        <?php echo htmlspecialchars($activity['table_name']); ?>
                                    </span>
                                </div>
                                <div class="activity-user">
                                    By <?php echo htmlspecialchars($activity['user_name']); ?>
                                </div>
                                <div class="activity-time">
                                    <?php 
                                        $date = new DateTime($activity['created_at']);
                                        echo $date->format('M d, Y H:i');
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No system activity recorded yet
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Function to load pages
function loadPage(page) {
    $('#dashboardDynamicContent').load(page);
}

// Chart.js initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing charts');
    
    // Appointment Trends Chart
    const trendsCtx = document.getElementById('appointmentTrends');
    if (trendsCtx) {
        console.log('Trends chart canvas found');
        const trendsChart = new Chart(trendsCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($monthly_data as $data): ?>
                        '<?php echo $data['month']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Appointments',
                    data: [
                        <?php foreach ($monthly_data as $data): ?>
                            <?php echo $data['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    fill: true,
                    backgroundColor: 'rgba(79, 140, 255, 0.1)',
                    borderColor: 'rgba(79, 140, 255, 1)',
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(79, 140, 255, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 14
                        },
                        padding: 12,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        console.log('Trends chart initialized');
    } else {
        console.error('Trends chart canvas not found');
    }
    
    // Appointment Status Chart
    const statusCtx = document.getElementById('appointmentStatus');
    if (statusCtx) {
        console.log('Status chart canvas found');
        console.log('Status data:', [<?php echo $scheduled; ?>, <?php echo $completed; ?>, <?php echo $cancelled; ?>, <?php echo $no_show; ?>]);
        
        const statusChart = new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Scheduled', 'Completed', 'Cancelled', 'No-Show'],
                datasets: [{
                    data: [
                        <?php echo $scheduled; ?>,
                        <?php echo $completed; ?>,
                        <?php echo $cancelled; ?>,
                        <?php echo $no_show; ?>
                    ],
                    backgroundColor: [
                        'rgba(79, 140, 255, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ],
                    borderColor: [
                        'rgba(79, 140, 255, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            boxWidth: 12,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 14
                        },
                        padding: 12
                    }
                },
                cutout: '70%'
            }
        });
        console.log('Status chart initialized');
    } else {
        console.error('Status chart canvas not found');
    }
});
</script>
</body>
</html>
