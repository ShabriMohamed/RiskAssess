<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit;
}
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Management - RiskAssess Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- FullCalendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Time Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .fc-event {cursor: pointer;}
        .fc-event-title {font-weight: 500;}
        .fc-day-today {background-color: rgba(79, 140, 255, 0.05) !important;}
        .fc-day-past {background-color: rgba(0, 0, 0, 0.02);}
        .fc-col-header-cell {background-color: #f8f9fa; font-weight: 600;}
        .fc-toolbar-title {font-size: 1.5rem !important; font-weight: 600;}
        .appointment-status-scheduled {
            background-color: #4f8cff !important;
            border-color: #4f8cff !important;
        }
        .appointment-status-completed {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
        }
        .appointment-status-cancelled {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        .appointment-status-no-show {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-scheduled {background-color: #e6f0ff; color: #0055cc;}
        .status-completed {background-color: #e6f9e6; color: #1e7e34;}
        .status-cancelled {background-color: #f8d7da; color: #b02a37;}
        .status-no-show {background-color: #e9ecef; color: #495057;}
        .nav-tabs .nav-link.active {
            border-color: transparent;
            border-bottom: 3px solid #4f8cff;
            font-weight: 600;
        }
        .nav-tabs .nav-link {color: #495057; border: none; padding: 0.75rem 1rem;}
        .nav-tabs {border-bottom: 1px solid #dee2e6; margin-bottom: 1.5rem;}
        .time-slot-available {
            background-color: #e6f9e6;
            border-color: #28a745;
            color: #1e7e34;
        }
        .time-slot-unavailable {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #b02a37;
        }
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
    </style>
</head>
<body>
<div id="loadingOverlay" style="display:none;">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <div id="loadingMessage">Processing...</div>
</div>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fa-solid fa-calendar-check"></i> Appointment Management</h4>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="refreshCalendarBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-sm btn-primary" id="openAddAppointmentBtn">
                <i class="fas fa-plus"></i> New Appointment
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs" id="appointmentTabs">
        <li class="nav-item">
            <a class="nav-link active" id="calendar-tab" data-bs-toggle="tab" href="#calendarView">Calendar View</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="list-tab" data-bs-toggle="tab" href="#listView">List View</a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="calendarView">
            <div class="row">
                <div class="col-md-3">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-filter"></i> Filters
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Counsellor</label>
                                <select id="counsellorFilter" class="form-select">
                                    <option value="">All Counsellors</option>
                                    <?php
                                    $counsellors = $conn->query("SELECT c.id, u.name FROM counsellors c 
                                                                JOIN users u ON c.user_id = u.id 
                                                                WHERE u.status = 'active' 
                                                                ORDER BY u.name");
                                    while($c = $counsellors->fetch_assoc()) {
                                        echo "<option value='{$c['id']}'>".htmlspecialchars($c['name'])."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select id="statusFilter" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="no-show">No-Show</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date Range</label>
                                <div class="input-group">
                                    <input type="text" id="startDateFilter" class="form-control date-picker" placeholder="Start">
                                    <span class="input-group-text">to</span>
                                    <input type="text" id="endDateFilter" class="form-control date-picker" placeholder="End">
                                </div>
                            </div>
                            <div class="d-grid">
                                <button id="applyFiltersBtn" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <i class="fas fa-info-circle"></i> Appointment Legend
                        </div>
                        <div class="card-body p-2">
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2" style="width:15px;height:15px;background-color:#4f8cff;border-radius:3px;"></div>
                                <span>Scheduled</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2" style="width:15px;height:15px;background-color:#28a745;border-radius:3px;"></div>
                                <span>Completed</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2" style="width:15px;height:15px;background-color:#dc3545;border-radius:3px;"></div>
                                <span>Cancelled</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="me-2" style="width:15px;height:15px;background-color:#6c757d;border-radius:3px;"></div>
                                <span>No-Show</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="card">
                        <div class="card-body">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="listView">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="appointmentsTable" class="table table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Counsellor</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Counsellor</label>
                        <select id="counsellorSelect" class="form-select" required>
                            <option value="">-- Select Counsellor --</option>
                            <?php
                            $counsellors = $conn->query("SELECT c.id, u.name FROM counsellors c 
                                                        JOIN users u ON c.user_id = u.id 
                                                        WHERE u.status = 'active' 
                                                        ORDER BY u.name");
                            while($c = $counsellors->fetch_assoc()) {
                                echo "<option value='{$c['id']}'>".htmlspecialchars($c['name'])."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client</label>
                        <select id="clientSelect" class="form-select" required>
                            <option value="">-- Select Client --</option>
                            <?php
                            $clients = $conn->query("SELECT id, name, email FROM users 
                                                    WHERE role = 'customer' AND status = 'active' 
                                                    ORDER BY name");
                            while($c = $clients->fetch_assoc()) {
                                echo "<option value='{$c['id']}'>".htmlspecialchars($c['name'])." ({$c['email']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="text" id="appointmentDate" class="form-control date-picker" required>
                    </div>
                </div>
                
                <div id="timeSlotContainer" class="mb-3 d-none">
                    <label class="form-label">Available Time Slots</label>
                    <div id="timeSlots" class="d-flex flex-wrap gap-2">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading available time slots...</p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 d-none" id="selectedTimeContainer">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="text" id="startTime" class="form-control" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="text" id="endTime" class="form-control" readonly required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Notes (optional)</label>
                    <textarea id="appointmentNotes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="scheduleAppointmentBtn" class="btn btn-primary">Schedule Appointment</button>
            </div>
        </div>
    </div>
</div>

<!-- View/Edit Appointment Modal -->
<div class="modal fade" id="viewAppointmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="appointmentId">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Client</label>
                        <div id="clientName" class="form-control-plaintext"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Counsellor</label>
                        <div id="counsellorName" class="form-control-plaintext"></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <div id="appointmentDateDisplay" class="form-control-plaintext"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Time</label>
                        <div id="appointmentTimeDisplay" class="form-control-plaintext"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select id="appointmentStatus" class="form-select">
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no-show">No-Show</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea id="viewAppointmentNotes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="deleteAppointmentBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="saveAppointmentChangesBtn" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
$(document).ready(function() {
    console.log("Document ready - Appointments");
    
    // Initialize FullCalendar
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        height: 'auto',
        expandRows: true,
        slotDuration: '00:30:00',
        slotLabelInterval: '01:00:00',
        dayMaxEvents: true,
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        },
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5], // Monday - Friday
            startTime: '09:00',
            endTime: '17:00'
        },
        eventClick: function(info) {
            const eventType = info.event.extendedProps.type;
            
            if (eventType === 'appointment') {
                const appointmentId = info.event.id;
                loadAppointmentDetails(appointmentId);
            }
        },
        events: function(info, successCallback, failureCallback) {
            const filters = {
                counsellor_id: $('#counsellorFilter').val(),
                status: $('#statusFilter').val(),
                start_date: $('#startDateFilter').val(),
                end_date: $('#endDateFilter').val(),
                start: info.startStr,
                end: info.endStr
            };
            
            $.ajax({
                url: 'processes/admin_appointments_events.php',
                type: 'GET',
                data: filters,
                success: function(result) {
                    successCallback(result);
                },
                error: function(xhr, status, error) {
                    console.error("Error loading events:", error);
                    failureCallback({ message: 'Error loading events' });
                    showAlert('Error loading calendar events: ' + error, 'danger');
                }
            });
        },
        eventClassNames: function(arg) {
            if (arg.event.extendedProps.type === 'appointment') {
                return [`appointment-status-${arg.event.extendedProps.status}`];
            }
            return [];
        }
    });
    calendar.render();
    
    // Initialize DataTable
    try {
        let appointmentsTable = $('#appointmentsTable').DataTable({
            ajax: {
                url: 'processes/admin_appointments_list.php',
                dataSrc: function(json) {
                    return json || [];
                }
            },
            columns: [
                { data: 'id' },
                { data: 'client_name' },
                { data: 'counsellor_name' },
                { data: 'appointment_date' },
                { data: 'appointment_time' },
                { 
                    data: 'status',
                    render: function(data) {
                        let badgeClass = '';
                        switch(data) {
                            case 'scheduled': badgeClass = 'status-scheduled'; break;
                            case 'completed': badgeClass = 'status-completed'; break;
                            case 'cancelled': badgeClass = 'status-cancelled'; break;
                            case 'no-show': badgeClass = 'status-no-show'; break;
                        }
                        return `<span class="status-badge ${badgeClass}">${data}</span>`;
                    }
                },
                { data: 'created_at' },
                { 
                    data: 'id',
                    render: function(data) {
                        return `
                        <button class="btn btn-sm btn-outline-primary view-appointment-btn" data-id="${data}">
                            <i class="fas fa-eye"></i>
                        </button>`;
                    }
                }
            ],
            order: [[3, 'desc'], [4, 'desc']],
            responsive: true
        });
        
        console.log("DataTable initialized");
    } catch (e) {
        console.error("Error initializing DataTable:", e);
    }
    
    // Initialize date pickers
    flatpickr('.date-picker', {
        dateFormat: "Y-m-d",
        minDate: "today"
    });
    
    // Open add appointment modal
    $('#openAddAppointmentBtn').click(function() {
        // Reset form
        $('#counsellorSelect').val('');
        $('#clientSelect').val('');
        $('#appointmentDate').val('');
        $('#appointmentNotes').val('');
        $('#timeSlotContainer').addClass('d-none');
        $('#selectedTimeContainer').addClass('d-none');
        
        $('#addAppointmentModal').modal('show');
    });
    
    // Tab change event
    $('#appointmentTabs a').on('click', function(e) {
        e.preventDefault();
        $(this).tab('show');
        
        if ($(this).attr('href') === '#listView') {
            if ($.fn.DataTable.isDataTable('#appointmentsTable')) {
                $('#appointmentsTable').DataTable().ajax.reload();
            }
        } else if ($(this).attr('href') === '#calendarView') {
            calendar.refetchEvents();
        }
    });
    
    // Refresh calendar button
    $('#refreshCalendarBtn').click(function() {
        calendar.refetchEvents();
        if ($('#list-tab').hasClass('active') && $.fn.DataTable.isDataTable('#appointmentsTable')) {
            $('#appointmentsTable').DataTable().ajax.reload();
        }
    });
    
    // Apply filters button
    $('#applyFiltersBtn').click(function() {
        calendar.refetchEvents();
    });
    
    // Counsellor and date selection for new appointment
    $('#counsellorSelect, #appointmentDate').change(function() {
        const counsellorId = $('#counsellorSelect').val();
        const appointmentDate = $('#appointmentDate').val();
        
        if (counsellorId && appointmentDate) {
            loadAvailableTimeSlots(counsellorId, appointmentDate);
        }
    });
    
    // Load available time slots
    function loadAvailableTimeSlots(counsellorId, date) {
        $('#timeSlotContainer').removeClass('d-none');
        $('#selectedTimeContainer').addClass('d-none');
        $('#startTime').val('');
        $('#endTime').val('');
        
        showLoading('Loading time slots...');
        
        $.ajax({
            url: 'processes/admin_appointments_time_slots.php',
            type: 'GET',
            data: {
                counsellor_id: counsellorId,
                date: date
            },
            dataType: 'json',
            success: function(slots) {
                hideLoading();
                let html = '';
                
                if (slots.length > 0) {
                    slots.forEach(function(slot) {
                        const btnClass = slot.available ? 'btn-outline-success time-slot-available' : 'btn-outline-danger time-slot-unavailable';
                        const disabled = slot.available ? '' : 'disabled';
                        
                        html += `
                        <button type="button" class="btn ${btnClass} select-time-slot" ${disabled}
                            data-start="${slot.start_time}" data-end="${slot.end_time}">
                            ${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}
                        </button>`;
                    });
                } else {
                    html = `<div class="alert alert-warning w-100">No available time slots found for this date.</div>`;
                }
                
                $('#timeSlots').html(html);
                
                // Add click event for time slot selection
                $('.select-time-slot').click(function() {
                    const startTime = $(this).data('start');
                    const endTime = $(this).data('end');
                    
                    $('#startTime').val(startTime);
                    $('#endTime').val(endTime);
                    $('#selectedTimeContainer').removeClass('d-none');
                    
                    // Highlight selected slot
                    $('.select-time-slot').removeClass('active');
                    $(this).addClass('active');
                });
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error loading time slots:", error);
                $('#timeSlots').html('<div class="alert alert-danger w-100">Error loading time slots. Please try again.</div>');
            }
        });
    }
    
    // Schedule appointment button
    $('#scheduleAppointmentBtn').click(function() {
        const counsellorId = $('#counsellorSelect').val();
        const clientId = $('#clientSelect').val();
        const appointmentDate = $('#appointmentDate').val();
        const startTime = $('#startTime').val();
        const endTime = $('#endTime').val();
        const notes = $('#appointmentNotes').val();
        
        if (!counsellorId || !clientId || !appointmentDate || !startTime || !endTime) {
            showAlert('Please fill all required fields and select a time slot', 'warning');
            return;
        }
        
        showLoading('Scheduling appointment...');
        
        $.ajax({
            url: 'processes/admin_appointments_add.php',
            type: 'POST',
            data: {
                counsellor_id: counsellorId,
                client_id: clientId,
                appointment_date: appointmentDate,
                start_time: startTime,
                end_time: endTime,
                notes: notes
            },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#addAppointmentModal').modal('hide');
                    
                    // Refresh calendar and table
                    calendar.refetchEvents();
                    if ($('#list-tab').hasClass('active') && $.fn.DataTable.isDataTable('#appointmentsTable')) {
                        $('#appointmentsTable').DataTable().ajax.reload();
                    }
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error scheduling appointment:", error);
                showAlert('Server error. Please try again: ' + error, 'danger');
            }
        });
    });
    
    // View appointment details
    $(document).on('click', '.view-appointment-btn', function() {
        const appointmentId = $(this).data('id');
        loadAppointmentDetails(appointmentId);
    });
    
    // Load appointment details
    function loadAppointmentDetails(appointmentId) {
        showLoading('Loading appointment details...');
        
        $.ajax({
            url: 'processes/admin_appointments_get.php',
            type: 'GET',
            data: { id: appointmentId },
            dataType: 'json',
            success: function(appointment) {
                hideLoading();
                $('#appointmentId').val(appointment.id);
                $('#clientName').text(appointment.client_name);
                $('#counsellorName').text(appointment.counsellor_name);
                $('#appointmentDateDisplay').text(appointment.appointment_date);
                $('#appointmentTimeDisplay').text(`${appointment.start_time.substring(0, 5)} - ${appointment.end_time.substring(0, 5)}`);
                $('#appointmentStatus').val(appointment.status);
                $('#viewAppointmentNotes').val(appointment.notes);
                
                $('#viewAppointmentModal').modal('show');
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error loading appointment details:", error);
                showAlert('Error loading appointment details: ' + error, 'danger');
            }
        });
    }
    
    // Save appointment changes
    $('#saveAppointmentChangesBtn').click(function() {
        const id = $('#appointmentId').val();
        const status = $('#appointmentStatus').val();
        const notes = $('#viewAppointmentNotes').val();
        
        showLoading('Saving changes...');
        
        $.ajax({
            url: 'processes/admin_appointments_update.php',
            type: 'POST',
            data: {
                id: id,
                status: status,
                notes: notes
            },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#viewAppointmentModal').modal('hide');
                    
                    // Refresh calendar and table
                    calendar.refetchEvents();
                    if ($('#list-tab').hasClass('active') && $.fn.DataTable.isDataTable('#appointmentsTable')) {
                        $('#appointmentsTable').DataTable().ajax.reload();
                    }
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error updating appointment:", error);
                showAlert('Server error. Please try again: ' + error, 'danger');
            }
        });
    });
    
    // Delete appointment
    $('#deleteAppointmentBtn').click(function() {
        const appointmentId = $('#appointmentId').val();
        
        if (confirm('Are you sure you want to delete this appointment? This action cannot be undone.')) {
            showLoading('Deleting appointment...');
            
            $.ajax({
                url: 'processes/admin_appointments_delete.php',
                type: 'POST',
                data: { id: appointmentId },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.success) {
                        showAlert(response.message, 'success');
                        $('#viewAppointmentModal').modal('hide');
                        
                        // Refresh calendar and table
                        calendar.refetchEvents();
                        if ($('#list-tab').hasClass('active') && $.fn.DataTable.isDataTable('#appointmentsTable')) {
                            $('#appointmentsTable').DataTable().ajax.reload();
                        }
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error("Error deleting appointment:", error);
                    showAlert('Server error. Please try again: ' + error, 'danger');
                }
            });
        }
    });
    
    // Helper functions
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
