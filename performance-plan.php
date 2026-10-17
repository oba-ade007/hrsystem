<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/PerformancePlanning.php';

$planningModel = new PerformancePlanning($db);
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$organisations = $planningModel->getOrganisationsForUser($userId, $userRole);
$employeeOrganisations = [];
foreach ($organisations as $organisation) {
    $membership = $planningModel->getEmployeeMembership((int)$organisation['id'], $userId);
    if ($membership) {
        $organisation['membership'] = $membership;
        $employeeOrganisations[] = $organisation;
    }
}

if (empty($employeeOrganisations)) {
    http_response_code(403);
    exit('An active employee membership for performance management was not found. Contact HR.');
}

$selectedOrganisationId = (int)($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? $employeeOrganisations[0]['id']);
$selectedOrganisation = null;
foreach ($employeeOrganisations as $organisation) {
    if ((int)$organisation['id'] === $selectedOrganisationId) {
        $selectedOrganisation = $organisation;
        break;
    }
}
if (!$selectedOrganisation) {
    http_response_code(403);
    exit('You do not have access to this organisation.');
}
$employee = $selectedOrganisation['membership'];
$cycles = $planningModel->getActiveCyclesForEmployee($selectedOrganisationId, (int)$employee['employee_id']);
$selectedCycleId = (int)($_GET['cycle_id'] ?? $_POST['cycle_id'] ?? ($cycles[0]['id'] ?? 0));
$selectedCycle = null;
foreach ($cycles as $cycle) {
    if ((int)$cycle['id'] === $selectedCycleId) {
        $selectedCycle = $cycle;
        break;
    }
}
$success = isset($_GET['saved']) ? 'KPI plan draft saved as a new version.' : (isset($_GET['submitted']) ? 'KPI plan submitted to the configured approval workflow.' : '');
$error = '';

