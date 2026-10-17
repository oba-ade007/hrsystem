<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'account']);

require_once '../src/config/database.php';
require_once '../src/models/Payroll.php';

$payroll = new Payroll($db);
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];
$companies = $payroll->getCompaniesForUser($userId, $role);
$companyId = (int)($_GET['company_id'] ?? $_POST['company_id'] ?? ($companies[0]['id'] ?? 0));
$runId = (int)($_GET['run_id'] ?? $_POST['run_id'] ?? 0);
$type = $_GET['type'] ?? 'summary';
$allowedTypes = ['summary', 'tax', 'pension', 'bank'];
if (!in_array($type, $allowedTypes, true)) { $type = 'summary'; }

if (!$companyId || !$payroll->hasCompanyAccess($companyId, $userId, $role)) {
    http_response_code(403);
    exit('You do not have access to this payroll company.');
}

$company = $payroll->getCompany($companyId);
$rows = $runId ? $payroll->getReportRows($companyId, $runId, $type) : [];
$run = $runId ? $payroll->getRun($companyId, $runId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Reports - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 18px; }
        .report-card { border: 1px solid #dfe7f1; border-radius: 10px; padding: 14px; background: #fff; }
        .table-wrap { overflow: auto; }
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
                <li class="active"><a href="payroll-reports.php"><i>📈</i> Payroll Reports</a></li>
                <li><a href="view-payslip.php"><i>💵</i> My Payslips</a></li>
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
                <h1>Payroll reports</h1>
                <p>Company payroll summaries, PAYE, pension, and bank schedules for the selected payroll run.</p>
            </div>

            <?php if (empty($companies)): ?>
                <section class="card"><p>No payroll company is available for this account.</p></section>
            <?php else: ?>
                <form method="GET" class="card">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payroll company</label>
                            <select name="company_id" onchange="this.form.submit()">
                                <?php foreach ($companies as $option): ?>
                                    <option value="<?php echo (int)$option['id']; ?>" <?php echo (int)$option['id'] === $companyId ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Report type</label>
                            <select name="type" onchange="this.form.submit()">
                                <option value="summary" <?php echo $type === 'summary' ? 'selected' : ''; ?>>Summary</option>
                                <option value="tax" <?php echo $type === 'tax' ? 'selected' : ''; ?>>PAYE</option>
                                <option value="pension" <?php echo $type === 'pension' ? 'selected' : ''; ?>>Pension</option>
                                <option value="bank" <?php echo $type === 'bank' ? 'selected' : ''; ?>>Bank schedule</option>
                            </select>
                        </div>
                    </div>
                    <noscript><button type="submit" class="btn btn-secondary">Load</button></noscript>
                </form>

                <?php if ($company && !$runId): ?>
                    <section class="card">
                        <h2>Recent runs</h2>
                        <?php $runs = $payroll->getRuns($companyId); ?>
                        <?php if (empty($runs)): ?>
                            <p>No payroll run has been prepared for this company yet.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($runs as $runRow): ?>
                                    <li>
                                        <a href="payroll-reports.php?company_id=<?php echo (int)$companyId; ?>&run_id=<?php echo (int)$runRow['id']; ?>&type=<?php echo $type; ?>"><?php echo htmlspecialchars($runRow['period_name']); ?> — <?php echo htmlspecialchars($runRow['status']); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php elseif ($run): ?>
                    <div class="report-grid">
                        <div class="report-card">
                            <strong>Run</strong>
                            <p><?php echo htmlspecialchars($run['period_name']); ?> / #<?php echo (int)$run['run_number']; ?></p>
                        </div>
                        <div class="report-card">
                            <strong>Status</strong>
                            <p><?php echo htmlspecialchars($run['status']); ?></p>
                        </div>
                        <div class="report-card">
                            <strong>Gross</strong>
                            <p><?php echo htmlspecialchars($company['currency_code']); ?> <?php echo number_format((float)$run['total_gross'], 2); ?></p>
                        </div>
                        <div class="report-card">
                            <strong>Net</strong>
                            <p><?php echo htmlspecialchars($company['currency_code']); ?> <?php echo number_format((float)$run['total_net'], 2); ?></p>
                        </div>
                    </div>

                    <?php if (empty($rows)): ?>
                        <section class="card"><p>No rows are available for this report type.</p></section>
                    <?php else: ?>
                        <section class="card">
                            <h2><?php echo strtoupper($type); ?> report</h2>
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Staff</th>
                                            <th>Department</th>
                                            <th>Branch</th>
                                            <?php if ($type === 'summary'): ?>
                                                <th>Basic</th><th>Allow.</th><th>Bonus</th><th>OT</th><th>Gross</th><th>PAYE</th><th>Emp. Pension</th><th>Net</th>
                                            <?php elseif ($type === 'tax'): ?>
                                                <th>Taxable</th><th>PAYE</th><th>Override</th><th>Reason</th>
                                            <?php elseif ($type === 'pension'): ?>
                                                <th>Pensionable</th><th>Employee</th><th>Employer</th><th>Override</th><th>Reason</th>
                                            <?php else: ?>
                                                <th>Bank</th><th>Account</th><th>Last 4</th><th>Net</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['staff_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($row['branch_name'] ?? '—'); ?></td>
                                                <?php if ($type === 'summary'): ?>
                                                    <td><?php echo number_format((float)($row['basic_salary'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['allowances'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['bonuses'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['overtime'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['gross_salary'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['paye_tax'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['employee_pension'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['net_salary'] ?? 0), 2); ?></td>
                                                <?php elseif ($type === 'tax'): ?>
                                                    <td><?php echo number_format((float)($row['taxable_earnings'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['paye_tax'] ?? 0), 2); ?></td>
                                                    <td><?php echo $row['tax_override_amount'] !== null ? number_format((float)$row['tax_override_amount'], 2) : '—'; ?></td>
                                                    <td><?php echo htmlspecialchars($row['tax_override_reason'] ?? '—'); ?></td>
                                                <?php elseif ($type === 'pension'): ?>
                                                    <td><?php echo number_format((float)($row['pensionable_earnings'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['employee_pension'] ?? 0), 2); ?></td>
                                                    <td><?php echo number_format((float)($row['employer_pension'] ?? 0), 2); ?></td>
                                                    <td><?php echo $row['employee_pension_override'] !== null || $row['employer_pension_override'] !== null ? number_format((float)($row['employee_pension_override'] ?? 0), 2) . ' / ' . number_format((float)($row['employer_pension_override'] ?? 0), 2) : '—'; ?></td>
                                                    <td><?php echo htmlspecialchars($row['pension_override_reason'] ?? '—'); ?></td>
                                                <?php else: ?>
                                                    <td><?php echo htmlspecialchars($row['bank_name'] ?? '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['bank_account_name'] ?? '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['bank_account_last4'] ?? '—'); ?></td>
                                                    <td><?php echo number_format((float)($row['net_salary'] ?? 0), 2); ?></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
