<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';

$user_id = $_SESSION['user_id'];
$employeeModel = new Employee($db);
$employee = $employeeModel->getEmployeeByUserId($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HR Software</title>
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
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="attendance.php"><i>📋</i> Attendance</a></li>
                    <li><a href="apply-leave.php"><i>🏖️</i> Apply Leave</a></li>
                    <li><a href="view-payslip.php"><i>💵</i> My Payslips</a></li>
                    <li><a href="my-amendments.php"><i>✎</i> My Amendments</a></li>
                    <li class="active"><a href="my-profile.php"><i>👤</i> My Profile</a></li>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="content">
            <div class="topbar">
                <div class="user-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                </div>
            </div>
            
            <div class="container">
                <div class="page-header">
                    <h1>My Profile</h1>
                </div>
                
                <?php if ($employee): ?>
                <div class="profile-card">
                    <div class="profile-header">
                        <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
                        <p class="profile-title"><?php echo htmlspecialchars($employee['position']); ?></p>
                    </div>
                    
                    <div class="profile-info">
                        <div class="info-row">
                            <label>Employee ID:</label>
                            <span><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Email:</label>
                            <span><?php echo htmlspecialchars($employee['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Department:</label>
                            <span><?php echo htmlspecialchars($employee['department_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Phone:</label>
                            <span><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Date of Birth:</label>
                            <span><?php echo $employee['date_of_birth'] ? date('d-m-Y', strtotime($employee['date_of_birth'])) : 'N/A'; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Joining Date:</label>
                            <span><?php echo date('d-m-Y', strtotime($employee['joining_date'])); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Address:</label>
                            <span><?php echo htmlspecialchars($employee['address'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <label>City:</label>
                            <span><?php echo htmlspecialchars($employee['city'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Emergency Contact:</label>
                            <span><?php echo htmlspecialchars($employee['emergency_contact'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['admin', 'hr'], true)): ?>
                    <div style="margin-top: 20px;">
                        <a href="edit-employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary" style="padding: 12px 20px; display: inline-block; text-decoration: none;">✎ Edit Profile</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    Employee profile not found. Please contact HR.
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
