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
$employeeOrganisations = [];
foreach ($organisations as $organisation) {
    $membership = $planningModel->getEmployeeMembership((int)$organisation['id'], $userId);
    if ($membership) { $organisation['membership'] = $membership; $employeeOrganisations[] = $organisation; }
}
if (empty($employeeOrganisations)) { http_response_code(403); exit('An active employee membership for appraisal results was not found.'); }
$selectedOrganisationId = (int)($_GET['organisation_id'] ?? $employeeOrganisations[0]['id']);
$selectedOrganisation = null;
foreach ($employeeOrganisations as $organisation) { if ((int)$organisation['id'] === $selectedOrganisationId) { $selectedOrganisation = $organisation; break; } }
if (!$selectedOrganisation) { http_response_code(403); exit('You do not have access to this organisation.'); }
$employee = $selectedOrganisation['membership'];
$releasedAppraisals = $appraisalModel->getReleasedAppraisalsForEmployee($selectedOrganisationId, (int)$employee['employee_id']);
$selectedAppraisalId = (int)($_GET['appraisal_id'] ?? ($releasedAppraisals[0]['id'] ?? 0));
$selectedAppraisal = null;
foreach ($releasedAppraisals as $released) { if ((int)$released['id'] === $selectedAppraisalId) { $selectedAppraisal = $released; break; } }
$objectives = $selectedAppraisal ? $appraisalModel->getAssessmentObjectives($selectedOrganisationId, (int)$selectedAppraisal['approved_plan_version_id']) : [];
$assessments = $selectedAppraisal ? $appraisalModel->getAssessmentHistory($selectedOrganisationId, $selectedAppraisalId) : [];
$goals = $selectedAppraisal ? $appraisalModel->getDevelopmentGoals($selectedOrganisationId, $selectedAppraisalId) : [];
$adjustments = $selectedAppraisal ? $appraisalModel->getScoreAdjustments($selectedOrganisationId, $selectedAppraisalId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance Results - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .result-score { display:flex; flex-wrap:wrap; gap:16px; align-items:center; padding:18px; background:#f4f8fc; border-radius:12px; }
        .result-score strong { font-size:1.5rem; color:#19324d; }
        .result-entry { border-left:4px solid #3498db; background:#f8fafc; padding:14px; margin:12px 0; border-radius:6px; }
        .result-item { padding:10px 12px; background:#fff; border:1px solid #e2e8f0; margin:8px 0; border-radius:6px; }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar"><div class="brand"><h2>HR System</h2></div><nav class="nav-menu"><ul>
        <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
        <li><a href="performance-plan.php"><i>🎯</i> My KPI Plan</a></li>
        <li><a href="performance-assessment.php"><i>📝</i> Assessments</a></li>
        <li class="active"><a href="performance-results.php"><i>🏅</i> My Results</a></li>
        <li class="menu-header">ACCOUNT</li><li><a href="logout.php"><i>🚪</i> Logout</a></li>
    </ul></nav></aside>
    <main class="content">
        <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
        <div class="container">
            <div class="page-header"><h1>My Performance Results</h1><p>Only finalised and released results are shown here.</p></div>
            <?php if (count($employeeOrganisations) > 1): ?><form method="GET" class="card"><label>Organisation</label><select name="organisation_id" onchange="this.form.submit()"><?php foreach ($employeeOrganisations as $organisation): ?><option value="<?php echo (int)$organisation['id']; ?>" <?php echo (int)$organisation['id'] === $selectedOrganisationId ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option><?php endforeach; ?></select></form><?php endif; ?>
            <?php if (empty($releasedAppraisals)): ?><section class="card"><p>No released appraisal results are available yet.</p></section><?php else: ?>
                <form method="GET" class="card"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><label>Appraisal cycle</label><select name="appraisal_id" onchange="this.form.submit()"><?php foreach ($releasedAppraisals as $released): ?><option value="<?php echo (int)$released['id']; ?>" <?php echo $selectedAppraisalId === (int)$released['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($released['cycle_name']); ?></option><?php endforeach; ?></select><noscript><button type="submit" class="btn btn-secondary">View</button></noscript></form>
                <?php if ($selectedAppraisal): ?>
                    <section class="card"><h2><?php echo htmlspecialchars($selectedAppraisal['cycle_name']); ?></h2><div class="result-score"><div><span>Final score</span><br><strong><?php echo htmlspecialchars((string)$selectedAppraisal['final_score']); ?></strong></div><div><span>Final rating</span><br><strong><?php echo htmlspecialchars($selectedAppraisal['final_rating_label'] ?? ''); ?></strong></div><div><span>Released</span><br><?php echo htmlspecialchars($selectedAppraisal['released_at']); ?></div></div><p>These approved targets are the version retained for this appraisal period.</p>
                        <?php foreach ($objectives as $objective): ?><article class="result-entry"><h3><?php echo htmlspecialchars($objective['title']); ?><?php if ($objective['weight'] !== null): ?> · <?php echo htmlspecialchars((string)$objective['weight']); ?>%<?php endif; ?></h3><p><?php echo nl2br(htmlspecialchars($objective['description'] ?? '')); ?></p><?php foreach ($objective['kpis'] as $kpi): ?><div class="result-item"><strong><?php echo htmlspecialchars($kpi['title']); ?></strong><p><?php echo nl2br(htmlspecialchars($kpi['description'] ?? '')); ?></p><small>Target: <?php echo htmlspecialchars((string)($kpi['target_value'] ?? '—')); ?> <?php echo htmlspecialchars($kpi['unit_of_measure'] ?? ''); ?> · Baseline: <?php echo htmlspecialchars((string)($kpi['baseline_value'] ?? '—')); ?> · Direction: <?php echo htmlspecialchars($kpi['direction']); ?></small></div><?php endforeach; ?></article><?php endforeach; ?>
                    </section>
                    <section class="card"><h2>Assessment history</h2><?php foreach ($assessments as $assessment): ?><article class="result-entry"><h3><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$assessment['assessment_type']))); ?> — <?php echo htmlspecialchars($assessment['assessor_name']); ?></h3><p>Score: <?php echo htmlspecialchars((string)($assessment['overall_score'] ?? 'Not calculated')); ?> · Submitted <?php echo htmlspecialchars($assessment['submitted_at'] ?? ''); ?></p><p><?php echo nl2br(htmlspecialchars($assessment['comments'] ?? '')); ?></p><?php foreach ($appraisalModel->getAssessmentItems($selectedOrganisationId,(int)$assessment['id']) as $item): ?><div class="result-item"><strong><?php echo htmlspecialchars($item['kpi_title'] ?? 'KPI'); ?></strong> · Score <?php echo htmlspecialchars((string)($item['score_value'] ?? '—')); ?> · Actual <?php echo htmlspecialchars((string)($item['actual_value'] ?? '—')); ?><p><?php echo nl2br(htmlspecialchars($item['comments'] ?? '')); ?></p></div><?php endforeach; ?><?php foreach ($appraisalModel->getEvidenceForAssessment($selectedOrganisationId,(int)$assessment['id']) as $evidence): ?><a href="performance-evidence.php?id=<?php echo (int)$evidence['id']; ?>"><?php echo htmlspecialchars($evidence['original_name']); ?></a><br><?php endforeach; ?><p><strong>Strengths:</strong> <?php echo nl2br(htmlspecialchars($assessment['strengths'] ?? '')); ?></p><p><strong>Challenges:</strong> <?php echo nl2br(htmlspecialchars($assessment['challenges'] ?? '')); ?></p><p><strong>Recommendations:</strong> <?php echo nl2br(htmlspecialchars($assessment['recommendations'] ?? '')); ?></p><p><strong>Training needs:</strong> <?php echo nl2br(htmlspecialchars($assessment['training_needs'] ?? '')); ?></p></article><?php endforeach; ?></section>
                    <?php if ($goals): ?><section class="card"><h2>Development plan</h2><?php foreach ($goals as $goal): ?><article class="result-entry"><h3><?php echo htmlspecialchars($goal['goal_title']); ?></h3><p><?php echo nl2br(htmlspecialchars($goal['description'] ?? '')); ?></p><p>Support: <?php echo nl2br(htmlspecialchars($goal['support_required'] ?? '')); ?> · Target: <?php echo htmlspecialchars($goal['target_date'] ?? '—'); ?></p></article><?php endforeach; ?></section><?php endif; ?>
                    <?php if ($adjustments): ?><section class="card"><h2>HR score adjustments</h2><?php foreach ($adjustments as $adjustment): ?><article class="result-entry"><p>Original reviewer score <?php echo htmlspecialchars((string)$adjustment['original_score']); ?> → final score <?php echo htmlspecialchars((string)$adjustment['adjusted_score']); ?></p><p>Reason: <?php echo nl2br(htmlspecialchars($adjustment['reason'])); ?></p><small>Adjusted by <?php echo htmlspecialchars($adjustment['adjusted_by_name'] ?? 'HR'); ?> · <?php echo htmlspecialchars($adjustment['created_at']); ?></small></article><?php endforeach; ?></section><?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="js/script.js"></script>
</body>
</html>
