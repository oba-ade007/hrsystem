<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin']);

require_once '../src/config/database.php';
require_once '../src/models/User.php';

$userModel = new User($db);
$users = $userModel->getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand">
                <h2>HR System</h2>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                    <li><a href="payroll.php"><i>💰</i> Payroll</a></li>
                    <li class="active"><a href="users.php"><i>🔐</i> Users</a></li>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="content">
            <div class="topbar">
                <div class="user-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                </div>
            </div>
            
            <div class="container">
                <div class="page-header">
                    <h1>User Management</h1>
                </div>
                
                <div class="card">
                    <h2>System Users (<?php echo count($users); ?>)</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No users found</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td><span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                    <td><?php echo date('d-m-Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-small btn-warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')">
                                            🔐 Reset Password
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Password Reset Modal -->
    <div id="resetPasswordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔐 Reset User Password</h3>
                <button class="modal-close" onclick="closeResetModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="resetContent">
                    <p><strong>User Email:</strong> <span id="userEmail"></span></p>
                    <p style="margin-top: 15px; color: #d32f2f; font-weight: bold;">⚠️ This will generate a new temporary password for the user.</p>
                    <p style="margin-top: 10px;">They will need to use this temporary password to log in and change it.</p>
                </div>
                <div id="resetSuccess" style="display: none;">
                    <div style="background-color: #e8f5e9; border: 1px solid #4caf50; padding: 15px; border-radius: 4px; margin: 15px 0;">
                        <p style="margin: 0; color: #2e7d32;"><strong>✓ Password reset successfully!</strong></p>
                        <p style="margin: 10px 0 0 0; color: #333;">
                            <strong>Temporary Password:</strong><br>
                            <code id="tempPassword" style="background: #f5f5f5; padding: 8px; border-radius: 3px; display: block; word-break: break-all; margin-top: 5px; font-family: monospace;"></code>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeResetModal()" id="closeBtn">Close</button>
                <button class="btn btn-warning" onclick="confirmReset()" id="confirmBtn">Reset Password</button>
            </div>
        </div>
    </div>
    
    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="modal-backdrop" style="display: none;" onclick="closeResetModal()"></div>
    
    <script>
        let resetUserId = null;
        
        function resetPassword(userId, userEmail) {
            resetUserId = userId;
            document.getElementById('userEmail').textContent = userEmail;
            document.getElementById('resetContent').style.display = 'block';
            document.getElementById('resetSuccess').style.display = 'none';
            document.getElementById('confirmBtn').style.display = 'inline-block';
            document.getElementById('closeBtn').textContent = 'Cancel';
            document.getElementById('resetPasswordModal').style.display = 'block';
            document.getElementById('modalBackdrop').style.display = 'block';
        }
        
        function confirmReset() {
            if (!resetUserId) return;
            
            document.getElementById('confirmBtn').disabled = true;
            document.getElementById('confirmBtn').textContent = 'Processing...';
            
            fetch('reset-password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=reset&user_id=' + resetUserId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('resetContent').style.display = 'none';
                    document.getElementById('resetSuccess').style.display = 'block';
                    document.getElementById('tempPassword').textContent = data.temporary_password;
                    document.getElementById('confirmBtn').style.display = 'none';
                    document.getElementById('closeBtn').textContent = 'Close';
                } else {
                    alert('Error: ' + (data.message || 'Failed to reset password'));
                    document.getElementById('confirmBtn').disabled = false;
                    document.getElementById('confirmBtn').textContent = 'Reset Password';
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                document.getElementById('confirmBtn').disabled = false;
                document.getElementById('confirmBtn').textContent = 'Reset Password';
            });
        }
        
        function closeResetModal() {
            document.getElementById('resetPasswordModal').style.display = 'none';
            document.getElementById('modalBackdrop').style.display = 'none';
            resetUserId = null;
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('resetPasswordModal');
            if (event.target === modal) {
                closeResetModal();
            }
        }
    </script>
    
    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            min-width: 400px;
            max-width: 600px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .modal-body {
            margin-bottom: 20px;
            color: #666;
            line-height: 1.6;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .modal-backdrop {
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-warning {
            background-color: #ff9800;
            border: none;
            color: white;
            padding: 8px 16px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-warning:hover {
            background-color: #f57c00;
        }
        
        .btn-secondary {
            background-color: #757575;
            border: none;
            color: white;
            padding: 8px 16px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-secondary:hover {
            background-color: #616161;
        }
    </style>
    
    <script src="js/script.js"></script>
</body>
</html>
