<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';
require_once '../src/models/User.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';

$employeeModel = new Employee($db);
$userModel = new User($db);
$departmentModel = new Department($db);
$branchModel = new Branch($db);

// Get departments and branches - direct database query
$departments = [];
$branches = [];

// Query departments directly
$deptResult = $db->getConnection()->query("SELECT id, name FROM departments ORDER BY name");
if ($deptResult && $deptResult->num_rows > 0) {
    $departments = $deptResult->fetch_all(MYSQLI_ASSOC);
}

// Query branches directly
$branchResult = $db->getConnection()->query("SELECT id, name FROM branches ORDER BY name");
if ($branchResult && $branchResult->num_rows > 0) {
    $branches = $branchResult->fetch_all(MYSQLI_ASSOC);
}

$message = '';
$error = '';

// Initialize form variables
$fullName = '';
$email = '';
$password = '';
$employeeId = '';
$departmentId = '';
$branchId = '';
$position = '';
$gradeLevel = 'Level 1';
$salary = '';
$joiningDate = '';
$phone = '';
$address = '';
$qualifications = '';
$role = 'employee';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $employeeId = $_POST['employee_id'] ?? '';
    $departmentId = $_POST['department_id'] ?? '';
    $branchId = $_POST['branch_id'] ?? '';
    $position = $_POST['position'] ?? '';
    $gradeLevel = $_POST['grade_level'] ?? 'Level 1';
    $salary = $_POST['salary'] ?? 0;
    $joiningDate = $_POST['joining_date'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $qualifications = $_POST['qualifications'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $credentialsData = []; // Store multiple credentials
    
    if (!$fullName || !$email || !$password || !$employeeId || !$departmentId || !$position || !$joiningDate) {
        $error = 'All required fields must be filled';
    } elseif ($userModel->userExists($email)) {
        $error = 'Email already exists';
    } else {
        // Handle multiple file uploads
        $uploadDir = __DIR__ . '/credentials/';
        $credentialNames = $_POST['credential_name'] ?? [];
        $credentialFiles = $_FILES['credential_file'] ?? [];
        
        // Ensure arrays are properly structured
        if (!is_array($credentialNames)) {
            $credentialNames = [$credentialNames];
        }
        
        // Process each credential
        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        foreach ($credentialNames as $index => $credName) {
            if (empty($credName)) continue;
            
            // Check if file exists for this credential
            if (isset($credentialFiles['error'][$index]) && $credentialFiles['error'][$index] === UPLOAD_ERR_OK) {
                $fileExt = pathinfo($credentialFiles['name'][$index], PATHINFO_EXTENSION);
                $fileSize = $credentialFiles['size'][$index];
                
                if (!in_array(strtolower($fileExt), $allowedExts)) {
                    $error = "Invalid file type for '$credName'. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG";
                    break;
                } elseif ($fileSize > $maxFileSize) {
                    $error = "File size for '$credName' exceeds 5MB limit";
                    break;
                } else {
                    // Generate unique filename
                    $newFileName = 'cred_' . $employeeId . '_' . $index . '_' . time() . '.' . $fileExt;
                    $uploadPath = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($credentialFiles['tmp_name'][$index], $uploadPath)) {
                        $credentialsData[] = [
                            'name' => $credName,
                            'file' => 'credentials/' . $newFileName
                        ];
                    } else {
                        $error = "Failed to upload credentials file for '$credName'";
                        break;
                    }
                }
            }
        }
        
        // Only proceed if no errors
        if (!$error) {
            // Create user account first with specified role
            $userId = $userModel->createUser($email, $password, $fullName, $role);
            
            if ($userId) {
                // Create employee record - store credentials as JSON
                $credentialsJson = !empty($credentialsData) ? json_encode($credentialsData) : '';
                
                $employeeData = [
                    'salary' => $salary,
                    'phone' => $phone,
                    'address' => $address,
                    'grade_level' => $gradeLevel,
                    'qualifications' => $qualifications,
                    'credentials_file' => $credentialsJson
                ];
                
                if ($employeeModel->createEmployeeWithBranch($userId, $employeeId, $departmentId, $branchId, $position, $joiningDate, $employeeData)) {
                    $message = 'Employee created successfully!';
                    // Clear form
                    $fullName = '';
                    $email = '';
                    $password = '';
                    $employeeId = '';
                    $departmentId = '';
                    $branchId = '';
                    $position = '';
                    $salary = '';
                    $joiningDate = '';
                    $phone = '';
                    $address = '';
                    $gradeLevel = 'Level 1';
                    $qualifications = '';
                    $role = 'employee';
                } else {
                    $error = 'Failed to create employee record';
                }
            } else {
                $error = 'Failed to create user account';
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
    <title>Add Employee - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand">
                <h2>HR System</h2>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li><a href="attendance.php"><i>📋</i> Attendance</a></li>
                    <li class="active"><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
                    <li class="menu-header">ACCOUNT</li>
                    <li><a href="logout.php"><i>🚪</i> Logout</a></li>
                </ul>
            </nav>
        </aside>
        
        <main class="content">
            <div class="topbar">
                <div class="user-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                </div>
            </div>
            
            <div class="container">
                <div class="page-header">
                    <h1>Add New Employee</h1>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="form-card">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($fullName); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" required placeholder="Min 6 characters">
                            </div>
                            
                            <div class="form-group">
                                <label for="employee_id">Employee ID *</label>
                                <input type="text" id="employee_id" name="employee_id" required placeholder="e.g., EMP001" value="<?php echo htmlspecialchars($employeeId); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department_id">Department *</label>
                                <select id="department_id" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $departmentId == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="branch_id">Branch</label>
                                <select id="branch_id" name="branch_id">
                                    <option value="">Select Branch</option>
                                    <?php foreach($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>" <?php echo $branchId == $branch['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="position">Position *</label>
                                <input type="text" id="position" name="position" required value="<?php echo htmlspecialchars($position); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="grade_level">Grade Level *</label>
                                <select id="grade_level" name="grade_level" required>
                                    <option value="Level 1" <?php echo $gradeLevel === 'Level 1' ? 'selected' : ''; ?>>Level 1</option>
                                    <option value="Level 2" <?php echo $gradeLevel === 'Level 2' ? 'selected' : ''; ?>>Level 2</option>
                                    <option value="Level 3" <?php echo $gradeLevel === 'Level 3' ? 'selected' : ''; ?>>Level 3</option>
                                    <option value="Level 4" <?php echo $gradeLevel === 'Level 4' ? 'selected' : ''; ?>>Level 4</option>
                                    <option value="Level 5" <?php echo $gradeLevel === 'Level 5' ? 'selected' : ''; ?>>Level 5</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="role">User Privileges *</label>
                                <select id="role" name="role" required>
                                    <option value="employee" <?php echo $role === 'employee' ? 'selected' : ''; ?>>Employee (Regular User)</option>
                                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Employee (Regular User - Alternate)</option>
                                    <option value="hr" <?php echo $role === 'hr' ? 'selected' : ''; ?>>HR</option>
                                    <option value="account" <?php echo $role === 'account' ? 'selected' : ''; ?>>Account</option>
                                    <option value="unit_heads" <?php echo $role === 'unit_heads' ? 'selected' : ''; ?>>Unit heads</option>
                                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin (Full Access)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="salary">Salary</label>
                                <input type="number" id="salary" name="salary" step="0.01" value="<?php echo htmlspecialchars($salary); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            </div>
                            
                            <div class="form-group">
                                <label for="joining_date">Joining Date *</label>
                                <input type="date" id="joining_date" name="joining_date" required value="<?php echo htmlspecialchars($joiningDate); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="qualifications">Qualifications</label>
                            <textarea id="qualifications" name="qualifications" rows="4" placeholder="e.g., B.Sc. Computer Science, MBA Finance, etc."><?php echo htmlspecialchars($qualifications); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <label style="margin: 0;">Credentials & Documents</label>
                                <button type="button" class="btn btn-small-add" id="addCredentialBtn">+ Add More</button>
                            </div>
                            
                            <div id="credentialsContainer">
                                <div class="credential-row" style="display: grid; grid-template-columns: 1fr 1.5fr auto; gap: 10px; margin-bottom: 15px; align-items: flex-end;">
                                    <input type="text" name="credential_name[]" class="credential-name" placeholder="e.g., Diploma, Certification, License" style="padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px;">
                                    <input type="file" name="credential_file[]" class="credential-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                                    <button type="button" class="btn-remove-cred" style="padding: 8px 12px; background-color: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">✕</button>
                                </div>
                            </div>
                            <small style="color: #666; display: block; margin-top: 5px;">Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Max 5MB each)</small>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">Create Employee</button>
                            <a href="employees.php" class="btn btn-secondary" style="padding: 12px 24px; text-decoration: none; text-align: center;">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Handle adding multiple credentials
        document.getElementById('addCredentialBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const container = document.getElementById('credentialsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'credential-row';
            newRow.style.cssText = 'display: grid; grid-template-columns: 1fr 1.5fr auto; gap: 10px; margin-bottom: 15px; align-items: flex-end;';
            newRow.innerHTML = `
                <input type="text" name="credential_name[]" class="credential-name" placeholder="e.g., Diploma, Certification, License" style="padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px;">
                <input type="file" name="credential_file[]" class="credential-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                <button type="button" class="btn-remove-cred" style="padding: 8px 12px; background-color: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">✕</button>
            `;
            
            // Add remove functionality to new row
            newRow.querySelector('.btn-remove-cred').addEventListener('click', function(e) {
                e.preventDefault();
                newRow.remove();
            });
            
            container.appendChild(newRow);
        });
        
        // Remove credential row
        document.querySelectorAll('.btn-remove-cred').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                this.closest('.credential-row').remove();
            });
        });
    </script>
    
    <script src="js/script.js"></script>
</body>
</html>
