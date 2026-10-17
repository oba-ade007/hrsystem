<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/LeaveWorkflow.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';
require_once '../src/models/User.php';

$db = $db;
$workflowModel = new LeaveWorkflow($db);
$departmentModel = new Department($db);
$branchModel = new Branch($db);
$userModel = new User($db);

$allRules = $workflowModel->getAllRules();
$departments = $departmentModel->getAllDepartments();
$branches = $branchModel->getAllBranches();
$approvers = $userModel->getAllUsers();
$leaveTypes = $db->query("SELECT * FROM leave_types ORDER BY name");
$leaveTypeList = $leaveTypes ? $leaveTypes->fetch_all(MYSQLI_ASSOC) : [];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $steps = [];
    $stepOrders = $_POST['step_order'] ?? [];
    $roleKeys = $_POST['approver_role'] ?? [];
    $userKeys = $_POST['approver_user_id'] ?? [];
    $notes = $_POST['notes'] ?? [];

    foreach ($stepOrders as $index => $order) {
        if (!isset($roleKeys[$index])) {
            continue;
        }

        $steps[] = [
            'step_order' => (int)$order,
            'approver_role' => $roleKeys[$index],
            'approver_user_id' => $userKeys[$index] ?? null,
            'notes' => $notes[$index] ?? null,
        ];
    }

    $workflowId = $workflowModel->createRule($_POST, $steps);
    if ($workflowId > 0) {
        $message = 'Leave workflow rule saved successfully.';
    } else {
        $message = 'Unable to save leave workflow rule.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Setup - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="wrapper">
    <aside class="sidebar">
        <div class="brand"><h2>HR System</h2></div>
        <nav class="nav-menu">
            <ul>
                <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                <li><a href="employees.php"><i>👥</i> Employees</a></li>
                <li><a href="departments.php"><i>🏢</i> Departments</a></li>
                <li><a href="employee-data-update-campaigns.php"><i>🗂️</i> Update Campaigns</a></li>
                <li><a href="hr-record-update-approvals.php"><i>✓</i> Record Approvals</a></li>
                <li><a href="leave-approvals.php"><i>✓</i> Leave Approvals</a></li>
                <li class="active"><a href="leave-setup.php"><i>⚙️</i> Leave Setup</a></li>
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
                <h1>Leave Setup</h1>
                <p>Configure flexible approval rules by leave type, employee, department, or branch.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Create Workflow Rule</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="leave_type_id">Leave Type</label>
                            <select name="leave_type_id" id="leave_type_id" required>
                                <option value="">Select leave type</option>
                                <?php foreach ($leaveTypeList as $type): ?>
                                    <option value="<?php echo (int)$type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="name">Rule Name</label>
                            <input type="text" name="name" id="name" placeholder="Annual Leave HR Approval" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="employee_id">Employee (optional)</label>
                            <select name="employee_id" id="employee_id">
                                <option value="">Any employee</option>
                                <?php
                                $employees = $db->query("SELECT e.id, u.full_name FROM employees e JOIN users u ON u.id = e.user_id ORDER BY u.full_name");
                                if ($employees):
                                    foreach ($employees->fetch_all(MYSQLI_ASSOC) as $employee):
                                ?>
                                        <option value="<?php echo (int)$employee['id']; ?>"><?php echo htmlspecialchars($employee['full_name']); ?></option>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="department_id">Department (optional)</label>
                            <select name="department_id" id="department_id">
                                <option value="">Any department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo (int)$department['id']; ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="branch_id">Branch (optional)</label>
                            <select name="branch_id" id="branch_id">
                                <option value="">Any branch</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo (int)$branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="designation">Designation (optional)</label>
                            <input type="text" name="designation" id="designation" placeholder="Senior Officer">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="grade_level">Grade Level (optional)</label>
                            <select name="grade_level" id="grade_level">
                                <option value="">Any grade</option>
                                <option>Level 1</option>
                                <option>Level 2</option>
                                <option>Level 3</option>
                                <option>Level 4</option>
                                <option>Level 5</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reporting_manager_user_id">Reporting Manager (optional)</label>
                            <select name="reporting_manager_user_id" id="reporting_manager_user_id">
                                <option value="">Any manager</option>
                                <?php foreach ($approvers as $approver): ?>
                                    <option value="<?php echo (int)$approver['id']; ?>"><?php echo htmlspecialchars($approver['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><input type="checkbox" name="is_default" value="1"> Mark as default rule for this leave type</label>
                    </div>

                    <h3>Approval Steps</h3>
                    <div id="workflow-steps">
                        <div class="workflow-step-card">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Step Order</label>
                                    <input type="number" name="step_order[]" min="1" value="1" required>
                                </div>
                                <div class="form-group">
                                    <label>Approver Role</label>
                                    <select name="approver_role[]" required>
                                        <option value="admin">Admin</option>
                                        <option value="hr">HR</option>
                                        <option value="unit_heads">Unit Head</option>
                                        <option value="account">Account</option>
                                        <option value="employee">Employee</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Specific User (optional)</label>
                                    <select name="approver_user_id[]">
                                        <option value="">Any matching user</option>
                                        <?php foreach ($approvers as $approver): ?>
                                            <option value="<?php echo (int)$approver['id']; ?>"><?php echo htmlspecialchars($approver['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <input type="text" name="notes[]" placeholder="Needed for payroll and leave review">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" class="btn btn-secondary" onclick="addWorkflowStep()">Add Approval Step</button>
                        <button type="submit" class="btn btn-primary">Save Workflow</button>
                    </div>
                </form>
            </div>

            <div class="card" style="margin-top: 30px;">
                <h2>Configured Workflow Rules</h2>
                <table class="table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Leave Type</th>
                        <th>Scope</th>
                        <th>Default</th>
                        <th>Steps</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($allRules)): ?>
                        <tr><td colspan="5" class="text-center">No workflow rules configured yet.</td></tr>
                    <?php else: foreach ($allRules as $rule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rule['name']); ?></td>
                            <td><?php echo htmlspecialchars($rule['leave_type_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $scopeParts = [];
                                if (!empty($rule['employee_id'])) $scopeParts[] = 'Employee';
                                if (!empty($rule['department_id'])) $scopeParts[] = 'Department';
                                if (!empty($rule['branch_id'])) $scopeParts[] = 'Branch';
                                if (!empty($rule['designation'])) $scopeParts[] = 'Designation';
                                if (!empty($rule['grade_level'])) $scopeParts[] = 'Grade';
                                if (!empty($rule['reporting_manager_user_id'])) $scopeParts[] = 'Manager';
                                echo htmlspecialchars(!empty($scopeParts) ? implode(', ', $scopeParts) : 'All employees');
                                ?>
                            </td>
                            <td><?php echo !empty($rule['is_default']) ? 'Yes' : 'No'; ?></td>
                            <td><?php
                                $steps = $workflowModel->getWorkflowSteps((int)$rule['id']);
                                echo count($steps);
                                ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function addWorkflowStep() {
    const container = document.getElementById('workflow-steps');
    const stepCount = container.children.length + 1;
    const step = document.createElement('div');
    step.className = 'workflow-step-card';
    step.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label>Step Order</label>
                <input type="number" name="step_order[]" min="1" value="${stepCount}" required>
            </div>
            <div class="form-group">
                <label>Approver Role</label>
                <select name="approver_role[]" required>
                    <option value="admin">Admin</option>
                    <option value="hr">HR</option>
                    <option value="unit_heads">Unit Head</option>
                    <option value="account">Account</option>
                    <option value="employee">Employee</option>
                </select>
            </div>
            <div class="form-group">
                <label>Specific User (optional)</label>
                <select name="approver_user_id[]">
                    <option value="">Any matching user</option>
                    <?php foreach ($approvers as $approver): ?>
                        <option value="<?php echo (int)$approver['id']; ?>"><?php echo htmlspecialchars($approver['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <input type="text" name="notes[]" placeholder="Needed for payroll and leave review">
        </div>
    `;
    container.appendChild(step);
}
</script>
</body>
</html>
