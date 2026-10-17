<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/PerformanceManagement.php';
require_once '../src/models/PerformancePlanning.php';

$configurationModel = new PerformanceManagement($db);
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
    exit('You do not have membership and reporting configuration access to this organisation.');
}
if (!$selectedOrganisationId && !empty($organisations)) {
    $selectedOrganisationId = (int)$organisations[0]['id'];
}
$success = isset($_GET['saved']) ? 'Organisation membership or reporting configuration saved.' : '';
$error = '';

if (empty($_SESSION['performance_csrf_token'])) {
    $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['performance_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } elseif ($_SESSION['user_role'] !== 'admin' && !in_array((int)($_POST['organisation_id'] ?? 0), array_map(function ($organisation) { return (int)$organisation['id']; }, $organisations), true)) {
        $error = 'You can only manage members and reporting lines for an organisation assigned to your HR account.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_employee') {
            $result = $planningModel->addEmployeeMember($selectedOrganisationId, (int)($_POST['employee_id'] ?? 0), $_POST['access_profile_key'] ?? '', (int)$_SESSION['user_id']);
        } elseif ($action === 'add_reviewer') {
            $result = $planningModel->addUserMember($selectedOrganisationId, (int)($_POST['user_id'] ?? 0), $_POST['access_profile_key'] ?? '', (int)$_SESSION['user_id']);
        } elseif ($action === 'update_profile') {
            $result = $planningModel->updateMemberProfile($selectedOrganisationId, (int)($_POST['membership_id'] ?? 0), $_POST['access_profile_key'] ?? '', (int)$_SESSION['user_id']);
        } elseif ($action === 'add_reporting_line') {
            $lineData = $_POST;
            $lineData['organisation_id'] = $selectedOrganisationId;
            $lineData['manager_user_id'] = null;
            $result = $planningModel->setReportingLine($lineData, (int)$_SESSION['user_id']);
        } elseif ($action === 'activate_cycle') {
            $result = $planningModel->activateCycle($selectedOrganisationId, (int)($_POST['cycle_id'] ?? 0), (int)$_SESSION['user_id']);
        } else {
            $result = ['success' => false, 'message' => 'Unsupported organisation configuration action.'];
        }

        if (!empty($result['success'])) {
            $_SESSION['performance_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: performance-members.php?organisation_id=' . $selectedOrganisationId . '&saved=1');
            exit;
        }
        $error = $result['message'] ?? 'The configuration could not be saved.';
    }
    $selectedOrganisationId = (int)($_POST['organisation_id'] ?? $selectedOrganisationId);
}

