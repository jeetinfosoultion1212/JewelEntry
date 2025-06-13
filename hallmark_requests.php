<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database configs
session_start();
require 'config/config.php';
require 'config/hallmarkpro_config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firmID'];

// Fetch hallmark requests with HallmarkPro data
$requestsQuery = "SELECT hr.*, hc.name as center_name,
    GROUP_CONCAT(
        CONCAT(
            hri.item_type, ' (', hri.quantity, ' pcs, ',
            hri.weight, 'g, ', hri.purity, '%)'
        ) SEPARATOR '|'
    ) as items_details
FROM hallmark_requests hr
LEFT JOIN hallmark_centers hc ON hr.hallmark_center_id = hc.id
LEFT JOIN hallmark_request_items hri ON hr.id = hri.request_id
WHERE hr.firm_id = ?
GROUP BY hr.id
ORDER BY hr.created_at DESC";

$requestsStmt = $conn->prepare($requestsQuery);
$requestsStmt->bind_param("i", $firm_id);
$requestsStmt->execute();
$requests = $requestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch HallmarkPro job cards
$jobCardsQuery = "SELECT * FROM job_cards WHERE firm_id = ? ORDER BY date_of_request DESC";
$jobCardsStmt = $hallmarkpro_conn->prepare($jobCardsQuery);
$jobCardsStmt->bind_param("i", $firm_id);
$jobCardsStmt->execute();
$jobCards = $jobCardsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Create a mapping of request numbers to job cards
$jobCardsMap = [];
foreach ($jobCards as $card) {
    $jobCardsMap[$card['request_no']] = $card;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hallmark Requests - Jewel Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <?php include 'includes/header.php'; ?>

        <!-- Main Content -->
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-semibold text-gray-800">Hallmark Requests</h1>
                    <a href="new_hallmark_request.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                        <i class="fas fa-plus mr-2"></i> New Request
                    </a>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <p class="text-green-700"><?php echo $_SESSION['success_message']; ?></p>
                        </div>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <!-- Requests Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HallmarkPro Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($requests as $request): ?>
                                    <?php 
                                    $jobCard = $jobCardsMap[$request['request_no']] ?? null;
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                #<?php echo $request['request_no']; ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('d M Y', strtotime($request['request_date'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['center_name']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php 
                                                $items = explode('|', $request['items_details']);
                                                foreach ($items as $item) {
                                                    echo '<div class="mb-1">' . htmlspecialchars($item) . '</div>';
                                                }
                                                ?>
                                            </div>
                                            <div class="text-sm text-gray-500 mt-2">
                                                Total: <?php echo $request['total_items']; ?> items, 
                                                <?php echo number_format($request['total_weight'], 3); ?>g
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($jobCard): ?>
                                                <div class="text-sm">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php
                                                        switch($jobCard['status']) {
                                                            case 'pending':
                                                                echo 'bg-yellow-100 text-yellow-800';
                                                                break;
                                                            case 'processing':
                                                                echo 'bg-blue-100 text-blue-800';
                                                                break;
                                                            case 'completed':
                                                                echo 'bg-green-100 text-green-800';
                                                                break;
                                                            case 'rejected':
                                                                echo 'bg-red-100 text-red-800';
                                                                break;
                                                        }
                                                        ?>">
                                                        <?php echo ucfirst($jobCard['status']); ?>
                                                    </span>
                                                </div>
                                                <?php if ($jobCard['job_no']): ?>
                                                    <div class="text-sm text-gray-500 mt-1">
                                                        Job #: <?php echo $jobCard['job_no']; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($jobCard['date_of_delivery']): ?>
                                                    <div class="text-sm text-gray-500">
                                                        Delivery: <?php echo date('d M Y', strtotime($jobCard['date_of_delivery'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-500">Not synced with HallmarkPro</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            <a href="view_hallmark_request.php?id=<?php echo $request['id']; ?>" 
                                                class="text-yellow-600 hover:text-yellow-900 mr-3">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <a href="edit_hallmark_request.php?id=<?php echo $request['id']; ?>" 
                                                    class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 