// new-billing.js - Full implementation for new billing page

document.addEventListener('DOMContentLoaded', () => {
  // ========== GLOBAL STATE ==========
  let products = [];
  let customer = null;
  let urdItems = [];
  let currentGoldRate24K = 0;
  let currentSilverRate999 = 0;

  // ========== NOTIFICATION UTILITY ==========
  function showNotification(message, type = 'success') {
    const notificationDiv = document.createElement('div');
    notificationDiv.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 ${
      type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white text-sm`;
    notificationDiv.textContent = message;
    document.body.appendChild(notificationDiv);
    setTimeout(() => {
      notificationDiv.remove();
    }, 3000);
  }

  // ========== INVOICE TYPE & NUMBER ==========
  const invoiceTypeSelect = document.getElementById('invoiceType');
  const invoiceNumberInput = document.getElementById('invoiceNumber');
  invoiceTypeSelect.addEventListener('change', fetchInvoiceNumber);
  fetchInvoiceNumber();
  function fetchInvoiceNumber() {
    const isGst = invoiceTypeSelect.value === 'gst';
    fetch('PC/sell.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'get_invoice_number', isGst }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          invoiceNumberInput.value = data.invoiceNumber;
        } else {
          invoiceNumberInput.value = '';
        }
      })
      .catch(() => {
        invoiceNumberInput.value = '';
      });
  }

  // ========== CUSTOMER SEARCH/ADD/DETAILS ==========
  const customerSearchInput = document.getElementById('customerSearch');
  const customerDropdown = document.getElementById('customerDropdown');
  const customerDetailsDiv = document.getElementById('customerDetails');
  let customerSearchTimeout = null;
  customerSearchInput.addEventListener('input', function () {
    clearTimeout(customerSearchTimeout);
    const term = this.value.trim();
    if (term.length < 2) {
      customerDropdown.classList.add('hidden');
      return;
    }
    customerSearchTimeout = setTimeout(() => {
      fetch('PC/sell.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'search_customers', term }),
      })
        .then(res => res.json())
        .then(data => {
          if (data.success && data.data.length > 0) {
            customerDropdown.innerHTML = '';
            data.data.forEach(cust => {
              const div = document.createElement('div');
              div.className = 'p-2 hover:bg-gray-100 cursor-pointer';
              div.textContent = `${cust.FirstName} ${cust.LastName} (${cust.PhoneNumber})`;
              div.addEventListener('click', () => selectCustomer(cust.id));
              customerDropdown.appendChild(div);
            });
            customerDropdown.classList.remove('hidden');
          } else {
            customerDropdown.innerHTML = '<div class="p-2 text-gray-400">No customers found</div>';
            customerDropdown.classList.remove('hidden');
          }
        });
    }, 300);
  });
  document.addEventListener('click', (e) => {
    if (!customerSearchInput.contains(e.target) && !customerDropdown.contains(e.target)) {
      customerDropdown.classList.add('hidden');
    }
  });
  function selectCustomer(customerId) {
    fetch('PC/sell.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'get_customer_details', customerId }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          customer = data.data;
          customerSearchInput.value = `${customer.FirstName} ${customer.LastName}`;
          customerDropdown.classList.add('hidden');
          showCustomerDetails(customer);
          fetchCustomerBalance(customer.id);
        }
      });
  }
  function showCustomerDetails(cust) {
    customerDetailsDiv.innerHTML = `
      <div class="font-bold text-lg">${cust.FirstName} ${cust.LastName}</div>
      <div class="text-sm text-gray-600">Phone: ${cust.PhoneNumber || ''}</div>
      <div class="text-sm text-gray-600">Email: ${cust.Email || ''}</div>
      <div id="customerDue" class="text-sm text-red-600"></div>
      <div id="customerAdvance" class="text-sm text-green-600"></div>
    `;
    customerDetailsDiv.classList.remove('hidden');
  }
  function fetchCustomerBalance(customerId) {
    fetch('PC/sell.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'get_customer_balance', customerId }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('customerDue').textContent = data.due > 0 ? `Due: ₹${data.due}` : '';
          document.getElementById('customerAdvance').textContent = data.advance > 0 ? `Advance: ₹${data.advance}` : '';
        }
      });
  }
  // Add Customer Modal (simple prompt for demo)
  document.getElementById('addCustomerBtn').addEventListener('click', () => {
    const name = prompt('Enter customer name:');
    if (!name) return;
    const phone = prompt('Enter phone number:');
    if (!phone) return;
    fetch('PC/sell.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'add_customer', firstName: name, lastName: '', phone }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showNotification('Customer added!', 'success');
          selectCustomer(data.customerId);
        } else {
          showNotification('Failed to add customer', 'error');
        }
      });
  });

  // ========== PRODUCT SEARCH/MANUAL/QR ADD ==========
  const productSearchInput = document.getElementById('productSearch');
  const productDropdown = document.getElementById('productDropdown');
  let productSearchTimeout = null;
  productSearchInput.addEventListener('input', function () {
    clearTimeout(productSearchTimeout);
    const term = this.value.trim();
    if (term.length < 2) {
      productDropdown.classList.add('hidden');
      return;
    }
    productSearchTimeout = setTimeout(() => {
      fetch('PC/sell.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'search_products', term }),
      })
        .then(res => res.json())
        .then(data => {
          if (data.success && data.data.length > 0) {
            productDropdown.innerHTML = '';
            data.data.forEach(prod => {
              const div = document.createElement('div');
              div.className = 'p-2 hover:bg-gray-100 cursor-pointer';
              div.textContent = `${prod.product_name || prod.jewelry_type} (${prod.purity})`;
              div.addEventListener('click', () => addProductToTable(prod));
              productDropdown.appendChild(div);
            });
            productDropdown.classList.remove('hidden');
          } else {
            productDropdown.innerHTML = '<div class="p-2 text-gray-400">No products found</div>';
            productDropdown.classList.remove('hidden');
          }
        });
    }, 300);
  });
  document.addEventListener('click', (e) => {
    if (!productSearchInput.contains(e.target) && !productDropdown.contains(e.target)) {
      productDropdown.classList.add('hidden');
    }
  });
  // Manual Product Add (simple prompt for demo)
  document.getElementById('addManualProductBtn').addEventListener('click', () => {
    const name = prompt('Enter product name:');
    if (!name) return;
    const purity = prompt('Enter purity:');
    if (!purity) return;
    const gross = prompt('Enter gross weight:');
    if (!gross) return;
    const net = prompt('Enter net weight:');
    if (!net) return;
    const rate = prompt('Enter rate per gram:');
    if (!rate) return;
    const prod = { id: 'manual_' + Date.now(), product_name: name, purity, gross_weight: gross, net_weight: net, rate_per_gram: rate };
    addProductToTable(prod);
  });
  // QR Scan (simulate with prompt for demo)
  document.getElementById('scanProductBtn').addEventListener('click', () => {
    const code = prompt('Enter product HUID/barcode:');
    if (!code) return;
    fetch('PC/sell.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'get_product_by_barcode', huid: code }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.data) {
          addProductToTable(data.data);
        } else {
          showNotification('Product not found', 'error');
        }
      });
  });

  // ========== PRODUCT TABLE ==========
  function addProductToTable(prod) {
    if (products.find(p => p.id === prod.id)) {
      showNotification('Product already added', 'error');
      return;
    }
    products.push(prod);
    renderProductTable();
    productDropdown.classList.add('hidden');
    productSearchInput.value = '';
    calculateTotals();
  }
  function renderProductTable() {
    const tbody = document.querySelector('#productsTable tbody');
    tbody.innerHTML = '';
    if (products.length === 0) {
      document.getElementById('emptyRow').classList.remove('hidden');
      return;
    }
    document.getElementById('emptyRow').classList.add('hidden');
    products.forEach((prod, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${prod.product_name || ''}</td>
        <td>${prod.purity || ''}</td>
        <td>${prod.gross_weight || ''}/${prod.net_weight || ''}</td>
        <td>${prod.stone_type || ''}</td>
        <td>${prod.rate_per_gram || ''}</td>
        <td>${prod.making_charge || ''}</td>
        <td>${calculateProductAmount(prod)}</td>
        <td><button class="text-red-500" onclick="window.removeProduct(${idx})">Remove</button></td>
      `;
      tbody.appendChild(tr);
    });
  }
  window.removeProduct = function(idx) {
    products.splice(idx, 1);
    renderProductTable();
    calculateTotals();
  };
  function calculateProductAmount(prod) {
    const net = parseFloat(prod.net_weight) || 0;
    const rate = parseFloat(prod.rate_per_gram) || 0;
    return '₹' + (net * rate).toFixed(2);
  }

  // ========== BILLING SUMMARY ==========
  function calculateTotals() {
    let subtotal = 0;
    products.forEach(prod => {
      subtotal += (parseFloat(prod.net_weight) || 0) * (parseFloat(prod.rate_per_gram) || 0);
    });
    document.getElementById('subTotal').textContent = '₹' + subtotal.toFixed(2);
    document.getElementById('grandTotal').textContent = '₹' + subtotal.toFixed(2);
    // Add more calculations for making, discounts, GST, etc. as needed
  }

  // ========== GENERATE BILL ==========
  document.getElementById('generateBillBtn').addEventListener('click', () => {
    if (!customer) {
      showNotification('Select a customer', 'error');
      return;
    }
    if (products.length === 0) {
      showNotification('Add at least one product', 'error');
      return;
    }
    const invoiceNumber = invoiceNumberInput.value;
    const invoiceDate = document.getElementById('invoiceDate').value;
    const isGst = invoiceTypeSelect.value === 'gst';
    const items = products.map(prod => ({
      productId: prod.id,
      productName: prod.product_name,
      purity: prod.purity,
      grossWeight: prod.gross_weight,
      netWeight: prod.net_weight,
      ratePerGram: prod.rate_per_gram,
    }));
    fetch('PC/sell.php', {
      method: 'POST',
      body: new URLSearchParams({
        action: 'generate_bill',
        customerId: customer.id,
        invoiceNumber,
        saleDate: invoiceDate,
        items: JSON.stringify(items),
        grandTotal: document.getElementById('grandTotal').textContent.replace(/[^\d.]/g, ''),
        isGstApplicable: isGst,
      }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showNotification('Bill generated!', 'success');
          // Optionally redirect or show modal
        } else {
          showNotification('Failed to generate bill', 'error');
        }
      });
  });

  // ========== INIT PLACEHOLDER ==========
  // Example: Set current date/time
  const dateElem = document.getElementById('currentDate');
  const timeElem = document.getElementById('currentTime');
  if (dateElem && timeElem) {
    const now = new Date();
    dateElem.textContent = now.toLocaleDateString();
    setInterval(() => {
      timeElem.textContent = new Date().toLocaleTimeString();
    }, 1000);
  }
}); 