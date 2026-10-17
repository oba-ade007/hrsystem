<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';
require_once '../src/models/Leave.php';

$user_id = $_SESSION['user_id'];
$employeeModel = new Employee($db);
$employee = $employeeModel->getEmployeeByUserId($user_id);
$leaveModel = new Leave($db);
$leaveTypes = $leaveModel->getLeaveTypes();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $leaveTypeId = $_POST['leave_type_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (!empty($startDate) && !empty($endDate) && !empty($leaveTypeId)) {
        if ($leaveModel->applyLeave($employee['id'], $leaveTypeId, $startDate, $endDate, $reason)) {
            $message = 'Leave application submitted successfully!';
        } else {
            $message = 'Unable to submit leave application.';
        }
    }
}

$myLeaves = $leaveModel->getLeavesByEmployee($employee['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Leave - HR Software</title>
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
                    <li class="active"><a href="apply-leave.php"><i>🏖️</i> Apply Leave</a></li>
                    <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
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
                    <h1>Apply for Leave</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <div class="form-card">
                    <h2>New Leave Request</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="leave_type_id">Leave Type:</label>
                            <select id="leave_type_id" name="leave_type_id" required>
                                <option value="">Select Leave Type</option>
                                <?php foreach($leaveTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['total_days']; ?> days)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date:</label>
                                <input type="date" id="start_date" name="start_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date:</label>
                                <input type="date" id="end_date" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reason">Reason:</label>
                            <textarea id="reason" name="reason" rows="4" placeholder="Provide reason for leave..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                    </form>
                </div>
                
                <div class="card" style="margin-top: 30px;">
                    <h2>My Leave Applications</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Applied On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myLeaves)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No leave applications yet</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($myLeaves as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($leave['start_date'])); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($leave['end_date'])); ?></td>
                                    <td><span class="badge badge-<?php echo $leave['status']; ?>"><?php echo ucfirst($leave['status']); ?></span></td>
                                    <td><?php echo date('d-m-Y', strtotime($leave['created_at'])); ?></td>
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
