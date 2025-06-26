<?php
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Prevent any output before PDF generation
ob_start();

// Include TCPDF and its dependencies
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf_autoconfig.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/include/tcpdf_font_data.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/include/tcpdf_fonts.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/include/tcpdf_colors.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/include/tcpdf_images.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/include/tcpdf_static.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

// Remove namespace usage
// use TCPDF\TCPDF;

function generateOrderPDF($order, $printType = 'customer') {
    // Enable error logging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Log the start of PDF generation
    error_log("Starting PDF generation for order #" . ($order['order_number'] ?? 'unknown'));
    
    try {
        // Clear any previous output
        ob_clean();
        
        // Create new PDF document using global namespace
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        error_log("TCPDF instance created successfully");
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($order['firm_name']);
        $pdf->SetTitle('Order #' . $order['order_number']);
        error_log("PDF document information set");

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add a page
        $pdf->AddPage();

        // Set font - Use a Unicode font that supports the Rupee symbol
        $pdf->SetFont('dejavusans', '', 10); // Changed font to dejavusans

        // Company Details
        $pdf->SetFont('dejavusans', 'B', 14); // Changed font
        $pdf->Cell(0, 10, $order['firm_name'], 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10); // Changed font
        $pdf->Cell(0, 5, $order['firm_address'], 0, 1, 'C');
        $pdf->Cell(0, 5, 'Phone: ' . $order['firm_phone'], 0, 1, 'C');
        if ($order['firm_email']) {
            $pdf->Cell(0, 5, 'Email: ' . $order['firm_email'], 0, 1, 'C');
        }
        if ($order['firm_gst']) {
            $pdf->Cell(0, 5, 'GST: ' . $order['firm_gst'], 0, 1, 'C');
        }
        if ($order['firm_pan']) {
            $pdf->Cell(0, 5, 'PAN: ' . $order['firm_pan'], 0, 1, 'C');
        }
        $pdf->Ln(8); // Increased line break after company details

        // Bank Details (if available)
        if ($order['BankAccountNumber'] && $order['BankName']) {
            $pdf->SetFont('dejavusans', 'B', 12); // Changed font
            $pdf->Cell(0, 10, 'Bank Details', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 10); // Changed font
            $pdf->Cell(40, 7, 'Bank Name:', 0, 0);
            $pdf->Cell(0, 7, $order['BankName'], 0, 1);
            $pdf->Cell(40, 7, 'Account No:', 0, 0);
            $pdf->Cell(0, 7, $order['BankAccountNumber'], 0, 1);
            if ($order['IFSCCode']) {
                $pdf->Cell(40, 7, 'IFSC Code:', 0, 0);
                $pdf->Cell(0, 7, $order['IFSCCode'], 0, 1);
            }
            if ($order['AccountType']) {
                $pdf->Cell(40, 7, 'Account Type:', 0, 0);
                $pdf->Cell(0, 7, $order['AccountType'], 0, 1);
            }
            $pdf->Ln(8); // Increased line break after bank details
        }

        // Order Details Header
        $pdf->SetFont('dejavusans', 'B', 12); // Changed font
        $pdf->Cell(0, 10, 'Order Details', 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 10); // Changed font

        // Order Information
        $pdf->Cell(40, 7, 'Order Number:', 0, 0);
        $pdf->Cell(0, 7, $order['order_number'], 0, 1);
        $pdf->Cell(40, 7, 'Order Date:', 0, 0);
        $pdf->Cell(0, 7, date('d-m-Y', strtotime($order['created_at'])), 0, 1);
        $pdf->Cell(40, 7, 'Status:', 0, 0);
        $pdf->Cell(0, 7, ucfirst($order['order_status']), 0, 1);
        $pdf->Ln(8); // Increased line break after order info

        // Customer Details (only for customer view)
        if ($printType === 'customer') {
            $pdf->SetFont('dejavusans', 'B', 12); // Changed font
            $pdf->Cell(0, 10, 'Customer Details', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 10); // Changed font
            
            $pdf->Cell(40, 7, 'Name:', 0, 0);
            $pdf->Cell(0, 7, $order['FirstName'] . ' ' . $order['LastName'], 0, 1);
            $pdf->Cell(40, 7, 'Phone:', 0, 0);
            $pdf->Cell(0, 7, $order['PhoneNumber'], 0, 1);
            if ($order['Email']) {
                $pdf->Cell(40, 7, 'Email:', 0, 0);
                $pdf->Cell(0, 7, $order['Email'], 0, 1);
            }
            if ($order['Address']) {
                $pdf->Cell(40, 7, 'Address:', 0, 0);
                $pdf->Cell(0, 7, $order['Address'], 0, 1);
            }
            $pdf->Ln(8); // Increased line break after customer details
        }

        // Karigar Details (only for karigar view)
        if ($printType === 'karigar') {
            $pdf->SetFont('dejavusans', 'B', 12); // Changed font
            $pdf->Cell(0, 10, 'Karigar Details', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 10); // Changed font
            
            if ($order['karigar_name']) {
                $pdf->Cell(40, 7, 'Name:', 0, 0);
                $pdf->Cell(0, 7, $order['karigar_name'], 0, 1);
                $pdf->Cell(40, 7, 'Phone:', 0, 0);
                $pdf->Cell(0, 7, $order['karigar_phone'], 0, 1);
            }
            $pdf->Ln(8); // Increased line break after karigar details
        }

        // Items Table Header
        $pdf->SetFont('dejavusans', 'B', 12); // Changed font
        $pdf->Cell(0, 10, 'Order Items', 0, 1, 'L');
        $pdf->SetFont('dejavusans', 'B', 10); // Changed font

        // Table Header and widths based on printType
        if ($printType === 'customer') {
            $header = array('Item', 'Details', 'Weight', 'Making', 'Total');
            $w = array(40, 75, 20, 25, 30); // Adjusted widths for customer view
        } else { // karigar view
            $header = array('Item', 'Details');
            $w = array(60, 135); // Adjusted widths for karigar view (no amounts)
        }

        $pdf->SetFillColor(240, 240, 240);
        for($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Table Content
        $pdf->SetFont('dejavusans', '', 10); // Changed font
        $pdf->SetFillColor(255, 255, 255);
        $fill = false;

        foreach($order['items'] as $item) {
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $current_page = $pdf->getPage();

            // Calculate estimated height for Item Name
            $item_name_text = '<strong>' . $item['item_name'] . '</strong>';
            $item_name_height = $pdf->getStringHeight($w[0] - 4, $item_name_text, false, true, '', 1); // 4 for left/right padding

            // Calculate estimated height for Details
            $details = array();
            if ($item['product_type']) $details[] = "Type: " . $item['product_type'];
            if ($item['metal_type']) $details[] = "Metal: " . $item['metal_type'];
            if ($item['purity']) $details[] = $item['purity'] . "K";
            if ($item['stone_type']) $details[] = "Stone: " . $item['stone_type'];
            if ($item['stone_quantity']) $details[] = "Qty: " . $item['stone_quantity'];
            if ($item['net_weight'] && $printType !== 'karigar') $details[] = "Net Wt: " . number_format($item['net_weight'], 3) . 'g'; 
            if ($item['design_customization']) $details[] = "Note: " . $item['design_customization'];
            if ($item['karigar_name'] && $printType === 'karigar') $details[] = "Karigar: " . $item['karigar_name'] . ($item['karigar_phone'] ? " (" . $item['karigar_phone'] . ")" : "");
            $details_string = implode("\n", $details);
            $details_height = $pdf->getStringHeight($w[1], $details_string, false, true, '', 2); // 2 for line spacing

            // Calculate total height for stacked images
            $image_display_height = 0;
            $imageWidth = 12; 
            $imageHeight = 12; 
            $image_padding = 1; 
            $num_images = 0;

            if (isset($item['images']) && is_array($item['images']) && count($item['images']) > 0) {
                $num_images = count($item['images']);
                $image_display_height = ($imageHeight * $num_images) + (($num_images > 1 ? $num_images - 1 : 0) * $image_padding); 
            }

            // Calculate overall row height
            $content_height_item_col = $item_name_height + ($num_images > 0 ? $image_display_height + $image_padding : 0); // Add padding below images
            $row_height = max(6, $content_height_item_col + 2, $details_height + 2); // Minimum 6mm, plus padding

            // Save current position for drawing cells
            $current_x = $pdf->GetX();
            $current_y = $pdf->GetY();

            // --- ITEM NAME COLUMN (write HTML content to allow strong tag) ---
            // The writeHTMLCell method handles internal padding. So, adjust $w[0] to its actual content width.
            $pdf->writeHTMLCell($w[0], $row_height, $current_x, $current_y, $item_name_text, 'LRT', 0, $fill, true, 'L', true, false, true, 'M'); // Align Middle
            
            // --- IMAGES (stacked vertically) ---
            $current_y_for_images = $current_y + $item_name_height + 1; // Start images below item name, with a little space
            if ($num_images > 0) {
                foreach ($item['images'] as $img_idx => $imagePath) {
                    $imageFilePath = __DIR__ . '/../' . $imagePath; 
                    if (file_exists($imageFilePath)) {
                        $img_x = $current_x + ($w[0] / 2) - ($imageWidth / 2); // Center image horizontally in column
                        $pdf->Image($imageFilePath, $img_x, $current_y_for_images, $imageWidth, $imageHeight, '', '', '', false, 300, '', false, false, 0, false, false, false);
                        $current_y_for_images += $imageHeight + $image_padding; 
                    }
                }
            }

            // --- DETAILS COLUMN ---
            $pdf->MultiCell($w[1], $row_height, $details_string, 'RT', 'L', $fill, 0, $current_x + $w[0], $current_y, true, 0, true, true, $row_height, 'M'); // Align Middle
            
            // --- WEIGHT, MAKING, TOTAL COLUMNS (CUSTOMER VIEW) ---
            if ($printType === 'customer') {
                $pdf->MultiCell($w[2], $row_height, number_format($item['net_weight'], 3) . 'g', 'RT', 'R', $fill, 0, $current_x + $w[0] + $w[1], $current_y, true, 0, false, true, $row_height, 'M'); // Align Middle
                $pdf->MultiCell($w[3], $row_height, '₹' . number_format($item['making_charges'], 2), 'RT', 'R', $fill, 0, $current_x + $w[0] + $w[1] + $w[2], $current_y, true, 0, false, true, $row_height, 'M'); // Align Middle
                $pdf->MultiCell($w[4], $row_height, '₹' . number_format($item['total_estimate'], 2), 'RT', 'R', $fill, 0, $current_x + $w[0] + $w[1] + $w[2] + $w[3], $current_y, true, 0, false, true, $row_height, 'M'); // Align Middle
            }

            // Move to the next line for the next row, based on calculated row_height
            $pdf->SetY($current_y + $row_height);
            $fill = !$fill;
        }

        // Closing line for the table
        if ($printType === 'customer') {
            $pdf->Cell(array_sum($w), 0, '', 'T');
        } else {
            $pdf->Cell($w[0] + $w[1], 0, '', 'T');
        }

        // Order Summary (only for customer view)
        if ($printType === 'customer') {
            $pdf->SetY($pdf->GetY() + 5); // Add spacing before summary
            $pdf->SetFont('dejavusans', 'B', 10); 
            
            $total_width_for_summary = array_sum($w); 
            $col1_width_summary = $total_width_for_summary - 60; 
            $col2_width_summary = 30; 
            $col3_width_summary = 30; 

            $pdf->Cell($col1_width_summary, 7, '', 0, 0);
            $pdf->Cell($col2_width_summary, 7, 'Total Amount:', 0, 0, 'R');
            $pdf->Cell($col3_width_summary, 7, '₹' . number_format($order['grand_total'], 2), 0, 1, 'R');
            
            $pdf->Cell($col1_width_summary, 7, '', 0, 0);
            $pdf->Cell($col2_width_summary, 7, 'Advance Paid:', 0, 0, 'R');
            $pdf->Cell($col3_width_summary, 7, '₹' . number_format($order['advance_amount'], 2), 0, 1, 'R');
            
            $pdf->Cell($col1_width_summary, 7, '', 0, 0);
            $pdf->Cell($col2_width_summary, 7, 'Balance Due:', 0, 0, 'R');
            $pdf->Cell($col3_width_summary, 7, '₹' . number_format($order['remaining_amount'], 2), 0, 1, 'R');
        }

        // Terms and Conditions
        $pdf->SetY($pdf->GetY() + 8); // Add spacing before terms
        $pdf->SetFont('dejavusans', 'B', 10); 
        $pdf->Cell(0, 7, 'Terms and Conditions:', 0, 1);
        $pdf->SetFont('dejavusans', '', 9); 
        $pdf->MultiCell(0, 5, "1. All prices are inclusive of applicable taxes.\n2. Advance payment is non-refundable.\n3. Delivery date is subject to work completion.\n4. Quality check will be done before delivery.", 0, 'L');

        // Footer
        $pdf->SetY($pdf->GetY() + 8); // Add spacing before footer
        $pdf->SetFont('dejavusans', 'I', 8); 
        $pdf->Cell(0, 5, 'This is a computer generated document. No signature required.', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated on: ' . date('d-m-Y H:i:s'), 0, 1, 'C');

        // Output the PDF
        $pdf->Output('order_' . $order['order_number'] . '.pdf', 'I');
        exit;
    } catch (\Exception $e) {
        error_log("PDF generation failed: " . $e->getMessage());
        throw $e;
    }
}

// Added this to ensure output encoding
ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');
?> 