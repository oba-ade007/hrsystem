<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/PerformancePlanning.php';
require_once '../src/models/PerformanceAppraisal.php';

$userId = (int)$_SESSION['user_id'];
$planningModel = new PerformancePlanning($db);
$appraisalModel = new PerformanceAppraisal($db);
$organisations = $planningModel->getOrganisationsForUser($userId, $_SESSION['user_role']);
$selectedOrganisationId = (int)($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? ($organisations[0]['id'] ?? 0));
$hasAccess = false;
foreach ($organisations as $organisation) {
    if ((int)$organisation['id'] === $selectedOrganisationId) { $hasAccess = true; break; }
}
if (!$hasAccess) { http_response_code(403); exit('You do not have access to this appraisal organisation.'); }
$success = isset($_GET['saved']) ? 'Assessment submitted and saved in the appraisal history.' : '';
$error = '';

if (empty($_SESSION['performance_csrf_token'])) { $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['performance_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } else {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'submit_assessment') {
            $result = $appraisalModel->submitAssessment(
                $selectedOrganisationId,
                $taskId,
                $userId,
                $_POST,
                is_array($_POST['items'] ?? null) ? $_POST['items'] : [],
                is_array($_POST['development_goals'] ?? null) ? $_POST['development_goals'] : [],
                is_array($_FILES['evidence'] ?? null) ? $_FILES['evidence'] : []
            );
        } elseif (in_array($action, ['returned', 'rejected'], true)) {
            $result = $appraisalModel->actionTask($selectedOrganisationId, $taskId, $userId, $action, trim((string)($_POST['reason'] ?? '')));
        } else {
            $result = ['success' => false, 'message' => 'Unsupported appraisal action.'];
        }
        if (!empty($result['success'])) {
            $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: performance-assessment.php?organisation_id=' . $selectedOrganisationId . '&saved=1');
            exit;
        }
        $error = $result['message'] ?? 'The appraisal action could not be completed.';
    }
}

