<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();
require_once '../src/config/database.php';

$evidenceId = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];
$stmt = $db->prepare("SELECT evidence.*, assessment.appraisal_id, appraisal.employee_id, appraisal.released_at,
        employee_user.user_id AS employee_user_id, member.access_profile_key
    FROM performance_assessment_evidence evidence
    JOIN performance_assessments assessment ON assessment.organisation_id = evidence.organisation_id AND assessment.id = evidence.assessment_id
    JOIN performance_appraisals appraisal ON appraisal.organisation_id = assessment.organisation_id AND appraisal.id = assessment.appraisal_id
    JOIN employees employee_user ON employee_user.id = appraisal.employee_id
    JOIN performance_organisation_members member ON member.organisation_id = evidence.organisation_id AND member.user_id = ? AND member.status = 'active'
    WHERE evidence.id = ?
    LIMIT 1");
$stmt->bind_param('ii', $userId, $evidenceId);
$stmt->execute();
$evidence = $stmt->get_result()->fetch_assoc();
if (!$evidence) {
    http_response_code(404);
    exit('Evidence not found.');
}

$isEmployeeOwner = (int)$evidence['employee_id'] > 0 && (int)$evidence['employee_user_id'] === $userId && !empty($evidence['released_at']);
$isReviewer = false;
$reviewStmt = $db->prepare("SELECT task.id
    FROM performance_appraisal_tasks task
    JOIN performance_organisation_members reviewer ON reviewer.organisation_id = task.organisation_id AND reviewer.user_id = ? AND reviewer.status = 'active'
    WHERE task.organisation_id = ? AND task.appraisal_id = ?
        AND (task.reviewer_user_id = ? OR (task.reviewer_user_id IS NULL AND task.reviewer_access_profile_key = reviewer.access_profile_key))
    LIMIT 1");
$reviewStmt->bind_param('iiii', $userId, $evidence['organisation_id'], $evidence['appraisal_id'], $userId);
$reviewStmt->execute();
$isReviewer = (bool)$reviewStmt->get_result()->fetch_assoc();
$isHrMember = in_array($role, ['admin', 'hr'], true);
if (!$isEmployeeOwner && !$isReviewer && !$isHrMember) {
    http_response_code(403);
    exit('You are not authorised to view this evidence.');
}
if ($evidence['validation_status'] !== 'mime_checked') {
    http_response_code(403);
    exit('This evidence is not available for download.');
}

$projectRoot = realpath(dirname(__DIR__));
$storageRoot = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'performance-evidence');
$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$evidence['storage_path']);
$filePath = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . $relativePath);
$rootPrefix = $storageRoot ? rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if (!$projectRoot || !$storageRoot || !$filePath || strpos(strtolower($filePath), strtolower($rootPrefix)) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('Evidence file is unavailable.');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'];
if (!in_array($mimeType, $allowedTypes, true)) {
    http_response_code(415);
    exit('Evidence file type is not permitted.');
}
$downloadName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', basename((string)$evidence['original_name']));
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
exit;
