<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';
require_once '../src/models/User.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';

$employeeModel = new Employee($db);
$userModel = new User($db);
$departmentModel = new Department($db);
$branchModel = new Branch($db);

$employeeId = $_GET['id'] ?? null;
if (!$employeeId) {
    header('Location: employees.php');
    exit;
}

// Get employee details
$employee = $employeeModel->getEmployeeById($employeeId);
if (!$employee) {
    header('Location: employees.php');
    exit;
}

// Check access: Admin/HR can edit any employee, regular users can only edit themselves
$isAdmin = $_SESSION['user_role'] === 'admin';
$isHR = $_SESSION['user_role'] === 'hr';
$isEmployee = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['employee', 'user'], true);
$isOwnRecord = $employee['user_id'] === $_SESSION['user_id'];

if (!$isAdmin && !$isHR && !($isEmployee && $isOwnRecord)) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access Denied. You don't have permission to edit this employee record.";
    exit;
}

// Get departments and branches
$departments = [];
$deptResult = $db->getConnection()->query("SELECT id, name FROM departments ORDER BY name");
if ($deptResult && $deptResult->num_rows > 0) {
    $departments = $deptResult->fetch_all(MYSQLI_ASSOC);
}

$branches = [];
$branchResult = $db->getConnection()->query("SELECT id, name FROM branches ORDER BY name");
if ($branchResult && $branchResult->num_rows > 0) {
    $branches = $branchResult->fetch_all(MYSQLI_ASSOC);
}

$message = '';
$error = '';

// Initialize form variables
$fullName = $employee['full_name'];
$email = $employee['email'];
$position = $employee['position'];
$departmentId = $employee['department_id'];
$branchId = $employee['branch_id'] ?? '';
$gradeLevel = $employee['grade_level'] ?? 'Level 1';
$salary = $employee['salary'] ?? 0;
$joiningDate = $employee['joining_date'];
$phone = $employee['phone'] ?? '';
$address = $employee['address'] ?? '';
$qualifications = $employee['qualifications'] ?? '';
$credentialsFile = $employee['credentials_file'] ?? '';
$credentialsArray = [];

// Parse credentials JSON if exists
if (!empty($credentialsFile)) {
    $decoded = json_decode($credentialsFile, true);
    if (is_array($decoded)) {
        $credentialsArray = $decoded;
    }
}

