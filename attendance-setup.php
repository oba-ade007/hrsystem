<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr', 'unit_heads']);

require_once '../src/config/database.php';
require_once '../src/models/Attendance.php';

$attendanceModel = new Attendance($db);
$branches = $attendanceModel->getBranches();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    try {
        if ($formType === 'branch') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'branch_code' => trim($_POST['branch_code'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'latitude' => $_POST['latitude'] ?? null,
                'longitude' => $_POST['longitude'] ?? null,
                'geofence_radius' => (int)($_POST['geofence_radius'] ?? 30),
                'working_hours' => trim($_POST['working_hours'] ?? '08:00-17:00'),
                'attendance_rules' => trim($_POST['attendance_rules'] ?? ''),
                'status' => $_POST['status'] ?? 'active'
            ];

            if (empty($data['name'])) {
                throw new Exception('Branch name is required.');
            }

            $attendanceModel->createBranch($data);
            $success = 'Branch created successfully.';
        }

        if ($formType === 'zone') {
            $data = [
                'branch_id' => (int)($_POST['branch_id'] ?? 0),
                'name' => trim($_POST['zone_name'] ?? ''),
                'code' => trim($_POST['zone_code'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'latitude' => $_POST['latitude'] ?? null,
                'longitude' => $_POST['longitude'] ?? null,
                'geofence_radius' => (int)($_POST['geofence_radius'] ?? 30),
                'status' => $_POST['status'] ?? 'active'
            ];

            if (empty($data['branch_id']) || empty($data['name'])) {
                throw new Exception('Branch and zone name are required.');
            }

            $attendanceModel->createZone($data);
            $success = 'Zone configured successfully.';
        }

        if ($formType === 'qr') {
            $data = [
                'branch_id' => (int)($_POST['branch_id'] ?? 0),
                'zone_id' => (int)($_POST['zone_id'] ?? 0),
                'token' => trim($_POST['token'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'latitude' => $_POST['latitude'] ?? null,
                'longitude' => $_POST['longitude'] ?? null,
                'geofence_radius' => (int)($_POST['geofence_radius'] ?? 30),
                'status' => $_POST['status'] ?? 'active'
            ];

            if (empty($data['branch_id']) || empty($data['zone_id']) || empty($data['token'])) {
                throw new Exception('Branch, zone and QR token are required.');
            }

            $attendanceModel->createQrCode($data);
            $success = 'QR location registered successfully.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    $branches = $attendanceModel->getBranches();
    $zones = [];
    foreach ($branches as $branch) {
        $zones[$branch['id']] = $attendanceModel->getZonesByBranch((int)$branch['id']);
    }
}

$allZones = [];
foreach ($branches as $branch) {
    $allZones[$branch['id']] = $attendanceModel->getZonesByBranch((int)$branch['id']);
}
$allQrCodes = [];
foreach ($branches as $branch) {
    $allQrCodes[$branch['id']] = $attendanceModel->getQrCodesByBranch((int)$branch['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Setup - HR System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="brand"><h2>HR System</h2></div>
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
                    <li class="active"><a href="attendance.php"><i>📋</i> Attendance</a></li>
                    <li><a href="attendance-setup.php"><i>⚙️</i> Attendance Setup</a></li>
                    <li><a href="my-profile.php"><i>👤</i> My Profile</a></li>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'hr', 'unit_heads'], true)): ?>
                    <li class="menu-header">MANAGEMENT</li>
                    <li><a href="employees.php"><i>👥</i> Employees</a></li>
                    <li><a href="departments.php"><i>🏢</i> Departments</a></li>
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
                    <h1>Attendance and GPS Setup</h1>
                    <p>Configure branches, zones and QR attendance points for staff movement and attendance.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="card">
                        <h2>Add Branch</h2>
                        <form method="POST">
                            <input type="hidden" name="form_type" value="branch">
                            <div class="form-group">
                                <label>Branch Name</label>
                                <input type="text" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Branch Code</label>
                                <input type="text" name="branch_code">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3"></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Latitude</label>
                                    <input type="text" name="latitude" step="any">
                                </div>
                                <div class="form-group">
                                    <label>Longitude</label>
                                    <input type="text" name="longitude" step="any">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Geofence Radius (m)</label>
                                    <input type="number" name="geofence_radius" value="30" min="5" max="500">
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Working Hours</label>
                                <input type="text" name="working_hours" value="08:00-17:00">
                            </div>
                            <div class="form-group">
                                <label>Attendance Rules</label>
                                <textarea name="attendance_rules" rows="3" placeholder="Late threshold, movement rules, overtime policy..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Branch</button>
                        </form>
                    </div>

                    <div class="card">
                        <h2>Add Zone</h2>
                        <form method="POST">
                            <input type="hidden" name="form_type" value="zone">
                            <div class="form-group">
                                <label>Branch</label>
                                <select name="branch_id" required>
                                    <option value="">Select branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo (int)$branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Zone Name</label>
                                <input type="text" name="zone_name" required>
                            </div>
                            <div class="form-group">
                                <label>Zone Code</label>
                                <input type="text" name="zone_code">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" rows="3"></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Latitude</label>
                                    <input type="text" name="latitude" step="any">
                                </div>
                                <div class="form-group">
                                    <label>Longitude</label>
                                    <input type="text" name="longitude" step="any">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Geofence Radius (m)</label>
                                    <input type="number" name="geofence_radius" value="30" min="5" max="500">
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Zone</button>
                        </form>
                    </div>

                    <div class="card">
                        <h2>Add QR Code Location</h2>
                        <form method="POST">
                            <input type="hidden" name="form_type" value="qr">
                            <div class="form-group">
                                <label>Branch</label>
                                <select name="branch_id" class="branch-selector" required>
                                    <option value="">Select branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo (int)$branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Zone</label>
                                <select name="zone_id" required>
                                    <option value="">Select zone</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <?php foreach (($allZones[$branch['id']] ?? []) as $zone): ?>
                                            <option value="<?php echo (int)$zone['id']; ?>" data-branch-id="<?php echo (int)$branch['id']; ?>"><?php echo htmlspecialchars($branch['name'] . ' / ' . $zone['name']); ?></option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>QR Token</label>
                                <input type="text" name="token" required placeholder="QR-CCI-01">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description" placeholder="CCI Admin Block">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Latitude</label>
                                    <input type="text" name="latitude" step="any" required>
                                </div>
                                <div class="form-group">
                                    <label>Longitude</label>
                                    <input type="text" name="longitude" step="any" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Geofence Radius (m)</label>
                                    <input type="number" name="geofence_radius" value="30" min="5" max="500">
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save QR Code</button>
                        </form>
                    </div>
                </div>

                <div class="card" style="margin-top: 30px;">
                    <h2>Configured Branches</h2>
                    <?php foreach ($branches as $branch): ?>
                        <div style="padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                            <strong><?php echo htmlspecialchars($branch['name']); ?></strong> | <?php echo htmlspecialchars($branch['branch_code'] ?: 'No code'); ?>
                            <div style="color: #666; font-size:14px;">
                                <?php echo htmlspecialchars($branch['address'] ?: 'No address'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="card" style="margin-top: 30px;">
                    <h2>Registered QR Locations</h2>
                    <?php foreach ($branches as $branch): ?>
                        <?php if (!empty($allQrCodes[$branch['id']])): ?>
                            <div style="padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                <strong><?php echo htmlspecialchars($branch['name']); ?></strong>
                                <ul style="margin: 8px 0 0 20px; padding: 0;">
                                    <?php foreach ($allQrCodes[$branch['id']] as $qr): ?>
                                        <li><?php echo htmlspecialchars($qr['description'] ?: $qr['token']); ?> — <?php echo htmlspecialchars($qr['token']); ?> — <?php echo (float)$qr['geofence_radius']; ?>m</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
