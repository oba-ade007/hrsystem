<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'manager']);

require_once '../src/config/database.php';
require_once '../src/models/Department.php';

$departmentModel = new Department($db);
$departments = $departmentModel->getAllDepartments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - HR Software</title>
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
                    <li class="active"><a href="departments.php"><i>🏢</i> Departments</a></li>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'])): ?>
                    <li><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                    <li><a href="payroll.php"><i>💰</i> Payroll</a></li>
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
                    <h1>Departments</h1>
                </div>
                
                <div class="card">
                    <h2>Company Departments (<?php echo count($departments); ?>)</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Description</th>
                                <th>Manager</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="3" class="text-center">No departments found</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($departments as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($dept['description'] ?? '', 0, 100)); ?></td>
                                    <td><?php echo htmlspecialchars($dept['manager_name'] ?? 'N/A'); ?></td>
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
