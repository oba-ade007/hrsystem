<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/Attendance.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';
require_once '../src/models/Employee.php';

$attendanceModel = new Attendance($db);
$departmentModel = new Department($db);
$branchModel = new Branch($db);
$employeeModel = new Employee($db);

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$branchId = $_GET['branch_id'] ?? '';
$departmentId = $_GET['department_id'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';
$export = $_GET['export'] ?? '';

if ($export === 'attendance') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-report.csv"');
    $rows = $attendanceModel->getAttendanceReport($startDate, $endDate, $branchId ?: null, $departmentId ?: null, $employeeId ?: null);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee', 'Employee ID', 'Branch', 'Zone', 'Event Type', 'Status', 'Date', 'Time', 'Distance (m)', 'Geofence (m)', 'Remarks']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['full_name'] ?? '',
            $row['employee_id'] ?? '',
            $row['branch_name'] ?? '',
            $row['zone_name'] ?? '',
            $row['event_type'] ?? '',
            $row['status'] ?? '',
            $row['event_date'] ?? '',
            $row['event_time'] ?? '',
            $row['distance_meters'] ?? '',
            $row['geofence_radius'] ?? '',
            $row['remarks'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

if ($export === 'movement') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="movement-report.csv"');
    $rows = $attendanceModel->getMovementReport($startDate, $endDate, $branchId ?: null, $departmentId ?: null, $employeeId ?: null);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee', 'Employee ID', 'Movement Type', 'Reason', 'Destination', 'Departure Time', 'Arrival Time', 'Status']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['full_name'] ?? '',
            $row['employee_id'] ?? '',
            $row['movement_type'] ?? '',
            $row['reason'] ?? '',
            $row['destination'] ?? '',
            $row['departure_time'] ?? '',
            $row['arrival_time'] ?? '',
            $row['status'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

$attendanceRows = $attendanceModel->getAttendanceReport($startDate, $endDate, $branchId ?: null, $departmentId ?: null, $employeeId ?: null);
$movementRows = $attendanceModel->getMovementReport($startDate, $endDate, $branchId ?: null, $departmentId ?: null, $employeeId ?: null);
$departments = $departmentModel->getAllDepartments();
$branches = $branchModel->getAllBranches();
$employees = $employeeModel->getAllEmployees();

$attendanceStats = ['resumption' => 0, 'late_arrival' => 0, 'closing' => 0, 'movement_out' => 0, 'movement_in' => 0, 'official_assignment' => 0, 'personal_movement' => 0, 'inter_branch_movement' => 0, 'absence' => 0];
foreach ($attendanceRows as $row) {
    $type = $row['event_type'] ?? '';
    if (isset($attendanceStats[$type])) {
        $attendanceStats[$type]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HR Software</title>
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
                    <li class="active"><a href="reports.php"><i>📄</i> Reports</a></li>
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
                    <h1>Attendance & Movement Reports</h1>
                    <p>Filter and export daily and period reports for attendance and staff movement.</p>
                </div>

                <div class="card">
                    <h2>Report Filters</h2>
                    <form method="GET" class="filter-form">
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">

                        <select name="branch_id">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo (int)$branch['id']; ?>" <?php echo $branchId == $branch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="department_id">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo (int)$department['id']; ?>" <?php echo $departmentId == $department['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($department['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo (int)$employee['id']; ?>" <?php echo $employeeId == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-primary">Apply</button>
                    </form>
                </div>

                <div class="attendance-stats" style="margin-top: 24px;">
                    <div class="stat-box"><h3>Resumption</h3><p class="stat-value"><?php echo (int)$attendanceStats['resumption']; ?></p></div>
                    <div class="stat-box"><h3>Late Arrival</h3><p class="stat-value"><?php echo (int)$attendanceStats['late_arrival']; ?></p></div>
                    <div class="stat-box"><h3>Closing</h3><p class="stat-value"><?php echo (int)$attendanceStats['closing']; ?></p></div>
                    <div class="stat-box"><h3>Movement Out</h3><p class="stat-value"><?php echo (int)$attendanceStats['movement_out']; ?></p></div>
                    <div class="stat-box"><h3>Movement In</h3><p class="stat-value"><?php echo (int)$attendanceStats['movement_in']; ?></p></div>
                    <div class="stat-box"><h3>Official Assignment</h3><p class="stat-value"><?php echo (int)$attendanceStats['official_assignment']; ?></p></div>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                        <h2>Attendance Report</h2>
                        <a href="reports.php?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&branch_id=<?php echo urlencode((string)$branchId); ?>&department_id=<?php echo urlencode((string)$departmentId); ?>&employee_id=<?php echo urlencode((string)$employeeId); ?>&export=attendance" class="btn btn-secondary">Export CSV</a>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>ID</th>
                                <th>Branch</th>
                                <th>Zone</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Distance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceRows)): ?>
                                <tr><td colspan="8" class="text-center">No attendance events found for the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($attendanceRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['employee_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['branch_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['zone_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['event_type'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['event_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['distance_meters'] ?? 0) . ' m'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                        <h2>Movement Report</h2>
                        <a href="reports.php?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&branch_id=<?php echo urlencode((string)$branchId); ?>&department_id=<?php echo urlencode((string)$departmentId); ?>&employee_id=<?php echo urlencode((string)$employeeId); ?>&export=movement" class="btn btn-secondary">Export CSV</a>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>ID</th>
                                <th>Movement</th>
                                <th>Reason</th>
                                <th>Destination</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movementRows)): ?>
                                <tr><td colspan="8" class="text-center">No movement records found for the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($movementRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['employee_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['movement_type'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['reason'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['destination'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['departure_time'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['arrival_time'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
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
