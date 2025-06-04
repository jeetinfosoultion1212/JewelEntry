<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Jewelry Invoice</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        @media print {
            body { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
                margin: 0;
                padding: 0;
                font-size: 11px;
            }
            .print-hide { display: none !important; }
            .page-break { page-break-after: always; }
            @page { 
                size: A4; 
                margin: 0.3in; 
            }
            .invoice-container {
                box-shadow: none !important;
                margin: 0 !important;
                max-width: none !important;
            }
            .gradient-bg {
                background: #f8fafc !important;
                color: #1e293b !important;
            }
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 6rem;
            font-weight: 900;
            color: rgba(0, 0, 0, 0.03);
            z-index: 0;
            pointer-events: none;
            user-select: none;
            letter-spacing: 0.2rem;
        }
        
        .content-layer {
            position: relative;
            z-index: 1;
            background: white;
        }
        
        .logo-placeholder {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border: 2px dashed #cbd5e1;
            transition: all 0.3s ease;
        }
        
        .logo-placeholder:hover {
            border-color: #94a3b8;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        }
        
        .bis-logo {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .gradient-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
        }
        
        .gradient-accent {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .invoice-title {
            background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }
        
        .card-shadow:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .status-badge {
            position: relative;
            overflow: hidden;
        }
        
        .status-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }
        
        .status-badge:hover::before {
            left: 100%;
        }
        
        .table-stripe {
            background: linear-gradient(90deg, transparent 0%, rgba(59, 130, 246, 0.05) 50%, transparent 100%);
        }
        
        .amount-highlight {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
        }
        
        .total-highlight {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-left: 4px solid #3b82f6;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 text-slate-900 font-sans">
    <!-- Dynamic Watermark -->
    <div id="watermark" class="watermark">ESTIMATE</div>

    <!-- Enhanced Control Panel -->
    <div class="print-hide glass-effect shadow-sm border-b p-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex flex-wrap gap-4 items-center justify-between">
            <div class="flex gap-3">
                <button onclick="loadSampleData()" 
                        class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <span class="mr-2">📊</span>Load Sample Data
                </button>
                <button onclick="toggleInvoiceType()" 
                        class="bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <span class="mr-2">🔄</span>Toggle GST/Non-GST
                </button>
                <button onclick="window.print()" 
                        class="bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <span class="mr-2">🖨️</span>Print Document
                </button>
            </div>
            <div class="flex items-center gap-4">
                <div class="status-badge px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-full text-sm font-medium">
                    <span id="invoice-status">Ready to Print</span>
                </div>
                <div id="document-type-badge" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full text-sm font-medium">
                    TAX INVOICE
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Invoice Container -->
    <div class="content-layer invoice-container max-w-6xl mx-auto bg-white shadow-2xl my-6 print:my-0 print:shadow-none rounded-lg overflow-hidden">
        
        <!-- Premium Header Section -->
        <div class="gradient-header p-8 print:p-6 text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-black opacity-5"></div>
            <div class="relative z-10">
                <div class="flex items-start justify-between mb-6">
                    <!-- Enhanced Firm Logo -->
                    <div class="flex-shrink-0">
                        <div id="firm-logo-container" class="logo-placeholder w-28 h-28 flex items-center justify-center rounded-xl shadow-lg">
                            <div class="text-center">
                                <div class="text-slate-500 text-lg font-bold mb-1">LOGO</div>
                                <div class="text-slate-400 text-xs">Upload Image</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enhanced Invoice Title & Details -->
                    <div class="text-center flex-1 mx-8">
                        <h1 id="invoice-title" class="text-5xl print:text-4xl font-black mb-4 tracking-tight">
                            <span class="text-white">TAX INVOICE</span>
                        </h1>
                        <div class="glass-effect rounded-xl px-6 py-4 inline-block backdrop-blur-sm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-slate-700">
                                <div class="text-center">
                                    <p class="text-sm font-semibold mb-1">Invoice Number</p>
                                    <p id="invoice-no" class="text-lg font-bold text-blue-600">INV-2024-001</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm font-semibold mb-1">Date</p>
                                    <p id="sale-date" class="text-lg font-bold text-slate-800">05-Jun-2024</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enhanced BIS Logo -->
                    <div class="flex-shrink-0">
                        <div class="bis-logo w-24 h-24 rounded-full flex items-center justify-center text-white">
                            <div class="text-center">
                                <div class="text-xl font-black">BIS</div>
                                <div class="text-xs font-medium">CERTIFIED</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Document Type Indicator -->
                <div class="text-center">
                    <div id="document-type-indicator" class="inline-flex items-center px-4 py-2 bg-white bg-opacity-20 rounded-full text-sm font-medium">
                        <span id="gst-status-icon" class="mr-2">🏢</span>
                        <span id="gst-status-text">GST Applicable - Tax Invoice</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Company & Customer Details -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-8 print:p-6 bg-gradient-to-b from-slate-50 to-white">
            <!-- Enhanced Firm Details -->
            <div class="card-shadow bg-white rounded-2xl border border-slate-200 p-6 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 gradient-accent"></div>
                <div class="flex items-center mb-4">
                    <div class="w-4 h-8 bg-gradient-to-b from-blue-600 to-blue-700 rounded-r mr-4"></div>
                    <h3 class="text-xl font-bold text-slate-900">From</h3>
                </div>
                <div id="firm-details" class="space-y-3">
                    <p class="text-2xl font-bold text-slate-900" id="firm-name">Shree Jewellers Pvt Ltd</p>
                    <p class="text-sm text-slate-600 leading-relaxed" id="firm-address">123 Gold Street, Zaveri Bazaar, Mumbai - 400001</p>
                    <div class="flex items-center text-sm text-slate-600">
                        <span class="mr-2">📞</span>
                        <span id="firm-phone">+91 9876543210</span>
                    </div>
                    <div class="flex items-center text-sm text-slate-600">
                        <span class="mr-2">🏢</span>
                        <span id="firm-gst">GST: 27ABCDE1234F1Z5</span>
                    </div>
                </div>
            </div>
            
            <!-- Enhanced Customer Details -->
            <div class="card-shadow bg-white rounded-2xl border border-slate-200 p-6 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-green-600 to-green-700"></div>
                <div class="flex items-center mb-4">
                    <div class="w-4 h-8 bg-gradient-to-b from-green-600 to-green-700 rounded-r mr-4"></div>
                    <h3 class="text-xl font-bold text-slate-900">Bill To</h3>
                </div>
                <div id="customer-details" class="space-y-3">
                    <p class="text-2xl font-bold text-slate-900" id="customer-name">Mr. Rajesh Kumar</p>
                    <p class="text-sm text-slate-600 leading-relaxed" id="customer-address">456 Silver Lane, Andheri West, Mumbai - 400058</p>
                    <div class="flex items-center text-sm text-slate-600">
                        <span class="mr-2">📞</span>
                        <span id="customer-phone">+91 9123456789</span>
                    </div>
                    <div class="flex items-center text-sm text-slate-600">
                        <span class="mr-2">🏢</span>
                        <span id="customer-gst">GST: 27FGHIJ5678K2L9</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Items Table -->
        <div class="p-8 print:p-6">
            <div class="flex items-center mb-6">
                <div class="w-5 h-8 bg-gradient-to-b from-purple-600 to-purple-700 rounded-r mr-4"></div>
                <h3 class="text-2xl font-bold text-slate-900">Item Details</h3>
            </div>
            
            <div class="card-shadow overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-sm">
                    <thead class="gradient-header text-white">
                        <tr>
                            <th class="px-4 py-4 text-left font-bold border-r border-slate-600">S.No</th>
                            <th class="px-4 py-4 text-left font-bold border-r border-slate-600">Product Description</th>
                            <th class="px-4 py-4 text-center font-bold border-r border-slate-600">HUID</th>
                            <th class="px-4 py-4 text-center font-bold border-r border-slate-600">Purity</th>
                            <th class="px-4 py-4 text-center font-bold border-r border-slate-600">Gross Wt.</th>
                            <th class="px-4 py-4 text-center font-bold border-r border-slate-600">Net Wt.</th>
                            <th class="px-4 py-4 text-center font-bold border-r border-slate-600">Rate/gm</th>
                            <th class="px-4 py-4 text-center font-bold border-r border-slate-600">Stone Details</th>
                            <th class="px-4 py-4 text-center font-bold border-r border-slate-600">Making</th>
                            <th class="px-4 py-4 text-right font-bold">Total</th>
                        </tr>
                    </thead>
                    <tbody id="items-table-body" class="bg-white">
                        <!-- Items will be populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enhanced Summary Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 p-8 print:p-6 bg-gradient-to-b from-white to-slate-50">
            <!-- Enhanced Payment Information -->
            <div class="lg:col-span-1">
                <div class="card-shadow bg-white rounded-2xl border border-slate-200 p-6 h-full relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-yellow-500 to-orange-500"></div>
                    <h4 class="font-bold text-slate-900 mb-4 flex items-center text-lg">
                        <div class="w-3 h-5 bg-gradient-to-b from-yellow-500 to-orange-500 rounded-r mr-3"></div>
                        Payment Information
                    </h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                            <span class="text-slate-600 font-medium">Method:</span>
                            <span id="payment-method" class="font-semibold text-slate-900">Cash</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                            <span class="text-slate-600 font-medium">Status:</span>
                            <span id="payment-status" class="px-3 py-1 rounded-full text-xs bg-green-100 text-green-800 font-bold">Paid</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                            <span class="text-slate-600 font-medium">Type:</span>
                            <span id="transaction-type" class="font-semibold text-slate-900">Sale</span>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-slate-200" id="notes-section">
                        <h5 class="text-sm font-bold text-slate-700 mb-3">Notes:</h5>
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-100">
                            <p id="notes" class="text-sm text-slate-700 leading-relaxed">Thank you for choosing us!</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Enhanced Amount Summary -->
            <div class="lg:col-span-2">
                <div class="card-shadow bg-white rounded-2xl border border-slate-200 p-6 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
                    <h4 class="font-bold text-slate-900 mb-6 flex items-center text-lg">
                        <div class="w-3 h-5 bg-gradient-to-b from-indigo-500 to-purple-500 rounded-r mr-3"></div>
                        Amount Summary
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Enhanced Breakdown -->
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between py-3 px-4 bg-slate-50 rounded-lg">
                                <span class="text-slate-600 font-medium">Metal Amount:</span>
                                <span id="total-metal-amount" class="font-bold text-slate-900">₹50,000.00</span>
                            </div>
                            <div class="flex justify-between py-3 px-4 bg-slate-50 rounded-lg">
                                <span class="text-slate-600 font-medium">Stone Amount:</span>
                                <span id="total-stone-amount" class="font-bold text-slate-900">₹15,000.00</span>
                            </div>
                            <div class="flex justify-between py-3 px-4 bg-slate-50 rounded-lg">
                                <span class="text-slate-600 font-medium">Making Charges:</span>
                                <span id="total-making-charges" class="font-bold text-slate-900">₹8,000.00</span>
                            </div>
                            <div class="flex justify-between py-3 px-4 bg-slate-50 rounded-lg">
                                <span class="text-slate-600 font-medium">Other Charges:</span>
                                <span id="total-other-charges" class="font-bold text-slate-900">₹1,000.00</span>
                            </div>
                            <div class="amount-highlight flex justify-between py-3 px-4 rounded-lg">
                                <span class="font-bold text-slate-800">Subtotal:</span>
                                <span id="subtotal" class="font-black text-slate-900">₹74,000.00</span>
                            </div>
                        </div>
                        
                        <!-- Enhanced Final Amount -->
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between py-3 px-4 bg-red-50 rounded-lg border border-red-100" id="discount-row">
                                <span class="text-red-700 font-medium">Total Discount:</span>
                                <span id="discount" class="text-red-700 font-bold">-₹2,000.00</span>
                            </div>
                            <div class="flex justify-between py-3 px-4 bg-blue-50 rounded-lg border border-blue-100" id="gst-row">
                                <span class="text-blue-700 font-medium">GST (3%):</span>
                                <span id="gst-amount" class="font-bold text-blue-700">₹2,160.00</span>
                            </div>
                            <div class="total-highlight flex justify-between py-4 px-4 rounded-lg">
                                <span class="text-xl font-black text-slate-800">Grand Total:</span>
                                <span id="grand-total" class="text-xl font-black text-blue-600">₹74,160.00</span>
                            </div>
                            <div class="flex justify-between py-3 px-4 bg-green-50 rounded-lg border border-green-200">
                                <span class="text-green-700 font-bold">Paid Amount:</span>
                                <span id="total-paid-amount" class="text-green-700 font-black">₹74,160.00</span>
                            </div>
                            <div class="flex justify-between py-3 px-4 bg-red-50 rounded-lg border border-red-200" id="due-row">
                                <span class="text-red-700 font-bold">Due Amount:</span>
                                <span id="due-amount" class="text-red-700 font-black">₹0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Footer -->
        <div class="gradient-header p-8 print:p-6 text-center text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-black opacity-5"></div>
            <div class="relative z-10 space-y-4">
                <p class="text-lg font-bold">This is a computer-generated document. No signature required.</p>
                <p class="text-sm opacity-90">Thank you for choosing us for your precious jewelry needs!</p>
                <div class="flex justify-center items-center space-x-6 text-sm mt-6">
                    <div class="flex items-center">
                        <span class="mr-2">💎</span>
                        <span class="font-medium">Premium Quality</span>
                    </div>
                    <div class="flex items-center">
                        <span class="mr-2">✓</span>
                        <span class="font-medium">BIS Certified</span>
                    </div>
                    <div class="flex items-center">
                        <span class="mr-2">🛡️</span>
                        <span class="font-medium">Lifetime Guarantee</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced sample data with GST toggle functionality
        let sampleInvoiceData = {
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
                notes: "Premium quality 22K gold jewelry with certified diamonds. All items are BIS hallmarked and come with proper certification. Thank you for your business!",
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

        // Enhanced utility functions
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount || 0);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        // Enhanced function to toggle between GST and Non-GST
      // Enhanced function to toggle between GST and Non-GST
      function toggleInvoiceType() {
            sampleInvoiceData.sale.is_gst_applicable = sampleInvoiceData.sale.is_gst_applicable ? 0 : 1;
            
            if (sampleInvoiceData.sale.is_gst_applicable) {
                // GST Invoice
                sampleInvoiceData.sale.gst_amount = (sampleInvoiceData.sale.subtotal - sampleInvoiceData.sale.discount) * 0.03;
                sampleInvoiceData.sale.grand_total = sampleInvoiceData.sale.subtotal - sampleInvoiceData.sale.discount + sampleInvoiceData.sale.gst_amount;
                document.getElementById('invoice-title').innerHTML = '<span class="text-white">TAX INVOICE</span>';
                document.getElementById('document-type-badge').textContent = 'TAX INVOICE';
                document.getElementById('document-type-indicator').innerHTML = '<span id="gst-status-icon" class="mr-2">🏢</span><span id="gst-status-text">GST Applicable - Tax Invoice</span>';
                document.getElementById('watermark').textContent = 'TAX INVOICE';
            } else {
                // Non-GST Invoice (Estimate)
                sampleInvoiceData.sale.gst_amount = 0;
                sampleInvoiceData.sale.grand_total = sampleInvoiceData.sale.subtotal - sampleInvoiceData.sale.discount;
                document.getElementById('invoice-title').innerHTML = '<span class="text-white">ESTIMATE</span>';
                document.getElementById('document-type-badge').textContent = 'ESTIMATE';
                document.getElementById('document-type-indicator').innerHTML = '<span id="gst-status-icon" class="mr-2">📄</span><span id="gst-status-text">Non-GST - Estimate Document</span>';
                document.getElementById('watermark').textContent = 'ESTIMATE';
            }
            
            // Update totals
            sampleInvoiceData.sale.total_paid_amount = sampleInvoiceData.sale.grand_total;
            sampleInvoiceData.sale.due_amount = 0;
            
            loadSampleData();
        }

        // Enhanced function to populate firm details
        function populateFirmDetails(firm) {
            document.getElementById('firm-name').textContent = firm.name;
            document.getElementById('firm-address').textContent = firm.address;
            document.getElementById('firm-phone').textContent = firm.phone;
            document.getElementById('firm-gst').textContent = `GST: ${firm.gst_number}`;
        }

        // Enhanced function to populate customer details
        function populateCustomerDetails(customer) {
            document.getElementById('customer-name').textContent = customer.name;
            document.getElementById('customer-address').textContent = customer.address;
            document.getElementById('customer-phone').textContent = customer.phone;
            document.getElementById('customer-gst').textContent = `GST: ${customer.gst_number}`;
        }

        // Enhanced function to populate sale details
        function populateSaleDetails(sale) {
            document.getElementById('invoice-no').textContent = sale.invoice_no;
            document.getElementById('sale-date').textContent = formatDate(sale.sale_date);
            document.getElementById('payment-method').textContent = sale.payment_method;
            document.getElementById('payment-status').textContent = sale.payment_status;
            document.getElementById('transaction-type').textContent = sale.transaction_type;
            document.getElementById('notes').textContent = sale.notes;

            // Update payment status styling
            const statusElement = document.getElementById('payment-status');
            if (sale.payment_status === 'Paid') {
                statusElement.className = 'px-3 py-1 rounded-full text-xs bg-green-100 text-green-800 font-bold';
            } else if (sale.payment_status === 'Partial') {
                statusElement.className = 'px-3 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800 font-bold';
            } else {
                statusElement.className = 'px-3 py-1 rounded-full text-xs bg-red-100 text-red-800 font-bold';
            }
        }

        // Enhanced function to populate items table
        function populateItemsTable(items) {
            const tbody = document.getElementById('items-table-body');
            tbody.innerHTML = '';

            items.forEach((item, index) => {
                const row = document.createElement('tr');
                row.className = index % 2 === 0 ? 'bg-white hover:bg-slate-50' : 'table-stripe hover:bg-slate-50';
                
                row.innerHTML = `
                    <td class="px-4 py-4 border-r border-slate-200 font-medium text-center">${index + 1}</td>
                    <td class="px-4 py-4 border-r border-slate-200">
                        <div class="font-semibold text-slate-900 mb-1">${item.product_name}</div>
                        <div class="text-xs text-slate-500">Product ID: ${item.product_id}</div>
                    </td>
                    <td class="px-4 py-4 border-r border-slate-200 text-center font-mono text-xs bg-blue-50">${item.huid_code}</td>
                    <td class="px-4 py-4 border-r border-slate-200 text-center font-bold text-yellow-700">${item.purity}</td>
                    <td class="px-4 py-4 border-r border-slate-200 text-center font-medium">${item.gross_weight}g</td>
                    <td class="px-4 py-4 border-r border-slate-200 text-center font-bold text-blue-600">${item.net_weight}g</td>
                    <td class="px-4 py-4 border-r border-slate-200 text-center font-medium">${formatCurrency(item.purity_rate)}</td>
                    <td class="px-4 py-4 border-r border-slate-200 text-center">
                        <div class="text-xs text-slate-600">${item.stone_type}</div>
                        <div class="font-medium">${item.stone_weight}ct</div>
                        <div class="text-xs font-bold text-green-600">${formatCurrency(item.stone_price)}</div>
                    </td>
                    <td class="px-4 py-4 border-r border-slate-200 text-center">
                        <div class="text-xs text-slate-600">${item.making_type}</div>
                        <div class="font-medium">${formatCurrency(item.making_rate)}/g</div>
                        <div class="text-xs font-bold text-purple-600">${formatCurrency(item.making_charges)}</div>
                    </td>
                    <td class="px-4 py-4 text-right font-bold text-lg text-slate-900">${formatCurrency(item.total)}</td>
                `;
                
                tbody.appendChild(row);
            });
        }

        // Enhanced function to populate amount summary
        function populateAmountSummary(sale) {
            document.getElementById('total-metal-amount').textContent = formatCurrency(sale.total_metal_amount);
            document.getElementById('total-stone-amount').textContent = formatCurrency(sale.total_stone_amount);
            document.getElementById('total-making-charges').textContent = formatCurrency(sale.total_making_charges);
            document.getElementById('total-other-charges').textContent = formatCurrency(sale.total_other_charges);
            document.getElementById('subtotal').textContent = formatCurrency(sale.subtotal);
            document.getElementById('discount').textContent = `-${formatCurrency(sale.discount)}`;
            document.getElementById('gst-amount').textContent = formatCurrency(sale.gst_amount);
            document.getElementById('grand-total').textContent = formatCurrency(sale.grand_total);
            document.getElementById('total-paid-amount').textContent = formatCurrency(sale.total_paid_amount);
            document.getElementById('due-amount').textContent = formatCurrency(sale.due_amount);

            // Show/hide GST row based on applicability
            const gstRow = document.getElementById('gst-row');
            if (sale.is_gst_applicable) {
                gstRow.style.display = 'flex';
            } else {
                gstRow.style.display = 'none';
            }

            // Update due amount styling
            const dueRow = document.getElementById('due-row');
            if (sale.due_amount > 0) {
                dueRow.style.display = 'flex';
            } else {
                dueRow.style.display = 'none';
            }
        }

        // Enhanced main function to load sample data
        function loadSampleData() {
            try {
                populateFirmDetails(sampleInvoiceData.firm);
                populateCustomerDetails(sampleInvoiceData.customer);
                populateSaleDetails(sampleInvoiceData.sale);
                populateItemsTable(sampleInvoiceData.items);
                populateAmountSummary(sampleInvoiceData.sale);

                // Update status
                document.getElementById('invoice-status').textContent = 'Data Loaded Successfully';
                
                // Brief success indication
                setTimeout(() => {
                    document.getElementById('invoice-status').textContent = 'Ready to Print';
                }, 2000);

                console.log('Sample data loaded successfully');
            } catch (error) {
                console.error('Error loading sample data:', error);
                document.getElementById('invoice-status').textContent = 'Error Loading Data';
            }
        }

        // Enhanced initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Set current date
            const today = new Date();
            const formattedDate = today.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
            document.getElementById('sale-date').textContent = formattedDate;

            // Load sample data by default
            loadSampleData();

            // Add print optimization
            window.addEventListener('beforeprint', function() {
                document.getElementById('invoice-status').textContent = 'Printing...';
            });

            window.addEventListener('afterprint', function() {
                document.getElementById('invoice-status').textContent = 'Print Complete';
                setTimeout(() => {
                    document.getElementById('invoice-status').textContent = 'Ready to Print';
                }, 2000);
            });

            console.log('Enhanced Jewelry Invoice System initialized successfully');
        });

        // Enhanced utility function for logo upload (placeholder)
        function uploadLogo() {
            // This would integrate with file upload functionality
            const logoContainer = document.getElementById('firm-logo-container');
            logoContainer.innerHTML = `
                <img src="/path/to/uploaded/logo.png" alt="Firm Logo" class="w-full h-full object-cover rounded-xl">
            `;
        }

        // Enhanced function to export data (placeholder)
        function exportInvoiceData() {
            const dataStr = JSON.stringify(sampleInvoiceData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `invoice_${sampleInvoiceData.sale.invoice_no}.json`;
            link.click();
            URL.revokeObjectURL(url);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            } else if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                exportInvoiceData();
            } else if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                loadSampleData();
            }
        });
    </script>
</body>
</html>