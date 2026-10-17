<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/Attendance.php';
require_once '../src/models/Employee.php';

$attendanceModel = new Attendance($db);
$employeeModel = new Employee($db);
$employees = $employeeModel->getAllEmployees();
$selectedEmployeeId = $_GET['employee_id'] ?? '';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $eventId = (int)($_POST['event_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $authorizedBy = trim($_POST['authorized_by'] ?? '');
    $correctedValue = trim($_POST['corrected_value'] ?? '');

    if (!$employeeId || !$eventId || empty($reason) || empty($authorizedBy) || empty($correctedValue)) {
        $error = 'Employee, attendance event, reason, authorized person, and corrected value are all required.';
    } else {
        $event = $attendanceModel->getAttendanceEventsForAudit($employeeId, 1);
        $selected = null;
        foreach ($event as $item) {
            if ((int)$item['id'] === $eventId) {
                $selected = $item;
                break;
            }
        }

        if (!$selected) {
            $error = 'The selected attendance event could not be found for the chosen employee.';
        } else {
            $oldValue = json_encode([
                'event_type' => $selected['event_type'],
                'status' => $selected['status'],
                'event_time' => $selected['event_time'],
                'remarks' => $selected['remarks']
            ]);

            $updated = $attendanceModel->logAudit(
                $_SESSION['user_id'],
                $_SESSION['user_role'],
                'ATTENDANCE_CORRECTION',
                'attendance_events',
                $eventId,
                $oldValue,
                json_encode([
                    'reason' => $reason,
                    'authorized_by' => $authorizedBy,
                    'corrected_value' => $correctedValue,
                    'corrected_at' => date('Y-m-d H:i:s')
                ]),
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            if ($updated) {
                $success = 'Attendance correction request recorded in the audit trail successfully.';
            } else {
                $error = 'The audit entry could not be saved. Please try again.';
            }
        }
    }
}

$events = $attendanceModel->getAttendanceEventsForAudit($selectedEmployeeId ?: null, 100);
$auditLogs = $attendanceModel->getAuditLogs(50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - HR Software</title>
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
                    <li><a href="reports.php"><i>📄</i> Reports</a></li>
                    <li class="active"><a href="audit-trail.php"><i>🧾</i> Audit Trail</a></li>
                    <li class="menu-header">MANAGEMENT</li>
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
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
                    <h1>Audit Trail & Attendance Corrections</h1>
                    <p>Review completed actions and record authorized attendance corrections with a full auditable trail.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2>Log Attendance Correction</h2>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Employee</label>
                                <select name="employee_id" required onchange="this.form.submit();">
                                    <option value="">Select employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo (int)$employee['id']; ?>" <?php echo $selectedEmployeeId == $employee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Attendance Event</label>
                                <select name="event_id" required>
                                    <option value="">Select event</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo (int)$event['id']; ?>"><?php echo htmlspecialchars($event['event_type'] . ' / ' . $event['event_time']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Reason for Correction</label>
                                <textarea name="reason" rows="3" required placeholder="e.g., Wrong QR scanned due to staff movement error"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Authorized Person</label>
                                <input type="text" name="authorized_by" required placeholder="HR manager name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Corrected Value / Notes</label>
                            <textarea name="corrected_value" rows="3" required placeholder="Example: Changed 'late_arrival' to 'resumption' and adjusted remarks."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Audit Entry</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2>Recent Audit Entries</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Entity ID</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auditLogs)): ?>
                                <tr><td colspan="6" class="text-center">No audit entries found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['actor_name'] ?: 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['entity_type'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($log['entity_id'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
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
