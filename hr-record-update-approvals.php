<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/EmployeeDataUpdateCampaign.php';

$campaignModel = new EmployeeDataUpdateCampaign($db);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rawSubmissionIds = $_POST['submission_ids'] ?? ($_POST['submission_id'] ?? []);
    $submissionIds = [];

    if (is_array($rawSubmissionIds)) {
        foreach ($rawSubmissionIds as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $submissionIds[] = $id;
            }
        }
    } else {
        $id = (int)$rawSubmissionIds;
        if ($id > 0) {
            $submissionIds[] = $id;
        }
    }

    $submissionIds = array_values(array_unique($submissionIds));

    if (!empty($submissionIds) && in_array($action, ['approve', 'reject', 'request_correction'], true)) {
        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $types = str_repeat('i', count($submissionIds));
        $sql = "SELECT * FROM employee_update_submissions WHERE id IN ($placeholders) AND approval_status = 'pending' ORDER BY created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$submissionIds);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($rows)) {
            $error = 'Submission not found or already processed.';
        } else {
            $reviewerId = (int)$_SESSION['user_id'];
            $reviewNotes = trim($_POST['review_notes'] ?? '');
            $db->begin_transaction();

            try {
                foreach ($rows as $submission) {
                    $employeeId = (int)$submission['employee_id'];
                    $campaignId = (int)$submission['campaign_id'];

                    if ($action === 'approve') {
                        $fieldName = $submission['field_name'];
                        $newValue = $submission['new_value'];
                        $setSql = "UPDATE employees SET `{$fieldName}` = ? WHERE id = ?";
                        $setStmt = $db->prepare($setSql);
                        $setStmt->bind_param('si', $newValue, $employeeId);
                        if (!$setStmt->execute()) {
                            throw new RuntimeException('Failed to update employee record for field: ' . $fieldName);
                        }
                    }

                    $approvalStatus = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
                    $reviewStmt = $db->prepare("UPDATE employee_update_submissions SET approval_status = ?, reviewer_id = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?");
                    $reviewStmt->bind_param('siis', $approvalStatus, $reviewerId, $reviewNotes, $submission['id']);
                    if (!$reviewStmt->execute()) {
                        throw new RuntimeException('Failed to apply review outcome for submission ID ' . (int)$submission['id']);
                    }

                    $historyStmt = $db->prepare("INSERT INTO employee_update_campaign_history (campaign_id, action, details, actor_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $details = 'Submission ' . $action . ' by ' . $_SESSION['full_name'] . ' for field ' . $submission['field_name'];
                    $historyAction = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'correction_requested');
                    $historyStmt->bind_param('issi', $campaignId, $historyAction, $details, $reviewerId);
                    if (!$historyStmt->execute()) {
                        throw new RuntimeException('Failed to log review history for submission ID ' . (int)$submission['id']);
                    }
                }

                $db->commit();
                $message = 'Submission(s) updated successfully.';
            } catch (Throwable $e) {
                $db->rollback();
                error_log('HR record update review failed. reviewer_id=' . $reviewerId . ', submission_ids=' . implode(',', $submissionIds) . ': ' . $e->getMessage());
                $error = 'We could not process the review action. Please try again or contact HR.';
            }
        }
    }
}

$submissions = $campaignModel->getPendingSubmissions();
$groupedSubmissions = [];
foreach ($submissions as $submission) {
    $groupKey = (int)$submission['campaign_id'] . ':' . (int)$submission['employee_id'];
    $groupedSubmissions[$groupKey][] = $submission;
}
$history = $campaignModel->getCampaignHistory();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Update Approvals</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .approval-card { border:1px solid #ddd; background:#fff; border-radius:8px; padding:18px; margin-bottom:18px; }
        .row { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        .badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:700; }
        .badge-pending { background:#fff3cd; color:#856404; }
        .badge-approved { background:#d4edda; color:#155724; }
        .badge-rejected { background:#f8d7da; color:#721c24; }
        textarea { width:100%; min-height:80px; border:1px solid #ccc; border-radius:6px; padding:10px; }
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
                <li><a href="employee-data-update-campaigns.php"><i>🗂️</i> Update Campaigns</a></li>
                <li class="active"><a href="hr-record-update-approvals.php"><i>✓</i> Record Approvals</a></li>
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
            <div class="page-header"><h1>Employee Record Update Approvals</h1></div>
            <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if (empty($groupedSubmissions)): ?>
                <div class="alert alert-info">No pending employee record update submissions.</div>
            <?php else: ?>
                <?php foreach ($groupedSubmissions as $group): ?>
                    <?php $firstSubmission = $group[0]; ?>
                    <div class="approval-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div>
                                <strong><?php echo htmlspecialchars($firstSubmission['full_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($firstSubmission['employee_id']); ?> | <?php echo htmlspecialchars($firstSubmission['department_name'] ?: 'N/A'); ?> | <?php echo htmlspecialchars($firstSubmission['branch_name'] ?: 'N/A'); ?></small>
                                <div style="margin-top: 4px;"><strong>Campaign:</strong> <?php echo htmlspecialchars($firstSubmission['campaign_name'] ?? 'N/A'); ?></div>
                            </div>
                            <span class="badge badge-pending">Pending</span>
                        </div>

                        <table class="table" style="margin-bottom:18px;">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group as $submission): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($submission['field_name']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['old_value'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($submission['new_value'] ?? ''); ?></td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($submission['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <form method="POST" style="margin-top:18px;">
                            <?php foreach ($group as $submission): ?>
                                <input type="hidden" name="submission_ids[]" value="<?php echo (int)$submission['id']; ?>">
                            <?php endforeach; ?>
                            <div class="field-group">
                                <label for="review_notes_<?php echo (int)$firstSubmission['employee_id']; ?>_<?php echo (int)$firstSubmission['campaign_id']; ?>">Review Notes</label>
                                <textarea id="review_notes_<?php echo (int)$firstSubmission['employee_id']; ?>_<?php echo (int)$firstSubmission['campaign_id']; ?>" name="review_notes" placeholder="Add approval, rejection or correction instructions"></textarea>
                            </div>
                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                                <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                                <button type="submit" name="action" value="request_correction" class="btn btn-warning">Request Correction</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="card" style="margin-top:30px;">
                <h2>Recent Activity</h2>
                <table class="table">
                    <thead>
                        <tr><th>Action</th><th>Details</th><th>Time</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="3">No activity yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['action']); ?></td>
                                    <td><?php echo htmlspecialchars($item['details']); ?></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($item['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
