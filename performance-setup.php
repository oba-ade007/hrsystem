<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/PerformanceManagement.php';
require_once '../src/models/PerformancePlanning.php';

$performanceModel = new PerformanceManagement($db);
$planningModel = new PerformancePlanning($db);
$organisations = $planningModel->getOrganisationsForUser((int)$_SESSION['user_id'], $_SESSION['user_role']);
$cycles = [];
foreach ($organisations as $organisation) {
    $cycles = array_merge($cycles, $performanceModel->getCycles((int)$organisation['id']));
}
$success = '';
$error = '';

if (empty($_SESSION['performance_csrf_token'])) {
    $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['created'])) {
    $success = $_GET['created'] === 'organisation'
        ? 'Organisation profile created.'
        : 'Appraisal cycle created in draft status.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['performance_csrf_token'], $csrfToken)) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $requestedOrganisationId = (int)($_POST['organisation_id'] ?? 0);
        $organisationAllowed = $_SESSION['user_role'] === 'admin';
        foreach ($organisations as $organisation) {
            if ((int)$organisation['id'] === $requestedOrganisationId) {
                $organisationAllowed = true;
                break;
            }
        }
        if ($action === 'create_organisation') {
            $result = $performanceModel->createOrganisation($_POST, (int)$_SESSION['user_id']);
        } elseif ($action === 'create_cycle' && $organisationAllowed) {
            $result = $performanceModel->createCycle($_POST, (int)$_SESSION['user_id']);
        } elseif ($action === 'create_cycle') {
            $result = ['success' => false, 'message' => 'You can only configure appraisal cycles for an organisation assigned to your HR account.'];
        } else {
            $result = ['success' => false, 'message' => 'The requested setup action is not supported.'];
        }

        if ($result['success']) {
            $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
            $created = $action === 'create_organisation' ? 'organisation' : 'cycle';
            header('Location: performance-setup.php?created=' . urlencode($created));
            exit;
        }
        $error = $result['message'] ?? 'The setup request could not be completed.';
    }

    $organisations = $planningModel->getOrganisationsForUser((int)$_SESSION['user_id'], $_SESSION['user_role']);
    $cycles = [];
    foreach ($organisations as $organisation) {
        $cycles = array_merge($cycles, $performanceModel->getCycles((int)$organisation['id']));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Management Setup - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .performance-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 22px; align-items: start; }
        .performance-note { color: #536273; font-size: 0.92rem; line-height: 1.5; }
        .cycle-deadlines { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0 16px; }
        .status-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-weight: 600; font-size: 0.82rem; }
        @media (max-width: 640px) { .performance-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand"><h2>HR System</h2></div>
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
                    <li class="active"><a href="performance-setup.php"><i>🎯</i> Performance Setup</a></li>
                    <li><a href="performance-templates.php"><i>🧩</i> Templates & Workflows</a></li>
                    <li><a href="performance-members.php"><i>👥</i> Members & Reporting</a></li>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="content">
            <div class="topbar">
                <div class="user-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                </div>
            </div>

            <div class="container">
                <div class="page-header">
                    <h1>Performance Management Setup</h1>
                    <p>Configure organisation profiles and create independent appraisal cycles. Cycles begin as drafts and do not overwrite earlier periods.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="performance-grid">
                    <section class="card">
                        <h2>Organisation Profile</h2>
                        <p class="performance-note">Organisation name, code, and timezone are configurable. No organisation-specific defaults are built into the module.</p>
                        <form method="POST" action="performance-setup.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>">
                            <input type="hidden" name="action" value="create_organisation">
                            <div class="form-group">
                                <label for="organisation-name">Organisation name</label>
                                <input id="organisation-name" type="text" name="name" maxlength="200" required>
                            </div>
                            <div class="form-group">
                                <label for="organisation-code">Organisation code</label>
                                <input id="organisation-code" type="text" name="organisation_code" minlength="3" maxlength="80" pattern="[a-z0-9][a-z0-9-]{1,78}[a-z0-9]" required>
                                <small>Use 3–80 lowercase letters, numbers, or hyphens.</small>
                            </div>
                            <div class="form-group">
                                <label for="organisation-timezone">Timezone</label>
                                <input id="organisation-timezone" type="text" name="timezone" value="UTC" maxlength="80" required>
                                <small>Use an IANA timezone, for example Europe/London.</small>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Organisation</button>
                        </form>
                    </section>

                    <section class="card">
                        <h2>New Appraisal Cycle</h2>
                        <p class="performance-note">Period names and period types are free-form. Enter only dates relevant to your organisation's calendar.</p>
                        <?php if (empty($organisations)): ?>
                            <div class="alert alert-info">Create an organisation profile before adding a cycle.</div>
                        <?php else: ?>
                            <form method="POST" action="performance-setup.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>">
                                <input type="hidden" name="action" value="create_cycle">
                                <div class="form-group">
                                    <label for="cycle-organisation">Organisation</label>
                                    <select id="cycle-organisation" name="organisation_id" required>
                                        <option value="">Select organisation</option>
                                        <?php foreach ($organisations as $organisation): ?>
                                            <?php if ($organisation['status'] === 'active'): ?>
                                                <option value="<?php echo (int)$organisation['id']; ?>"><?php echo htmlspecialchars($organisation['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="cycle-name">Cycle name</label>
                                        <input id="cycle-name" type="text" name="name" maxlength="200" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cycle-type">Period type</label>
                                        <input id="cycle-type" type="text" name="period_type" maxlength="50" placeholder="Annual, quarterly, probationary, project-based" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="period-label">Period label (optional)</label>
                                    <input id="period-label" type="text" name="period_label" maxlength="120" placeholder="Organisation-defined period label">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="cycle-start">Starts on</label>
                                        <input id="cycle-start" type="date" name="starts_on" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cycle-end">Ends on</label>
                                        <input id="cycle-end" type="date" name="ends_on" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cycle-timezone">Cycle timezone</label>
                                    <input id="cycle-timezone" type="text" name="timezone" value="UTC" maxlength="80" required>
                                </div>
                                <h3>Optional deadlines</h3>
                                <div class="cycle-deadlines">
                                    <div class="form-group"><label for="kpi-deadline">KPI setting deadline</label><input id="kpi-deadline" type="datetime-local" name="kpi_setting_deadline"></div>
                                    <div class="form-group"><label for="kpi-approval-deadline">KPI approval deadline</label><input id="kpi-approval-deadline" type="datetime-local" name="kpi_approval_deadline"></div>
                                    <div class="form-group"><label for="appraisal-opens">Appraisal opens</label><input id="appraisal-opens" type="datetime-local" name="appraisal_opens_at"></div>
                                    <div class="form-group"><label for="appraisal-closes">Appraisal closes</label><input id="appraisal-closes" type="datetime-local" name="appraisal_closes_at"></div>
                                    <div class="form-group"><label for="review-deadline">Review deadline</label><input id="review-deadline" type="datetime-local" name="review_deadline"></div>
                                    <div class="form-group"><label for="finalisation-deadline">Finalisation deadline</label><input id="finalisation-deadline" type="datetime-local" name="finalisation_deadline"></div>
                                </div>
                                <button type="submit" class="btn btn-primary">Save Cycle as Draft</button>
                            </form>
                        <?php endif; ?>
                    </section>
                </div>

                <section class="card" style="margin-top: 24px;">
                    <h2>Configured Organisations (<?php echo count($organisations); ?>)</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Organisation</th><th>Code</th><th>Timezone</th><th>Status</th><th>Created by</th></tr></thead>
                            <tbody>
                                <?php if (empty($organisations)): ?>
                                    <tr><td colspan="5" class="text-center">No organisation profiles have been created.</td></tr>
                                <?php else: foreach ($organisations as $organisation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($organisation['name']); ?></td>
                                        <td><?php echo htmlspecialchars($organisation['organisation_code']); ?></td>
                                        <td><?php echo htmlspecialchars($organisation['timezone']); ?></td>
                                        <td><span class="status-pill"><?php echo htmlspecialchars(ucfirst($organisation['status'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($organisation['created_by_name'] ?? 'System'); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card" style="margin-top: 24px;">
                    <h2>Appraisal Cycles (<?php echo count($cycles); ?>)</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Cycle</th><th>Organisation</th><th>Period type</th><th>Period</th><th>Dates</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($cycles)): ?>
                                    <tr><td colspan="6" class="text-center">No appraisal cycles have been created.</td></tr>
                                <?php else: foreach ($cycles as $cycle): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cycle['name']); ?></td>
                                        <td><?php echo htmlspecialchars($cycle['organisation_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cycle['period_type']); ?></td>
                                        <td><?php echo htmlspecialchars($cycle['period_label'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($cycle['starts_on'] . ' to ' . $cycle['ends_on']); ?></td>
                                        <td><span class="status-pill"><?php echo htmlspecialchars(ucfirst($cycle['status'])); ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
