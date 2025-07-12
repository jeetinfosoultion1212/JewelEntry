<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jewelry Billing System - New</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
  <div class="max-w-5xl mx-auto p-4">
    <div class="bg-yellow-100 rounded-lg shadow p-4 mb-4 flex items-center justify-between">
      <div class="flex items-center space-x-2">
        <i class="fas fa-gem text-yellow-500 text-2xl"></i>
        <span class="font-bold text-lg">Jewelry Billing System</span>
      </div>
      <div class="text-sm text-gray-600">Date: <span id="currentDate"></span> | Time: <span id="currentTime"></span></div>
    </div>
    <!-- Invoice Type & Number -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <div>
        <label class="block text-sm font-medium mb-1">Invoice Type</label>
        <select id="invoiceType" class="w-full border rounded px-2 py-1">
          <option value="gst">GST Invoice</option>
          <option value="estimate">Estimate</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Invoice No</label>
        <input id="invoiceNumber" type="text" class="w-full border rounded px-2 py-1 bg-blue-50" readonly>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Date</label>
        <input id="invoiceDate" type="date" class="w-full border rounded px-2 py-1">
      </div>
    </div>
    <!-- Customer Search/Add/Details -->
    <div class="mb-4">
      <label class="block text-sm font-medium mb-1">Customer</label>
      <div class="flex space-x-2">
        <input id="customerSearch" type="text" class="flex-1 border rounded px-2 py-1" placeholder="Search by name or phone">
        <button id="addCustomerBtn" class="bg-green-500 text-white px-3 py-1 rounded">+ Add</button>
      </div>
      <div id="customerDropdown" class="bg-white border rounded shadow mt-1 hidden"></div>
      <div id="customerDetails" class="mt-2 hidden bg-white rounded p-2 border">
        <!-- Populated by JS: name, phone, due, advance -->
      </div>
    </div>
    <!-- Product Search/Manual/QR -->
    <div class="mb-4">
      <div class="flex space-x-2">
        <input id="productSearch" type="text" class="flex-1 border rounded px-2 py-1" placeholder="Search by type, ID or HUID">
        <button id="scanProductBtn" class="bg-blue-500 text-white px-3 py-1 rounded"><i class="fas fa-qrcode"></i> Scan</button>
        <button id="addManualProductBtn" class="bg-purple-500 text-white px-3 py-1 rounded">+ Manual</button>
      </div>
      <div id="productDropdown" class="bg-white border rounded shadow mt-1 hidden"></div>
    </div>
    <!-- Product Table -->
    <div class="mb-4">
      <table class="min-w-full bg-white rounded shadow product-table" id="productsTable">
        <thead>
          <tr>
            <th>Product</th>
            <th>Purity</th>
            <th>Gross/Net</th>
            <th>Stone</th>
            <th>Rate/G</th>
            <th>Making</th>
            <th>Amount</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr id="emptyRow"><td colspan="8" class="text-center text-gray-400">No products added yet.</td></tr>
        </tbody>
      </table>
    </div>
    <!-- Billing Summary -->
    <div class="bg-white rounded shadow p-4 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <div class="flex justify-between mb-1"><span>Sub Total:</span><span id="subTotal">₹0.00</span></div>
          <div class="flex justify-between mb-1"><span>Making Charge:</span><span id="makingChargeTotal">₹0.00</span></div>
          <div class="flex justify-between mb-1"><span>Loyalty Discount:</span><span id="loyaltyDiscountAmount">-₹0.00</span></div>
          <div class="flex justify-between mb-1"><span>Coupon Discount:</span><span id="couponDiscountAmount">-₹0.00</span></div>
          <div class="flex justify-between mb-1"><span>Manual Discount:</span><span id="manualDiscountAmount">-₹0.00</span></div>
          <div class="flex justify-between mb-1"><span>GST Amount:</span><span id="gstAmount">₹0.00</span></div>
          <div class="flex justify-between font-bold text-lg"><span>Grand Total:</span><span id="grandTotal">₹0.00</span></div>
        </div>
        <div>
          <!-- Payment section placeholder -->
        </div>
        <div>
          <!-- Notes or extra options -->
        </div>
      </div>
    </div>
    <div class="flex justify-end space-x-2">
      <button id="resetBtn" class="bg-gray-300 px-4 py-2 rounded">Reset</button>
      <button id="generateBillBtn" class="bg-green-600 text-white px-6 py-2 rounded">Generate Bill</button>
    </div>
  </div>
  <!-- Modals for Add Customer, Manual Product, QR Scan will be added by JS -->
  <script src="js/new-billing.js"></script>
</body>
</html> 