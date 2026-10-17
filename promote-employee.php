<?php
session_start();
require_once '../src/config/database.php';
require_once '../src/middleware/AuthMiddleware.php';
require_once '../src/models/Employee.php';

// Check authentication and admin role
AuthMiddleware::checkRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['level'])) {
    $employeeId = $_POST['id'];
    $targetLevel = (int)$_POST['level'];
    
    // Validate level is between 1 and 5
    if ($targetLevel >= 1 && $targetLevel <= 5) {
        $employeeModel = new Employee($db);
        $newGrade = "Level {$targetLevel}";
        
        // Update grade level
        $updateData = ['grade_level' => $newGrade];
        if ($employeeModel->updateEmployee($employeeId, $updateData)) {
            // Success - redirect back with success message
            header('Location: employees.php?promoted=' . $employeeId);
            exit;
        }
    }
}

// If we get here, something went wrong - redirect back
header('Location: employees.php');
exit;
?>
