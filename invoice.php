<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewellery Invoice</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
                margin: 0;
                padding: 0;
                font-size: 12px;
            }
            .print-hide { display: none !important; }
            .page-break { page-break-after: always; }
            @page { 
                size: A4; 
                margin: 0.4in; 
            }
            .invoice-container {
                box-shadow: none !important;
                margin: 0 !important;
                max-width: none !important;
            }
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 8rem;
            font-weight: 900;
            color: rgba(0, 0, 0, 0.02);
            z-index: 0;
            pointer-events: none;
            user-select: none;
            letter-spacing: 0.3rem;
        }
        
        .content-layer {
            position: relative;
            z-index: 1;
            background: white;
        }
        
        .logo-placeholder {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            border: 2px dashed #d1d5db;
        }
        
        .bis-logo {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
    <!-- Watermark -->
    <div class="watermark">CERTIFIED</div>

    <!-- Control Panel (Hidden in Print) -->
    <div class="print-hide bg-white shadow-sm border-b p-4 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto flex flex-wrap gap-3 items-center justify-between">
            <div class="flex gap-3">
                <button onclick="loadSampleData()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    Load Sample Data
                </button>
                <button onclick="window.print()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    🖨️ Print Invoice
                </button>
                <button onclick="generatePDF()" 
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    📄 Generate PDF
                </button>
            </div>
            <div class="text-sm text-gray-600">
                <span id="invoice-status" class="font-medium">Ready to Print</span>
            </div>
        </div>
    </div>

    <!-- Invoice Container -->
    <div class="content-layer invoice-container max-w-5xl mx-auto bg-white shadow-lg my-4 print:my-0 print:shadow-none">
        <!-- Header Section -->
        <div class="border-b-4 border-gray-800 p-6 print:p-4">
            <div class="flex items-start justify-between mb-4">
                <!-- Firm Logo -->
                <div class="flex-shrink-0">
                    <div id="firm-logo-container" class="logo-placeholder w-24 h-24 flex items-center justify-center rounded-lg">
                        <span class="text-gray-500 text-xs font-medium">LOGO</span>
                    </div>
                </div>
                
                <!-- Invoice Title & Details -->
                <div class="text-center flex-1 mx-8">
                    <h1 id="invoice-title" class="text-4xl print:text-3xl font-bold text-gray-900 mb-2">TAX INVOICE</h1>
                    <div class="bg-gray-100 inline-block px-4 py-2 rounded-lg">
                        <p class="text-sm font-semibold">Invoice No: <span id="invoice-no" class="text-blue-600">INV-2024-001</span></p>
                        <p class="text-sm">Date: <span id="sale-date" class="font-medium">05-Jun-2024</span></p>
                    </div>
                </div>
                
                <!-- BIS Logo -->
                <div class="flex-shrink-0">
                    <div class="bis-logo w-20 h-20 rounded-full flex items-center justify-center text-white">
                        <div class="text-center">
                            <div class="text-lg font-bold">BIS</div>
                            <div class="text-xs">CERTIFIED</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Company & Customer Details -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6 print:p-4 bg-gray-50 print:bg-white">
            <!-- Firm Details -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center mb-3">
                    <div class="w-3 h-6 bg-blue-600 rounded-r mr-3"></div>
                    <h3 class="text-lg font-bold text-gray-900">From</h3>
                </div>
                <div id="firm-details" class="space-y-1">
                    <p class="text-xl font-bold text-gray-900" id="firm-name">Shree Jewellers Pvt Ltd</p>
                    <p class="text-sm text-gray-600" id="firm-address">123 Gold Street, Zaveri Bazaar, Mumbai - 400001</p>
                    <p class="text-sm text-gray-600" id="firm-phone">📞 +91 9876543210</p>
                    <p class="text-sm text-gray-600" id="firm-gst">🏢 GST: 27ABCDE1234F1Z5</p>
                </div>
            </div>
            
            <!-- Customer Details -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center mb-3">
                    <div class="w-3 h-6 bg-green-600 rounded-r mr-3"></div>
                    <h3 class="text-lg font-bold text-gray-900">Bill To</h3>
                </div>
                <div id="customer-details" class="space-y-1">
                    <p class="text-xl font-bold text-gray-900" id="customer-name">Mr. Rajesh Kumar</p>
                    <p class="text-sm text-gray-600" id="customer-address">456 Silver Lane, Andheri West, Mumbai - 400058</p>
                    <p class="text-sm text-gray-600" id="customer-phone">📞 +91 9123456789</p>
                    <p class="text-sm text-gray-600" id="customer-gst">🏢 GST: 27FGHIJ5678K2L9</p>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="p-6 print:p-4">
            <div class="flex items-center mb-4">
                <div class="w-4 h-6 bg-purple-600 rounded-r mr-3"></div>
                <h3 class="text-xl font-bold text-gray-900">Item Details</h3>
            </div>
            
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold border-r border-gray-600">S.No</th>
                            <th class="px-3 py-3 text-left font-semibold border-r border-gray-600">Product Description</th>
                            <th class="px-3 py-3 text-center font-semibold border-r border-gray-600">HUID</th>
                            <th class="px-3 py-3 text-center font-semibold border-r border-gray-600">Purity</th>
                            <th class="px-3 py-3 text-center font-semibold border-r border-gray-600">Gross Wt.</th>
                            <th class="px-3 py-3 text-center font-semibold border-r border-gray-600">Net Wt.</th>
                            <th class="px-3 py-3 text-center font-semibold border-r border-gray-600">Rate/gm</th>
                            <th class="px-3 py-3 text-center font-semibold border-r border-gray-600">Stone Details</th>
                            <th class="px-3 py-3 text-center font-semibold border-r border-gray-600">Making</th>
                            <th class="px-3 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody id="items-table-body" class="bg-white">
                        <!-- Items will be populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 p-6 print:p-4 bg-gray-50 print:bg-white">
            <!-- Payment Information -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg border border-gray-200 p-4 h-full">
                    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                        <div class="w-2 h-4 bg-yellow-500 rounded-r mr-2"></div>
                        Payment Info
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Method:</span>
                            <span id="payment-method" class="font-medium">Cash</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span id="payment-status" class="px-2 py-1 rounded text-xs bg-green-100 text-green-800 font-medium">Paid</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Type:</span>
                            <span id="transaction-type" class="font-medium">Sale</span>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t border-gray-200" id="notes-section">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Notes:</h5>
                        <p id="notes" class="text-xs text-gray-600 bg-gray-50 p-2 rounded">Thank you for choosing us!</p>
                    </div>
                </div>
            </div>
            
            <!-- Amount Summary -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                        <div class="w-2 h-4 bg-indigo-500 rounded-r mr-2"></div>
                        Amount Summary
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Breakdown -->
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between py-1">
                                <span class="text-gray-600">Metal Amount:</span>
                                <span id="total-metal-amount" class="font-medium">₹50,000.00</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-gray-600">Stone Amount:</span>
                                <span id="total-stone-amount" class="font-medium">₹15,000.00</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-gray-600">Making Charges:</span>
                                <span id="total-making-charges" class="font-medium">₹8,000.00</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-gray-600">Other Charges:</span>
                                <span id="total-other-charges" class="font-medium">₹1,000.00</span>
                            </div>
                            <div class="flex justify-between py-1 border-t border-gray-200 pt-2">
                                <span class="font-medium">Subtotal:</span>
                                <span id="subtotal" class="font-bold">₹74,000.00</span>
                            </div>
                        </div>
                        
                        <!-- Final Amount -->
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between py-1" id="discount-row">
                                <span class="text-red-600">Total Discount:</span>
                                <span id="discount" class="text-red-600 font-medium">-₹2,000.00</span>
                            </div>
                            <div class="flex justify-between py-1" id="gst-row">
                                <span class="text-gray-600">GST (3%):</span>
                                <span id="gst-amount" class="font-medium">₹2,160.00</span>
                            </div>
                            <div class="flex justify-between py-2 border-t-2 border-gray-800 mt-2 pt-2">
                                <span class="text-lg font-bold">Grand Total:</span>
                                <span id="grand-total" class="text-lg font-bold text-indigo-600">₹74,160.00</span>
                            </div>
                            <div class="flex justify-between py-1 bg-green-50 px-2 rounded">
                                <span class="text-green-700 font-medium">Paid Amount:</span>
                                <span id="total-paid-amount" class="text-green-700 font-bold">₹74,160.00</span>
                            </div>
                            <div class="flex justify-between py-1 bg-red-50 px-2 rounded" id="due-row">
                                <span class="text-red-700 font-medium">Due Amount:</span>
                                <span id="due-amount" class="text-red-700 font-bold">₹0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t-2 border-gray-800 p-6 print:p-4 text-center bg-gray-50 print:bg-white">
            <div class="space-y-2">
                <p class="text-sm font-semibold text-gray-700">This is a computer-generated invoice. No signature required.</p>
                <p class="text-xs text-gray-500">Thank you for choosing us for your precious jewelry needs!</p>
                <div class="flex justify-center items-center space-x-4 text-xs text-gray-400 mt-3">
                    <span>💎 Premium Quality</span>
                    <span>✓ BIS Certified</span>
                    <span>🛡️ Lifetime Guarantee</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Comprehensive sample data matching your database structure
        const sampleInvoiceData = {
            sale: {
                id: 1,
                invoice_no: "INV-2024-001",
                firm_id: 1,
                customer_id: 1,
                sale_date: "2024-06-05",
                total_metal_amount: 50000.00,
                total_stone_amount: 15000.00,
                total_making_charges: 8000.00,
                total_other_charges: 1000.00,
                discount: 2000.00,
                urd_amount: 0.00,
                subtotal: 72000.00,
                gst_amount: 2160.00,
                grand_total: 74160.00,
                total_paid_amount: 74160.00,
                advance_amount: 0.00,
                due_amount: 0.00,
                payment_status: "Paid",
                payment_method: "Cash",
                is_gst_applicable: 1,
                notes: "Premium quality 22K gold jewelry with certified diamonds. Thank you for your business!",
                user_id: 1,
                coupon_discount: 500.00,
                loyalty_discount: 1000.00,
                manual_discount: 500.00,
                coupon_code: "GOLD20",
                transaction_type: "Sale"
            },
            firm: {
                id: 1,
                name: "Shree Jewellers Pvt Ltd",
                address: "123 Gold Street, Zaveri Bazaar, Mumbai - 400001, Maharashtra, India",
                phone: "+91 9876543210",
                gst_number: "27ABCDE1234F1Z5",
                logo_path: "/images/firm-logo.png"
            },
            customer: {
                id: 1,
                name: "Mr. Rajesh Kumar",
                address: "456 Silver Lane, Andheri West, Mumbai - 400058, Maharashtra, India",
                phone: "+91 9123456789",
                gst_number: "27FGHIJ5678K2L9"
            },
            items: [
                {
                    id: 1,
                    sale_id: 1,
                    product_id: 101,
                    product_name: "22K Gold Traditional Necklace Set with Diamonds",
                    huid_code: "HUID123456789",
                    rate_24k: 6800.00,
                    purity: "22K",
                    purity_rate: 6200.00,
                    gross_weight: 28.50,
                    less_weight: 2.50,
                    net_weight: 26.00,
                    metal_amount: 35000.00,
                    stone_type: "Diamond",
                    stone_weight: 3.25,
                    stone_price: 12000.00,
                    making_type: "Handmade",
                    making_rate: 280.00,
                    making_charges: 7000.00,
                    hm_charges: 650.00,
                    other_charges: 350.00,
                    total_charges: 8000.00,
                    total: 55000.00
                },
                {
                    id: 2,
                    sale_id: 1,
                    product_id: 102,
                    product_name: "22K Gold Designer Earrings with Ruby",
                    huid_code: "HUID987654321",
                    rate_24k: 6800.00,
                    purity: "22K",
                    purity_rate: 6200.00,
                    gross_weight: 12.80,
                    less_weight: 0.80,
                    net_weight: 12.00,
                    metal_amount: 15000.00,
                    stone_type: "Ruby",
                    stone_weight: 1.50,
                    stone_price: 3000.00,
                    making_type: "Machine",
                    making_rate: 150.00,
                    making_charges: 1000.00,
                    hm_charges: 120.00,
                    other_charges: 40.00,
                    total_charges: 1160.00,
                    total: 19160.00
                }
            ]
        };

        // Utility function for currency formatting
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount || 0);
        }

        // Format date for display
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        // Main function to populate invoice data
        function populateInvoiceData(data) {
            try {
                // Update header information
                document.getElementById('invoice-title').textContent = 
                    data.sale.is_gst_applicable ? 'TAX INVOICE' : 'PROFORMA INVOICE';
                document.getElementById('invoice-no').textContent = data.sale.invoice_no;
                document.getElementById('sale-date').textContent = formatDate(data.sale.sale_date);

                // Populate firm details
                document.getElementById('firm-name').textContent = data.firm.name;
                document.getElementById('firm-address').textContent = data.firm.address;
                document.getElementById('firm-phone').textContent = `📞 ${data.firm.phone}`;
                document.getElementById('firm-gst').textContent = `🏢 GST: ${data.firm.gst_number}`;

                // Populate customer details
                document.getElementById('customer-name').textContent = data.customer.name;
                document.getElementById('customer-address').textContent = data.customer.address;
                document.getElementById('customer-phone').textContent = `📞 ${data.customer.phone}`;
                document.getElementById('customer-gst').textContent = `🏢 GST: ${data.customer.gst_number}`;

                // Populate items table
                populateItemsTable(data.items);

                // Update payment information
                document.getElementById('payment-method').textContent = data.sale.payment_method;
                document.getElementById('payment-status').textContent = data.sale.payment_status;
                document.getElementById('transaction-type').textContent = data.sale.transaction_type;
                document.getElementById('notes').textContent = data.sale.notes;

                // Update payment status styling
                const statusElement = document.getElementById('payment-status');
                statusElement.className = `px-2 py-1 rounded text-xs font-medium ${
                    data.sale.payment_status === 'Paid' ? 'bg-green-100 text-green-800' : 
                    data.sale.payment_status === 'Partial' ? 'bg-yellow-100 text-yellow-800' : 
                    'bg-red-100 text-red-800'
                }`;

                // Update amounts
                document.getElementById('total-metal-amount').textContent = formatCurrency(data.sale.total_metal_amount);
                document.getElementById('total-stone-amount').textContent = formatCurrency(data.sale.total_stone_amount);
                document.getElementById('total-making-charges').textContent = formatCurrency(data.sale.total_making_charges);
                document.getElementById('total-other-charges').textContent = formatCurrency(data.sale.total_other_charges);
                document.getElementById('subtotal').textContent = formatCurrency(data.sale.subtotal);
                document.getElementById('discount').textContent = `-${formatCurrency(data.sale.discount)}`;
                document.getElementById('gst-amount').textContent = formatCurrency(data.sale.gst_amount);
                document.getElementById('grand-total').textContent = formatCurrency(data.sale.grand_total);
                document.getElementById('total-paid-amount').textContent = formatCurrency(data.sale.total_paid_amount);
                document.getElementById('due-amount').textContent = formatCurrency(data.sale.due_amount);

                // Conditional display of rows
                document.getElementById('gst-row').style.display = data.sale.is_gst_applicable ? 'flex' : 'none';
                document.getElementById('discount-row').style.display = data.sale.discount > 0 ? 'flex' : 'none';
                document.getElementById('due-row').style.display = data.sale.due_amount > 0 ? 'flex' : 'none';

                // Update status
                document.getElementById('invoice-status').textContent = 'Invoice Loaded Successfully';

            } catch (error) {
                console.error('Error populating invoice data:', error);
                document.getElementById('invoice-status').textContent = 'Error Loading Data';
            }
        }

        // Function to populate items table
        function populateItemsTable(items) {
            const tbody = document.getElementById('items-table-body');
            tbody.innerHTML = '';

            items.forEach((item, index) => {
                const row = document.createElement('tr');
                row.className = `border-b border-gray-200 ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}`;
                
                // Stone details formatting
                const stoneDetails = item.stone_type && item.stone_weight ? 
                    `${item.stone_type}<br><span class="text-xs text-gray-500">${item.stone_weight}g @ ${formatCurrency(item.stone_price)}</span>` : 
                    '<span class="text-gray-400 text-xs">N/A</span>';
                
                // Making details formatting
                const makingDetails = `${item.making_type}<br><span class="text-xs text-gray-500">${formatCurrency(item.making_charges)}</span>`;
                
                row.innerHTML = `
                    <td class="px-3 py-3 text-center font-medium border-r border-gray-200">${index + 1}</td>
                    <td class="px-3 py-3 border-r border-gray-200">
                        <div class="font-medium text-gray-900">${item.product_name}</div>
                        <div class="text-xs text-gray-500 mt-1">Product ID: ${item.product_id}</div>
                    </td>
                    <td class="px-3 py-3 text-center text-xs font-mono border-r border-gray-200">${item.huid_code}</td>
                    <td class="px-3 py-3 text-center font-medium border-r border-gray-200">${item.purity}</td>
                    <td class="px-3 py-3 text-center border-r border-gray-200">${item.gross_weight}g</td>
                    <td class="px-3 py-3 text-center font-semibold border-r border-gray-200">${item.net_weight}g</td>
                    <td class="px-3 py-3 text-center border-r border-gray-200">${formatCurrency(item.rate_24k)}</td>
                    <td class="px-3 py-3 text-center text-xs border-r border-gray-200">${stoneDetails}</td>
                    <td class="px-3 py-3 text-center text-xs border-r border-gray-200">${makingDetails}</td>
                    <td class="px-3 py-3 text-right font-bold text-indigo-600">${formatCurrency(item.total)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Function to load sample data
        function loadSampleData() {
            populateInvoiceData(sampleInvoiceData);
        }

        // Public function to load real database data
        function loadInvoiceData(saleData, firmData, customerData, itemsData) {
            const invoiceData = {
                sale: saleData,
                firm: firmData,
                customer: customerData,
                items: itemsData
            };
            populateInvoiceData(invoiceData);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSampleData();
        });

        // Export function for external use
        window.loadInvoiceData = loadInvoiceData;

        function generatePDF() {
            const invoiceNo = document.getElementById('invoice-no').textContent;
            // Extract just the number part from the invoice number
            const invoiceId = invoiceNo.replace(/[^0-9]/g, '');
            console.log('Generating PDF for invoice ID:', invoiceId);
            window.open(`generate_pdf_invoice.php?invoice_id=${invoiceId}`, '_blank');
        }
    </script>
</body>
</html>