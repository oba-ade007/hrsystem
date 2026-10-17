<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/PerformanceManagement.php';
require_once '../src/models/PerformancePlanning.php';

$performanceModel = new PerformanceManagement($db);
$planningModel = new PerformancePlanning($db);
$organisations = $planningModel->getOrganisationsForUser((int)$_SESSION['user_id'], $_SESSION['user_role']);
$selectedOrganisationId = (int)($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? 0);
$organisationAllowed = $_SESSION['user_role'] === 'admin';
foreach ($organisations as $organisation) {
    if ((int)$organisation['id'] === $selectedOrganisationId) {
        $organisationAllowed = true;
        break;
    }
}
if ($selectedOrganisationId && !$organisationAllowed) {
    http_response_code(403);
    exit('You do not have configuration access to this appraisal organisation.');
}
if (!$selectedOrganisationId && !empty($organisations)) {
    $selectedOrganisationId = (int)$organisations[0]['id'];
}
$success = '';
$error = '';

if (empty($_SESSION['performance_csrf_token'])) {
    $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['performance_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } elseif ($_SESSION['user_role'] !== 'admin' && !in_array((int)($_POST['organisation_id'] ?? 0), array_map(function ($organisation) { return (int)$organisation['id']; }, $organisations), true)) {
        $error = 'You can only configure templates and workflows for an organisation assigned to your HR account.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_rating_scale') {
            $values = [];
            foreach (($_POST['rating_score'] ?? []) as $index => $score) {
                $values[] = [
                    'score_value' => $score,
                    'label' => $_POST['rating_label'][$index] ?? '',
                    'description' => $_POST['rating_description'][$index] ?? '',
                    'minimum_percent' => $_POST['rating_minimum'][$index] ?? '',
                    'maximum_percent' => $_POST['rating_maximum'][$index] ?? ''
                ];
            }
            $result = $performanceModel->createRatingScale($_POST, $values, (int)$_SESSION['user_id']);
        } elseif ($action === 'create_template_version') {
            $criteria = [];
            foreach (($_POST['criteria_title'] ?? []) as $index => $title) {
                $criteria[] = [
                    'title' => $title,
                    'criterion_type' => $_POST['criteria_type'][$index] ?? '',
                    'code' => $_POST['criteria_code'][$index] ?? '',
                    'description' => $_POST['criteria_description'][$index] ?? '',
                    'weight' => $_POST['criteria_weight'][$index] ?? '',
                    'is_required' => $_POST['criteria_required'][$index] ?? 0,
                    'config_json' => $_POST['criteria_config'][$index] ?? '{}'
                ];
            }
            $stages = [];
            foreach (($_POST['stage_name'] ?? []) as $index => $stageName) {
                $stages[] = [
                    'stage_name' => $stageName,
                    'workflow_phase' => $_POST['stage_phase'][$index] ?? '',
                    'stage_order' => $_POST['stage_order'][$index] ?? '',
                    'assignment_type' => $_POST['stage_assignment_type'][$index] ?? '',
                    'assignment_config_json' => $_POST['stage_assignment_config'][$index] ?? '{}',
                    'execution_mode' => $_POST['stage_execution_mode'][$index] ?? 'sequential',
                    'is_mandatory' => $_POST['stage_mandatory'][$index] ?? 0,
                    'allow_return' => $_POST['stage_allow_return'][$index] ?? 0,
                    'allow_reject' => $_POST['stage_allow_reject'][$index] ?? 0,
                    'sla_days' => $_POST['stage_sla_days'][$index] ?? '',
                    'conditions_json' => $_POST['stage_conditions'][$index] ?? '{}'
                ];
            }
            $result = $performanceModel->createTemplateVersion($_POST, $criteria, $stages, (int)$_SESSION['user_id']);
        } elseif ($action === 'publish_template_version') {
            $result = $performanceModel->publishTemplateVersion((int)($_POST['organisation_id'] ?? 0), (int)($_POST['version_id'] ?? 0), (int)$_SESSION['user_id']);
        } elseif ($action === 'assign_template_to_cycle') {
            $result = $performanceModel->assignTemplateToCycle(
                (int)($_POST['organisation_id'] ?? 0),
                (int)($_POST['cycle_id'] ?? 0),
                (int)($_POST['template_version_id'] ?? 0),
                $_POST['employee_scope_json'] ?? '{}',
                (int)$_SESSION['user_id']
            );
        } else {
            $result = ['success' => false, 'message' => 'The requested setup action is not supported.'];
        }

        if (!empty($result['success'])) {
            $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
            $selectedOrganisationId = (int)($_POST['organisation_id'] ?? $selectedOrganisationId);
            header('Location: performance-templates.php?organisation_id=' . $selectedOrganisationId . '&saved=1');
            exit;
        }
        $error = $result['message'] ?? 'The setup request could not be completed.';
    }
    $selectedOrganisationId = (int)($_POST['organisation_id'] ?? $selectedOrganisationId);
}

