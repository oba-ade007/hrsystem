<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';
require_once '../src/models/EmployeeDataUpdateCampaign.php';

$employeeModel = new Employee($db);
$campaignModel = new EmployeeDataUpdateCampaign($db);

$userId = $_SESSION['user_id'];
$employee = $employeeModel->getEmployeeByUserId($userId);
$activeCampaign = null;
$allowedFields = [];
$message = '';
$error = '';

if ($employee) {
    $activeCampaign = $campaignModel->getActiveCampaignForEmployee((int)$employee['id']);
    if ($activeCampaign) {
        $allowedFields = $campaignModel->getAllowedFields((int)$activeCampaign['id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update'])) {
    if (!$employee || !$activeCampaign) {
        $error = 'There is no active employee update window for your record right now.';
    } else {
        $allowedNames = array_map(function ($field) {
            return $field['field_name'];
        }, $allowedFields);

        $changes = [];
        foreach ($allowedNames as $fieldName) {
            $newValue = isset($_POST[$fieldName]) ? trim($_POST[$fieldName]) : '';
            $oldValue = isset($employee[$fieldName]) ? $employee[$fieldName] : '';
            if ($newValue !== (string)$oldValue) {
                $changes[] = ['field' => $fieldName, 'old' => $oldValue, 'new' => $newValue];
            }
        }

        if (empty($changes)) {
            $message = 'No changes were submitted for approval.';
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $submissionIds = [];
            $db->begin_transaction();

            try {
                $stmt = $db->prepare("INSERT INTO employee_update_submissions (campaign_id, employee_id, field_name, old_value, new_value, approval_status, ip_address, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())");

                foreach ($changes as $change) {
                    $fieldName = $change['field'];
                    $oldValue = $change['old'];
                    $newValue = $change['new'];
                    $stmt->bind_param('iissss', $activeCampaign['id'], $employee['id'], $fieldName, $oldValue, $newValue, $ip);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Failed to insert employee update submission for field: ' . $fieldName);
                    }
                    $submissionIds[] = $stmt->insert_id;
                }

                $historyStmt = $db->prepare("INSERT INTO employee_update_campaign_history (campaign_id, action, details, actor_id, created_at) VALUES (?, 'employee_update_submitted', ?, ?, NOW())");
                $details = 'Employee submitted record changes for review';
                $historyStmt->bind_param('isi', $activeCampaign['id'], $details, $userId);
                if (!$historyStmt->execute()) {
                    throw new RuntimeException('Failed to log employee update submission history.');
                }

                $db->commit();
                $message = 'Your record update request has been submitted for HR review.';
                error_log('Employee record update submitted. user_id=' . (int)$userId . ', employee_id=' . (int)$employee['id'] . ', campaign_id=' . (int)$activeCampaign['id'] . ', submission_ids=' . implode(',', $submissionIds));
            } catch (Throwable $e) {
                $db->rollback();
                error_log('Employee record update failed. user_id=' . (int)$userId . ', employee_id=' . (int)$employee['id'] . ', campaign_id=' . (int)$activeCampaign['id'] . ': ' . $e->getMessage());
                $error = 'We could not submit your update request. Please try again or contact HR.';
            }
        }
    }
}

$allowedFieldsNames = array_map(function ($field) {
    return $field['field_name'];
}, $allowedFields);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Record Update</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .campaign-banner { background: #e8f5e9; border: 1px solid #a5d6a7; color: #1b5e20; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .record-form { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .field-group { margin-bottom: 20px; }
        .field-group label { display: block; font-weight: 700; margin-bottom: 8px; }
        .field-group input, .field-group textarea, .field-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
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
                <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
                <li class="active"><a href="employee-record-update.php"><i>✎</i> Update My Records</a></li>
                <li><a href="my-amendments.php"><i>📝</i> My Amendments</a></li>
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
                <h1>Employee Record Update</h1>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if (!$employee): ?>
                <div class="alert alert-warning">Your employee profile could not be found. Contact HR.</div>
            <?php elseif (!$activeCampaign): ?>
                <div class="alert alert-info">No active employee record update campaign is currently open for your account.</div>
            <?php else: ?>
                <div class="campaign-banner">
                    <h2>Employee Record Update is Open</h2>
                    <p><strong>Campaign:</strong> <?php echo htmlspecialchars($activeCampaign['name']); ?></p>
                    <p><strong>Deadline:</strong> <?php echo date('d-m-Y H:i', strtotime($activeCampaign['end_at'])); ?></p>
                    <p><?php echo nl2br(htmlspecialchars($activeCampaign['instructions'] ?: 'Please update only the fields approved by HR.')); ?></p>
                </div>

                <form method="POST" class="record-form">
                    <input type="hidden" name="submit_update" value="1">
                    <div class="form-grid">
                        <?php foreach ($allowedFields as $field): ?>
                            <?php $fieldName = $field['field_name']; ?>
                            <div class="field-group">
                                <label for="field_<?php echo htmlspecialchars($fieldName); ?>"><?php echo htmlspecialchars($field['label']); ?></label>
                                <input id="field_<?php echo htmlspecialchars($fieldName); ?>" type="text" name="<?php echo htmlspecialchars($fieldName); ?>" value="<?php echo htmlspecialchars($employee[$fieldName] ?? ''); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:20px;">
                        <button type="submit" class="btn btn-primary">Update My Records</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
