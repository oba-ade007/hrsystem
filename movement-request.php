<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';
require_once '../src/models/Attendance.php';

$userId = $_SESSION['user_id'];
$employeeModel = new Employee($db);
$employee = $employeeModel->getEmployeeByUserId($userId);

if (!$employee) {
    die('Employee profile not found. Please contact HR.');
}

$attendanceModel = new Attendance($db);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movementType = trim($_POST['movement_type'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $departureTime = trim($_POST['departure_time'] ?? '');
    $arrivalTime = trim($_POST['arrival_time'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($movementType) || empty($reason) || empty($destination)) {
        $error = 'Movement type, reason, and destination are required.';
    } else {
        $submission = [
            'employee_id' => (int)$employee['id'],
            'branch_id' => isset($employee['branch_id']) ? (int)$employee['branch_id'] : null,
            'zone_id' => null,
            'movement_type' => $movementType,
            'reason' => $reason,
            'destination' => $destination,
            'departure_qr_id' => null,
            'departure_latitude' => null,
            'departure_longitude' => null,
            'departure_time' => $departureTime ?: date('Y-m-d H:i:s'),
            'arrival_qr_id' => null,
            'arrival_latitude' => null,
            'arrival_longitude' => null,
            'arrival_time' => $arrivalTime ?: date('Y-m-d H:i:s'),
            'status' => 'pending',
            'remarks' => $remarks ?: 'Submitted by employee for approval.'
        ];

        if ($attendanceModel->recordMovement($submission)) {
            $success = 'Movement request submitted successfully and is pending approval.';
        } else {
            $error = 'Movement request could not be submitted. Please try again.';
        }
    }
}

$requests = $attendanceModel->getMovementRequestsByEmployee((int)$employee['id'], 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movement Request - HR Software</title>
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
                    <li class="active"><a href="movement-request.php"><i>🚶</i> Movement Request</a></li>
                    <li><a href="apply-leave.php"><i>🏖️</i> Apply Leave</a></li>
                    <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
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
                    <h1>Movement Request</h1>
                    <p>Submit movement, assignment, or branch-travel requests for manager approval.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2>New Movement Request</h2>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Movement Type</label>
                                <select name="movement_type" required>
                                    <option value="">Select movement type</option>
                                    <option value="official_assignment">Official Assignment</option>
                                    <option value="personal_movement">Personal Movement</option>
                                    <option value="inter_branch_movement">Inter-Branch Movement</option>
                                    <option value="movement_out">Movement Out</option>
                                    <option value="movement_in">Movement In</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Destination</label>
                                <input type="text" name="destination" placeholder="e.g., Head Office / Field visit / Lagos branch" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Departure Time</label>
                                <input type="datetime-local" name="departure_time">
                            </div>
                            <div class="form-group">
                                <label>Arrival Time</label>
                                <input type="datetime-local" name="arrival_time">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Reason</label>
                            <textarea name="reason" rows="3" placeholder="State the reason for the movement request" required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Additional Remarks</label>
                            <textarea name="remarks" rows="3" placeholder="Any extra information for approval"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2>My Movement Requests</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Destination</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr><td colspan="5" class="text-center">You have no movement requests yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['movement_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($request['destination'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($request['status'] ?? 'pending'); ?></td>
                                        <td><?php echo htmlspecialchars($request['created_at'] ?? 'N/A'); ?></td>
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
