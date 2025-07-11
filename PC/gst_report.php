<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function fetch_gst_data($conn, $from, $to) {
    $sql = "SELECT
        pi.*, p.invoice_number, p.purchase_date, p.stock_name,
        s.name AS supplier_name, s.gst AS supplier_gstin
    FROM purchase_items pi
    JOIN metal_purchases p ON pi.purchase_id = p.purchase_id
    JOIN suppliers s ON p.source_id = s.id
    WHERE p.purchase_date BETWEEN ? AND ?
    ORDER BY p.purchase_date, p.invoice_number, pi.id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function generate_excel($data) {
    function columnLetter($c) {
        $c = intval($c);
        $letter = '';
        while ($c > 0) {
            $mod = ($c - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $c = (int)(($c - $mod) / 26);
        }
        return $letter;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headers = [
        'Date', 'Invoice No', 'Supplier', 'Supplier GSTIN', 'HSN', 'Material',
        'Purity', 'Qty', 'Unit', 'Rate', 'Taxable Value', 'GST %', 'GST Amt', 'Total', 'Stock Name'
    ];
    foreach ($headers as $i => $h) {
        $sheet->setCellValue(columnLetter($i+1) . '1', $h);
    }
    $rowNum = 2;
    foreach ($data as $row) {
        $col = 1;
        $sheet->setCellValue(columnLetter($col++) . $rowNum, date('Y-m-d', strtotime($row['purchase_date'])));
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['invoice_number']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['supplier_name']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['supplier_gstin']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['hsn_code']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['material_type']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['purity']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['quantity']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['unit_measurement']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['rate_per_unit']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['total_amount']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['gst_percent']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['gst_amount']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['total_amount'] + $row['gst_amount']);
        $sheet->setCellValue(columnLetter($col++) . $rowNum, $row['stock_name']);
        $rowNum++;
    }
    $sheet->setTitle('GST Purchases');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="GST_Purchase_Report.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

function generate_tally_xml($data) {
    $stock_items = [];
    $ledger_names = [];
    $xml = '<?xml version="1.0"?>\n<ENVELOPE>\n<HEADER>\n<TALLYREQUEST>Import Data</TALLYREQUEST>\n</HEADER>\n<BODY>\n<IMPORTDATA>\n<REQUESTDESC>\n<REPORTNAME>Vouchers</REPORTNAME>\n</REQUESTDESC>\n<REQUESTDATA>';

    foreach ($data as $row) {
        $invoice_date = date('Ymd', strtotime($row['purchase_date']));
        $supplier = htmlspecialchars($row['supplier_name']);
        $gstin = $row['supplier_gstin'];
        $stock_name = htmlspecialchars($row['stock_name']);
        $amount = number_format($row['total_amount'], 2, '.', '');
        $gst = number_format($row['gst_amount'], 2, '.', '');
        $qty = number_format($row['quantity'], 3, '.', '');
        $rate = number_format($row['rate_per_unit'], 2, '.', '');
        $unit = $row['unit_measurement'];

        // Create Stock Item block
        if (!isset($stock_items[$stock_name])) {
            $stock_items[$stock_name] = true;
            $xml .= "\n<TALLYMESSAGE>\n<STOCKITEM NAME=\"$stock_name\">\n<NAME>$stock_name</NAME>\n<PARENT>gold</PARENT>\n<BASEUNITS>$unit</BASEUNITS>\n</STOCKITEM>\n</TALLYMESSAGE>";
        }

        // Create Ledger block
        if (!isset($ledger_names[$supplier])) {
            $ledger_names[$supplier] = true;
            $xml .= "\n<TALLYMESSAGE>\n<LEDGER NAME=\"$supplier\">\n<NAME>$supplier</NAME>\n<PARENT>Sundry Creditors</PARENT>\n<ISBILLWISEON>Yes</ISBILLWISEON>\n<TAXREGISTRATIONNUMBER>$gstin</TAXREGISTRATIONNUMBER>\n</LEDGER>\n</TALLYMESSAGE>";
        }

        // Create Voucher block
        $xml .= "\n<TALLYMESSAGE>\n<VOUCHER VCHTYPE=\"Purchase\" ACTION=\"Create\">\n<DATE>$invoice_date</DATE>\n<REFERENCEDATE>$invoice_date</REFERENCEDATE>\n<REFERENCE>$row[invoice_number]</REFERENCE>\n<PARTYLEDGERNAME>$supplier</PARTYLEDGERNAME>\n<ISINVOICE>Yes</ISINVOICE>\n<LEDGERENTRIES.LIST>\n<LEDGERNAME>$supplier</LEDGERNAME>\n<AMOUNT>-" . ($amount + $gst) . "</AMOUNT>\n</LEDGERENTRIES.LIST>\n<ALLINVENTORYENTRIES.LIST>\n<STOCKITEMNAME>$stock_name</STOCKITEMNAME>\n<ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>\n<ACTUALQTY>$qty $unit</ACTUALQTY>\n<BILLEDQTY>$qty $unit</BILLEDQTY>\n<RATE>$rate/$unit</RATE>\n<AMOUNT>$amount</AMOUNT>\n</ALLINVENTORYENTRIES.LIST>\n<LEDGERENTRIES.LIST>\n<LEDGERNAME>Input CGST</LEDGERNAME>\n<AMOUNT>-" . ($gst/2) . "</AMOUNT>\n</LEDGERENTRIES.LIST>\n<LEDGERENTRIES.LIST>\n<LEDGERNAME>Input SGST</LEDGERNAME>\n<AMOUNT>-" . ($gst/2) . "</AMOUNT>\n</LEDGERENTRIES.LIST>\n</VOUCHER>\n</TALLYMESSAGE>";
    }

    $xml .= '</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>';

    header('Content-Type: text/xml');
    header('Content-Disposition: attachment;filename="tally_gst_purchase.xml"');
    echo $xml;
    exit();
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

if (isset($_GET['action'])) {
    $data = fetch_gst_data($conn, $from, $to);
    if ($_GET['action'] === 'excel') generate_excel($data);
    if ($_GET['action'] === 'xml') generate_tally_xml($data);
}

$data = fetch_gst_data($conn, $from, $to);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GST Purchase Report Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        body { background-color: #f9f9f9; }
        h2 { font-weight: bold; }
        .table th, .table td { vertical-align: middle; font-size: 14px; }
    </style>
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4 text-primary"><i class="bi bi-file-earmark-bar-graph"></i> GST Purchase Report Generator</h2>
    <form class="row g-3 mb-3" method="get">
        <div class="col-md-4">
            <label class="form-label">From Date</label>
            <input type="date" name="from" value="<?=htmlspecialchars($from)?>" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">To Date</label>
            <input type="date" name="to" value="<?=htmlspecialchars($to)?>" class="form-control" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">🔍 Show Report</button>
        </div>
    </form>
    <div class="mb-3">
        <a class="btn btn-success" href="?action=excel&from=<?=urlencode($from)?>&to=<?=urlencode($to)?>">📥 Export to Excel</a>
        <a class="btn btn-secondary" href="?action=xml&from=<?=urlencode($from)?>&to=<?=urlencode($to)?>">📤 Export to Tally XML</a>
        <button class="btn btn-warning ms-2" onclick="exportTableToExcel()">🧾 Client-Side Excel</button>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Date</th><th>Invoice No</th><th>Supplier</th><th>Supplier GSTIN</th>
                    <th>HSN</th><th>Material</th><th>Purity</th><th>Qty</th><th>Unit</th>
                    <th>Rate</th><th>Taxable</th><th>GST %</th><th>GST Amt</th><th>Total</th><th>Stock Name</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="15" class="text-center">No data found.</td></tr>
            <?php else: ?>
                <?php foreach ($data as $row): ?>
                <tr>
                    <td><?=htmlspecialchars($row['purchase_date'])?></td>
                    <td><?=htmlspecialchars($row['invoice_number'])?></td>
                    <td><?=htmlspecialchars($row['supplier_name'])?></td>
                    <td><?=htmlspecialchars($row['supplier_gstin'])?></td>
                    <td><?=htmlspecialchars($row['hsn_code'])?></td>
                    <td><?=htmlspecialchars($row['material_type'])?></td>
                    <td><?=htmlspecialchars($row['purity'])?></td>
                    <td><?=htmlspecialchars($row['quantity'])?></td>
                    <td><?=htmlspecialchars($row['unit_measurement'])?></td>
                    <td><?=htmlspecialchars($row['rate_per_unit'])?></td>
                    <td><?=htmlspecialchars($row['total_amount'])?></td>
                    <td><?=htmlspecialchars($row['gst_percent'])?></td>
                    <td><?=htmlspecialchars($row['gst_amount'])?></td>
                    <td><?=htmlspecialchars($row['total_amount'] + $row['gst_amount'])?></td>
                    <td><?=htmlspecialchars($row['stock_name'])?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function exportTableToExcel() {
    var wb = XLSX.utils.table_to_book(document.querySelector("table"), {sheet: "GST Purchases"});
    XLSX.writeFile(wb, "GST_Purchase_Client.xlsx");
}
</script>
</body>
</html>
