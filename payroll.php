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
$company = null;
foreach ($companies as $candidate) { if ((int)$candidate['id'] === $companyId) { $company = $candidate; break; } }
$success = isset($_GET['saved']) ? 'Payroll information saved.' : '';
$error = '';
if (empty($_SESSION['payroll_csrf_token'])) { $_SESSION['payroll_csrf_token'] = bin2hex(random_bytes(32)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['payroll_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $postedCompanyId = (int)($_POST['company_id'] ?? $companyId);
        $isAdmin = $role === 'admin';
        $isPayrollAdmin = $isAdmin || ($companyId && $payroll->hasCompanyAccess($companyId, $userId, $role, ['payroll_admin','payroll_hr']));
        if ($action === 'create_company' && in_array($role, ['admin','hr'], true)) {
            $result = $payroll->createCompany($_POST, $userId);
        } elseif (!$isAdmin && !$payroll->hasCompanyAccess($postedCompanyId, $userId, $role)) {
            $result = ['success' => false, 'message' => 'Your account is not assigned to this payroll company.'];
        } elseif ($action === 'update_policy' && $isPayrollAdmin) {
            $result = $payroll->updateCompanyPolicy($postedCompanyId, $_POST, $userId);
        } elseif ($action === 'add_member' && $isPayrollAdmin) {
            $result = $payroll->addCompanyMember($postedCompanyId, (int)($_POST['user_id'] ?? 0), $_POST['profile_key'] ?? '', $userId);
        } elseif ($action === 'add_employee' && $isPayrollAdmin) {
            $result = $payroll->addEmployeeProfile($postedCompanyId, $_POST, $userId);
        } elseif ($action === 'create_period' && $isPayrollAdmin) {
            $result = $payroll->createPeriod($postedCompanyId, $_POST, $userId);
        } elseif ($action === 'create_tax_rule' && $isPayrollAdmin) {
            $result = $payroll->saveStatutoryRule($postedCompanyId, 'tax', $_POST, $userId);
        } elseif ($action === 'create_pension_rule' && $isPayrollAdmin) {
            $result = $payroll->saveStatutoryRule($postedCompanyId, 'pension', $_POST, $userId);
        } elseif ($action === 'review_rule' && $isPayrollAdmin) {
            $result = $payroll->reviewStatutoryRule($postedCompanyId, $_POST['rule_kind'] ?? '', (int)($_POST['rule_id'] ?? 0), $userId, $_POST['decision'] ?? '', trim((string)($_POST['comments'] ?? '')));
        } else {
            $result = ['success' => false, 'message' => 'This action requires payroll administrator access for the selected company.'];
        }
        if (!empty($result['success'])) {
            $_SESSION['payroll_csrf_token'] = bin2hex(random_bytes(32));
            $redirectCompany = $action === 'create_company' ? (int)$result['id'] : $postedCompanyId;
            header('Location: payroll.php?company_id=' . $redirectCompany . '&saved=1');
            exit;
        }
        $error = $result['message'] ?? 'Payroll setup request failed.';
        if ($postedCompanyId) { $companyId = $postedCompanyId; }
    }
}

$companies = $payroll->getCompaniesForUser($userId, $role);
if ($companyId && !$company && $role === 'admin') { $company = $payroll->getCompany($companyId); }
if (!$company && $companyId && $payroll->hasCompanyAccess($companyId, $userId, $role)) { $company = $payroll->getCompany($companyId); }
$canConfigure = $company && $payroll->hasCompanyAccess($companyId, $userId, $role, ['payroll_admin','payroll_hr']);
$profiles = $company ? $payroll->getEmployeeProfiles($companyId) : [];
$periods = $company ? $payroll->getPeriods($companyId) : [];
$runs = $company ? $payroll->getRuns($companyId) : [];
$rules = $company ? $payroll->getRules($companyId) : [];
$users = $company && $canConfigure ? $payroll->getEligibleUsers($companyId) : [];
$employees = $company && $canConfigure ? $payroll->getEligibleEmployees($companyId) : [];
$flags = $company ? $payroll->getComplianceFlags($companyId, date('Y-m-d')) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - HR Software</title><link rel="stylesheet" href="css/style.css">
    <style>
        .payroll-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:20px;align-items:start}.payroll-notice{border-left:4px solid #d97706;background:#fff7ed;color:#7c2d12;padding:12px 16px;margin:12px 0;border-radius:6px}.payroll-help{color:#536273;font-size:.9rem;line-height:1.5}.payroll-table{overflow:auto}.status-pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-weight:700}.json-config{font-family:Consolas,monospace;min-height:110px}@media(max-width:640px){.payroll-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar"><div class="brand"><h2>HR System</h2></div><nav class="nav-menu"><ul>
        <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li><li class="active"><a href="payroll.php"><i>💰</i> Payroll</a></li>
        <?php if (in_array($role,['admin','hr','account'],true)): ?><li><a href="payroll-reports.php"><i>📈</i> Payroll Reports</a></li><?php endif; ?>
        <li><a href="employees.php"><i>👥</i> Employees</a></li><li><a href="view-payslip.php"><i>💵</i> My Payslips</a></li><li class="menu-header">ACCOUNT</li><li><a href="logout.php"><i>🚪</i> Logout</a></li>
    </ul></nav></aside>
    <main class="content"><div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
        <div class="container">
            <div class="page-header"><h1>Payroll Management</h1><p>Company-configured payroll policies, effective-dated rules, controlled payroll processing, review, approval and locking.</p></div>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if (empty($companies) && in_array($role,['admin','hr'],true)): ?>
                <section class="card"><h2>Create payroll company</h2><p class="payroll-help">Payroll companies have separate tax/pension policies, access lists, employee profiles, periods and run history. The creator is assigned payroll administrator for this company.</p>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="action" value="create_company">
                        <div class="form-row"><div class="form-group"><label>Company name</label><input name="name" maxlength="200" required></div><div class="form-group"><label>Company code</label><input name="company_code" pattern="[a-z0-9][a-z0-9-]{1,78}[a-z0-9]" required></div></div>
                        <div class="form-row"><div class="form-group"><label>Country code (optional)</label><input name="country_code" maxlength="2" placeholder="NG"></div><div class="form-group"><label>State/region (optional)</label><input name="state_region" placeholder="Lagos"></div></div>
                        <div class="form-row"><div class="form-group"><label>Currency code</label><input name="currency_code" value="NGN" maxlength="3" required></div><div class="form-group"><label>Timezone</label><input name="timezone" value="Africa/Lagos" required></div></div>
                        <label><input type="checkbox" name="tax_enabled" value="1"> Enable automated PAYE calculation (configure and independently review rules)</label><br>
                        <label><input type="checkbox" name="pension_enabled" value="1"> Enable automated pension calculation (configure and independently review rules)</label><br><br>
                        <button class="btn btn-primary" type="submit">Create payroll company</button>
                    </form>
                </section>
            <?php elseif (empty($companies)): ?>
                <section class="card"><p>No payroll company is assigned to your account. Contact HR or payroll administration.</p></section>
            <?php else: ?>
                <form method="GET" class="card"><label for="company-select">Payroll company</label><select id="company-select" name="company_id" onchange="this.form.submit()"><?php foreach($companies as $item): ?><option value="<?php echo (int)$item['id']; ?>" <?php echo $companyId === (int)$item['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['name'].' ('.$item['currency_code'].')'); ?></option><?php endforeach; ?></select><noscript><button class="btn btn-secondary" type="submit">Open</button></noscript></form>
                <?php if (!$company): ?><section class="card"><p>Company access not found. Contact payroll administration.</p></section>
                <?php else: ?>
                    <?php foreach ($flags as $flag): ?><div class="payroll-notice"><strong>Compliance review required:</strong> <?php echo htmlspecialchars($flag); ?></div><?php endforeach; ?>
                    <div class="payroll-grid">
                        <?php if ($canConfigure): ?>
                        <section class="card"><h2>Company payroll policy</h2><p class="payroll-help">Tax and pension are independent. Disabling automated calculations does not establish legal exemption; review obligations separately.</p>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="company_id" value="<?php echo $companyId; ?>"><input type="hidden" name="action" value="update_policy">
                                <label><input type="checkbox" name="tax_enabled" value="1" <?php echo !empty($company['tax_enabled'])?'checked':''; ?>> Enable PAYE calculation</label><br>
                                <label><input type="checkbox" name="pension_enabled" value="1" <?php echo !empty($company['pension_enabled'])?'checked':''; ?>> Enable pension calculation</label><br><br>
                                <h3>Earning tax/pension treatment</h3>
                                <?php foreach(['allowances'=>'Allowances','bonuses'=>'Bonuses','overtime'=>'Overtime'] as $key=>$label): ?>
                                <div class="form-row"><label class="form-group"><input type="checkbox" name="<?php echo $key; ?>_taxable" value="1" <?php echo !empty(json_decode($company['payroll_settings_json']?:'{}',true)[$key.'_taxable'])?'checked':''; ?>> <?php echo $label; ?> count as taxable</label><label class="form-group"><input type="checkbox" name="<?php echo $key; ?>_pensionable" value="1" <?php echo !empty(json_decode($company['payroll_settings_json']?:'{}',true)[$key.'_pensionable'])?'checked':''; ?>> <?php echo $label; ?> count as pensionable</label></div>
                                <?php endforeach; ?>
                                <div class="form-group"><label>Review and approval profile keys (comma-separated)</label><input name="approval_profiles" value="<?php echo htmlspecialchars(implode(',',json_decode($company['payroll_settings_json']?:'{}',true)['approval_profiles']??['payroll_reviewer','payroll_approver'])); ?>"></div>
                                <button class="btn btn-primary" type="submit">Save company policy</button>
                            </form>
                        </section>
                        <section class="card"><h2>Assign payroll users</h2><p class="payroll-help">Access profiles: payroll_admin, payroll_hr, payroll_preparer, payroll_reviewer, payroll_approver, payroll_account. Assign distinct reviewers and approvers to preserve separation of duties.</p>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="company_id" value="<?php echo $companyId; ?>"><input type="hidden" name="action" value="add_member">
                                <div class="form-group"><label>User</label><select name="user_id" required><option value="">Choose user</option><?php foreach($users as $u): ?><option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['full_name'].' — '.$u['email'].' ('.$u['role'].')'); ?></option><?php endforeach; ?></select></div>
                                <div class="form-group"><label>Company access profile</label><select name="profile_key"><option>payroll_hr</option><option>payroll_preparer</option><option>payroll_reviewer</option><option>payroll_approver</option><option>payroll_account</option><option>payroll_admin</option></select></div><button class="btn btn-primary" type="submit">Assign user</button>
                            </form>
                            <h3>Current payroll users</h3><ul><?php foreach($payroll->getCompanyMembers($companyId) as $m): ?><li><?php echo htmlspecialchars($m['full_name'].' — '.$m['access_profile_key']); ?></li><?php endforeach; ?></ul>
                        </section>
                        <section class="card"><h2>Register existing employees</h2><p class="payroll-help">This links existing HR employee records. It does not create or duplicate employees.</p>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="company_id" value="<?php echo $companyId; ?>"><input type="hidden" name="action" value="add_employee">
                                <div class="form-group"><label>Active HR employee</label><select name="employee_id" required><option value="">Choose employee</option><?php foreach($employees as $e): ?><option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['full_name'].' — '.$e['staff_number'].' — '.($e['department_name']??'')); ?></option><?php endforeach; ?></select></div>
                                <div class="form-row"><div class="form-group"><label>Pay frequency</label><select name="pay_frequency"><option value="monthly">Monthly</option><option value="semimonthly">Semi-monthly</option><option value="biweekly">Biweekly</option><option value="weekly">Weekly</option><option value="quarterly">Quarterly</option><option value="annual">Annual</option><option value="custom">Custom period</option></select></div><div class="form-group"><label>Existing salary basis</label><select name="salary_basis"><option value="monthly">Monthly amount</option><option value="annual">Annual amount</option><option value="per_period">Per-period amount</option></select></div></div>
                                <div class="form-group"><label>Company-specific basic salary override (optional)</label><input type="number" name="base_salary_override" min="0" step="0.01"></div>
                                <h3>Bank payment details (optional)</h3><p class="payroll-help">To collect account number, configure PAYROLL_ENCRYPTION_KEY in the server environment. Account data is encrypted at rest; only last four digits are displayed.</p>
                                <div class="form-row"><div class="form-group"><label>Bank</label><input name="bank_name"></div><div class="form-group"><label>Bank code</label><input name="bank_code"></div></div>
                                <div class="form-row"><div class="form-group"><label>Account name</label><input name="bank_account_name"></div><div class="form-group"><label>Account number</label><input name="bank_account_number" inputmode="numeric" autocomplete="off"></div></div>
                                <button class="btn btn-primary" type="submit">Register employee for payroll</button>
                            </form>
                        </section>
                        <section class="card"><h2>Create PAYE tax rule</h2><p class="payroll-help">No statutory rates are preloaded. Enter verified, current rules, effective dates and source references. New rules need separate independent approval before use.</p>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="company_id" value="<?php echo $companyId; ?>"><input type="hidden" name="action" value="create_tax_rule">
                                <div class="form-group"><label>Rule name / jurisdiction</label><input name="rule_name" required placeholder="Organisation-named PAYE rule"><input name="jurisdiction_code" placeholder="Country/state code e.g. NG-LA"></div>
                                <div class="form-row"><div class="form-group"><label>Formula</label><select name="formula_type"><option value="progressive_annual">Annual progressive bands</option><option value="percentage_per_period">Percentage per pay period</option><option value="fixed_per_period">Fixed amount per period</option></select></div><div class="form-group"><label>Version label</label><input name="rule_version"></div></div>
                                <div class="form-group"><label>Rule configuration JSON</label><textarea class="json-config" name="rule_config_json" required placeholder='{"bands":[{"up_to":500000,"rate_percent":7},{"up_to":null,"rate_percent":15}],"annual_relief_fixed":0,"annual_relief_percent":0}'></textarea><small>Example is structural only and is not a legal rate recommendation.</small></div>
                                <div class="form-row"><div class="form-group"><label>Effective from</label><input type="date" name="effective_from" required></div><div class="form-group"><label>Effective to</label><input type="date" name="effective_to"></div></div><div class="form-group"><label>Source / legal reference</label><input name="source_reference" required></div><button class="btn btn-primary" type="submit">Save pending-review tax rule</button>
                            </form>
                        </section>
                        <section class="card"><h2>Create pension rule</h2><p class="payroll-help">Employee and employer rates or fixed amounts are independently configurable, effective-dated, and reviewed before use.</p>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="company_id" value="<?php echo $companyId; ?>"><input type="hidden" name="action" value="create_pension_rule">
                                <div class="form-group"><label>Rule name / jurisdiction</label><input name="rule_name" required><input name="jurisdiction_code" placeholder="Country/state code"></div>
                                <div class="form-row"><div class="form-group"><label>Formula</label><select name="formula_type"><option value="percentage">Rates by contribution basis</option><option value="fixed_per_period">Fixed amounts per period</option></select></div><div class="form-group"><label>Version label</label><input name="rule_version"></div></div>
                                <div class="form-group"><label>Rule configuration JSON</label><textarea class="json-config" name="rule_config_json" required placeholder='{"basis":"pensionable_earnings","employee_rate_percent":8,"employer_rate_percent":10}'></textarea><small>Example is structural only. Confirm current law and company policy before activation.</small></div>
                                <div class="form-row"><div class="form-group"><label>Effective from</label><input type="date" name="effective_from" required></div><div class="form-group"><label>Effective to</label><input type="date" name="effective_to"></div></div><div class="form-group"><label>Source / legal reference</label><input name="source_reference" required></div><button class="btn btn-primary" type="submit">Save pending-review pension rule</button>
                            </form>
                        </section>
                        <section class="card"><h2>Create pay period</h2><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="company_id" value="<?php echo $companyId; ?>"><input type="hidden" name="action" value="create_period">
                            <div class="form-group"><label>Period name</label><input name="name" required placeholder="Payroll period label"></div><div class="form-row"><div class="form-group"><label>Frequency</label><select name="period_type"><option value="monthly">Monthly</option><option value="semimonthly">Semi-monthly</option><option value="biweekly">Biweekly</option><option value="weekly">Weekly</option><option value="quarterly">Quarterly</option><option value="annual">Annual</option><option value="custom">Custom</option></select></div><div class="form-group"><label>Period label</label><input name="period_label"></div></div>
                            <div class="form-row"><div class="form-group"><label>Starts on</label><input type="date" name="starts_on" required></div><div class="form-group"><label>Ends on</label><input type="date" name="ends_on" required></div></div><div class="form-group"><label>Pay date</label><input type="date" name="pay_date"></div><button class="btn btn-primary" type="submit">Create period</button>
                        </form></section>
                        <?php endif; ?>
                    </div>
                    <section class="card"><h2>Company employees (<?php echo count($profiles); ?>)</h2><div class="payroll-table"><table class="table"><thead><tr><th>Employee</th><th>Staff ID</th><th>Department</th><th>Branch</th><th>Frequency</th><th>Salary basis</th><th>Bank</th><th>Account</th></tr></thead><tbody><?php if(!$profiles): ?><tr><td colspan="8" class="text-center">No payroll employees registered.</td></tr><?php else: foreach($profiles as $profile): ?><tr><td><?php echo htmlspecialchars($profile['full_name']); ?></td><td><?php echo htmlspecialchars($profile['staff_number']); ?></td><td><?php echo htmlspecialchars($profile['department_name']??'—'); ?></td><td><?php echo htmlspecialchars($profile['branch_name']??'—'); ?></td><td><?php echo htmlspecialchars($profile['pay_frequency']); ?></td><td><?php echo htmlspecialchars($profile['salary_basis']); ?></td><td><?php echo htmlspecialchars($profile['bank_name']??'—'); ?></td><td><?php echo !empty($profile['bank_account_last4'])?'•••• '.htmlspecialchars($profile['bank_account_last4']):'—'; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
                    <section class="card"><h2>Effective-dated statutory rules</h2><div class="payroll-table"><table class="table"><thead><tr><th>Type</th><th>Rule</th><th>Jurisdiction</th><th>Formula</th><th>Effective</th><th>Status</th><th>Source</th><th>Review</th></tr></thead><tbody><?php if(!$rules): ?><tr><td colspan="8" class="text-center">No statutory rules configured.</td></tr><?php else: foreach($rules as $rule): ?><tr><td><?php echo strtoupper($rule['rule_kind']); ?></td><td><?php echo htmlspecialchars($rule['rule_name']); ?></td><td><?php echo htmlspecialchars($rule['jurisdiction_code']??'—'); ?></td><td><?php echo htmlspecialchars($rule['formula_type']); ?></td><td><?php echo htmlspecialchars($rule['effective_from'].' to '.($rule['effective_to']?:'open')); ?></td><td><?php echo htmlspecialchars($rule['status']); ?></td><td><?php echo htmlspecialchars($rule['source_reference']??'—'); ?></td><td><?php if($canConfigure && $rule['status']==='pending_review' && (int)$rule['created_by']!==$userId): ?><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']); ?>"><input type="hidden" name="company_id" value="<?php echo $companyId; ?>"><input type="hidden" name="action" value="review_rule"><input type="hidden" name="rule_kind" value="<?php echo $rule['rule_kind']; ?>"><input type="hidden" name="rule_id" value="<?php echo (int)$rule['id']; ?>"><input type="text" name="comments" placeholder="Review note" required><button name="decision" value="approve" class="btn btn-small btn-primary">Approve</button><button name="decision" value="reject" class="btn btn-small btn-danger">Reject</button></form><?php elseif($rule['status']==='pending_review'): ?>Waiting for a different reviewer<?php else: ?><?php echo htmlspecialchars($rule['reviewed_by_name']??''); ?><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
                    <section class="card"><h2>Payroll periods and runs</h2><div class="payroll-table"><table class="table"><thead><tr><th>Period</th><th>Dates</th><th>Pay date</th><th>Period status</th><th>Runs</th><th>Action</th></tr></thead><tbody><?php if(!$periods): ?><tr><td colspan="6" class="text-center">No payroll periods created.</td></tr><?php else: foreach($periods as $period): ?><tr><td><?php echo htmlspecialchars($period['name']); ?></td><td><?php echo htmlspecialchars($period['starts_on'].' to '.$period['ends_on']); ?></td><td><?php echo htmlspecialchars($period['pay_date']??'—'); ?></td><td><?php echo htmlspecialchars($period['status']); ?></td><td><?php echo (int)$period['run_count']; ?></td><td><?php if($canConfigure && $period['status']==='open' && (int)$period['run_count']===0): ?><a class="btn btn-small btn-primary" href="payroll-run.php?company_id=<?php echo $companyId; ?>&period_id=<?php echo (int)$period['id']; ?>">Prepare payroll</a><?php else: ?><span>See payroll runs</span><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
                    <section class="card"><h2>Payroll runs</h2><div class="payroll-table"><table class="table"><thead><tr><th>Period</th><th>Run #</th><th>Status</th><th>Gross</th><th>PAYE</th><th>Employee pension</th><th>Net</th><th>Action</th></tr></thead><tbody><?php if(!$runs): ?><tr><td colspan="8" class="text-center">No payroll runs yet.</td></tr><?php else: foreach($runs as $run): ?><tr><td><?php echo htmlspecialchars($run['period_name']); ?></td><td><?php echo (int)$run['run_number']; ?></td><td><span class="status-pill"><?php echo htmlspecialchars($run['status']); ?></span></td><td><?php echo number_format($run['total_gross'],2); ?></td><td><?php echo number_format($run['total_tax'],2); ?></td><td><?php echo number_format($run['total_employee_pension'],2); ?></td><td><strong><?php echo number_format($run['total_net'],2); ?></strong></td><td><a class="btn btn-small btn-secondary" href="payroll-run.php?company_id=<?php echo $companyId; ?>&run_id=<?php echo (int)$run['id']; ?>">Open</a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
