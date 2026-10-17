<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads', 'account']);

require_once '../src/config/database.php';
require_once '../src/models/Leave.php';
require_once '../src/models/LeaveWorkflow.php';

$leaveModel = new Leave($db);
$workflowModel = new LeaveWorkflow($db);
$pendingLeaves = $workflowModel->getPendingApprovals($_SESSION['user_id'], $_SESSION['user_role']);
$pendingLeaves = array_merge($pendingLeaves, array_filter($leaveModel->getPendingLeaves(), function ($leave) {
    return empty($leave['workflow_rule_id']);
}));

$action = $_GET['action'] ?? '';
$leaveId = $_GET['leave_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($leaveId)) {
    $remarks = $_POST['remarks'] ?? '';
    
    $approvalId = (int)($_POST['approval_id'] ?? 0);
    if ($approvalId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $approvalAction = $action === 'approve' ? 'approved' : 'rejected';
        $workflowModel->actionApproval($approvalId, $_SESSION['user_id'], $approvalAction, $remarks);
    } elseif ($approvalId === 0 && $action === 'approve') {
        $leaveModel->approveLeave($leaveId, $_SESSION['user_id'], $remarks);
    } elseif ($approvalId === 0 && $action === 'reject') {
        $leaveModel->rejectLeave($leaveId, $remarks);
    }
    
    header('Location: leave-approvals.php');
    exit;
}

$pendingLeaves = $workflowModel->getPendingApprovals($_SESSION['user_id'], $_SESSION['user_role']);
$pendingLeaves = array_merge($pendingLeaves, array_filter($leaveModel->getPendingLeaves(), function ($leave) {
    return empty($leave['workflow_rule_id']);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approvals - HR Software</title>
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
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
                    <li><a href="employee-data-update-campaigns.php"><i>🗂️</i> Update Campaigns</a></li>
                    <li><a href="hr-record-update-approvals.php"><i>✓</i> Record Approvals</a></li>
                    <li class="active"><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                    <li><a href="leave-setup.php"><i>⚙️</i> Leave Setup</a></li>
                    <li><a href="employee-amendments.php"><i>✎</i> Amendments</a></li>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr', 'account'])): ?>
                    <li><a href="payroll.php"><i>💰</i> Payroll</a></li>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <li><a href="users.php"><i>🔐</i> Users</a></li>
                    <?php endif; ?>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
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
                    <h1>Leave Approvals</h1>
                    <p><?php echo count($pendingLeaves); ?> pending approval(s)</p>
                </div>
                
                <div class="card">
                    <h2>Pending Leave Requests</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingLeaves)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No pending leave requests</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($pendingLeaves as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($leave['start_date'])); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($leave['end_date'])); ?></td>
                                    <td><?php echo (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60 * 60 * 24) + 1; ?></td>
                                    <td><?php echo htmlspecialchars(substr($leave['reason'] ?? '', 0, 30)); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($leave['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" action="leave-approvals.php?action=approve&leave_id=<?php echo $leave['id']; ?>" style="display: inline;">
                                            <input type="hidden" name="approval_id" value="<?php echo (int)$leave['approval_id']; ?>">
                                            <button type="submit" class="btn btn-small" style="background-color: #27ae60; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">Approve</button>
                                        </form>
                                        <form method="POST" action="leave-approvals.php?action=reject&leave_id=<?php echo $leave['id']; ?>" style="display: inline;">
                                            <input type="hidden" name="approval_id" value="<?php echo (int)$leave['approval_id']; ?>">
                                            <button type="submit" class="btn btn-small" style="background-color: #e74c3c; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">Reject</button>
                                        </form>
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
    
    <script src="js/script.js"></script>
</body>
</html>
