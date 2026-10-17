<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$user_role = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <h2>HR System</h2>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li class="active"><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="attendance.php"><i>📋</i> Attendance</a></li>
                    <li><a href="movement-request.php"><i>🚶</i> Movement Request</a></li>
                    <li><a href="performance-plan.php"><i>🎯</i> My KPI Plan</a></li>
                    <li><a href="performance-plan-approvals.php"><i>✓</i> KPI Plan Approvals</a></li>
                    <li><a href="performance-assessment.php"><i>📝</i> Assessments</a></li>
                    <li><a href="performance-results.php"><i>🏅</i> My Results</a></li>
                    <?php if (in_array($user_role, ['admin', 'hr', 'unit_heads'], true)): ?>
                    <li><a href="attendance-dashboard.php"><i>📈</i> Live Status</a></li>
                    <li><a href="attendance-setup.php"><i>⚙️</i> Attendance Setup</a></li>
                    <li><a href="reports.php"><i>📄</i> Reports</a></li>
                    <li><a href="movement-approvals.php"><i>🚶</i> Movement Approvals</a></li>
                    <li><a href="audit-trail.php"><i>🧾</i> Audit Trail</a></li>
                    <?php endif; ?>
                    <li><a href="apply-leave.php"><i>🏖️</i> Apply Leave</a></li>
                    <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
                    
                    <?php if (in_array($user_role, ['employee', 'user'], true)): ?>
                    <li><a href="my-amendments.php"><i>✎</i> My Amendments</a></li>
                    <li><a href="view-payslip.php"><i>💵</i> My Payslips</a></li>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_role, ['admin', 'hr', 'unit_heads'])): ?>
                    <li class="menu-header">MANAGEMENT</li>
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
                    <li><a href="employee-data-update-campaigns.php"><i>🗂️</i> Update Campaigns</a></li>
                    <li><a href="hr-record-update-approvals.php"><i>✓</i> Record Approvals</a></li>
                    <li><a href="leave-setup.php"><i>⚙️</i> Leave Setup</a></li>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_role, ['admin', 'hr', 'unit_heads'])): ?>
                    <li><a href="employee-amendments.php"><i>✎</i> Amendments</a></li>
                    <li><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_role, ['admin', 'hr', 'account'])): ?>
                    <li><a href="payroll.php"><i>💰</i> Payroll</a></li>
                    <?php endif; ?>
                    
                    <?php if ($user_role === 'admin'): ?>
                    <li><a href="users.php"><i>🔐</i> Users</a></li>
                    <?php endif; ?>
                    <?php if (in_array($user_role, ['admin', 'hr'], true)): ?>
                    <li><a href="reports.php"><i>📈</i> Reports</a></li>
                    <li><a href="performance-setup.php"><i>🎯</i> Performance Setup</a></li>
                    <li><a href="performance-templates.php"><i>🧩</i> Templates & Workflows</a></li>
                    <li><a href="performance-members.php"><i>👥</i> Members & Reporting</a></li>
                    <?php endif; ?>
                    <?php if (in_array($user_role, ['admin', 'hr'], true)): ?>
                    <li><a href="performance-appraisals.php"><i>📑</i> Appraisal Administration</a></li>
                    <li><a href="performance-reports.php"><i>📈</i> Performance Distribution</a></li>
                    <?php endif; ?>
                    
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="user-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
                    <small><?php echo ucfirst($user_role); ?></small>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="container">
                <div class="page-header">
                    <h1>Dashboard</h1>
                    <p>Welcome to HR Management System</p>
                </div>
                
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon">👥</div>
                        <div class="card-content">
                            <h3>Total Employees</h3>
                            <p class="card-value"><?php 
                                require_once '../src/config/database.php';
                                require_once '../src/models/Employee.php';
                                $employeeModel = new Employee($db);
                                $employees = $employeeModel->getAllEmployees();
                                echo count($employees);
                            ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">📋</div>
                        <div class="card-content">
                            <h3>Present Today</h3>
                            <p class="card-value">
                                <?php 
                                    require_once '../src/models/Attendance.php';
                                    $attendanceModel = new Attendance($db);
                                    $todayAttendance = $attendanceModel->getAttendanceByDate(date('Y-m-d'));
                                    $presentEmployeeIds = [];
                                    foreach ($todayAttendance as $attendance) {
                                        if (in_array($attendance['event_type'], ['resumption', 'late_arrival', 'overtime'], true) && !in_array((int)$attendance['employee_id'], $presentEmployeeIds, true)) {
                                            $presentEmployeeIds[] = (int)$attendance['employee_id'];
                                        }
                                    }
                                    echo count($presentEmployeeIds);
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">🏢</div>
                        <div class="card-content">
                            <h3>Departments</h3>
                            <p class="card-value">
                                <?php 
                                    require_once '../src/models/Department.php';
                                    $departmentModel = new Department($db);
                                    $departments = $departmentModel->getAllDepartments();
                                    echo count($departments);
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">🏖️</div>
                        <div class="card-content">
                            <h3>Pending Approvals</h3>
                            <p class="card-value">
                                <?php 
                                    require_once '../src/models/Leave.php';
                                    $leaveModel = new Leave($db);
                                    if (in_array($user_role, ['admin', 'hr'])) {
                                        $pendingLeaves = $leaveModel->getPendingLeaves();
                                        echo count($pendingLeaves);
                                    } else {
                                        echo '0';
                                    }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-section">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="apply-leave.php" class="action-btn">Apply for Leave</a>
                        <a href="attendance.php" class="action-btn">View Attendance</a>
                        <a href="movement-request.php" class="action-btn">Movement Request</a>
                        <a href="performance-plan.php" class="action-btn">My KPI Plan</a>
                        <a href="performance-plan-approvals.php" class="action-btn">KPI Plan Approvals</a>
                        <a href="performance-assessment.php" class="action-btn">Performance Assessments</a>
                        <a href="performance-results.php" class="action-btn">My Performance Results</a>
                        <?php if (in_array($user_role, ['admin', 'hr', 'unit_heads'], true)): ?>
                        <a href="attendance-dashboard.php" class="action-btn">Live Staff Status</a>
                        <a href="attendance-setup.php" class="action-btn">Attendance Setup</a>
                        <a href="reports.php" class="action-btn">Reports</a>
                        <a href="movement-approvals.php" class="action-btn">Movement Approvals</a>
                        <a href="audit-trail.php" class="action-btn">Audit Trail</a>
                        <?php endif; ?>
                        <a href="my-profile.php" class="action-btn">Update Profile</a>
                        <?php if (in_array($user_role, ['admin', 'hr'])): ?>
                        <a href="employee-data-update-campaigns.php" class="action-btn">Employee Update Campaigns</a>
                        <a href="employees.php" class="action-btn">Manage Employees</a>
                        <?php endif; ?>
                        <?php if (in_array($user_role, ['admin', 'hr'], true)): ?>
                        <a href="performance-setup.php" class="action-btn">Performance Management Setup</a>
                        <a href="performance-templates.php" class="action-btn">Performance Templates</a>
                        <a href="performance-members.php" class="action-btn">Performance Members & Reporting</a>
                        <?php endif; ?>
                        <?php if (in_array($user_role, ['admin', 'hr'], true)): ?>
                        <a href="performance-appraisals.php" class="action-btn">Appraisal Administration</a>
                        <a href="performance-reports.php" class="action-btn">Performance Distribution</a>
                        <?php endif; ?>
                        <?php if (in_array($user_role, ['employee', 'user'], true)): ?>
                        <a href="employee-record-update.php" class="action-btn">Update My Records</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
