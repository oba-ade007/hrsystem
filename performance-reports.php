<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkRole(['admin', 'hr']);

require_once '../src/config/database.php';
require_once '../src/models/PerformanceManagement.php';
require_once '../src/models/PerformancePlanning.php';
require_once '../src/models/PerformanceAppraisal.php';

$userId = (int)$_SESSION['user_id'];
$configModel = new PerformanceManagement($db);
$planningModel = new PerformancePlanning($db);
$appraisalModel = new PerformanceAppraisal($db);
$organisations = $planningModel->getOrganisationsForUser($userId, $_SESSION['user_role']);
$organisationId = (int)($_GET['organisation_id'] ?? ($organisations[0]['id'] ?? 0));
$hasAccess = false;
foreach ($organisations as $organisation) { if ((int)$organisation['id'] === $organisationId) { $hasAccess = true; break; } }
if (!$hasAccess) { http_response_code(403); exit('You do not have access to this appraisal organisation.'); }
$cycles = $configModel->getCycles($organisationId);
$filters = $appraisalModel->getPerformanceFilterOptions($organisationId);
$cycleId = (int)($_GET['cycle_id'] ?? ($cycles[0]['id'] ?? 0));
$branchId = (int)($_GET['branch_id'] ?? 0);
$departmentId = (int)($_GET['department_id'] ?? 0);
$export = $_GET['export'] ?? '';
$rows = $cycleId ? $appraisalModel->getFinalScores($organisationId, $cycleId, $branchId ?: null, $departmentId ?: null) : [];

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="performance-distribution.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee', 'Employee ID', 'Branch', 'Department', 'Position', 'Grade', 'Final Score', 'Final Rating']);
    foreach ($rows as $row) {
        fputcsv($output, [$row['employee_name'], $row['staff_number'], $row['branch_name'] ?? '', $row['department_name'] ?? '', $row['position'] ?? '', $row['grade_level'] ?? '', $row['final_score'], $row['final_rating_label']]);
    }
    fclose($output);
    exit;
}

