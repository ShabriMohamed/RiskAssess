<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Counsellor Management - RiskAssess Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .counsellor-photo-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 50%; }
    </style>
</head>
<body>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fa-solid fa-user-tie"></i> Counsellor Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCounsellorModal"><i class="fa fa-plus"></i> Assign Counsellor</button>
    </div>
    <table id="counsellorsTable" class="table table-striped table-hover w-100">
        <thead>
            <tr>
                <th>#ID</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Email</th>
                <th>License</th>
                <th>Specialties</th>
                <th>Status</th>
                <th>Assigned</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Add Counsellor Modal -->
<div class="modal fade" id="addCounsellorModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addCounsellorForm" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header"><h5 class="modal-title">Assign Counsellor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label>Select User</label>
            <select name="user_id" class="form-select" required>
                <option value="">-- Select Staff User --</option>
                <?php
                require_once 'config.php';
                $users = $conn->query("SELECT id, name, email FROM users WHERE role = 'staff' AND id NOT IN (SELECT user_id FROM counsellors)");
                while($u = $users->fetch_assoc()) {
                    echo "<option value='{$u['id']}'>".htmlspecialchars($u['name'])." ({$u['email']})</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-2"><label>License Number</label>
          <input type="text" name="license_number" class="form-control"></div>
        <div class="mb-2"><label>Specialties (comma separated)</label>
          <input type="text" name="specialties" class="form-control"></div>
        <div class="mb-2"><label>Bio</label>
          <textarea name="bio" class="form-control"></textarea></div>
        <div class="mb-2"><label>Profile Photo</label>
          <input type="file" name="profile_photo" accept="image/*" class="form-control" required>
          <div class="form-text">JPEG, PNG, GIF. Max 2MB. Professional headshot recommended.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Assign</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Counsellor Modal -->
<div class="modal fade" id="editCounsellorModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editCounsellorForm" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header"><h5 class="modal-title">Edit Counsellor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editCounsellorId">
        <div class="mb-2"><label>License Number</label>
          <input type="text" name="license_number" id="editLicenseNumber" class="form-control"></div>
        <div class="mb-2"><label>Specialties (comma separated)</label>
          <input type="text" name="specialties" id="editSpecialties" class="form-control"></div>
        <div class="mb-2"><label>Bio</label>
          <textarea name="bio" id="editBio" class="form-control"></textarea></div>
        <div class="mb-2"><label>Profile Photo</label>
          <input type="file" name="profile_photo" accept="image/*" class="form-control">
          <div class="form-text">Leave blank to keep existing photo.</div>
        </div>
        <div id="currentPhoto" class="mb-2"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- JS: jQuery, DataTables, Bootstrap -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let table = $('#counsellorsTable').DataTable({
        ajax: 'processes/admin_counsellors_fetch.php',
        columns: [
            { data: 'id' },
            { data: 'photo' },
            { data: 'name' },
            { data: 'email' },
            { data: 'license_number' },
            { data: 'specialties' },
            { data: 'status' },
            { data: 'created_at' },
            { data: 'actions' }
        ],
        order: [[7, 'desc']]
    });

    // Add Counsellor
    $('#addCounsellorForm').submit(function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: 'processes/admin_counsellors_add.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                showAlert(res.message, res.success ? 'success' : 'danger');
                if(res.success) {
                    $('#addCounsellorModal').modal('hide');
                    $('#addCounsellorForm')[0].reset();
                    setTimeout(function() {
                        table.ajax.reload(null, false);
                    }, 350);
                }
            },
            error: function() {
                showAlert('Server error. Please try again.', 'danger');
            }
        });
    });

    // Edit Counsellor (populate modal)
    $(document).on('click', '.editCounsellorBtn', function() {
        let id = $(this).data('id');
        $.get('processes/admin_counsellors_get.php', {id}, function(c) {
            $('#editCounsellorId').val(c.id);
            $('#editLicenseNumber').val(c.license_number);
            $('#editSpecialties').val(c.specialties);
            $('#editBio').val(c.bio);
            if (c.profile_photo) {
                $('#currentPhoto').html(`<img src="uploads/counsellors/${c.profile_photo}" class="counsellor-photo-thumb mb-2">`);
            } else {
                $('#currentPhoto').html('');
            }
            $('#editCounsellorModal').modal('show');
        }, 'json');
    });

    // Save Edit Counsellor
    $('#editCounsellorForm').submit(function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: 'processes/admin_counsellors_edit.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                showAlert(res.message, res.success ? 'success' : 'danger');
                if(res.success) {
                    $('#editCounsellorModal').modal('hide');
                    setTimeout(function() {
                        table.ajax.reload(null, false);
                    }, 350);
                }
            },
            error: function() {
                showAlert('Server error. Please try again.', 'danger');
            }
        });
    });

    // Delete Counsellor
    $(document).on('click', '.deleteCounsellorBtn', function() {
        let id = $(this).data('id');
        if(confirm('Are you sure you want to remove this counsellor?')) {
            $.post('processes/admin_counsellors_delete.php', {id}, function(res) {
                showAlert(res.message, res.success ? 'success' : 'danger');
                if(res.success) table.ajax.reload(null, false);
            }, 'json');
        }
    });

    function showAlert(message, type) {
        let alertHtml = `<div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3" role="alert" style="z-index:9999;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        $('body').append(alertHtml);
        setTimeout(() => { $('.alert').alert('close'); }, 3500);
    }
});
</script>
</body>
</html>
