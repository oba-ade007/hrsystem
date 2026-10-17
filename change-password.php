<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../src/config/database.php';
require_once '../src/models/User.php';

$userModel = new User($db);
$error = '';
$success = '';

// Ensure user still has must_change_password set (optional check)
$user = $userModel->getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($new) || empty($confirm)) {
        $error = 'Please fill all fields';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match';
    } elseif (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        $changed = $userModel->changeUserPassword($_SESSION['user_id'], $new);
        if ($changed) {
            $success = 'Password changed successfully. Redirecting...';
            // Log the password change
            $db->query("INSERT INTO activity_logs (user_id, action, description, module, ip_address) VALUES ({$_SESSION['user_id']}, 'PASSWORD_CHANGE', 'User changed password', 'Authentication', '{$_SERVER['REMOTE_ADDR']}')");
            header('Refresh:2; url=dashboard.php');
        } else {
            $error = 'Failed to change password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="change-password-page">
    <div class="container">
        <div class="box">
            <h2>Change Your Password</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                </div>

                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>
