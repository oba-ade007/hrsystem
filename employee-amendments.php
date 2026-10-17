<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';

$employeeModel = new Employee($db);
$message = '';
$error = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $amendmentId = $_POST['amendment_id'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if (!empty($amendmentId) && !empty($action)) {
        if ($action === 'approve') {
            // Get amendment details
            $amendResult = $db->query("
                SELECT ea.*, e.id as emp_id
                FROM employee_amendments ea
                JOIN employees e ON ea.employee_id = e.id
                WHERE ea.id = $amendmentId
            ");
            
            if ($amendResult && $amendResult->num_rows > 0) {
                $amendment = $amendResult->fetch_assoc();
                
                // Update employee record
                $updateStmt = $db->prepare("
                    UPDATE employees
                    SET phone = ?, address = ?, city = ?, state = ?, zip_code = ?, 
                        emergency_contact = ?, emergency_contact_phone = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $updateStmt->bind_param(
                    'sssssssi',
                    $amendment['phone'],
                    $amendment['address'],
                    $amendment['city'],
                    $amendment['state'],
                    $amendment['zip_code'],
                    $amendment['emergency_contact'],
                    $amendment['emergency_contact_phone'],
                    $amendment['emp_id']
                );
                
                if ($updateStmt->execute()) {
                    // Update amendment status to approved
                    $approveStmt = $db->prepare("
                        UPDATE employee_amendments
                        SET status = 'approved', approved_by = ?, approval_date = NOW()
                        WHERE id = ?
                    ");
                    $approveStmt->bind_param('ii', $_SESSION['user_id'], $amendmentId);
                    $approveStmt->execute();
                    
                    $message = 'Amendment approved and applied to employee record successfully!';
                } else {
                    $error = 'Failed to apply amendment: ' . $updateStmt->error;
                }
            }
        } elseif ($action === 'reject') {
            $rejectStmt = $db->prepare("
                UPDATE employee_amendments
                SET status = 'rejected', approved_by = ?, approval_date = NOW(), rejection_reason = ?
                WHERE id = ?
            ");
            $rejectStmt->bind_param('isi', $_SESSION['user_id'], $remarks, $amendmentId);
            
            if ($rejectStmt->execute()) {
                $message = 'Amendment rejected successfully!';
            } else {
                $error = 'Failed to reject amendment: ' . $rejectStmt->error;
            }
        }
    }
}

// Get pending amendments
$pendingResult = $db->query("
    SELECT ea.*, e.employee_id, u.full_name, u.email
    FROM employee_amendments ea
    JOIN employees e ON ea.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    WHERE ea.status = 'pending'
    ORDER BY ea.created_at DESC
");
$pending = $pendingResult ? $pendingResult->fetch_all(MYSQLI_ASSOC) : [];

// Get approved amendments
$approvedResult = $db->query("
    SELECT ea.*, e.employee_id, u.full_name, u.email, au.full_name as approved_by_name
    FROM employee_amendments ea
    JOIN employees e ON ea.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    LEFT JOIN users au ON ea.approved_by = au.id
    WHERE ea.status = 'approved'
    ORDER BY ea.approval_date DESC
    LIMIT 50
");
$approved = $approvedResult ? $approvedResult->fetch_all(MYSQLI_ASSOC) : [];

// Get rejected amendments
$rejectedResult = $db->query("
    SELECT ea.*, e.employee_id, u.full_name, u.email, au.full_name as approved_by_name
    FROM employee_amendments ea
    JOIN employees e ON ea.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    LEFT JOIN users au ON ea.approved_by = au.id
    WHERE ea.status = 'rejected'
    ORDER BY ea.approval_date DESC
    LIMIT 50
");
$rejected = $rejectedResult ? $rejectedResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Amendments - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .amendment-card {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .amendment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .amendment-data {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            background-color: white;
            border-radius: 4px;
        }
        .data-label {
            font-weight: bold;
            color: #666;
        }
        .data-value {
            color: #333;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-approve {
            background-color: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-approve:hover {
            background-color: #229954;
        }
        .btn-reject {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-reject:hover {
            background-color: #c0392b;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 500px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            margin-bottom: 15px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab-button {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            color: #666;
            border-bottom: 3px solid transparent;
        }
        .tab-button.active {
            color: #27ae60;
            border-bottom-color: #27ae60;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
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
                    <li><a href="attendance.php"><i>📋</i> Attendance</a></li>
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
                    <li><a href="employee-data-update-campaigns.php"><i>🗂️</i> Update Campaigns</a></li>
                    <li><a href="hr-record-update-approvals.php"><i>✓</i> Record Approvals</a></li>
                    <li class="active"><a href="employee-amendments.php"><i>✎</i> Amendments</a></li>
                    <li><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                    <li><a href="payroll.php"><i>💰</i> Payroll</a></li>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <li><a href="users.php"><i>🔐</i> Users</a></li>
                    <?php endif; ?>
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
                    <h1>Employee Record Amendments</h1>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="tabs">
                    <button class="tab-button active" onclick="switchTab('pending')">
                        ⏳ Pending (<?php echo count($pending); ?>)
                    </button>
                    <button class="tab-button" onclick="switchTab('approved')">
                        ✓ Approved (<?php echo count($approved); ?>)
                    </button>
                    <button class="tab-button" onclick="switchTab('rejected')">
                        ✗ Rejected (<?php echo count($rejected); ?>)
                    </button>
                </div>
                
                <!-- PENDING AMENDMENTS -->
                <div id="pending" class="tab-content active">
                    <?php if (empty($pending)): ?>
                    <div class="alert alert-info">No pending amendments to review.</div>
                    <?php else: ?>
                        <?php foreach($pending as $amendment): ?>
                        <div class="amendment-card">
                            <div class="amendment-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($amendment['full_name']); ?></h3>
                                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                                        ID: <?php echo htmlspecialchars($amendment['employee_id']); ?> | 
                                        Email: <?php echo htmlspecialchars($amendment['email']); ?>
                                    </p>
                                </div>
                                <span class="status-badge status-pending">Pending</span>
                            </div>
                            
                            <div class="amendment-data">
                                <div class="data-row">
                                    <span class="data-label">Phone:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['phone'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Address:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['address'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">City:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['city'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">State:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['state'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Zip Code:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['zip_code'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Emergency Contact:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Emergency Phone:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact_phone'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Requested:</span>
                                    <span class="data-value"><?php echo date('d-m-Y H:i', strtotime($amendment['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button class="btn-approve" onclick="approveAmendment(<?php echo $amendment['id']; ?>)">✓ Approve</button>
                                <button class="btn-reject" onclick="rejectAmendment(<?php echo $amendment['id']; ?>)">✗ Reject</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- APPROVED AMENDMENTS -->
                <div id="approved" class="tab-content">
                    <?php if (empty($approved)): ?>
                    <div class="alert alert-info">No approved amendments.</div>
                    <?php else: ?>
                        <?php foreach($approved as $amendment): ?>
                        <div class="amendment-card">
                            <div class="amendment-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($amendment['full_name']); ?></h3>
                                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                                        ID: <?php echo htmlspecialchars($amendment['employee_id']); ?> | 
                                        Email: <?php echo htmlspecialchars($amendment['email']); ?>
                                    </p>
                                </div>
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                            
                            <div class="amendment-data">
                                <div class="data-row">
                                    <span class="data-label">Phone:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['phone'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Address:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['address'] ?? ''); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Approved by:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['approved_by_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Approval Date:</span>
                                    <span class="data-value"><?php echo date('d-m-Y H:i', strtotime($amendment['approval_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- REJECTED AMENDMENTS -->
                <div id="rejected" class="tab-content">
                    <?php if (empty($rejected)): ?>
                    <div class="alert alert-info">No rejected amendments.</div>
                    <?php else: ?>
                        <?php foreach($rejected as $amendment): ?>
                        <div class="amendment-card">
                            <div class="amendment-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($amendment['full_name']); ?></h3>
                                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                                        ID: <?php echo htmlspecialchars($amendment['employee_id']); ?> | 
                                        Email: <?php echo htmlspecialchars($amendment['email']); ?>
                                    </p>
                                </div>
                                <span class="status-badge status-rejected">Rejected</span>
                            </div>
                            
                            <div class="amendment-data">
                                <div class="data-row">
                                    <span class="data-label">Rejected by:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['approved_by_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Rejection Date:</span>
                                    <span class="data-value"><?php echo date('d-m-Y H:i', strtotime($amendment['approval_date'])); ?></span>
                                </div>
                                <div class="data-row" style="grid-column: 1/-1;">
                                    <span class="data-label">Reason:</span>
                                    <span class="data-value"><?php echo htmlspecialchars($amendment['rejection_reason'] ?? 'No reason provided'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Approval Modals -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeApproveModal()">&times;</span>
                <h2>Approve Amendment</h2>
            </div>
            <form method="POST">
                <p>Are you sure you want to approve this amendment?</p>
                <input type="hidden" id="approveAmendmentId" name="amendment_id" value="">
                <input type="hidden" name="action" value="approve">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-approve">✓ Approve</button>
                    <button type="button" class="btn-reject" onclick="closeApproveModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Rejection Modals -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeRejectModal()">&times;</span>
                <h2>Reject Amendment</h2>
            </div>
            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label for="remarks">Reason for Rejection:</label>
                    <textarea id="remarks" name="remarks" rows="4" style="width: 100%; padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px;" required></textarea>
                </div>
                <input type="hidden" id="rejectAmendmentId" name="amendment_id" value="">
                <input type="hidden" name="action" value="reject">
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-reject">✗ Reject</button>
                    <button type="button" class="btn-approve" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active from all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab and mark button as active
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function approveAmendment(amendmentId) {
            document.getElementById('approveAmendmentId').value = amendmentId;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function closeApproveModal() {
            document.getElementById('approveModal').style.display = 'none';
        }
        
        function rejectAmendment(amendmentId) {
            document.getElementById('rejectAmendmentId').value = amendmentId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('remarks').value = '';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const approveModal = document.getElementById('approveModal');
            const rejectModal = document.getElementById('rejectModal');
            if (event.target === approveModal) {
                approveModal.style.display = 'none';
            }
            if (event.target === rejectModal) {
                rejectModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
