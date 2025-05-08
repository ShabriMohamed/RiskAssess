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

// Get all active counselors
$counselors_query = $conn->query("
    SELECT c.id, c.bio, c.specialties, c.profile_photo, c.license_number, 
           u.name, u.email, u.telephone
    FROM counsellors c
    JOIN users u ON c.user_id = u.id
    WHERE u.status = 'active'
    ORDER BY u.name ASC
");

$counselors = [];
if ($counselors_query) {
    while ($row = $counselors_query->fetch_assoc()) {
        $counselors[] = $row;
    }
}

// Get user's upcoming appointments to prevent double booking
$upcoming_appointments = [];
$upcoming_query = $conn->query("
    SELECT appointment_date, start_time, end_time
    FROM appointments
    WHERE client_id = $user_id AND status = 'scheduled'
    AND (appointment_date > CURDATE() OR 
        (appointment_date = CURDATE() AND end_time > CURTIME()))
");

if ($upcoming_query) {
    while ($row = $upcoming_query->fetch_assoc()) {
        $upcoming_appointments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - RiskAssess</title>
    
    <!-- Core CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Preload key resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css"></noscript>
    
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"></noscript>
    
    <!-- Preload JavaScript resources -->
    <link rel="preload" href="https://code.jquery.com/jquery-3.6.0.min.js" as="script">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" as="script">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js" as="script">
    
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --primary-light: #e6f0ff;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
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
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .booking-container {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
            transition: var(--transition);
        }
        
        .booking-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .booking-body {
            padding: 1.5rem;
        }
        
        /* Step Indicators */
        .booking-steps {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }
        
        .booking-step {
            flex: 1;
            text-align: center;
            padding: 1rem 0.5rem;
            position: relative;
            transition: var(--transition);
        }
        
        .booking-step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            width: 20px;
            height: 2px;
            background: #ddd;
            transition: var(--transition);
        }
        
        .booking-step.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .booking-step.completed {
            color: var(--success);
        }
        
        .booking-step.completed:not(:last-child):after {
            background: var(--success);
        }
        
        .booking-step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f1f1f1;
            margin-bottom: 0.5rem;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .booking-step.active .booking-step-number {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }
        
        .booking-step.completed .booking-step-number {
            background: var(--success);
            color: white;
        }
        
        /* Step Content Sections */
        .booking-section {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }
        
        .booking-section.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Counselor Cards */
        .counselor-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .counselor-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            background: white;
        }
        
        .counselor-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .counselor-card.selected {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        .counselor-card.selected:before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 10px;
            right: 10px;
            color: var(--primary);
            font-size: 1.5rem;
            z-index: 2;
        }
        
        .counselor-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--border);
            background: var(--light);
        }
        
        .counselor-photo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .counselor-card:hover .counselor-photo {
            transform: scale(1.05);
        }
        
        .counselor-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .counselor-specialty {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .counselor-body {
            padding: 1.5rem;
        }
        
        .counselor-bio {
            margin-bottom: 1rem;
            font-size: 0.95rem;
            color: #444;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .counselor-details {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .counselor-details i {
            width: 20px;
            color: var(--primary);
        }
        
        /* Time Slots */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .time-slot {
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            border: 1px solid #ddd;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }
        
        .time-slot:hover {
            background: var(--light);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .time-slot.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }
        
        .time-slot.unavailable {
            background: #f1f1f1;
            color: #999;
            cursor: not-allowed;
            border-color: #ddd;
        }
        
        .time-slot.unavailable:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                rgba(0, 0, 0, 0.03),
                rgba(0, 0, 0, 0.03) 10px,
                rgba(0, 0, 0, 0.06) 10px,
                rgba(0, 0, 0, 0.06) 20px
            );
        }
        
        /* Summary Section */
        .booking-summary {
            background: var(--light);
            border-radius: var(--radius-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .summary-label {
            font-weight: 600;
            color: var(--gray);
        }
        
        .summary-value {
            color: var(--dark);
            font-weight: 500;
        }
        
        /* Footer Navigation */
        .booking-footer {
            display: flex;
            justify-content: space-between;
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--light);
        }
        
        /* Calendar Styling */
        .calendar-container {
            height: 500px;
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
            background: white;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            z-index: 1;
        }
        
        .calendar-placeholder.hidden {
            display: none;
        }
        
        /* Success Section */
        .booking-success {
            text-align: center;
            padding: 2rem;
        }
        
        .booking-success i {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1.5rem;
            animation: success-pulse 2s infinite;
        }
        
        @keyframes success-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .booking-success h3 {
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .booking-success p {
            color: var(--gray);
            margin-bottom: 2rem;
        }
        
        /* Search Filters */
        .search-filters {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
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
        
        #loadingMessage {
            color: var(--dark);
            font-weight: 500;
        }
        
        /* Alert Styling */
        .alert-floating {
            box-shadow: var(--shadow-md);
            border-left: 4px solid;
        }
        
        .alert-floating.alert-success {
            border-left-color: var(--success);
        }
        
        .alert-floating.alert-warning {
            border-left-color: var(--warning);
        }
        
        .alert-floating.alert-danger {
            border-left-color: var(--danger);
        }
        
        /* Button Enhancements */
        .btn {
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
        }
        
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .counselor-cards {
                grid-template-columns: 1fr;
            }
            
            .booking-steps {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .booking-step:not(:last-child):after {
                width: 2px;
                height: 20px;
                right: 50%;
                top: 100%;
                transform: translateX(50%);
            }
            
            .time-slots {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div id="loadingOverlay">
        <div class="spinner"></div>
        <div id="loadingMessage">Loading appointment system...</div>
    </div>

    <div class="booking-container">
        <div class="booking-header">
            <i class="fas fa-calendar-plus me-2"></i> Book a Counselling Appointment
        </div>
        
        <div class="booking-body">
            <div class="booking-steps">
                <div class="booking-step active" data-step="1">
                    <div class="booking-step-number">1</div>
                    <div>Select Counselor</div>
                </div>
                <div class="booking-step" data-step="2">
                    <div class="booking-step-number">2</div>
                    <div>Choose Date & Time</div>
                </div>
                <div class="booking-step" data-step="3">
                    <div class="booking-step-number">3</div>
                    <div>Confirm Details</div>
                </div>
                <div class="booking-step" data-step="4">
                    <div class="booking-step-number">4</div>
                    <div>Appointment Booked</div>
                </div>
            </div>
            
            <!-- Step 1: Select Counselor -->
            <div class="booking-section active" id="step1">
                <div class="search-filters">
                    <div class="row">
                        <div class="col-md-8 mb-3 mb-md-0">
                            <input type="text" id="counselorSearch" class="form-control" placeholder="Search by name or specialty...">
                        </div>
                        <div class="col-md-4">
                            <select id="specialtyFilter" class="form-select">
                                <option value="">All Specialties</option>
                                <option value="Anxiety">Anxiety</option>
                                <option value="Depression">Depression</option>
                                <option value="Stress">Stress Management</option>
                                <option value="Trauma">Trauma</option>
                                <option value="Relationships">Relationships</option>
                                <option value="Career">Career Counseling</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="counselor-cards">
                    <?php if (empty($counselors)): ?>
                        <div class="alert alert-info w-100">No counselors are currently available. Please try again later.</div>
                    <?php else: ?>
                        <?php foreach ($counselors as $counselor): ?>
                            <div class="counselor-card" data-id="<?php echo $counselor['id']; ?>" data-name="<?php echo htmlspecialchars($counselor['name']); ?>" data-specialties="<?php echo htmlspecialchars($counselor['specialties']); ?>">
                                <div class="counselor-header">
                                    <img src="<?php echo !empty($counselor['profile_photo']) ? 'uploads/counsellors/' . $counselor['profile_photo'] : 'assets/img/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($counselor['name']); ?>" class="counselor-photo">
                                    <div>
                                        <div class="counselor-name"><?php echo htmlspecialchars($counselor['name']); ?></div>
                                        <div class="counselor-specialty"><?php echo htmlspecialchars($counselor['specialties']); ?></div>
                                    </div>
                                </div>
                                <div class="counselor-body">
                                    <div class="counselor-bio"><?php echo htmlspecialchars($counselor['bio'] ?? 'No bio available.'); ?></div>
                                    <div class="counselor-details">
                                        <div class="mb-1"><i class="fas fa-id-card-alt"></i> License: <?php echo htmlspecialchars($counselor['license_number']); ?></div>
                                        <div class="mb-1"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($counselor['email']); ?></div>
                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($counselor['telephone']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="alert alert-info" id="noCounselorsMessage" style="display: none;">
                    No counselors match your search criteria. Please try different keywords.
                </div>
            </div>
            
            <!-- Step 2: Choose Date & Time -->
            <div class="booking-section" id="step2">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="calendar-container">
                            <div class="calendar-placeholder">
                                <div class="spinner"></div>
                                <p class="mt-3">Loading calendar...</p>
                            </div>
                            <div id="appointmentCalendar"></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-clock me-2"></i> Available Time Slots
                            </div>
                            <div class="card-body">
                                <div id="selectedDate" class="mb-3 fw-bold text-center"></div>
                                <div id="timeSlots" class="time-slots">
                                    <div class="alert alert-info">
                                        Please select a date from the calendar to view available time slots.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Step 3: Confirm Details -->
            <div class="booking-section" id="step3">
                <h4 class="mb-4">Appointment Summary</h4>
                <div class="booking-summary">
                    <div class="summary-item">
                        <div class="summary-label">Counselor</div>
                        <div class="summary-value" id="summaryName"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Date</div>
                        <div class="summary-value" id="summaryDate"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Time</div>
                        <div class="summary-value" id="summaryTime"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="appointmentNotes" class="form-label">Additional Notes (optional)</label>
                    <textarea id="appointmentNotes" class="form-control" rows="3" placeholder="Any specific concerns or topics you'd like to discuss..."></textarea>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> By confirming this appointment, you agree to our <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">terms and conditions</a>.
                </div>
            </div>
            
            <!-- Step 4: Confirmation -->
            <div class="booking-section" id="step4">
                <div class="booking-success">
                    <i class="fas fa-check-circle"></i>
                    <h3>Appointment Booked Successfully!</h3>
                    <p>Your appointment has been confirmed. You will receive a confirmation email shortly.</p>
                    <div class="booking-summary">
                        <div class="summary-item">
                            <div class="summary-label">Appointment ID</div>
                            <div class="summary-value" id="confirmationId"></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Counselor</div>
                            <div class="summary-value" id="confirmationName"></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Date & Time</div>
                            <div class="summary-value" id="confirmationDateTime"></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="button" class="btn btn-primary me-2" id="viewAppointmentsBtn">
                            <i class="fas fa-calendar-check me-2"></i> View My Appointments
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="bookAnotherBtn">
                            <i class="fas fa-plus me-2"></i> Book Another Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="booking-footer">
            <button type="button" class="btn btn-outline-secondary" id="prevBtn" style="display: none;">
                <i class="fas fa-arrow-left me-2"></i> Previous
            </button>
            <button type="button" class="btn btn-primary" id="nextBtn" disabled>
                Next <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Appointment Booking Terms</h6>
                    <p>By booking an appointment with RiskAssess, you agree to the following terms:</p>
                    <ul>
                        <li>You must arrive 10 minutes before your scheduled appointment time.</li>
                        <li>Cancellations must be made at least 24 hours in advance.</li>
                        <li>Late cancellations or no-shows may result in a cancellation fee.</li>
                        <li>All information shared during counseling sessions is confidential.</li>
                        <li>RiskAssess reserves the right to reschedule appointments if necessary.</li>
                    </ul>
                    
                    <h6>Privacy Policy</h6>
                    <p>RiskAssess is committed to protecting your privacy. All personal information collected will be used only for the purpose of providing counseling services and will not be shared with third parties without your consent.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Inline initialization script to ensure immediate execution -->
    <script>
        // Global variables
        let bookingData = {
            counselorId: null,
            counselorName: null,
            appointmentDate: null,
            startTime: null,
            endTime: null,
            notes: null
        };
        
        let calendar = null;
        let currentStep = 1;
        let librariesLoaded = false;
        
        // Document ready function
        $(document).ready(function() {
            console.log("Document ready - initializing booking system");
            initApp();
        });
        
        // Initialize the application
        function initApp() {
            // Attach event handlers for static elements
            attachEventHandlers();
            
            // Load libraries and initialize calendar
            loadLibraries();
            
            // Show the page after a short delay to ensure smooth transitions
            setTimeout(function() {
                hideLoading();
            }, 500);
        }
        
        // Attach event handlers
        function attachEventHandlers() {
            // Counselor selection
            $(document).on('click', '.counselor-card', function() {
                $('.counselor-card').removeClass('selected');
                $(this).addClass('selected');
                
                bookingData.counselorId = $(this).data('id');
                bookingData.counselorName = $(this).data('name');
                
                // Enable next button
                $('#nextBtn').prop('disabled', false);
            });
            
            // Search and filter counselors
            $('#counselorSearch, #specialtyFilter').on('input change', function() {
                const searchTerm = $('#counselorSearch').val().toLowerCase();
                const specialty = $('#specialtyFilter').val().toLowerCase();
                let found = false;
                
                $('.counselor-card').each(function() {
                    const name = $(this).data('name').toLowerCase();
                    const specialties = $(this).data('specialties').toLowerCase();
                    
                    const nameMatch = name.includes(searchTerm);
                    const specialtyMatch = specialty === '' || specialties.includes(specialty);
                    
                    if (nameMatch && specialtyMatch) {
                        $(this).show();
                        found = true;
                    } else {
                        $(this).hide();
                    }
                });
                
                if (found) {
                    $('#noCounselorsMessage').hide();
                } else {
                    $('#noCounselorsMessage').show();
                }
            });
            
            // Time slot selection
            $(document).on('click', '.time-slot:not(.unavailable)', function() {
                $('.time-slot').removeClass('selected');
                $(this).addClass('selected');
                
                bookingData.startTime = $(this).data('start');
                bookingData.endTime = $(this).data('end');
                
                // Enable next button
                $('#nextBtn').prop('disabled', false);
            });
            
            // Navigation buttons
            $('#nextBtn').click(function() {
                nextStep();
            });
            
            $('#prevBtn').click(function() {
                prevStep();
            });
            
            // View appointments button
            $('#viewAppointmentsBtn').click(function() {
                navigateToAppointments();
            });
            
            // Book another appointment button
            $('#bookAnotherBtn').click(function() {
                resetBookingProcess();
            });
        }
        
        // Load required libraries
        function loadLibraries() {
            if (librariesLoaded) return;
            
            // Load FullCalendar
            const fcScript = document.createElement('script');
            fcScript.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js';
            fcScript.async = true;
            
            fcScript.onload = function() {
                console.log("FullCalendar loaded");
                
                // Load Flatpickr
                const fpScript = document.createElement('script');
                fpScript.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
                fpScript.async = true;
                
                fpScript.onload = function() {
                    console.log("Flatpickr loaded");
                    librariesLoaded = true;
                    
                    // Initialize calendar when moving to step 2
                    if (currentStep === 2) {
                        initializeCalendar();
                    }
                };
                
                document.head.appendChild(fpScript);
            };
            
            document.head.appendChild(fcScript);
        }
        
        // Initialize calendar
        function initializeCalendar() {
            if (!librariesLoaded) {
                console.log("Libraries not loaded yet, waiting...");
                setTimeout(initializeCalendar, 100);
                return;
            }
            
            try {
                console.log("Initializing calendar");
                const calendarEl = document.getElementById('appointmentCalendar');
                
                if (!calendarEl) {
                    console.error("Calendar element not found");
                    return;
                }
                
                if (calendar) {
                    console.log("Calendar already initialized, refreshing events");
                    calendar.refetchEvents();
                    $('.calendar-placeholder').addClass('hidden');
                    return;
                }
                
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth'
                    },
                    height: '100%',
                    selectable: true,
                    selectAllow: function(selectInfo) {
                        return selectInfo.start >= new Date().setHours(0, 0, 0, 0);
                    },
                    dateClick: function(info) {
                        handleDateClick(info);
                    },
                    events: function(info, successCallback, failureCallback) {
                        fetchAvailabilityEvents(info, successCallback, failureCallback);
                    },
                    eventDidMount: function(info) {
                        // Add tooltip
                        $(info.el).tooltip({
                            title: info.event.title,
                            placement: 'top',
                            trigger: 'hover',
                            container: 'body'
                        });
                    }
                });
                
                calendar.render();
                $('.calendar-placeholder').addClass('hidden');
                console.log("Calendar initialized successfully");
            } catch (error) {
                console.error("Error initializing calendar:", error);
                showAlert('Error initializing calendar. Please refresh the page.', 'danger');
            }
        }
        
        // Handle date click in calendar
        function handleDateClick(info) {
            // Only allow future dates
            const selectedDate = new Date(info.dateStr);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate >= today) {
                $('.fc-day').removeClass('selected-date');
                $(info.dayEl).addClass('selected-date');
                
                // Format date for display
                const formattedDate = new Intl.DateTimeFormat('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }).format(selectedDate);
                
                $('#selectedDate').text(formattedDate);
                bookingData.appointmentDate = info.dateStr;
                
                // Load time slots for this date and counselor
                loadTimeSlots(bookingData.counselorId, info.dateStr);
            }
        }
        
        // Fetch availability events for calendar
        function fetchAvailabilityEvents(info, successCallback, failureCallback) {
            // If no counselor selected, return empty events
            if (!bookingData.counselorId) {
                successCallback([]);
                return;
            }
            
            // Load counselor's availability and existing appointments
            $.ajax({
                url: 'processes/user_appointment_availability.php',
                type: 'GET',
                data: {
                    counselor_id: bookingData.counselorId,
                    start: info.startStr,
                    end: info.endStr
                },
                success: function(result) {
                    successCallback(result);
                },
                error: function(xhr, status, error) {
                    console.error("Error loading availability:", error);
                    failureCallback({ message: 'Error loading availability' });
                    showAlert('Error loading counselor availability. Please try again.', 'danger');
                }
            });
        }
        
        // Load time slots for a specific date
        function loadTimeSlots(counselorId, date) {
            if (!counselorId || !date) {
                return;
            }
            
            showLoading('Loading available time slots...');
            
            $.ajax({
                url: 'processes/user_appointment_time_slots.php',
                type: 'GET',
                data: {
                    counselor_id: counselorId,
                    date: date
                },
                dataType: 'json',
                success: function(slots) {
                    hideLoading();
                    let html = '';
                    
                    if (slots.length > 0) {
                        slots.forEach(function(slot) {
                            const btnClass = slot.available ? 'time-slot' : 'time-slot unavailable';
                            const disabled = !slot.available ? 'disabled' : '';
                            
                            html += `
                            <div class="${btnClass}" data-start="${slot.start_time}" data-end="${slot.end_time}" ${disabled}>
                                ${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}
                            </div>`;
                        });
                    } else {
                        html = `<div class="alert alert-info w-100">No available time slots for this date.</div>`;
                    }
                    
                    $('#timeSlots').html(html);
                    
                    // Reset selected time
                    bookingData.startTime = null;
                    bookingData.endTime = null;
                    $('#nextBtn').prop('disabled', true);
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error("Error loading time slots:", error);
                    $('#timeSlots').html('<div class="alert alert-danger w-100">Error loading time slots. Please try again.</div>');
                }
            });
        }
        
        // Move to next step
        function nextStep() {
            // Validate current step
            if (currentStep === 1 && !bookingData.counselorId) {
                showAlert('Please select a counselor to continue.', 'warning');
                return;
            }
            
            if (currentStep === 2 && (!bookingData.appointmentDate || !bookingData.startTime)) {
                showAlert('Please select a date and time slot to continue.', 'warning');
                return;
            }
            
            // Move to next step
            if (currentStep < 4) {
                // If moving to confirmation step, update summary
                if (currentStep === 2) {
                    updateSummary();
                }
                
                // If moving to final step, book the appointment
                if (currentStep === 3) {
                    bookAppointment();
                    return; // Don't proceed until booking is complete
                }
                
                // Hide current step, show next step
                $(`#step${currentStep}`).removeClass('active');
                currentStep++;
                $(`#step${currentStep}`).addClass('active');
                
                // Update step indicators
                updateStepIndicators();
                
                // Show/hide prev button
                $('#prevBtn').show();
                
                // Update next button text for final step
                if (currentStep === 3) {
                    $('#nextBtn').html('Confirm Appointment <i class="fas fa-check ms-2"></i>');
                }
                
                // Disable next button for step 2 until selections are made
                if (currentStep === 2) {
                    $('#nextBtn').prop('disabled', true);
                    
                    // Initialize calendar when moving to step 2
                    initializeCalendar();
                }
            }
        }
        
        // Move to previous step
        function prevStep() {
            if (currentStep > 1) {
                // Hide current step, show previous step
                $(`#step${currentStep}`).removeClass('active');
                currentStep--;
                $(`#step${currentStep}`).addClass('active');
                
                // Update step indicators
                updateStepIndicators();
                
                // Hide prev button if on first step
                if (currentStep === 1) {
                    $('#prevBtn').hide();
                }
                
                // Reset next button text
                $('#nextBtn').html('Next <i class="fas fa-arrow-right ms-2"></i>');
                
                // Enable next button for steps 1 and 3
                if (currentStep === 1 || currentStep === 3) {
                    $('#nextBtn').prop('disabled', false);
                }
                
                // For step 2, check if time is selected
                if (currentStep === 2) {
                    $('#nextBtn').prop('disabled', !bookingData.startTime);
                }
            }
        }
        
        // Update step indicators
        function updateStepIndicators() {
            $('.booking-step').removeClass('active completed');
            
            // Mark current step as active
            $(`.booking-step[data-step="${currentStep}"]`).addClass('active');
            
            // Mark previous steps as completed
            for (let i = 1; i < currentStep; i++) {
                $(`.booking-step[data-step="${i}"]`).addClass('completed');
            }
        }
        
        // Update appointment summary
        function updateSummary() {
            $('#summaryName').text(bookingData.counselorName);
            
            // Format date for display
            const date = new Date(bookingData.appointmentDate);
            const formattedDate = new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }).format(date);
            
            $('#summaryDate').text(formattedDate);
            $('#summaryTime').text(`${bookingData.startTime.substring(0, 5)} - ${bookingData.endTime.substring(0, 5)}`);
        }
        
        // Book appointment
        function bookAppointment() {
            // Get notes
            bookingData.notes = $('#appointmentNotes').val();
            
            showLoading('Booking your appointment...');
            
            $.ajax({
                url: 'processes/user_appointment_book.php',
                type: 'POST',
                data: {
                    counselor_id: bookingData.counselorId,
                    appointment_date: bookingData.appointmentDate,
                    start_time: bookingData.startTime,
                    end_time: bookingData.endTime,
                    notes: bookingData.notes
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.success) {
                        // Update confirmation details
                        $('#confirmationId').text(response.appointment_id);
                        $('#confirmationName').text(bookingData.counselorName);
                        $('#confirmationDateTime').text(`${$('#summaryDate').text()} at ${bookingData.startTime.substring(0, 5)}`);
                        
                        // Move to confirmation step
                        $(`#step${currentStep}`).removeClass('active');
                        currentStep = 4;
                        $(`#step${currentStep}`).addClass('active');
                        
                        // Update step indicators
                        updateStepIndicators();
                        
                        // Hide navigation buttons
                        $('#prevBtn, #nextBtn').hide();
                    } else {
                        showAlert(response.message || 'Error booking appointment. Please try again.', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error("Error booking appointment:", error);
                    showAlert('Server error. Please try again later.', 'danger');
                }
            });
        }
        
        // Navigate to appointments page
        function navigateToAppointments() {
            $('.sidebar .nav-link').removeClass('active');
            $('.sidebar .nav-link[data-page="user_appointments.php"]').addClass('active');
            $('#dashboardDynamicContent').fadeOut(120, function() {
                $('#dashboardDynamicContent').load('user_appointments.php', function() {
                    $(this).fadeIn(120);
                });
            });
        }
        
        // Reset booking process
        function resetBookingProcess() {
            // Reset booking data
            bookingData = {
                counselorId: null,
                counselorName: null,
                appointmentDate: null,
                startTime: null,
                endTime: null,
                notes: null
            };
            
            // Reset UI
            $('.counselor-card').removeClass('selected');
            $('#selectedDate').text('');
            $('#timeSlots').html('<div class="alert alert-info">Please select a date from the calendar to view available time slots.</div>');
            $('#appointmentNotes').val('');
            
            // Reset to step 1
            $('.booking-section').removeClass('active');
            $('#step1').addClass('active');
            currentStep = 1;
            
            // Update step indicators
            updateStepIndicators();
            
            // Reset navigation buttons
            $('#prevBtn').hide();
            $('#nextBtn').show().prop('disabled', true).html('Next <i class="fas fa-arrow-right ms-2"></i>');
        }
        
        // Show loading overlay
        function showLoading(message) {
            $('#loadingMessage').text(message || 'Loading...');
            $('#loadingOverlay').css('opacity', '1').show();
        }
        
        // Hide loading overlay
        function hideLoading() {
            $('#loadingOverlay').css('opacity', '0');
            setTimeout(function() {
                $('#loadingOverlay').hide();
            }, 300);
        }
        
        // Show alert message
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
    </script>
</body>
</html>
