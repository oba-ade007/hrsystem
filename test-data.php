<?php
require_once '../src/config/database.php';
require_once '../src/models/Department.php';
require_once '../src/models/Branch.php';

echo "Testing Database Connection and Data Retrieval...\n\n";

// Test connection
echo "Database Connection: ";
if ($db) {
    echo "✓ Connected\n\n";
} else {
    echo "✗ Failed\n\n";
    exit;
}

// Test departments
echo "Departments:\n";
$departmentModel = new Department($db);
$departments = $departmentModel->getAllDepartments();
if (is_array($departments)) {
    echo "Count: " . count($departments) . "\n";
    foreach($departments as $dept) {
        echo "  - " . $dept['name'] . " (ID: " . $dept['id'] . ")\n";
    }
} else {
    echo "Error retrieving departments\n";
}

echo "\n\nBranches:\n";
$branchModel = new Branch($db);
$branches = $branchModel->getAllBranches();
if (is_array($branches)) {
    echo "Count: " . count($branches) . "\n";
    foreach($branches as $branch) {
        echo "  - " . $branch['name'] . " (ID: " . $branch['id'] . ")\n";
    }
} else {
    echo "Error retrieving branches\n";
}

echo "\n\n✓ Test Complete!";
?>
