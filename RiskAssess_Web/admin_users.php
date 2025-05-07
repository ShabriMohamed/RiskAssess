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
    <title>User Management - RiskAssess Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fa-solid fa-users"></i> User Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fa fa-plus"></i> Add User</button>
    </div>
    <table id="usersTable" class="table table-striped table-hover w-100">
        <thead>
            <tr>
                <th>#ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Registered</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addUserForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><label>Name</label>
          <input type="text" name="name" class="form-control" required></div>
        <div class="mb-2"><label>Email</label>
          <input type="email" name="email" class="form-control" required></div>
        <div class="mb-2"><label>Password</label>
          <input type="password" name="password" class="form-control" required minlength="8"></div>
        <div class="mb-2"><label>Role</label>
          <select name="role" class="form-select" required>
            <option value="customer">Customer</option>
            <option value="staff">Counsellor</option>
            <option value="admin">Admin</option>
          </select></div>
        <div class="mb-2"><label>Address</label>
          <input type="text" name="address" class="form-control"></div>
        <div class="mb-2"><label>Telephone</label>
          <input type="text" name="telephone" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Add User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editUserForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editUserId">
        <div class="mb-2"><label>Name</label>
          <input type="text" name="name" id="editName" class="form-control" required></div>
        <div class="mb-2"><label>Email</label>
          <input type="email" name="email" id="editEmail" class="form-control" required></div>
        <div class="mb-2"><label>Role</label>
          <select name="role" id="editRole" class="form-select" required>
            <option value="customer">Customer</option>
            <option value="staff">Counsellor</option>
            <option value="admin">Admin</option>
          </select></div>
        <div class="mb-2"><label>Status</label>
          <select name="status" id="editStatus" class="form-select" required>
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
          </select></div>
        <div class="mb-2"><label>Address</label>
          <input type="text" name="address" id="editAddress" class="form-control"></div>
        <div class="mb-2"><label>Telephone</label>
          <input type="text" name="telephone" id="editTelephone" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwdModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="resetPwdForm" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="resetPwdUserId">
        <div class="mb-2"><label>New Password</label>
          <input type="password" name="password" class="form-control" required minlength="8"></div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-warning">Reset Password</button>
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
    let table = $('#usersTable').DataTable({
        ajax: 'processes/admin_users_fetch.php',
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'email' },
            { data: 'role' },
            { data: 'status' },
            { data: 'created_at' },
            { data: 'actions' }
        ],
        order: [[5, 'desc']]
    });

    // Add User
    $('#addUserForm').submit(function(e) {
        e.preventDefault();
        $.post('processes/admin_users_add.php', $(this).serialize(), function(res) {
            alert(res.message);
            if(res.success) {
                $('#addUserModal').modal('hide');
                table.ajax.reload();
            }
        }, 'json');
    });

    // Edit User (populate modal)
    $(document).on('click', '.editUserBtn', function() {
        let id = $(this).data('id');
        $.get('processes/admin_users_get.php', {id}, function(user) {
            $('#editUserId').val(user.id);
            $('#editName').val(user.name);
            $('#editEmail').val(user.email);
            $('#editRole').val(user.role);
            $('#editStatus').val(user.status);
            $('#editAddress').val(user.address);
            $('#editTelephone').val(user.telephone);
            $('#editUserModal').modal('show');
        }, 'json');
    });

    // Save Edit User
    $('#editUserForm').submit(function(e) {
        e.preventDefault();
        $.post('processes/admin_users_edit.php', $(this).serialize(), function(res) {
            alert(res.message);
            if(res.success) {
                $('#editUserModal').modal('hide');
                table.ajax.reload();
            }
        }, 'json');
    });

    // Reset Password
    $(document).on('click', '.resetPwdBtn', function() {
        $('#resetPwdUserId').val($(this).data('id'));
        $('#resetPwdModal').modal('show');
    });
    $('#resetPwdForm').submit(function(e) {
        e.preventDefault();
        $.post('processes/admin_users_resetpwd.php', $(this).serialize(), function(res) {
            alert(res.message);
            if(res.success) $('#resetPwdModal').modal('hide');
        }, 'json');
    });

    // Enable/Disable User
    $(document).on('click', '.toggleUserBtn', function() {
        let id = $(this).data('id');
        let action = $(this).data('action');
        if(confirm('Are you sure you want to ' + action + ' this user?')) {
            $.post('processes/admin_users_toggle.php', {id, action}, function(res) {
                alert(res.message);
                if(res.success) table.ajax.reload();
            }, 'json');
        }
    });

    // Delete User
    $(document).on('click', '.deleteUserBtn', function() {
        let id = $(this).data('id');
        if(confirm('Are you sure you want to permanently delete this user?')) {
            $.post('processes/admin_users_delete.php', {id}, function(res) {
                alert(res.message);
                if(res.success) table.ajax.reload();
            }, 'json');
        }
    });
});
</script>
</body>
</html>
