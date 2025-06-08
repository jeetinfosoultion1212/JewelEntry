<?php
require 'config/config.php';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Insert gold saving plans
$plans = [
    [
        'firm_id' => 1,
        'plan_name' => 'Swarna Lakshmi Plan',
        'description' => 'Pay 11/Get 12 - Monthly savings with bonus',
        'duration_months' => 12,
        'min_amount_per_installment' => 5000.00,
        'installment_frequency' => 'monthly',
        'bonus_percentage' => 8.33,
        'status' => 'active',
        'terms_conditions' => 'Customer must pay all 11 installments to receive bonus. No late payment allowed.'
    ],
    [
        'firm_id' => 1,
        'plan_name' => 'Dhan Varsha Plan',
        'description' => 'Save weekly for a year and earn bonus gold at end',
        'duration_months' => 12,
        'min_amount_per_installment' => 2000.00,
        'installment_frequency' => 'monthly',
        'bonus_percentage' => 10.00,
        'status' => 'active',
        'terms_conditions' => 'Full bonus is only applicable if all installments are paid without fail.'
    ],
    [
        'firm_id' => 1,
        'plan_name' => 'Akshaya Gold Plan',
        'description' => 'Flexible plan with quarterly deposits and festival bonus',
        'duration_months' => 9,
        'min_amount_per_installment' => 10000.00,
        'installment_frequency' => 'monthly',
        'bonus_percentage' => 7.00,
        'status' => 'active',
        'terms_conditions' => 'Installments must be paid before due date for eligibility of bonus.'
    ]
];

// Insert plans
foreach ($plans as $plan) {
    $stmt = $conn->prepare("INSERT INTO gold_saving_plans (firm_id, plan_name, description, duration_months, min_amount_per_installment, installment_frequency, bonus_percentage, status, terms_conditions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issidssss", 
        $plan['firm_id'],
        $plan['plan_name'],
        $plan['description'],
        $plan['duration_months'],
        $plan['min_amount_per_installment'],
        $plan['installment_frequency'],
        $plan['bonus_percentage'],
        $plan['status'],
        $plan['terms_conditions']
    );
    $stmt->execute();
    $plan_id = $stmt->insert_id;
    $stmt->close();

    // Insert customer enrollment
    $enrollment_date = date('Y-m-d');
    $maturity_date = date('Y-m-d', strtotime("+{$plan['duration_months']} months"));
    
    $stmt = $conn->prepare("INSERT INTO customer_gold_plans (firm_id, customer_id, plan_id, enrollment_date, maturity_date, current_status, total_amount_paid, total_gold_accrued, notes) VALUES (?, 39, ?, ?, ?, 'active', 0.00, 0.0000, ?)");
    $notes = "{$plan['plan_name']} - Monthly installment of ₹" . number_format($plan['min_amount_per_installment'], 2);
    $stmt->bind_param("iisss", 
        $plan['firm_id'],
        $plan_id,
        $enrollment_date,
        $maturity_date,
        $notes
    );
    $stmt->execute();
    $stmt->close();
}

echo "Demo data added successfully!";
$conn->close();
?> 