<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/EmployeeDataUpdateCampaign.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';

$campaignModel = new EmployeeDataUpdateCampaign($db);
$departmentModel = new Department($db);
$branchModel = new Branch($db);

$selectedDepartment = $_GET['department_id'] ?? '';
$selectedBranch = $_GET['branch_id'] ?? '';
$selectedStatus = $_GET['status'] ?? 'all';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_campaign') {
        $name = trim($_POST['campaign_name'] ?? '');
        $startAt = trim($_POST['start_at'] ?? '');
        $endAt = trim($_POST['end_at'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $employees = $_POST['employees'] ?? [];
        $departments = $_POST['departments'] ?? [];
        $branches = $_POST['branches'] ?? [];
        $fields = $_POST['allowed_fields'] ?? [];

        if ($name === '' || $startAt === '' || $endAt === '') {
            $error = 'Campaign name, start date/time and end date/time are required.';
        } elseif (strtotime($endAt) <= strtotime($startAt)) {
            $error = 'End date/time must be after the start date/time.';
        } else {
            $db->begin_transaction();
            try {
                $status = (strtotime($startAt) <= time() && strtotime($endAt) >= time()) ? 'active' : 'scheduled';
                $stmt = $db->prepare("INSERT INTO employee_update_campaigns (name, start_at, end_at, instructions, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param('sssssi', $name, $startAt, $endAt, $instructions, $status, $_SESSION['user_id']);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create campaign.');
                }
                $campaignId = $stmt->insert_id;

                if (!empty($fields)) {
                    $fieldStmt = $db->prepare("INSERT INTO employee_update_campaign_fields (campaign_id, field_name, label) VALUES (?, ?, ?)");
                    foreach ($fields as $fieldName) {
                        $fieldName = trim((string)$fieldName);
                        if ($fieldName !== '') {
                            $fieldLabel = ucfirst(str_replace('_', ' ', $fieldName));
                            $fieldStmt->bind_param('iss', $campaignId, $fieldName, $fieldLabel);
                            $fieldStmt->execute();
                        }
                    }
                    $fieldStmt->close();
                }

                $targetStmt = $db->prepare("INSERT INTO employee_update_campaign_targets (campaign_id, employee_id, department_id, branch_id) VALUES (?, ?, ?, ?)");
                foreach ($employees as $employeeId) {
                    $id = (int)$employeeId;
                    if ($id > 0) {
                        $employeeIdValue = $id;
                        $departmentIdValue = null;
                        $branchIdValue = null;
                        $targetStmt->bind_param('iiii', $campaignId, $employeeIdValue, $departmentIdValue, $branchIdValue);
                        $targetStmt->execute();
                    }
                }
                foreach ($departments as $departmentId) {
                    $id = (int)$departmentId;
                    if ($id > 0) {
                        $employeeIdValue = null;
                        $departmentIdValue = $id;
                        $branchIdValue = null;
                        $targetStmt->bind_param('iiii', $campaignId, $employeeIdValue, $departmentIdValue, $branchIdValue);
                        $targetStmt->execute();
                    }
                }
                foreach ($branches as $branchId) {
                    $id = (int)$branchId;
                    if ($id > 0) {
                        $employeeIdValue = null;
                        $departmentIdValue = null;
                        $branchIdValue = $id;
                        $targetStmt->bind_param('iiii', $campaignId, $employeeIdValue, $departmentIdValue, $branchIdValue);
                        $targetStmt->execute();
                    }
                }
                $targetStmt->close();

                $historyStmt = $db->prepare("INSERT INTO employee_update_campaign_history (campaign_id, action, details, actor_id, created_at) VALUES (?, 'created', ?, ?, NOW())");
                $details = 'Campaign created by ' . $_SESSION['full_name'];
                $historyStmt->bind_param('isi', $campaignId, $details, $_SESSION['user_id']);
                $historyStmt->execute();
                $historyStmt->close();

                $db->commit();
                $message = 'Employee data update campaign created successfully.';
            } catch (Exception $e) {
                $db->rollback();
                $error = $e->getMessage();
            }
        }
    }

    if ($action === 'update_status') {
        $campaignId = (int)($_POST['campaign_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['scheduled', 'active', 'paused', 'closed'];
        if ($campaignId > 0 && in_array($status, $allowed, true)) {
            $stmt = $db->prepare("UPDATE employee_update_campaigns SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $status, $campaignId);
            if ($stmt->execute()) {
                $message = 'Campaign status updated successfully.';
            } else {
                $error = 'Failed to update campaign status.';
            }
        }
    }
}

$campaigns = $campaignModel->getCampaigns();
$allDepartments = $departmentModel->getAllDepartments();
$allBranches = $branchModel->getAllBranches();
$dashboardStats = $campaignModel->getDashboardStats($selectedDepartment ?: null, $selectedBranch ?: null, $selectedStatus);
$allEmployees = $db->query("SELECT e.id, e.employee_id, u.full_name, d.name AS department_name, b.name AS branch_name FROM employees e JOIN users u ON u.id = e.user_id LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN branches b ON b.id = e.branch_id ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);

$openCampaign = $db->query("SELECT * FROM employee_update_campaigns WHERE status IN ('active', 'scheduled') AND start_at <= NOW() AND end_at >= NOW() ORDER BY start_at DESC LIMIT 1")->fetch_assoc();
$openCampaignTargets = [];
if ($openCampaign) {
    $openCampaignId = (int)$openCampaign['id'];
    $openCampaignTargets = $db->query("SELECT DISTINCT u.full_name, e.employee_id, d.name AS department_name, b.name AS branch_name
        FROM employees e
        JOIN users u ON u.id = e.user_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN branches b ON b.id = e.branch_id
        WHERE e.id IN (
            SELECT employee_id FROM employee_update_campaign_targets WHERE campaign_id = {$openCampaignId} AND employee_id IS NOT NULL
        )
        OR e.department_id IN (
            SELECT department_id FROM employee_update_campaign_targets WHERE campaign_id = {$openCampaignId} AND department_id IS NOT NULL
        )
        OR e.branch_id IN (
            SELECT branch_id FROM employee_update_campaign_targets WHERE campaign_id = {$openCampaignId} AND branch_id IS NOT NULL
        )
        ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);
}

$availableFields = [
    ['name' => 'phone', 'label' => 'Phone'],
    ['name' => 'address', 'label' => 'Address'],
    ['name' => 'city', 'label' => 'City'],
    ['name' => 'state', 'label' => 'State'],
    ['name' => 'zip_code', 'label' => 'Zip Code'],
    ['name' => 'emergency_contact', 'label' => 'Emergency Contact'],
    ['name' => 'emergency_contact_phone', 'label' => 'Emergency Contact Phone'],
    ['name' => 'credentials_file', 'label' => 'Credentials & Documents'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Data Update Campaigns</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .field-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 10px; }
        .check-group { margin-bottom: 12px; }
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-scheduled { background: #e8f0fe; color: #1a73e8; }
        .status-active { background: #d4edda; color: #155724; }
        .status-paused { background: #fff3cd; color: #856404; }
        .status-closed { background: #f8d7da; color: #721c24; }
        .table-wrap { overflow-x: auto; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin: 20px 0; }
        .summary-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 18px; }
        .summary-card h3 { margin: 0 0 10px; font-size: 14px; color: #666; }
        .summary-card .value { font-size: 28px; font-weight: bold; color: #2c3e50; }
        .summary-card .small { color: #777; font-size: 12px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin: 20px 0; }
        .filter-row .field { min-width: 180px; }
        .filter-row label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar">
        <div class="brand"><h2>HR System</h2></div>
        <nav class="nav-menu">
            <ul>
                <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                <li><a href="attendance.php"><i>📋</i> Attendance</a></li>
                <li><a href="employees.php"><i>👥</i> Employees</a></li>
                <li><a href="departments.php"><i>🏢</i> Departments</a></li>
                <li><a href="employee-amendments.php"><i>✎</i> Amendments</a></li>
                <li class="active"><a href="employee-data-update-campaigns.php"><i>🗂️</i> Update Campaigns</a></li>
                <li><a href="hr-record-update-approvals.php"><i>✓</i> Record Approvals</a></li>
                <li><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                <li class="menu-header">ACCOUNT</li>
                <li><a href="logout.php"><i>🚪</i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="content">
        <div class="topbar">
            <div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div>
        </div>

        <div class="container">
            <div class="page-header">
                <h1>Employee Data Update Campaigns</h1>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="card">
                <h2>HR Dashboard</h2>
                <?php if ($openCampaign): ?>
                    <div style="margin-bottom: 20px; padding: 14px 16px; border: 1px solid #c8e6c9; background: #f1f8e9; border-radius: 8px;">
                        <strong>Open campaign target check:</strong> <?php echo htmlspecialchars($openCampaign['name']); ?>
                        <div style="margin-top: 8px; color: #2e7d32;">
                            <?php echo count($openCampaignTargets); ?> employee(s) currently matched to this campaign.
                        </div>
                        <?php if (!empty($openCampaignTargets)): ?>
                            <div style="margin-top: 12px; max-height: 180px; overflow-y: auto; border: 1px solid #bfd8b8; background: #fff; border-radius: 6px; padding: 10px;">
                                <?php foreach ($openCampaignTargets as $target): ?>
                                    <div style="padding: 6px 0; border-bottom: 1px solid #eee;">
                                        <strong><?php echo htmlspecialchars($target['full_name']); ?></strong>
                                        <span style="color: #555;">(<?php echo htmlspecialchars($target['employee_id']); ?>)</span>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo htmlspecialchars($target['department_name'] ?? 'No department'); ?> / <?php echo htmlspecialchars($target['branch_name'] ?? 'No branch'); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 12px; color: #b71c1c;">No employees are currently targeted by this open campaign.</div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 20px; padding: 12px 14px; border: 1px solid #f5c6cb; background: #f8d7da; border-radius: 8px; color: #721c24;">
                        No open campaign is currently active for matching.
                    </div>
                <?php endif; ?>
                <form method="GET" class="filter-row">
                    <div class="field">
                        <label for="department_id">Department</label>
                        <select name="department_id" id="department_id">
                            <option value="">All Departments</option>
                            <?php foreach ($allDepartments as $department): ?>
                                <option value="<?php echo (int)$department['id']; ?>" <?php echo (string)$selectedDepartment === (string)$department['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($department['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="branch_id">Branch</label>
                        <select name="branch_id" id="branch_id">
                            <option value="">All Branches</option>
                            <?php foreach ($allBranches as $branch): ?>
                                <option value="<?php echo (int)$branch['id']; ?>" <?php echo (string)$selectedBranch === (string)$branch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $selectedStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $selectedStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $selectedStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $selectedStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>

                <div class="summary-grid">
                    <div class="summary-card">
                        <h3>Total Employees Targeted</h3>
                        <div class="value"><?php echo (int)$dashboardStats['total_targeted']; ?></div>
                    </div>
                    <div class="summary-card">
                        <h3>Updated</h3>
                        <div class="value"><?php echo (int)$dashboardStats['updated']; ?></div>
                    </div>
                    <div class="summary-card">
                        <h3>Not Updated</h3>
                        <div class="value"><?php echo (int)$dashboardStats['not_updated']; ?></div>
                    </div>
                    <div class="summary-card">
                        <h3>Completion</h3>
                        <div class="value"><?php echo number_format((float)$dashboardStats['completion_percentage'], 2); ?>%</div>
                    </div>
                    <div class="summary-card">
                        <h3>Pending Approval</h3>
                        <div class="value"><?php echo (int)$dashboardStats['pending_approval']; ?></div>
                    </div>
                    <div class="summary-card">
                        <h3>Approved</h3>
                        <div class="value"><?php echo (int)$dashboardStats['approved']; ?></div>
                    </div>
                    <div class="summary-card">
                        <h3>Rejected</h3>
                        <div class="value"><?php echo (int)$dashboardStats['rejected']; ?></div>
                    </div>
                    <div class="summary-card">
                        <h3>Filter</h3>
                        <div class="small"><?php echo htmlspecialchars($selectedDepartment ? 'Department' : 'All'); ?> / <?php echo htmlspecialchars($selectedBranch ? 'Branch' : 'All'); ?> / <?php echo htmlspecialchars(ucfirst($selectedStatus)); ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Create Campaign</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_campaign">
                    <div class="grid-2">
                        <div>
                            <label for="campaign_name">Campaign Name</label>
                            <input type="text" id="campaign_name" name="campaign_name" required>
                        </div>
                        <div></div>
                        <div>
                            <label for="start_at">Start Date/Time</label>
                            <input type="datetime-local" id="start_at" name="start_at" required>
                        </div>
                        <div>
                            <label for="end_at">End Date/Time</label>
                            <input type="datetime-local" id="end_at" name="end_at" required>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <label for="instructions">Instructions</label>
                        <textarea id="instructions" name="instructions" rows="4" style="width: 100%;"></textarea>
                    </div>

                    <div class="grid-2" style="margin-top: 20px;">
                        <div>
                            <h3>Employees</h3>
                            <div class="check-group" style="max-height: 220px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 6px;">
                                <?php foreach ($allEmployees as $employee): ?>
                                    <label style="display:block; margin-bottom:8px;">
                                        <input type="checkbox" name="employees[]" value="<?php echo (int)$employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['full_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <h3>Departments</h3>
                            <div class="check-group" style="max-height: 220px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 6px;">
                                <?php foreach ($allDepartments as $department): ?>
                                    <label style="display:block; margin-bottom:8px;">
                                        <input type="checkbox" name="departments[]" value="<?php echo (int)$department['id']; ?>">
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <h3>Branches</h3>
                        <div class="check-group" style="max-height: 220px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 6px;">
                            <?php foreach ($allBranches as $branch): ?>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="checkbox" name="branches[]" value="<?php echo (int)$branch['id']; ?>">
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <h3>Fields Employees May Update</h3>
                        <div class="field-list">
                            <?php foreach ($availableFields as $field): ?>
                                <label style="display:flex; align-items:center; gap:10px;">
                                    <input type="checkbox" name="allowed_fields[]" value="<?php echo htmlspecialchars($field['name']); ?>">
                                    <span><?php echo htmlspecialchars($field['label']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Create Campaign</button>
                    </div>
                </form>
            </div>

            <div class="card" style="margin-top: 30px;">
                <h2>Campaign List</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Employees</th>
                                <th>Fields</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($campaigns)): ?>
                                <tr><td colspan="7">No campaigns found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($campaign['start_at'])); ?></td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($campaign['end_at'])); ?></td>
                                        <td><span class="status-badge status-<?php echo htmlspecialchars($campaign['status']); ?>"><?php echo ucfirst(htmlspecialchars($campaign['status'])); ?></span></td>
                                        <td><?php echo (int)($campaign['target_count'] ?? 0); ?></td>
                                        <td><?php echo (int)($campaign['field_count'] ?? 0); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="campaign_id" value="<?php echo (int)$campaign['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" style="padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                    <option value="scheduled" <?php echo $campaign['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                    <option value="active" <?php echo $campaign['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="paused" <?php echo $campaign['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                                    <option value="closed" <?php echo $campaign['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
