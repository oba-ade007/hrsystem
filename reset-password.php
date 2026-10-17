<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin']);

require_once '../src/config/database.php';
require_once '../src/models/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_POST['user_id'] ?? null;

$userModel = new User($db);

if ($action === 'reset') {
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    // Get the user to be reset
    $user = $userModel->getUserById($userId);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Generate temporary password
    $tempPassword = $userModel->generateTemporaryPassword(12);
    
    // Update user password
    if ($userModel->resetUserPassword($userId, $tempPassword)) {
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully',
            'temporary_password' => $tempPassword,
            'email' => $user['email'],
            'full_name' => $user['full_name']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