$role = $employee['role'] ?? 'employee';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $position = $_POST['position'] ?? '';
    $departmentId = $_POST['department_id'] ?? '';
    $branchId = $_POST['branch_id'] ?? '';
    $gradeLevel = $_POST['grade_level'] ?? 'Level 1';
    $salary = $_POST['salary'] ?? 0;
    $joiningDate = $_POST['joining_date'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $qualifications = $_POST['qualifications'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $newCredentialsArray = $credentialsArray; // Keep existing credentials by default
    
    if (!$fullName || !$email || !$position || !$departmentId || !$joiningDate) {
        $error = 'All required fields must be filled';
    } else {
        // Handle multiple file uploads
        $uploadDir = __DIR__ . '/credentials/';
        $credentialNames = $_POST['credential_name'] ?? [];
        $credentialFiles = $_FILES['credential_file'] ?? [];
        $credentialsToDelete = $_POST['credentials_to_delete'] ?? [];
        
        // Ensure arrays are properly structured
        if (!is_array($credentialNames)) {
            $credentialNames = [$credentialNames];
        }
        
        // Delete requested credentials
        foreach ($credentialsToDelete as $index) {
            if (isset($credentialsArray[$index])) {
                $oldFilePath = __DIR__ . '/' . $credentialsArray[$index]['file'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
                unset($credentialsArray[$index]);
            }
        }
        $credentialsArray = array_values($credentialsArray); // Re-index array
        $newCredentialsArray = $credentialsArray;
        
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
                        $newCredentialsArray[] = [
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
            // Check if this is an employee updating their own record
            if ($isEmployee && $isOwnRecord) {
                // Save amendment request for approval (employees can only edit contact info)
                $amendmentStmt = $db->prepare("
                    INSERT INTO employee_amendments 
                    (employee_id, requested_by, phone, address, city, state, zip_code, emergency_contact, emergency_contact_phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if (!$amendmentStmt) {
                    $error = 'Database error: ' . $db->error . '. Please ensure the database schema is up to date.';
                } else {
                    $city = $_POST['city'] ?? '';
                    $state = $_POST['state'] ?? '';
                    $zipCode = $_POST['zip_code'] ?? '';
                    $emergencyContact = $_POST['emergency_contact'] ?? '';
                    $emergencyContactPhone = $_POST['emergency_contact_phone'] ?? '';
                    
                    $amendmentStmt->bind_param(
                        'iisssssss',
                        $employee['id'],
                        $_SESSION['user_id'],
                        $phone,
                        $address,
                        $city,
                        $state,
                        $zipCode,
                        $emergencyContact,
                        $emergencyContactPhone
                    );
                    
                    if ($amendmentStmt->execute()) {
                        $message = 'Your changes have been submitted for HR approval. You will be notified once approved.';
                    } else {
                        $error = 'Failed to submit amendment request: ' . $amendmentStmt->error;
                    }
                }
            } else {
                // HR/Admin can update directly without approval
                // Update user table with role
                $userUpdateStmt = $db->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, role = ? 
                    WHERE id = ?
                ");
                $userUpdateStmt->bind_param('sssi', $fullName, $email, $role, $employee['user_id']);
                
                if ($userUpdateStmt->execute()) {
                    // Update employee table - store credentials as JSON
                    $credentialsJson = !empty($newCredentialsArray) ? json_encode($newCredentialsArray) : '';
                    
                    // Update employee table
                    $employeeData = [
                        'position' => $position,
                        'department_id' => $departmentId,
                        'branch_id' => $branchId,
                        'grade_level' => $gradeLevel,
                        'salary' => $salary,
                        'joining_date' => $joiningDate,
                        'phone' => $phone,
                        'address' => $address,
                        'qualifications' => $qualifications,
                        'credentials_file' => $credentialsJson
                    ];
                    
                    if ($employeeModel->updateEmployee($employeeId, $employeeData)) {
                        $message = 'Employee updated successfully!';
                        // Refresh employee data
                        $employee = $employeeModel->getEmployeeById($employeeId);
                        $fullName = $employee['full_name'];
                        $email = $employee['email'];
                        $position = $employee['position'];
                        $departmentId = $employee['department_id'];
                        $branchId = $employee['branch_id'] ?? '';
                        $gradeLevel = $employee['grade_level'] ?? 'Level 1';
                        $salary = $employee['salary'] ?? 0;
                        $joiningDate = $employee['joining_date'];
                        $phone = $employee['phone'] ?? '';
                    $address = $employee['address'] ?? '';
                    $qualifications = $employee['qualifications'] ?? '';
                    $credentialsFile = $employee['credentials_file'] ?? '';
                    $credentialsArray = [];
                    if (!empty($credentialsFile)) {
                        $decoded = json_decode($credentialsFile, true);
                        if (is_array($decoded)) {
                            $credentialsArray = $decoded;
                        }
                    }
                    $role = $employee['role'] ?? 'employee';
                    } else {
                        $error = 'Failed to update employee record';
                    }
                } else {
                    $error = 'Failed to update user information';
                }
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
    <title>Edit Employee - HR Software</title>
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
                    <h1>Edit Employee</h1>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="form-card">
                    <?php if ($isEmployee && $isOwnRecord): ?>
                    <div class="alert alert-info" style="margin-bottom: 20px; background-color: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; border: 1px solid #bee5eb;">
                        ℹ️ You can only edit your personal contact information. Other fields are managed by HR.
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($fullName); ?>" <?php echo ($isEmployee && $isOwnRecord) ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" <?php echo ($isEmployee && $isOwnRecord) ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department_id">Department *</label>
                                <select id="department_id" name="department_id" required <?php echo ($isEmployee && $isOwnRecord) ? 'disabled' : ''; ?>>
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $departmentId == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isEmployee && $isOwnRecord): ?>
                                <input type="hidden" name="department_id" value="<?php echo htmlspecialchars($departmentId); ?>">
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="branch_id">Branch</label>
                                <select id="branch_id" name="branch_id" <?php echo ($isEmployee && $isOwnRecord) ? 'disabled' : ''; ?>>
                                    <option value="">Select Branch</option>
                                    <?php foreach($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>" <?php echo $branchId == $branch['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isEmployee && $isOwnRecord): ?>
                                <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($branchId); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="position">Position *</label>
                                <input type="text" id="position" name="position" required value="<?php echo htmlspecialchars($position); ?>" <?php echo ($isEmployee && $isOwnRecord) ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="grade_level">Grade Level *</label>
                                <select id="grade_level" name="grade_level" required <?php echo ($isEmployee && $isOwnRecord) ? 'disabled' : ''; ?>>
                                    <option value="Level 1" <?php echo $gradeLevel === 'Level 1' ? 'selected' : ''; ?>>Level 1</option>
                                    <option value="Level 2" <?php echo $gradeLevel === 'Level 2' ? 'selected' : ''; ?>>Level 2</option>
                                    <option value="Level 3" <?php echo $gradeLevel === 'Level 3' ? 'selected' : ''; ?>>Level 3</option>
                                    <option value="Level 4" <?php echo $gradeLevel === 'Level 4' ? 'selected' : ''; ?>>Level 4</option>
                                    <option value="Level 5" <?php echo $gradeLevel === 'Level 5' ? 'selected' : ''; ?>>Level 5</option>
                                </select>
                                <?php if ($isEmployee && $isOwnRecord): ?>
                                <input type="hidden" name="grade_level" value="<?php echo htmlspecialchars($gradeLevel); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="role">User Privileges *</label>
                                <select id="role" name="role" required <?php echo ($isEmployee && $isOwnRecord) ? 'disabled' : ''; ?>>
                                    <option value="employee" <?php echo $role === 'employee' ? 'selected' : ''; ?>>Employee (Regular User)</option>
                                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Employee (Regular User - Alternate)</option>
                                    <option value="hr" <?php echo $role === 'hr' ? 'selected' : ''; ?>>HR</option>
                                    <option value="account" <?php echo $role === 'account' ? 'selected' : ''; ?>>Account</option>
                                    <option value="unit_heads" <?php echo $role === 'unit_heads' ? 'selected' : ''; ?>>Unit heads</option>
                                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin (Full Access)</option>
                                </select>
                                <?php if ($isEmployee && $isOwnRecord): ?>
                                <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="joining_date">Joining Date *</label>
                                <input type="date" id="joining_date" name="joining_date" required value="<?php echo htmlspecialchars($joiningDate); ?>" <?php echo ($isEmployee && $isOwnRecord) ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="salary">Salary</label>
                                <input type="number" id="salary" name="salary" step="0.01" value="<?php echo htmlspecialchars($salary); ?>" <?php echo ($isEmployee && $isOwnRecord) ? 'readonly' : ''; ?>>
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
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($employee['city'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="state">State</label>
                                <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($employee['state'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="zip_code">Zip Code</label>
                                <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($employee['zip_code'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact</label>
                                <input type="text" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($employee['emergency_contact'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="qualifications">Qualifications</label>
                            <textarea id="qualifications" name="qualifications" rows="4" placeholder="e.g., B.Sc. Computer Science, MBA Finance, etc." <?php echo ($isEmployee && $isOwnRecord) ? 'readonly' : ''; ?>><?php echo htmlspecialchars($qualifications); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <label style="margin: 0;">Credentials & Documents</label>
                                <button type="button" class="btn btn-small-add" id="addCredentialBtn">+ Add More</button>
                            </div>
                            
                            <?php if (!empty($credentialsArray)): ?>
                            <div style="margin-bottom: 15px; padding: 10px; background-color: #f0f0f0; border-radius: 4px;">
                                <strong>Current Credentials:</strong>
                                <div style="margin-top: 8px;">
                                    <?php foreach ($credentialsArray as $idx => $cred): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background-color: white; border-radius: 4px; margin-bottom: 5px;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($cred['name']); ?>:</strong> 
                                            <a href="<?php echo htmlspecialchars($cred['file']); ?>" target="_blank" class="link-btn">📄 View</a>
                                        </div>
                                        <label style="display: flex; align-items: center; gap: 5px; margin: 0; cursor: pointer;">
                                            <input type="checkbox" name="credentials_to_delete[]" value="<?php echo $idx; ?>" style="cursor: pointer;">
                                            <span style="font-size: 12px; color: #e74c3c;">Delete</span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div id="credentialsContainer">
                                <div class="credential-row" style="display: grid; grid-template-columns: 1fr 1.5fr auto; gap: 10px; margin-bottom: 15px; align-items: flex-end;">
                                    <input type="text" name="credential_name[]" class="credential-name" placeholder="e.g., Diploma, Certification, License" style="padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px;">
                                    <input type="file" name="credential_file[]" class="credential-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                                    <button type="button" class="btn-remove-cred" style="padding: 8px 12px; background-color: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">✕</button>
                                </div>
                            </div>
                            <small style="color: #666; display: block; margin-top: 5px;">Add new credentials - Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Max 5MB each)</small>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">Update Employee</button>
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
