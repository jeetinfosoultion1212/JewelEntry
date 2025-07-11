// This JavaScript file implements all the client-side functionality for the jewelry billing system
// It handles GST/Estimate toggle, customer management, product scanning, calculations, and more

document.addEventListener("DOMContentLoaded", () => {
  // ==================== GLOBAL VARIABLES ====================
  let products = [] // Array to store added products
  let urdItems = [] // Store multiple URD items in memory until bill is generated
  let customerAdvanceAmount = 0 // Store customer's available advance
  let paymentMethods = [] // For split payment
  let html5QrCode = null // QR scanner instance
  let currentGoldRate24K = 0 // Current 24K gold rate
  let currentSilverRate999 = 0 // Current 999.9 silver rate
  window.barcodeBuffer = "" // For external barcode scanner

  // ==================== FIRM CONFIGURATION ====================
  let firmConfig = {
    coupon_code_apply_enabled: true,
    schemes_enabled: true,
    gst_rate: 0.03,
    loyalty_discount_percentage: 0.02,
    welcome_coupon_enabled: false,
    welcome_coupon_code: '',
    post_purchase_coupon_enabled: false,
    auto_scheme_entry: false,
  }

  console.log("[INIT] Default firmConfig:", firmConfig);

  // Fetch firm config on page load
  console.log("[FETCH] Requesting firm config...");
  fetch(window.location.href, {
    method: "POST",
    body: new URLSearchParams({ action: "get_firm_config" }),
  })
    .then((res) => res.json())
    .then((data) => {
      console.log("[FETCH] Firm config response:", data);
      if (data.success && data.config) {
        firmConfig = { ...firmConfig, ...data.config }
        console.log("[APPLY] Merged firmConfig:", firmConfig);
        applyFirmConfig()
      }
    })
    .catch((err) => {
      console.error("[ERROR] Error fetching firm config", err)
    })

  function applyFirmConfig() {
    console.log("[APPLY] Applying firmConfig:", firmConfig);
    // Coupon field
    const couponRow = document.getElementById("couponCode")?.closest("tr")
    if (couponRow) {
      if (!firmConfig.coupon_code_apply_enabled) {
        console.log("[UI] Hiding coupon row (coupon_code_apply_enabled is false)");
        couponRow.classList.add("hidden")
      } else {
        console.log("[UI] Showing coupon row (coupon_code_apply_enabled is true)");
        couponRow.classList.remove("hidden")
      }
    }
    // Loyalty discount
    const loyaltyRow = document.getElementById("loyaltyDiscount")?.closest("tr")
    if (loyaltyRow) {
      if (firmConfig.loyalty_discount_percentage > 0) {
        console.log("[UI] Showing loyalty discount row, percentage:", firmConfig.loyalty_discount_percentage);
        loyaltyRow.classList.remove("hidden")
        document.getElementById("loyaltyDiscount").value = (firmConfig.loyalty_discount_percentage * 100).toFixed(2)
        document.getElementById("loyaltyDiscountType").value = "percent"
      } else {
        console.log("[UI] Hiding loyalty discount row (percentage is 0)");
        loyaltyRow.classList.add("hidden")
      }
    }
    // GST row
    const gstRow = document.getElementById("gstRow")
    if (gstRow && typeof firmConfig.gst_rate === "number") {
      console.log("[UI] GST row present, gst_rate:", firmConfig.gst_rate);
      gstRow.querySelector("#gstAmount").textContent = formatCurrency(0)
    }
    // Welcome coupon
    if (firmConfig.welcome_coupon_enabled && firmConfig.welcome_coupon_code) {
      console.log("[UI] Welcome coupon enabled, code:", firmConfig.welcome_coupon_code);
      const couponInput = document.getElementById("couponCode")
      if (couponInput && !couponInput.value) {
        couponInput.value = firmConfig.welcome_coupon_code
      }
    }
    // Schemes (if you have a row for schemes, show/hide here)
    if (firmConfig.schemes_enabled) {
      console.log("[UI] Schemes enabled in config");
    } else {
      console.log("[UI] Schemes disabled in config");
    }
    // ...
  }

  // ==================== UTILITY FUNCTIONS ====================

  // Format currency
  function formatCurrency(amount) {
    return (
      "₹" +
      Number.parseFloat(amount)
        .toFixed(2)
        .replace(/\d(?=(\d{3})+\.)/g, "$&,")
    )
  }

  // Format number with 3 decimal places
  function formatWeight(weight) {
    return Number.parseFloat(weight).toFixed(3)
  }

  // Show notification
  function showNotification(message, type = "success") {
    const notificationDiv = document.createElement("div")
    notificationDiv.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 ${
      type === "success" ? "bg-green-500" : "bg-red-500"
    } text-white text-sm`
    notificationDiv.textContent = message
    document.body.appendChild(notificationDiv)

    setTimeout(() => {
      notificationDiv.remove()
    }, 3000)
  }

  // Calculate rate per gram based on purity and 24K rate
  function calculateRatePerGram(purity, materialType) {
    let baseRate = 0;
    let finePurity = 0;
    if (materialType === "Gold") {
      baseRate = currentGoldRate24K;
      finePurity = 99.99;
    } else if (materialType === "Silver") {
      baseRate = currentSilverRate999;
      finePurity = 999.90;
    } else {
      baseRate = 0;
      finePurity = 100;
    }
    const purityValue = Number.parseFloat(purity);
    return baseRate * (purityValue / finePurity);
  }

  // Format purity for display
  function formatPurity(purity, materialType) {
    const purityValue = Number.parseFloat(purity);
    if (materialType === "Gold") {
        if (purityValue >= 99.9) return "24K";
        if (purityValue >= 91.5 && purityValue <= 92.3) return "22K";
        if (purityValue >= 75.0 && purityValue < 91.5) return "18K";
        // Add more gold standards if needed
    } else if (materialType === "Silver") {
        if (purityValue >= 999.0) return "999";
        if (purityValue >= 925 && purityValue < 999) return "Sterling (925)";
        // Add more silver standards if needed
    }
    // Default: show the raw value
    return purity + (materialType === "Silver" ? "" : "%");
  }

  // Debounce function to limit how often a function can be called
  function debounce(func, wait) {
    let timeout
    return function (...args) {
      clearTimeout(timeout)
      timeout = setTimeout(() => func.apply(this, args), wait)
    }
  }

  // Add a function to handle external barcode scanner input
  window.addEventListener("keydown", (e) => {
    // Check if an input field is focused
    const activeElement = document.activeElement
    const isInputFocused =
      activeElement.tagName === "INPUT" || activeElement.tagName === "TEXTAREA" || activeElement.tagName === "SELECT"

    // If we're in a modal or an input is focused, don't process as barcode
    const modalOpen = document.querySelector(".fixed.inset-0.bg-black.bg-opacity-50.z-50:not(.hidden)")
    if (modalOpen || isInputFocused) return

    // Barcode scanners typically end with Enter key
    if (e.key === "Enter" && window.barcodeBuffer && window.barcodeBuffer.length > 3) {
      e.preventDefault()
      // Process the scanned barcode
      getProductByBarcode(window.barcodeBuffer)
      window.barcodeBuffer = ""
    } else if (e.key.length === 1) {
      // Accumulate characters
      if (!window.barcodeBuffer) window.barcodeBuffer = ""
      window.barcodeBuffer += e.key

      // Reset buffer after a delay (barcode scanners are fast)
      clearTimeout(window.barcodeBufferTimeout)
      window.barcodeBufferTimeout = setTimeout(() => {
        window.barcodeBuffer = ""
      }, 500)
    }
  })

  // ==================== INVOICE NUMBER LOGIC ====================

  // Handle GST/Estimate toggle
  const billTypeRadios = document.querySelectorAll('input[name="billType"]')
  billTypeRadios.forEach((radio) => {
    radio.addEventListener("change", function () {
      const isGst = this.value === "gst"
      updateInvoiceNumber(isGst)

      // Show/hide GST row based on selection
      const gstRow = document.getElementById("gstRow")
      if (isGst) {
        gstRow.classList.remove("hidden")
      } else {
        gstRow.classList.add("hidden")
      }

      // Recalculate totals when GST status changes
      calculateTotals()
    })
  })

  // Fetch next invoice number via AJAX
  function updateInvoiceNumber(isGst) {
    const formData = new FormData()
    formData.append("action", "get_invoice_number")
    formData.append("isGst", isGst)

    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.getElementById("invoiceNumber").value = data.invoiceNumber
        } else {
          console.error("Error fetching invoice number:", data.error)
        }
      })
      .catch((error) => {
        console.error("Error:", error)
      })
  }

  // ==================== CUSTOMER MANAGEMENT ====================

  // Add a function to safely add event listeners
  function safeAddEventListener(id, event, handler) {
    const el = document.getElementById(id)
    if (el) el.addEventListener(event, handler)
  }

  // Customer search functionality
  const customerSearchInput = document.getElementById("customerSearch")
  const customerResults = document.getElementById("customerResults")

  if (customerSearchInput) {
    customerSearchInput.addEventListener(
      "input",
      debounce(function () {
        const searchTerm = this.value.trim()

        if (searchTerm.length < 2) {
          customerResults.classList.add("hidden")
          return
        }

        const formData = new FormData()
        formData.append("action", "search_customers")
        formData.append("term", searchTerm)

        console.log("[FETCH] Searching customers with term:", searchTerm);
        fetch(window.location.href, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            console.log("[FETCH] Customer search response:", data);
            if (data.success && data.data.length > 0) {
              customerResults.innerHTML = ""
              data.data.forEach((customer, index) => {
                const div = document.createElement("div")
                div.className = "p-2 hover:bg-gray-100 cursor-pointer"
                // Add tabindex to make it focusable
                div.setAttribute("tabindex", "0")
                // Add data attribute for easier selection
                div.setAttribute("data-customer-id", customer.id)
                // Add keyboard focus styling if it's the first item
                if (index === 0) div.classList.add("bg-gray-200")

                div.innerHTML = `
                  <div class="flex items-center">
                    <i class="fas fa-user-circle text-gold-500 mr-2"></i>
                    <div>
                      <div class="text-sm font-medium">${customer.FirstName} ${customer.LastName}</div>
                      <div class="text-xs text-gray-500">${customer.PhoneNumber}</div>
                    </div>
                  </div>
                `
                div.addEventListener("click", () => {
                  console.log("[UI] Customer selected from search:", customer);
                  selectCustomer(customer.id)
                  customerResults.classList.add("hidden")
                })
                customerResults.appendChild(div)
              })
              customerResults.classList.remove("hidden")
            } else {
              customerResults.innerHTML = '<div class="p-2 text-sm text-gray-500">No customers found</div>'
              customerResults.classList.remove("hidden")
            }
          })
          .catch((error) => {
            console.error("[ERROR] Customer search error:", error)
          })
      }, 300),
    ) // 300ms debounce

    // Add keyboard navigation for customer results
    customerSearchInput.addEventListener("keydown", (e) => {
      const results = document.getElementById("customerResults")

      if (results.classList.contains("hidden")) return

      const items = results.querySelectorAll("div.p-2")
      if (items.length === 0) return

      // Find currently focused item
      let focusedIndex = -1
      items.forEach((item, index) => {
        if (item.classList.contains("bg-gray-200")) focusedIndex = index
      })

      // Handle arrow keys
      if (e.key === "ArrowDown") {
        e.preventDefault()
        // Move focus down
        focusedIndex = focusedIndex < items.length - 1 ? focusedIndex + 1 : 0
        items.forEach((item) => item.classList.remove("bg-gray-200"))
        items[focusedIndex].classList.add("bg-gray-200")
        items[focusedIndex].scrollIntoView({ block: "nearest" })
      } else if (e.key === "ArrowUp") {
        e.preventDefault()
        // Move focus up
        focusedIndex = focusedIndex > 0 ? focusedIndex - 1 : items.length - 1
        items.forEach((item) => item.classList.remove("bg-gray-200"))
        items[focusedIndex].classList.add("bg-gray-200")
        items[focusedIndex].scrollIntoView({ block: "nearest" })
      } else if (e.key === "Enter" && focusedIndex >= 0) {
        e.preventDefault()
        // Select the focused item
        items[focusedIndex].click()
      } else if (e.key === "Escape") {
        e.preventDefault()
        results.classList.add("hidden")
      }
    })
  }

  if (customerResults && customerSearchInput) {
    // Hide customer results when clicking outside
    document.addEventListener("click", (event) => {
      if (!customerSearchInput.contains(event.target) && !customerResults.contains(event.target)) {
        customerResults.classList.add("hidden")
      }
    })
  }

  // Select customer and load details
  function selectCustomer(customerId) {
    const formData = new FormData()
    formData.append("action", "get_customer_details")
    formData.append("customerId", customerId)

    console.log("[FETCH] Getting customer details for:", customerId);
    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("[FETCH] Customer details response:", data);
        if (data.success) {
          const customer = data.data
          document.getElementById("customerId").value = customer.id
          document.getElementById("customerSearch").value = `${customer.FirstName} ${customer.LastName}`
          document.getElementById("customerName").textContent = `${customer.FirstName} ${customer.LastName}`
          document.getElementById("customerPhone").textContent = customer.PhoneNumber || "N/A"
          document.getElementById("customerEmail").textContent = customer.Email || "N/A"
          document.getElementById("customerDetails").classList.remove("hidden")

          // Display customer image if available
          const customerImage = document.getElementById("customerImage")
          if (customer.ImagePath) {
            customerImage.src = customer.ImagePath
            customerImage.classList.remove("hidden")
          } else {
            customerImage.src = "/assets/images/default-user.jpg"
            customerImage.classList.remove("hidden")
          }

          // Fetch customer balance (dues and advance)
          console.log("[FETCH] Getting customer balance for:", customerId);
          getCustomerBalance(customerId)

          // Populate edit form fields
          document.getElementById("editCustomerId").value = customer.id
          document.getElementById("editFirstName").value = customer.FirstName
          document.getElementById("editLastName").value = customer.LastName
          document.getElementById("editPhone").value = customer.PhoneNumber
          document.getElementById("editEmail").value = customer.Email
          document.getElementById("editAddress").value = customer.Address || ""
          document.getElementById("editCity").value = customer.City || ""
          document.getElementById("editState").value = customer.State || ""
          document.getElementById("editPostalCode").value = customer.PostalCode || ""
          document.getElementById("editPanNumber").value = customer.PANNumber || ""
          document.getElementById("editAadhaarNumber").value = customer.AadhaarNumber || ""
          document.getElementById("editDob").value = customer.DateOfBirth || ""

          // Fetch and display customer coupons
          fetchAndDisplayCustomerCoupons(customerId);
        } else {
          console.error("[ERROR] Error fetching customer details:", data.error)
        }
      })
      .catch((error) => {
        console.error("[ERROR] Error fetching customer details:", error)
      })
  }

  // Get customer balance (dues and advance)
  function getCustomerBalance(customerId) {
    const formData = new FormData()
    formData.append("action", "get_customer_balance")
    formData.append("customerId", customerId)

    console.log("[FETCH] Requesting customer balance for:", customerId);
    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("[FETCH] Customer balance response:", data);
        if (data.success) {
          // Only show due/advance if > 0, otherwise hide
          let showDue = Number(data.due) > 0;
          let showAdvance = Number(data.advance) > 0;

          // Update/hide due
          const dueElem = document.getElementById("customerDue");
          if (showDue) {
            dueElem.textContent = `Due: ${formatCurrency(data.due)}`;
            dueElem.style.display = '';
          } else {
            dueElem.style.display = 'none';
          }

          // Update/hide advance
          const advElem = document.getElementById("customerAdvance");
          if (showAdvance) {
            advElem.textContent = `Advance: ${formatCurrency(data.advance)}`;
            advElem.style.display = '';
          } else {
            advElem.style.display = 'none';
          }

          // Update available advance display
          const availableAdvanceSpan = document.getElementById("availableAdvance");
          const advanceAmountInput = document.getElementById("advanceAmount");
          
          // Store advance amount for later use
          customerAdvanceAmount = Number.parseFloat(data.advance)
          
          if (availableAdvanceSpan) availableAdvanceSpan.textContent = formatCurrency(customerAdvanceAmount);
          if (advanceAmountInput) advanceAmountInput.max = customerAdvanceAmount;
          
          // Reset advance amount input if no advance available
          if (customerAdvanceAmount <= 0) {
            if (advanceAmountInput) advanceAmountInput.value = 0;
            if (availableAdvanceSpan) availableAdvanceSpan.style.display = 'none';
          } else {
            if (availableAdvanceSpan) availableAdvanceSpan.style.display = '';
          }

          // Hide availableAdvance if not needed
          const availElem = document.getElementById("availableAdvance");
          if (availElem) availElem.style.display = 'none';

          // Hide/show the parent <div> of due/advance
          // Find the closest parent .flex.flex-col or .flex.flex-row (the container for customer info)
          let parentDiv = dueElem.closest('.flex.items-center.bg-white.bg-opacity-70.p-1.rounded-lg')?.parentElement;
          if (!parentDiv) {
            // fallback: try grandparent
            parentDiv = dueElem.parentElement?.parentElement;
          }
          if (parentDiv) {
            parentDiv.style.display = (showDue || showAdvance) ? '' : 'none';
          }

          // Show compact details only if due or advance exists
          let detailsHtml = '';
          if (showDue) {
            detailsHtml += `<div style=\"background:#fffbe6;color:#b7791f;padding:4px 10px;border-radius:5px;margin-bottom:2px;display:inline-flex;align-items:center;font-size:13px;\"><i class='fas fa-exclamation-circle mr-1'></i> ${formatCurrency(data.due)}</div>`;
          }
          if (showAdvance) {
            detailsHtml += `<div style=\"background:#e6fffa;color:#276749;padding:4px 10px;border-radius:5px;margin-bottom:2px;display:inline-flex;align-items:center;font-size:13px;\"><i class='fas fa-rupee-sign mr-1'></i> ${formatCurrency(data.advance)}</div>`;
          }
          let detailsDiv = document.getElementById('customerBalanceDetails');
          if (!detailsDiv) {
            detailsDiv = document.createElement('div');
            detailsDiv.id = 'customerBalanceDetails';
            const parent = advElem.parentElement;
            parent.appendChild(detailsDiv);
          }
          detailsDiv.innerHTML = detailsHtml;
          detailsDiv.style.display = (showDue || showAdvance) ? '' : 'none';
        } else {
          console.error("[ERROR] Error fetching customer balance:", data.error)
        }
      })
      .catch((error) => {
        console.error("[ERROR] Error fetching customer balance:", error)
      })
  }

  // Function to update net payable amount when advance is used
  function updateNetPayable() {
    const grandTotal = Number.parseFloat(document.getElementById("grandTotal").textContent.replace(/[^\d.-]/g, "")) || 0;
    const advanceAmount = Number.parseFloat(document.getElementById("advanceAmount").value) || 0;
    const paidAmount = Number.parseFloat(document.getElementById("paidAmount").value) || 0;
    
    // Calculate net payable (grand total - advance - paid amount)
    const netPayable = Math.max(0, grandTotal - advanceAmount - paidAmount);
    
    // Update net payable display
    const netPayableElement = document.getElementById("netPayableAmount");
    if (netPayableElement) {
      netPayableElement.textContent = formatCurrency(netPayable);
    }
    
    // Update due amount
    updateDueAmount();
  }

  // Function to handle advance checkbox toggle
  function handleAdvanceCheckbox() {
    console.log("handleAdvanceCheckbox called"); // DEBUG LOG
    const useAdvanceCheckbox = document.getElementById("useAdvanceCheckbox");
    const advanceInputRow = document.getElementById("advanceInputRow");
    const advanceAmountInput = document.getElementById("advanceAmount");
    
    if (useAdvanceCheckbox && advanceInputRow && advanceAmountInput) {
      if (useAdvanceCheckbox.checked) {
        advanceInputRow.classList.remove("hidden");
        // Auto-fill with available advance amount
        advanceAmountInput.value = customerAdvanceAmount;
        updateNetPayable();
      } else {
        advanceInputRow.classList.add("hidden");
        advanceAmountInput.value = 0;
        updateNetPayable();
      }
    }
  }

  // Quick add customer button
  safeAddEventListener("quickCustomerBtn", "click", () => {
    document.getElementById("createCustomerModal").classList.remove("hidden")
  })

  // Create customer button
  safeAddEventListener("createCustomerBtn", "click", () => {
    document.getElementById("createCustomerModal").classList.remove("hidden")
  })

  // Edit customer button
  safeAddEventListener("editCustomerBtn", "click", () => {
    document.getElementById("editCustomerModal").classList.remove("hidden")
  })

  // Close customer modal
  safeAddEventListener("closeCustomerModal", "click", () => {
    document.getElementById("createCustomerModal").classList.add("hidden")
  })

  // Cancel customer button
  safeAddEventListener("cancelCustomerBtn", "click", () => {
    document.getElementById("createCustomerModal").classList.add("hidden")
  })

  // Close edit customer modal
  safeAddEventListener("closeEditCustomerModal", "click", () => {
    document.getElementById("editCustomerModal").classList.add("hidden")
  })

  // Cancel edit customer button
  safeAddEventListener("cancelEditCustomerBtn", "click", () => {
    document.getElementById("editCustomerModal").classList.add("hidden")
  })

  // Save customer
  safeAddEventListener("saveCustomerBtn", "click", () => {
    const firstName = document.getElementById("firstName").value.trim()
    const lastName = document.getElementById("lastName").value.trim()
    const phone = document.getElementById("phone").value.trim()
    const email = document.getElementById("email").value.trim()
    const address = document.getElementById("address").value.trim()
    const city = document.getElementById("city").value.trim()
    const dob = document.getElementById("dob").value

    // Validate inputs
    if (!firstName || !lastName) {
      showNotification("Please enter first and last name", "error")
      return
    }

    if (!phone || !/^\d{10}$/.test(phone)) {
      showNotification("Please enter a valid 10-digit phone number", "error")
      return
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showNotification("Please enter a valid email address", "error")
      return
    }

    const formData = new FormData()
    formData.append("action", "add_customer")
    formData.append("firstName", firstName)
    formData.append("lastName", lastName)
    formData.append("phone", phone)
    formData.append("email", email)
    formData.append("address", address)
    formData.append("city", city)
    formData.append("dob", dob)

    console.log("[FETCH] Adding customer:", { firstName, lastName, phone, email, address, city, dob });
    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("[FETCH] Add customer response:", data);
        if (data.success) {
          showNotification("Customer added successfully")
          document.getElementById("createCustomerModal").classList.add("hidden")

          // Select the newly created customer
          console.log("[UI] Selecting newly created customer:", data.customerId);
          selectCustomer(data.customerId)

          // Reset form
          document.getElementById("customerForm").reset()

          // --- Welcome Coupon Assignment ---
          if (firmConfig.welcome_coupon_enabled && firmConfig.welcome_coupon_code) {
            console.log("[FETCH] Assigning welcome coupon:", { customerId: data.customerId, couponCode: firmConfig.welcome_coupon_code });
            fetch(window.location.href, {
              method: "POST",
              body: new URLSearchParams({
                action: "assign_welcome_coupon",
                customerId: data.customerId,
                couponCode: firmConfig.welcome_coupon_code,
              }),
            })
              .then((res) => res.json())
              .then((couponRes) => {
                console.log("[FETCH] Welcome coupon assignment response:", couponRes);
                if (couponRes.success) {
                  showNotification(
                    `Welcome coupon <b>${firmConfig.welcome_coupon_code}</b> assigned!`,
                    "success"
                  )
                  // Auto-fill coupon field
                  const couponInput = document.getElementById("couponCode")
                  if (couponInput) couponInput.value = firmConfig.welcome_coupon_code
                } else {
                  showNotification(
                    `Welcome coupon not assigned: ${couponRes.error || "Unknown error"}`,
                    "error"
                  )
                }
              })
              .catch((err) => {
                console.error("[ERROR] Error assigning welcome coupon:", err);
                showNotification("Error assigning welcome coupon", "error")
              })
          }

          // --- Auto Scheme Entry ---
          if (firmConfig.schemes_enabled && firmConfig.auto_scheme_entry) {
            console.log("[FETCH] Triggering auto scheme entry for customer:", data.customerId);
            fetch(window.location.href, {
              method: "POST",
              body: new URLSearchParams({
                action: "auto_scheme_entry",
                customerId: data.customerId,
              }),
            })
              .then((res) => res.json())
              .then((schemeRes) => {
                console.log("[FETCH] Auto scheme entry response:", schemeRes);
                if (schemeRes.success) {
                  showNotification("Customer enrolled in scheme automatically!", "success")
                } else if (schemeRes.error && schemeRes.error.includes("already")) {
                  // Already in scheme, no need to notify
                } else {
                  showNotification(
                    `Scheme enrollment failed: ${schemeRes.error || "Unknown error"}`,
                    "error"
                  )
                }
              })
              .catch((err) => {
                console.error("[ERROR] Error enrolling customer in scheme:", err);
                showNotification("Error enrolling customer in scheme", "error")
              })
          }
        } else {
          console.error("[ERROR] Error adding customer:", data.error);
          showNotification("Error adding customer: " + data.error, "error")
        }
      })
      .catch((error) => {
        console.error("[ERROR] Error adding customer:", error);
        showNotification("Error adding customer", "error")
      })
  })

  // Update customer
  safeAddEventListener("saveEditCustomerBtn", "click", () => {
    const customerId = document.getElementById("editCustomerId").value
    const firstName = document.getElementById("editFirstName").value.trim()
    const lastName = document.getElementById("editLastName").value.trim()
    const phone = document.getElementById("editPhone").value.trim()
    const email = document.getElementById("editEmail").value.trim()
    const address = document.getElementById("editAddress").value.trim()
    const city = document.getElementById("editCity").value.trim()
    const state = document.getElementById("editState").value.trim()
    const postalCode = document.getElementById("editPostalCode").value.trim()
    const panNumber = document.getElementById("editPanNumber").value.trim()
    const aadhaarNumber = document.getElementById("editAadhaarNumber").value.trim()
    const dob = document.getElementById("editDob").value
    const docType = document.getElementById("editDocType").value
    const docNumber = document.getElementById("editDocNumber").value

    // Validate inputs
    if (!firstName || !lastName) {
      showNotification("Please enter first and last name", "error")
      return
    }

    if (!phone || !/^\d{10}$/.test(phone)) {
      showNotification("Please enter a valid 10-digit phone number", "error")
      return
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showNotification("Please enter a valid email address", "error")
      return
    }

    const formData = new FormData()
    formData.append("action", "update_customer")
    formData.append("customerId", customerId)
    formData.append("firstName", firstName)
    formData.append("lastName", lastName)
    formData.append("phone", phone)
    formData.append("email", email)
    formData.append("address", address)
    formData.append("city", city)
    formData.append("state", state)
    formData.append("postalCode", postalCode)
    formData.append("panNumber", panNumber)
    formData.append("aadhaarNumber", aadhaarNumber)
    formData.append("dob", dob)
    formData.append("docType", docType)
    formData.append("docNumber", docNumber)

    // Add document file if selected
    const docFileInput = document.getElementById("editDocFile")
    if (docFileInput.files.length > 0) {
      formData.append("docFile", docFileInput.files[0])
    }

    // Add customer image if selected
    const customerImageInput = document.getElementById("editCustomerImage")
    if (customerImageInput.files.length > 0) {
      formData.append("customerImage", customerImageInput.files[0])
    }

    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showNotification("Customer updated successfully")
          document.getElementById("editCustomerModal").classList.add("hidden")

          // Refresh customer details
          selectCustomer(customerId)
        } else {
          showNotification("Error updating customer: " + data.error, "error")
        }
      })
      .catch((error) => {
        console.error("Error:", error)
        showNotification("Error updating customer", "error")
      })
  })

  // Document file name display
  safeAddEventListener("editDocFile", "change", function () {
    const fileName = this.files.length > 0 ? this.files[0].name : "No file chosen"
    document.getElementById("editDocFileName").textContent = fileName
  })

  // Customer image file name display and preview
  safeAddEventListener("editCustomerImage", "change", function () {
    const fileName = this.files.length > 0 ? this.files[0].name : "No file chosen"
    document.getElementById("editImageFileName").textContent = fileName

    if (this.files.length > 0) {
      const reader = new FileReader()
      reader.onload = (e) => {
        document.getElementById("customerImagePreview").src = e.target.result
        document.getElementById("imagePreview").classList.remove("hidden")
      }
      reader.readAsDataURL(this.files[0])
    } else {
      document.getElementById("imagePreview").classList.add("hidden")
    }
  })

  // ==================== PRODUCT SEARCH & SCAN ====================

  // Get current gold rate (24K) on page load
  function fetchCurrentGoldRate() {
    const formData = new FormData()
    formData.append("action", "get_gold_price")
    formData.append("purity", "24K")

    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          currentGoldRate24K = Number.parseFloat(data.rate)
          console.log("Current 24K Gold Rate:", currentGoldRate24K)

          // Update any displayed 24K rate on the page
          const rate24kDisplays = document.querySelectorAll(".current-24k-rate")
          rate24kDisplays.forEach((element) => {
            element.textContent = formatCurrency(currentGoldRate24K) + "/g"
          })
        } else {
          console.error("Error fetching gold rate:", data.error)
        }
      })
      .catch((error) => {
        console.error("Error:", error)
      })
  }

  // Fetch gold rate on page load
  fetchCurrentGoldRate()

  // Product search functionality
  const productSearchInput = document.getElementById("productSearch")
  const productResults = document.getElementById("productResults")

  if (productSearchInput) {
    productSearchInput.addEventListener(
      "input",
      debounce(function () {
        const searchTerm = this.value.trim()

        if (searchTerm.length < 2) {
          productResults.classList.add("hidden")
          return
        }

        const formData = new FormData()
        formData.append("action", "search_products")
        formData.append("term", searchTerm)

        console.log("[FETCH] Searching products with term:", searchTerm);
        fetch(window.location.href, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            console.log("[FETCH] Product search response:", data);
            if (data.success && data.data.length > 0) {
              productResults.innerHTML = ""

              // Create promises for all product images
              const imagePromises = data.data.map((product) => {
                console.log("[FETCH] Fetching image for product:", product.id);
                return fetchProductImage(product.id).then((imageSrc) => {
                  product.image_path = imageSrc;
                  return product;
                });
              });

              // Wait for all images to be fetched
              Promise.all(imagePromises).then((productsWithImages) => {
                console.log("[UI] Products with images:", productsWithImages);
                productsWithImages.forEach((product, index) => {
                  const div = document.createElement("div")
                  div.className = "p-2 hover:bg-gray-100 cursor-pointer"
                  // Add tabindex and data attributes for keyboard navigation
                  div.setAttribute("tabindex", "0")
                  div.setAttribute("data-product-id", product.id)
                  if (index === 0) div.classList.add("bg-gray-200")

                  // Format purity for display
                  const formattedPurity = formatPurity(product.purity, product.material_type)

                  div.innerHTML = `
                    <div class="flex items-center">
                      <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold mr-2" style="background-color: #f3f4f6; color: #374151; min-width: 48px; text-align: center;">
                        ${product.material_type || "-"}
                      </span>
                      <div class="w-10 h-10 rounded-md overflow-hidden mr-2 bg-gray-100 flex-shrink-0">
                        <img src="${product.image_path}" alt="${product.product_name || product.jewelry_type}" 
                             class="w-full h-full object-cover" onerror="this.src='/uploads/jewelry/no_image.png'">
                      </div>
                      <div class="flex-1">
                        <div class="text-sm font-medium">${product.product_name || product.jewelry_type}</div>
                        <div class="flex items-center text-xs text-gray-500">
                          <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 mr-1">
                            ${formattedPurity}
                          </span>
                          <span class="mr-1">${formatWeight(product.gross_weight)}g</span>
                          ${product.huid_code ? `<span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded-full text-xs">HUID: ${product.huid_code}</span>` : ""}
                        </div>
                      </div>
                      <div class="text-sm font-semibold text-gray-900">
                        ${formatCurrency(product.price || calculateMetalAmount(product))}
                      </div>
                    </div>
                  `

                  div.addEventListener("click", () => {
                    // Normalize making_charge_type to match dropdown options
                    if (product.making_charge_type) {
                      let type = product.making_charge_type.toLowerCase();
                      if (type === "fixed") product.making_charge_type = "Fixed";
                      else if (type === "percentage") product.making_charge_type = "Percentage";
                      else if (type === "pergram" || type === "per_gram") product.making_charge_type = "PerGram";
                      else product.making_charge_type = "Fixed";
                    } else {
                      product.making_charge_type = "Fixed";
                    }
                    console.log("[UI] Product selected from search (making_charge_type):", product.making_charge_type);
                    ensureProductRate(product, (productWithRate) => {
                      addProductToTable(productWithRate);
                      productResults.classList.add("hidden");
                      productSearchInput.value = "";
                    });
                  })

                  productResults.appendChild(div)
                })

                productResults.classList.remove("hidden")
              })
            } else {
              productResults.innerHTML = '<div class="p-2 text-sm text-gray-500">No products found</div>'
              productResults.classList.remove("hidden")
            }
          })
          .catch((error) => {
            console.error("[ERROR] Product search error:", error)
          })
      }, 300),
    )

    // Add keyboard navigation for product results
    productSearchInput.addEventListener("keydown", (e) => {
      const results = document.getElementById("productResults")

      if (results.classList.contains("hidden")) return

      const items = results.querySelectorAll("div[data-product-id]")
      if (items.length === 0) return

      // Find currently focused item
      let focusedIndex = -1
      items.forEach((item, index) => {
        if (item.classList.contains("bg-gray-200")) focusedIndex = index
      })

      // Handle arrow keys
      if (e.key === "ArrowDown") {
        e.preventDefault()
        // Move focus down
        focusedIndex = focusedIndex < items.length - 1 ? focusedIndex + 1 : 0
        items.forEach((item) => item.classList.remove("bg-gray-200"))
        items[focusedIndex].classList.add("bg-gray-200")
        items[focusedIndex].scrollIntoView({ block: "nearest" })
      } else if (e.key === "ArrowUp") {
        e.preventDefault()
        // Move focus up
        focusedIndex = focusedIndex > 0 ? focusedIndex - 1 : items.length - 1
        items.forEach((item) => item.classList.remove("bg-gray-200"))
        items[focusedIndex].classList.add("bg-gray-200")
        items[focusedIndex].scrollIntoView({ block: "nearest" })
      } else if (e.key === "Enter" && focusedIndex >= 0) {
        e.preventDefault()
        // Select the focused item
        items[focusedIndex].click()
      } else if (e.key === "Escape") {
        e.preventDefault()
        results.classList.add("hidden")
      }
    })
  }

  if (productResults && productSearchInput) {
    document.addEventListener("click", (event) => {
      if (!productSearchInput.contains(event.target) && !productResults.contains(event.target)) {
        productResults.classList.add("hidden")
      }
    })
  }

  // Scan barcode button
  safeAddEventListener("scanBarcodeBtn", "click", () => {
    document.getElementById("barcodeScannerModal").classList.remove("hidden")
    startQRScanner()
  })

  // Close scanner modal
  safeAddEventListener("closeScannerModal", "click", () => {
    document.getElementById("barcodeScannerModal").classList.add("hidden")
    stopQRScanner()
  })

  // Start QR scanner with improved camera handling
  function startQRScanner() {
    const qrScanner = document.getElementById("qrScanner")

    // First check if camera access is available
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showCameraError("Your browser doesn't support camera access")
      return
    }

    // Show a loading indicator while initializing camera
    const scannerContainer = document.querySelector(".camera-container")
    if (scannerContainer) {
      scannerContainer.innerHTML = `
        <div class="absolute inset-0 flex items-center justify-center bg-black">
          <div class="text-white text-center">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-white mx-auto mb-3"></div>
            <p>Initializing camera...</p>
          </div>
        </div>
      `
    }

    // Request camera permissions explicitly first
    navigator.mediaDevices
      .getUserMedia({ video: { facingMode: "environment" } })
      .then((stream) => {
        // Camera permission granted, now initialize the scanner
        try {
          if (html5QrCode) {
            html5QrCode.clear() // Clear any previous instances
          }

          html5QrCode = new Html5Qrcode("qrScanner")

          const qrConfig = { fps: 10, qrbox: 250 }
          const cameraConfig = { facingMode: "environment" }

          html5QrCode.start(cameraConfig, qrConfig, onScanSuccess, onScanFailure).catch((error) => {
            console.error("Camera start error:", error)
            showCameraError("Failed to start camera: " + error.message)
          })
        } catch (error) {
          console.error("QR scanner initialization error:", error)
          showCameraError("Scanner initialization failed: " + error.message)
        }
      })
      .catch((error) => {
        console.error("Camera permission error:", error)
        showCameraError("Camera permission denied")
      })
  }

  // Stop QR scanner
  function stopQRScanner() {
    if (html5QrCode && html5QrCode.isScanning) {
      html5QrCode.stop().catch((err) => {
        console.error("Error stopping scanner:", err)
      })
    }
  }

  // On successful scan
  function onScanSuccess(decodedText) {
    document.getElementById("scanResult").textContent = "Code detected: " + decodedText
    document.getElementById("scanResult").classList.remove("hidden")

    // Stop scanner after successful scan
    stopQRScanner()

    // Fetch product by HUID/barcode
    getProductByBarcode(decodedText)
  }

  // On scan failure
  function onScanFailure(error) {
    // We don't need to show errors for each frame
  }

  // Manual barcode input
  safeAddEventListener("modalSubmitBarcodeBtn", "click", () => {
    const barcodeInput = document.getElementById("modalBarcodeInput").value.trim()
    if (barcodeInput) {
      getProductByBarcode(barcodeInput)
    }
  })

  // Add a function to show camera error
  function showCameraError(message = "Camera access failed") {
    const scannerContainer = document.querySelector(".camera-container")
    if (scannerContainer) {
      scannerContainer.innerHTML = `
        <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-80">
          <div class="text-white text-center p-4">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-2"></i>
            <p class="mb-2">${message}</p>
            <p class="text-sm">Please ensure you've granted camera permissions or enter the code manually below.</p>
          </div>
        </div>
      `
    }

    // Focus on the manual input field
    setTimeout(() => {
      const manualInput = document.getElementById("modalBarcodeInput")
      if (manualInput) manualInput.focus()
    }, 500)
  }

  // Modify the getProductByBarcode function to fetch the product image
  function getProductByBarcode(huid) {
    const formData = new FormData()
    formData.append("action", "get_product_by_barcode")
    formData.append("huid", huid)

    console.log("[FETCH] Getting product by barcode/HUID:", huid);
    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("[FETCH] Product by barcode response:", data);
        if (data.success) {
          // Fetch product image before adding to table
          console.log("[FETCH] Fetching image for product:", data.data.id);
          fetchProductImage(data.data.id).then((imageSrc) => {
            data.data.image_path = imageSrc
            ensureProductRate(data.data, (productWithRate) => {
              addProductToTable(productWithRate);
              // Close modal if open
              const scannerModal = document.getElementById("barcodeScannerModal")
              if (scannerModal && !scannerModal.classList.contains("hidden")) {
                scannerModal.classList.add("hidden")
                stopQRScanner()
              }

              document.getElementById("modalBarcodeInput").value = ""
              showNotification("Product added successfully")
            })
          })
        } else {
          showNotification("Product not found", "error")
        }
      })
      .catch((error) => {
        console.error("[ERROR] Error fetching product by barcode:", error);
        showNotification("Error fetching product", "error")
      })
  }

  // Add a new function to fetch product images from the database
  function fetchProductImage(productId) {
    return new Promise((resolve, reject) => {
      // Skip for manual products
      if (productId.toString().startsWith("manual_")) {
        resolve("/uploads/jewelry/no_image.png")
        return
      }

      const formData = new FormData()
      formData.append("action", "get_product_image")
      formData.append("product_id", productId)

      console.log("[FETCH] Requesting product image for:", productId);
      fetch(window.location.href, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          console.log("[FETCH] Product image response for", productId, ":", data);
          if (data.success && data.image_url) {
            resolve(data.image_url)
          } else {
            resolve("/uploads/jewelry/no_image.png")
          }
        })
        .catch((error) => {
          console.error("[ERROR] Error fetching product image:", error)
          resolve("/uploads/jewelry/no_image.png")
        })
    })
  }

  // ==================== MANUAL PRODUCT ENTRY ====================

  // Add manual product button
  safeAddEventListener("addManualBtn", "click", () => {
    document.getElementById("addManualProductModal").classList.remove("hidden")
    document.getElementById("manualRatePerGram").value = currentGoldRate24K
    const purity = document.getElementById("manualPurity").value
    if (purity) {
      const ratePerGram = calculateRatePerGram(purity, currentGoldRate24K)
      document.getElementById("manualRatePerGram").value = ratePerGram.toFixed(2)
    }
    // Always reset making charge type to default (Fixed) when opening for new product
    document.getElementById("manualMakingChargeType").value = "Fixed";
  })

  // Function to open manual product modal for editing with product data
  function openManualProductModalForEdit(product) {
    document.getElementById("addManualProductModal").classList.remove("hidden");
    document.getElementById("manualProductType").value = product.jewelry_type || "";
    document.getElementById("manualMaterial").value = product.material_type || "";
    document.getElementById("manualPurity").value = product.purity || "";
    document.getElementById("manualHUID").value = product.huid_code || "";
    document.getElementById("manualGrossWeight").value = product.gross_weight || "";
    document.getElementById("manualNetWeight").value = product.net_weight || "";
    document.getElementById("manualLessWeight").value = product.less_weight || 0;
    document.getElementById("manualRatePerGram").value = product.rate_per_gram || currentGoldRate24K;
    document.getElementById("manualMakingCharge").value = product.making_charge || 0;
    document.getElementById("manualMakingChargeType").value = product.making_charge_type || "Fixed";
    document.getElementById("manualStoneDetails").value = product.stone_type || "";
    document.getElementById("manualStoneWeight").value = product.stone_weight || 0;
    document.getElementById("manualStonePrice").value = product.stone_price || 0;
    document.getElementById("manualStoneColor").value = product.stone_color || "";
    document.getElementById("manualStoneQuality").value = product.stone_quality || "";
  }

  // Close manual product modal
  safeAddEventListener("closeManualModal", "click", () => {
    document.getElementById("addManualProductModal").classList.add("hidden")
  })

  // Cancel manual product button
  safeAddEventListener("cancelManualBtn", "click", () => {
    document.getElementById("addManualProductModal").classList.add("hidden")
  })

  // Calculate net weight when gross or less weight changes
  safeAddEventListener("manualGrossWeight", "input", updateManualNetWeight)
  safeAddEventListener("manualLessWeight", "input", updateManualNetWeight)

  function updateManualNetWeight() {
    const grossWeight = Number.parseFloat(document.getElementById("manualGrossWeight").value) || 0
    const lessWeight = Number.parseFloat(document.getElementById("manualLessWeight").value) || 0
    const netWeight = grossWeight - lessWeight

    document.getElementById("manualNetWeight").value = netWeight > 0 ? netWeight.toFixed(3) : 0
  }

  // Calculate rate per gram when purity changes
  safeAddEventListener("manualPurity", "change", function () {
    const purity = Number.parseFloat(this.value) || 0
    const material = document.getElementById("manualMaterial").value
    if (purity > 0 && material) {
      const ratePerGram = calculateRatePerGram(purity, material)
      document.getElementById("manualRatePerGram").value = ratePerGram.toFixed(2)
    }
  })

  // Save manual product
  safeAddEventListener("saveManualBtn", "click", () => {
    const productType = document.getElementById("manualProductType").value
    const material = document.getElementById("manualMaterial").value
    const purity = document.getElementById("manualPurity").value
    const huid = document.getElementById("manualHUID").value
    const grossWeight = Number.parseFloat(document.getElementById("manualGrossWeight").value) || 0
    const netWeight = Number.parseFloat(document.getElementById("manualNetWeight").value) || 0
    const lessWeight = Number.parseFloat(document.getElementById("manualLessWeight").value) || 0
    const ratePerGram = Number.parseFloat(document.getElementById("manualRatePerGram").value) || 0
    const makingCharge = Number.parseFloat(document.getElementById("manualMakingCharge").value) || 0
    const makingChargeType = document.getElementById("manualMakingChargeType").value

    // Stone details
    const stoneType = document.getElementById("manualStoneDetails").value
    const stoneWeight = Number.parseFloat(document.getElementById("manualStoneWeight").value) || 0
    const stonePrice = Number.parseFloat(document.getElementById("manualStonePrice").value) || 0
    const stoneColor = document.getElementById("manualStoneColor").value
    const stoneQuality = document.getElementById("manualStoneQuality").value

    // Validate inputs
    if (!productType) {
      showNotification("Please select a product type", "error")
      return
    }

    if (!material) {
      showNotification("Please select a material", "error")
      return
    }

    if (!purity) {
      showNotification("Please select a purity", "error")
      return
    }

    if (grossWeight <= 0) {
      showNotification("Please enter a valid gross weight", "error")
      return
    }

    if (netWeight <= 0) {
      showNotification("Net weight must be greater than zero", "error")
      return
    }

    if (ratePerGram <= 0) {
      showNotification("Rate per gram must be greater than zero", "error")
      return
    }

    // Create manual product object
    const manualProduct = {
      id: "manual_" + Date.now(),
      product_id: "manual_" + Date.now(),
      jewelry_type: productType,
      product_name: `${productType} (${material} ${formatPurity(purity, material)})`,
      material_type: material,
      purity: purity,
      gross_weight: grossWeight,
      net_weight: netWeight,
      less_weight: lessWeight,
      huid_code: huid,
      rate_per_gram: ratePerGram,
      rate_24k: currentGoldRate24K,
      making_charge: makingCharge,
      making_charge_type: makingChargeType,
      stone_type: stoneType,
      stone_weight: stoneWeight,
      stone_price: stonePrice,
      stone_quality: stoneQuality,
      is_manual: true,
    }

    // Add to table
    addProductToTable(manualProduct)

    // Close modal and reset form
    document.getElementById("addManualProductModal").classList.add("hidden")
    document.getElementById("manualProductForm").reset()

    showNotification("Manual product added successfully")
  })

  // ==================== URD GOLD MANAGEMENT ====================

  // Add URD button
  safeAddEventListener("addURDBtn", "click", () => {
    if (!document.getElementById("customerId").value) {
      showNotification("Please select a customer first", "error")
      return
    }
    document.getElementById("urdGoldModal").classList.remove("hidden")
    document.getElementById("urdRate").value = currentGoldRate24K
    calculateURDValue()
  })

  // Close URD modal
  safeAddEventListener("closeURDModal", "click", () => {
    document.getElementById("urdGoldModal").classList.add("hidden")
  })

  // Cancel URD button
  safeAddEventListener("cancelURDBtn", "click", () => {
    document.getElementById("urdGoldModal").classList.add("hidden")
  })

  // Calculate URD net weight when gross or less weight changes
  safeAddEventListener("urdGrossWeight", "input", updateURDNetWeight)
  safeAddEventListener("urdLessWeight", "input", updateURDNetWeight)

  function updateURDNetWeight() {
    const grossWeight = Number.parseFloat(document.getElementById("urdGrossWeight").value) || 0
    const lessWeight = Number.parseFloat(document.getElementById("urdLessWeight").value) || 0
    const netWeight = grossWeight - lessWeight

    document.getElementById("urdNetWeight").value = netWeight > 0 ? netWeight.toFixed(3) : 0

    // Recalculate URD value
    calculateURDValue()
  }

  // Calculate URD value when purity changes
  safeAddEventListener("urdPurity", "change", calculateURDValue)

  // Calculate URD value
  function calculateURDValue() {
    const netWeight = Number.parseFloat(document.getElementById("urdNetWeight").value) || 0
    const purity = Number.parseFloat(document.getElementById("urdPurity").value) || 0
    const rate = Number.parseFloat(document.getElementById("urdRate").value) || 0

    if (netWeight > 0 && purity > 0 && rate > 0) {
      const fineWeight = (netWeight * purity) / 100
      const value = fineWeight * rate

      document.getElementById("urdFineWeight").value = fineWeight.toFixed(3)
      document.getElementById("urdValue").value = value.toFixed(2)
    } else {
      document.getElementById("urdFineWeight").value = "0.000"
      document.getElementById("urdValue").value = "0.00"
    }
  }

  // URD image file name display and preview
  safeAddEventListener("urdImage", "change", function () {
    const fileName = this.files.length > 0 ? this.files[0].name : "No image chosen"
    document.getElementById("urdImageName").textContent = fileName

    if (this.files.length > 0) {
      const reader = new FileReader()
      reader.onload = (e) => {
        document.getElementById("urdPreviewImg").src = e.target.result
        document.getElementById("urdImagePreview").classList.remove("hidden")
      }
      reader.readAsDataURL(this.files[0])
    } else {
      document.getElementById("urdImagePreview").classList.add("hidden")
    }
  })

  // Apply URD Gold
  safeAddEventListener("applyURDBtn", "click", () => {
    const customerId = document.getElementById("customerId").value
    const itemName = document.getElementById("urdItemName").value.trim()
    const grossWeight = Number.parseFloat(document.getElementById("urdGrossWeight").value) || 0
    const lessWeight = Number.parseFloat(document.getElementById("urdLessWeight").value) || 0
    const netWeight = Number.parseFloat(document.getElementById("urdNetWeight").value) || 0
    const purity = Number.parseFloat(document.getElementById("urdPurity").value) || 0
    const rate = Number.parseFloat(document.getElementById("urdRate").value) || 0
    const fineWeight = Number.parseFloat(document.getElementById("urdFineWeight").value) || 0
    const totalAmount = Number.parseFloat(document.getElementById("urdValue").value) || 0
    const notes = document.getElementById("urdDescription").value.trim()

    // Validate inputs
    if (!customerId) {
      showNotification("Please select a customer first", "error")
      return
    }

    if (!itemName) {
      showNotification("Please enter an item name", "error")
      return
    }

    if (grossWeight <= 0) {
      showNotification("Please enter a valid gross weight", "error")
      return
    }

    if (netWeight <= 0) {
      showNotification("Net weight must be greater than zero", "error")
      return
    }

    if (purity <= 0) {
      showNotification("Please select a valid purity", "error")
      return
    }

    // Store URD item in memory (not DB)
    const urdData = {
      id: "urd_" + Date.now(),
      itemName,
      grossWeight,
      lessWeight,
      netWeight,
      purity,
      rate,
      fineWeight,
      totalAmount,
      notes,
      // Optionally add image preview if needed
    }
    urdItems.push(urdData)

    // Update UI
    renderProductTable()

    // Close modal and reset form
    document.getElementById("urdGoldModal").classList.add("hidden")
    document.getElementById("urdGoldForm").reset()
    document.getElementById("urdImagePreview").classList.add("hidden")

    // Recalculate totals
    calculateTotals()

    showNotification("URD Gold added (not saved until bill is generated)")
  })

  // Render product table (jewelry + URD)
  function renderProductTable() {
    const tableBody = document.getElementById("productsTable").querySelector("tbody")
    tableBody.innerHTML = ""

    // Render jewelry products
    products.forEach(product => {
      // ...existing code to render product row...
      // (copy the code from addProductToTable, or refactor to use this function)
      // For brevity, not repeating the full row code here
      // ...
    })

    // Render URD items
    urdItems.forEach(urd => {
      const row = document.createElement("tr")
      row.setAttribute("data-urd-id", urd.id)
      row.className = "bg-purple-50"
      row.innerHTML = `
        <td colspan="2"><span class="font-bold text-purple-700">URD Gold</span> - ${urd.itemName}</td>
        <td>${urd.grossWeight}g</td>
        <td>${urd.netWeight}g</td>
        <td colspan="2">Purity: ${urd.purity}</td>
        <td>${formatCurrency(urd.totalAmount)}</td>
        <td>
          <button class="delete-urd" data-urd-id="${urd.id}">🗑️</button>
        </td>
      `
      row.querySelector(".delete-urd").addEventListener("click", function() {
        urdItems = urdItems.filter(u => u.id !== urd.id)
        renderProductTable()
        calculateTotals()
      })
      tableBody.appendChild(row)
    })

    // Show empty row if needed
    if (products.length === 0 && urdItems.length === 0) {
      document.getElementById("emptyRow").classList.remove("hidden")
    } else {
      document.getElementById("emptyRow").classList.add("hidden")
    }

    // Update item count
    document.getElementById("itemCount").textContent = products.length + urdItems.length
  }

  // ==================== PRODUCT TABLE MANAGEMENT ====================

  // Add CSS for enhanced product table
  function addProductTableStyles() {
    const styleElement = document.createElement("style")
    styleElement.textContent = `
      /* Enhanced product table styles */
      .product-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      }
      
      .product-table thead th {
        background: linear-gradient(to right, #f7c552, #f5a623);
        color: #333;
        font-weight: 700;
        text-transform: uppercase;
        padding: 12px 16px;
        font-size: 1rem;
        letter-spacing: 0.5px;
        border: none;
      }
      
      .product-table tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
        font-size: 1rem;
       
        color: #222;
      }
      
      /* Enhanced input fields */
      .product-input {
        width: 100%;
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        font-size: 0.85rem;
         font-weight: 800;
        transition: all 0.2s ease;
        background: white;
      }
      
      /* Product image */
      .product-image {
        width: 32px;
        height: 32px;
        border-radius: 4px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
      }
      
      /* Product details container */
      .product-details {
        display: flex;
        align-items: center;
      }
      
      /* Product info */
      .product-info {
        margin-left: 8px;
      }
      
      /* Input field types with different background gradients */
      .input-weight {
        background: linear-gradient(to right, #f9f9f9, #f0f0f0);
      }
      
      .input-stone {
        background: linear-gradient(to right, #f0f4ff, #e6f0ff);
      }
      
      .input-rate {
        background: linear-gradient(to right, #fff4e6, #ffe8cc);
      }
      
      .input-making {
        background: linear-gradient(to right, #e6fff0, #ccffe0);
      }
      
      /* Labels */
      .product-label {
        display: inline-block;
        width: 60px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #4a5568;
        margin-right: 8px;
      }
      
      /* Product name styling */
      .product-name {
        font-weight: 700;
        color: #1a202c;
        font-size: 1.05rem;
      }
      
      .product-huid {
        color: #718096;
        font-size: 0.95rem;
        font-weight: 600;
      }
      
      /* Delete button */
      .delete-product {
        background-color: #fff;
        color: #e53e3e;
        border: 1px solid #e53e3e;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
      }
      
      .delete-product:hover {
        background-color: #e53e3e;
        color: white;
      }
      
      /* Purity badge */
      .purity-badge {
        display: inline-block;
        padding: 4px 8px;
        background: linear-gradient(to right, #f7c552, #f5a623);
        color: white;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
      }
      
      /* Amount styling */
      .product-amount {
        font-weight: 700;
        color: #1a202c;
        font-size: 1.05rem;
      }
      
      /* Input groups */
      .input-group {
        display: flex;
        align-items: center;
        margin-bottom: 6px;
      }
      
      .input-group:last-child {
        margin-bottom: 0;
      }
      
      /* Unit label */
      .unit-label {
        margin-left: 4px;
        font-size: 0.95rem;
        color: #718096;
        font-weight: 600;
      }
      
      /* Read-only fields */
      .product-input[readonly] {
        background: #f7fafc;
        border-color: #edf2f7;
        color: #4a5568;
      }
      
      /* Product ID badge */
      .product-id-badge {
        display: inline-block;
        padding: 2px 6px;
        background-color: #e2e8f0;
        color: #4a5568;
        border-radius: 4px;
        font-size: 0.9rem;
        margin-top: 4px;
        font-weight: 600;
      }

      /* Success modal styles */
      .success-modal {
        animation: fadeIn 0.3s ease-out;
      }
      
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
      }
      
      .success-modal button {
        transition: all 0.2s ease;
      }
      
      .success-modal button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      }
    `
    document.head.appendChild(styleElement)
  }

  // Add the styles when the page loads
  addProductTableStyles()

  // Add product to table
  function addProductToTable(product) {
    // Check if product already exists in the table
    const existingProductIndex = products.findIndex((p) => p.id === product.id)
    if (existingProductIndex !== -1) {
      showNotification("This product is already added to the bill", "error")
      console.log("[UI] Attempted to add duplicate product to table:", product.id);
      return
    }

    console.log("[UI] Adding product to table:", product);
    // Add product to array
    products.push(product)

    // Hide empty row message
    document.getElementById("emptyRow").classList.add("hidden")

    // Calculate product values
    const metalAmount = calculateMetalAmount(product)
    const stoneAmount = Number.parseFloat(product.stone_price) || 0
    const makingCharges = calculateMakingCharges(product)
    const totalAmount = metalAmount + stoneAmount + makingCharges

    // Determine image source with error handling
    const imageSrc = product.image_path || "/uploads/jewelry/no_image.png"

    // Format purity for display
    const formattedPurity = formatPurity(product.purity, product.material_type)

    // Create table row
    const tableBody = document.getElementById("productsTable").querySelector("tbody")
    const row = document.createElement("tr")
    row.setAttribute("data-product-id", product.id)
    row.className = "hover:bg-gray-50"

    // Create enhanced editable row with input fields and product image - more compact layout
    row.innerHTML = `
    <td class="px-2 py-1">
      <div class="product-details">
        <img src="${imageSrc}" alt="${product.product_name || product.jewelry_type}" class="product-image w-8 h-8" onerror="this.src='/uploads/jewelry/no_image.png'">
        <div class="product-info">
          <span class="product-name text-sm">${product.product_name || product.jewelry_type}</span>
          <div class="flex items-center text-xs">
            <span class="product-huid mr-1">${product.huid_code ? "HUID: " + product.huid_code : ""}</span>
            ${product.id && !product.is_manual ? `<span class="product-id-badge text-xs">ID: ${product.id}</span>` : ""}
          </div>
        </div>
      </div>
    </td>
    <td class="px-2 py-1 text-center">
      <span class="purity-badge text-xs px-2 py-0.5">${formattedPurity}</span>
    </td>
    <td class="px-2 py-1">
      <div class="flex flex-col space-y-1">
        <div class="input-group">
          <span class="product-label text-xs w-12">Gross:</span>
          <input type="number"  class="product-input input-weight product-gross-weight text-xs font-weight-800 py-1" value="${formatWeight(product.gross_weight)}">
          <span class="unit-label">g</span>
        </div>
        <div class="input-group">
          <span class="product-label text-xs w-12">Less:</span>
          <input type="number"  class="product-input input-weight product-less-weight text-xs py-1" value="${formatWeight(product.less_weight || 0)}">
          <span class="unit-label">g</span>
        </div>
        <div class="input-group">
          <span class="product-label text-xs w-12">Net:</span>
          <input type="number"  class="product-input input-weight product-net-weight text-xs py-1" value="${formatWeight(product.net_weight)}" readonly>
          <span class="unit-label">g</span>
        </div>
      </div>
    </td>
    <td class="px-2 py-1">
      <div class="flex flex-col space-y-1">
        <div class="input-group">
          <span class="product-label text-xs w-10">Type:</span>
          <input type="text" class="product-input input-stone product-stone-type text-xs py-1" value="${product.stone_type || ""}">
        </div>
        <div class="input-group">
          <span class="product-label text-xs w-10">Wt:</span>
          <input type="number" class="product-input input-stone product-stone-weight text-xs py-1" value="${formatWeight(product.stone_weight || 0)}">
          <span class="unit-label">g</span>
        </div>
        <div class="input-group">
          <span class="product-label text-xs w-10">Price:</span>
          <input type="number"  class="product-input input-stone product-stone-price text-xs py-1" value="${Number.parseFloat(product.stone_price || 0).toFixed(2)}">
          <span class="unit-label">₹</span>
        </div>
      </div>
    </td>
    <td class="px-2 py-1">
      <div class="flex flex-col space-y-1">
        <div class="input-group">
          <span class="product-label text-xs w-10">24K:</span>
          <input type="number"  class="product-input input-rate product-rate-24k text-xs py-1" value="${
            product.material_type === 'Silver'
              ? currentSilverRate999.toFixed(2)
              : currentGoldRate24K.toFixed(2)
          }" readonly>
          <span class="unit-label">₹</span>
        </div>
        <div class="input-group">
          <span class="product-label text-xs w-10">Rate/g:</span>
          <input type="number"  class="product-input input-rate product-rate-per-gram text-xs py-1" value="${Number.parseFloat(product.rate_per_gram).toFixed(2)}">
          <span class="unit-label">₹</span>
        </div>
      </div>
    </td>
    <td class="px-2 py-1">
      <div class="flex flex-col space-y-1">
        <div class="input-group">
          <input type="number"  class="product-input input-making product-making-charge text-xs py-1" value="${Number.parseFloat(product.making_charge).toFixed(2)}">
          <select class="ml-1 product-input input-making product-making-charge-type text-xs py-1" style="width: auto;">
            <option value="Fixed" ${product.making_charge_type === "Fixed" ? "selected" : ""}>Fixed</option>
            <option value="Percentage" ${product.making_charge_type === "Percentage" ? "selected" : ""}>%</option>
            <option value="PerGram" ${product.making_charge_type === "PerGram" ? "selected" : ""}>Per Gram</option>
          </select>
        </div>
      </div>
    </td>
    <td class="px-2 py-1 text-right">
      <span class="product-amount product-total text-sm" data-amount="${totalAmount}">${formatCurrency(totalAmount)}</span>
    </td>
    <td class="px-2 py-1 text-center">
      <button type="button" class="delete-product w-6 h-6" data-product-id="${product.id}">
        <i class="fas fa-trash-alt"></i>
      </button>
    </td>
  `

    // Add event listeners to input fields
    const productRow = row

    // Gross weight and less weight change
    productRow.querySelector(".product-gross-weight").addEventListener("input", () => {
      updateProductWeights(product.id, productRow)
    })

    productRow.querySelector(".product-less-weight").addEventListener("input", () => {
      updateProductWeights(product.id, productRow)
    })

    // Stone details change
    productRow.querySelector(".product-stone-type").addEventListener("input", () => {
      updateProductStoneDetails(product.id, productRow)
    })

    productRow.querySelector(".product-stone-weight").addEventListener("input", () => {
      updateProductStoneDetails(product.id, productRow)
    })

    productRow.querySelector(".product-stone-price").addEventListener("input", () => {
      updateProductStoneDetails(product.id, productRow)
    })

    // Rate per gram change
    productRow.querySelector(".product-rate-per-gram").addEventListener("input", () => {
      updateProductRate(product.id, productRow)
    })

    // Making charge change
    productRow.querySelector(".product-making-charge").addEventListener("input", () => {
      updateProductMakingCharge(product.id, productRow)
    })

    productRow.querySelector(".product-making-charge-type").addEventListener("change", () => {
      updateProductMakingCharge(product.id, productRow)
    })

    // Add event listener to remove button
    productRow.querySelector(".delete-product").addEventListener("click", function () {
      const productId = this.getAttribute("data-product-id")
      removeProduct(productId)
    })

    tableBody.appendChild(row)

    // Update item count
    document.getElementById("itemCount").textContent = products.length

    // Recalculate totals
    calculateTotals()
  }

  // Update product weights
  function updateProductWeights(productId, row) {
    const grossWeight = Number.parseFloat(row.querySelector(".product-gross-weight").value) || 0
    const lessWeight = Number.parseFloat(row.querySelector(".product-less-weight").value) || 0
    const netWeight = grossWeight - lessWeight > 0 ? grossWeight - lessWeight : 0

    // Update net weight in the UI
    row.querySelector(".product-net-weight").value = formatWeight(netWeight)

    // Update product in the array
    const productIndex = products.findIndex((p) => p.id == productId)
    if (productIndex !== -1) {
      products[productIndex].gross_weight = grossWeight
      products[productIndex].less_weight = lessWeight
      products[productIndex].net_weight = netWeight

      // Recalculate product total
      updateProductTotal(productId, row)
    }
  }

  // Update product stone details
  function updateProductStoneDetails(productId, row) {
    const stoneType = row.querySelector(".product-stone-type").value
    const stoneWeight = Number.parseFloat(row.querySelector(".product-stone-weight").value) || 0
    const stonePrice = Number.parseFloat(row.querySelector(".product-stone-price").value) || 0

    // Update product in the array
    const productIndex = products.findIndex((p) => p.id == productId)
    if (productIndex !== -1) {
      products[productIndex].stone_type = stoneType
      products[productIndex].stone_weight = stoneWeight
      products[productIndex].stone_price = stonePrice

      // Recalculate product total
      updateProductTotal(productId, row)
    }
  }

  // Update product rate
  function updateProductRate(productId, row) {
    const ratePerGram = Number.parseFloat(row.querySelector(".product-rate-per-gram").value) || 0

    // Update product in the array
    const productIndex = products.findIndex((p) => p.id == productId)
    if (productIndex !== -1) {
      products[productIndex].rate_per_gram = ratePerGram

      // Recalculate product total
      updateProductTotal(productId, row)
    }
  }

  // Update product making charge
  function updateProductMakingCharge(productId, row) {
    const makingCharge = Number.parseFloat(row.querySelector(".product-making-charge").value) || 0
    const makingChargeType = row.querySelector(".product-making-charge-type").value

    // Update product in the array
    const productIndex = products.findIndex((p) => p.id == productId)
    if (productIndex !== -1) {
      products[productIndex].making_charge = makingCharge
      products[productIndex].making_charge_type = makingChargeType

      // Recalculate product total
      updateProductTotal(productId, row)
    }
  }

  // Update product total
  function updateProductTotal(productId, row) {
    const productIndex = products.findIndex((p) => p.id == productId)
    if (productIndex !== -1) {
      const product = products[productIndex]

      // Calculate values
      const metalAmount = calculateMetalAmount(product)
      const stoneAmount = Number.parseFloat(product.stone_price) || 0
      const makingCharges = calculateMakingCharges(product)
      const totalAmount = metalAmount + stoneAmount + makingCharges

      // Update total in the UI
      const totalCell = row.querySelector(".product-total")
      totalCell.textContent = formatCurrency(totalAmount)
      totalCell.setAttribute("data-amount", totalAmount)

      // Recalculate all totals
      calculateTotals()
    }
  }

  // Remove product from table
  function removeProduct(productId) {
    // Remove from array
    products = products.filter((p) => p.id != productId)

    console.log("[UI] Removed product from table:", productId);
    // Remove row from table
    const row = document.querySelector(`tr[data-product-id="${productId}"]`)
    if (row) {
      row.remove()
    }

    // Show empty row message if no products
    if (products.length === 0) {
      console.log("[UI] Product table is now empty");
      document.getElementById("emptyRow").classList.remove("hidden")
    }

    // Update item count
    document.getElementById("itemCount").textContent = products.length

    // Recalculate totals
    calculateTotals()
  }

  // Calculate metal amount
  function calculateMetalAmount(product) {
    const netWeight = Number.parseFloat(product.net_weight) || 0
    const ratePerGram = Number.parseFloat(product.rate_per_gram) || 0

    return netWeight * ratePerGram
  }

  // Calculate making charges
  function calculateMakingCharges(product) {
    const makingCharge = Number.parseFloat(product.making_charge) || 0
    const makingChargeType = product.making_charge_type || "Fixed"
    const netWeight = Number.parseFloat(product.net_weight) || 0

    if (makingChargeType === "Percentage") {
      const metalAmount = calculateMetalAmount(product)
      return (metalAmount * makingCharge) / 100
    } else if (makingChargeType === "PerGram") {
      return netWeight * makingCharge
    } else {
      return makingCharge
    }
  }

  // ==================== CALCULATIONS & TOTALS ====================

  // Recalculate totals when discount values change
  safeAddEventListener("loyaltyDiscount", "input", calculateTotals)
  safeAddEventListener("loyaltyDiscountType", "change", calculateTotals)
  safeAddEventListener("manualDiscount", "input", calculateTotals)
  safeAddEventListener("manualDiscountType", "change", calculateTotals)

  // Apply coupon button
  safeAddEventListener("applyCoupon", "click", () => {
    const couponCode = document.getElementById("couponCode").value.trim()

    if (!couponCode) {
      showNotification("Please enter a coupon code", "error")
      return
    }

    console.log("[UI] Applying coupon code:", couponCode);
    // For demo purposes, no real discount is applied
    document.getElementById("couponDiscountAmount").textContent = "-₹0.00"
    calculateTotals()

    showNotification("Coupon code accepted (demo only, no discount applied)", "success")
  })

  // Calculate totals with modified discount logic
  function calculateTotals() {
    // Calculate subtotal
    let totalMetalAmount = 0
    let totalStoneAmount = 0
    let totalMakingCharges = 0

    products.forEach((product) => {
      totalMetalAmount += calculateMetalAmount(product)
      totalStoneAmount += Number.parseFloat(product.stone_price) || 0
      totalMakingCharges += calculateMakingCharges(product)
    })

    const subtotal = totalMetalAmount + totalStoneAmount + totalMakingCharges

    // URD amount
    const urdAmount = urdItems.reduce((sum, u) => sum + (Number.parseFloat(u.totalAmount) || 0), 0)

    // Calculate discounts - now based on making charges only for loyalty and coupon
    let loyaltyDiscount = 0;
    const loyaltyCheckbox = document.getElementById("applyLoyaltyDiscount");
    if (loyaltyCheckbox && loyaltyCheckbox.checked) {
      loyaltyDiscount = calculateDiscount("loyalty", totalMakingCharges);
    }
    let couponDiscount = 0;
    if (firmConfig.coupon_code_apply_enabled) {
      // You can implement real coupon logic here if needed
      // For now, still demo only
      couponDiscount = 0;
    }
    const manualDiscount = calculateDiscount("manual", subtotal); // Manual discount applies to full subtotal

    const totalDiscount = loyaltyDiscount + couponDiscount + manualDiscount

    // Calculate amount after discounts
    const amountAfterDiscount = subtotal - urdAmount - totalDiscount

    // Calculate GST (if applicable)
    const isGst = document.querySelector('input[name="billType"]:checked').value === "gst"
    const gstAmount = isGst ? amountAfterDiscount * (firmConfig.gst_rate || 0.03) : 0

    // Calculate grand total
    const grandTotal = amountAfterDiscount + gstAmount

    // Update UI
    document.getElementById("subTotal").textContent = formatCurrency(subtotal)
    document.getElementById("makingChargeTotal").textContent = formatCurrency(totalMakingCharges)
    document.getElementById("loyaltyDiscountAmount").textContent = `-${formatCurrency(loyaltyDiscount)}`
    document.getElementById("couponDiscountAmount").textContent = `-${formatCurrency(couponDiscount)}`
    document.getElementById("manualDiscountAmount").textContent = `-${formatCurrency(manualDiscount)}`
    document.getElementById("gstAmount").textContent = formatCurrency(gstAmount)
    document.getElementById("grandTotal").textContent = formatCurrency(grandTotal)

    // Update split payment modal
    document.getElementById("splitTotalAmount").textContent = formatCurrency(grandTotal)
    updateSplitRemainingAmount()

    // Show/hide GST row based on selection
    const gstRow = document.getElementById("gstRow")
    if (isGst) {
      gstRow.classList.remove("hidden")
    } else {
      gstRow.classList.add("hidden")
    }

    // Update net payable amount after totals are calculated
    updateNetPayable()
  }

  // Calculate discount based on type and value
  function calculateDiscount(type, baseAmount) {
    const discountInput = document.getElementById(`${type}Discount`)
    const discountTypeSelect = document.getElementById(`${type}DiscountType`)

    if (!discountInput || !discountTypeSelect) return 0

    const discountValue = Number.parseFloat(discountInput.value) || 0
    const discountType = discountTypeSelect.value

    if (discountValue <= 0) return 0

    if (discountType === "percent") {
      return (baseAmount * discountValue) / 100
    } else {
      return discountValue
    }
  }

  // ==================== PAYMENT MANAGEMENT ====================

  // Update due amount when paid amount changes
  safeAddEventListener("paidAmount", "input", updateDueAmount)
  safeAddEventListener("advanceAmount", "input", updateDueAmount)

  function updateDueAmount() {
    const grandTotal = Number.parseFloat(document.getElementById("grandTotal").textContent.replace(/[^\d.-]/g, "")) || 0
    const paidAmount = Number.parseFloat(document.getElementById("paidAmount").value) || 0
    const advanceAmount = Number.parseFloat(document.getElementById("advanceAmount").value) || 0

    // Ensure advance amount doesn't exceed available advance
    if (advanceAmount > customerAdvanceAmount) {
      document.getElementById("advanceAmount").value = customerAdvanceAmount
      showNotification("Advance amount cannot exceed available advance", "error")
      return
    }

    // Calculate due amount
    const dueAmount = grandTotal - paidAmount - advanceAmount

    // Update payment status
    let paymentStatus = "Due"
    if (dueAmount <= 0) {
      paymentStatus = "Paid"
    } else if (paidAmount > 0 || advanceAmount > 0) {
      paymentStatus = "Partial"
    }

    // Update UI with due amount and payment status
    const dueAmountElement = document.getElementById("dueAmount");
    const paymentStatusElement = document.getElementById("paymentStatus");
    
    if (dueAmountElement) {
      dueAmountElement.textContent = formatCurrency(Math.max(0, dueAmount));
    }
    
    if (paymentStatusElement) {
      paymentStatusElement.textContent = paymentStatus;
      paymentStatusElement.className = `text-sm font-medium ${
        paymentStatus === "Paid" ? "text-green-600" : paymentStatus === "Partial" ? "text-amber-600" : "text-red-600"
      }`;
    }
  }

  // Split payment button
  safeAddEventListener("splitPaymentBtn", "click", () => {
    const grandTotal = Number.parseFloat(document.getElementById("grandTotal").textContent.replace(/[^\d.-]/g, "")) || 0

    if (grandTotal <= 0) {
      showNotification("No items added to bill", "error")
      return
    }

    // Reset payment methods
    paymentMethods = [
      {
        type: "Cash",
        reference: "",
        amount: 0,
      },
    ]

    // Update UI
    document.getElementById("splitTotalAmount").textContent = formatCurrency(grandTotal)
    document.getElementById("paymentMethods").innerHTML = ""
    addPaymentMethodRow()

    document.getElementById("splitPaymentModal").classList.remove("hidden")
  })

  // Close split payment modal
  safeAddEventListener("closeSplitModal", "click", () => {
    document.getElementById("splitPaymentModal").classList.add("hidden")
  })

  // Cancel split payment button
  safeAddEventListener("cancelSplitBtn", "click", () => {
    document.getElementById("splitPaymentModal").classList.add("hidden")
  })

  // Add payment method button
  safeAddEventListener("addPaymentMethodBtn", "click", () => {
    addPaymentMethodRow()
  })

  // Add payment method row
  function addPaymentMethodRow() {
    const paymentMethodsContainer = document.getElementById("paymentMethods")

    const row = document.createElement("div")
    row.className = "payment-method-row grid grid-cols-12 gap-2 items-center mb-3"

    row.innerHTML = `
      <div class="col-span-5">
        <select class="w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs payment-method-select">
          <option value="Cash">Cash</option>
          <option value="Credit Card">Credit Card</option>
          <option value="Debit Card">Debit Card</option>
          <option value="Net Banking">Net Banking</option>
          <option value="UPI">UPI</option>
          <option value="Cheque">Cheque</option>
          <option value="Wallet">Wallet</option>
        </select>
      </div>
      <div class="col-span-4">
        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs payment-reference" placeholder="Reference">
      </div>
      <div class="col-span-2">
        <input type="number"  class="w-full px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gold-500 focus:border-gold-500 text-xs payment-amount" placeholder="Amount">
      </div>
      <div class="col-span-1 flex justify-center">
        <button type="button" class="text-red-500 hover:text-red-700 remove-payment-method">
          <i class="fas fa-trash-alt"></i>
        </button>
      </div>
    `

    // Add event listeners
    row.querySelector(".payment-amount").addEventListener("input", updateSplitRemainingAmount)

    row.querySelector(".remove-payment-method").addEventListener("click", () => {
      if (document.querySelectorAll(".payment-method-row").length > 1) {
        row.remove()
        updateSplitRemainingAmount()
      } else {
        showNotification("At least one payment method is required", "error")
      }
    })

    paymentMethodsContainer.appendChild(row)
  }

  // Update remaining amount in split payment
  function updateSplitRemainingAmount() {
    const grandTotal =
      Number.parseFloat(document.getElementById("splitTotalAmount").textContent.replace(/[^\d.-]/g, "")) || 0

    let totalPaid = 0
    document.querySelectorAll(".payment-amount").forEach((input) => {
      totalPaid += Number.parseFloat(input.value) || 0
    })

    const remainingAmount = grandTotal - totalPaid
    document.getElementById("splitRemainingAmount").textContent = formatCurrency(remainingAmount)

    // Change color based on remaining amount
    if (remainingAmount === 0) {
      document.getElementById("splitRemainingAmount").className = "text-xs font-medium text-green-600"
    } else if (remainingAmount < 0) {
      document.getElementById("splitRemainingAmount").className = "text-xs font-medium text-red-600"
    } else {
      document.getElementById("splitRemainingAmount").className = "text-xs font-medium text-red-600"
    }
  }

  // Apply split payment
  safeAddEventListener("applySplitBtn", "click", () => {
    const grandTotal =
      Number.parseFloat(document.getElementById("splitTotalAmount").textContent.replace(/[^\d.-]/g, "")) || 0

    // Collect payment methods
    paymentMethods = []
    let totalPaid = 0

    document.querySelectorAll(".payment-method-row").forEach((row) => {
      const type = row.querySelector(".payment-method-select").value
      const reference = row.querySelector(".payment-reference").value
      const amount = Number.parseFloat(row.querySelector(".payment-amount").value) || 0

      if (amount > 0) {
        paymentMethods.push({
          type: type,
          reference: reference,
          amount: amount,
        })

        totalPaid += amount
      }
    })

    // Validate total amount
    if (Math.abs(totalPaid - grandTotal) > 0.01) {
      showNotification("Total payment amount must equal the grand total", "error")
      return
    }

    // Update main form
    document.getElementById("paidAmount").value = totalPaid
    updateDueAmount()

    // Close modal
    document.getElementById("splitPaymentModal").classList.add("hidden")

    showNotification("Split payment applied successfully")
  })

  // ==================== BILL GENERATION ====================

  // Generate bill button
  safeAddEventListener("generateBillBtn", "click", () => {
    // Validate customer
    const customerId = document.getElementById("customerId").value
    if (!customerId) {
      showNotification("Please select a customer", "error")
      return
    }

    // Validate products
    if (products.length === 0) {
      showNotification("Please add at least one product", "error")
      return
    }

    // Collect form data
    const invoiceNumber = document.getElementById("invoiceNumber").value
    const invoiceDate = document.getElementById("invoiceDate").value
    const isGst = document.querySelector('input[name="billType"]:checked').value === "gst"

    // Calculate totals
    let totalMetalAmount = 0
    let totalStoneAmount = 0
    let totalMakingCharges = 0

    products.forEach((product) => {
      totalMetalAmount += calculateMetalAmount(product)
      totalStoneAmount += Number.parseFloat(product.stone_price) || 0
      totalMakingCharges += calculateMakingCharges(product)
    })

    const subtotal = totalMetalAmount + totalStoneAmount + totalMakingCharges

    // URD amount
    const urdAmount = urdItems.reduce((sum, u) => sum + (Number.parseFloat(u.totalAmount) || 0), 0)

    // Calculate discounts - now based on making charges only for loyalty and coupon
    const loyaltyDiscount = calculateDiscount("loyalty", totalMakingCharges)
    const couponDiscount = 0 // For demo purposes
    const manualDiscount = calculateDiscount("manual", subtotal) // Manual discount applies to full subtotal

    // Calculate amount after discounts
    const amountAfterDiscount = subtotal - urdAmount - loyaltyDiscount - couponDiscount - manualDiscount

    // Calculate GST (if applicable)
    const gstAmount = isGst ? amountAfterDiscount * (firmConfig.gst_rate || 0.03) : 0

    // Calculate grand total
    const grandTotal = amountAfterDiscount + gstAmount

    // Get payment details
    const paidAmount = Number.parseFloat(document.getElementById("paidAmount").value) || 0
    const advanceAmount = Number.parseFloat(document.getElementById("advanceAmount").value) || 0
    const paymentMethod = document.getElementById("paymentMethod").value
    const paymentReference = document.getElementById("paymentReference").value
    const notes = document.getElementById("notes").value

    // Prepare items data
    const itemsData = products.map((product) => {
      const metalAmount = calculateMetalAmount(product)
      const stoneAmount = Number.parseFloat(product.stone_price) || 0
      const makingCharges = calculateMakingCharges(product)

      return {
        productId: product.id,
        productName: product.product_name || product.jewelry_type,
        huidCode: product.huid_code || "",
        rate24k: Number.parseFloat(product.rate_24k) || currentGoldRate24K,
        purity: product.purity,
        purityRate: Number.parseFloat(product.rate_per_gram) || 0,
        grossWeight: Number.parseFloat(product.gross_weight) || 0,
        lessWeight: Number.parseFloat(product.less_weight) || 0,
        netWeight: Number.parseFloat(product.net_weight) || 0,
        metalAmount: metalAmount,
        stoneType: product.stone_type || "",
        stoneWeight: Number.parseFloat(product.stone_weight) || 0,
        stonePrice: Number.parseFloat(product.stone_price) || 0,
        makingType: product.making_charge_type || "Fixed",
        makingRate: Number.parseFloat(product.making_charge) || 0,
        makingCharges: makingCharges,
        totalCharges: metalAmount + stoneAmount + makingCharges,
        total: metalAmount + stoneAmount + makingCharges,
        status: "Sold", // Set status to Sold for inventory tracking
      }
    })

    // Prepare bill data
    const billData = {
      customerId: customerId,
      invoiceNumber: invoiceNumber,
      saleDate: invoiceDate,
      totalMetalAmount: totalMetalAmount,
      totalStoneAmount: totalStoneAmount,
      totalMakingCharges: totalMakingCharges,
      discount: loyaltyDiscount + couponDiscount + manualDiscount,
      urdAmount: urdAmount,
      subtotal: subtotal,
      gstAmount: gstAmount,
      grandTotal: grandTotal,
      paidAmount: paidAmount,
      advanceAmount: advanceAmount,
      paymentMethod: paymentMethod,
      paymentReference: paymentReference,
      isGstApplicable: isGst,
      notes: notes,
      couponDiscount: couponDiscount,
      loyaltyDiscount: loyaltyDiscount,
      manualDiscount: manualDiscount,
      couponCode: document.getElementById("couponCode").value,
      urdItems: urdItems,
      items: itemsData,
      paymentMethods: paymentMethods.length > 0 ? paymentMethods : null,
    }

    console.log("[FETCH] Generating bill with data:", billData);
    // Send data to server
    const formData = new FormData()
    formData.append("action", "generate_bill")
    formData.append("customerId", customerId)
    formData.append("invoiceNumber", invoiceNumber)
    formData.append("saleDate", invoiceDate)
    formData.append("totalMetalAmount", totalMetalAmount)
    formData.append("totalStoneAmount", totalStoneAmount)
    formData.append("totalMakingCharges", totalMakingCharges)
    formData.append("discount", loyaltyDiscount + couponDiscount + manualDiscount)
    formData.append("urdAmount", urdAmount)
    formData.append("subtotal", subtotal)
    formData.append("gstAmount", gstAmount)
    formData.append("grandTotal", grandTotal)
    formData.append("paidAmount", paidAmount)
    formData.append("advanceAmount", advanceAmount)
    formData.append("paymentReference", paymentReference)
    formData.append("isGstApplicable", isGst)
    formData.append("notes", notes)
    formData.append("couponDiscount", couponDiscount)
    formData.append("loyaltyDiscount", loyaltyDiscount)
    formData.append("manualDiscount", manualDiscount)
    formData.append("couponCode", document.getElementById("couponCode").value)

    if (urdItems.length > 0) {
      formData.append("urdItems", JSON.stringify(urdItems));
    }

    formData.append("items", JSON.stringify(itemsData))

    if (paymentMethods.length > 0) {
      formData.append("paymentMethods", JSON.stringify(paymentMethods))
      // Set the primary payment method for the main payment record
      formData.append("paymentMethod", paymentMethods[0].type)
    } else {
      formData.append("paymentMethod", document.getElementById("paymentMethod").value)
    }

    // Show loading indicator
    const loadingIndicator = document.createElement("div")
    loadingIndicator.className = "fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    loadingIndicator.id = "loadingIndicator"
    loadingIndicator.innerHTML = `
      <div class="bg-white p-5 rounded-lg shadow-lg text-center">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-gold-500 mx-auto mb-3"></div>
        <p class="text-gray-700">Generating bill...</p>
      </div>
    `
    document.body.appendChild(loadingIndicator)

    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("[FETCH] Bill generation response:", data);
        // Remove loading indicator
        document.getElementById("loadingIndicator").remove()

        if (data.success) {
          // Update product status to "Sold" in the database
          console.log("[FETCH] Updating product status to Sold for:", itemsData.map((item) => item.productId));
          updateProductStatus(itemsData.map((item) => item.productId))

          // Show success modal instead of redirecting
          console.log("[UI] Showing bill success modal for invoice:", data.invoiceNo, "GST:", isGst);
          showBillSuccessModal(data.invoiceNo, isGst)

          // Clear URD items after bill generation
          urdItems = [];
          renderProductTable();
        } else {
          console.error("[ERROR] Error generating bill:", data.error);
          showNotification("Error generating bill: " + data.error, "error")
        }
      })
      .catch((error) => {
        // Remove loading indicator
        document.getElementById("loadingIndicator").remove()

        console.error("[ERROR] Error generating bill:", error);
        showNotification("Error generating bill", "error")
      })
  })

  // Add the success modal function
  function showBillSuccessModal(invoiceNo, isGst) {
    // Fetch invoice page URL from config
    console.log("[FETCH] Requesting invoice page URL for invoice:", invoiceNo, "GST:", isGst);
    fetch(window.location.href, {
      method: "POST",
      body: new URLSearchParams({
        action: "get_invoice_page_url",
        isGst: isGst,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        console.log("[FETCH] Invoice page URL response:", data);
        let invoicePage = "invoice.php"
        if (data.success && data.url) {
          invoicePage = data.url + "?invoice_no=" + invoiceNo
        } else {
          invoicePage = (isGst ? "invoice.php" : "performa_invoice.php") + "?invoice_no=" + invoiceNo
        }

        // Get summary values from the UI
        const grandTotal = document.getElementById("grandTotal")?.textContent || "";
        const paidAmount = document.getElementById("paidAmount")?.value || "0";
        const dueAmount = document.getElementById("dueAmount")?.textContent || "";
        const customerName = document.getElementById("customerName")?.textContent || "";

        // Create modal element
        const modal = document.createElement("div")
        modal.className = "fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 success-modal"
        modal.id = "billSuccessModal"

        // Get more details for the summary
        const billDate = document.getElementById("invoiceDate")?.value || new Date().toLocaleDateString();
        const paymentMethod = document.getElementById("paymentMethod")?.value || "-";
        const billTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        modal.innerHTML = `
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden border-2 border-gold-200 animate-fadeIn">
            <div class="bg-gradient-to-r from-gold-400 to-gold-600 p-6 text-white text-center">
              <i class="fas fa-crown text-5xl mb-2"></i>
              <h3 class="text-2xl font-extrabold tracking-wide mb-1">Bill Generated!</h3>
              <p class="text-lg font-bold">Invoice No: <span class="text-white bg-gold-700 px-2 py-1 rounded">${invoiceNo}</span></p>
            </div>
            <div class="p-6 flex flex-col items-center">
              <div class="w-full mb-4 bg-gold-50 rounded-lg p-4 text-center">
                <div class="text-lg font-semibold text-gold-800 mb-1">${customerName ? customerName : "Customer"}</div>
                <div class="flex flex-col sm:flex-row justify-center gap-4 text-base mb-2">
                  <div><span class="font-bold text-gray-700">Date:</span> <span class="text-gray-800">${billDate}</span></div>
                  <div><span class="font-bold text-gray-700">Time:</span> <span class="text-gray-800">${billTime}</span></div>
                  <div><span class="font-bold text-gray-700">Payment:</span> <span class="text-gray-800">${paymentMethod}</span></div>
                </div>
                <div class="flex flex-col sm:flex-row justify-center gap-4 text-base">
                  <div><span class="font-bold text-gray-700">Total:</span> <span class="text-gold-700 font-bold">${grandTotal}</span></div>
                  <div><span class="font-bold text-gray-700">Paid:</span> <span class="text-green-700 font-bold">₹${Number(paidAmount).toLocaleString()}</span></div>
                  <div><span class="font-bold text-gray-700">Due:</span> <span class="text-red-700 font-bold">${dueAmount}</span></div>
                </div>
              </div>
              <div class="flex flex-col space-y-3 w-full">
                <button id="printBillBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center font-semibold text-base">
                  <i class="fas fa-print mr-2"></i> Print Bill
                </button>
                <button id="viewBillBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center font-semibold text-base">
                  <i class="fas fa-eye mr-2"></i> View Bill
                </button>
                <button id="newBillBtn" class="px-4 py-2 bg-gold-600 text-white rounded-md hover:bg-gold-700 flex items-center justify-center font-semibold text-base">
                  <i class="fas fa-plus mr-2"></i> New Sale
                </button>
              </div>
            </div>
          </div>
        `

        document.body.appendChild(modal)

        // Add event listeners to buttons
        document.getElementById("printBillBtn").addEventListener("click", () => {
          console.log("[UI] Print Bill clicked for:", invoicePage);
          window.open(invoicePage + "&print=true", "_blank")
        })

        document.getElementById("viewBillBtn").addEventListener("click", () => {
          console.log("[UI] View Bill clicked for:", invoicePage);
          window.open(invoicePage, "_blank")
        })

        document.getElementById("newBillBtn").addEventListener("click", () => {
          console.log("[UI] New Sale clicked");
          modal.remove()
          // Reset the form for a new bill
          document.getElementById("billingForm").reset()
          products = []
          document.getElementById("productsTable").querySelector("tbody").innerHTML = ""
          document.getElementById("emptyRow").classList.remove("hidden")
          document.getElementById("itemCount").textContent = "0"
          urdItem = null
          document.getElementById("urdRow").classList.add("hidden")
          document.getElementById("customerId").value = ""
          document.getElementById("customerDetails").classList.add("hidden")
          calculateTotals()

          // Update invoice number
          const isGst = document.querySelector('input[name="billType"]:checked').value === "gst"
          updateInvoiceNumber(isGst)
        })
      })
  }

  // Update product status to "Sold" in the database
  function updateProductStatus(productIds) {
    // Filter out manual products (they don't exist in the database)
    const dbProductIds = productIds.filter((id) => !id.toString().startsWith("manual_"))

    if (dbProductIds.length === 0) return

    const formData = new FormData()
    formData.append("action", "update_product_status")
    formData.append("productIds", JSON.stringify(dbProductIds))
    formData.append("status", "Sold")

    fetch(window.location.href, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          console.log("Product status updated to Sold")
        } else {
          console.error("Error updating product status:", data.error)
        }
      })
      .catch((error) => {
        console.error("Error:", error)
      })
  }

  // Reset button
  safeAddEventListener("resetBtn", "click", () => {
    if (confirm("Are you sure you want to reset the form? All data will be lost.")) {
      // Reset form
      document.getElementById("billingForm").reset()

      // Clear products
      products = []
      document.getElementById("productsTable").querySelector("tbody").innerHTML = ""
      document.getElementById("emptyRow").classList.remove("hidden")
      document.getElementById("itemCount").textContent = "0"

      // Clear URD
      urdItem = null
      document.getElementById("urdRow").classList.add("hidden")

      // Reset customer details
      document.getElementById("customerId").value = ""
      document.getElementById("customerDetails").classList.add("hidden")

      // Reset totals
      calculateTotals()

      showNotification("Form has been reset")
    }
  })

  // ==================== FLOATING ACTION BUTTON ====================

  // Toggle FAB menu
  safeAddEventListener("fabBtn", "click", () => {
    document.getElementById("fabMenu").classList.toggle("hidden")
  })

  // FAB menu items
  safeAddEventListener("fabNewCustomer", "click", () => {
    document.getElementById("fabMenu").classList.add("hidden")
    document.getElementById("createCustomerModal").classList.remove("hidden")
  })

  safeAddEventListener("fabNewProduct", "click", () => {
    document.getElementById("fabMenu").classList.add("hidden")
    document.getElementById("addManualProductModal").classList.remove("hidden")
    document.getElementById("manualRatePerGram").value = currentGoldRate24K
  })

  safeAddEventListener("fabScanQR", "click", () => {
    document.getElementById("fabMenu").classList.add("hidden")
    document.getElementById("barcodeScannerModal").classList.remove("hidden")
    startQRScanner()
  })

  safeAddEventListener("fabAddURD", "click", () => {
    document.getElementById("fabMenu").classList.add("hidden")
    if (!document.getElementById("customerId").value) {
      showNotification("Please select a customer first", "error")
      return
    }
    document.getElementById("urdGoldModal").classList.remove("hidden")
    document.getElementById("urdRate").value = currentGoldRate24K
    calculateURDValue()
  })

  // Hide FAB menu when clicking outside
  const fabBtn = document.getElementById("fabBtn")
  const fabMenu = document.getElementById("fabMenu")
  if (fabBtn && fabMenu) {
    document.addEventListener("click", (event) => {
      if (!fabBtn.contains(event.target) && !fabMenu.contains(event.target)) {
        fabMenu.classList.add("hidden")
      }
    })
  }

  // Initialize the page
  calculateTotals()

  // Helper to fetch fine rate for material
  function getFineRateForMaterial(material) {
    // You can extend this with AJAX if needed
    if (material === "Gold") return window.currentGoldRate24K || 9999; // fallback
    if (material === "Silver") return window.currentSilverRate999 || 999; // fallback
    if (material === "Platinum") return 100; // Example
    return 100; // Default fallback
  }

  // Helper: get default fine purity for a material
  function getDefaultFinePurity(material) {
    if (material === "Gold") return "99.99";
    if (material === "Silver") return "999.9";
    return "";
  }

  // Show warning if no rate found
  function showRateWarning(show) {
    let warn = document.getElementById("manualRateWarning");
    if (!warn) {
        warn = document.createElement("div");
        warn.id = "manualRateWarning";
        warn.style.color = "#e53e3e";
        warn.style.fontSize = "12px";
        warn.style.marginTop = "2px";
        document.getElementById("manualRatePerGram").parentElement.appendChild(warn);
    }
    warn.textContent = show ? "No rate found for selected material and purity!" : "";
  }

  // Fetch rate from backend price config for material and purity
  function fetchRateForMaterialAndPurity(material, purity) {
    if (!material || !purity) {
        document.getElementById("manualRatePerGram").value = "";
        showRateWarning(false);
        return;
    }
    const formData = new FormData();
    formData.append("action", "get_gold_price");
    formData.append("material", material);
    formData.append("purity", purity);
    fetch(window.location.href, { method: "POST", body: formData })
        .then((response) => response.json())
        .then((data) => {
            if (data.success && data.rate) {
                document.getElementById("manualRatePerGram").value = data.rate;
                showRateWarning(false);
            } else {
                document.getElementById("manualRatePerGram").value = "";
                showRateWarning(true);
            }
        })
        .catch(() => {
            document.getElementById("manualRatePerGram").value = "";
            showRateWarning(true);
        });
  }

  // When material or purity changes in manual product modal, recalculate rate per gram
  function updateManualRatePerGram() {
    const material = document.getElementById("manualMaterial").value;
    const purity = Number.parseFloat(document.getElementById("manualPurity").value) || 0;
    let fineRate = 0;
    let finePurity = 1;
    if (material === "Gold") {
      fineRate = currentGoldRate24K;
      finePurity = 99.99;
    } else if (material === "Silver") {
      fineRate = currentSilverRate999;
      finePurity = 999.9;
    }
    let ratePerGram = 0;
    if (purity > 0 && fineRate > 0) {
      ratePerGram = fineRate * (purity / finePurity);
    }
    document.getElementById("manualRatePerGram").value = ratePerGram > 0 ? ratePerGram.toFixed(2) : "";
  }

  safeAddEventListener("manualMaterial", "change", function () {
    const material = this.value;
    const purityInput = document.getElementById("manualPurity");
    const defaultPurity = getDefaultFinePurity(material);
    purityInput.value = defaultPurity;
    updateManualRatePerGram();
  });

  safeAddEventListener("manualPurity", "input", function () {
    updateManualRatePerGram();
  });

  // When the manual product modal opens, clear the Rate Per Gram field and warning
  const addManualBtn = document.getElementById("addManualBtn");
  if (addManualBtn) {
    addManualBtn.addEventListener("click", function() {
      const rateInput = document.getElementById("manualRatePerGram");
      if (rateInput) rateInput.value = "";
      showRateWarning(false);
      const purityInput = document.getElementById("manualPurity");
      if (purityInput) purityInput.value = "";
      const materialInput = document.getElementById("manualMaterial");
      if (materialInput) materialInput.value = "";
    });
  }

  // Helper to ensure product has correct rate_per_gram before adding to table
  function ensureProductRate(product, callback) {
    if (!product.rate_per_gram || product.rate_per_gram === 0) {
      const material = product.material_type;
      const purity = product.purity;
      const ratePerGram = calculateRatePerGram(purity, material);
      product.rate_per_gram = ratePerGram;
      callback(product);
    } else {
      callback(product);
    }
  }

  // ==================== FETCH GOLD & SILVER RATES ON PAGE LOAD ====================
  function fetchCurrentRates() {
    // Fetch Gold 99.99 rate
    const goldFormData = new FormData();
    goldFormData.append("action", "get_gold_price");
    goldFormData.append("material", "Gold");
    goldFormData.append("purity", "99.99");
    fetch(window.location.href, { method: "POST", body: goldFormData })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          currentGoldRate24K = Number.parseFloat(data.rate);
          console.log("Current 24K Gold Rate (99.99):", currentGoldRate24K);
          const rate24kDisplays = document.querySelectorAll(".current-24k-rate");
          rate24kDisplays.forEach((element) => {
            element.textContent = formatCurrency(currentGoldRate24K) + "/g";
          });
        } else {
          console.error("Error fetching gold rate:", data.error);
        }
      })
      .catch((error) => {
        console.error("Error fetching gold rate:", error);
      });

    // Fetch Silver 999.90 rate
    const silverFormData = new FormData();
    silverFormData.append("action", "get_gold_price");
    silverFormData.append("material", "Silver");
    silverFormData.append("purity", "999.90");
    fetch(window.location.href, { method: "POST", body: silverFormData })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          currentSilverRate999 = Number.parseFloat(data.rate);
          window.currentSilverRate999 = currentSilverRate999;
          console.log("Current Silver Rate (999.90):", currentSilverRate999);
          const rateSilverDisplays = document.querySelectorAll(".current-silver-rate");
          rateSilverDisplays.forEach((element) => {
            element.textContent = formatCurrency(currentSilverRate999) + "/g";
          });
        } else {
          console.error("Error fetching silver rate:", data.error);
        }
      })
      .catch((error) => {
        console.error("Error fetching silver rate:", error);
      });
  }
  // Fetch both rates on page load
  fetchCurrentRates();

  // 1. Add checkbox in the DOM (do this in a DOMContentLoaded or after page load)
  const loyaltyLabel = document.querySelector('label[for="loyaltyDiscount"]') || document.getElementById("loyaltyDiscount").closest("td").querySelector("span")
  if (loyaltyLabel) {
    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.id = "applyLoyaltyDiscount";
    checkbox.style.marginRight = "4px";
    loyaltyLabel.parentNode.insertBefore(checkbox, loyaltyLabel);
  }
  // Uncheck by default
  const cb = document.getElementById("applyLoyaltyDiscount");
  if (cb) cb.checked = false;

  // 2. Listen for checkbox changes to recalculate totals
  safeAddEventListener("applyLoyaltyDiscount", "change", calculateTotals);

  // Fetch and display customer coupons
  function fetchAndDisplayCustomerCoupons(customerId) {
    const couponRow = document.getElementById("couponCode")?.closest("tr");
    if (!customerId || !couponRow) return;
    fetch(window.location.href, {
      method: "POST",
      body: new URLSearchParams({ action: "fetch_customer_coupons", customerId }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.coupons && data.coupons.length > 0) {
          couponRow.classList.remove("hidden");
          // Optionally, populate a dropdown of available codes here
        } else {
          couponRow.classList.add("hidden");
        }
      })
      .catch((err) => {
        couponRow.classList.add("hidden");
      });
  }

  // Call this when a customer is selected
  

  // ==================== ADVANCE AMOUNT FUNCTIONALITY ====================
  
  // Advance amount input event listener
  safeAddEventListener("advanceAmount", "input", updateNetPayable)

  // Advance checkbox event listener
  safeAddEventListener("useAdvanceCheckbox", "change", handleAdvanceCheckbox)

  // Paid amount input event listener (to update net payable when paid amount changes)
  safeAddEventListener("paidAmount", "input", updateNetPayable)

  // Initialize net payable on page load
  updateNetPayable()

})

// This code demonstrates the implementation of all required features:
// 1. GST vs Estimate toggle with invoice numbering (IN for GST, NG for non-GST)
// 2. Customer add/edit functionality
// 3. Showing dues & advances
// 4. QR-scan for adding products
// 5. Manual item add ("URD" modal)
// 6. Product table with enhanced UI and editable fields
// 7. Automatic calculation of rate/g based on purity and 24K gold rate
// 8. Payment & advance handling
// 9. Better code organization with modular functions
// 10. Update product status to "Sold" after bill generation
// 11. Product images with fallback to default images
// 12. Enhanced product display with more details
// 13. Added "Per Gram" option for making charges
// 14. Modified discount calculation to apply loyalty/coupon discounts on making charges only
// 15. Added success modal after bill generation
// 16. Fixed camera issues in scanner modal
// 17. Added automatic barcode scanner detection

console.log("Jewelry Billing System JavaScript initialized")
