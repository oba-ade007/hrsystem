<?php
session_start();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../src/config/database.php';
    require_once '../src/models/User.php';
    
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $adminSecret = $_POST['admin_secret'] ?? '';
    
    // Verify admin secret (security measure)
    $correctSecret = 'admin_setup_2024'; // Change this to your secure code
    
    if (!$fullName || !$email || !$password || !$confirmPassword) {
        $error = 'All fields are required';
    } elseif ($adminSecret !== $correctSecret) {
        $error = 'Invalid admin setup code';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        $userModel = new User($db);
        
        if ($userModel->userExists($email)) {
            $error = 'Email already registered';
        } else {
            $userId = $userModel->createUser($email, $password, $fullName, 'admin');
            
            if ($userId) {
                $success = 'Admin account created successfully! You can now login.';
                // Clear form
                $fullName = '';
                $email = '';
                $password = '';
                $confirmPassword = '';
            } else {
                $error = 'Failed to create admin account';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .register-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .register-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #999;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #999;
            font-size: 12px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="register-header">
                <h1>Create Admin Account</h1>
                <p>Register a new administrator</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="icon">✕</i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="icon">✓</i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required 
                           value="<?php echo htmlspecialchars($fullName); ?>" 
                           placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email); ?>" 
                           placeholder="Enter email address">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter password (min 6 characters)">
                    <small>Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm password">
                </div>
                
                <div class="form-group">
                    <label for="admin_secret">Admin Setup Code</label>
                    <input type="password" id="admin_secret" name="admin_secret" required 
                           placeholder="Enter admin setup code">
                    <small>Security verification code required</small>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                    <a href="login.php" class="btn btn-secondary">Back to Login</a>
                </div>
            </form>
            
            <div class="back-link">
                <p style="font-size: 12px; color: #999;">
                    Already have an account? <a href="login.php">Sign in here</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
