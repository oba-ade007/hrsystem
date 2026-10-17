<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/PerformanceManagement.php';
require_once '../src/models/PerformancePlanning.php';
require_once '../src/models/PerformanceAppraisal.php';

$userId = (int)$_SESSION['user_id'];
$configModel = new PerformanceManagement($db);
$planningModel = new PerformancePlanning($db);
$appraisalModel = new PerformanceAppraisal($db);
$organisations = $planningModel->getOrganisationsForUser($userId, $_SESSION['user_role']);
if (empty($organisations)) {
    http_response_code(200);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Performance Setup Required - HR Software</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
        <div class="wrapper">
            <aside class="sidebar">
                <div class="brand"><h2>HR System</h2></div>
                <nav class="nav-menu"><ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="performance-setup.php"><i>⚙️</i> Performance Setup</a></li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul></nav>
            </aside>
            <main class="content">
                <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
                <div class="container">
                    <div class="page-header"><h1>Performance setup required</h1><p>No active appraisal organisation is assigned to this account.</p></div>
                    <section class="card">
                        <h2>Start with organisation setup</h2>
                        <p>Create the first appraisal organisation in Performance Setup. The HR user who creates it is automatically assigned as its organisation administrator and can then configure cycles, templates, reporting lines, and reviewer assignments.</p>
                        <a href="performance-setup.php" class="btn btn-primary">Open Performance Setup</a>
                    </section>
                </div>
            </main>
        </div>
    </body>
    </html>
    <?php
    exit;
}
$selectedOrganisationId = (int)($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? ($organisations[0]['id'] ?? 0));
$hasAccess = false;
foreach ($organisations as $organisation) {
    if ((int)$organisation['id'] === $selectedOrganisationId) { $hasAccess = true; break; }
}
if (!$hasAccess) { http_response_code(403); exit('You do not have access to this appraisal organisation.'); }
$cycles = $configModel->getCycles($selectedOrganisationId);
$selectedCycleId = (int)($_GET['cycle_id'] ?? $_POST['cycle_id'] ?? 0);
$success = isset($_GET['saved']) ? 'The appraisal administration action was recorded.' : '';
$error = '';

if (empty($_SESSION['performance_csrf_token'])) { $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['performance_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'start_appraisal') {
            $result = $appraisalModel->startAppraisal($selectedOrganisationId, (int)($_POST['cycle_id'] ?? 0), (int)($_POST['employee_id'] ?? 0), $userId);
        } elseif ($action === 'finalise') {
            $result = $appraisalModel->finaliseAppraisal($selectedOrganisationId, (int)($_POST['appraisal_id'] ?? 0), $userId, $_POST['final_score'] ?? '', (int)($_POST['rating_value_id'] ?? 0), trim((string)($_POST['reason'] ?? '')));
        } elseif ($action === 'release') {
            $result = $appraisalModel->releaseAppraisal($selectedOrganisationId, (int)($_POST['appraisal_id'] ?? 0), $userId);
        } else {
            $result = ['success' => false, 'message' => 'Unsupported appraisal administration action.'];
        }
        if (!empty($result['success'])) {
            $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
            $selectedCycleId = (int)($_POST['cycle_id'] ?? $selectedCycleId);
            header('Location: performance-appraisals.php?organisation_id=' . $selectedOrganisationId . '&cycle_id=' . $selectedCycleId . '&saved=1');
            exit;
        }
        $error = $result['message'] ?? 'The appraisal action could not be completed.';
    }
}

