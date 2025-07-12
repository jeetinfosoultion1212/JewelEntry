<?php
session_start();
require 'config/config.php';

if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    header("Location: login.php?error=Session+expired.+Please+login+again.");
    exit();
}

$firm_id = $_SESSION['firmID'];
$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_name = trim($_POST['plan_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration_months = intval($_POST['duration_months'] ?? 0);
    $min_amount_per_installment = floatval($_POST['min_amount_per_installment'] ?? 0);
    $installment_frequency = trim($_POST['installment_frequency'] ?? '');
    $bonus_percentage = floatval($_POST['bonus_percentage'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');
    $terms_conditions = trim($_POST['terms_conditions'] ?? '');

    // Basic validation
    if ($plan_name === '' || $duration_months <= 0 || $min_amount_per_installment <= 0 || $installment_frequency === '' || $status === '') {
        header("Location: gold_plan.php?error=Please+fill+all+required+fields");
        exit();
    }

    $query = "INSERT INTO gold_saving_plans (firm_id, plan_name, description, duration_months, min_amount_per_installment, installment_frequency, bonus_percentage, status, terms_conditions, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        header("Location: gold_plan.php?error=Failed+to+prepare+statement");
        exit();
    }
    $stmt->bind_param(
        "isssdssdsi",
        $firm_id,
        $plan_name,
        $description,
        $duration_months,
        $min_amount_per_installment,
        $installment_frequency,
        $bonus_percentage,
        $status,
        $terms_conditions,
        $user_id
    );
    if ($stmt->execute()) {
        header("Location: gold_plan.php?success=Plan+added+successfully");
    } else {
        header("Location: gold_plan.php?error=Failed+to+add+plan");
    }
    $stmt->close();
    $conn->close();
    exit();
} else {
    header("Location: gold_plan.php");
    exit();
} 