if (empty($_SESSION['performance_csrf_token'])) {
    $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedCycle) {
    if (!hash_equals($_SESSION['performance_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } else {
        $postedObjectives = $_POST['objectives'] ?? [];
        if (!is_array($postedObjectives)) {
            $postedObjectives = [];
        }
        $action = $_POST['action'] ?? 'save';
        $saved = $planningModel->savePlanDraft(
            $selectedOrganisationId,
            $selectedCycleId,
            (int)($_POST['template_version_id'] ?? 0),
            $userId,
            trim((string)($_POST['employee_comments'] ?? '')),
            $postedObjectives
        );
        if (empty($saved['success'])) {
            $error = $saved['message'] ?? 'The KPI plan could not be saved.';
        } elseif ($action === 'submit') {
            $submitted = $planningModel->submitPlan($selectedOrganisationId, (int)$saved['version_id'], $userId);
            if (empty($submitted['success'])) {
                $error = $submitted['message'] ?? 'The KPI plan could not be submitted.';
            } else {
                $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
                header('Location: performance-plan.php?organisation_id=' . $selectedOrganisationId . '&cycle_id=' . $selectedCycleId . '&submitted=1');
                exit;
            }
        } else {
            $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: performance-plan.php?organisation_id=' . $selectedOrganisationId . '&cycle_id=' . $selectedCycleId . '&saved=1');
            exit;
        }
    }
}

$cycleTemplates = $selectedCycle ? $planningModel->getCycleTemplateAssignments($selectedOrganisationId, $selectedCycleId, (int)$employee['employee_id']) : [];
$plan = $selectedCycle ? $planningModel->getEmployeePlan($selectedOrganisationId, $selectedCycleId, (int)$employee['employee_id']) : null;
$objectives = $plan && !empty($plan['current_version_id']) ? $planningModel->getPlanObjectives($selectedOrganisationId, (int)$plan['current_version_id']) : [];
$planActions = $plan && !empty($plan['current_version_id']) ? $planningModel->getPlanActionHistory($selectedOrganisationId, (int)$plan['current_version_id']) : [];
$reviewHistory = $plan && !empty($plan['current_version_id']) ? $planningModel->getPlanReviewHistory($selectedOrganisationId, (int)$plan['current_version_id']) : [];
$editableStatuses = ['draft', 'returned', 'rejected'];
$canEdit = !$plan || in_array($plan['version_status'] ?? '', $editableStatuses, true);
$displayObjectives = $objectives ?: [[
    'title' => '', 'description' => '', 'alignment' => '', 'objective_type' => '', 'weight' => '', 'due_date' => '',
    'kpis' => [[
        'title' => '', 'description' => '', 'measurement_type' => '', 'direction' => '', 'measurement_method' => '',
        'baseline_value' => '', 'target_value' => '', 'unit_of_measure' => '', 'measurement_frequency' => '',
        'weight' => '', 'deadline' => '', 'evidence_requirements_json' => '{}', 'measurement_config_json' => '{}'
    ]]
]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My KPI Plan - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .pm-panel { border: 1px solid #dfe7f1; border-radius: 12px; padding: 16px; margin: 16px 0; background: #fbfdff; }
        .pm-kpi { background: #fff; border-left: 4px solid #3498db; }
        .pm-help { color: #536273; font-size: .9rem; line-height: 1.5; }
        .pm-status { display: inline-block; padding: 5px 11px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-weight: 700; }
        .pm-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .pm-json { font-family: Consolas, monospace; }
        @media (max-width: 640px) { .pm-panel { padding: 12px; } }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar">
        <div class="brand"><h2>HR System</h2></div>
        <nav class="nav-menu"><ul>
            <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
            <li><a href="attendance.php"><i>📋</i> Attendance</a></li>
            <li class="active"><a href="performance-plan.php"><i>🎯</i> My KPI Plan</a></li>
            <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
            <li class="menu-header">ACCOUNT</li>
            <li><a href="logout.php"><i>🚪</i> Logout</a></li>
        </ul></nav>
    </aside>
    <main class="content">
        <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
        <div class="container">
            <div class="page-header"><h1>My KPI Plan</h1><p>Propose objectives and measurable indicators for an active appraisal cycle. Approved targets are version-locked.</p></div>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="GET" class="card">
                <div class="form-row">
                    <?php if (count($employeeOrganisations) > 1): ?><div class="form-group"><label for="organisation-select">Organisation</label><select id="organisation-select" name="organisation_id" onchange="this.form.submit()"><?php foreach ($employeeOrganisations as $organisation): ?><option value="<?php echo (int)$organisation['id']; ?>" <?php echo $selectedOrganisationId === (int)$organisation['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option><?php endforeach; ?></select></div><?php else: ?><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><?php endif; ?>
                    <div class="form-group"><label for="cycle-select">Active appraisal cycle</label><select id="cycle-select" name="cycle_id" onchange="this.form.submit()" <?php echo empty($cycles) ? 'disabled' : ''; ?>><?php foreach ($cycles as $cycle): ?><option value="<?php echo (int)$cycle['id']; ?>" <?php echo $selectedCycleId === (int)$cycle['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cycle['name'] . ' — ' . $cycle['period_label']); ?></option><?php endforeach; ?></select></div>
                </div>
                <noscript><button type="submit" class="btn btn-secondary">Load cycle</button></noscript>
            </form>

            <?php if (!$selectedCycle): ?>
                <div class="card"><h2>No active cycle available</h2><p>Your organisation has no active appraisal cycle with a published template assigned. Contact HR.</p></div>
            <?php else: ?>
                <div class="card">
                    <h2><?php echo htmlspecialchars($selectedCycle['name']); ?></h2>
                    <p><?php echo htmlspecialchars($selectedCycle['starts_on'] . ' to ' . $selectedCycle['ends_on']); ?> · <?php echo htmlspecialchars($selectedCycle['period_type']); ?></p>
                    <?php if ($plan): ?><p>Current plan status: <span class="pm-status"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $plan['version_status'] ?? $plan['status']))); ?></span> · Version <?php echo (int)$plan['version_number']; ?></p><?php endif; ?>
                    <?php if ($canEdit && empty($cycleTemplates)): ?><div class="alert alert-info">No published template is assigned to this cycle. Contact HR.</div><?php endif; ?>
                </div>

                <?php if ($canEdit && !empty($cycleTemplates)): ?>
                    <form method="POST" class="card" id="kpi-plan-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>">
                        <input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>">
                        <input type="hidden" name="cycle_id" value="<?php echo $selectedCycleId; ?>">
                        <h2><?php echo $plan ? 'Revise KPI Plan' : 'Create KPI Plan'; ?></h2>
                        <?php if ($plan && in_array($plan['version_status'], ['returned', 'rejected'], true)): ?><div class="alert alert-info">Your last submission was <?php echo htmlspecialchars($plan['version_status']); ?>. Review the comments below and submit a new version.</div><?php endif; ?>
                        <div class="form-group"><label for="template-version">Appraisal template</label><select id="template-version" name="template_version_id" required><?php foreach ($cycleTemplates as $template): ?><option value="<?php echo (int)$template['template_version_id']; ?>" <?php echo $plan && (int)$plan['template_version_id'] === (int)$template['template_version_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($template['template_name'] . ' v' . $template['version_number']); ?></option><?php endforeach; ?></select></div>
                        <div id="objective-list">
                            <?php foreach ($displayObjectives as $objectiveIndex => $objective): ?>
                                <section class="pm-panel objective-card">
                                    <h3>Objective <span class="objective-number"><?php echo $objectiveIndex + 1; ?></span></h3>
                                    <div class="form-group"><label>Objective title</label><input name="objectives[<?php echo $objectiveIndex; ?>][title]" maxlength="250" value="<?php echo htmlspecialchars($objective['title'] ?? ''); ?>" required></div>
                                    <div class="form-group"><label>Description</label><textarea name="objectives[<?php echo $objectiveIndex; ?>][description]" rows="2"><?php echo htmlspecialchars($objective['description'] ?? ''); ?></textarea></div>
                                    <div class="form-row">
                                        <div class="form-group"><label>Strategic / departmental alignment</label><input name="objectives[<?php echo $objectiveIndex; ?>][alignment]" value="<?php echo htmlspecialchars($objective['alignment'] ?? ''); ?>"></div>
                                        <div class="form-group"><label>Objective type (organisation-defined)</label><input name="objectives[<?php echo $objectiveIndex; ?>][objective_type]" value="<?php echo htmlspecialchars($objective['objective_type'] ?? ''); ?>"></div>
                                    </div>
                                    <div class="form-row"><div class="form-group"><label>Weight (if required by template)</label><input type="number" name="objectives[<?php echo $objectiveIndex; ?>][weight]" min="0" max="100" step="any" value="<?php echo htmlspecialchars((string)($objective['weight'] ?? '')); ?>"></div><div class="form-group"><label>Due date</label><input type="date" name="objectives[<?php echo $objectiveIndex; ?>][due_date]" value="<?php echo htmlspecialchars((string)($objective['due_date'] ?? '')); ?>"></div></div>
                                    <h4>Key Performance Indicators</h4>
                                    <div class="kpi-list" data-objective-index="<?php echo $objectiveIndex; ?>">
                                        <?php $kpis = !empty($objective['kpis']) ? $objective['kpis'] : [['title'=>'','description'=>'','measurement_type'=>'','direction'=>'','measurement_method'=>'','baseline_value'=>'','target_value'=>'','unit_of_measure'=>'','measurement_frequency'=>'','weight'=>'','deadline'=>'','evidence_requirements_json'=>'{}','measurement_config_json'=>'{}']]; ?>
                                        <?php foreach ($kpis as $kpiIndex => $kpi): ?>
                                            <div class="pm-panel pm-kpi kpi-card">
                                                <h5>KPI <span class="kpi-number"><?php echo $kpiIndex + 1; ?></span></h5>
                                                <div class="form-group"><label>KPI description / title</label><input name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][title]" maxlength="250" value="<?php echo htmlspecialchars($kpi['title'] ?? ''); ?>" required></div>
                                                <div class="form-group"><label>Expected result / detail</label><textarea name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][description]" rows="2"><?php echo htmlspecialchars($kpi['description'] ?? ''); ?></textarea></div>
                                                <div class="form-row"><div class="form-group"><label>Measurement type</label><input name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][measurement_type]" value="<?php echo htmlspecialchars($kpi['measurement_type'] ?? ''); ?>" placeholder="numeric, percentage, milestone, qualitative" required></div><div class="form-group"><label>Direction</label><input name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][direction]" value="<?php echo htmlspecialchars($kpi['direction'] ?? ''); ?>" placeholder="higher_is_better, lower_is_better, target" required></div></div>
                                                <div class="form-group"><label>Measurement method</label><input name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][measurement_method]" value="<?php echo htmlspecialchars($kpi['measurement_method'] ?? ''); ?>"></div>
                                                <div class="form-row"><div class="form-group"><label>Baseline value</label><input type="number" step="any" name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][baseline_value]" value="<?php echo htmlspecialchars((string)($kpi['baseline_value'] ?? '')); ?>"></div><div class="form-group"><label>Target value (numeric)</label><input type="number" step="any" name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][target_value]" value="<?php echo htmlspecialchars((string)($kpi['target_value'] ?? '')); ?>"></div></div>
                                                <div class="form-row"><div class="form-group"><label>Unit of measurement</label><input name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][unit_of_measure]" value="<?php echo htmlspecialchars($kpi['unit_of_measure'] ?? ''); ?>"></div><div class="form-group"><label>Measurement frequency</label><input name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][measurement_frequency]" value="<?php echo htmlspecialchars($kpi['measurement_frequency'] ?? ''); ?>"></div></div>
                                                <div class="form-row"><div class="form-group"><label>KPI weight (if required by template)</label><input type="number" min="0" max="100" step="any" name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][weight]" value="<?php echo htmlspecialchars((string)($kpi['weight'] ?? '')); ?>"></div><div class="form-group"><label>Deadline</label><input type="date" name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][deadline]" value="<?php echo htmlspecialchars((string)($kpi['deadline'] ?? '')); ?>"></div></div>
                                                <div class="form-row"><div class="form-group"><label>Evidence requirements JSON</label><textarea class="pm-json" name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][evidence_requirements_json]" rows="2"><?php echo htmlspecialchars($kpi['evidence_requirements_json'] ?? '{}'); ?></textarea></div><div class="form-group"><label>Measurement configuration JSON</label><textarea class="pm-json" name="objectives[<?php echo $objectiveIndex; ?>][kpis][<?php echo $kpiIndex; ?>][measurement_config_json]" rows="2"><?php echo htmlspecialchars($kpi['measurement_config_json'] ?? '{}'); ?></textarea></div></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary add-kpi">Add KPI</button>
                                </section>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-secondary" id="add-objective">Add objective</button>
                        <div class="form-group" style="margin-top:16px"><label>Employee comments / planning notes</label><textarea name="employee_comments" rows="3"><?php echo htmlspecialchars($plan['employee_comments'] ?? ''); ?></textarea></div>
                        <p class="pm-help">Scoring weights are validated against the selected template's configured rules. Numeric targets are optional for qualitative KPIs; describe qualitative expectations in the measurement configuration.</p>
                        <div class="pm-actions"><button type="submit" name="action" value="save" class="btn btn-secondary">Save Draft</button><button type="submit" name="action" value="submit" class="btn btn-primary" onclick="return confirm('Save this plan and submit it for approval?')">Save and Submit for Approval</button></div>
                    </form>
                <?php elseif ($plan): ?>
                    <section class="card"><h2>Approved / in-review plan</h2><p>This plan is locked to protect approved targets and review history.</p>
                        <?php foreach ($objectives as $objective): ?><div class="pm-panel"><h3><?php echo htmlspecialchars($objective['title']); ?> <small><?php echo htmlspecialchars((string)$objective['weight']); ?>%</small></h3><p><?php echo nl2br(htmlspecialchars($objective['description'] ?? '')); ?></p><?php foreach ($objective['kpis'] as $kpi): ?><div class="pm-panel pm-kpi"><strong><?php echo htmlspecialchars($kpi['title']); ?></strong><p><?php echo htmlspecialchars($kpi['description'] ?? ''); ?></p><p>Type: <?php echo htmlspecialchars($kpi['measurement_type']); ?> · Direction: <?php echo htmlspecialchars($kpi['direction']); ?> · Baseline: <?php echo htmlspecialchars((string)($kpi['baseline_value'] ?? '—')); ?> · Target: <?php echo htmlspecialchars((string)($kpi['target_value'] ?? '—')); ?> · Weight: <?php echo htmlspecialchars((string)($kpi['weight'] ?? '—')); ?></p></div><?php endforeach; ?></div><?php endforeach; ?></section>
                <?php endif; ?>

                <?php if (!empty($reviewHistory) || !empty($planActions)): ?>
                    <section class="card"><h2>Plan history and reviewer comments</h2>
                        <?php foreach ($reviewHistory as $review): ?><div class="pm-panel"><strong><?php echo htmlspecialchars($review['stage_name']); ?></strong> · <?php echo htmlspecialchars(ucfirst($review['status'])); ?> · <?php echo htmlspecialchars($review['reviewer_name'] ?? 'Reviewer'); ?><br><small><?php echo htmlspecialchars($review['action_at'] ?? ''); ?></small><?php if (!empty($review['comments'])): ?><p><?php echo nl2br(htmlspecialchars($review['comments'])); ?></p><?php endif; ?></div><?php endforeach; ?>
                        <?php foreach ($planActions as $entry): ?><div class="pm-panel"><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $entry['action']))); ?></strong> · <?php echo htmlspecialchars($entry['created_at']); ?><?php if (!empty($entry['comments'])): ?><p><?php echo nl2br(htmlspecialchars($entry['comments'])); ?></p><?php endif; ?></div><?php endforeach; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