$values = array_map(function ($row) { return (float)$row['final_score']; }, $rows);
$count = count($values);
$average = $count ? array_sum($values) / $count : null;
$sorted = $values;
sort($sorted, SORT_NUMERIC);
$median = null;
if ($count) {
    $middle = (int)floor($count / 2);
    $median = $count % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2;
}
$standardDeviation = null;
if ($count > 1 && $average !== null) {
    $sumSquares = 0.0;
    foreach ($values as $value) { $sumSquares += ($value - $average) ** 2; }
    $standardDeviation = sqrt($sumSquares / $count);
}
$ratingCounts = [];
foreach ($rows as $row) {
    $label = trim((string)$row['final_rating_label']) ?: 'Unrated';
    $ratingCounts[$label] = ($ratingCounts[$label] ?? 0) + 1;
}
arsort($ratingCounts);
$histogram = [];
if ($count && $count < 30) {
    foreach ($values as $value) {
        $label = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        $histogram[$label] = ($histogram[$label] ?? 0) + 1;
    }
    ksort($histogram, SORT_NUMERIC);
} elseif ($count >= 30) {
    $minimum = min($values);
    $maximum = max($values);
    $binCount = min(10, max(3, (int)round(sqrt($count))));
    $width = $maximum > $minimum ? ($maximum - $minimum) / $binCount : 1;
    for ($index = 0; $index < $binCount; $index++) {
        $start = $minimum + $index * $width;
        $end = $index === $binCount - 1 ? $maximum : $start + $width;
        $label = number_format($start, 2) . '–' . number_format($end, 2);
        $histogram[$label] = 0;
    }
    foreach ($values as $value) {
        $index = $maximum > $minimum ? min($binCount - 1, (int)floor(($value - $minimum) / $width)) : 0;
        $start = $minimum + $index * $width;
        $end = $index === $binCount - 1 ? $maximum : $start + $width;
        $label = number_format($start, 2) . '–' . number_format($end, 2);
        $histogram[$label]++;
    }
}
$histogramMax = $histogram ? max($histogram) : 1;
$ratingMax = $ratingCounts ? max($ratingCounts) : 1;
$csvUrl = 'performance-reports.php?' . http_build_query(['organisation_id' => $organisationId, 'cycle_id' => $cycleId, 'branch_id' => $branchId, 'department_id' => $departmentId, 'export' => 'csv']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Distribution - HR Software</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .report-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin:20px 0; }
        .report-stat { background:#fff; border:1px solid #dfe7f1; border-radius:10px; padding:16px; }
        .report-stat strong { display:block; font-size:1.5rem; color:#19324d; margin-top:4px; }
        .distribution-row { display:grid; grid-template-columns:minmax(130px,1fr) minmax(100px,3fr) 50px; gap:12px; align-items:center; margin:12px 0; }
        .bar-track { height:18px; background:#e7edf5; border-radius:999px; overflow:hidden; }
        .bar-fill { height:100%; background:linear-gradient(90deg,#3b82f6,#0f766e); min-width:2px; }
        .chart-note { color:#536273; line-height:1.5; }
        @media(max-width:600px){.distribution-row{grid-template-columns:1fr 2fr 36px;gap:8px}}
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar"><div class="brand"><h2>HR System</h2></div><nav class="nav-menu"><ul>
        <li><a href="dashboard.php"><i>📊</i> Dashboard</a></li>
        <li><a href="performance-appraisals.php"><i>📑</i> Appraisals</a></li>
        <li class="active"><a href="performance-reports.php"><i>📈</i> Performance Distribution</a></li>
        <li><a href="performance-plan-approvals.php"><i>✓</i> KPI Plan Approvals</a></li>
        <li class="menu-header">ACCOUNT</li><li><a href="logout.php"><i>🚪</i> Logout</a></li>
    </ul></nav></aside>
    <main class="content"><div class="topbar"><div class="user-info"><span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span></div></div>
        <div class="container">
            <div class="page-header"><h1>Performance Distribution</h1><p>Explore released final scores and configured rating categories. This is the actual employee distribution—not a forced or fitted normal curve.</p></div>
            <section class="card"><h2>Filters</h2><form method="GET" class="filter-form">
                <?php if (count($organisations) > 1): ?><select name="organisation_id" aria-label="Organisation"><?php foreach ($organisations as $organisation): ?><option value="<?php echo (int)$organisation['id']; ?>" <?php echo (int)$organisation['id'] === $organisationId ? 'selected' : ''; ?>><?php echo htmlspecialchars($organisation['name']); ?></option><?php endforeach; ?></select><?php else: ?><input type="hidden" name="organisation_id" value="<?php echo $organisationId; ?>"><?php endif; ?>
                <select name="cycle_id" required><option value="">Select cycle</option><?php foreach ($cycles as $cycle): ?><option value="<?php echo (int)$cycle['id']; ?>" <?php echo (int)$cycle['id'] === $cycleId ? 'selected' : ''; ?>><?php echo htmlspecialchars($cycle['name']); ?></option><?php endforeach; ?></select>
                <select name="branch_id"><option value="">All branches</option><?php foreach ($filters['branches'] as $branch): ?><option value="<?php echo (int)$branch['id']; ?>" <?php echo (int)$branch['id'] === $branchId ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option><?php endforeach; ?></select>
                <select name="department_id"><option value="">All departments</option><?php foreach ($filters['departments'] as $department): ?><option value="<?php echo (int)$department['id']; ?>" <?php echo (int)$department['id'] === $departmentId ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['name']); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-primary">Apply filters</button>
            </form></section>
            <div class="report-stats">
                <div class="report-stat">Employees<strong><?php echo $count; ?></strong></div>
                <div class="report-stat">Average score<strong><?php echo $average === null ? '—' : number_format($average, 2); ?></strong></div>
                <div class="report-stat">Median score<strong><?php echo $median === null ? '—' : number_format($median, 2); ?></strong></div>
                <div class="report-stat">Population standard deviation<strong><?php echo $standardDeviation === null ? '—' : number_format($standardDeviation, 2); ?></strong></div>
            </div>
            <?php if ($count > 0 && $count < 30): ?><div class="alert alert-info">This group has fewer than 30 employees. A bell curve or normality assumption is statistically inappropriate; the chart below shows actual scores only.</div><?php endif; ?>
            <section class="card"><h2>Actual score distribution</h2><p class="chart-note">Bars group the actual final scores into observed score bins. They are not normal-distribution expectations, and no employee is forced into a category.</p>
                <?php if (!$histogram): ?><p>No released final scores match the selected filters.</p><?php else: foreach ($histogram as $label => $amount): ?><div class="distribution-row"><span><?php echo htmlspecialchars((string)$label); ?></span><div class="bar-track" role="img" aria-label="<?php echo htmlspecialchars((string)$label . ': ' . $amount . ' employees'); ?>"><div class="bar-fill" style="width:<?php echo number_format(($amount / $histogramMax) * 100, 2); ?>%"></div></div><strong><?php echo (int)$amount; ?></strong></div><?php endforeach; endif; ?>
            </section>
            <section class="card"><h2>Configured rating categories</h2><?php if (!$ratingCounts): ?><p>No released results available.</p><?php else: foreach ($ratingCounts as $label => $amount): ?><div class="distribution-row"><span><?php echo htmlspecialchars($label); ?> <small>(<?php echo number_format($amount / $count * 100, 1); ?>%)</small></span><div class="bar-track"><div class="bar-fill" style="width:<?php echo number_format(($amount / $ratingMax) * 100, 2); ?>%"></div></div><strong><?php echo (int)$amount; ?></strong></div><?php endforeach; endif; ?></section>
            <section class="card"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><h2>Released appraisal detail</h2><a class="btn btn-secondary" href="<?php echo htmlspecialchars($csvUrl); ?>">Export CSV</a></div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Employee</th><th>Employee ID</th><th>Branch</th><th>Department</th><th>Position</th><th>Grade</th><th>Final score</th><th>Rating</th></tr></thead><tbody>
                    <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center">No released appraisal results found.</td></tr><?php else: foreach ($rows as $row): ?><tr><td><?php echo htmlspecialchars($row['employee_name']); ?></td><td><?php echo htmlspecialchars($row['staff_number']); ?></td><td><?php echo htmlspecialchars($row['branch_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['department_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['position'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['grade_level'] ?? '—'); ?></td><td><?php echo htmlspecialchars((string)$row['final_score']); ?></td><td><?php echo htmlspecialchars($row['final_rating_label'] ?? '—'); ?></td></tr><?php endforeach; endif; ?>
                </tbody></table></div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
