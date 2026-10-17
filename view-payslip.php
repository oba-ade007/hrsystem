<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';

$user_id = $_SESSION['user_id'];
$employeeModel = new Employee($db);
$employee = $employeeModel->getEmployeeByUserId($user_id);

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get employee payslips
$result = $db->query("
    SELECT p.*, e.salary, e.employee_id, u.full_name
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    WHERE e.user_id = $user_id AND MONTH(p.created_at) = $month AND YEAR(p.created_at) = $year
    ORDER BY p.created_at DESC
");

$payslips = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payslips - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .payslip-details {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .payslip-header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .payslip-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .payslip-total {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-weight: bold;
            border-top: 2px solid #333;
            margin-top: 10px;
        }
        .btn-print {
            background-color: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .btn-print:hover {
            background-color: #229954;
        }
    </style>
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
                    <li class="active"><a href="view-payslip.php"><i>💵</i> My Payslips</a></li>
                    <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
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
                    <h1>My Payslips</h1>
                </div>
                
                <div class="card">
                    <h2>View Payslips</h2>
                    <form method="GET" class="filter-form" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <select name="month" required style="padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px;">
                            <?php for($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?php echo $i == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" required style="padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px;">
                            <?php for($i = 2020; $i <= date('Y'); $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>
                
                <?php if (!empty($payslips)): ?>
                    <?php foreach($payslips as $payslip): ?>
                    <div class="payslip-details">
                        <div class="payslip-header">
                            <h3>Payslip for <?php echo date('F Y', strtotime($payslip['created_at'])); ?></h3>
                            <p><strong>Employee:</strong> <?php echo htmlspecialchars($payslip['full_name']); ?> | <strong>ID:</strong> <?php echo htmlspecialchars($payslip['employee_id']); ?></p>
                        </div>
                        
                        <div class="payslip-row">
                            <span><strong>Salary (Monthly):</strong></span>
                            <span>₦<?php echo number_format($payslip['salary'], 2); ?></span>
                        </div>
                        
                        <?php if ($payslip['deduction']): ?>
                        <div class="payslip-row">
                            <span><strong>Deductions:</strong></span>
                            <span>-₦<?php echo number_format($payslip['deduction'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($payslip['bonus']): ?>
                        <div class="payslip-row">
                            <span><strong>Bonus:</strong></span>
                            <span>+₦<?php echo number_format($payslip['bonus'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="payslip-total">
                            <span>Net Salary:</span>
                            <span>₦<?php echo number_format($payslip['net_salary'], 2); ?></span>
                        </div>
                        
                        <div class="payslip-row" style="border-bottom: none;">
                            <span><strong>Payment Date:</strong></span>
                            <span><?php echo date('d-m-Y', strtotime($payslip['payment_date'])); ?></span>
                        </div>
                        
                        <button class="btn-print" onclick="window.print()">🖨️ Print Payslip</button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No payslips found for the selected month and year.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
