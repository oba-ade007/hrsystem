<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';

$employeeModel = new Employee($db);
$departmentModel = new Department($db);
$branchModel = new Branch($db);

$departmentId = $_GET['dept'] ?? null;
$employees = $employeeModel->getAllEmployees($departmentId);
$departments = $departmentModel->getAllDepartments();
$branches = $branchModel->getAllBranches();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - HR Software</title>
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
                    <li><a href="employee-data-update-campaigns.php"><i>🗂️</i> Update Campaigns</a></li>
                    <li><a href="hr-record-update-approvals.php"><i>✓</i> Record Approvals</a></li>
                    <li><a href="employee-amendments.php"><i>✎</i> Amendments</a></li>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr', 'unit_heads'])): ?>
                    <li><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr', 'account'])): ?>
                    <li><a href="payroll.php"><i>💰</i> Payroll</a></li>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <li><a href="users.php"><i>🔐</i> Users</a></li>
                    <?php endif; ?>
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
                    <h1>Employee Management</h1>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <a href="add-employee.php" class="btn btn-primary" style="padding: 12px 20px; display: inline-block; text-decoration: none;">+ Add New Employee</a>
                </div>
                
                <div class="card">
                    <h2>Filter by Department</h2>
                    <form method="GET" class="filter-form">
                        <select name="dept">
                            <option value="">All Departments</option>
                            <?php foreach($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $departmentId == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2>Employees (<?php echo count($employees); ?>)</h2>
                    <?php if (isset($_GET['promoted'])): ?>
                    <div style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                        ✓ Employee promoted to next grade level successfully!
                    </div>
                    <?php endif; ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Position</th>
                                <th>Grade Level</th>
                                <th>Department</th>
                                <th>Branch</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="<?php echo $_SESSION['user_role'] === 'admin' ? '10' : '9'; ?>" class="text-center">No employees found</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($employees as $emp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                    <td>
                                        <span class="badge badge-grade" style="background-color: #3498db; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                            <?php echo htmlspecialchars($emp['grade_level'] ?? 'Level 1'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                    <td><?php 
                                        // Find branch name from branches array
                                        $branchName = 'N/A';
                                        if (!empty($emp['branch_id'])) {
                                            foreach($branches as $branch) {
                                                if ($branch['id'] == $emp['branch_id']) {
                                                    $branchName = $branch['name'];
                                                    break;
                                                }
                                            }
                                        }
                                        echo htmlspecialchars($branchName);
                                    ?></td>
                                    <td>
                                        <?php 
                                            $roleLabel = ucfirst($emp['role']);
                                            $roleBadgeClass = $emp['role'] === 'admin' ? 'badge-danger' : 'badge-info';
                                        ?>
                                        <span class="badge <?php echo $roleBadgeClass; ?>"><?php echo $roleLabel; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($emp['phone'] ?? '-'); ?></td>
                                    <td><span class="badge badge-<?php echo $emp['status']; ?>"><?php echo ucfirst($emp['status']); ?></span></td>
                                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <td style="display: flex; gap: 8px; align-items: center;">
                                        <a href="edit-employee.php?id=<?php echo $emp['id']; ?>" class="btn-small btn-primary" style="padding: 6px 12px; text-decoration: none; font-size: 12px; white-space: nowrap;">Edit</a>
                                        <?php 
                                            $currentGrade = $emp['grade_level'] ?? 'Level 1';
                                            preg_match('/Level (\d+)/', $currentGrade, $matches);
                                            $currentLevel = isset($matches[1]) ? (int)$matches[1] : 1;
                                        ?>
                                        <form method="POST" action="promote-employee.php" style="display: inline; margin: 0; padding: 0;">
                                            <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                                            <select name="level" style="padding: 6px 8px; font-size: 12px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; background-color: white;" onchange="if(this.value && this.value != '<?php echo $currentLevel; ?>') { if(confirm('Change grade level from <?php echo $currentLevel; ?> to ' + this.value + '?')) { this.form.submit(); } else { this.value = ''; } }">
                                                <option value="">Change Level...</option>
                                                <option value="1" <?php echo $currentLevel == 1 ? 'disabled' : ''; ?>>Level 1</option>
                                                <option value="2" <?php echo $currentLevel == 2 ? 'disabled' : ''; ?>>Level 2</option>
                                                <option value="3" <?php echo $currentLevel == 3 ? 'disabled' : ''; ?>>Level 3</option>
                                                <option value="4" <?php echo $currentLevel == 4 ? 'disabled' : ''; ?>>Level 4</option>
                                                <option value="5" <?php echo $currentLevel == 5 ? 'disabled' : ''; ?>>Level 5</option>
                                            </select>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>