(function() {
    const objectiveList = document.getElementById('objective-list');
    const addObjective = document.getElementById('add-objective');
    if (!objectiveList || !addObjective) return;
    function resetValues(node) {
        node.querySelectorAll('input, textarea').forEach(function(field) { field.value = field.classList.contains('pm-json') ? '{}' : ''; });
        node.querySelectorAll('.objective-number, .kpi-number').forEach(function(label, index) { label.textContent = String(index + 1); });
    }
    objectiveList.querySelectorAll('.objective-card').forEach(function(card, objectiveIndex) {
        const addKpi = card.querySelector('.add-kpi');
        const kpiList = card.querySelector('.kpi-list');
        if (!addKpi || !kpiList) return;
        let kpiCounter = kpiList.querySelectorAll('.kpi-card').length;
        addKpi.addEventListener('click', function() {
            const clone = kpiList.querySelector('.kpi-card').cloneNode(true);
            clone.querySelectorAll('[name]').forEach(function(field) {
                field.name = field.name.replace(/\[kpis\]\[\d+\]/, '[kpis][' + kpiCounter + ']');
            });
            resetValues(clone);
            kpiList.appendChild(clone);
            kpiCounter++;
        });
    });
    let objectiveCounter = objectiveList.querySelectorAll('.objective-card').length;
    addObjective.addEventListener('click', function() {
        const clone = objectiveList.querySelector('.objective-card').cloneNode(true);
        clone.querySelectorAll('[name]').forEach(function(field) {
            field.name = field.name.replace(/objectives\[\d+\]/, 'objectives[' + objectiveCounter + ']');
            field.name = field.name.replace(/\[kpis\]\[\d+\]/, '[kpis][0]');
        });
        const kpiList = clone.querySelector('.kpi-list');
        kpiList.dataset.objectiveIndex = objectiveCounter;
        const addKpi = clone.querySelector('.add-kpi');
        addKpi.replaceWith(addKpi.cloneNode(true));
        const newAddKpi = clone.querySelector('.add-kpi');
        let kpiCounter = kpiList.querySelectorAll('.kpi-card').length;
        newAddKpi.addEventListener('click', function() {
            const kpiClone = kpiList.querySelector('.kpi-card').cloneNode(true);
            kpiClone.querySelectorAll('[name]').forEach(function(field) { field.name = field.name.replace(/\[kpis\]\[\d+\]/, '[kpis][' + kpiCounter + ']'); });
            resetValues(kpiClone);
            kpiList.appendChild(kpiClone);
            kpiCounter++;
        });
        resetValues(clone);
        objectiveList.appendChild(clone);
        objectiveCounter++;
    });
})();
</script>
</body>
</html>
