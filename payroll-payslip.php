<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Payroll.php';

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];
$companyId = (int)($_GET['company_id'] ?? 0);
$itemId = (int)($_GET['item_id'] ?? 0);

if (!$companyId || !$itemId) {
    http_response_code(400);
    exit('Select a valid payroll item.');
}

$payroll = new Payroll($db);
$company = $payroll->getCompany($companyId);
if (!$company) {
    http_response_code(404);
    exit('Payroll company not found.');
}

$stmt = $db->prepare("SELECT item.*, run.status AS run_status, run.total_gross, run.total_tax, run.total_employee_pension, run.total_net, period.name AS period_name, period.starts_on, period.ends_on, period.pay_date, employee.employee_id AS staff_number, employee.position, employee.grade_level, employee.branch_id, employee.department_id, user.full_name, department.name AS department_name, branch.name AS branch_name
    FROM payroll_run_items item
    JOIN payroll_runs run ON run.company_id = item.company_id AND run.id = item.run_id
    JOIN payroll_periods period ON period.company_id = run.company_id AND period.id = run.period_id
    JOIN employees employee ON employee.id = item.employee_id
    JOIN users user ON user.id = employee.user_id
    LEFT JOIN departments department ON department.id = employee.department_id
    LEFT JOIN branches branch ON branch.id = employee.branch_id
    WHERE item.company_id = ? AND item.id = ? LIMIT 1");
$stmt->bind_param('ii', $companyId, $itemId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
if (!$item) {
    http_response_code(404);
    exit('This payslip is not available.');
}

$hasAccess = $role === 'admin' || $role === 'hr' || $userId === (int)$db->query("SELECT user_id FROM employees WHERE id = " . (int)$item['employee_id'])->fetch_assoc()['user_id'] || $payroll->hasCompanyAccess($companyId, $userId, $role, ['payroll_admin', 'payroll_hr', 'payroll_account', 'payroll_approver', 'payroll_reviewer', 'payroll_preparer']);
if (!$hasAccess) {
    http_response_code(403);
    exit('You do not have access to this payslip.');
}

$components = $payroll->getRunComponents($companyId, (int)$item['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($item['full_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .payslip-box { max-width: 920px; margin: 0 auto; border: 1px solid #dfe7f1; background: #fff; border-radius: 12px; padding: 24px; }
        .payslip-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 24px; }
        .payslip-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .summary-row { display: flex; justify-content: space-between; border-bottom: 1px solid #eef2f7; padding: 8px 0; }
        .summary-total { border-top: 2px solid #111827; font-weight: 700; }
    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand"><h2>HR System</h2></div>
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="payroll.php"><i>💰</i> Payroll</a></li>
                    <li><a href="payroll-reports.php?company_id=<?php echo (int)$companyId; ?>&run_id=<?php echo (int)$item['run_id']; ?>&type=summary"><i>📈</i> Payroll Reports</a></li>
                    <li class="active"><a href="payroll-payslip.php?company_id=<?php echo (int)$companyId; ?>&item_id=<?php echo (int)$item['id']; ?>"><i>💵</i> Payslip</a></li>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        <main class="content">
            <div class="topbar">
                <div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div>
            </div>
            <div class="container">
                <div class="page-header">
                    <h1>Payslip</h1>
                    <p><?php echo htmlspecialchars($company['name']); ?> · <?php echo htmlspecialchars($item['period_name']); ?></p>
                </div>
                <div class="payslip-box">
                    <div class="payslip-header">
                        <div>
                            <h2><?php echo htmlspecialchars($item['full_name']); ?></h2>
                            <p><?php echo htmlspecialchars($item['staff_number']); ?> · <?php echo htmlspecialchars($item['position'] ?? '—'); ?></p>
                            <p><?php echo htmlspecialchars($item['department_name'] ?? '—'); ?> · <?php echo htmlspecialchars($item['branch_name'] ?? '—'); ?></p>
                        </div>
                        <div>
                            <p><strong>Pay period:</strong> <?php echo htmlspecialchars($item['starts_on']); ?> to <?php echo htmlspecialchars($item['ends_on']); ?></p>
                            <p><strong>Pay date:</strong> <?php echo htmlspecialchars($item['pay_date'] ?? '—'); ?></p>
                            <p><strong>Status:</strong> <?php echo htmlspecialchars($item['run_status']); ?></p>
                        </div>
                    </div>

                    <div class="payslip-summary">
                        <div><strong>Basic Salary</strong><div><?php echo number_format((float)$item['basic_salary'], 2); ?></div></div>
                        <div><strong>Allowances</strong><div><?php echo number_format((float)$item['allowances'], 2); ?></div></div>
                        <div><strong>Bonuses</strong><div><?php echo number_format((float)$item['bonuses'], 2); ?></div></div>
                        <div><strong>Overtime</strong><div><?php echo number_format((float)$item['overtime'], 2); ?></div></div>
                        <div><strong>Gross Salary</strong><div><?php echo number_format((float)$item['gross_salary'], 2); ?></div></div>
                    </div>

                    <div class="summary-row"><span>PAYE tax</span><span><?php echo number_format((float)$item['paye_tax'], 2); ?></span></div>
                    <div class="summary-row"><span>Employee pension</span><span><?php echo number_format((float)$item['employee_pension'], 2); ?></span></div>
                    <div class="summary-row"><span>Employer pension</span><span><?php echo number_format((float)$item['employer_pension'], 2); ?></span></div>
                    <div class="summary-row"><span>Loan deduction</span><span><?php echo number_format((float)$item['loan_deduction'], 2); ?></span></div>
                    <div class="summary-row"><span>Other deductions</span><span><?php echo number_format((float)$item['other_deductions'], 2); ?></span></div>
                    <div class="summary-row summary-total"><span>Net salary</span><span><?php echo number_format((float)$item['net_salary'], 2); ?></span></div>

                    <?php if (!empty($components)): ?>
                        <h3>Component breakdown</h3>
                        <table class="table">
                            <thead>
                                <tr><th>Type</th><th>Code</th><th>Label</th><th>Amount</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($components as $component): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($component['component_type']); ?></td>
                                        <td><?php echo htmlspecialchars($component['component_code'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($component['label']); ?></td>
                                        <td><?php echo number_format((float)$component['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <p style="margin-top: 18px;"><a href="javascript:window.print()" class="btn btn-primary">Print payslip</a></p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
