<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config/config.php';
require 'config/hallmarkpro_config.php';

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firmID'];

// Date range logic
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fetch firm info
$firmQuery = "SELECT * FROM Firm WHERE id = ?";
$firmStmt = $conn->prepare($firmQuery);
$firmStmt->bind_param("i", $firm_id);
$firmStmt->execute();
$firm = $firmStmt->get_result()->fetch_assoc();
$bis_no = $firm['BISRegistrationNumber'] ?? '';
$show_bis_modal = empty($bis_no);

// Handle BIS number submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bis_no'])) {
    $bis_no = trim($_POST['bis_no']);
    if ($bis_no !== '') {
        $updateStmt = $conn->prepare("UPDATE Firm SET BISRegistrationNumber = ? WHERE id = ?");
        $updateStmt->bind_param("si", $bis_no, $firm_id);
        $updateStmt->execute();
        header("Location: hallmark_requests.php");
        exit();
    }
}

$jeweller = null;
$jobCards = [];
$transactions = [];
if (!$show_bis_modal) {
    // Fetch jeweller by licence_no
    $jewellerQuery = "SELECT * FROM jewellers WHERE licence_no = ?";
    $jewellerStmt = $hallmarkpro_conn->prepare($jewellerQuery);
    $jewellerStmt->bind_param("s", $bis_no);
    $jewellerStmt->execute();
    $jeweller = $jewellerStmt->get_result()->fetch_assoc();

    // Fetch job cards for this licence_no and date range
    $jobCardsQuery = "SELECT * FROM job_cards WHERE licence_no = ? AND date_of_request BETWEEN ? AND ? ORDER BY date_of_request DESC";
    $jobCardsStmt = $hallmarkpro_conn->prepare($jobCardsQuery);
    $jobCardsStmt->bind_param("sss", $bis_no, $start_date, $end_date);
    $jobCardsStmt->execute();
    $jobCards = $jobCardsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch transactions for this licence_no and date range
    $transactionsQuery = "SELECT * FROM transactions WHERE licence_no = ? AND date BETWEEN ? AND ? ORDER BY date DESC";
    $transactionsStmt = $hallmarkpro_conn->prepare($transactionsQuery);
    $transactionsStmt->bind_param("sss", $bis_no, $start_date, $end_date);
    $transactionsStmt->execute();
    $transactions = $transactionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Group job cards by request_no
    $groupedRequests = [];
    foreach ($jobCards as $card) {
        $req = $card['request_no'];
        if (!isset($groupedRequests[$req])) {
            $groupedRequests[$req] = [
                'request_no' => $req,
                'date_of_request' => $card['date_of_request'],
                'job_nos' => [],
                'total_pcs' => 0,
                'total_weight' => 0,
                'purity' => $card['purity'],
                'status' => $card['status'],
            ];
        }
        $groupedRequests[$req]['job_nos'][] = $card['job_no'];
        $groupedRequests[$req]['total_pcs'] += intval($card['pcs']);
        $groupedRequests[$req]['total_weight'] += floatval($card['weight']);
    }
    // Index transactions by request_no for total_amount and payment_status
    $transactionsByRequest = [];
    foreach ($transactions as $txn) {
        $req = $txn['request_no'];
        if (!isset($transactionsByRequest[$req])) {
            $transactionsByRequest[$req] = [
                'total_amount' => 0,
                'paid_amount' => 0,
                'payment_status' => 'Unpaid',
                'bill_no' => '',
            ];
        }
        $transactionsByRequest[$req]['total_amount'] += isset($txn['total_amount']) ? floatval($txn['total_amount']) : 0;
        $transactionsByRequest[$req]['paid_amount'] += isset($txn['paid_amount']) ? floatval($txn['paid_amount']) : 0;
        // Use the latest payment_status if available
        if (!empty($txn['payment_status'])) {
            $transactionsByRequest[$req]['payment_status'] = $txn['payment_status'];
        }
        if (!empty($txn['bill_no'])) {
            $transactionsByRequest[$req]['bill_no'] = $txn['bill_no'];
        }
    }
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
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/header.php'; ?>
        <div class="container mx-auto px-2 py-4 w-full flex flex-col min-h-[calc(100vh-56px)]">
            <div class="flex-1 flex flex-col">
                <!-- Date Range Filter -->
                <form method="get" class="flex items-center gap-1 mb-2 bg-white px-2 py-1 rounded shadow-sm w-full max-w-xs border border-gray-200">
                    <span class="text-gray-400 text-xs mr-1"><i class="fa fa-calendar-alt"></i></span>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border border-gray-200 rounded px-1 py-0.5 text-xs w-[110px] focus:outline-none" required>
                    <span class="text-gray-400 text-xs mx-1">–</span>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border border-gray-200 rounded px-1 py-0.5 text-xs w-[110px] focus:outline-none" required>
                    <button type="submit" class="bg-blue-600 text-white px-2 py-0.5 rounded text-xs ml-2 flex items-center">
                        <i class="fa fa-filter mr-1"></i>Go
                    </button>
                </form>

               

                <?php if ($show_bis_modal): ?>
                <div id="bisModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50">
                    <form method="POST" class="relative bg-white rounded-2xl shadow-2xl min-w-[370px] max-w-[95vw] w-full p-0 overflow-hidden animate-fadeIn">
                        <!-- Accent Bar -->
                        <div class="h-2 bg-blue-200 w-full"></div>
                        <div class="p-7 pt-5 flex flex-col items-center">
                            <div class="flex items-center justify-center mb-3">
                                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 shadow text-blue-500 text-2xl">
                                    <i class="fas fa-id-card-alt"></i>
                                </span>
                            </div>
                            <h2 class="text-xl font-extrabold text-gray-800 mb-1 text-center">BIS Registration Required</h2>
                            <p class="text-gray-600 text-sm text-center mb-3">Unlock your <b>daily hallmark requests</b>, <b>bills</b>, and <b>HUID codes</b> in one place by entering your <b>BIS Gold Registration Number</b>.</p>
                            <ul class="list-disc pl-5 mb-2 text-xs text-gray-500 text-left w-full max-w-xs mx-auto">
                                <li>View all your hallmarking activity, bills, and HUID codes at a glance.</li>
                                <li>Add this BIS number in bulk to your stock for easier management.</li>
                            </ul>
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 px-3 py-2 rounded text-xs mb-4 w-full max-w-xs mx-auto">
                                <b>Note:</b> This feature is <b>only for exclusive Mahalaxmi Hallmarking Centre customers</b>.
                            </div>
                            <input type="text" name="bis_no" class="border border-blue-200 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 rounded-lg p-2 w-full max-w-xs mb-3 text-center text-base transition" placeholder="Enter BIS Registration Number" required autofocus>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 transition text-white px-6 py-2 rounded-lg w-full max-w-xs font-bold shadow mt-1 text-base flex items-center justify-center gap-2">
                                <i class="fas fa-unlock-alt text-yellow-300"></i> Save & Continue
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                    <!-- Compact Stats Section (always visible) -->
                    <?php
                    // Calculate stats from $jobCards
                    $totalRequests = count($jobCards);
                    $totalWeight = 0;
                    $totalPcs = 0;
                    $purityStats = [];
                    foreach ($jobCards as $card) {
                        $totalWeight += floatval($card['weight']);
                        $totalPcs += intval($card['pcs']);
                        $purity = $card['purity'] ?? '';
                        if ($purity) {
                            if (!isset($purityStats[$purity])) {
                                $purityStats[$purity] = ['weight' => 0, 'pcs' => 0];
                            }
                            $purityStats[$purity]['weight'] += floatval($card['weight']);
                            $purityStats[$purity]['pcs'] += intval($card['pcs']);
                        }
                    }
                    ?>
                    <div class="overflow-x-auto pb-2 mb-2 w-full">
                        <div class="flex space-x-2 min-w-[320px] w-full">
                            <!-- Total Requests Card -->
                            <div class="flex-shrink-0 bg-yellow-100 rounded-md shadow p-1.5 w-28 flex items-center border border-yellow-200">
                                <span class="bg-yellow-400 text-white rounded p-1 mr-1 text-xs"><i class="fas fa-list-ul"></i></span>
                                <span class="text-base font-bold text-yellow-800 mr-1"><?php echo $totalRequests; ?></span>
                                <span class="text-[10px] text-yellow-700">Req</span>
                            </div>
                            <!-- Total Weight Card -->
                            <div class="flex-shrink-0 bg-amber-100 rounded-md shadow p-1.5 w-28 flex items-center border border-amber-200">
                                <span class="bg-amber-400 text-white rounded p-1 mr-1 text-xs"><i class="fas fa-balance-scale"></i></span>
                                <span class="text-base font-bold text-amber-800 mr-1"><?php echo number_format($totalWeight, 2); ?>g</span>
                                <span class="text-[10px] text-amber-700">Wt</span>
                            </div>
                            <!-- Total Pcs Card -->
                            <div class="flex-shrink-0 bg-purple-100 rounded-md shadow p-1.5 w-24 flex items-center border border-purple-200">
                                <span class="bg-purple-400 text-white rounded p-1 mr-1 text-xs"><i class="fas fa-cubes"></i></span>
                                <span class="text-base font-bold text-purple-800 mr-1"><?php echo $totalPcs; ?></span>
                                <span class="text-[10px] text-purple-700">Pcs</span>
                            </div>
                            <!-- Purity Stats: Each as a Compact Card -->
                            <?php foreach ($purityStats as $purity => $stat): ?>
                                <div class="flex-shrink-0 bg-blue-100 rounded-md shadow p-1.5 w-32 flex items-center border border-blue-200">
                                    <span class="bg-blue-400 text-white rounded p-1 mr-1 text-xs"><i class="fas fa-certificate"></i></span>
                                    <span class="text-xs font-bold text-blue-900 mr-1"><?php echo htmlspecialchars($purity); ?></span>
                                    <span class="text-xs text-blue-800 mr-1"><?php echo number_format($stat['weight'], 2); ?>g</span>
                                    <span class="text-[10px] text-blue-700"><?php echo $stat['pcs']; ?>pcs</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- End Compact Stats Section -->
                    <?php if ($jeweller): ?>
                        <!-- Jeweller info card is hidden/removed as per user request -->
                    <?php else: ?>
                        <div class="bg-red-100 border border-red-300 text-red-700 rounded-lg p-4 mb-6">
                            No jeweller found for BIS Registration Number <b><?php echo htmlspecialchars($bis_no); ?></b> in HallmarkPro database.
                        </div>
                    <?php endif; ?>

                    <!-- Hallmark Requests Table (Job Cards) -->
                    <div class="bg-white rounded-t-xl shadow-md overflow-hidden flex-1 flex flex-col mb-8 w-full">
                        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-t-xl border-b border-gray-100">
                            <span class="font-bold text-sm text-gray-800">Hallmark Request</span>
                        </div>
                       
                        <div class="overflow-x-auto w-full flex-1 flex flex-col">
                            <!-- Compact List Header -->
                            <div class="flex items-center justify-between px-2 py-1 bg-gray-50 text-[11px] font-semibold text-gray-500 border-b border-gray-100 w-full sticky top-0 z-10" style="backdrop-filter: blur(2px);">
                                <div class="min-w-[30px] mr-1 text-center">S.No</div>
                                <div class="min-w-[90px] mr-1">Date / Request</div>
                                <div class="min-w-[44px] mr-1 text-center">Pcs / Wt</div>
                                <div class="min-w-[38px] mr-1 text-center">Purity</div>
                                <div class="min-w-[54px] mr-1 text-center">Amount</div>
                                <div class="min-w-[50px] mr-1 text-center">DCSE</div>
                                <div class="min-w-[28px] text-center">Bill</div>
                            </div>
                            <!-- Compact List View: make only this part scrollable vertically, fill remaining height -->
                            <div class="divide-y divide-gray-100 bg-white rounded-b flex-1 overflow-y-auto w-full" style="max-height: 420px; min-height: 120px;">
                                <?php if (!empty($jobCards)): ?>
                                    <?php $serial = 1; foreach ($groupedRequests as $req): ?>
                                        <?php
                                            $txn = $transactionsByRequest[$req['request_no']] ?? ['total_amount' => 0, 'paid_amount' => 0, 'payment_status' => 'Unpaid'];
                                            $total_amount = $txn['total_amount'];
                                            $paid = $txn['paid_amount'];
                                            $paymentStatus = $txn['payment_status'];
                                            $due = $total_amount - $paid;
                                            $billNo = $txn['bill_no'] ?? '';
                                            $billNoTrim = trim($billNo);
                                            $billPdfUrl = '';
                                            $billExists = false;
                                            // Get DCSE from the first job card of this request
                                            $dcse = '-';
                                            foreach ($jobCards as $card) {
                                                if ($card['request_no'] == $req['request_no']) {
                                                    $dcse = $card['DCSE'] ?? '-';
                                                    break;
                                                }
                                            }
                                            if (!empty($billNoTrim) && $billNoTrim !== '0') {
                                                $billPdfUrl = 'https://mahalaxmihallmarkingcentre.com/admin/bills/' . urlencode($billNoTrim) . '.pdf';
                                                // Check if the PDF exists (HEAD request)
                                                $headers = @get_headers($billPdfUrl);
                                                if ($headers && strpos($headers[0], '200') !== false) {
                                                    $billExists = true;
                                                }
                                            }
                                        ?>
                                        <div class="flex flex-wrap items-center justify-between px-2 py-1 hover:bg-gray-50 transition group text-xs">
                                            <!-- Serial No -->
                                            <div class="min-w-[30px] mr-1 text-center">
                                                <?= $serial ?>
                                            </div>
                                            <!-- Date & Request No -->
                                            <div class="flex flex-col min-w-[90px] mr-1">
                                                <?php if (!empty($billNoTrim)): ?>
                                                    <span class="text-xs font-bold text-blue-800 leading-tight">
                                                        <a href="huid_data.php?request_no=<?= urlencode($req['request_no']) ?>" class="hover:underline" title="View HUID Data">
                                                            <?= htmlspecialchars($billNo) ?>
                                                        </a>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-[10px] text-gray-400 font-medium mt-0.5">Request: #<?= htmlspecialchars($req['request_no']) ?></span>
                                                <span class="text-[9px] text-gray-400 font-medium"><?= date('d M Y', strtotime($req['date_of_request'])) ?></span>
                                            </div>
                                            <!-- Pcs / Weight -->
                                            <div class="flex flex-col items-center min-w-[44px] mr-1">
                                                <span class="text-[10px] text-gray-400">Pcs</span>
                                                <span class="font-bold text-purple-700"><?= $req['total_pcs'] ?></span>
                                                <span class="text-[9px] text-amber-700 font-semibold"><?= number_format($req['total_weight'], 2) ?>g</span>
                                            </div>
                                            <!-- Purity -->
                                            <div class="flex flex-col items-center min-w-[38px] mr-1">
                                                <span class="text-[10px] text-gray-400">Purity</span>
                                                <span class="font-bold text-blue-700"><?= htmlspecialchars($req['purity'] ?? '-') ?></span>
                                                <!-- Status from job cards under purity -->
                                                <span class="text-[9px] text-gray-500 font-semibold"><?= htmlspecialchars($req['status'] ?? '-') ?></span>
                                            </div>
                                            <!-- Amount (Paid/Due) -->
                                            <div class="flex flex-col items-center min-w-[54px] mr-1">
                                                <span class="text-[10px] text-gray-400">Amount</span>
                                                <span class="font-bold text-green-700">
                                                    ₹<?= number_format($total_amount, 2) ?>
                                                </span>
                                                <span class="text-[9px] font-semibold <?= $paymentStatus === 'Paid' ? 'text-green-600' : ($paymentStatus === 'Partial' ? 'text-yellow-600' : 'text-red-600') ?>">
                                                    <?= $paymentStatus ?>
                                                </span>
                                            </div>
                                            <!-- DCSE -->
                                            <div class="flex flex-col items-center min-w-[50px] mr-1">
                                                <span class="text-[10px] text-gray-400">DCSE</span>
                                                <span class="font-bold text-gray-700"><?= htmlspecialchars($dcse) ?></span>
                                            </div>
                                            <!-- Download Bill -->
                                            <div class="flex items-center min-w-[28px] justify-center">
                                                <?php if (empty($billNoTrim) || $billNoTrim === '0'): ?>
                                                    <span class="text-xs text-gray-400 font-semibold">NA</span>
                                                <?php elseif ($billExists): ?>
                                                    <a href="<?= htmlspecialchars($billPdfUrl) ?>" target="_blank" title="Download Bill PDF" class="text-blue-500 hover:text-blue-700 transition text-base">
                                                        <i class="fas fa-file-download text-xs"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-300 cursor-not-allowed" title="Bill PDF not available">
                                                        <i class="fas fa-file-download text-xs"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php $serial++; endforeach; ?>
                                <?php else: ?>
                                    <div class="px-4 py-4 text-center text-gray-400 text-xs">No hallmark job cards found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'includes/bottom_nav.php'; ?>
</body>
</html>
