<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['employee', 'hr', 'admin', 'account', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';

$employeeModel = new Employee($db);
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$user_role = $_SESSION['user_role'];

// Get current user's employee record
$empResult = $db->query("SELECT id FROM employees WHERE user_id = $user_id");
$employee = $empResult ? $empResult->fetch_assoc() : null;
$employee_id = $employee['id'] ?? null;

if (!$employee_id) {
    header('Location: /hr-software/public/dashboard.php');
    exit;
}

// Get all amendments for this employee
$pendingResult = $db->query("
    SELECT * FROM employee_amendments
    WHERE employee_id = $employee_id AND status = 'pending'
    ORDER BY created_at DESC
");
$pending = $pendingResult ? $pendingResult->fetch_all(MYSQLI_ASSOC) : [];

$approvedResult = $db->query("
    SELECT ea.*, u.full_name as approved_by_name
    FROM employee_amendments ea
    LEFT JOIN users u ON ea.approved_by = u.id
    WHERE ea.employee_id = $employee_id AND ea.status = 'approved'
    ORDER BY ea.approval_date DESC
    LIMIT 50
");
$approved = $approvedResult ? $approvedResult->fetch_all(MYSQLI_ASSOC) : [];

$rejectedResult = $db->query("
    SELECT ea.*, u.full_name as approved_by_name
    FROM employee_amendments ea
    LEFT JOIN users u ON ea.approved_by = u.id
    WHERE ea.employee_id = $employee_id AND ea.status = 'rejected'
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
    <title>My Amendments - HR Software</title>
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
        }
        .data-label {
            font-weight: bold;
            color: #666;
            min-width: 120px;
        }
        .data-value {
            color: #333;
            word-break: break-word;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
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
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab-button {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            border-bottom: 3px solid transparent;
            color: #666;
            transition: all 0.3s;
        }
        .tab-button.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        .rejection-reason {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .approval-info {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
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
                    <li><a href="apply-leave.php"><i>🏖️</i> Apply Leave</a></li>
                    <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
                    <?php if ($user_role === 'employee'): ?>
                        <li class="active"><a href="my-amendments.php"><i>✎</i> My Amendments</a></li>
                        <li><a href="view-payslip.php"><i>💵</i> My Payslips</a></li>
                    <?php endif; ?>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div class="user-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
                </div>
                <div class="user-role"><?php echo ucfirst($user_role); ?></div>
            </div>

            <div class="content-area">
                <h1>My Amendment Requests</h1>
                <p>Track the status of your submitted record updates</p>

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

                <!-- Pending Amendments -->
                <div id="pending" class="tab-content active">
                    <?php if (empty($pending)): ?>
                        <div class="empty-state">
                            <p>⏳ No pending amendment requests</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending as $amendment): ?>
                            <div class="amendment-card">
                                <div class="amendment-header">
                                    <span class="status-badge status-pending">Pending Review</span>
                                    <small style="color: #999;">Submitted: <?php echo date('d-m-Y H:i', strtotime($amendment['created_at'])); ?></small>
                                </div>
                                <div class="amendment-data">
                                    <div class="data-row">
                                        <span class="data-label">Phone:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['phone'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Address:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['address'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">City:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['city'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">State:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['state'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Zip Code:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['zip_code'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Emergency Contact:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Emergency Phone:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact_phone'] ?? '-'); ?></span>
                                    </div>
                                </div>
                                <div style="color: #999; font-size: 0.9rem; padding-top: 10px; border-top: 1px solid #ddd;">
                                    ⏳ Your request is under review. HR will notify you once processed.
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Approved Amendments -->
                <div id="approved" class="tab-content">
                    <?php if (empty($approved)): ?>
                        <div class="empty-state">
                            <p>✓ No approved amendments yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($approved as $amendment): ?>
                            <div class="amendment-card">
                                <div class="amendment-header">
                                    <span class="status-badge status-approved">Approved ✓</span>
                                    <small style="color: #999;">Approved: <?php echo date('d-m-Y H:i', strtotime($amendment['approval_date'])); ?></small>
                                </div>
                                <div class="amendment-data">
                                    <div class="data-row">
                                        <span class="data-label">Phone:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['phone'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Address:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['address'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">City:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['city'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">State:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['state'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Zip Code:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['zip_code'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Emergency Contact:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Emergency Phone:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact_phone'] ?? '-'); ?></span>
                                    </div>
                                </div>
                                <div class="approval-info">
                                    <strong>✓ Approved by <?php echo htmlspecialchars($amendment['approved_by_name'] ?? 'HR Staff'); ?></strong><br>
                                    Your changes have been applied to your employee record.
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Rejected Amendments -->
                <div id="rejected" class="tab-content">
                    <?php if (empty($rejected)): ?>
                        <div class="empty-state">
                            <p>✗ No rejected amendments</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rejected as $amendment): ?>
                            <div class="amendment-card">
                                <div class="amendment-header">
                                    <span class="status-badge status-rejected">Rejected ✗</span>
                                    <small style="color: #999;">Rejected: <?php echo date('d-m-Y H:i', strtotime($amendment['approval_date'])); ?></small>
                                </div>
                                <div class="amendment-data">
                                    <div class="data-row">
                                        <span class="data-label">Phone:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['phone'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Address:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['address'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">City:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['city'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">State:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['state'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Zip Code:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['zip_code'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Emergency Contact:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact'] ?? '-'); ?></span>
                                    </div>
                                    <div class="data-row">
                                        <span class="data-label">Emergency Phone:</span>
                                        <span class="data-value"><?php echo htmlspecialchars($amendment['emergency_contact_phone'] ?? '-'); ?></span>
                                    </div>
                                </div>
                                <div class="rejection-reason">
                                    <strong>✗ Rejected by <?php echo htmlspecialchars($amendment['approved_by_name'] ?? 'HR Staff'); ?></strong><br>
                                    <?php if (!empty($amendment['rejection_reason'])): ?>
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($amendment['rejection_reason']); ?>
                                    <?php else: ?>
                                        No reason provided
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
