<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/Attendance.php';

$attendanceModel = new Attendance($db);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movementId = (int)($_POST['movement_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$movementId || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid movement approval request.';
    } else {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $updated = $attendanceModel->actionMovementApproval($movementId, $_SESSION['user_id'], $status, $remarks);

        if ($updated) {
            $success = $action === 'approve' ? 'Movement request approved successfully.' : 'Movement request rejected successfully.';
        } else {
            $error = 'The movement request could not be updated. Please try again.';
        }
    }
}

$pendingMovement = $attendanceModel->getPendingMovementApprovals();
$recentMovement = $attendanceModel->getRecentMovementEvents(25);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movement Approvals - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand"><h2>HR System</h2></div>
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="attendance.php"><i>📋</i> Attendance</a></li>
                    <li><a href="attendance-dashboard.php"><i>📈</i> Live Status</a></li>
                    <li><a href="attendance-setup.php"><i>⚙️</i> Attendance Setup</a></li>
                    <li><a href="reports.php"><i>📄</i> Reports</a></li>
                    <li class="active"><a href="movement-approvals.php"><i>🚶</i> Movement Approvals</a></li>
                    <li><a href="audit-trail.php"><i>🧾</i> Audit Trail</a></li>
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
                    <h1>Movement Approvals</h1>
                    <p>Review pending movement and assignment requests before they are accepted into the daily attendance record.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2>Pending Movement Requests</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Destination</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingMovement)): ?>
                                <tr><td colspan="8" class="text-center">No movement requests pending approval.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pendingMovement as $movement): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($movement['full_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['movement_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['reason'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['destination'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['departure_time'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['arrival_time'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['created_at'] ?? 'N/A'); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="movement_id" value="<?php echo (int)$movement['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="text" name="remarks" placeholder="Approval note" style="margin-bottom: 6px; display:block; width: 160px;">
                                                <button type="submit" class="btn btn-small" style="background-color:#27ae60; color:white; border:none; border-radius:4px; padding: 6px 10px; cursor:pointer;">Approve</button>
                                            </form>
                                            <form method="POST" style="display:inline; margin-left: 5px;">
                                                <input type="hidden" name="movement_id" value="<?php echo (int)$movement['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="text" name="remarks" placeholder="Reject note" style="margin-bottom:6px; display:block; width: 160px;">
                                                <button type="submit" class="btn btn-small" style="background-color:#e74c3c; color:white; border:none; border-radius:4px; padding: 6px 10px; cursor:pointer;">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2>Recent Movement Activity</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentMovement)): ?>
                                <tr><td colspan="4" class="text-center">No movement activity recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentMovement as $movement): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($movement['full_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['movement_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['status'] ?? 'pending'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['created_at'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
