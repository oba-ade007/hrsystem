<?php
session_start();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../src/config/database.php';
    require_once '../src/models/User.php';
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $userModel = new User($db);
        $result = $userModel->authenticate($email, $password);
        
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['email'] = $result['email'];
            $_SESSION['full_name'] = $result['full_name'];
            $_SESSION['user_role'] = $result['role'];
            
            // Log the login
            $db->query("
                INSERT INTO activity_logs (user_id, action, description, module, ip_address) 
                VALUES ({$result['user_id']}, 'LOGIN', 'User logged in', 'Authentication', '{$_SERVER['REMOTE_ADDR']}')
            ");
            
            // If user must change password on first login, redirect to change-password page
            if (!empty($result['must_change_password'])) {
                header('Location: change-password.php');
                exit;
            }

            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Software - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>HR Software</h1>
                <p>Employee Management System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="icon">✕</i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>Demo Credentials:</p>
                <p><strong>Email:</strong> admin@hr.com</p>
                <p><strong>Password:</strong> admin123</p>
                <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">
                <p style="margin-top: 15px; text-align: center;">
                    <a href="register-admin.php" style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 13px;">
                        + Register New Admin Account
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
