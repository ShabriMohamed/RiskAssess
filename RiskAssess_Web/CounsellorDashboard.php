<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}
$counsellor_name = $_SESSION['name'] ?? 'Counsellor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Counsellor Dashboard - RiskAssess</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5.2 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Poppins', sans-serif;
            color: #23272f;
            min-height: 100vh;
        }
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            min-width: 220px;
            max-width: 240px;
            background: #fff;
            border-right: 1px solid #e5e7eb;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: 2px 0 12px 0 rgba(0,0,0,0.04);
        }
        .sidebar .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: #4f8cff;
            letter-spacing: 1px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .sidebar .nav-link {
            color: #23272f;
            font-size: 1.05rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: #4f8cff;
            color: #fff;
            box-shadow: 0 2px 8px 0 rgba(79,140,255,0.08);
        }
        .sidebar .logout {
            margin-top: auto;
            color: #ff6f61;
            background: none;
            border: none;
            font-size: 1.05rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-align: left;
            transition: background 0.2s, color 0.2s;
        }
        .sidebar .logout:hover {
            background: #ffeaea;
            color: #ff3f3f;
        }
        .main-content {
            flex: 1;
            padding: 2.5rem 2rem;
            background: transparent;
            min-height: 100vh;
            transition: background 0.3s;
        }
        .dashboard-header {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #4f8cff;
            letter-spacing: 0.5px;
        }
        .welcome-box {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 16px 0 rgba(0,0,0,0.04);
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            animation: fadeIn 0.7s;
        }
        .welcome-box i {
            font-size: 2.5rem;
            color: #4f8cff;
        }
        @media (max-width: 900px) {
            .dashboard-wrapper { flex-direction: column; }
            .sidebar { flex-direction: row; min-width: 100%; max-width: 100%; padding: 1rem; border-right: none; border-bottom: 1px solid #e5e7eb; }
            .sidebar .logo { display: none; }
            .sidebar .logout { margin-top: 0; }
        }
        @media (max-width: 600px) {
            .main-content { padding: 1rem 0.3rem; }
            .welcome-box { flex-direction: column; align-items: flex-start; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(24px);}
            to { opacity: 1; transform: none;}
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <nav class="sidebar shadow-sm">
        <div class="logo">RiskAssess</div>
        <a href="#" class="nav-link active" data-page="counsellor_overview.php"><i class="fa-solid fa-house"></i> Dashboard</a>
        <a href="#" class="nav-link" data-page="counsellor_appointments.php"><i class="fa-solid fa-calendar-check"></i> My Appointments</a>
        <a href="#" class="nav-link" data-page="counsellor_clients.php"><i class="fa-solid fa-users"></i> My Clients</a>
        <a href="#" class="nav-link" data-page="counsellor_messages.php"><i class="fa-solid fa-envelope"></i> Messages</a>
        <a href="#" class="nav-link" data-page="counsellor_availability.php"><i class="fa-solid fa-clock"></i> My Availability</a>
        <a href="#" class="nav-link" data-page="counsellor_profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <button class="logout" onclick="window.location.href='logout.php'"><i class="fa-solid fa-sign-out-alt"></i> Logout</button>
    </nav>
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="dashboard-header">Welcome, <?php echo htmlspecialchars($counsellor_name); ?>!</div>
        <div class="welcome-box">
            <i class="fa-solid fa-stethoscope"></i>
            <div>
                <div style="font-size:1.2rem;font-weight:500;">Your RiskAssess Counsellor Dashboard</div>
                <div style="color:#555;">See your appointments, manage clients, and stay connected with ease.</div>
            </div>
        </div>
        <div id="dashboardDynamicContent">
            <!-- Dynamic content will be loaded here -->
        </div>
    </main>
</div>

<!-- Bootstrap 5.2 JS and jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Sidebar navigation click handler
    $('.sidebar .nav-link').on('click', function(e) {
        e.preventDefault();
        $('.sidebar .nav-link').removeClass('active');
        $(this).addClass('active');
        let page = $(this).data('page');
        if(page) {
            $('#dashboardDynamicContent').fadeOut(120, function() {
                $('#dashboardDynamicContent').load(page, function() {
                    $(this).fadeIn(120);
                });
            });
        }
    });
    // Load default overview
    $('#dashboardDynamicContent').load('counsellor_overview.php');
});
</script>
</body>
</html>
