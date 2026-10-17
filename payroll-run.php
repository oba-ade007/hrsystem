<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'account']);
require_once '../src/config/database.php';
require_once '../src/models/Payroll.php';

$payroll = new Payroll($db);
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];
$companyId = (int)($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
$runId = (int)($_GET['run_id'] ?? $_POST['run_id'] ?? 0);
$periodId = (int)($_GET['period_id'] ?? $_POST['period_id'] ?? 0);
if (!$payroll->hasCompanyAccess($companyId, $userId, $role)) { http_response_code(403); exit('You do not have access to this payroll company.'); }
$canPrepare = $role === 'admin' || $payroll->hasCompanyAccess($companyId, $userId, $role, ['payroll_admin','payroll_hr','payroll_preparer']);
$success = isset($_GET['saved']) ? 'Payroll action completed.' : '';
$error = '';
if (empty($_SESSION['payroll_csrf_token'])) { $_SESSION['payroll_csrf_token'] = bin2hex(random_bytes(32)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['payroll_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'prepare') {
        if (!$canPrepare) { $error = 'Your payroll access profile cannot prepare a run.'; }
        else {
            $result = $payroll->prepareRun($companyId, $periodId, $userId, is_array($_POST['entries'] ?? null) ? $_POST['entries'] : []);
            if (!empty($result['success'])) { $_SESSION['payroll_csrf_token']=bin2hex(random_bytes(32)); header('Location: payroll-run.php?company_id='.$companyId.'&run_id='.(int)$result['id'].'&saved=1'); exit; }
            $error = $result['message'] ?? 'Payroll preparation failed.';
        }
    } else {
        $result = $payroll->transitionRun($companyId, $runId, $userId, $_POST['action'] ?? '', trim((string)($_POST['comments'] ?? '')));
        if (!empty($result['success'])) { $_SESSION['payroll_csrf_token']=bin2hex(random_bytes(32)); header('Location: payroll-run.php?company_id='.$companyId.'&run_id='.$runId.'&saved=1'); exit; }
        $error = $result['message'] ?? 'Payroll workflow action failed.';
    }
}

$run = $runId ? $payroll->getRun($companyId, $runId) : null;
$items = $run ? $payroll->getRunItems($companyId, $runId) : [];
$period = null;
if (!$run && $periodId) {
    $stmt=$db->prepare("SELECT * FROM payroll_periods WHERE company_id=? AND id=? AND status='open'");$stmt->bind_param('ii',$companyId,$periodId);$stmt->execute();$period=$stmt->get_result()->fetch_assoc();
    if (!$period) { http_response_code(404); exit('Open payroll period not found.'); }
    $eligible=$payroll->getEmployeeProfiles($companyId);
} elseif ($run) {
    $company=$payroll->getCompany($companyId);
    $runFlags=json_decode($run['compliance_flags_json']?:'[]',true)?:[];
} else { http_response_code(400); exit('Select a payroll period or run.'); }
$profile = null;
$members=$payroll->getCompanyMembers($companyId);
foreach($members as $member){if((int)$member['user_id']===$userId){$profile=$member['access_profile_key'];break;}}
$action=[];
if($run){
    if($run['status']==='prepared' && in_array($profile,['payroll_admin','payroll_hr','payroll_preparer'],true)){$action=['submit_review','Submit for review'];}
    elseif($run['status']==='pending_review' && in_array($profile,['payroll_reviewer','payroll_account','payroll_admin'],true)){$action=['review','Mark reviewed'];}
    elseif($run['status']==='reviewed' && in_array($profile,['payroll_approver','payroll_admin'],true)){$action=['approve','Approve payroll'];}
    elseif($run['status']==='approved' && in_array($profile,['payroll_approver','payroll_admin'],true)){$action=['lock','Lock payroll'];}
    elseif($run['status']==='locked' && in_array($profile,['payroll_account','payroll_admin'],true)){$action=['mark_paid','Mark paid'];}
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Payroll Run - HR Software</title><link rel="stylesheet" href="css/style.css"><style>.run-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}.employee-pay{border:1px solid #dfe7f1;background:#fff;border-radius:10px;padding:16px;margin:14px 0}.component-row{display:grid;grid-template-columns:1.3fr 1fr 1fr 1fr;gap:8px;margin:8px 0}.muted{color:#596879;font-size:.9rem}.payroll-table{overflow:auto}@media(max-width:720px){.component-row{grid-template-columns:1fr 1fr}}</style></head>
<body><div class="wrapper"><aside class="sidebar"><div class="brand"><h2>HR System</h2></div><nav class="nav-menu"><ul><li><a href="dashboard.php"><i>📊</i> Dashboard</a></li><li class="active"><a href="payroll.php"><i>💰</i> Payroll</a></li><li><a href="payroll-reports.php"><i>📈</i> Payroll Reports</a></li><li><a href="view-payslip.php"><i>💵</i> My Payslips</a></li><li class="menu-header">ACCOUNT</li><li><a href="logout.php"><i>🚪</i> Logout</a></li></ul></nav></aside>
<main class="content"><div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div><div class="container"><div class="page-header"><h1><?php echo $run?'Payroll Run #'.(int)$run['run_number']:'Prepare Payroll'; ?></h1><p><?php echo htmlspecialchars($run?$run['company_name'].' · '.$run['period_name']:$period['name']); ?></p></div>
<?php if($success):?><div class="alert alert-success"><?php echo htmlspecialchars($success);?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?php echo htmlspecialchars($error);?></div><?php endif;?>
<?php if($period):?><section class="card"><h2>Payroll inputs</h2><p class="muted">Basic salary is sourced from the existing HR employee record unless a company-specific payroll salary override is set. Variable items and statutory overrides are stored in this run. Every manual tax/pension override requires a reason.</p>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']);?>"><input type="hidden" name="company_id" value="<?php echo $companyId;?>"><input type="hidden" name="period_id" value="<?php echo $periodId;?>"><input type="hidden" name="action" value="prepare">
<?php foreach($eligible as $e):?><article class="employee-pay"><h3><?php echo htmlspecialchars($e['full_name'].' · '.$e['staff_number']);?></h3><p class="muted"><?php echo htmlspecialchars(($e['department_name']??''). ' · '.($e['branch_name']??'').' · '.($e['position']??''));?> · HR salary <?php echo number_format((float)($e['base_salary_override']??$e['salary']),2);?> (<?php echo htmlspecialchars($e['salary_basis']);?>)</p>
<div class="run-grid"><div class="form-group"><label>Allowances</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][allowances]" value="0"></div><div class="form-group"><label>Bonus</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][bonuses]" value="0"></div><div class="form-group"><label>Overtime</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][overtime]" value="0"></div><div class="form-group"><label>Loan repayment</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][loan_deduction]" value="0"></div><div class="form-group"><label>Other deductions</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][other_deductions]" value="0"></div><div class="form-group"><label>PAYE override (optional)</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][tax_override_amount]"></div><div class="form-group"><label>Employee pension override</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][employee_pension_override]"></div><div class="form-group"><label>Employer pension override</label><input type="number" min="0" step="0.01" name="entries[<?php echo (int)$e['employee_id'];?>][employer_pension_override]"></div></div>
<div class="form-row"><div class="form-group"><label>PAYE override reason</label><input name="entries[<?php echo (int)$e['employee_id'];?>][tax_override_reason]"></div><div class="form-group"><label>Pension override reason</label><input name="entries[<?php echo (int)$e['employee_id'];?>][pension_override_reason]"></div></div>
<div class="form-group"><label>Additional earning/deduction components</label><div class="component-list" data-employee="<?php echo (int)$e['employee_id'];?>"></div><button type="button" class="btn btn-small btn-secondary add-component">Add component</button></div></article><?php endforeach;?>
<button type="submit" class="btn btn-primary" onclick="return confirm('Prepare this payroll run? Prepared lines will be snapshotted and require review before approval.')">Prepare payroll run</button></form></section>
<?php elseif($run):?><section class="card"><h2>Run status: <?php echo htmlspecialchars($run['status']);?></h2><p><?php echo htmlspecialchars($run['starts_on'].' to '.$run['ends_on'].' · pay date '.($run['pay_date']??'not set'));?></p><?php foreach($runFlags??[] as $flag):?><div class="payroll-notice"><?php echo htmlspecialchars($flag);?></div><?php endforeach;?><div class="run-grid"><div><strong>Gross:</strong> <?php echo htmlspecialchars($run['currency_code']).' '.number_format($run['total_gross'],2);?></div><div><strong>PAYE:</strong> <?php echo number_format($run['total_tax'],2);?></div><div><strong>Employee pension:</strong> <?php echo number_format($run['total_employee_pension'],2);?></div><div><strong>Employer pension:</strong> <?php echo number_format($run['total_employer_pension'],2);?></div><div><strong>Deductions:</strong> <?php echo number_format($run['total_deductions'],2);?></div><div><strong>Net pay:</strong> <?php echo number_format($run['total_net'],2);?></div></div>
<?php if($action):?><form method="POST" style="margin-top:18px"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf_token']);?>"><input type="hidden" name="company_id" value="<?php echo $companyId;?>"><input type="hidden" name="run_id" value="<?php echo $runId;?>"><div class="form-group"><label>Review / return / rejection comments</label><textarea name="comments" rows="2"></textarea></div><button class="btn btn-primary" type="submit" name="action" value="<?php echo $action[0];?>" onclick="return confirm('Proceed with: <?php echo htmlspecialchars($action[1],ENT_QUOTES);?>?')"><?php echo htmlspecialchars($action[1]);?></button><?php if($run['status']==='pending_review'):?><button class="btn btn-secondary" name="action" value="return" type="submit">Return to preparer</button><button class="btn btn-danger" name="action" value="reject" type="submit">Reject run</button><?php endif;?></form><?php endif;?>
<p><a class="btn btn-secondary" href="payroll-reports.php?company_id=<?php echo $companyId;?>&run_id=<?php echo $runId;?>&type=summary">Payroll summary</a> <a class="btn btn-secondary" href="payroll-reports.php?company_id=<?php echo $companyId;?>&run_id=<?php echo $runId;?>&type=tax">PAYE report</a> <a class="btn btn-secondary" href="payroll-reports.php?company_id=<?php echo $companyId;?>&run_id=<?php echo $runId;?>&type=pension">Pension report</a> <a class="btn btn-secondary" href="payroll-reports.php?company_id=<?php echo $companyId;?>&run_id=<?php echo $runId;?>&type=bank">Bank schedule</a></p>
<div class="payroll-table"><table class="table"><thead><tr><th>Employee</th><th>Department</th><th>Branch</th><th>Basic</th><th>Allowances</th><th>Bonus</th><th>Overtime</th><th>Gross</th><th>PAYE</th><th>Employee pension</th><th>Employer pension</th><th>Loans</th><th>Other deduction</th><th>Net</th><th>Payslip</th></tr></thead><tbody><?php foreach($items as $item):?><tr><td><?php echo htmlspecialchars($item['full_name'].' ('.$item['staff_number'].')');?></td><td><?php echo htmlspecialchars($item['department_name']??'—');?></td><td><?php echo htmlspecialchars($item['branch_name']??'—');?></td><?php foreach(['basic_salary','allowances','bonuses','overtime','gross_salary','paye_tax','employee_pension','employer_pension','loan_deduction','other_deductions','net_salary'] as $field):?><td><?php echo number_format((float)$item[$field],2);?></td><?php endforeach;?><td><?php if(in_array($run['status'],['locked','paid'],true)):?><a href="payroll-payslip.php?company_id=<?php echo $companyId;?>&item_id=<?php echo (int)$item['id'];?>">Print</a><?php else:?>After lock<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section><?php endif;?>
</div></main></div>
<script>
(function(){document.querySelectorAll('.employee-pay').forEach(function(card){var list=card.querySelector('.component-list');var button=card.querySelector('.add-component');if(!list||!button)return;var employee=list.dataset.employee;button.addEventListener('click',function(){var index=list.children.length;var row=document.createElement('div');row.className='component-row';row.innerHTML='<select name="entries['+employee+'][components]['+index+'][component_type]"><option value="earning">Earning</option><option value="deduction">Deduction</option></select><input name="entries['+employee+'][components]['+index+'][label]" placeholder="Component label" required><input type="number" min="0" step="0.01" name="entries['+employee+'][components]['+index+'][amount]" placeholder="Amount" required><label><input type="checkbox" name="entries['+employee+'][components]['+index+'][taxable]" value="1"> Taxable</label><label><input type="checkbox" name="entries['+employee+'][components]['+index+'][pensionable]" value="1"> Pensionable</label>';list.appendChild(row);});});})();
</script>
</body></html>