if (isset($_GET['saved'])) {
    $success = 'Performance configuration saved and audit recorded.';
}

$templates = $selectedOrganisationId ? $performanceModel->getTemplatesByOrganisation($selectedOrganisationId) : [];
$ratingScales = $selectedOrganisationId ? $performanceModel->getRatingScalesByOrganisation($selectedOrganisationId) : [];
$cycles = $selectedOrganisationId ? $performanceModel->getCycles($selectedOrganisationId) : [];
$publishedVersions = $selectedOrganisationId ? $performanceModel->getPublishedTemplateVersions($selectedOrganisationId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Templates - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .pm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 22px; align-items: start; }
        .pm-row { border: 1px solid #dfe7f1; border-radius: 10px; padding: 14px; margin: 12px 0; background: #fbfdff; }
        .pm-row h4 { margin-top: 0; }
        .pm-help { color: #536273; font-size: .9rem; line-height: 1.5; }
        .pm-json { font-family: Consolas, monospace; min-height: 72px; }
        @media (max-width: 640px) { .pm-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand"><h2>HR System</h2></div>
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="performance-setup.php"><i>⚙️</i> Performance Setup</a></li>
                    <li class="active"><a href="performance-templates.php"><i>🧩</i> Templates & Workflows</a></li>
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="content">
            <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
            <div class="container">
                <div class="page-header">
                    <h1>Templates, Scales & Workflows</h1>
                    <p>Build versioned assessment templates and data-driven review stages. Published versions remain available for historical cycles.</p>
                </div>

                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if (empty($organisations)): ?>
                    <div class="card"><p>Create an organisation profile in Performance Setup before configuring templates.</p></div>
                <?php else: ?>
                    <form method="GET" class="card">
                        <label for="organisation-filter">Configure organisation</label>
                        <select id="organisation-filter" name="organisation_id" onchange="this.form.submit()">
                            <?php foreach ($organisations as $organisation): ?>
                                <option value="<?php echo (int)$organisation['id']; ?>" <?php echo $selectedOrganisationId === (int)$organisation['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-secondary">Load</button></noscript>
                    </form>

                    <div class="pm-grid">
                        <section class="card">
                            <h2>Rating Scale</h2>
                            <p class="pm-help">Define your own score values, labels, and optional threshold bands. Thresholds do not impose a forced distribution.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>">
                                <input type="hidden" name="action" value="create_rating_scale">
                                <input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>">
                                <div class="form-group"><label>Scale name</label><input type="text" name="name" maxlength="150" required></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Scale type</label><input type="text" name="scale_type" value="numeric" maxlength="40" required></div>
                                    <div class="form-group"><label>Description</label><input type="text" name="description" maxlength="500"></div>
                                </div>
                                <h3>Rating anchors</h3>
                                <div id="rating-rows">
                                    <div class="pm-row rating-row">
                                        <div class="form-row">
                                            <div class="form-group"><label>Score</label><input type="number" name="rating_score[]" step="any" required></div>
                                            <div class="form-group"><label>Label</label><input type="text" name="rating_label[]" required></div>
                                        </div>
                                        <div class="form-group"><label>Description</label><input type="text" name="rating_description[]"></div>
                                        <div class="form-row">
                                            <div class="form-group"><label>Minimum percentage (optional)</label><input type="number" name="rating_minimum[]" min="0" max="100" step="any"></div>
                                            <div class="form-group"><label>Maximum percentage (optional)</label><input type="number" name="rating_maximum[]" min="0" max="100" step="any"></div>
                                        </div>
                                    </div>
                                    <div class="pm-row rating-row">
                                        <div class="form-row">
                                            <div class="form-group"><label>Score</label><input type="number" name="rating_score[]" step="any" required></div>
                                            <div class="form-group"><label>Label</label><input type="text" name="rating_label[]" required></div>
                                        </div>
                                        <div class="form-group"><label>Description</label><input type="text" name="rating_description[]"></div>
                                        <div class="form-row">
                                            <div class="form-group"><label>Minimum percentage (optional)</label><input type="number" name="rating_minimum[]" min="0" max="100" step="any"></div>
                                            <div class="form-group"><label>Maximum percentage (optional)</label><input type="number" name="rating_maximum[]" min="0" max="100" step="any"></div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="addRow('rating-rows')">Add rating anchor</button>
                                <button type="submit" class="btn btn-primary">Save rating scale</button>
                            </form>
                            <hr>
                            <h3>Configured scales</h3>
                            <?php if (empty($ratingScales)): ?><p>No scales configured.</p><?php else: ?>
                                <ul><?php foreach ($ratingScales as $scale): ?><li><?php echo htmlspecialchars($scale['name']); ?> — <?php echo (int)$scale['anchor_count']; ?> anchors</li><?php endforeach; ?></ul>
                            <?php endif; ?>
                        </section>

                        <section class="card">
                            <h2>Template Version</h2>
                            <p class="pm-help">Each submission creates a new draft version. Once published, that version cannot be edited through this screen; create another version for changes.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>">
                                <input type="hidden" name="action" value="create_template_version">
                                <input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>">
                                <div class="form-group">
                                    <label>Existing template (optional)</label>
                                    <select name="template_id">
                                        <option value="0">Create a new template</option>
                                        <?php foreach ($templates as $template): ?><option value="<?php echo (int)$template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Template name (required for a new template)</label><input type="text" name="name" maxlength="200"></div>
                                    <div class="form-group"><label>Description</label><input type="text" name="description" maxlength="500"></div>
                                </div>
                                <div class="form-group"><label>Employee scope JSON</label><textarea class="pm-json" name="employee_scope_json">{}</textarea></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Assessment methods JSON</label><textarea class="pm-json" name="assessment_methods_json">{}</textarea></div>
                                    <div class="form-group"><label>Scoring configuration JSON</label><textarea class="pm-json" name="scoring_config_json">{}</textarea></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Weight rules JSON</label><textarea class="pm-json" name="weight_rules_json">{}</textarea></div>
                                    <div class="form-group"><label>Final score rule key</label><input type="text" name="final_score_rule" maxlength="100" placeholder="Choose a documented rule key"></div>
                                </div>
                                <div class="form-group">
                                    <label>Rating scale</label>
                                    <select name="rating_scale_id"><option value="">Not selected</option><?php foreach ($ratingScales as $scale): ?><option value="<?php echo (int)$scale['id']; ?>"><?php echo htmlspecialchars($scale['name']); ?></option><?php endforeach; ?></select>
                                </div>
                                <h3>Assessment criteria</h3>
                                <p class="pm-help">Criterion types and configuration are organisation-defined. Weight is optional here; the JSON weight rules will define the validation policy.</p>
                                <div id="criteria-rows">
                                    <div class="pm-row criterion-row">
                                        <div class="form-row"><div class="form-group"><label>Type</label><input name="criteria_type[]" required placeholder="objective, competency, behaviour"></div><div class="form-group"><label>Code (optional)</label><input name="criteria_code[]"></div></div>
                                        <div class="form-group"><label>Title</label><input name="criteria_title[]" required></div>
                                        <div class="form-group"><label>Description</label><textarea name="criteria_description[]" rows="2"></textarea></div>
                                        <div class="form-row"><div class="form-group"><label>Weight (optional, 0–100)</label><input name="criteria_weight[]" type="number" min="0" max="100" step="any"></div><div class="form-group"><label><input type="checkbox" name="criteria_required[0]" value="1"> Required</label></div></div>
                                        <div class="form-group"><label>Criterion config JSON</label><textarea name="criteria_config[]" class="pm-json">{}</textarea></div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="addCriterion()">Add criterion</button>
                                <h3>Workflow stages</h3>
                                <p class="pm-help">Any number of ordered stages can be configured. Assignment types, role keys, conditions, SLA days, and routing parameters are stored as data.</p>
                                <div id="stage-rows">
                                    <div class="pm-row stage-row">
                                        <div class="form-row"><div class="form-group"><label>Stage name</label><input name="stage_name[]" required></div><div class="form-group"><label>Workflow phase</label><input name="stage_phase[]" required placeholder="kpi_approval, appraisal"></div></div>
                                        <div class="form-row"><div class="form-group"><label>Stage order</label><input name="stage_order[]" type="number" min="1" value="1" required></div><div class="form-group"><label>Assignment type</label><input name="stage_assignment_type[]" required placeholder="reporting_manager, role, user"></div></div>
                                        <div class="form-group"><label>Assignment config JSON</label><textarea name="stage_assignment_config[]" class="pm-json">{}</textarea></div>
                                        <div class="form-row"><div class="form-group"><label>Execution mode</label><select name="stage_execution_mode[]"><option value="sequential">Sequential</option><option value="parallel">Parallel</option></select></div><div class="form-group"><label>SLA days (optional)</label><input name="stage_sla_days[]" type="number" min="1"></div></div>
                                        <div class="form-group"><label>Conditions JSON</label><textarea name="stage_conditions[]" class="pm-json">{}</textarea></div>
                                        <div class="form-row">
                                            <div class="form-group"><label><input type="checkbox" name="stage_mandatory[0]" value="1" checked> Mandatory</label></div>
                                            <div class="form-group"><label><input type="checkbox" name="stage_allow_return[0]" value="1" checked> Allow return</label></div>
                                            <div class="form-group"><label><input type="checkbox" name="stage_allow_reject[0]" value="1" checked> Allow rejection</label></div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="addStage()">Add workflow stage</button>
                                <button type="submit" class="btn btn-primary">Save draft version</button>
                            </form>
                        </section>
                    </div>

                    <section class="card" style="margin-top: 24px;">
                        <h2>Templates and versions</h2>
                        <?php if (empty($templates)): ?><p>No templates created for this organisation yet.</p><?php else: ?>
                            <?php foreach ($templates as $template): ?>
                                <div class="pm-row">
                                    <h3><?php echo htmlspecialchars($template['name']); ?> <small>(<?php echo htmlspecialchars($template['status']); ?>)</small></h3>
                                    <p><?php echo htmlspecialchars($template['description'] ?? ''); ?></p>
                                    <?php $versions = $performanceModel->getTemplateVersions($selectedOrganisationId, (int)$template['id']); ?>
                                    <table class="table"><thead><tr><th>Version</th><th>Status</th><th>Created by</th><th>Published</th><th>Action</th></tr></thead><tbody>
                                        <?php foreach ($versions as $version): ?><tr>
                                            <td><?php echo (int)$version['version_number']; ?></td>
                                            <td><?php echo htmlspecialchars($version['status']); ?></td>
                                            <td><?php echo htmlspecialchars($version['created_by_name'] ?? 'System'); ?></td>
                                            <td><?php echo htmlspecialchars($version['published_at'] ?? '—'); ?></td>
                                            <td><?php if ($version['status'] === 'draft'): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="action" value="publish_template_version"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="version_id" value="<?php echo (int)$version['id']; ?>"><button type="submit" class="btn btn-small btn-primary">Publish version</button></form><?php else: ?>Read-only<?php endif; ?></td>
                                        </tr><?php endforeach; ?>
                                    </tbody></table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <section class="card" style="margin-top: 24px;">
                        <h2>Assign published template to a cycle</h2>
                        <?php if (empty($cycles) || empty($publishedVersions)): ?><p>Create a cycle and publish a template version before assigning it.</p><?php else: ?>
                            <form method="POST" class="form-row">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>">
                                <input type="hidden" name="action" value="assign_template_to_cycle">
                                <input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>">
                                <div class="form-group"><label>Cycle</label><select name="cycle_id" required><?php foreach ($cycles as $cycle): ?><option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['name'] . ' — ' . $cycle['status']); ?></option><?php endforeach; ?></select></div>
                                <div class="form-group"><label>Published template version</label><select name="template_version_id" required><?php foreach ($publishedVersions as $version): ?><option value="<?php echo (int)$version['id']; ?>"><?php echo htmlspecialchars($version['template_name'] . ' v' . $version['version_number']); ?></option><?php endforeach; ?></select></div>
                                <div class="form-group"><label>Employee scope JSON (optional)</label><textarea name="employee_scope_json" class="pm-json">{}</textarea></div>
                                <div class="form-group" style="align-self:end"><button type="submit" class="btn btn-primary">Assign version</button></div>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        function addRow(containerId) {
            const container = document.getElementById(containerId);
            const clone = container.lastElementChild.cloneNode(true);
            clone.querySelectorAll('input, textarea').forEach(function(field) { field.value = ''; });
            container.appendChild(clone);
        }
        let criterionCount = 1;
        function addCriterion() {
            const container = document.getElementById('criteria-rows');
            const clone = container.lastElementChild.cloneNode(true);
            clone.querySelectorAll('input, textarea').forEach(function(field) {
                if (field.type === 'checkbox') { field.checked = false; field.name = 'criteria_required[' + criterionCount + ']'; }
                else { field.value = field.tagName === 'TEXTAREA' && field.name === 'criteria_config[]' ? '{}' : ''; }
            });
            container.appendChild(clone);
            criterionCount++;
        }
        let stageCount = 1;
        function addStage() {
            const container = document.getElementById('stage-rows');
            const clone = container.lastElementChild.cloneNode(true);
            clone.querySelectorAll('input, textarea').forEach(function(field) {
                if (field.type === 'checkbox') {
                    const base = field.name.substring(0, field.name.indexOf('['));
                    field.checked = field.name.indexOf('stage_mandatory') === 0;
                    field.name = base + '[' + stageCount + ']';
                } else { field.value = field.name === 'stage_order[]' ? String(stageCount + 1) : (field.tagName === 'TEXTAREA' ? '{}' : ''); }
            });
            clone.querySelectorAll('select').forEach(function(field) { field.selectedIndex = 0; });
            container.appendChild(clone);
            stageCount++;
        }
    </script>
</body>
</html>
