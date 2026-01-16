<?php
session_start();
require_once '../includes/functions.php';
require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management - Sophen Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-gray: #ecf0f1;
            --dark-gray: #34495e;
            --admin-color: #8e44ad;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        }

        /* Sidebar styles */
        .sidebar {
            background: linear-gradient(180deg, var(--admin-color), #7d3c98);
            width: 280px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-logo {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo i {
            font-size: 30px;
            color: white;
        }

        .sidebar-title {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin: 5px 15px;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .main-content {
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .navbar-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .navbar-btn {
            padding: 8px 12px;
            background: var(--admin-color);
            color: white;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .navbar-btn:hover {
            background: #7d3c98;
            color: white;
            transform: translateY(-1px);
        }

        .profile-content {
            padding: 30px;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .section-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: var(--admin-color);
        }

        .btn-primary-custom {
            background: var(--admin-color);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            background: #7d3c98;
            transform: translateY(-1px);
        }

        .table-container {
            padding: 0;
        }

        .profile-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }

        .profile-table thead {
            background: var(--light-gray);
        }

        .profile-table th {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 20px;
            border: none;
        }

        .profile-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .profile-table tbody tr:hover {
            background: rgba(142, 68, 173, 0.05);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 3px;
        }

        .user-username {
            font-size: 0.85rem;
            color: var(--dark-gray);
            opacity: 0.8;
        }

        .role-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            display: inline-block;
        }

        .role-admin {
            background: rgba(142, 68, 173, 0.1);
            color: var(--admin-color);
        }

        .role-security {
            background: rgba(230, 126, 34, 0.1);
            color: #e67e22;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
        }

        .table-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-edit {
            background: var(--secondary-color);
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-remove {
            background: var(--danger-color);
            color: white;
        }

        .btn-remove:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        .btn-disabled {
            background: #bdc3c7;
            color: #7f8c8d;
            cursor: not-allowed;
        }

        .form-section {
            padding: 25px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            font-size: 0.9rem;
        }

        .form-control-custom {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: var(--admin-color);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
        }

        .form-select-custom {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            transition: border-color 0.3s ease;
        }

        .form-select-custom:focus {
            border-color: var(--admin-color);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            margin-top: 20px;
        }

        .btn-save {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background: #229954;
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #7f8c8d;
            transform: translateY(-1px);
        }

        .alert-custom {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: none;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--dark-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Modal overrides */
        .modal-header {
            background: var(--admin-color);
            color: white;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 700;
        }

        .btn-close {
            filter: brightness(0) invert(1);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .profile-content {
                padding: 20px;
            }

            .form-row {
                flex-direction: column;
                gap: 15px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .table-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-home"></i>
            </div>
            <h3 class="sidebar-title">Sophen</h3>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="overview.php" class="nav-link">
                    <i class="fas fa-eye"></i>
                    Overview
                </a>
            </div>
            <div class="nav-item">
                <a href="manage-visitors.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Manage Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="manage-residents.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Manage Residents
                </a>
            </div>
            <div class="nav-item">
                <a href="profile.php" class="nav-link active">
                    <i class="fas fa-user-cog"></i>
                    Profile
                </a>
            </div>
            <div class="nav-item">
                <a href="blocked-visitors.php" class="nav-link">
                    <i class="fas fa-user-slash"></i>
                    Blocked Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <h1 class="page-title">Profile</h1>
            <div class="navbar-actions">
                <a href="index.php" class="navbar-btn">
                    <i class="fas fa-tachometer-alt"></i>
                </a>
                <a href="logout.php" class="navbar-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Success/Error Alerts -->
            <div class="alert alert-success alert-custom" id="successAlert" style="display: none;">
                <i class="fas fa-check-circle me-2"></i>
                <span id="successMessage">Action completed successfully</span>
            </div>
            <div class="alert alert-danger alert-custom" id="errorAlert" style="display: none;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="errorMessage">An error occurred</span>
            </div>

            <!-- System Users Section -->
            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-users-cog"></i>
                        System Users
                    </h3>
                    <button class="btn btn-primary btn-primary-custom" onclick="showNewProfileForm()">
                        <i class="fas fa-plus me-2"></i>New Profile
                    </button>
                </div>

                <div class="table-container">
                    <table class="table profile-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- New Profile Form -->
            <div class="section-card" id="newProfileCard" style="display: none;">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-user-plus"></i>
                        New Profile
                    </h3>
                    <button class="btn btn-secondary" onclick="hideNewProfileForm()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="form-section">
                    <form id="newProfileForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control-custom" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control-custom" name="last_name" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control-custom" name="username" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select class="form-select-custom" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="administrator">Administrator</option>
                                    <option value="security">Security</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control-custom" name="password" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control-custom" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="hideNewProfileForm()">Cancel</button>
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save me-2"></i>Create Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="section-card" id="editProfileCard" style="display: none;">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-user-edit"></i>
                        Edit Profile
                    </h3>
                    <button class="btn btn-secondary" onclick="hideEditProfileForm()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="form-section">
                    <form id="editProfileForm">
                        <input type="hidden" name="user_id" id="editUserId">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control-custom" name="first_name" id="editFirstName" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control-custom" name="last_name" id="editLastName" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control-custom" name="username" id="editUsername" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select class="form-select-custom" name="role" id="editRole" required>
                                    <option value="administrator">Administrator</option>
                                    <option value="security">Security</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password (Leave blank to keep current)</label>
                                <input type="password" class="form-control-custom" name="password">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control-custom" name="confirm_password">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="hideEditProfileForm()">Cancel</button>
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Load users on page ready
        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
        });

        async function loadUsers() {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = `<tr><td colspan="5" class="text-center">Loading users...</td></tr>`;
            try {
                const res = await fetch('process/profile-management.php?action=list_users');
                const data = await res.json();
                if (!data.success) { throw new Error(data.message || 'Failed to load users'); }
                renderUsers(data.users || []);
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-danger">${err.message}</td></tr>`;
            }
        }

        function renderUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            if (!users || users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <div>No users found. Create a new profile to get started.</div>
                </td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            users.forEach(u => appendUserRow(u));
        }

        function roleBadgeHtml(role_name) {
            const r = (role_name || '').toLowerCase();
            if (r === 'administrator') {
                return '<span class="role-badge role-admin">Administrator</span>';
            }
            return '<span class="role-badge role-security">Security</span>';
        }

        function appendUserRow(u) {
            const tbody = document.getElementById('usersTableBody');
            const tr = document.createElement('tr');
            const fullName = `${u.first_name || ''} ${u.last_name || ''}`.trim();
            const created = u.created_at ? new Date(u.created_at).toLocaleString() : '';
            tr.innerHTML = `
                <td>
                    <div class="user-info">
                        <div class="user-name">${escapeHtml(fullName)}</div>
                        <div class="user-username">${escapeHtml(u.username || '')}</div>
                    </div>
                </td>
                <td><strong>${escapeHtml(u.username || '')}</strong></td>
                <td>${roleBadgeHtml(u.role_name)}</td>
                <td>${escapeHtml(created)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="table-btn btn-edit" onclick="editProfile(${u.user_id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        ${u.username === 'admin' ? '<button class="table-btn btn-disabled" disabled><i class="fas fa-shield-alt"></i> System</button>' : '<button class="table-btn btn-remove" onclick="removeProfile('+u.user_id+')"><i class="fas fa-trash"></i> Remove</button>'}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Show/Hide New Profile Form
        function showNewProfileForm() {
            document.getElementById('newProfileCard').style.display = 'block';
            document.getElementById('newProfileForm').reset();
            document.querySelector('[name="first_name"]').focus();
        }

        function hideNewProfileForm() {
            document.getElementById('newProfileCard').style.display = 'none';
            document.getElementById('newProfileForm').reset();
        }

        // Show/Hide Edit Profile Form
        function showEditProfileForm() {
            document.getElementById('editProfileCard').style.display = 'block';
        }

        function hideEditProfileForm() {
            document.getElementById('editProfileCard').style.display = 'none';
            document.getElementById('editProfileForm').reset();
        }

        // Edit Profile Function
        async function editProfile(userId) {
            try {
                const res = await fetch(`process/profile-management.php?action=get_user&user_id=${encodeURIComponent(userId)}`);
                const data = await res.json();
                if (!data.success) { throw new Error(data.message || 'Failed to load user'); }

                const u = data.user;
                document.getElementById('editUserId').value = u.user_id;
                document.getElementById('editFirstName').value = u.first_name || '';
                document.getElementById('editLastName').value = u.last_name || '';
                document.getElementById('editUsername').value = u.username || '';
                const roleLower = (u.role_name || '').toLowerCase();
                document.getElementById('editRole').value = roleLower === 'administrator' ? 'administrator' : 'security';
                showEditProfileForm();
            } catch (err) {
                showError(err.message);
            }
        }

        // Remove Profile Function
        function removeProfile(userId) {
            if (confirm('Are you sure you want to remove this profile? This action cannot be undone.')) {
                showSuccess('Profile removed successfully');
                // Add actual removal logic here
            }
        }

        // Alert Functions
        function showSuccess(message) {
            const alert = document.getElementById('successAlert');
            const messageEl = document.getElementById('successMessage');
            messageEl.textContent = message;
            alert.style.display = 'block';
            document.getElementById('errorAlert').style.display = 'none';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        function showError(message) {
            const alert = document.getElementById('errorAlert');
            const messageEl = document.getElementById('errorMessage');
            messageEl.textContent = message;
            alert.style.display = 'block';
            document.getElementById('successAlert').style.display = 'none';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        // Form Submission Handlers
        document.getElementById('newProfileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            if (password !== confirmPassword) {
                showError('Passwords do not match');
                return;
            }
            formData.append('action', 'create_user');
            try {
                const res = await fetch('process/profile-management.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (!data.success) { throw new Error(data.message || 'Failed to create profile'); }
                showSuccess(data.message || 'New profile created successfully');
                hideNewProfileForm();
                // Append new user to table
                if (data.user) { appendUserRow(data.user); }
            } catch (err) {
                showError(err.message);
            }
        });

        document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            if (password && password !== confirmPassword) {
                showError('Passwords do not match');
                return;
            }
            formData.append('action', 'update_user');
            try {
                const res = await fetch('process/profile-management.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (!data.success) { throw new Error(data.message || 'Failed to update profile'); }
                showSuccess(data.message || 'Profile updated successfully');
                hideEditProfileForm();
                // Refresh users list to reflect changes
                loadUsers();
            } catch (err) {
                showError(err.message);
            }
        });

        // Auto-hide alerts on click
        document.getElementById('successAlert').addEventListener('click', function() {
            this.style.display = 'none';
        });
        
        document.getElementById('errorAlert').addEventListener('click', function() {
            this.style.display = 'none';
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Add mobile menu button functionality if needed
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                // Add mobile-specific functionality
            }
        });
    </script>
</body>
</html>