$members = $selectedOrganisationId ? $planningModel->getOrganisationMembers($selectedOrganisationId) : [];
$employeeMembers = $selectedOrganisationId ? $planningModel->getEmployeeMembers($selectedOrganisationId) : [];
$eligibleEmployees = $selectedOrganisationId ? $planningModel->getEligibleEmployees($selectedOrganisationId) : [];
$eligibleReviewers = $selectedOrganisationId ? $planningModel->getUnassignedReviewerUsers($selectedOrganisationId) : [];
$reportingLines = $selectedOrganisationId ? $planningModel->getReportingLines($selectedOrganisationId) : [];
$cycles = $selectedOrganisationId ? $configurationModel->getCycles($selectedOrganisationId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Organisation Members - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .pm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; align-items: start; }
        .pm-help { color: #536273; font-size: .9rem; line-height: 1.5; }
        .profile-form { display: flex; gap: 6px; min-width: 220px; }
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
                    <li><a href="performance-templates.php"><i>🧩</i> Templates & Workflows</a></li>
                    <li class="active"><a href="performance-members.php"><i>👥</i> Members & Reporting</a></li>
                    <li><a href="employees.php"><i>👤</i> Employees</a></li>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        <main class="content">
            <div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
            <div class="container">
                <div class="page-header">
                    <h1>Organisation Membership & Reporting</h1>
                    <p>Assign existing HR identities to an appraisal organisation and capture effective-dated supervisor levels. Employee records are reused, not duplicated.</p>
                </div>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (empty($organisations)): ?>
                    <div class="card"><p>Create an organisation profile before assigning members.</p></div>
                <?php else: ?>
                    <form method="GET" class="card">
                        <label for="organisation-filter">Organisation</label>
                        <select id="organisation-filter" name="organisation_id" onchange="this.form.submit()">
                            <?php foreach ($organisations as $organisation): ?><option value="<?php echo (int)$organisation['id']; ?>" <?php echo $selectedOrganisationId === (int)$organisation['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option><?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-secondary">Load</button></noscript>
                    </form>
                    <div class="alert alert-info">Tenant safety note: appraisal records are organisation-scoped. The legacy HR pages remain single-tenant until their user, employee, branch, and department queries are updated; do not activate multiple organisations for shared employee data in production yet.</div>

                    <div class="pm-grid">
                        <section class="card">
                            <h2>Add existing employee</h2>
                            <p class="pm-help">Only active employees not already mapped to an appraisal organisation are listed.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="action" value="add_employee">
                                <div class="form-group"><label>Employee</label><select name="employee_id" required><option value="">Select employee</option><?php foreach ($eligibleEmployees as $employee): ?><option value="<?php echo (int)$employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['full_name'] . ' — ' . $employee['staff_number'] . ' — ' . $employee['position']); ?></option><?php endforeach; ?></select></div>
                                <div class="form-group"><label>Access profile key</label><input name="access_profile_key" value="employee" maxlength="100" required><small>Use a configurable key such as employee or manager.</small></div>
                                <button type="submit" class="btn btn-primary" <?php echo empty($eligibleEmployees) ? 'disabled' : ''; ?>>Add employee</button>
                            </form>
                        </section>
                        <section class="card">
                            <h2>Add non-employee reviewer</h2>
                            <p class="pm-help">Use existing user accounts for reviewers who do not have an employee record in this HR database.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="action" value="add_reviewer">
                                <div class="form-group"><label>User</label><select name="user_id" required><option value="">Select user</option><?php foreach ($eligibleReviewers as $reviewer): ?><option value="<?php echo (int)$reviewer['id']; ?>"><?php echo htmlspecialchars($reviewer['full_name'] . ' — ' . $reviewer['email']); ?></option><?php endforeach; ?></select></div>
                                <div class="form-group"><label>Access profile key</label><input name="access_profile_key" value="reviewer" maxlength="100" required></div>
                                <button type="submit" class="btn btn-primary" <?php echo empty($eligibleReviewers) ? 'disabled' : ''; ?>>Add reviewer</button>
                            </form>
                        </section>
                    </div>

                    <section class="card" style="margin-top: 22px;">
                        <h2>Members (<?php echo count($members); ?>)</h2>
                        <div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Employee ID</th><th>Position / Department</th><th>Branch</th><th>Access profile</th><th>Update profile</th></tr></thead><tbody>
                            <?php if (empty($members)): ?><tr><td colspan="6" class="text-center">No members assigned.</td></tr><?php else: foreach ($members as $member): ?>
                                <tr><td><?php echo htmlspecialchars($member['full_name']); ?><br><small><?php echo htmlspecialchars($member['email']); ?></small></td><td><?php echo htmlspecialchars($member['staff_number'] ?? 'Reviewer'); ?></td><td><?php echo htmlspecialchars(trim(($member['position'] ?? '') . ' / ' . ($member['department_name'] ?? ''), ' /')); ?></td><td><?php echo htmlspecialchars($member['branch_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($member['access_profile_key'] ?? '—'); ?></td><td><form method="POST" class="profile-form"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="membership_id" value="<?php echo (int)$member['membership_id']; ?>"><input type="hidden" name="action" value="update_profile"><input name="access_profile_key" value="<?php echo htmlspecialchars($member['access_profile_key'] ?? ''); ?>" maxlength="100" required><button type="submit" class="btn btn-small btn-secondary">Save</button></form></td></tr>
                            <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </section>

                    <div class="pm-grid" style="margin-top: 22px;">
                        <section class="card">
                            <h2>Reporting line</h2>
                            <p class="pm-help">Choose a manager from this organisation's employee memberships. Add a separate level for each supervisor; effective periods may not overlap for the same employee and level.</p>
                            <?php if (count($employeeMembers) < 2): ?><p>Add at least two employee members before assigning a manager.</p><?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="action" value="add_reporting_line">
                                    <div class="form-group"><label>Employee</label><select name="employee_id" required><?php foreach ($employeeMembers as $employee): ?><option value="<?php echo (int)$employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['full_name'] . ' — ' . $employee['staff_number']); ?></option><?php endforeach; ?></select></div>
                                    <div class="form-group"><label>Manager / supervisor</label><select name="manager_employee_id" required><option value="">Select manager</option><?php foreach ($employeeMembers as $manager): ?><option value="<?php echo (int)$manager['employee_id']; ?>"><?php echo htmlspecialchars($manager['full_name'] . ' — ' . $manager['staff_number']); ?></option><?php endforeach; ?></select></div>
                                    <div class="form-row"><div class="form-group"><label>Hierarchy level</label><input type="number" name="level_order" min="1" value="1" required></div><div class="form-group"><label>Relationship type</label><input type="text" name="relationship_type" value="supervisor" maxlength="80" required></div></div>
                                    <div class="form-row"><div class="form-group"><label>Effective from</label><input type="date" name="valid_from" required></div><div class="form-group"><label>Effective until (optional)</label><input type="date" name="valid_until"></div></div>
                                    <button type="submit" class="btn btn-primary">Save reporting line</button>
                                </form>
                            <?php endif; ?>
                        </section>
                        <section class="card">
                            <h2>Activate a cycle</h2>
                            <p class="pm-help">Activation is allowed only for a draft cycle with at least one assigned, published template version.</p>
                            <?php $draftCycles = array_filter($cycles, function ($cycle) { return $cycle['status'] === 'draft'; }); ?>
                            <?php if (empty($draftCycles)): ?><p>No draft cycles are available.</p><?php else: ?>
                                <?php foreach ($draftCycles as $cycle): ?><form method="POST" style="margin-bottom: 12px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['performance_csrf_token']); ?>"><input type="hidden" name="organisation_id" value="<?php echo $selectedOrganisationId; ?>"><input type="hidden" name="cycle_id" value="<?php echo (int)$cycle['id']; ?>"><input type="hidden" name="action" value="activate_cycle">
                                    <strong><?php echo htmlspecialchars($cycle['name']); ?></strong> <button type="submit" class="btn btn-small btn-primary" onclick="return confirm('Activate this appraisal cycle?')">Activate</button>
                                </form><?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                    </div>

                    <section class="card" style="margin-top: 22px;">
                        <h2>Reporting hierarchy history</h2>
                        <div class="table-responsive"><table class="table"><thead><tr><th>Employee</th><th>Manager</th><th>Level</th><th>Relationship</th><th>Effective period</th><th>Status</th></tr></thead><tbody>
                            <?php if (empty($reportingLines)): ?><tr><td colspan="6" class="text-center">No reporting lines configured.</td></tr><?php else: foreach ($reportingLines as $line): ?>
                                <tr><td><?php echo htmlspecialchars(($line['employee_name'] ?? 'Unknown') . ' — ' . ($line['staff_number'] ?? '')); ?></td><td><?php echo htmlspecialchars($line['manager_name'] ?: ($line['manager_user_name'] ?: 'Unassigned')); ?></td><td><?php echo (int)$line['level_order']; ?></td><td><?php echo htmlspecialchars($line['relationship_type']); ?></td><td><?php echo htmlspecialchars($line['valid_from'] . ' to ' . ($line['valid_until'] ?: 'Present')); ?></td><td><?php echo htmlspecialchars($line['status']); ?></td></tr>
                            <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
