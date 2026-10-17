<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/Attendance.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';
require_once '../src/models/Employee.php';

$date = $_GET['date'] ?? date('Y-m-d');
$branchId = $_GET['branch_id'] ?? '';
$departmentId = $_GET['department_id'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';
$staffCategory = $_GET['staff_category'] ?? '';

$attendanceModel = new Attendance($db);
$departmentModel = new Department($db);
$branchModel = new Branch($db);
$employeeModel = new Employee($db);

$summary = $attendanceModel->getLiveStatusSummary($date, $branchId ?: null, $departmentId ?: null, $employeeId ?: null, $staffCategory ?: null);
$liveRows = $attendanceModel->getLiveStaffStatusRows($date, $branchId ?: null, $departmentId ?: null, $employeeId ?: null, $staffCategory ?: null);
$departments = $departmentModel->getAllDepartments();
$branches = $branchModel->getAllBranches();
$employees = $employeeModel->getAllEmployees();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - HR Software</title>
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
                    <li class="active"><a href="attendance-dashboard.php"><i>📈</i> Live Status</a></li>
                    <li><a href="attendance-setup.php"><i>⚙️</i> Attendance Setup</a></li>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr', 'unit_heads'], true)): ?>
                    <li class="menu-header">MANAGEMENT</li>
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
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
                    <h1>Live Staff Status Dashboard</h1>
                    <p>Current employee attendance status based on the latest accepted attendance or movement event.</p>
                </div>

                <div class="card">
                    <h2>Filters</h2>
                    <form method="GET" class="filter-form">
                        <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">

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

                        <select name="staff_category">
                            <option value="">All Staff Categories</option>
                            <option value="admin" <?php echo $staffCategory === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="hr" <?php echo $staffCategory === 'hr' ? 'selected' : ''; ?>>HR</option>
                            <option value="unit_heads" <?php echo $staffCategory === 'unit_heads' ? 'selected' : ''; ?>>Unit Heads</option>
                            <option value="employee" <?php echo $staffCategory === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        </select>

                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>

                <div class="attendance-stats" style="margin-top: 24px;">
                    <div class="stat-box"><h3>Total Staff</h3><p class="stat-value"><?php echo (int)$summary['total_staff']; ?></p></div>
                    <div class="stat-box"><h3>Present</h3><p class="stat-value"><?php echo (int)$summary['present']; ?></p></div>
                    <div class="stat-box"><h3>Absent</h3><p class="stat-value"><?php echo (int)$summary['absent']; ?></p></div>
                    <div class="stat-box"><h3>Late</h3><p class="stat-value"><?php echo (int)$summary['late']; ?></p></div>
                    <div class="stat-box"><h3>Not Resumed</h3><p class="stat-value"><?php echo (int)$summary['not_resumed']; ?></p></div>
                    <div class="stat-box"><h3>Outside Office</h3><p class="stat-value"><?php echo (int)$summary['outside_office']; ?></p></div>
                    <div class="stat-box"><h3>Official Assignment</h3><p class="stat-value"><?php echo (int)$summary['official_assignment']; ?></p></div>
                    <div class="stat-box"><h3>At Another Branch</h3><p class="stat-value"><?php echo (int)$summary['another_branch']; ?></p></div>
                    <div class="stat-box"><h3>Closed</h3><p class="stat-value"><?php echo (int)$summary['closed']; ?></p></div>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2>Current Location and Status</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Home Branch</th>
                                <th>Current Location</th>
                                <th>Status</th>
                                <th>Last Event</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($liveRows)): ?>
                                <tr><td colspan="6" class="text-center">No staff status available for this filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($liveRows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['home_branch_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['current_location'] ?: $row['home_branch_name'] ?: 'N/A'); ?></td>
                                        <td><span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['event_type'] ?: 'No event'); ?></td>
                                        <td><?php echo htmlspecialchars($row['event_time'] ?: 'N/A'); ?></td>
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
