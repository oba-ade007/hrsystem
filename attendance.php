<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

require_once '../src/config/database.php';
require_once '../src/models/Employee.php';
require_once '../src/models/Attendance.php';

$userId = $_SESSION['user_id'];
$employeeModel = new Employee($db);
$employee = $employeeModel->getEmployeeByUserId($userId);

if (!$employee) {
    die('Employee profile not found. Please contact HR.');
}

$attendanceModel = new Attendance($db);
$branchId = (int)($employee['branch_id'] ?? 0);
$zones = $branchId ? $attendanceModel->getZonesByBranch($branchId) : [];
$qrCodes = $branchId ? $attendanceModel->getQrCodesByBranch($branchId) : [];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventType = $_POST['event_type'] ?? '';
    $token = trim($_POST['qr_token'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $accuracy = $_POST['accuracy'] ?? null;

    if (empty($eventType)) {
        $error = 'Please select an attendance action.';
    } elseif (empty($token)) {
        $error = 'Please scan or enter the QR code for this location.';
    } elseif (empty($latitude) || empty($longitude)) {
        $error = 'Location permission is required to record attendance.';
    } else {
        $qrCode = $attendanceModel->getActiveQrCodeByToken($token);
        if (!$qrCode) {
            $error = 'QR code has expired or is invalid. Please scan the current QR code.';
        } else {
            if ((int)$qrCode['branch_id'] !== $branchId) {
                $error = 'This QR code is not authorized for your assigned branch.';
            } else {
                $validation = $attendanceModel->validateLocation((float)$latitude, (float)$longitude, $qrCode);
                if (!$validation['valid']) {
                    $error = $validation['message'];
                } else {
                    $eventDate = date('Y-m-d');
                    $eventTime = date('Y-m-d H:i:s');
                    $eventRecord = [
                        'employee_id' => (int)$employee['id'],
                        'branch_id' => (int)$qrCode['branch_id'],
                        'zone_id' => (int)$qrCode['zone_id'],
                        'qr_code_id' => (int)$qrCode['id'],
                        'event_type' => $eventType,
                        'event_date' => $eventDate,
                        'event_time' => $eventTime,
                        'latitude' => (float)$latitude,
                        'longitude' => (float)$longitude,
                        'distance_meters' => $validation['distance_meters'],
                        'geofence_radius' => (float)$validation['allowed_radius'],
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                        'status' => 'valid',
                        'remarks' => 'Validated via GPS geofence and QR token.'
                    ];

                    if ($attendanceModel->recordEvent($eventRecord)) {
                        $success = ucfirst(str_replace('_', ' ', $eventType)) . ' recorded successfully. Distance from QR: ' . $validation['distance_meters'] . 'm.';
                    } else {
                        $error = 'Attendance could not be saved. Please try again.';
                    }
                }
            }
        }
    }
}

$recentEvents = $attendanceModel->getAttendanceByEmployee((int)$employee['id'], 10);
$todaySummary = $attendanceModel->getTodaySummary(date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .attendance-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-top: 20px; }
        .attendance-action { background: #f8fafc; border: 1px solid #dfe7f1; border-radius: 12px; padding: 16px; text-align: center; }
        .attendance-action button { width: 100%; padding: 16px 12px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .qr-selection { width: 100%; margin-top: 10px; }
        .location-warning { color: #b45309; font-size: 14px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand"><h2>HR System</h2></div>
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li class="active"><a href="attendance.php"><i>📋</i> Attendance</a></li>
                    <li><a href="apply-leave.php"><i>🏖️</i> Apply Leave</a></li>
                    <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr', 'unit_heads'], true)): ?>
                    <li><a href="attendance-setup.php"><i>⚙️</i> Attendance Setup</a></li>
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
                    <h1>Staff Attendance & Movement</h1>
                    <p>Use GPS and QR validation to record attendance and movement events securely.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2>Employee Attendance Actions</h2>
                    <div class="attendance-grid">
                        <?php
                        $actions = [
                            ['resumption', '🟢 RESUME'],
                            ['closing', '🔴 CLOSE'],
                            ['late_arrival', '⏰ LATE ARRIVAL'],
                            ['early_departure', '⏪ EARLY DEPARTURE'],
                            ['overtime', '🕒 OVERTIME'],
                            ['movement_out', '➡️ MOVEMENT OUT'],
                            ['movement_in', '⬅️ MOVEMENT IN'],
                            ['official_assignment', '🏫 OFFICIAL ASSIGNMENT'],
                            ['personal_movement', '🚶 PERSONAL MOVEMENT'],
                            ['inter_branch_movement', '🔁 INTER-BRANCH']
                        ];
                        ?>

                        <?php foreach ($actions as $action): ?>
                            <div class="attendance-action">
                                <form class="attendance-action-form" method="POST">
                                    <input type="hidden" name="event_type" value="<?php echo htmlspecialchars($action[0]); ?>">
                                    <input type="hidden" name="latitude" value="">
                                    <input type="hidden" name="longitude" value="">
                                    <input type="hidden" name="accuracy" value="">
                                    <select name="qr_token" class="qr-selection" required>
                                        <option value="">Select QR code</option>
                                        <?php foreach ($qrCodes as $qr): ?>
                                            <option value="<?php echo htmlspecialchars($qr['token']); ?>"><?php echo htmlspecialchars($qr['description'] ?: $qr['token']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($action[1]); ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="location-warning">Attendance requires internet connectivity and browser location permission. GPS accuracy is checked server-side before the event is approved.</div>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2>Today's Attendance Summary</h2>
                    <div class="attendance-stats">
                        <div class="stat-box">
                            <h3>Resume</h3>
                            <p class="stat-value"><?php echo (int)($todaySummary['resumption'] ?? 0); ?></p>
                        </div>
                        <div class="stat-box">
                            <h3>Movement Out</h3>
                            <p class="stat-value"><?php echo (int)($todaySummary['movement_out'] ?? 0); ?></p>
                        </div>
                        <div class="stat-box">
                            <h3>Movement In</h3>
                            <p class="stat-value"><?php echo (int)($todaySummary['movement_in'] ?? 0); ?></p>
                        </div>
                        <div class="stat-box">
                            <h3>Official Assignment</h3>
                            <p class="stat-value"><?php echo (int)($todaySummary['official_assignment'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2>Recent Attendance Events</h2>
                    <?php if (empty($recentEvents)): ?>
                        <p>No attendance events recorded yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Branch</th>
                                    <th>Zone</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentEvents as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                        <td><?php echo htmlspecialchars($event['branch_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($event['zone_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($event['event_time']); ?></td>
                                        <td><?php echo htmlspecialchars($event['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="js/script.js"></script>
    <script>
        document.querySelectorAll('.attendance-action-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const latitude = form.querySelector('[name="latitude"]');
                const longitude = form.querySelector('[name="longitude"]');
                const accuracy = form.querySelector('[name="accuracy"]');
                const token = form.querySelector('[name="qr_token"]');

                if (!latitude.value || !longitude.value || !token.value) {
                    e.preventDefault();
                    if (!navigator.geolocation) {
                        alert('Location services are not supported by this browser.');
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(function(position) {
                        latitude.value = position.coords.latitude;
                        longitude.value = position.coords.longitude;
                        accuracy.value = position.coords.accuracy || 0;
                        form.submit();
                    }, function(error) {
                        alert('Location permission is required to record attendance. Please allow location access and try again.');
                    }, {
                        enableHighAccuracy: true,
                        timeout: 20000,
                        maximumAge: 0
                    });
                }
            });
        });
    </script>
</body>
</html>