$eligibleEmployees = $selectedCycleId ? $appraisalModel->getAppraisableEmployees($selectedOrganisationId, $selectedCycleId) : [];
$appraisals = $appraisalModel->getAppraisalsForHr($selectedOrganisationId, $selectedCycleId ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Appraisals - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .pm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; align-items: start; }
        .pm-entry { border: 1px solid #dfe7f1; border-radius: 10px; padding: 14px; margin: 12px 0; background: #fbfdff; }
        .pm-history { border-left: 3px solid #3498db; padding: 10px 14px; margin: 10px 0; background: #f8fafc; }
        .pm-badge { display:inline-block; padding:4px 10px; border-radius:999px; background:#eef2ff; color:#3730a3; font-weight:700; }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar"><div class="brand"><h2>HR System</h2></div><nav class="nav-menu"><ul>
        <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
        <li><a href="performance-setup.php"><i>⚙️</i> Performance Setup</a></li>
        <li><a href="performance-templates.php"><i>🧩</i> Templates & Workflows</a></li>
        <li><a href="performance-members.php"><i>👥</i> Members & Reporting</a></li>
        <li class="active"><a href="performance-appraisals.php"><i>📑</i> Appraisals & Finalisation</a></li>
        <li><a href="performance-plan-approvals.php"><i>✓</i> KPI Plan Approvals</a></li>
        <li class="menu-header">ACCOUNT</li><li><a href="logout.php"><i>🚪</i> Logout</a></li>
    </ul></nav></aside>
    <main class="content">
        <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
        <div class="container">
            <div class="page-header"><h1>Appraisal Administration</h1><p>Initiate appraisals from approved KPI plans, review independent assessments, and finalise/release locked HR records.</p></div>
            <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <div class="pm-grid">
                <form method="GET" class="card">
                    <h2>Organisation and cycle</h2>
                    <?php if (count($organisations) > 1): ?><div class="form-group"><label>Organisation</label><select name="organisation_id" onchange="this.form.submit()"><?php foreach ($organisations as $organisation): ?><option value="<?php echo (int)$organisation['id']; ?>" <?php echo (int)$organisation['id'] === $selectedOrganisationId ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option><?php endforeach; ?></select></div><?php else: ?><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><?php endif; ?>
                    <div class="form-group"><label>Cycle filter</label><select name="cycle_id" onchange="this.form.submit()"><option value="">All cycles</option><?php foreach ($cycles as $cycle): ?><option value="<?php echo (int)$cycle['id']; ?>" <?php echo $selectedCycleId === (int)$cycle['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cycle['name'] . ' — ' . $cycle['status']); ?></option><?php endforeach; ?></select></div>
                    <noscript><button class="btn btn-secondary" type="submit">Apply</button></noscript>
                </form>
                <section class="card">
                    <h2>Initiate employee appraisal</h2>
                    <?php if (!$selectedCycleId): ?><p>Select a cycle to view employees with approved KPI plans.</p>
                    <?php elseif (empty($eligibleEmployees)): ?><p>No approved plans remain to be initiated for this cycle.</p>
                    <?php else: ?><form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="cycle_id" value="<?php echo $selectedCycleId; ?>"><input type="hidden" name="action" value="start_appraisal">
                        <div class="form-group"><label>Employee with approved KPI plan</label><select name="employee_id" required><?php foreach ($eligibleEmployees as $employee): ?><option value="<?php echo (int)$employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['employee_name'] . ' — ' . $employee['staff_number']); ?></option><?php endforeach; ?></select></div>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Create this appraisal from the employee’s approved KPI plan and snapshot its reviewer route?')">Start appraisal</button>
                    </form><?php endif; ?>
                </section>
            </div>

            <section class="card" style="margin-top:22px"><h2>Appraisals (<?php echo count($appraisals); ?>)</h2>
                <?php if (empty($appraisals)): ?><p>No appraisal instances found for this organisation/cycle.</p><?php else: ?>
                    <?php foreach ($appraisals as $appraisal): ?>
                        <article class="pm-entry">
                            <h3><?php echo htmlspecialchars($appraisal['employee_name']); ?> <small><?php echo htmlspecialchars($appraisal['staff_number']); ?></small></h3>
                            <p><?php echo htmlspecialchars($appraisal['cycle_name']); ?> · <?php echo htmlspecialchars($appraisal['department_name'] ?? ''); ?> · <span class="pm-badge"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $appraisal['status']))); ?></span></p>
                            <?php $assessments = $appraisalModel->getAssessmentHistory($selectedOrganisationId, (int)$appraisal['id']); ?>
                            <?php foreach ($assessments as $assessment): ?>
                                <div class="pm-history"><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $assessment['assessment_type']))); ?></strong> · <?php echo htmlspecialchars($assessment['assessor_name']); ?> · Score: <?php echo htmlspecialchars((string)($assessment['overall_score'] ?? 'Not calculated')); ?><p><?php echo nl2br(htmlspecialchars($assessment['comments'] ?? '')); ?></p>
                                    <?php $assessmentItems = $appraisalModel->getAssessmentItems($selectedOrganisationId, (int)$assessment['id']); ?>
                                    <?php foreach ($assessmentItems as $item): ?><small><?php echo htmlspecialchars($item['kpi_title'] ?? 'Criterion'); ?>: <?php echo htmlspecialchars((string)($item['score_value'] ?? '')); ?> (actual <?php echo htmlspecialchars((string)($item['actual_value'] ?? '—')); ?>) · <?php echo htmlspecialchars($item['comments'] ?? ''); ?></small><br><?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($appraisal['status'] === 'awaiting_finalisation'): ?>
                                <?php $ratingValues = $appraisalModel->getRatingValuesForVersion($selectedOrganisationId, (int)$appraisal['template_version_id']); ?>
                                <form method="POST" class="pm-entry">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="cycle_id" value="<?php echo $selectedCycleId; ?>"><input type="hidden" name="appraisal_id" value="<?php echo (int)$appraisal['id']; ?>"><input type="hidden" name="action" value="finalise">
                                    <h4>HR finalisation</h4><p class="pm-help">Final score is entered by HR and kept separate from the original self/reviewer scores. If it differs from the latest reviewer score, an adjustment record and reason are retained.</p>
                                    <div class="form-row"><div class="form-group"><label>Final score</label><input type="number" name="final_score" step="any" required></div><div class="form-group"><label>Configured rating category</label><select name="rating_value_id" required><?php foreach ($ratingValues as $rating): ?><option value="<?php echo (int)$rating['id']; ?>"><?php echo htmlspecialchars($rating['label'] . ' (' . $rating['score_value'] . ')'); ?></option><?php endforeach; ?></select></div></div>
                                    <div class="form-group"><label>Finalisation / moderation reason</label><textarea name="reason" rows="3" required></textarea></div><button type="submit" class="btn btn-primary" onclick="return confirm('Finalise and lock this appraisal?')">Finalise and lock</button>
                                </form>
                            <?php elseif ($appraisal['status'] === 'finalised' && empty($appraisal['released_at'])): ?>
                                <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="cycle_id" value="<?php echo $selectedCycleId; ?>"><input type="hidden" name="appraisal_id" value="<?php echo (int)$appraisal['id']; ?>"><input type="hidden" name="action" value="release"><button type="submit" class="btn btn-secondary">Release result to employee</button></form>
                            <?php elseif ($appraisal['status'] === 'finalised'): ?>
                                <p><strong>Final result:</strong> <?php echo htmlspecialchars((string)$appraisal['final_score']); ?> · <?php echo htmlspecialchars($appraisal['final_rating_label'] ?? ''); ?> · Released <?php echo htmlspecialchars($appraisal['released_at']); ?></p>
                            <?php endif; ?>
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
