<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/PerformancePlanning.php';

$planningModel = new PerformancePlanning($db);
$userId = (int)$_SESSION['user_id'];
$organisations = $planningModel->getOrganisationsForUser($userId, $_SESSION['user_role']);
$selectedOrganisationId = (int)($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? ($organisations[0]['id'] ?? 0));
$hasAccess = false;
foreach ($organisations as $organisation) {
    if ((int)$organisation['id'] === $selectedOrganisationId) {
        $hasAccess = true;
        break;
    }
}
if (!$hasAccess) {
    http_response_code(403);
    exit('You do not have access to this appraisal organisation.');
}
$success = isset($_GET['saved']) ? 'KPI plan review action recorded.' : '';
$error = '';

if (empty($_SESSION['performance_csrf_token'])) {
    $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['performance_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } else {
        $result = $planningModel->actionPlanApproval(
            $selectedOrganisationId,
            (int)($_POST['task_id'] ?? 0),
            $userId,
            $_POST['decision'] ?? '',
            trim((string)($_POST['comments'] ?? ''))
        );
        if (!empty($result['success'])) {
            $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: performance-plan-approvals.php?organisation_id=' . $selectedOrganisationId . '&saved=1');
            exit;
        }
        $error = $result['message'] ?? 'The review could not be recorded.';
    }
}

$tasks = $planningModel->getPendingPlanTasksForUser($selectedOrganisationId, $userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Plan Approvals - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .plan-review { border: 1px solid #dfe7f1; border-radius: 12px; padding: 18px; margin: 18px 0; background: #fff; }
        .review-meta { display: flex; flex-wrap: wrap; gap: 12px 22px; color: #536273; margin: 8px 0 14px; }
        .review-objective { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin: 12px 0; }
        .review-kpi { padding: 10px 12px; border-left: 3px solid #3498db; margin: 8px 0; background: #fff; }
        .decision-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar">
        <div class="brand"><h2>HR System</h2></div>
        <nav class="nav-menu"><ul>
            <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
            <li><a href="performance-plan.php"><i>🎯</i> My KPI Plan</a></li>
            <li class="active"><a href="performance-plan-approvals.php"><i>✓</i> KPI Plan Approvals</a></li>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <li><a href="performance-setup.php"><i>⚙️</i> Performance Setup</a></li>
            <li><a href="performance-templates.php"><i>🧩</i> Templates & Workflows</a></li>
            <li><a href="performance-members.php"><i>👥</i> Members & Reporting</a></li>
            <?php endif; ?>
            <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
            <li class="menu-header">ACCOUNT</li>
            <li><a href="logout.php"><i>🚪</i> Logout</a></li>
        </ul></nav>
    </aside>
    <main class="content">
        <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
        <div class="container">
            <div class="page-header"><h1>KPI Plan Approvals</h1><p>Only tasks assigned directly to you or your organisation access profile are shown. Later workflow stages remain hidden until earlier stages finish.</p></div>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if (count($organisations) > 1): ?>
                <form method="GET" class="card"><label for="organisation-filter">Organisation</label><select id="organisation-filter" name="organisation_id" onchange="this.form.submit()"><?php foreach ($organisations as $organisation): ?><option value="<?php echo (int)$organisation['id']; ?>" <?php echo (int)$organisation['id'] === $selectedOrganisationId ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option><?php endforeach; ?></select><noscript><button type="submit" class="btn btn-secondary">Load</button></noscript></form>
            <?php endif; ?>
            <section class="card"><h2>Pending tasks (<?php echo count($tasks); ?>)</h2>
                <?php if (empty($tasks)): ?><p>No KPI plan approvals are currently assigned to you.</p><?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <article class="plan-review">
                            <h3><?php echo htmlspecialchars($task['employee_name']); ?> <small>(<?php echo htmlspecialchars($task['staff_number']); ?>)</small></h3>
                            <div class="review-meta"><span>Cycle: <?php echo htmlspecialchars($task['cycle_name']); ?></span><span>Template: Version <?php echo (int)$task['version_number']; ?></span><span>Stage: <?php echo htmlspecialchars($task['stage_name']); ?></span><span>Review level: <?php echo (int)$task['stage_order']; ?></span></div>
                            <?php $objectives = $planningModel->getPlanObjectives($selectedOrganisationId, (int)$task['plan_version_id']); ?>
                            <?php foreach ($objectives as $objective): ?>
                                <div class="review-objective">
                                    <h4><?php echo htmlspecialchars($objective['title']); ?><?php if ($objective['weight'] !== null): ?> · <?php echo htmlspecialchars((string)$objective['weight']); ?>%<?php endif; ?></h4>
                                    <p><?php echo nl2br(htmlspecialchars($objective['description'] ?? '')); ?></p>
                                    <?php foreach ($objective['kpis'] as $kpi): ?>
                                        <div class="review-kpi"><strong><?php echo htmlspecialchars($kpi['title']); ?></strong><p><?php echo nl2br(htmlspecialchars($kpi['description'] ?? '')); ?></p><small>Type: <?php echo htmlspecialchars($kpi['measurement_type']); ?> · Direction: <?php echo htmlspecialchars($kpi['direction']); ?> · Baseline: <?php echo htmlspecialchars((string)($kpi['baseline_value'] ?? '—')); ?> · Target: <?php echo htmlspecialchars((string)($kpi['target_value'] ?? '—')); ?> · Weight: <?php echo htmlspecialchars((string)($kpi['weight'] ?? '—')); ?> · Due: <?php echo htmlspecialchars($kpi['deadline'] ?? '—'); ?></small></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>">
                                <input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>">
                                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                <div class="form-group"><label>Review comments (required for return or rejection)</label><textarea name="comments" rows="3" placeholder="Explain requested changes or rejection reason"></textarea></div>
                                <div class="decision-actions">
                                    <button type="submit" name="decision" value="approved" class="btn btn-primary">Approve</button>
                                    <?php if ((int)$task['allow_return']): ?><button type="submit" name="decision" value="returned" class="btn btn-secondary">Return for revision</button><?php endif; ?>
                                    <?php if ((int)$task['allow_reject']): ?><button type="submit" name="decision" value="rejected" class="btn btn-danger">Reject</button><?php endif; ?>
                                </div>
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
