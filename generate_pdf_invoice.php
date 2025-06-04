<?php
// Fixed Invoice PDF Generator
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in PDF
set_time_limit(60);

// Clear all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

try {
    // Load required files
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    $config = require_once('config/database.php');
    
    // Database connection
    $conn = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name']
    );
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    
    // Validate invoice ID
    if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id'])) {
        throw new Exception("Invalid invoice ID");
    }
    
    $invoice_id = (int)$_GET['invoice_id'];
    
    // Simple PDF class without complex header/footer
    class SimpleInvoicePDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, 'TAX INVOICE', 0, false, 'C');
            $this->Ln(20);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C');
        }
    }
    
    // Create PDF
    $pdf = new SimpleInvoicePDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document info
    $pdf->SetCreator('Invoice System');
    $pdf->SetAuthor('Your Company');
    $pdf->SetTitle('Invoice #' . $invoice_id);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add page
    $pdf->AddPage();
    
    // Get invoice data
    $sql = "SELECT s.*, c.FirstName, c.LastName, c.Address as customer_address, c.PhoneNumber as customer_phone, c.GSTNumber as customer_gst,
            f.FirmName as firm_name, f.Address as firm_address, f.PhoneNumber as firm_phone, f.GSTNumber as firm_gst 
            FROM jewellery_sales s 
            LEFT JOIN customer c ON s.customer_id = c.id 
            LEFT JOIN firm f ON s.firm_id = f.id 
            WHERE s.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    
    if (!$invoice) {
        throw new Exception("Invoice not found");
    }
    
    // Company Info
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, $invoice['firm_name'] ?? 'N/A', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, $invoice['firm_address'] ?? '', 0, 1, 'L');
    $pdf->Cell(0, 6, 'Phone: ' . ($invoice['firm_phone'] ?? ''), 0, 1, 'L');
    $pdf->Cell(0, 6, 'GST: ' . ($invoice['firm_gst'] ?? ''), 0, 1, 'L');
    
    // Customer Info
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Bill To:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, ($invoice['FirstName'] ?? '') . ' ' . ($invoice['LastName'] ?? ''), 0, 1, 'L');
    $pdf->Cell(0, 6, $invoice['customer_address'] ?? '', 0, 1, 'L');
    $pdf->Cell(0, 6, 'Phone: ' . ($invoice['customer_phone'] ?? ''), 0, 1, 'L');
    
    // Invoice Details
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Invoice Details:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Invoice #: ' . ($invoice['invoice_no'] ?? ''), 0, 1, 'L');
    $pdf->Cell(0, 6, 'Date: ' . (isset($invoice['sale_date']) ? date('d-m-Y', strtotime($invoice['sale_date'])) : ''), 0, 1, 'L');
    
    // Items Table
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 10);
    
    // Simple table header
    $pdf->Cell(15, 8, 'S.No', 1, 0, 'C');
    $pdf->Cell(50, 8, 'Description', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Weight', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Rate', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Making', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Total', 1, 1, 'C');
    
    // Get items
    $sql = "SELECT * FROM jewellery_sales_items WHERE sale_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pdf->SetFont('helvetica', '', 9);
    $row = 1;
    while($item = $result->fetch_assoc()) {
        $pdf->Cell(15, 6, $row, 1, 0, 'C');
        $pdf->Cell(50, 6, substr($item['product_name'] ?? '', 0, 20), 1, 0, 'L');
        $pdf->Cell(25, 6, number_format($item['net_weight'] ?? 0, 3), 1, 0, 'R');
        $pdf->Cell(25, 6, number_format($item['rate_24k'] ?? 0, 0), 1, 0, 'R');
        $pdf->Cell(25, 6, number_format($item['making_charges'] ?? 0, 0), 1, 0, 'R');
        $pdf->Cell(30, 6, number_format($item['total'] ?? 0, 2), 1, 1, 'R');
        $row++;
    }
    
    // Totals
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(140, 7, 'Sub Total:', 0, 0, 'R');
    $pdf->Cell(30, 7, number_format($invoice['subtotal'] ?? 0, 2), 1, 1, 'R');
    
    if (($invoice['gst_amount'] ?? 0) > 0) {
        $pdf->Cell(140, 7, 'GST Amount:', 0, 0, 'R');
        $pdf->Cell(30, 7, number_format($invoice['gst_amount'], 2), 1, 1, 'R');
    }
    
    $pdf->Cell(140, 7, 'Grand Total:', 0, 0, 'R');
    $pdf->Cell(30, 7, number_format($invoice['grand_total'] ?? 0, 2), 1, 1, 'R');
    
    // Terms
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Terms and Conditions:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->MultiCell(0, 4, "1. All prices are inclusive of applicable taxes.\n2. Goods once sold will not be taken back.\n3. This is a computer generated invoice.", 0, 'L');
    
    // Close database
    $conn->close();
    
    // Output PDF with proper headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Invoice_' . $invoice_id . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $pdf->Output('Invoice_' . $invoice_id . '.pdf', 'S');
    
} catch (Exception $e) {
    // Error handling
    header('Content-Type: text/html');
    echo "<h3>PDF Generation Error</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please contact system administrator.</p>";
}
?>