$tasks = $appraisalModel->getPendingTasksForUser($selectedOrganisationId, $userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Assessments - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .assessment-card { border: 1px solid #dfe7f1; border-radius: 12px; padding: 18px; margin: 18px 0; background: #fff; }
        .assessment-meta { display:flex; flex-wrap:wrap; gap:10px 20px; color:#536273; margin:10px 0 16px; }
        .assessment-objective { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin:12px 0; }
        .assessment-kpi { background:#fff; border-left:4px solid #3498db; border-radius:4px; padding:14px; margin:10px 0; }
        .assessment-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
        .pm-help { color:#536273; font-size:.9rem; line-height:1.5; }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar">
        <div class="brand"><h2>HR System</h2></div>
        <nav class="nav-menu"><ul>
            <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
            <li><a href="performance-plan.php"><i>🎯</i> My KPI Plan</a></li>
            <li class="active"><a href="performance-assessment.php"><i>📝</i> Assessments</a></li>
            <li><a href="performance-plan-approvals.php"><i>✓</i> KPI Plan Approvals</a></li>
            <?php if (in_array($_SESSION['user_role'], ['admin', 'hr'], true)): ?><li><a href="performance-appraisals.php"><i>📑</i> Appraisal Administration</a></li><?php endif; ?>
            <li><a href="performance-results.php"><i>🏅</i> My Results</a></li>
            <li class="menu-header">ACCOUNT</li><li><a href="logout.php"><i>🚪</i> Logout</a></li>
        </ul></nav>
    </aside>
    <main class="content">
        <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
        <div class="container">
            <div class="page-header"><h1>Performance Assessments</h1><p>Complete your self-appraisal or an assigned review. Each submission is stored separately so reviewer scores never overwrite the employee’s assessment.</p></div>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if (count($organisations) > 1): ?><form method="GET" class="card"><label>Organisation</label><select name="organisation_id" onchange="this.form.submit()"><?php foreach ($organisations as $organisation): ?><option value="<?php echo (int)$organisation['id']; ?>" <?php echo (int)$organisation['id'] === $selectedOrganisationId ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option><?php endforeach; ?></select><noscript><button class="btn btn-secondary" type="submit">Load</button></noscript></form><?php endif; ?>
            <section class="card"><h2>Assigned tasks (<?php echo count($tasks); ?>)</h2>
                <?php if (empty($tasks)): ?><p>No performance assessments are currently assigned to you.</p><?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <?php $detail = $appraisalModel->getTaskDetail($selectedOrganisationId, (int)$task['id'], $userId); ?>
                        <?php if (!$detail) { continue; } ?>
                        <?php $objectives = $appraisalModel->getAssessmentObjectives($selectedOrganisationId, (int)$task['approved_plan_version_id']); ?>
                        <?php $ratingValues = $appraisalModel->getRatingValuesForVersion($selectedOrganisationId, (int)$task['template_version_id']); ?>
                        <?php $history = $appraisalModel->getAssessmentHistory($selectedOrganisationId, (int)$task['appraisal_record_id']); ?>
                        <article class="assessment-card">
                            <h3><?php echo htmlspecialchars($task['employee_name']); ?> <small><?php echo htmlspecialchars($task['staff_number']); ?></small></h3>
                            <div class="assessment-meta"><span>Cycle: <?php echo htmlspecialchars($task['cycle_name']); ?></span><span>Stage: <?php echo htmlspecialchars($task['stage_name']); ?></span><span>Level: <?php echo (int)$task['stage_order']; ?></span><span>Assessment type: <?php $stageSettings=json_decode($task['assignment_config_json'] ?: '{}',true); echo htmlspecialchars($stageSettings['assessment_type'] ?? ($task['assignment_type'] === 'employee' ? 'self' : 'reviewer')); ?></span></div>
                            <?php foreach ($history as $pastAssessment): ?>
                                <div class="assessment-objective"><strong><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$pastAssessment['assessment_type']))); ?> — <?php echo htmlspecialchars($pastAssessment['assessor_name']); ?></strong> · Score: <?php echo htmlspecialchars((string)($pastAssessment['overall_score'] ?? 'Not calculated')); ?><p><?php echo nl2br(htmlspecialchars($pastAssessment['comments'] ?? '')); ?></p>
                                    <?php foreach ($appraisalModel->getAssessmentItems($selectedOrganisationId,(int)$pastAssessment['id']) as $pastItem): ?><small><?php echo htmlspecialchars($pastItem['kpi_title'] ?? 'KPI'); ?>: score <?php echo htmlspecialchars((string)($pastItem['score_value'] ?? '—')); ?>, actual <?php echo htmlspecialchars((string)($pastItem['actual_value'] ?? '—')); ?> — <?php echo htmlspecialchars($pastItem['comments'] ?? ''); ?></small><br><?php endforeach; ?>
                                    <?php foreach ($appraisalModel->getEvidenceForAssessment($selectedOrganisationId,(int)$pastAssessment['id']) as $evidence): ?><a href="performance-evidence.php?id=<?php echo (int)$evidence['id']; ?>"><?php echo htmlspecialchars($evidence['original_name']); ?></a> · <?php echo htmlspecialchars($evidence['kpi_title'] ?? 'Evidence'); ?><br><?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                <?php foreach ($objectives as $objective): ?>
                                    <section class="assessment-objective"><h4><?php echo htmlspecialchars($objective['title']); ?><?php if ($objective['weight'] !== null): ?> · <?php echo htmlspecialchars((string)$objective['weight']); ?>%<?php endif; ?></h4><p><?php echo nl2br(htmlspecialchars($objective['description'] ?? '')); ?></p>
                                        <?php foreach ($objective['kpis'] as $kpi): ?>
                                            <?php $evidenceRequirements=json_decode($kpi['evidence_requirements_json'] ?: '{}',true); $evidenceRequired=is_array($evidenceRequirements) && !empty($evidenceRequirements['required']); ?>
                                            <div class="assessment-kpi">
                                                <strong><?php echo htmlspecialchars($kpi['title']); ?></strong><p><?php echo nl2br(htmlspecialchars($kpi['description'] ?? '')); ?></p>
                                                <p class="pm-help">Target: <?php echo htmlspecialchars((string)($kpi['target_value'] ?? '—')); ?> <?php echo htmlspecialchars($kpi['unit_of_measure'] ?? ''); ?> · Baseline: <?php echo htmlspecialchars((string)($kpi['baseline_value'] ?? '—')); ?> · Direction: <?php echo htmlspecialchars($kpi['direction']); ?> · Due: <?php echo htmlspecialchars($kpi['deadline'] ?? '—'); ?></p>
                                                <div class="form-row"><div class="form-group"><label>Actual result (numeric, if applicable)</label><input type="number" step="any" name="items[<?php echo (int)$kpi['id']; ?>][actual_value]"></div><div class="form-group"><label>Configured score</label><?php if ($ratingValues): ?><select name="items[<?php echo (int)$kpi['id']; ?>][score_value]" required><option value="">Select rating</option><?php foreach ($ratingValues as $rating): ?><option value="<?php echo htmlspecialchars((string)$rating['score_value']); ?>"><?php echo htmlspecialchars($rating['label'] . ' (' . $rating['score_value'] . ')'); ?></option><?php endforeach; ?></select><?php else: ?><input type="number" step="any" name="items[<?php echo (int)$kpi['id']; ?>][score_value]" required><?php endif; ?></div></div>
                                                <div class="form-group"><label>Result comments / achievement evidence</label><textarea name="items[<?php echo (int)$kpi['id']; ?>][comments]" rows="2"></textarea></div>
                                                <div class="form-group"><label>Supporting evidence <?php echo $evidenceRequired ? '(required)' : '(optional)'; ?></label><input type="file" name="evidence[<?php echo (int)$kpi['id']; ?>]" accept=".pdf,.png,.jpg,.jpeg,.txt" <?php echo $evidenceRequired ? 'required' : ''; ?>><small>PDF, PNG, JPEG, or plain text; maximum 10 MB.</small></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </section>
                                <?php endforeach; ?>
                                <div class="form-row"><div class="form-group"><label>Overall score (optional manual score)</label><input type="number" step="any" name="overall_score"><small>The template may calculate a weighted or arithmetic mean if configured; otherwise enter an explicit overall score or leave it blank.</small></div><div class="form-group"><label>Overall comments</label><textarea name="comments" rows="3" required></textarea></div></div>
                                <div class="form-row"><div class="form-group"><label>Strengths</label><textarea name="strengths" rows="2"></textarea></div><div class="form-group"><label>Challenges</label><textarea name="challenges" rows="2"></textarea></div></div>
                                <div class="form-row"><div class="form-group"><label>Recommendations</label><textarea name="recommendations" rows="2"></textarea></div><div class="form-group"><label>Training / development needs</label><textarea name="training_needs" rows="2"></textarea></div></div>
                                <h4>Development goal (optional)</h4>
                                <div class="form-row"><div class="form-group"><label>Goal</label><input name="development_goals[0][goal_title]"></div><div class="form-group"><label>Target date</label><input type="date" name="development_goals[0][target_date]"></div></div>
                                <div class="form-group"><label>Development support required</label><textarea name="development_goals[0][support_required]" rows="2"></textarea></div>
                                <div class="assessment-actions"><button type="submit" name="action" value="submit_assessment" class="btn btn-primary" onclick="return confirm('Submit this assessment? Submitted scores remain recorded as your assessment version.')">Submit assessment</button>
                                    <?php if ((int)$detail['allow_return']): ?><button type="submit" name="action" value="returned" class="btn btn-secondary" formnovalidate>Return appraisal for correction</button><?php endif; ?>
                                    <?php if ((int)$detail['allow_reject']): ?><button type="submit" name="action" value="rejected" class="btn btn-danger" formnovalidate>Reject appraisal</button><?php endif; ?>
                                </div>
                                <div class="form-group"><label>Reason for return or rejection</label><textarea name="reason" rows="2"></textarea></div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="js/script.js"></script>
</body>
</html>
