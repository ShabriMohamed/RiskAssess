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
    <title>Availability Scheduling - RiskAssess Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- FullCalendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <!-- Time Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .fc-event {cursor: pointer;}
        .fc-event-title {font-weight: 500;}
        .fc-day-today {background-color: rgba(79, 140, 255, 0.05) !important;}
        .fc-day-past {background-color: rgba(0, 0, 0, 0.02);}
        .fc-col-header-cell {background-color: #f8f9fa; font-weight: 600;}
        .fc-toolbar-title {font-size: 1.5rem !important; font-weight: 600;}
        .time-slot {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #e9ecef;
        }
        .time-slot:hover {background-color: #e9ecef;}
        .time-slot-actions {display: flex; gap: 8px;}
        .time-slot-day {font-weight: 600; color: #4f8cff;}
        .nav-tabs .nav-link.active {
            border-color: transparent;
            border-bottom: 3px solid #4f8cff;
            font-weight: 600;
        }
        .nav-tabs .nav-link {color: #495057; border: none; padding: 0.75rem 1rem;}
        .nav-tabs {border-bottom: 1px solid #dee2e6; margin-bottom: 1.5rem;}
        .exception-date {font-weight: 600; color: #dc3545;}
        .exception-available {color: #28a745;}
        .exception-unavailable {color: #dc3545;}
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
        <h4 class="mb-0"><i class="fa-solid fa-clock"></i> Availability Scheduling</h4>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="refreshCalendarBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-sm btn-primary" id="openBulkModalBtn">
                <i class="fas fa-calendar-plus"></i> Bulk Schedule
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-user-tie"></i> Select Counsellor
                </div>
                <div class="card-body">
                    <select id="counsellorSelect" class="form-select mb-3">
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
                    <div id="counsellorInfo" class="d-none">
                        <div class="text-center mb-3">
                            <img id="counsellorPhoto" src="" alt="Counsellor Photo" class="rounded-circle" width="80" height="80">
                            <h5 id="counsellorName" class="mt-2 mb-0"></h5>
                            <p id="counsellorSpecialties" class="text-muted small"></p>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" id="addRegularScheduleBtn">
                                <i class="fas fa-plus-circle"></i> Add Regular Schedule
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="addExceptionBtn">
                                <i class="fas fa-calendar-times"></i> Add Exception Date
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3 d-none" id="scheduleCard">
                <div class="card-header bg-light">
                    <ul class="nav nav-tabs card-header-tabs" id="scheduleTabs">
                        <li class="nav-item">
                            <a class="nav-link active" id="regular-tab" data-bs-toggle="tab" href="#regularSchedule">Regular</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="exceptions-tab" data-bs-toggle="tab" href="#exceptionsSchedule">Exceptions</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="regularSchedule">
                            <div id="regularScheduleList" class="mb-3">
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                    <p>No regular schedule set</p>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="exceptionsSchedule">
                            <div id="exceptionsScheduleList" class="mb-3">
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                    <p>No exceptions set</p>
                                </div>
                            </div>
                        </div>
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

<!-- Add Regular Schedule Modal -->
<div class="modal fade" id="regularScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Regular Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="regularScheduleId" name="id">
                <input type="hidden" id="regularCounsellorId" name="counsellor_id">
                
                <div class="mb-3">
                    <label class="form-label">Day of Week</label>
                    <select id="dayOfWeek" class="form-select" required>
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                        <option value="0">Sunday</option>
                    </select>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Time</label>
                        <input type="text" id="startTime" class="form-control time-picker" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Time</label>
                        <input type="text" id="endTime" class="form-control time-picker" required>
                    </div>
                </div>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="isAvailable" value="1" checked>
                    <label class="form-check-label" for="isAvailable">Available for Appointments</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveRegularScheduleBtn" class="btn btn-primary">Save Schedule</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Exception Modal -->
<div class="modal fade" id="exceptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Exception Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="exceptionId">
                <input type="hidden" id="exceptionCounsellorId">
                
                <div class="mb-3">
                    <label class="form-label">Exception Date</label>
                    <input type="text" id="exceptionDate" class="form-control date-picker" required>
                </div>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="exceptionIsAvailable" value="1">
                    <label class="form-check-label" for="exceptionIsAvailable">Available on this date</label>
                    <div class="form-text">Uncheck for days off, holidays, etc.</div>
                </div>
                
                <div id="exceptionTimeFields">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="text" id="exceptionStartTime" class="form-control time-picker">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="text" id="exceptionEndTime" class="form-control time-picker">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Reason (optional)</label>
                    <input type="text" id="exceptionReason" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveExceptionBtn" class="btn btn-primary">Save Exception</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Schedule Modal -->
<div class="modal fade" id="bulkScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Schedule Setup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Counsellor</label>
                    <select id="bulkCounsellorId" class="form-select" required>
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
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">Weekday Schedule</div>
                            <div class="card-body">
                                <div class="form-check mb-2">
                                    <input class="form-check-input weekday-check" type="checkbox" id="monday" value="1" checked>
                                    <label class="form-check-label" for="monday">Monday</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input weekday-check" type="checkbox" id="tuesday" value="2" checked>
                                    <label class="form-check-label" for="tuesday">Tuesday</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input weekday-check" type="checkbox" id="wednesday" value="3" checked>
                                    <label class="form-check-label" for="wednesday">Wednesday</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input weekday-check" type="checkbox" id="thursday" value="4" checked>
                                    <label class="form-check-label" for="thursday">Thursday</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input weekday-check" type="checkbox" id="friday" value="5" checked>
                                    <label class="form-check-label" for="friday">Friday</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input weekday-check" type="checkbox" id="saturday" value="6">
                                    <label class="form-check-label" for="saturday">Saturday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input weekday-check" type="checkbox" id="sunday" value="0">
                                    <label class="form-check-label" for="sunday">Sunday</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">Time Schedule</div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Start Time</label>
                                        <input type="text" id="bulkStartTime" class="form-control time-picker" value="09:00">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">End Time</label>
                                        <input type="text" id="bulkEndTime" class="form-control time-picker" value="17:00">
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="bulkIsAvailable" checked>
                                    <label class="form-check-label" for="bulkIsAvailable">Available for Appointments</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This will create a regular weekly schedule for the selected counsellor. Any existing schedule for the selected days will be replaced.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="applyBulkScheduleBtn" class="btn btn-primary">Apply Bulk Schedule</button>
            </div>
        </div>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
$(document).ready(function() {
    console.log("Document ready");
    
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
            const eventData = {
                id: info.event.id,
                title: info.event.title,
                start: info.event.start,
                end: info.event.end,
                ...info.event.extendedProps
            };
            
            if (eventType === 'availability') {
                // Edit regular schedule
                $('#regularScheduleId').val(eventData.id);
                $('#regularCounsellorId').val(eventData.counsellor_id);
                $('#dayOfWeek').val(eventData.day_of_week);
                $('#startTime').val(formatTime(eventData.start));
                $('#endTime').val(formatTime(eventData.end));
                $('#isAvailable').prop('checked', eventData.is_available === 1);
                $('#regularScheduleModal').modal('show');
            } else if (eventType === 'exception') {
                // Edit exception
                $('#exceptionId').val(eventData.id);
                $('#exceptionCounsellorId').val(eventData.counsellor_id);
                $('#exceptionDate').val(formatDate(eventData.start));
                $('#exceptionIsAvailable').prop('checked', eventData.is_available === 1);
                $('#exceptionStartTime').val(eventData.start_time ? formatTime(eventData.start) : '');
                $('#exceptionEndTime').val(eventData.end_time ? formatTime(eventData.end) : '');
                $('#exceptionReason').val(eventData.reason || '');
                toggleExceptionTimeFields(eventData.is_available === 1);
                $('#exceptionModal').modal('show');
            }
        },
        events: function(info, successCallback, failureCallback) {
            const counsellorId = $('#counsellorSelect').val();
            if (!counsellorId) {
                successCallback([]);
                return;
            }
            
            $.ajax({
                url: 'processes/admin_availability_events.php',
                type: 'GET',
                data: {
                    counsellor_id: counsellorId,
                    start: info.startStr,
                    end: info.endStr
                },
                success: function(result) {
                    successCallback(result);
                },
                error: function(xhr, status, error) {
                    console.error("Error loading events:", error);
                    failureCallback({ message: 'Error loading events' });
                    showAlert('Error loading calendar events: ' + error, 'danger');
                }
            });
        }
    });
    calendar.render();
    
    // Initialize time pickers
    flatpickr('.time-picker', {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 15
    });
    
    // Initialize date picker
    flatpickr('.date-picker', {
        dateFormat: "Y-m-d",
        minDate: "today"
    });
    
    // Open bulk schedule modal
    $('#openBulkModalBtn').click(function() {
        $('#bulkScheduleModal').modal('show');
    });
    
    // Counsellor select change
    $('#counsellorSelect').change(function() {
        const counsellorId = $(this).val();
        if (counsellorId) {
            loadCounsellorInfo(counsellorId);
            $('#counsellorInfo').removeClass('d-none');
            $('#scheduleCard').removeClass('d-none');
            loadRegularSchedule(counsellorId);
            loadExceptions(counsellorId);
            calendar.refetchEvents();
        } else {
            $('#counsellorInfo').addClass('d-none');
            $('#scheduleCard').addClass('d-none');
            calendar.refetchEvents();
        }
    });
    
    // Add regular schedule button
    $('#addRegularScheduleBtn').click(function() {
        const counsellorId = $('#counsellorSelect').val();
        $('#regularScheduleId').val('');
        $('#regularCounsellorId').val(counsellorId);
        $('#dayOfWeek').val(1);
        $('#startTime').val('09:00');
        $('#endTime').val('17:00');
        $('#isAvailable').prop('checked', true);
        $('#regularScheduleModal').modal('show');
    });
    
    // Save regular schedule
    $('#saveRegularScheduleBtn').click(function() {
        console.log('Save regular schedule button clicked');
        
        const id = $('#regularScheduleId').val();
        const counsellor_id = $('#regularCounsellorId').val();
        const day_of_week = $('#dayOfWeek').val();
        const start_time = $('#startTime').val();
        const end_time = $('#endTime').val();
        const is_available = $('#isAvailable').is(':checked') ? 1 : 0;
        
        if (!counsellor_id || !day_of_week || !start_time || !end_time) {
            showAlert('Please fill all required fields', 'warning');
            return;
        }
        
        showLoading('Saving schedule...');
        
        $.ajax({
            url: 'processes/admin_availability_save_regular.php',
            type: 'POST',
            data: {
                id: id,
                counsellor_id: counsellor_id,
                day_of_week: day_of_week,
                start_time: start_time,
                end_time: end_time,
                is_available: is_available
            },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#regularScheduleModal').modal('hide');
                    loadRegularSchedule(counsellor_id);
                    calendar.refetchEvents();
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error saving regular schedule:", error);
                console.log(xhr.responseText);
                showAlert('Server error. Please try again: ' + error, 'danger');
            }
        });
    });
    
    // Add exception button
    $('#addExceptionBtn').click(function() {
        const counsellorId = $('#counsellorSelect').val();
        $('#exceptionId').val('');
        $('#exceptionCounsellorId').val(counsellorId);
        $('#exceptionDate').val('');
        $('#exceptionIsAvailable').prop('checked', false);
        $('#exceptionStartTime').val('');
        $('#exceptionEndTime').val('');
        $('#exceptionReason').val('');
        toggleExceptionTimeFields(false);
        $('#exceptionModal').modal('show');
    });
    
    // Toggle exception time fields based on availability
    $('#exceptionIsAvailable').change(function() {
        toggleExceptionTimeFields($(this).is(':checked'));
    });
    
    function toggleExceptionTimeFields(show) {
        if (show) {
            $('#exceptionTimeFields').show();
        } else {
            $('#exceptionTimeFields').hide();
        }
    }
    
    // Save exception
    $('#saveExceptionBtn').click(function() {
        console.log('Save exception button clicked');
        
        const id = $('#exceptionId').val();
        const counsellor_id = $('#exceptionCounsellorId').val();
        const exception_date = $('#exceptionDate').val();
        const is_available = $('#exceptionIsAvailable').is(':checked') ? 1 : 0;
        const start_time = $('#exceptionStartTime').val();
        const end_time = $('#exceptionEndTime').val();
        const reason = $('#exceptionReason').val();
        
        if (!counsellor_id || !exception_date) {
            showAlert('Please fill all required fields', 'warning');
            return;
        }
        
        if (is_available && (!start_time || !end_time)) {
            showAlert('Start and end times are required when available', 'warning');
            return;
        }
        
        showLoading('Saving exception...');
        
        $.ajax({
            url: 'processes/admin_availability_save_exception.php',
            type: 'POST',
            data: {
                id: id,
                counsellor_id: counsellor_id,
                exception_date: exception_date,
                is_available: is_available,
                start_time: start_time,
                end_time: end_time,
                reason: reason
            },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#exceptionModal').modal('hide');
                    loadExceptions(counsellor_id);
                    calendar.refetchEvents();
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error saving exception:", error);
                console.log(xhr.responseText);
                showAlert('Server error. Please try again: ' + error, 'danger');
            }
        });
    });
    
    // Apply bulk schedule
    $('#applyBulkScheduleBtn').click(function() {
        console.log('Apply bulk schedule button clicked');
        
        const counsellorId = $('#bulkCounsellorId').val();
        if (!counsellorId) {
            showAlert('Please select a counsellor', 'warning');
            return;
        }
        
        const selectedDays = [];
        $('.weekday-check:checked').each(function() {
            selectedDays.push($(this).val());
        });
        
        if (selectedDays.length === 0) {
            showAlert('Please select at least one day of the week', 'warning');
            return;
        }
        
        const startTime = $('#bulkStartTime').val();
        const endTime = $('#bulkEndTime').val();
        const isAvailable = $('#bulkIsAvailable').is(':checked') ? 1 : 0;
        
        if (!startTime || !endTime) {
            showAlert('Please set start and end times', 'warning');
            return;
        }
        
        showLoading('Processing bulk schedule...');
        
        $.ajax({
            url: 'processes/admin_availability_bulk_schedule.php',
            type: 'POST',
            data: {
                counsellor_id: counsellorId,
                days: selectedDays,
                start_time: startTime,
                end_time: endTime,
                is_available: isAvailable
            },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#bulkScheduleModal').modal('hide');
                    
                    // If the current selected counsellor is the same as in bulk form
                    if ($('#counsellorSelect').val() == counsellorId) {
                        loadRegularSchedule(counsellorId);
                        calendar.refetchEvents();
                    }
                    
                    // Update the counsellor select if it wasn't already selected
                    if ($('#counsellorSelect').val() != counsellorId) {
                        $('#counsellorSelect').val(counsellorId).trigger('change');
                    }
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error in bulk scheduling:", error);
                console.log(xhr.responseText);
                showAlert('Server error. Please try again: ' + error, 'danger');
            }
        });
    });
    
    // Refresh calendar button
    $('#refreshCalendarBtn').click(function() {
        calendar.refetchEvents();
    });
    
    // Load counsellor info
    function loadCounsellorInfo(counsellorId) {
        $.ajax({
            url: 'processes/admin_counsellors_get.php',
            type: 'GET',
            data: { id: counsellorId },
            dataType: 'json',
            success: function(counsellor) {
                $('#counsellorName').text(counsellor.name);
                $('#counsellorSpecialties').text(counsellor.specialties || 'No specialties listed');
                if (counsellor.profile_photo) {
                    $('#counsellorPhoto').attr('src', 'uploads/counsellors/' + counsellor.profile_photo);
                } else {
                    $('#counsellorPhoto').attr('src', 'assets/img/default-profile.png');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error loading counsellor info:", error);
                showAlert('Error loading counsellor information: ' + error, 'danger');
            }
        });
    }
    
    // Load regular schedule
    function loadRegularSchedule(counsellorId) {
        $.ajax({
            url: 'processes/admin_availability_get_regular.php',
            type: 'GET',
            data: { counsellor_id: counsellorId },
            dataType: 'json',
            success: function(schedules) {
                let html = '';
                if (schedules.length > 0) {
                    schedules.forEach(function(schedule) {
                        const dayName = getDayName(schedule.day_of_week);
                        const availabilityClass = schedule.is_available == 1 ? 'text-success' : 'text-danger';
                        const availabilityText = schedule.is_available == 1 ? 'Available' : 'Unavailable';
                        
                        html += `
                        <div class="time-slot">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="time-slot-day">${dayName}</span>
                                    <div>${schedule.start_time.substring(0, 5)} - ${schedule.end_time.substring(0, 5)}</div>
                                    <div class="${availabilityClass}"><i class="fas fa-circle fa-xs"></i> ${availabilityText}</div>
                                </div>
                                <div class="time-slot-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-regular-btn" 
                                        data-id="${schedule.id}" 
                                        data-counsellor-id="${schedule.counsellor_id}"
                                        data-day="${schedule.day_of_week}"
                                        data-start="${schedule.start_time}"
                                        data-end="${schedule.end_time}"
                                        data-available="${schedule.is_available}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-regular-btn" 
                                        data-id="${schedule.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                    });
                } else {
                    html = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-day fa-2x mb-2"></i>
                        <p>No regular schedule set</p>
                    </div>`;
                }
                $('#regularScheduleList').html(html);
                
                // Add event listeners for edit and delete buttons
                $('.edit-regular-btn').click(function() {
                    const id = $(this).data('id');
                    const counsellorId = $(this).data('counsellor-id');
                    const day = $(this).data('day');
                    const start = $(this).data('start');
                    const end = $(this).data('end');
                    const available = $(this).data('available');
                    
                    $('#regularScheduleId').val(id);
                    $('#regularCounsellorId').val(counsellorId);
                    $('#dayOfWeek').val(day);
                    $('#startTime').val(start.substring(0, 5));
                    $('#endTime').val(end.substring(0, 5));
                    $('#isAvailable').prop('checked', available == 1);
                    
                    $('#regularScheduleModal').modal('show');
                });
                
                $('.delete-regular-btn').click(function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to delete this schedule?')) {
                        deleteRegularSchedule(id);
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error("Error loading regular schedule:", error);
                showAlert('Error loading regular schedule: ' + error, 'danger');
            }
        });
    }
    
    // Load exceptions
    function loadExceptions(counsellorId) {
        $.ajax({
            url: 'processes/admin_availability_get_exceptions.php',
            type: 'GET',
            data: { counsellor_id: counsellorId },
            dataType: 'json',
            success: function(exceptions) {
                let html = '';
                if (exceptions.length > 0) {
                    exceptions.forEach(function(exception) {
                        const availabilityClass = exception.is_available == 1 ? 'exception-available' : 'exception-unavailable';
                        const availabilityIcon = exception.is_available == 1 ? 'fa-check-circle' : 'fa-times-circle';
                        const availabilityText = exception.is_available == 1 ? 'Available' : 'Unavailable';
                        const timeInfo = exception.is_available == 1 && exception.start_time ? 
                            `${exception.start_time.substring(0, 5)} - ${exception.end_time.substring(0, 5)}` : '';
                        const reasonText = exception.reason ? `<div class="text-muted small">${exception.reason}</div>` : '';
                        
                        html += `
                        <div class="time-slot">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="exception-date">${formatDateDisplay(exception.exception_date)}</span>
                                    <div class="${availabilityClass}"><i class="fas ${availabilityIcon} fa-xs"></i> ${availabilityText}</div>
                                    <div>${timeInfo}</div>
                                    ${reasonText}
                                </div>
                                <div class="time-slot-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-exception-btn" 
                                        data-id="${exception.id}" 
                                        data-counsellor-id="${exception.counsellor_id}"
                                        data-date="${exception.exception_date}"
                                        data-available="${exception.is_available}"
                                        data-start="${exception.start_time || ''}"
                                        data-end="${exception.end_time || ''}"
                                        data-reason="${exception.reason || ''}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-exception-btn" 
                                        data-id="${exception.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                    });
                } else {
                    html = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p>No exceptions set</p>
                    </div>`;
                }
                $('#exceptionsScheduleList').html(html);
                
                // Add event listeners for edit and delete buttons
                $('.edit-exception-btn').click(function() {
                    const id = $(this).data('id');
                    const counsellorId = $(this).data('counsellor-id');
                    const date = $(this).data('date');
                    const available = $(this).data('available');
                    const start = $(this).data('start');
                    const end = $(this).data('end');
                    const reason = $(this).data('reason');
                    
                    $('#exceptionId').val(id);
                    $('#exceptionCounsellorId').val(counsellorId);
                    $('#exceptionDate').val(date);
                    $('#exceptionIsAvailable').prop('checked', available == 1);
                    $('#exceptionStartTime').val(start);
                    $('#exceptionEndTime').val(end);
                    $('#exceptionReason').val(reason);
                    
                    toggleExceptionTimeFields(available == 1);
                    
                    $('#exceptionModal').modal('show');
                });
                
                $('.delete-exception-btn').click(function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to delete this exception?')) {
                        deleteException(id);
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error("Error loading exceptions:", error);
                showAlert('Error loading exceptions: ' + error, 'danger');
            }
        });
    }
    
    // Delete regular schedule
    function deleteRegularSchedule(id) {
        showLoading('Deleting schedule...');
        
        $.ajax({
            url: 'processes/admin_availability_delete_regular.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message, 'success');
                    const counsellorId = $('#counsellorSelect').val();
                    loadRegularSchedule(counsellorId);
                    calendar.refetchEvents();
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error deleting regular schedule:", error);
                showAlert('Server error. Please try again: ' + error, 'danger');
            }
        });
    }
    
    // Delete exception
    function deleteException(id) {
        showLoading('Deleting exception...');
        
        $.ajax({
            url: 'processes/admin_availability_delete_exception.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message, 'success');
                    const counsellorId = $('#counsellorSelect').val();
                    loadExceptions(counsellorId);
                    calendar.refetchEvents();
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Error deleting exception:", error);
                showAlert('Server error. Please try again: ' + error, 'danger');
            }
        });
    }
    
    // Helper functions
    function getDayName(dayNum) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return days[dayNum];
    }
    
    function formatTime(date) {
        if (!date) return '';
        if (typeof date === 'string') {
            // If it's already in HH:MM format
            if (date.match(/^\d{2}:\d{2}(:\d{2})?$/)) {
                return date.substring(0, 5);
            }
            date = new Date(date);
        }
        return date.toTimeString().substring(0, 5);
    }
    
    function formatDate(date) {
        if (!date) return '';
        if (typeof date === 'string') {
            // If it's already in YYYY-MM-DD format
            if (date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                return date;
            }
            date = new Date(date);
        }
        return date.toISOString().substring(0, 10);
    }
    
    function formatDateDisplay(dateStr) {
        const date = new Date(dateStr);
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
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
