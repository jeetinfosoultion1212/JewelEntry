    // Global variables
    let selectedCustomer = null
    let selectedProduct = null
    let cartItems = []
    let editingItemIndex = -1
    let isNewProductMode = false
    let html5QrCode = null
    let activeTab = "basic-info"
    let isCartVisible = false
    let currentStream = null
    let currentUrdItem = null
    let currentTransactionTotal = 0
    let paymentMethods = []
    let currentInvoiceNo = ""
    let urdImageData = null
    let availableAdvancePayments = []
    let selectedAdvancePayments = []
    
    // NEW: Global variable for firm configuration
    let firmConfiguration = {
      nonGstBillPage: "thermal_invoice.php",
      gstBillPage: "thermal_invoice.php",
      couponCodeApplyEnabled: true,
      schemesEnabled: true,
      gstRate: 0.03,
      loyaltyDiscountPercentage: 0.02,
      welcomeCouponEnabled: true,
      welcomeCouponCode: "WELCOME10",
      // NEW: Post-purchase coupon configuration
      postPurchaseCouponEnabled: false,
    }
    
    // DOM elements
    const customerNameInput = document.getElementById("customerName")
    const customerDropdown = document.getElementById("customerDropdown")
    const selectionDetails = document.getElementById("selectionDetails")
    const productSearchInput = document.getElementById("productSearch")
    const productDropdown = document.getElementById("productDropdown")
    const newProductIndicator = document.getElementById("newProductIndicator")
    const jewelryTypeSelect = document.getElementById("jewelryType")
    const materialTypeSelect = document.getElementById("materialType")
    
    // Function to update material details title based on selected material type
    function updateMaterialDetailsTitle() {
        const materialType = materialTypeSelect?.value || "Gold"; // Default to Gold if no material selected
        const materialDetailsTitle = document.getElementById('materialDetailsTitle');
        if (materialDetailsTitle) {
            materialDetailsTitle.textContent = materialType;
        }
    }
    
    // Product form elements
    const productNameInput = document.getElementById("productName")
    const huidCodeInput = document.getElementById("huidCode")
    const rate24kInput = document.getElementById("rate24k")
    const purityInput = document.getElementById("purity")
    const purityRateInput = document.getElementById("purityRate")
    const grossWeightInput = document.getElementById("grossWeight")
    const lessWeightInput = document.getElementById("lessWeight")
    const netWeightInput = document.getElementById("netWeight")
    const metalAmountInput = document.getElementById("metalAmount")
    const stoneTypeSelect = document.getElementById("stoneType")
    const stoneWeightInput = document.getElementById("stoneWeight")
    const stonePriceInput = document.getElementById("stonePrice")
    const makingTypeSelect = document.getElementById("makingType")
    const makingRateInput = document.getElementById("makingRate")
    const makingChargesInput = document.getElementById("makingCharges")
    const hmChargesInput = document.getElementById("hmCharges")
    const otherChargesInput = document.getElementById("otherCharges")
    const totalChargesInput = document.getElementById("totalCharges")
    const floatingTotalSpan = document.getElementById("floatingTotal")
    const addToCartBtn = document.getElementById("addToCart")
    
    // Cart elements
    const cartBottomSheet = document.getElementById("cartBottomSheet")
    const closeBottomSheetBtn = document.getElementById("closeBottomSheet")
    const cartItemsContainer = document.getElementById("cartItems")
    const cartItemCount = document.getElementById("cartItemCount")
    const bottomNavCartBadge = document.getElementById("bottomNavCartBadge")
    const priceBreakdownContainer = document.getElementById("priceBreakdownContainer")
    const gstApplicableCheckbox = document.getElementById("gstApplicable")
    const gstAmountInput = document.getElementById("gstAmount")
    const grandTotalSpan = document.getElementById("grandTotal")
    const clearCartBtn = document.getElementById("clearCart")
    const proceedToCheckoutBtn = document.getElementById("proceedToCheckout")
    const editModeIndicator = document.getElementById("editModeIndicator")
    const addToCartText = document.getElementById("addToCartText")
    const cartBtnIcon = document.getElementById("cartBtnIcon")
    
    // Customer modal elements
    const customerModal = document.getElementById("customerModal")
    const modalTabs = document.querySelectorAll(".modal-tab")
    const modalTabContents = document.querySelectorAll(".modal-tab-content")
    const newCustomerFirstNameInput = document.getElementById("newCustomerFirstName")
    const newCustomerLastNameInput = document.getElementById("newCustomerLastName")
    const newCustomerPhoneInput = document.getElementById("newCustomerPhone")
    const newCustomerEmailInput = document.getElementById("newCustomerEmail")
    const newCustomerAddressInput = document.getElementById("newCustomerAddress")
    const newCustomerCityInput = document.getElementById("newCustomerCity")
    const newCustomerStateInput = document.getElementById("newCustomerState")
    const newCustomerPostalCodeInput = document.getElementById("newCustomerPostalCode")
    const newCustomerGstInput = document.getElementById("newCustomerGst")
    const customerImageInput = document.getElementById("customerImage")
    
    // QR Scanner modal elements
    const qrScannerModal = document.getElementById("qrScannerModal")
    
    // URD modal elements
    const urdModal = document.getElementById("urdModal")
    
    // NEW: Function to fetch firm configuration
    async function fetchFirmConfiguration(firmId) {
      try {
        const response = await fetch(`sale-entry.php?action=getFirmConfiguration&firm_id=${firmId}`)
    
        // Check if response is ok
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }
    
        // Get response text first to debug
        const responseText = await response.text()
        console.log("Raw response:", responseText)
    
        // Try to parse JSON
        let data
        try {
          data = JSON.parse(responseText)
        } catch (parseError) {
          console.error("JSON parse error:", parseError)
          console.error("Response text:", responseText)
          throw new Error("Invalid JSON response from server")
        }
    
        if (data.success) {
          // Update global configuration with fetched data
          firmConfiguration = {
            nonGstBillPage: data.non_gst_bill_page_url || "thermal_invoice.php",
            gstBillPage: data.gst_bill_page_url || "thermal_invoice.php",
            couponCodeApplyEnabled: data.coupon_code_apply_enabled || false,
            schemesEnabled: data.schemes_enabled || false,
            gstRate: Number.parseFloat(data.gst_rate) || 0.03,
            loyaltyDiscountPercentage: Number.parseFloat(data.loyalty_discount_percentage) || 0.02,
            welcomeCouponEnabled: data.welcome_coupon_enabled || false,
            welcomeCouponCode: data.welcome_coupon_code || "WELCOME10",
            // NEW: Post-purchase coupon configuration
            postPurchaseCouponEnabled: data.post_purchase_coupon_enabled || false,
          }
    
          console.log("Firm configuration loaded:", firmConfiguration)
          return firmConfiguration
        } else {
          console.warn("Failed to fetch firm configuration:", data.message || "Unknown error")
          showToast("Failed to load firm settings. Using defaults.", "error")
          return firmConfiguration // Return defaults
        }
      } catch (error) {
        console.error("Error fetching firm configuration:", error)
        showToast("Failed to load firm settings. Using defaults.", "error")
        return firmConfiguration // Return defaults on error
      }
    }
    
    // Initialize cart state
    function initializeCart() {
      if (!window.cart) {
        window.cart = {
          loyaltyDiscount: false,
          appliedCoupon: null,
          manualDiscount: null,
          urdAmount: 0,
          gstEnabled: false,
          urdDetails: null,
          discount: 0,
          subtotal: 0,
          gstAmount: 0,
          grandTotal: 0,
        }
      }
      syncGSTState()
    }
    
    // Enhanced DOMContentLoaded event listener
    document.addEventListener("DOMContentLoaded", async () => {
      // Initialize cart state
      initializeCart()
    
      // NEW: Fetch firm configuration first
      const firmId = window.firmID || 1 // Get from session or default
      await fetchFirmConfiguration(firmId)
    
      // Fetch initial gold rate
      fetchGoldRate()
    
      // Set up event listeners
      setupEventListeners()
    
      // Check URL parameters for QR code
      checkUrlForQRCode()
    
      // Set up modal tabs
      setupModalTabs()
    
      // Set initial material details title
      updateMaterialDetailsTitle();
    
      console.log("Jewelry Billing System initialized with firm configuration")
    })
    
    // Set up modal tabs with enhanced UI
    function setupModalTabs() {
      const modalTabs = document.querySelectorAll(".modal-tab")
      const tabContents = document.querySelectorAll(".modal-tab-content")
    
      modalTabs.forEach((tab) => {
        tab.addEventListener("click", () => {
          modalTabs.forEach((t) => t.classList.remove("active"))
          tabContents.forEach((c) => c.classList.remove("active"))
    
          tab.classList.add("active")
    
          const contentId = tab.getAttribute("data-tab") + "-content"
          const contentElement = document.getElementById(contentId)
          if (contentElement) {
            contentElement.classList.add("active")
          }
        })
      })
    }
    
    // Check URL for QR code parameter
    function checkUrlForQRCode() {
      const urlParams = new URLSearchParams(window.location.search)
      const qrCode = urlParams.get("code")
    
      if (qrCode) {
        fetchProductByQRCode(qrCode)
      }
    }
    
    // Set up all event listeners
    function setupEventListeners() {
      // Customer search
      if (customerNameInput) customerNameInput.addEventListener("input", debounce(searchCustomers, 300))
      if (customerNameInput)
        customerNameInput.addEventListener("focus", () => {
          if (customerNameInput.value.length > 0) {
            searchCustomers()
          }
        })
    
      // Product search
      if (productSearchInput) productSearchInput.addEventListener("input", debounce(searchProducts, 300))
      if (productSearchInput)
        productSearchInput.addEventListener("focus", () => {
          if (productSearchInput.value.length > 0) {
            searchProducts()
          }
        })
    
      // Weight calculations
      if (grossWeightInput) grossWeightInput.addEventListener("input", calculateNetWeight)
      if (lessWeightInput) lessWeightInput.addEventListener("input", calculateNetWeight)
    
      // Rate calculations
      if (rate24kInput) rate24kInput.addEventListener("input", calculatePurityRate)
      if (purityInput) purityInput.addEventListener("input", calculatePurityRate)
    
      // Making charge calculations
      if (makingTypeSelect) makingTypeSelect.addEventListener("change", calculateMakingCharges)
      if (makingRateInput) makingRateInput.addEventListener("input", calculateMakingCharges)
    
      // Other charges calculations
      if (hmChargesInput) hmChargesInput.addEventListener("input", calculateTotalCharges)
      if (otherChargesInput) otherChargesInput.addEventListener("input", calculateTotalCharges)
    
      // Total calculations
      if (netWeightInput) netWeightInput.addEventListener("change", calculateMetalAmount)
      if (purityRateInput) purityRateInput.addEventListener("change", calculateMetalAmount)
      if (stonePriceInput) stonePriceInput.addEventListener("input", calculateTotal)
      if (makingChargesInput) makingChargesInput.addEventListener("change", calculateTotal)
      if (totalChargesInput) totalChargesInput.addEventListener("change", calculateTotal)
    
      // Cart actions
      if (addToCartBtn) addToCartBtn.addEventListener("click", addToCart)
      if (closeBottomSheetBtn) closeBottomSheetBtn.addEventListener("click", hideCart)
      if (clearCartBtn) clearCartBtn.addEventListener("click", clearCart)
      if (proceedToCheckoutBtn) proceedToCheckoutBtn.addEventListener("click", proceedToCheckout)
    
      // Toggle GST on/off
      const gstApplicableEl = document.getElementById("gstApplicable")
      if (gstApplicableEl) {
        gstApplicableEl.addEventListener("change", updateCartTotals)
        gstApplicableEl.addEventListener("change", function () {
          console.log("GST toggle changed:", this.checked)
          updateCartTotals()
        })
      }
    
      // URD input click handler
      const urdAmountEl = document.getElementById("urdAmount")
      if (urdAmountEl) urdAmountEl.addEventListener("click", showUrdModal)
    
      // Document click to close dropdowns
      document.addEventListener("click", (e) => {
        if (
          customerDropdown &&
          customerNameInput &&
          !customerNameInput.contains(e.target) &&
          !customerDropdown.contains(e.target)
        ) {
          customerDropdown.classList.remove("show")
        }
    
        if (
          productDropdown &&
          productSearchInput &&
          !productSearchInput.contains(e.target) &&
          !productDropdown.contains(e.target)
        ) {
          productDropdown.classList.remove("show")
        }
      })
    
      // Material type change
      if (materialTypeSelect) {
        materialTypeSelect.addEventListener('change', () => {
          console.log("Material type changed to: ", materialTypeSelect.value);
          updateMaterialDetailsTitle();
          fetchRateForMaterialType(materialTypeSelect.value);
          calculatePurityRate();
        });
      }
    
      console.log("Event listeners set up")
    }
    
    // Toggle new product mode
    function toggleNewProductMode() {
      isNewProductMode = !isNewProductMode

      console.log("toggleNewProductMode called. isNewProductMode: ", isNewProductMode);

      if (isNewProductMode) {
        if (newProductIndicator) newProductIndicator.classList.remove("hidden")
        if (productSearchInput) productSearchInput.placeholder = "Enter new product name..."
        if (productSearchInput) productSearchInput.value = ""
        resetForm()
        
        // Default material type for new product entry
        if (materialTypeSelect) {
            materialTypeSelect.value = "Gold"; 
            console.log("Setting materialTypeSelect to Gold in new product mode. Current value: ", materialTypeSelect.value);
            updateMaterialDetailsTitle(); // Update the displayed title immediately
        }
        fetchRateForMaterialType(materialTypeSelect?.value || "Gold");
    
        if (selectedCustomer && addToCartBtn) {
          addToCartBtn.disabled = false
        }
      } else {
        if (newProductIndicator) newProductIndicator.classList.add("hidden")
        if (productSearchInput) productSearchInput.placeholder = "Search product..."
        if (productSearchInput) productSearchInput.value = ""
        resetForm()
    
        if (addToCartBtn) addToCartBtn.disabled = true
      }
    }
    
    // Open QR Scanner
    function openQRScanner() {
      if (!qrScannerModal) return
      qrScannerModal.style.display = "flex"
    
      if (html5QrCode === null) {
        const qrReaderElement = document.getElementById("qr-reader")
        if (!qrReaderElement) {
          console.error("QR Reader element not found.")
          showToast("QR Reader element not found.", "error")
          return
        }
        html5QrCode = new Html5Qrcode("qr-reader")
      }
    
      const qrConfig = { fps: 10, qrbox: { width: 250, height: 250 } }
    
      html5QrCode.start({ facingMode: "environment" }, qrConfig, onQRCodeSuccess, onQRCodeError).catch((err) => {
        console.error("Error starting QR scanner:", err)
        showToast("Could not start camera. Please check permissions.", "error")
      })
    }
    
    // Close QR Scanner
    function closeQRScanner() {
      if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode
          .stop()
          .then(() => {
            console.log("QR Code scanning stopped.")
            if (qrScannerModal) qrScannerModal.style.display = "none"
          })
          .catch((err) => {
            console.error("Error stopping QR Code scanner:", err)
          })
      } else {
        if (qrScannerModal) qrScannerModal.style.display = "none"
      }
    }
    
    // QR Code Success Handler
    function onQRCodeSuccess(decodedText) {
      const qrResultEl = document.getElementById("qr-reader-results")
      if (qrResultEl)
        qrResultEl.innerHTML = `<div class="text-green-600 font-medium">QR Code detected: ${decodedText}</div>`
    
      if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode
          .stop()
          .then(() => {
            console.log("QR Code scanning stopped after successful scan.")
            if (qrScannerModal) qrScannerModal.style.display = "none"
            fetchProductByQRCode(decodedText)
          })
          .catch((err) => {
            console.error("Error stopping QR Code scanner:", err)
          })
      }
    }
    
    // QR Code Error Handler
    function onQRCodeError(error) {
      // This function will be called frequently, so we don't need to log every error
    }
    
    // Fetch product by QR code
    function fetchProductByQRCode(code) {
      fetch(`sale-entry.php?action=getProductByQR&code=${encodeURIComponent(code)}`)
        .then((response) => {
          console.log("QR code product search response status:", response.status)
          return response.json()
        })
        .then((data) => {
          console.log("QR code product search result:", data)
          if (data.success) {
            selectProduct(data.product)
            showToast(`Product found: ${data.product.product_name}`)
          } else {
            showToast("Product not found with this QR code", "error")
          }
        })
        .catch((error) => {
          console.error("Error fetching product by QR code:", error)
          showToast("Error fetching product", "error")
        })
    }
    
    // Debounce function to limit API calls
    function debounce(func, delay) {
      let timeout
      return function () {
        const args = arguments
        clearTimeout(timeout)
        timeout = setTimeout(() => func.apply(this, args), delay)
      }
    }
    
    // Format currency
    function formatCurrency(amount) {
      return new Intl.NumberFormat("en-IN", {
        style: "currency",
        currency: "INR",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(amount)
    }
    
    // Search customers
    function searchCustomers() {
      if (!customerNameInput || !customerDropdown) return
      const searchTerm = customerNameInput.value.trim()
      if (searchTerm.length < 2) {
        customerDropdown.classList.remove("show")
        return
      }
    
      console.log("Searching customers for:", searchTerm)
    
      fetch(`sale-entry.php?action=searchCustomers&term=${encodeURIComponent(searchTerm)}`)
        .then((response) => {
          console.log("Customer search response status:", response.status)
          return response.json()
        })
        .then((data) => {
          console.log("Customer search results:", data)
          if (data.length > 0) {
            renderCustomerDropdown(data)
            customerDropdown.classList.add("show")
          } else {
            customerDropdown.innerHTML = '<div class="p-2 text-center text-gray-500 text-xs">No customers found</div>'
            customerDropdown.classList.add("show")
          }
        })
        .catch((error) => {
          console.error("Error searching customers:", error)
          customerDropdown.innerHTML = '<div class="p-2 text-center text-red-500 text-xs">Error searching customers</div>'
          customerDropdown.classList.add("show")
        })
    }
    
    // Render customer dropdown
    function renderCustomerDropdown(customers) {
      if (!customerDropdown) return
      customerDropdown.innerHTML = ""
    
      customers.forEach((customer) => {
        const item = document.createElement("div")
        item.className = "customer-item"
    
        const hasDue = customer.due_amount > 0
        const hasCompletedOrders = customer.completedOrders && customer.completedOrders.length > 0
    
        item.innerHTML = `
                <div class="customer-name">
                    <span class="text-blue-600">#${customer.id}</span> ${customer.FirstName} ${customer.LastName}
                    ${hasDue ? `<span class="due-badge">₹${Number.parseFloat(customer.due_amount).toFixed(2)}</span>` : ""}
                </div>
                <div class="customer-info">${customer.PhoneNumber}</div>
                ${hasCompletedOrders ? `<div class="text-[10px] text-green-600 mt-0.5">${customer.completedOrders.length} completed orders with advance</div>` : ""}
            `
    
        item.addEventListener("click", () => {
          selectCustomer(customer)
          if (customerDropdown) customerDropdown.classList.remove("show")
        })
    
        customerDropdown.appendChild(item)
      })
    
      console.log("Customer dropdown rendered with", customers.length, "items")
    }
    
    // Select a customer
    function selectCustomer(customer) {
      selectedCustomer = customer
      if (customerNameInput) customerNameInput.value = `${customer.FirstName} ${customer.LastName}`
    
      if (selectionDetails) {
        let detailsHTML = `
                <div class="detail-badge">
                    <i class="fas fa-id-card"></i>
                    <span>#${customer.id}</span>
                </div>
                <div class="detail-badge">
                    <i class="fas fa-user"></i>
                    <span>${customer.FirstName} ${customer.LastName}</span>
                </div>
                <div class="detail-badge">
                    <i class="fas fa-phone"></i>
                    <span>${customer.PhoneNumber}</span>
                </div>
            `
    
        if (customer.due_amount > 0) {
          detailsHTML += `
                    <div class="due-badge">
                        <i class="fas fa-exclamation-circle"></i>
                        <span> ₹${Number.parseFloat(customer.due_amount).toFixed(2)}</span>
                    </div>
                `
        }
    
        selectionDetails.innerHTML = detailsHTML
        selectionDetails.classList.remove("hidden")
    
        // NEW: Fetch and display available coupons after customer details are rendered
        if (firmConfiguration.couponCodeApplyEnabled) {
          console.log("Coupon functionality enabled. Attempting to fetch coupons for customer:", customer.id);
          fetchAndDisplayCustomerCoupons(customer.id).then(coupons => {
            console.log("fetchAndDisplayCustomerCoupons resolved with coupons:", coupons);
            if (coupons && coupons.length > 0) {
              let couponBadgesHtml = '';
              coupons.forEach(coupon => {
                couponBadgesHtml += `
                  <span class="coupon-badge text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800" title="${coupon.description}">
                    ${coupon.code} (${coupon.usageLeft} uses)
                  </span>
                `;
              });

              const couponSection = document.createElement('div');
              couponSection.className = 'mt-2 p-2 bg-yellow-50 rounded-lg border border-yellow-200';
              couponSection.innerHTML = `
                <h4 class="text-xs font-semibold text-yellow-800 mb-1"><i class="fas fa-gift text-yellow-600 mr-1"></i> Available Coupons</h4>
                <div class="flex flex-wrap gap-1">
                  ${couponBadgesHtml}
                </div>
              `;
              selectionDetails.appendChild(couponSection);
            } else {
                console.log("No coupons returned for customer:", customer.id);
            }
          }).catch(error => {
              console.error("Error in fetchAndDisplayCustomerCoupons in selectCustomer:", error);
          });
        }
      }
    
      if (addToCartBtn && (selectedProduct || isNewProductMode)) {
        addToCartBtn.disabled = false
      }
    
      console.log("Customer selected:", customer)
    
      // Fetch and display customer advance payments
      fetchCustomerAdvancePayments(customer.id);
    }
    
    // NEW: Function to fetch and display customer coupons (refactored to only fetch and return)
    async function fetchAndDisplayCustomerCoupons(customerId) {
      // These elements are for the cart view, not the customer selection details
      // const availableCouponsContainer = document.getElementById('availableCouponsContainer');
      // const availableCouponsList = document.getElementById('availableCouponsList');

      // if (!availableCouponsContainer || !availableCouponsList) return;

      // availableCouponsList.innerHTML = ''; // Clear previous list
      // availableCouponsContainer.classList.add('hidden'); // Hide by default

      try {
        const response = await fetch(`sale-entry.php?action=getCustomerCoupons&customerId=${customerId}`);
        const data = await response.json();

        console.log("Fetch customer coupons API response:", data);

        if (data.success && data.coupons) { // Removed check for coupons.length > 0 here, it will be handled by the caller
          return data.coupons; // Return coupons for selectCustomer to use
        } else {
          console.log('No coupons available for this customer or API call unsuccessful.', data);
          return [];
        }
      } catch (error) {
        console.error('Error fetching customer coupons in fetchAndDisplayCustomerCoupons:', error);
        showToast("Error fetching customer coupons.", "error");
        return [];
      }
    }

    // Search products
    function searchProducts() {
      if (isNewProductMode || !productSearchInput || !productDropdown) {
        if (productDropdown) productDropdown.classList.remove("show")
        return
      }
    
      const searchTerm = productSearchInput.value.trim()
      if (searchTerm.length < 2) {
        productDropdown.classList.remove("show")
        return
      }
    
      productDropdown.innerHTML = '<div class="p-2 text-center text-gray-500 text-xs">Searching...</div>'
      productDropdown.classList.add("show")
    
      fetch(`sale-entry.php?action=searchProducts&term=${encodeURIComponent(searchTerm)}`)
        .then((response) => response.json())
        .then((data) => {
          if (!productDropdown) return
          if (data.length > 0) {
            renderProductDropdown(data)
          } else {
            productDropdown.innerHTML = '<div class="p-2 text-center text-gray-500 text-xs">No products found</div>'
          }
        })
        .catch((error) => {
          console.error("Error searching products:", error)
          if (productDropdown)
            productDropdown.innerHTML = '<div class="p-2 text-center text-red-500 text-xs">Error searching products</div>'
        })
    }
    
    // Render product dropdown with a more compact and enhanced UI
    function renderProductDropdown(products) {
      if (!productDropdown) return
      productDropdown.innerHTML = ""
    
      products.forEach((product) => {
        const item = document.createElement("div")
        item.className =
          "product-item flex justify-between items-center p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100"
    
        const isSold = product.status === "Sold"
        if (isSold) {
          item.classList.add("bg-red-50")
        }
    
        item.innerHTML = `
                <div class="flex-1">
                    <div class="font-semibold text-xs ${isSold ? "text-red-700" : ""}">${product.product_name}</div>
                    <div class="text-[8px] text-gray-600 flex gap-2">
                        <span>${product.jewelry_type}</span>
                        <span>${product.gross_weight}g</span>
                    </div>
                </div>
                <div class="text-[8px] font-medium ${isSold ? "text-red-600" : "text-green-600"}">
                    ${isSold ? "Sold" : "Available"}
                </div>
            `
    
        item.addEventListener("click", () => {
          if (isSold) {
            alert("This product is already sold and not available for purchase.")
          } else {
            selectProduct(product)
          }
          if (productDropdown) productDropdown.classList.remove("show")
        })
    
        productDropdown.appendChild(item)
      })
    }
    
    // Select a product
    function selectProduct(product) {
      if (product.status === "Sold") {
        return
      }
    
      console.log("selectProduct called with product:", product);
    
      selectedProduct = product
      if (productSearchInput) productSearchInput.value = product.product_name
    
      if (productNameInput) productNameInput.value = product.product_name
      if (huidCodeInput) huidCodeInput.value = product.huid_code || ""
      if (purityInput) purityInput.value = product.purity || 0
      if (grossWeightInput) grossWeightInput.value = product.gross_weight || 0
      if (lessWeightInput) lessWeightInput.value = product.less_weight || 0
      if (netWeightInput) netWeightInput.value = product.net_weight || 0
      if (stoneTypeSelect) stoneTypeSelect.value = product.stone_type || "None"
      if (stoneWeightInput) stoneWeightInput.value = product.stone_weight || 0
      if (stonePriceInput) stonePriceInput.value = product.stone_price || 0
    
      if (makingTypeSelect) {
        if (!product.making_charge_type || product.making_charge_type === "" || product.making_charge_type === "0") {
          makingTypeSelect.value = "percentage"
        } else {
          makingTypeSelect.value = product.making_charge_type
        }
      }
      if (makingRateInput) makingRateInput.value = product.making_charge || 0
    
      if (materialTypeSelect) {
        materialTypeSelect.value = product.material_type || ""; // Set the select dropdown value
        console.log("Material type select value after setting:", materialTypeSelect.value);
        updateMaterialDetailsTitle();
      }
    
      // Fetch appropriate rate based on material type
      fetchRateForMaterialType(product.material_type);
    
      if (selectedCustomer && addToCartBtn) {
        addToCartBtn.disabled = false
      }
    }
    
    // Fetch rate for a given material type
    function fetchRateForMaterialType(materialType) {
      const url = `sale-entry.php?action=getGoldRate&material_type=${encodeURIComponent(materialType)}`;
      fetch(url)
        .then((response) => {
          console.log(`${materialType} rate response status:`, response.status);
          return response.json();
        })
        .then((data) => {
          console.log(`${materialType} rate data:`, data);
          if (rate24kInput) rate24kInput.value = data.rate;
          // Only set purity for Silver if it's not already set by product data
          if (materialType === "Silver" && (purityInput.value === "0" || purityInput.value === "")) {
            purityInput.value = 999.90;
          }
          calculatePurityRate();
        })
        .catch((error) => {
          console.error(`Error fetching ${materialType} rate:`, error);
          showToast(`Failed to fetch ${materialType} rate. Using default value.`, "error");
        });
    }
    
    // Fetch gold rate (now uses the generic function)
    function fetchGoldRate() {
      fetchRateForMaterialType("Gold");
    }
    
    // Calculate purity rate
    function calculatePurityRate() {
      const rate24k = Number.parseFloat(rate24kInput?.value) || 0
      const purity = Number.parseFloat(purityInput?.value) || 0
      const materialType = materialTypeSelect?.value; // Get the selected material type

      console.log("calculatePurityRate called. materialType:", materialType, "purity:", purity, "rate24k:", rate24k);

      if (purity > 0) {
        let purityFactor;
        if (materialType === "Silver") {
          purityFactor = purity / 1000; // For Silver, purity is typically out of 1000 (e.g., 999.9)
        } else {
          purityFactor = purity / 100; // For Gold and others, purity is typically out of 100 (percentage)
        }
        const purityRate = (rate24k * purityFactor)
        console.log("Purity Factor:", purityFactor, "Calculated purityRate:", purityRate);
        if (purityRateInput) purityRateInput.value = purityRate.toFixed(2)
      } else {
        if (purityRateInput) purityRateInput.value = 0
      }

      calculateMetalAmount()
    }
    
    // Calculate net weight
    function calculateNetWeight() {
      const grossWeight = Number.parseFloat(grossWeightInput?.value) || 0
      const lessWeight = Number.parseFloat(lessWeightInput?.value) || 0
    
      const netWeight = Math.max(0, grossWeight - lessWeight)
      if (netWeightInput) netWeightInput.value = netWeight.toFixed(2)
    
      calculateMetalAmount()
    }
    
    // Calculate metal amount
    function calculateMetalAmount() {
      const netWeight = Number.parseFloat(netWeightInput?.value) || 0
      const purityRate = Number.parseFloat(purityRateInput?.value) || 0

      console.log("calculateMetalAmount called. netWeight:", netWeight, "purityRate:", purityRate);

      const metalAmount = netWeight * purityRate
      console.log("Calculated metalAmount:", metalAmount);
      if (metalAmountInput) metalAmountInput.value = metalAmount.toFixed(2)

      // Chain subsequent calculations here to ensure they use the updated metalAmount
      calculateMakingCharges();
      calculateTotalCharges();
      calculateTotal();
    }
    
    // Calculate making charges
    function calculateMakingCharges() {
      const makingType = makingTypeSelect?.value
      const makingRate = Number.parseFloat(makingRateInput?.value) || 0
      const netWeight = Number.parseFloat(netWeightInput?.value) || 0
      const metalAmount = Number.parseFloat(metalAmountInput?.value) || 0; // Read from input

      console.log("calculateMakingCharges called. makingType:", makingType, "makingRate:", makingRate, "netWeight:", netWeight, "metalAmount from input:", metalAmount);

      let makingCharges = 0

      if (makingType === "per_gram") {
        makingCharges = netWeight * makingRate
      } else if (makingType === "percentage") {
        makingCharges = (metalAmount * makingRate) / 100
      } else if (makingType === "fixed") {
        makingCharges = makingRate
      }

      console.log("Calculated makingCharges:", makingCharges);

      if (makingChargesInput) makingChargesInput.value = makingCharges.toFixed(2)
      calculateTotalCharges()
    }
    
    // Calculate total charges
    function calculateTotalCharges() {
      const makingCharges = Number.parseFloat(makingChargesInput?.value) || 0
      const hmCharges = Number.parseFloat(hmChargesInput?.value) || 0
      const otherCharges = Number.parseFloat(otherChargesInput?.value) || 0
    
      const totalChargesVal = makingCharges + hmCharges + otherCharges
      if (totalChargesInput) totalChargesInput.value = totalChargesVal.toFixed(2)
    
      calculateTotal()
    }
    
    // Calculate total
    function calculateTotal() {
      const metalAmount = Number.parseFloat(metalAmountInput?.value) || 0
      const stonePrice = Number.parseFloat(stonePriceInput?.value) || 0
      const totalCharges = Number.parseFloat(totalChargesInput?.value) || 0
    
      const total = metalAmount + stonePrice + totalCharges
      if (floatingTotalSpan) floatingTotalSpan.textContent = formatCurrency(total)
    }
    
    // Show customer modal
    function showCustomerModal() {
      if (!customerModal) return
      customerModal.style.display = "flex"
      setupModalTabs()
    
      // Reset form
      if (newCustomerFirstNameInput) newCustomerFirstNameInput.value = ""
      if (newCustomerLastNameInput) newCustomerLastNameInput.value = ""
      if (newCustomerPhoneInput) newCustomerPhoneInput.value = ""
      if (newCustomerEmailInput) newCustomerEmailInput.value = ""
      if (newCustomerAddressInput) newCustomerAddressInput.value = ""
      if (newCustomerCityInput) newCustomerCityInput.value = ""
      if (newCustomerStateInput) newCustomerStateInput.value = ""
      if (newCustomerPostalCodeInput) newCustomerPostalCodeInput.value = ""
      if (newCustomerGstInput) newCustomerGstInput.value = ""
    
      // Reset tabs
      const allModalTabs = customerModal.querySelectorAll(".modal-tab")
      const allModalTabContents = customerModal.querySelectorAll(".modal-tab-content")
    
      allModalTabs.forEach((t) => t.classList.remove("active"))
      allModalTabContents.forEach((c) => c.classList.remove("active"))
    
      const firstTab = customerModal.querySelector('.modal-tab[data-tab="basic-info"]')
      const firstTabContent = customerModal.querySelector("#basic-info-content")
    
      if (firstTab) firstTab.classList.add("active")
      if (firstTabContent) firstTabContent.classList.add("active")
      activeTab = "basic-info"
    }
    
    // Close customer modal
    function closeCustomerModal() {
      if (customerModal) customerModal.style.display = "none"
    }
    
    // Enhanced save customer with welcome coupon messaging
    function saveCustomer() {
      const firstName = newCustomerFirstNameInput?.value.trim()
      const lastName = newCustomerLastNameInput?.value.trim()
      const phone = newCustomerPhoneInput?.value.trim()
    
      if (!firstName || !phone) {
        showToast("First name and phone number are required", "error")
        return
      }
    
      const customerData = new FormData()
      customerData.append("firstName", firstName)
      customerData.append("lastName", lastName)
      customerData.append("phone", phone)
      customerData.append("email", newCustomerEmailInput?.value.trim())
      customerData.append("address", newCustomerAddressInput?.value.trim())
      customerData.append("city", newCustomerCityInput?.value.trim())
      customerData.append("state", newCustomerStateInput?.value.trim())
      customerData.append("postalCode", newCustomerPostalCodeInput?.value.trim())
      customerData.append("gst", newCustomerGstInput?.value.trim())
    
      console.log("Saving customer data:", Object.fromEntries(customerData))
    
      fetch("sale-entry.php?action=addCustomer", {
        method: "POST",
        body: customerData,
      })
        .then((response) => {
          console.log("Add customer response status:", response.status)
          return response.json()
        })
        .then((data) => {
          console.log("Add customer response:", data)
          if (data.success) {
            // NEW: Check for welcome coupon message from backend
            let successMessage = "Customer added successfully"
            if (data.welcomeCouponMessage) {
              successMessage = data.welcomeCouponMessage
            }
    
            showToast(successMessage)
            closeCustomerModal()
            selectCustomer(data.customer)
          } else {
            showToast(data.message, "error")
          }
        })
        .catch((error) => {
          console.error("Error adding customer:", error)
          showToast("Failed to add customer", "error")
        })
    }
    
    // Save new jewelry item
    function saveNewJewelryItem() {
      return new Promise((resolve, reject) => {
        if (!productNameInput?.value.trim()) {
          showToast("Product name is required", "error")
          reject("Product name is required")
          return
        }
        if (!jewelryTypeSelect?.value) {
          showToast("Jewelry type is required", "error")
          reject("Jewelry type is required")
          return
        }
        if (!materialTypeSelect?.value) {
          showToast("Material type is required", "error")
          reject("Material type is required")
          return
        }
        if (Number.parseFloat(purityInput?.value) <= 0) {
          showToast("Purity must be greater than 0", "error")
          reject("Purity must be greater than 0")
          return
        }
        if (Number.parseFloat(grossWeightInput?.value) <= 0) {
          showToast("Gross weight must be greater than 0", "error")
          reject("Gross weight must be greater than 0")
          return
        }
    
        const jewelryData = new FormData()
        jewelryData.append("productName", productNameInput.value.trim())
        jewelryData.append("jewelryType", jewelryTypeSelect.value)
        jewelryData.append("materialType", materialTypeSelect.value)
        jewelryData.append("purity", purityInput.value)
        jewelryData.append("huidCode", huidCodeInput?.value.trim())
        jewelryData.append("grossWeight", grossWeightInput.value)
        jewelryData.append("lessWeight", lessWeightInput?.value)
        jewelryData.append("netWeight", netWeightInput?.value)
        jewelryData.append("stoneType", stoneTypeSelect?.value)
        jewelryData.append("stoneWeight", stoneWeightInput?.value)
        jewelryData.append("stonePrice", stonePriceInput?.value)
        jewelryData.append("makingChargeType", makingTypeSelect?.value)
        jewelryData.append("makingCharge", makingRateInput?.value)
    
        fetch("sale-entry.php?action=addJewelryItem", {
          method: "POST",
          body: jewelryData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast("Jewelry item added successfully")
              selectedProduct = data.jewelry
              resolve(data.jewelry)
            } else {
              showToast(data.message, "error")
              reject(data.message)
            }
          })
          .catch((error) => {
            console.error("Error adding jewelry item:", error)
            showToast("Failed to add jewelry item", "error")
            reject(error)
          })
      })
    }
    
    // Show toast notification
    function showToast(message, type = "success") {
      const toast = document.getElementById("toast")
      const toastMessage = document.getElementById("toastMessage")
    
      if (!toast || !toastMessage) return
    
      toastMessage.textContent = message
      toast.className = "toast show"
    
      if (type === "error") {
        toast.style.backgroundColor = "#ef4444"
      } else if (type === "info") {
        toast.style.backgroundColor = "#3b82f6"
      } else {
        toast.style.backgroundColor = "#10b981"
      }
    
      setTimeout(() => {
        toast.classList.remove("show")
      }, 3000)
    }
    
    async function addToCart() {
      console.log("addToCart called")
      if (!selectedCustomer) {
        showToast("Please select a customer", "error")
        return
      }
    
      try {
        if (isNewProductMode && !selectedProduct) {
          const confirmNew = window.confirm("This will create a new jewelry item record. Continue?")
          if (confirmNew) {
            await saveNewJewelryItem()
          } else {
            return
          }
        }
    
        const item = {
          productId: selectedProduct ? selectedProduct.id : null,
          productName: productNameInput?.value || "",
          huidCode: huidCodeInput?.value || "",
          rate24k: Number.parseFloat(rate24kInput?.value) || 0,
          purity: Number.parseFloat(purityInput?.value) || 0,
          purityRate: Number.parseFloat(purityRateInput?.value) || 0,
          grossWeight: Number.parseFloat(grossWeightInput?.value) || 0,
          lessWeight: Number.parseFloat(lessWeightInput?.value) || 0,
          netWeight: Number.parseFloat(netWeightInput?.value) || 0,
          metalAmount: Number.parseFloat(metalAmountInput?.value) || 0,
          stoneType: stoneTypeSelect?.value || "None",
          stoneWeight: Number.parseFloat(stoneWeightInput?.value) || 0,
          stonePrice: Number.parseFloat(stonePriceInput?.value) || 0,
          makingType: makingTypeSelect?.value || "per_gram",
          makingRate: Number.parseFloat(makingRateInput?.value) || 0,
          makingCharges: Number.parseFloat(makingChargesInput?.value) || 0,
          hmCharges: Number.parseFloat(hmChargesInput?.value) || 0,
          otherCharges: Number.parseFloat(otherChargesInput?.value) || 0,
          totalCharges: Number.parseFloat(totalChargesInput?.value) || 0,
          total:
            (Number.parseFloat(metalAmountInput?.value) || 0) +
            (Number.parseFloat(stonePriceInput?.value) || 0) +
            (Number.parseFloat(totalChargesInput?.value) || 0),
          uniqueId: Date.now() + Math.random().toString(36).substring(2, 9),
        }
    
        console.log("Item to add to cart:", item)
    
        if (editingItemIndex >= 0) {
          cartItems[editingItemIndex] = item
          showToast("Item updated in cart")
          editingItemIndex = -1
    
          if (editModeIndicator) editModeIndicator.classList.remove("show")
          if (addToCartText) addToCartText.textContent = "Add to Cart"
          if (cartBtnIcon) cartBtnIcon.className = "fas fa-cart-plus"
        } else {
          cartItems.push(item)
          showToast("Item added to cart")
        }
    
        updateCartUI()
        resetForm()
    
        if (isNewProductMode) {
          toggleNewProductMode()
        }
    
        console.log("Calling showCart() with", cartItems.length, "items")
        showCart()
      } catch (error) {
        console.error("Error in addToCart:", error)
        showToast("Failed to add item to cart", "error")
      }
    }
    
    // Reset form
    function resetForm() {
      if (productSearchInput) productSearchInput.value = ""
      selectedProduct = null
    
      if (productNameInput) productNameInput.value = ""
      if (huidCodeInput) huidCodeInput.value = ""
      if (purityInput) purityInput.value = "0"
      if (grossWeightInput) grossWeightInput.value = "0"
      if (lessWeightInput) lessWeightInput.value = "0"
      if (netWeightInput) netWeightInput.value = "0"
      if (metalAmountInput) metalAmountInput.value = "0"
      if (stoneTypeSelect) stoneTypeSelect.value = "None"
      if (stoneWeightInput) stoneWeightInput.value = "0"
      if (stonePriceInput) stonePriceInput.value = "0"
      if (makingTypeSelect) makingTypeSelect.value = "per_gram"
      if (makingRateInput) makingRateInput.value = "0"
      if (makingChargesInput) makingChargesInput.value = "0"
      if (hmChargesInput) hmChargesInput.value = "35"
      if (otherChargesInput) otherChargesInput.value = "0"
      if (totalChargesInput) totalChargesInput.value = "0"
    
      if (jewelryTypeSelect) jewelryTypeSelect.value = ""
      if (materialTypeSelect) materialTypeSelect.value = ""
    
      if (floatingTotalSpan) floatingTotalSpan.textContent = formatCurrency(0)
    
      if (addToCartBtn && (!isNewProductMode || !selectedCustomer)) {
        addToCartBtn.disabled = true
      }
    }
    
    // Enhanced showCart function with product table, discounts, URD and improved UI
    function showCart() {
      console.log("showCart called, cart items:", cartItems.length)
    
      if (cartItems.length === 0) {
        showToast("Your cart is empty", "error")
        return
      }
    
      const entryForm = document.getElementById("entry-form")
      if (entryForm) entryForm.style.display = "none"
    
      let cartViewContainer = document.getElementById("cart-view-container")
      if (!cartViewContainer) {
        cartViewContainer = document.createElement("div")
        cartViewContainer.id = "cart-view-container"
        cartViewContainer.className = "tab-content active p-2 bg-blue-50/30 rounded-lg border border-blue-100 mb-2"
        cartViewContainer.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-blue-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-blue-900">Cart View</h3>
                            <p class="text-xs text-blue-500" id="cartViewItemCount">${cartItems.length} items</p>
                        </div>
                    </div>
                    <button id="backToEntryForm" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-xs font-medium flex items-center gap-1">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Entry</span>
                    </button>
                </div>
                
                <div class="overflow-x-auto bg-white rounded-lg shadow-sm mb-2 border border-blue-100">
                    <table class="w-full text-xs">
                        <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                            <tr class="text-blue-800">
                                <th class="px-2 py-1.5 text-left font-medium">Item</th>
                                <th class="px-2 py-1.5 text-right font-medium w-16">Weight</th>
                                <th class="px-2 py-1.5 text-right font-medium w-20">Amount</th>
                                <th class="px-2 py-1.5 text-center font-medium w-16">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cartViewItems" class="divide-y divide-blue-50"></tbody>
                    </table>
                </div>
                
                <div class="bg-white p-2 rounded-lg shadow-sm border border-blue-100 mb-2">
                    <div class="flex justify-between items-center mb-1">
                        <h4 class="text-xs font-semibold text-blue-900">Additional Adjustments</h4>
                        <button id="toggleAdjustments" class="text-xs text-blue-500 flex items-center gap-1">
                            <span>Show</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div id="adjustmentsContent" class="hidden">
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div class="relative">
                                <input type="text" id="couponCode" placeholder="Coupon Code" 
                                    class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg" 
                                    ${!firmConfiguration.couponCodeApplyEnabled ? "disabled" : ""}>
                                <button id="applyCoupon" class="absolute right-1 top-1/2 transform -translate-y-1/2 px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs"
                                    ${!firmConfiguration.couponCodeApplyEnabled ? "disabled" : ""}>
                                    Apply
                                </button>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-2 pointer-events-none">
                                    <i class="fas fa-percentage text-xs text-gray-500"></i>
                                </div>
                                <input type="text" id="discountAmountInput" placeholder="Discount" 
                                    class="w-full pl-6 pr-16 py-1.5 text-xs border border-gray-300 rounded-lg">
                                <div class="absolute inset-y-0 right-8 flex items-center">
                                    <select id="discountType" class="h-full text-xs bg-transparent text-gray-500 border-0 border-l border-gray-300 py-0 pl-2 pr-7">
                                        <option value="fixed">₹</option>
                                        <option value="percentage">%</option>
                                    </select>
                                </div>
                                <button id="addDiscount" class="absolute right-0 top-0 h-full px-2 text-green-600 flex items-center">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                            <div class="flex items-center gap-1">
                                <input type="checkbox" id="loyaltyDiscountCheck" class="h-3 w-3 text-blue-600">
                                <label for="loyaltyDiscountCheck" class="text-xs text-gray-700">Loyalty (${(firmConfiguration.loyaltyDiscountPercentage * 100).toFixed(0)}%)</label>
                                <span id="loyaltyDiscountAmountDisplay" class="text-xs font-medium text-green-600 ml-auto">₹0.00</span>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-2 pointer-events-none">
                                    <i class="fas fa-coins text-xs text-gray-500"></i>
                                </div>
                                <input type="number" id="urdAmountInputDisplay" placeholder="URD Amount" 
                                    class="w-full pl-6 pr-8 py-1.5 text-xs border border-gray-300 rounded-lg">
                                <button id="addURD" class="absolute right-1 top-1/2 transform -translate-y-1/2 text-purple-600">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <div id="appliedDiscountsList" class="text-xs text-gray-600 border-t border-gray-100 pt-1 hidden"></div>
                    </div>
                </div>
                <div id="cartViewSummary" class="bg-white p-2 rounded-lg shadow-sm border border-blue-100"></div>
            `
    
        if (entryForm) entryForm.insertAdjacentElement("afterend", cartViewContainer)
    
        document.getElementById("backToEntryForm")?.addEventListener("click", () => {
          hideCart()
          if (entryForm) entryForm.style.display = "block"
          const cvc = document.getElementById("cart-view-container")
          if (cvc) cvc.style.display = "none"
        })
    
        setupDiscountListeners()
    
        document.getElementById("toggleAdjustments")?.addEventListener("click", function () {
          const content = document.getElementById("adjustmentsContent")
          if (!content) return
          const isHidden = content.classList.contains("hidden")
          content.classList.toggle("hidden")
          this.querySelector("span").textContent = isHidden ? "Hide" : "Show"
          this.querySelector("i").classList.toggle("fa-chevron-down")
          this.querySelector("i").classList.toggle("fa-chevron-up")
        })
      } else {
        const itemCountEl = document.getElementById("cartViewItemCount")
        if (itemCountEl) itemCountEl.textContent = cartItems.length + " items"
        cartViewContainer.style.display = "block"
      }
    
      updateCartViewItems()
      updateCartViewSummary()
    
      if (cartBottomSheet) cartBottomSheet.style.transform = "translateY(0)"
      isCartVisible = true
    
      // Fetch and display customer coupons if a customer is selected
      if (selectedCustomer) {
        fetchAndDisplayCustomerCoupons(selectedCustomer.id).then(coupons => {
            // Automatically apply the first available coupon if none is applied and coupon functionality is enabled
            if (firmConfiguration.couponCodeApplyEnabled && !cart.appliedCoupon && coupons && coupons.length > 0) {
                const couponCodeInput = document.getElementById("couponCode");
                const applyCouponBtn = document.getElementById("applyCoupon");

                if (couponCodeInput && applyCouponBtn) {
                    couponCodeInput.value = coupons[0].code; // Set the first coupon code
                    applyCouponCode(coupons[0].code, selectedCustomer.id, cart.gstEnabled); // Programmatically apply
                }
            }
        });
      } else {
        // Hide coupons section if no customer is selected
        const availableCouponsContainer = document.getElementById('availableCouponsContainer');
        if (availableCouponsContainer) {
          availableCouponsContainer.classList.add('hidden');
        }
      }
      // NEW: If a coupon is already applied, display it in the input and disable it.
      if (cart.appliedCoupon) {
        const couponCodeInput = document.getElementById("couponCode");
        const applyCouponBtn = document.getElementById("applyCoupon");
        if (couponCodeInput) {
          couponCodeInput.value = cart.appliedCoupon.code;
          couponCodeInput.disabled = true;
        }
        if (applyCouponBtn) {
          applyCouponBtn.disabled = true;
          applyCouponBtn.textContent = "Applied";
        }
        // Ensure adjustments content is visible if a coupon is applied
        const adjustmentsContent = document.getElementById("adjustmentsContent");
        const toggleAdjustmentsBtn = document.getElementById("toggleAdjustments");
        if (adjustmentsContent && adjustmentsContent.classList.contains("hidden")) {
          adjustmentsContent.classList.remove("hidden");
          if (toggleAdjustmentsBtn) {
            toggleAdjustmentsBtn.querySelector("span").textContent = "Hide";
            toggleAdjustmentsBtn.querySelector("i").classList.remove("fa-chevron-down");
            toggleAdjustmentsBtn.querySelector("i").classList.add("fa-chevron-up");
          }
        }
      }
    }
    
    // Add event listener for applying coupons using event delegation
    document.getElementById('availableCouponsList')?.addEventListener('click', function(event) {
      const target = event.target;
      if (target.classList.contains('apply-coupon-btn')) {
        const couponCode = target.dataset.couponCode;
        if (couponCode && selectedCustomer) {
          applyCouponCode(couponCode, selectedCustomer.id, cart.gstEnabled);
          // Optionally disable the apply button after clicking
          // target.disabled = true;
        }
      }
    });
    
    // NEW: Enhanced function to set up listeners for discount and URD functionality with coupon validation
    function setupDiscountListeners() {
      document.getElementById("applyCoupon")?.addEventListener("click", () => {
        if (!firmConfiguration.couponCodeApplyEnabled) {
          showToast("Coupon functionality is disabled for this firm", "error")
          return
        }
    
        const couponCodeInput = document.getElementById("couponCode")
        if (!couponCodeInput) return
        const couponCode = couponCodeInput.value.trim().toUpperCase()
    
        if (!couponCode) {
          showToast("Please enter a coupon code", "error")
          return
        }
    
        if (!selectedCustomer) {
          showToast("Please select a customer first", "error")
          return
        }
    
        // NEW: Call backend to validate coupon
        applyCouponCode(couponCode, selectedCustomer.id, cart.gstEnabled)
      })
    
      document.getElementById("addDiscount")?.addEventListener("click", () => {
        const discountAmountInput = document.getElementById("discountAmountInput")
        const discountTypeSelect = document.getElementById("discountType")
        if (!discountAmountInput || !discountTypeSelect) return
    
        const discountAmount = Number.parseFloat(discountAmountInput.value)
        const discountType = discountTypeSelect.value
    
        if (isNaN(discountAmount) || discountAmount <= 0) {
          showToast("Please enter a valid discount amount", "error")
          return
        }
    
        cart.manualDiscount = { type: discountType, value: discountAmount }
        const description = discountType === "percentage" ? `${discountAmount}% off` : `₹${discountAmount.toFixed(2)} off`
        showAppliedDiscount("manual", "Manual Discount", description)
        updateCartViewSummary()
        showToast(`Manual discount applied: ${description}`, "success")
        discountAmountInput.value = ""
      })
    
      const urdAmountInputDisplay = document.getElementById("urdAmountInputDisplay")
      if (urdAmountInputDisplay) {
        urdAmountInputDisplay.addEventListener("focus", function (e) {
          e.preventDefault()
          this.blur()
          showUrdModal()
        })
        urdAmountInputDisplay.addEventListener(
          "touchstart",
          (e) => {
            e.preventDefault()
            showUrdModal()
          },
          { passive: false },
        )
      }
    
      document.getElementById("addURD")?.addEventListener("click", showUrdModal)
    
      document.getElementById("loyaltyDiscountCheck")?.addEventListener("change", function () {
        cart.loyaltyDiscount = this.checked
        updateCartViewSummary()
    
        if (this.checked) {
          showToast("Loyalty discount applied", "success")
        } else {
          removeDiscount("loyalty")
          showToast("Loyalty discount removed", "info")
        }
      })
    }
    // NEW: Function to apply coupon code with backend validation
    function applyCouponCode(couponCode, customerId, isGst) {
      const applyCouponBtn = document.getElementById("applyCoupon")
      const couponCodeInput = document.getElementById("couponCode")

      if (applyCouponBtn) {
        applyCouponBtn.disabled = true
        applyCouponBtn.textContent = "Checking..."
      }

      // Fix: Update the parameter names to match PHP endpoint
      fetch(
        `sale-entry.php?action=validateCustomerCoupon&coupon_code=${encodeURIComponent(couponCode)}&customerId=${customerId}&isGst=${isGst ? 1 : 0}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            const coupon = data.coupon
            cart.appliedCoupon = {
              code: coupon.code,
              type: coupon.type,
              value: Number.parseFloat(coupon.value),
              description: `${coupon.description ? coupon.description + " (" : ""}${
                coupon.type === "percentage" ? coupon.value + "%" : "₹" + coupon.value
              } off${coupon.description ? ")" : ""}`,
            }
            showAppliedDiscount("coupon", coupon.code, cart.appliedCoupon.description)
            updateCartViewSummary()
            showToast(`Coupon "${coupon.code}" applied successfully!`, "success")
            // document.getElementById("couponCode").value = "" // OLD: Clear input
            if (couponCodeInput) {
              couponCodeInput.value = coupon.code // NEW: Set input value to applied coupon code
              couponCodeInput.disabled = true // NEW: Disable input
            }
            if (applyCouponBtn) applyCouponBtn.disabled = true // NEW: Disable apply button
          } else {
            showToast(data.message || "Invalid or expired coupon", "error")
          }
        })
        .catch((error) => {
          console.error("Error validating coupon:", error)
          showToast("Error validating coupon. Please try again.", "error")
        })
        .finally(() => {
          if (applyCouponBtn) {
            if (cart.appliedCoupon) { // If a coupon is applied, set text to 'Applied'
              applyCouponBtn.textContent = "Applied";
            } else { // Otherwise, re-enable and set to 'Apply'
              applyCouponBtn.disabled = false;
              applyCouponBtn.textContent = "Apply";
            }
          }
        })
    }
    
    // Function to display applied discounts in the list
    function showAppliedDiscount(type, title, description) {
      const discountsList = document.getElementById("appliedDiscountsList")
      if (!discountsList) return
      discountsList.classList.remove("hidden")
    
      const existingItem = discountsList.querySelector(`[data-discount-id="${type}"]`)
      if (existingItem) existingItem.remove()
    
      const discountItem = document.createElement("div")
      discountItem.className = "flex justify-between items-center py-1"
      discountItem.setAttribute("data-discount-id", type)
    
      let iconClass = "fa-tag",
        colorClass = "text-green-600"
      if (type === "urd") {
        iconClass = "fa-coins"
        colorClass = "text-purple-600"
      } else if (type === "loyalty") {
        iconClass = "fa-award"
        colorClass = "text-amber-600"
      }
    
      discountItem.innerHTML = `
            <div class="flex items-center gap-1">
                <i class="fas ${iconClass} ${colorClass}"></i>
                <span class="font-medium">${title}</span>
                <span class="text-gray-500"> - ${description}</span>
            </div>
            <button class="text-red-500 hover:text-red-700" onclick="removeDiscount('${type}')">
                <i class="fas fa-times-circle"></i>
            </button>
        `
      discountsList.appendChild(discountItem)
    }
    
    // Function to remove an applied discount
    function removeDiscount(type) {
      if (type === "coupon") {
        cart.appliedCoupon = null
        const couponCodeInput = document.getElementById("couponCode")
        const applyCouponBtn = document.getElementById("applyCoupon")
        if (couponCodeInput) {
          couponCodeInput.value = ""
          couponCodeInput.disabled = false
        }
        if (applyCouponBtn) {
          applyCouponBtn.disabled = false
          applyCouponBtn.textContent = "Apply"
        }
      } else if (type === "manual") cart.manualDiscount = null
      else if (type === "urd") {
        cart.urdAmount = 0
        cart.urdDetails = null
        const urdDisplay = document.getElementById("urdAmountInputDisplay")
        if (urdDisplay) urdDisplay.value = ""
      } else if (type === "loyalty") {
        cart.loyaltyDiscount = false
        const loyaltyCheck = document.getElementById("loyaltyDiscountCheck")
        if (loyaltyCheck) loyaltyCheck.checked = false
      }
    
      const discountsList = document.getElementById("appliedDiscountsList")
      const discountItem = discountsList?.querySelector(`[data-discount-id="${type}"]`)
      if (discountItem) discountItem.remove()
      if (discountsList && discountsList.children.length === 0) {
        discountsList.classList.add("hidden")
      }
    
      updateCartViewSummary()
      showToast(`${type.charAt(0).toUpperCase() + type.slice(1)} discount removed`, "info")
    }
    
    // Function to update cart view items
    function updateCartViewItems() {
      const itemsContainer = document.getElementById("cartViewItems")
      if (!itemsContainer) return
      itemsContainer.innerHTML = ""
      cartItems.forEach((item, index) => {
        const row = document.createElement("tr")
        row.className = index % 2 === 0 ? "bg-white" : "bg-blue-50/30"
        row.innerHTML = `
                <td class="px-2 py-1.5">
                    <div class="font-medium text-blue-900">${item.productName}</div>
                    <div class="text-[10px] text-gray-500">
                        ${item.purity}% | MC: ${formatCurrency(item.makingCharges)}
                    </div>
                </td>
                <td class="px-2 py-1.5 text-right">${item.netWeight.toFixed(3)}g</td>
                <td class="px-2 py-1.5 text-right font-medium">${formatCurrency(item.total)}</td>
                <td class="px-2 py-1.5 text-center">
                    <div class="flex justify-center gap-1">
                        <button onclick="editCartItem(${index})" class="p-1 text-blue-600 hover:bg-blue-50 rounded">
                            <i class="fas fa-edit text-[10px]"></i>
                        </button>
                        <button onclick="removeCartItem(${index})" class="p-1 text-red-600 hover:bg-red-50 rounded">
                            <i class="fas fa-trash-alt text-[10px]"></i>
                        </button>
                    </div>
                </td>
            `
        itemsContainer.appendChild(row)
      })
    }
    
    // Function to update cart view summary with discounts (this calls updateCartTotals)
    function updateCartViewSummary() {
      updateCartTotals()
    
      const summaryContainer = document.getElementById("cartViewSummary")
      if (!summaryContainer) return
    
      const totalItems = cartItems.length
      const totalMetalAmount = cartItems.reduce((sum, item) => sum + item.metalAmount, 0)
      const totalMakingCharges = cartItems.reduce((sum, item) => sum + item.makingCharges, 0)
      const totalStonePrice = cartItems.reduce((sum, item) => sum + (item.stonePrice || 0), 0)
      const totalOtherCharges = cartItems.reduce((sum, item) => sum + (item.hmCharges || 0) + (item.otherCharges || 0), 0)
    
      const subtotalBeforeDiscounts = cartItems.reduce((sum, item) => sum + item.total, 0)
      const totalDiscount = cart.discount || 0
      const subtotalAfterDiscount = cart.subtotal || 0
      const urdAmountDisplay = cart.urdAmount || 0
      const gstAmountDisplay = cart.gstAmount || 0
      const grandTotalDisplay = cart.grandTotal || 0
    
      let summaryHTML = `
            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                <div class="text-xs text-gray-600">Total Items:</div>
                <div class="text-xs font-semibold text-right">${totalItems}</div>
                <div class="text-xs text-gray-600">Metal Amount:</div>
                <div class="text-xs font-semibold text-right">${formatCurrency(totalMetalAmount)}</div>
                <div class="text-xs text-gray-600">Making Charges:</div>
                <div class="text-xs font-semibold text-right">${formatCurrency(totalMakingCharges)}</div>`
    
      if (totalStonePrice > 0) {
        summaryHTML += `
                <div class="text-xs text-gray-600">Stone Price:</div>
                <div class="text-xs font-semibold text-right">${formatCurrency(totalStonePrice)}</div>`
      }
      if (totalOtherCharges > 0) {
        summaryHTML += `
                <div class="text-xs text-gray-600">Other Charges:</div>
                <div class="text-xs font-semibold text-right">${formatCurrency(totalOtherCharges)}</div>`
      }
      summaryHTML += `
            <div class="text-xs text-gray-600">Subtotal:</div>
            <div class="text-xs font-semibold text-right">${formatCurrency(subtotalBeforeDiscounts)}</div>`
    
      if (totalDiscount > 0) {
        summaryHTML += `
                <div class="text-xs text-green-600 font-medium mt-1">Total Discounts:</div>
                <div class="text-xs font-semibold text-right text-green-600 mt-1">-${formatCurrency(totalDiscount)}</div>
                <div class="text-xs text-blue-700">Subtotal after discount:</div>
                <div class="text-xs font-semibold text-right text-blue-700">${formatCurrency(subtotalAfterDiscount)}</div>`
      }
    
      // Display individual applied discounts from the list
      if (cart.appliedCoupon) {
        showAppliedDiscount("coupon", cart.appliedCoupon.code, cart.appliedCoupon.description)
      }
      if (cart.manualDiscount) {
        const desc =
          cart.manualDiscount.type === "percentage"
            ? `${cart.manualDiscount.value}% off`
            : `${formatCurrency(cart.manualDiscount.value)} off`
        showAppliedDiscount("manual", "Manual Discount", desc)
      }
      if (cart.loyaltyDiscount) {
        const loyaltyAmountValue = totalMakingCharges * firmConfiguration.loyaltyDiscountPercentage;
        document.getElementById("loyaltyDiscountAmountDisplay").textContent = formatCurrency(loyaltyAmountValue);
        showAppliedDiscount(
          "loyalty",
          "Loyalty Discount",
          `${(firmConfiguration.loyaltyDiscountPercentage * 100).toFixed(0)}% off (${formatCurrency(loyaltyAmountValue)})`,
        )
      } else {
        const loyaltyAmtDisp = document.getElementById("loyaltyDiscountAmountDisplay")
        if (loyaltyAmtDisp) loyaltyAmtDisp.textContent = formatCurrency(0)
      }
    
      if (cart.urdDetails && cart.urdAmount > 0) {
        showAppliedDiscount("urd", `URD (${cart.urdDetails.itemName})`, `${formatCurrency(cart.urdAmount)}`)
      }
    
      if (urdAmountDisplay > 0) {
        summaryHTML += `
                <div class="text-xs text-purple-600 border-t border-blue-100 pt-1 mt-1">URD Payment:</div>
                <div class="text-xs font-semibold text-right text-purple-600 border-t border-blue-100 pt-1 mt-1">-${formatCurrency(urdAmountDisplay)}</div>`
      }
    
      summaryHTML += `
            <div class="text-xs text-purple-600 flex items-center gap-1 mt-2">
                <input type="checkbox" id="gstToggle" class="h-3 w-3 text-purple-600" ${cart.gstEnabled ? "checked" : ""}>
                <span>Apply GST (${(firmConfiguration.gstRate * 100).toFixed(1)}%)</span>
            </div>
            <div class="text-xs font-semibold text-right text-purple-600 mt-2">${formatCurrency(gstAmountDisplay)}</div>
            <div class="text-sm text-blue-800 font-bold pt-2 mt-2 border-t border-blue-100">Grand Total:</div>
            <div class="text-sm font-bold text-right text-blue-800 pt-2 mt-2 border-t border-blue-100">${formatCurrency(grandTotalDisplay)}</div>
        </div>
        <div class="flex justify-end mt-4">
            <button onclick="proceedToCheckout()" class="px-4 py-2 bg-gradient-to-r from-green-500 to-blue-500 text-white rounded-lg text-sm font-medium flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Proceed to Checkout</span>
            </button>
        </div>`
      summaryContainer.innerHTML = summaryHTML
    
      const gstToggle = document.getElementById("gstToggle")
      if (gstToggle) {
        gstToggle.addEventListener("change", function () {
          cart.gstEnabled = this.checked
          updateCartViewSummary()
        })
      }
    
      if (gstApplicableCheckbox) gstApplicableCheckbox.checked = cart.gstEnabled
    }
    
    // Hide cart function with entry form restoration
    function hideCart(animate = true) {
      const entryForm = document.getElementById("entry-form")
      if (entryForm) entryForm.style.display = "block"
    
      const cartViewContainer = document.getElementById("cart-view-container")
      if (cartViewContainer) cartViewContainer.style.display = "none"
    
      if (cartBottomSheet) {
        cartBottomSheet.style.transform = "translateY(100%)"
      }
      isCartVisible = false
    }
    
    // Function to get the last invoice number based on GST status
    function getLastInvoiceNumber(isGstApplicable) {
      return new Promise((resolve, reject) => {
        fetch(`sale-entry.php?action=getLastInvoiceNumber&gst=${isGstApplicable ? 1 : 0}`)
          .then((response) => response.json())
          .then((data) => {
            console.log("Last invoice number:", data)
            resolve(data.invoiceNo)
          })
          .catch((error) => {
            console.error("Error fetching last invoice number:", error)
            reject(error)
          })
      })
    }
    
    // Function to generate next invoice number
    function generateNextInvoiceNumber(lastInvoiceNo, isGstApplicable) {
      const prefix = isGstApplicable ? "IN" : "NG"
      if (lastInvoiceNo && lastInvoiceNo.startsWith(prefix)) {
        const numPart = lastInvoiceNo.substring(prefix.length)
        const nextNum = Number.parseInt(numPart, 10) + 1
        return prefix + String(nextNum).padStart(2, "0")
      }
      return prefix + "01"
    }
    
    function fetchCustomerAdvancePayments(customerId) {
      return new Promise((resolve, reject) => {
        fetch(`sale-entry.php?action=getCustomerAdvancePayments&customerId=${customerId}`)
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              availableAdvancePayments = data.advancePayments
              resolve(data.advancePayments)
            } else {
              availableAdvancePayments = []
              reject(data.message || "Failed to fetch advance payments")
            }
          })
          .catch((error) => {
            console.error("Error fetching advance payments:", error)
            availableAdvancePayments = []
            reject("Network error while fetching advance payments")
          })
      })
    }
    
    // Function to display advance payments in checkout modal
  // Function to display advance payments in checkout modal
function displayAdvancePayments() {
  const advanceSection = document.getElementById("customerAdvanceSection")
  const advanceList = document.getElementById("advancePaymentsList")
  const totalAdvanceAmountEl = document.getElementById("totalAdvanceAmount")

  selectedAdvancePayments = []

  if (
    !advanceSection ||
    !advanceList ||
    !totalAdvanceAmountEl ||
    !availableAdvancePayments ||
    availableAdvancePayments.length === 0
  ) {
    if (advanceSection) advanceSection.classList.add("hidden")
    updateSelectedAdvanceAmount()
    return
  }

  advanceSection.classList.remove("hidden")
  const totalAdvance = availableAdvancePayments.reduce(
    (sum, payment) => sum + Number.parseFloat(payment.available_amount),
    0,
  )
  totalAdvanceAmountEl.textContent = formatCurrency(totalAdvance)
  advanceList.innerHTML = ""

  availableAdvancePayments.forEach((payment) => {
    const paymentItem = document.createElement("div")
    paymentItem.className = "bg-white p-2 rounded-lg border border-green-100 text-xs"
    const availableAmount = Number.parseFloat(payment.available_amount)
    const orderDate = new Date(payment.created_at).toLocaleDateString()
    paymentItem.innerHTML = `
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-medium">${payment.order_no} - ${payment.item_name || ''}</div>
                    <div class="text-gray-500 text-[10px]">Date: ${orderDate}</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-green-600 font-medium">${formatCurrency(availableAmount)}</span>
                    <input type="checkbox" 
                        data-order-id="${payment.id}" 
                        data-amount="${availableAmount}" 
                        class="advance-payment-checkbox h-4 w-4 text-green-600 rounded border-green-300"
                        onchange="toggleAdvancePayment(this)">
                </div>
            </div>`
    advanceList.appendChild(paymentItem)
  })
  updateSelectedAdvanceAmount()
}

function toggleAdvancePayment(checkbox) {
  const orderId = checkbox.dataset.orderId;
  const amount = Number.parseFloat(checkbox.dataset.amount);
  const orderNumber = checkbox.dataset.orderNumber || "";
  const itemName = checkbox.dataset.itemName || "";

  if (checkbox.checked) {
    if (!selectedAdvancePayments.some(p => p.id === orderId)) {
      selectedAdvancePayments.push({
        id: orderId,
        amount: amount,
        order_number: orderNumber,
        item_name: itemName
      });
    }
  } else {
    selectedAdvancePayments = selectedAdvancePayments.filter(p => p.id !== orderId);
  }
  updatePaymentMethodsWithAdvance();
}

function updateSelectedAdvanceAmount() {
  const selectedAmount = selectedAdvancePayments.reduce((sum, payment) => sum + payment.amount, 0)
  const el = document.getElementById("selectedAdvanceAmount")
  if (el) el.textContent = formatCurrency(selectedAmount)
}

// Track if the user has manually edited the main payment method
let mainPaymentManuallyEdited = false;

function updatePaymentMethodsWithAdvance() {
  // Remove all previous advance adjustments
  paymentMethods = paymentMethods.filter(pm => pm.type !== "advance_adjustment");

  // Add all selected advances as payment methods
  selectedAdvancePayments.forEach(advance => {
    paymentMethods.push({
      id: `adv-${advance.id}`,
      type: "advance_adjustment",
      amount: advance.amount,
      orderId: advance.id,
      orderNo: advance.order_number || "",
      itemName: advance.item_name || ""
    });
  });

  // Removed auto-add of main payment method with remaining amount

  renderPaymentMethods();
  updatePaymentSummary();
}

async function proceedToCheckout() {
  if (cartItems.length === 0) {
    showToast("Your cart is empty", "error")
    return
  }
  if (!selectedCustomer) {
    showToast("Please select a customer", "error")
    return
  }

  try {
    updateCartTotals()

    const grandTotal = Math.round(cart.grandTotal) // Round to nearest integer

    const lastInvoiceNo = await getLastInvoiceNumber(cart.gstEnabled)
    currentInvoiceNo = generateNextInvoiceNumber(lastInvoiceNo, cart.gstEnabled)

    try {
      await fetchCustomerAdvancePayments(selectedCustomer.id)
    } catch (error) {
      console.error("Error fetching advance payments:", error)
    }

    const checkoutModal = document.getElementById("checkoutModal")
    if (!checkoutModal) return

    const today = new Date().toLocaleDateString()

    const checkoutTotalAmountEl = document.getElementById("checkoutTotalAmount")
    const checkoutGstAmountEl = document.getElementById("checkoutGstAmount")
    const checkoutDiscountEl = document.getElementById("checkoutDiscount")
    const checkoutUrdAmountEl = document.getElementById("checkoutUrdAmount")
    const grandTotalElement = document.getElementById("checkoutGrandTotal")
    const invoiceDateEl = document.getElementById("invoiceDate")
    const customerDetailsEl = document.getElementById("customerDetails")
    const invoiceNumberEl = document.getElementById("invoiceNumber")

    if (invoiceDateEl) invoiceDateEl.textContent = today
    if (customerDetailsEl) customerDetailsEl.textContent = `${selectedCustomer.FirstName} ${selectedCustomer.LastName}`
    if (invoiceNumberEl) invoiceNumberEl.textContent = currentInvoiceNo

    if (checkoutTotalAmountEl)
      checkoutTotalAmountEl.textContent = formatCurrency(cartItems.reduce((sum, item) => sum + item.total, 0))
    if (checkoutGstAmountEl) checkoutGstAmountEl.textContent = formatCurrency(cart.gstAmount)
    if (checkoutDiscountEl) checkoutDiscountEl.textContent = formatCurrency(cart.discount)
    if (checkoutUrdAmountEl) checkoutUrdAmountEl.textContent = formatCurrency(cart.urdAmount)

    if (grandTotalElement) {
      grandTotalElement.textContent = formatCurrency(grandTotal)
      grandTotalElement.setAttribute("data-original-total", grandTotal)
    }

    // Reset advance payments display
    displayAdvancePayments()

    // --- Payment Methods Initialization ---
    paymentMethods = []
    currentTransactionTotal = grandTotal
    mainPaymentManuallyEdited = false

    // Always add a default Cash payment method
    paymentMethods.push({
      id: `main-${Date.now()}`,
      type: "cash",
      amount: 0,
      reference: ""
    });

    renderPaymentMethods();
    updatePaymentSummary();
    if (checkoutModal) checkoutModal.style.display = "flex";
  } catch (error) {
    console.error("Error in checkout process:", error)
    showToast("Error preparing checkout", "error")
  }
}

// --- Update Payment Methods With Advance ---
function updatePaymentMethodsWithAdvance() {
  // Remove all previous advance adjustments
  paymentMethods = paymentMethods.filter(pm => pm.type !== "advance_adjustment");

  // Add all selected advances as payment methods
  selectedAdvancePayments.forEach(advance => {
    paymentMethods.push({
      id: `adv-${advance.id}`,
      type: "advance_adjustment",
      amount: advance.amount,
      orderId: advance.id,
      orderNo: advance.order_number || "",
      itemName: advance.item_name || ""
    });
  });

  // Calculate remaining due
  const totalAdvance = selectedAdvancePayments.reduce((sum, adv) => sum + adv.amount, 0);
  let totalOtherPayments = paymentMethods.filter(pm => pm.type !== "advance_adjustment").reduce((sum, pm) => sum + (Number(pm.amount) || 0), 0);
  let remainingDue = Math.max(0, currentTransactionTotal - totalAdvance - totalOtherPayments);

  // If user hasn't manually edited the main payment, auto-fill it
  let mainPayment = paymentMethods.find(pm => pm.type !== "advance_adjustment");
  if (mainPayment && !mainPaymentManuallyEdited) {
    mainPayment.amount = remainingDue;
  }

  renderPaymentMethods();
  updatePaymentSummary();
}

// --- Add Payment Method ---
function addPaymentMethod(amount = null) {
  // Calculate current due
  const totalAdvance = paymentMethods.filter(pm => pm.type === "advance_adjustment").reduce((sum, pm) => sum + (Number(pm.amount) || 0), 0);
  const totalOtherPayments = paymentMethods.filter(pm => pm.type !== "advance_adjustment").reduce((sum, pm) => sum + (Number(pm.amount) || 0), 0);
  const remainingDue = Math.max(0, currentTransactionTotal - totalAdvance - totalOtherPayments);

  const newPayment = {
    id: `pm-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    type: "cash",
    amount: amount !== null ? amount : remainingDue,
    reference: "",
  };
  paymentMethods.push(newPayment);
  renderPaymentMethods();
}

// --- Update Payment Method ---
function updatePaymentMethod(id, field, value, shouldRerender = false) {
  const paymentIndex = paymentMethods.findIndex((p) => p.id === id)
  if (paymentIndex === -1) return

  if (field === "amount") {
    // Validation: Cap the amount so total does not exceed currentTransactionTotal
    const totalAdvance = paymentMethods.filter((p) => p.type === "advance_adjustment").reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
    const otherPayments = paymentMethods.filter((p, idx) => p.type !== "advance_adjustment" && idx !== paymentIndex).reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
    let maxAllowed = Math.max(0, currentTransactionTotal - totalAdvance - otherPayments);
    let newValue = Math.max(0, Math.min(Number(value) || 0, maxAllowed));
    paymentMethods[paymentIndex][field] = newValue;
    if (paymentIndex === 0) mainPaymentManuallyEdited = true;
  } else {
    paymentMethods[paymentIndex][field] = value
    if (field === "type" && value === "cash") {
      paymentMethods[paymentIndex].reference = ""
    }
  }
  if (shouldRerender) {
    renderPaymentMethods()
  }
  updatePaymentSummary();
}

// --- Remove Payment Method ---
function removePaymentMethod(idToRemove) {
  const paymentToRemove = paymentMethods.find((p) => p.id === idToRemove)
  paymentMethods = paymentMethods.filter((p) => p.id !== idToRemove)

  if (paymentToRemove && paymentToRemove.type === "advance_adjustment") {
    const advanceCheckbox = document.querySelector(
      `.advance-payment-checkbox[data-order-id='${paymentToRemove.orderId}']`,
    )
    if (advanceCheckbox) {
      advanceCheckbox.checked = false
    }
    selectedAdvancePayments = selectedAdvancePayments.filter((adv) => adv.id !== paymentToRemove.orderId)
    updateSelectedAdvanceAmount()
  }

  // After removal, auto-fill remaining due in the first available method if not manually edited
  const totalAdvance = paymentMethods.filter(pm => pm.type === "advance_adjustment").reduce((sum, pm) => sum + (Number(pm.amount) || 0), 0);
  let totalOtherPayments = paymentMethods.filter(pm => pm.type !== "advance_adjustment").reduce((sum, pm) => sum + (Number(pm.amount) || 0), 0);
  let remainingDue = Math.max(0, currentTransactionTotal - totalAdvance - totalOtherPayments);
  let mainPayment = paymentMethods.find(pm => pm.type !== "advance_adjustment");
  if (mainPayment && !mainPaymentManuallyEdited) {
    mainPayment.amount = remainingDue;
  }

  renderPaymentMethods()
  updatePaymentSummary() // Ensure summary is updated after removing a payment method
}

// --- Update Payment Summary ---
function updatePaymentSummary() {
  const totalPaidElement = document.getElementById("totalPaidAmount");
  const remainingElement = document.getElementById("remainingAmount");

  // Always sum all payment methods (including advances)
  const totalPaid = paymentMethods.reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
  const dueAmount = Math.max(0, currentTransactionTotal - totalPaid);

  if (totalPaidElement) totalPaidElement.textContent = formatCurrency(totalPaid);
  if (remainingElement) remainingElement.textContent = formatCurrency(dueAmount);

  // UI feedback for due
  const dueMsg = document.getElementById("dueAmountMsg");
  if (dueMsg) {
    if (dueAmount > 0) {
      dueMsg.textContent = "Will be added to customer dues";
      dueMsg.classList.remove("hidden");
    } else {
      dueMsg.textContent = "";
      dueMsg.classList.add("hidden");
    }
  }

  // Validation: Prevent overpayment
  if (totalPaid > currentTransactionTotal) {
    showToast("Total paid cannot exceed the bill amount!", "error");
  }
}

// --- Fix Total Selected for Advances ---
function updateSelectedAdvanceAmount() {
  const selectedAmount = selectedAdvancePayments.reduce((sum, payment) => sum + payment.amount, 0)
  const el = document.getElementById("selectedAdvanceAmount")
  if (el) el.textContent = formatCurrency(selectedAmount)
}
    
    // Function to edit a cart item
    function editCartItem(index) {
      editingItemIndex = index
      if (editModeIndicator) editModeIndicator.classList.add("show")
      if (addToCartText) addToCartText.textContent = "Update Item"
      if (cartBtnIcon) cartBtnIcon.className = "fas fa-save"
    
      const item = cartItems[index]
      if (!item) return
    
      if (productNameInput) productNameInput.value = item.productName
      if (huidCodeInput) huidCodeInput.value = item.huidCode || ""
      if (rate24kInput) rate24kInput.value = item.rate24k
      if (purityInput) purityInput.value = item.purity
      if (purityRateInput) purityRateInput.value = item.purityRate
      if (grossWeightInput) grossWeightInput.value = item.grossWeight
      if (lessWeightInput) lessWeightInput.value = item.lessWeight
      if (netWeightInput) netWeightInput.value = item.netWeight
      if (metalAmountInput) metalAmountInput.value = item.metalAmount
      if (stoneTypeSelect) stoneTypeSelect.value = item.stoneType || "None"
      if (stoneWeightInput) stoneWeightInput.value = item.stoneWeight
      if (stonePriceInput) stonePriceInput.value = item.stonePrice
      if (makingTypeSelect) makingTypeSelect.value = item.makingType
      if (makingRateInput) makingRateInput.value = item.makingRate
      if (makingChargesInput) makingChargesInput.value = item.makingCharges
      if (hmChargesInput) hmChargesInput.value = item.hmCharges
      if (otherChargesInput) otherChargesInput.value = item.otherCharges
      if (totalChargesInput) totalChargesInput.value = item.totalCharges
    
      calculateTotal()
      hideCart()
      if (addToCartBtn) addToCartBtn.disabled = false
    }
    
    // Function to remove a cart item
    function removeCartItem(index) {
      if (confirm("Are you sure you want to remove this item from the cart?")) {
        cartItems.splice(index, 1)
        updateCartUI()
        showToast("Item removed from cart")
        if (cartItems.length === 0) {
          hideCart()
          const cartViewContainer = document.getElementById("cart-view-container")
          if (cartViewContainer) cartViewContainer.style.display = "none"
        } else {
          if (document.getElementById("cart-view-container")?.style.display === "block") {
            updateCartViewItems()
            updateCartViewSummary()
          }
        }
      }
    }
    
    // Function to update cart UI (both bottom sheet and cart view)
    function updateCartUI() {
      if (cartItemCount) cartItemCount.textContent = cartItems.length + " items"
      if (bottomNavCartBadge) bottomNavCartBadge.textContent = cartItems.length
    
      if (cartItemsContainer) {
        cartItemsContainer.innerHTML = ""
        cartItems.forEach((item, index) => {
          const row = document.createElement("tr")
          row.className = index % 2 === 0 ? "bg-white" : "bg-blue-50/30"
          row.innerHTML = `
                    <td class="px-2 py-2">
                        <div class="font-medium text-blue-900">${item.productName}</div>
                        <div class="text-[10px] text-gray-500">${item.purity}% | ${item.netWeight.toFixed(3)}g</div>
                    </td>
                    <td class="px-2 py-2 text-right">${item.netWeight.toFixed(3)}g</td>
                    <td class="px-2 py-2 text-right">${formatCurrency(item.metalAmount)}</td>
                    <td class="px-2 py-2 text-right">${formatCurrency(item.makingCharges)}</td>
                    <td class="px-2 py-2 text-right font-medium">${formatCurrency(item.total)}</td>
                    <td class="px-2 py-2 text-center">
                        <div class="flex justify-center gap-1">
                            <button onclick="editCartItem(${index})" class="p-1 text-blue-600 hover:bg-blue-50 rounded"><i class="fas fa-edit"></i></button>
                            <button onclick="removeCartItem(${index})" class="p-1 text-red-600 hover:bg-red-50 rounded"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>`
          cartItemsContainer.appendChild(row)
        })
      }
    
      updateCartTotals()
    
      if (document.getElementById("cart-view-container")?.style.display === "block") {
        updateCartViewItems()
        updateCartViewSummary()
      } else if (cartItems.length > 0 && priceBreakdownContainer) {
        // updateCartTotals already handles the price breakdown
      } else if (priceBreakdownContainer && grandTotalSpan) {
        priceBreakdownContainer.innerHTML = '<div class="text-center text-gray-500 py-2">No items in cart</div>'
        grandTotalSpan.textContent = formatCurrency(0)
      }
    }
    
    // NEW: Enhanced updateCartTotals function using firm configuration
    function updateCartTotals() {
      if (!window.cart) initializeCart()
    
      const totalMetalAmount = cartItems.reduce((sum, item) => sum + Number.parseFloat(item.metalAmount || 0), 0);
      const totalStoneAmount = cartItems.reduce((sum, item) => sum + (Number.parseFloat(item.stonePrice) || 0), 0);
      const totalMakingCharges = cartItems.reduce((sum, item) => sum + Number.parseFloat(item.makingCharges || 0), 0);
      const totalOtherCharges = cartItems.reduce((sum, item) => sum + (Number.parseFloat(item.hmCharges) || 0) + (Number.parseFloat(item.otherCharges) || 0), 0);
    
      const totalAmountBeforeDiscounts = totalMetalAmount + totalStoneAmount + totalMakingCharges + totalOtherCharges;
    
      // Calculate total discount based on the full amount before any discounts
      cart.discount = calculateTotalDiscount(totalAmountBeforeDiscounts, totalMakingCharges);
    
      // Calculate subtotal: sum of components - discount
      cart.subtotal = totalAmountBeforeDiscounts - cart.discount;
      // Ensure subtotal is not negative
      cart.subtotal = Math.max(0, cart.subtotal);
    
      // Note: The individual item totals in cartItems still include the original making charges.
      // The discount is reflected only in the overall cart totals (subtotal, grandTotal).
    
      const gstApplicableEl = document.getElementById("gstApplicable")
      const gstToggleInCartView = document.getElementById("gstToggle")
    
      if (gstApplicableEl && gstApplicableEl === document.activeElement) cart.gstEnabled = gstApplicableEl.checked
      else if (gstToggleInCartView && gstToggleInCartView === document.activeElement)
        cart.gstEnabled = gstToggleInCartView.checked
    
      // NEW: Use firm configuration GST rate instead of hardcoded 0.03
      cart.gstAmount = cart.gstEnabled ? Number.parseFloat((cart.subtotal * firmConfiguration.gstRate).toFixed(2)) : 0
    
      const urdVal = Number.parseFloat(cart.urdAmount) || 0
      cart.grandTotal = Number.parseFloat((cart.subtotal + cart.gstAmount - urdVal).toFixed(2))
    
      if (priceBreakdownContainer) {
        if (cartItems.length === 0) {
          priceBreakdownContainer.innerHTML = '<div class="text-center text-gray-500 py-2">No items in cart</div>'
          if (grandTotalSpan) grandTotalSpan.textContent = formatCurrency(0)
        } else {
          const totalMetalAmount = cartItems.reduce((sum, item) => sum + Number.parseFloat(item.metalAmount || 0), 0)
          const totalStoneAmount = cartItems.reduce((sum, item) => sum + (Number.parseFloat(item.stonePrice) || 0), 0)
          const totalMakingCharges = cartItems.reduce((sum, item) => sum + Number.parseFloat(item.makingCharges || 0), 0)
          const totalOtherCharges = cartItems.reduce(
            (sum, item) => sum + (Number.parseFloat(item.hmCharges) || 0) + (Number.parseFloat(item.otherCharges) || 0),
            0,
          )
    
          priceBreakdownContainer.innerHTML = `
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <div class="text-xs text-gray-600">Total Items:</div>
                        <div class="text-xs font-semibold text-right">${cartItems.length}</div>
                        <div class="text-xs text-gray-600">Metal Amt:</div>
                        <div class="text-xs font-semibold text-right">${formatCurrency(totalMetalAmount)}</div>
                        <div class="text-xs text-gray-600">Stone Amt:</div>
                        <div class="text-xs font-semibold text-right">${formatCurrency(totalStoneAmount)}</div>
                        <div class="text-xs text-gray-600">Making Chrgs:</div>
                        <div class="text-xs font-semibold text-right">${formatCurrency(totalMakingCharges)}</div>
                        <div class="text-xs text-gray-600">Other Chrgs:</div>
                        <div class="text-xs font-semibold text-right">${formatCurrency(totalOtherCharges)}</div>
                        <div class="text-xs text-gray-600 font-medium">Subtotal:</div>
                        <div class="text-xs font-semibold text-right">${formatCurrency(totalAmountBeforeDiscounts)}</div>
                        ${
                          cart.discount > 0
                            ? `
                            <div class="text-xs text-green-600">Discount:</div>
                            <div class="text-xs font-semibold text-right text-green-600">-${formatCurrency(cart.discount)}</div>
                            <div class="text-xs text-blue-600">After Discount:</div>
                            <div class="text-xs font-semibold text-right text-blue-600">${formatCurrency(cart.subtotal)}</div>
                        `
                            : ""
                        }
                        ${
                          cart.gstEnabled
                            ? `
                            <div class="text-xs text-purple-600">GST (${(firmConfiguration.gstRate * 100).toFixed(1)}%):</div>
                            <div class="text-xs font-semibold text-right text-purple-600">${formatCurrency(cart.gstAmount)}</div>
                        `
                            : ""
                        }
                        ${
                          urdVal > 0
                            ? `
                            <div class="text-xs text-orange-600">URD Ded.:</div>
                            <div class="text-xs font-semibold text-right text-orange-600">-${formatCurrency(urdVal)}</div>
                        `
                            : ""
                        }
                        <div class="text-sm text-gray-800 font-medium pt-2 border-t">Grand Total:</div>
                        <div class="text-sm font-bold text-right pt-2 border-t">${formatCurrency(cart.grandTotal)}</div>
                    </div>`
          if (grandTotalSpan) grandTotalSpan.textContent = formatCurrency(cart.grandTotal)
        }
      }
    
      if (gstAmountInput) gstAmountInput.value = cart.gstAmount.toFixed(2)
      const checkoutGstEl = document.getElementById("checkoutGstAmount")
      if (checkoutGstEl) checkoutGstEl.textContent = formatCurrency(cart.gstAmount)
      if (gstApplicableEl) gstApplicableEl.checked = cart.gstEnabled
      if (gstToggleInCartView) gstToggleInCartView.checked = cart.gstEnabled
    
      console.log("Cart totals updated:", JSON.parse(JSON.stringify(cart)))
    }
    
    // NEW: Enhanced function to calculate total discount using firm configuration
    function calculateTotalDiscount(totalAmountBeforeDiscounts, totalMakingCharges) {
      let currentTotalDiscount = 0;

      if (cart.appliedCoupon) {
        const coupon = cart.appliedCoupon;
        if (coupon.type === "percentage") {
          currentTotalDiscount += totalMakingCharges * (coupon.value / 100);
        } else {
          currentTotalDiscount += Math.min(coupon.value, totalAmountBeforeDiscounts);
        }
      }

      if (cart.manualDiscount) {
        const discount = cart.manualDiscount;
        if (discount.type === "percentage") {
          currentTotalDiscount += totalAmountBeforeDiscounts * (discount.value / 100);
        } else {
          currentTotalDiscount += Math.min(discount.value, totalAmountBeforeDiscounts);
        }
      }

      if (cart.loyaltyDiscount) {
        currentTotalDiscount += totalMakingCharges * firmConfiguration.loyaltyDiscountPercentage;
      }
      return Number.parseFloat(currentTotalDiscount.toFixed(2));
    }
    
    // Function to clear cart
    function clearCart() {
      if (confirm("Are you sure you want to clear the cart? This will remove all items.")) {
        cartItems = []
        cart.appliedCoupon = null
        cart.manualDiscount = null
        cart.loyaltyDiscount = false
        cart.urdAmount = 0
        cart.urdDetails = null
    
        updateCartUI()
        hideCart()
        const cartViewContainer = document.getElementById("cart-view-container")
        if (cartViewContainer) cartViewContainer.style.display = "none"
        const appliedDiscountsList = document.getElementById("appliedDiscountsList")
        if (appliedDiscountsList) {
          appliedDiscountsList.innerHTML = ""
          appliedDiscountsList.classList.add("hidden")
        }
    
        showToast("Cart cleared")
      }
    }
    
    // Function to sync GST state between different parts of the UI
    function syncGSTState() {
      const gstCheckbox = document.getElementById("gstApplicable")
      if (gstCheckbox) gstCheckbox.checked = cart.gstEnabled
    
      const gstToggle = document.getElementById("gstToggle")
      if (gstToggle) gstToggle.checked = cart.gstEnabled
    }
    
    // Function to close checkout modal
    function closeCheckoutModal() {
      const modal = document.getElementById("checkoutModal")
      if (modal) modal.style.display = "none"
    }
    
    // Function to add a payment method
    function addPaymentMethod(amount = 0) {
      const newPayment = {
        id: `pm-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        type: "cash",
        amount: Number.parseFloat(amount) || 0,
        reference: "",
      }
      paymentMethods.push(newPayment)
      renderPaymentMethods()
    }
    
    // Function to update a payment method
    function updatePaymentMethod(id, field, value, shouldRerender = false) {
      const paymentIndex = paymentMethods.findIndex((p) => p.id === id)
      if (paymentIndex === -1) return
    
      if (field === "amount") {
        const totalAdvance = paymentMethods.filter((p) => p.type === "advance_adjustment").reduce((sum, p) => sum + (p.amount || 0), 0);
        const maxAllowed = Math.max(0, currentTransactionTotal - totalAdvance);
        paymentMethods[paymentIndex][field] = Math.max(0, Math.min(Number.parseFloat(value) || 0, maxAllowed));
        mainPaymentManuallyEdited = true;
        console.log('[DEBUG] updatePaymentMethod (manual edit):', {
          id,
          value,
          currentTransactionTotal,
          totalAdvance,
          maxAllowed,
          paymentMethods: JSON.parse(JSON.stringify(paymentMethods)),
        });
      } else {
        paymentMethods[paymentIndex][field] = value
        if (field === "type" && value === "cash") {
          paymentMethods[paymentIndex].reference = ""
        }
      }
      if (shouldRerender) {
        renderPaymentMethods()
      }
    }
    
    // Function to remove a payment method
    function removePaymentMethod(idToRemove) {
      const paymentToRemove = paymentMethods.find((p) => p.id === idToRemove)
    
      paymentMethods = paymentMethods.filter((p) => p.id !== idToRemove)
    
      if (paymentToRemove && paymentToRemove.type === "advance_adjustment") {
        const advanceCheckbox = document.querySelector(
          `.advance-payment-checkbox[data-order-id='${paymentToRemove.orderId}']`,
        )
        if (advanceCheckbox) {
          advanceCheckbox.checked = false
        }
        selectedAdvancePayments = selectedAdvancePayments.filter((adv) => adv.id !== paymentToRemove.orderId)
        updateSelectedAdvanceAmount()
      }
    
      const nonAdvancePaymentMethods = paymentMethods.filter((pm) => pm.type !== "advance_adjustment")
      const totalAdvancePaid = paymentMethods
        .filter((pm) => pm.type === "advance_adjustment")
        .reduce((sum, pm) => sum + (pm.amount || 0), 0)
      const remainingForNonAdvance = currentTransactionTotal - totalAdvancePaid
    
      if (nonAdvancePaymentMethods.length === 0 && remainingForNonAdvance > 0) {
        addPaymentMethod(remainingForNonAdvance)
      } else if (paymentMethods.length === 0 && currentTransactionTotal > 0) {
        addPaymentMethod(currentTransactionTotal)
      } else if (paymentMethods.length === 0 && currentTransactionTotal === 0) {
        addPaymentMethod(0)
      }
    
      renderPaymentMethods()
      updatePaymentSummary() // Ensure summary is updated after removing a payment method
    }
    // NEW: Enhanced processSale function with success modal instead of immediate redirect
    function processSale() {
      if (paymentMethods.length === 0 && currentTransactionTotal > 0) {
        showToast("Please add at least one payment method", "error")
        return
      }
    
      updateCartTotals()
    
      const totalPaidByNonAdvance = paymentMethods
        .filter((p) => p.type !== "advance_adjustment")
        .reduce((sum, payment) => sum + Number.parseFloat(payment.amount || 0), 0)
      const totalAdvanceAdjusted = paymentMethods
        .filter((p) => p.type === "advance_adjustment")
        .reduce((sum, payment) => sum + Number.parseFloat(payment.amount || 0), 0)
    
      const totalAmountBeforeDiscounts = cartItems.reduce((sum, item) => sum + item.total, 0);
      const totalMakingChargesFromItems = cartItems.reduce((sum, item) => sum + item.makingCharges, 0);

      let calculatedCouponDiscount = 0;
      if (cart.appliedCoupon) {
        const coupon = cart.appliedCoupon;
        if (coupon.type === "percentage") {
          calculatedCouponDiscount = totalMakingChargesFromItems * (coupon.value / 100);
        } else {
          calculatedCouponDiscount = Math.min(coupon.value, totalAmountBeforeDiscounts);
        }
      }

      let calculatedLoyaltyDiscount = 0;
      if (cart.loyaltyDiscount) {
        calculatedLoyaltyDiscount = totalMakingChargesFromItems * firmConfiguration.loyaltyDiscountPercentage;
      }

      let calculatedManualDiscountAmount = 0;
      if (cart.manualDiscount) {
        const md = cart.manualDiscount;
        if (md.type === "percentage") {
          calculatedManualDiscountAmount = totalAmountBeforeDiscounts * (md.value / 100);
        } else {
          calculatedManualDiscountAmount = Math.min(md.value, totalAmountBeforeDiscounts);
        }
      }

      const actualTotalDiscountForSaleData = Number.parseFloat(
        (calculatedCouponDiscount + calculatedLoyaltyDiscount + calculatedManualDiscountAmount).toFixed(2),
      )
    
      const saleData = {
        invoiceNo: currentInvoiceNo,
        customerId: selectedCustomer.id,
        items: cartItems.map((item) => ({
          productId: item.productId,
          productName: item.productName,
          huidCode: item.huidCode || "",
          rate24k: item.rate24k,
          purity: item.purity,
          purityRate: item.purityRate,
          grossWeight: item.grossWeight,
          lessWeight: item.lessWeight,
          netWeight: item.netWeight,
          metalAmount: item.metalAmount,
          stoneType: item.stoneType || "",
          stoneWeight: item.stoneWeight || 0,
          stonePrice: item.stonePrice || 0,
          makingType: item.makingType || "per_gram",
          makingRate: item.makingRate,
          makingCharges: item.makingCharges,
          hmCharges: item.hmCharges || 0,
          otherCharges: item.otherCharges || 0,
          totalCharges: item.totalCharges,
          total: item.total,
        })),
        totalMetal: cartItems.reduce((sum, item) => sum + item.metalAmount, 0),
        totalStone: cartItems.reduce((sum, item) => sum + (item.stonePrice || 0), 0),
        totalMaking: cartItems.reduce((sum, item) => sum + item.makingCharges, 0),
        totalOther: cartItems.reduce((sum, item) => sum + (item.hmCharges || 0) + (item.otherCharges || 0), 0),
    
        discount: actualTotalDiscountForSaleData,
    
        urdAmount: cart.urdAmount || 0,
        subtotal: cart.subtotal,
        gstAmount: cart.gstAmount,
        grandTotal: cart.grandTotal,
        isGstApplicable: cart.gstEnabled,
        notes: document.getElementById("saleNotes")?.value || "",
        paymentMethods: paymentMethods.map((pm) => ({
          type: pm.type,
          amount: pm.amount,
          reference: pm.reference || null,
          orderId: pm.orderId || null,
        })),
        urdDetails: cart.urdDetails || null,
    
        couponCode: cart.appliedCoupon ? cart.appliedCoupon.code : "",
        couponDiscount: Number.parseFloat(calculatedCouponDiscount.toFixed(2)),
        loyaltyDiscount: Number.parseFloat(calculatedLoyaltyDiscount.toFixed(2)),
        manualDiscount: Number.parseFloat(calculatedManualDiscountAmount.toFixed(2)),
    
        advancePaymentAmount: Number.parseFloat(totalAdvanceAdjusted.toFixed(2)),
        advanceOrderIds: paymentMethods.filter((p) => p.type === "advance_adjustment" && p.orderId).map((p) => p.orderId),
      }
    
      const completeBtn = document.getElementById("completeSaleBtn")
      if (completeBtn) {
        completeBtn.disabled = true
        completeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...'
      }
    
      console.log("Sending sale data:", JSON.parse(JSON.stringify(saleData)))
    
      fetch("sale-entry.php?action=processCheckout", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(saleData),
      })
        .then((response) => {
          if (!response.ok) {
            return response.text().then((text) => {
              throw new Error("Server error: " + text)
            })
          }
          return response.json()
        })
        .then((data) => {
          console.log("Server response:", data)
          
          if (data.success) {
            console.log("Sale completed successfully!")
            console.log("Sale ID:", data.saleId)
            console.log("Invoice No:", data.invoiceNo)
            console.log("Advance Amount:", data.advanceAmount)
            console.log("Regular Payment:", data.regularPayment)
            
            if (data.schemeEntries) {
              console.log("Scheme entries created:", data.schemeEntries)
            }
            
            showToast("Sale completed successfully!", "success")
            
            // NEW: Store necessary data before clearing cart
            const modalData = {
              ...data,
              customerName: selectedCustomer ? `${selectedCustomer.FirstName} ${selectedCustomer.LastName}` : '',
              grandTotal: cart.grandTotal,
              isGstEnabled: cart.gstEnabled,
              appliedCoupon: cart.appliedCoupon || null, // Pass applied coupon details
              schemeEntries: data.schemeEntries || [],   // Pass scheme entries
            }
            
            // Clear form data
            cartItems = []
            initializeCart()
            updateCartUI()
            closeCheckoutModal()
            resetForm()
            selectedCustomer = null
            if (customerNameInput) customerNameInput.value = ""
            if (selectionDetails) {
              selectionDetails.innerHTML = ""
              selectionDetails.classList.add("hidden")
            }
            const cartView = document.getElementById("cart-view-container")
            if (cartView) cartView.style.display = "none"
    
            // NEW: Show success modal instead of immediate redirect
            console.log("About to show modal with data:", modalData)
            try {
              showSaleSuccessModal(modalData)
            } catch (error) {
              console.error("Error showing modal:", error)
              // Fallback: redirect if modal fails
              const redirectUrl = modalData.isGstEnabled ? firmConfiguration.gstBillPage : firmConfiguration.nonGstBillPage
              window.location.href = `${redirectUrl}?id=${modalData.saleId}`
            }
            
          } else {
            console.error("Sale failed:", data.message)
            showToast(data.message || "Error processing sale", "error")
            if (completeBtn) {
              completeBtn.disabled = false
              completeBtn.innerHTML = "Complete Sale"
            }
          }
        })
        .catch((error) => {
          console.error("Error processing sale:", error)
          showToast("Error processing sale: " + error.message, "error")
          if (completeBtn) {
            completeBtn.disabled = false
            completeBtn.innerHTML = "Complete Sale"
          }
        })
    }
    
    // NEW: Function to show success modal after sale completion
    function showSaleSuccessModal(saleData) {
      console.log("Creating modal with data:", saleData)
      
      // Remove any existing modal first
      const existingModal = document.getElementById('saleSuccessModal')
      if (existingModal) {
        existingModal.remove()
      }
      
      // Create modal HTML - simplified to avoid template literal issues
      const modalDiv = document.createElement('div')
      modalDiv.id = 'saleSuccessModal'
      modalDiv.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50'
      modalDiv.style.zIndex = '9999'
      
      modalDiv.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
          <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
              <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </div>
            
            <h3 class="text-lg font-medium text-gray-900 mb-2">Sale Completed Successfully!</h3>
            
            <div class="text-sm text-gray-600 space-y-2 mb-6">
              <p><strong>Invoice No:</strong> ${saleData.invoiceNo || 'N/A'}</p>
              <p><strong>Sale ID:</strong> ${saleData.saleId || 'N/A'}</p>
              ${saleData.customerName ? `<p><strong>Customer:</strong> ${saleData.customerName}</p>` : ''}
              <p><strong>Total Amount:</strong> ₹${(saleData.grandTotal || 0).toFixed(2)}</p>
              ${(saleData.advanceAmount || 0) > 0 ? `<p><strong>Advance Used:</strong> ₹${(saleData.advanceAmount || 0).toFixed(2)}</p>` : ''}
              
              ${saleData.appliedCoupon ? `
                <p class="text-green-600"><strong>Coupon Applied:</strong> ${saleData.appliedCoupon.code} (${saleData.appliedCoupon.description})</p>
              ` : ''}
              
              ${saleData.newlyAssignedCoupon ? `
                <p class="text-purple-600 mt-2"><strong>New Coupon Assigned:</strong> ${saleData.newlyAssignedCoupon.message}</p>
              ` : ''}

              ${saleData.schemeEntries && saleData.schemeEntries.length > 0 ? `
                <div class="mt-3 pt-3 border-t border-gray-200">
                  <p class="font-semibold text-blue-700 mb-1">Scheme Entries:</p>
                  <ul class="list-disc list-inside ml-2">
                    ${saleData.schemeEntries.map(entry => `
                      <li>${entry.scheme_title}</li>
                    `).join('')}
                  </ul>
                </div>
              ` : ''}
            </div>
            
            <div class="flex space-x-3 justify-center">
              <button id="closeModalBtn" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                Close
              </button>
              <button id="printBillBtn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                🖨️ Print Bill
              </button>
            </div>
          </div>
        </div>
      `
      
      // Add modal to page
      document.body.appendChild(modalDiv)
      console.log("Modal added to DOM")
      
      // Add event listeners
      const closeBtn = document.getElementById('closeModalBtn')
      const printBtn = document.getElementById('printBillBtn')
      
      if (closeBtn) {
        closeBtn.addEventListener('click', function() {
          console.log("Close button clicked")
          closeSaleSuccessModal()
        })
      }
      
      if (printBtn) {
        printBtn.addEventListener('click', function() {
          console.log("Print button clicked")
          printBillAndClose(saleData.saleId, saleData.isGstEnabled)
        })
      }
      
      // Close modal when clicking outside
      modalDiv.addEventListener('click', function(e) {
        if (e.target === modalDiv) {
          console.log("Clicked outside modal")
          closeSaleSuccessModal()
        }
      })
      
      console.log("Modal setup complete")
    }
    
    // NEW: Function to close the success modal
    function closeSaleSuccessModal() {
      const modal = document.getElementById('saleSuccessModal')
      if (modal) {
        modal.remove()
      }
    }
    
    // NEW: Function to print bill and close modal
    function printBillAndClose(saleId, isGstEnabled) {
      const redirectUrl = isGstEnabled ? firmConfiguration.gstBillPage : firmConfiguration.nonGstBillPage
      console.log("Redirecting to bill page:", `${redirectUrl}?id=${saleId}`)
      window.location.href = `${redirectUrl}?id=${saleId}`
    }
    // URD Modal Functions
    function showUrdModal() {
      if (!urdModal) return
      urdModal.style.display = "flex"
    
      fetch("sale-entry.php?action=getGoldRate")
        .then((response) => response.json())
        .then((data) => {
          const urdRateEl = document.getElementById("urdRate")
          const urdPurityEl = document.getElementById("urdPurity")
          if (urdRateEl) urdRateEl.value = data.rate
          if (urdPurityEl) urdPurityEl.value = 92
          calculateUrdValues("purity")
        })
        .catch((error) => console.error("Error fetching gold rate for URD:", error))
    
      const itemName = document.getElementById("urdItemName")
      const grossWt = document.getElementById("urdGrossWeight")
      const lessWt = document.getElementById("urdLessWeight")
      const notes = document.getElementById("urdNotes")
      if (itemName) itemName.value = cart.urdDetails?.itemName || ""
      if (grossWt) grossWt.value = cart.urdDetails?.grossWeight || ""
      if (lessWt) lessWt.value = cart.urdDetails?.lessWeight || ""
      if (notes) notes.value = cart.urdDetails?.notes || ""
      urdImageData = cart.urdDetails?.imageData || null
    
      if (urdImageData) {
        const imgPreview = document.getElementById("urdCapturedImage")
        if (imgPreview) imgPreview.src = urdImageData
        document.getElementById("cameraPreview")?.classList.add("hidden")
        document.getElementById("urdImagePreview")?.classList.remove("hidden")
      } else {
        document.getElementById("cameraPreview")?.classList.remove("hidden")
        document.getElementById("urdImagePreview")?.classList.add("hidden")
        const startCamBtn = document.getElementById("startCameraBtn")
        if (startCamBtn) startCamBtn.classList.remove("hidden")
        const camPrev = document.getElementById("cameraPreview")
        if (camPrev) camPrev.classList.add("hidden")
      }
      calculateUrdWeights()
    }
    
    function closeUrdModal() {
      if (urdModal) urdModal.style.display = "none"
      if (currentStream) {
        currentStream.getTracks().forEach((track) => track.stop())
        currentStream = null
      }
    }
    
    function calculateUrdWeights() {
      const grossWeight = Number.parseFloat(document.getElementById("urdGrossWeight")?.value) || 0
      const lessWeight = Number.parseFloat(document.getElementById("urdLessWeight")?.value) || 0
      const netWeight = Math.max(0, grossWeight - lessWeight)
    
      const netWtEl = document.getElementById("urdNetWeight")
      const netWtDispEl = document.getElementById("urdNetWeightDisplay")
      if (netWtEl) netWtEl.value = netWeight.toFixed(3)
      if (netWtDispEl) netWtDispEl.textContent = netWeight.toFixed(3) + "g"
    
      calculateUrdValues("weight")
    }
    
    function calculateUrdValues(changedField) {
      const netWeight = Number.parseFloat(document.getElementById("urdNetWeight")?.value) || 0
      const purity = Number.parseFloat(document.getElementById("urdPurity")?.value) || 0
      const rate = Number.parseFloat(document.getElementById("urdRate")?.value) || 0
    
      const fineWeight = netWeight * (purity / 100)
      const totalAmount = fineWeight * rate
    
      const fineWtEl = document.getElementById("urdFineWeight")
      const rateDispEl = document.getElementById("urdRateDisplay")
      const totalEl = document.getElementById("urdTotal")
    
      if (fineWtEl) fineWtEl.textContent = fineWeight.toFixed(3) + "g"
      if (rateDispEl) rateDispEl.textContent = formatCurrency(rate)
      if (totalEl) totalEl.textContent = formatCurrency(totalAmount)
    
      currentUrdItem = {
        itemName: document.getElementById("urdItemName")?.value || "",
        grossWeight: Number.parseFloat(document.getElementById("urdGrossWeight")?.value) || 0,
        lessWeight: Number.parseFloat(document.getElementById("urdLessWeight")?.value) || 0,
        netWeight: netWeight,
        purity: purity,
        rate: rate,
        fineWeight: fineWeight,
        totalAmount: totalAmount,
        imageData: urdImageData,
        notes: document.getElementById("urdNotes")?.value || "",
      }
    }
    
    function openUrdCamera() {
      const video = document.getElementById("urdVideo")
      const cameraPreview = document.getElementById("cameraPreview")
      const startCameraBtn = document.getElementById("startCameraBtn")
    
      if (!video || !cameraPreview || !startCameraBtn) return
    
      cameraPreview.classList.remove("hidden")
      video.classList.remove("hidden")
      startCameraBtn.classList.add("hidden")
    
      navigator.mediaDevices
        .getUserMedia({ video: { facingMode: "environment" } })
        .then((stream) => {
          video.srcObject = stream
          currentStream = stream
        })
        .catch((err) => {
          console.error("Error accessing camera:", err)
          showToast("Could not access camera", "error")
          cameraPreview.classList.add("hidden")
          startCameraBtn.classList.remove("hidden")
        })
    }
    
    function captureUrdImage() {
      const video = document.getElementById("urdVideo")
      const cameraPreview = document.getElementById("cameraPreview")
      const imagePreview = document.getElementById("urdImagePreview")
      const capturedImage = document.getElementById("urdCapturedImage")
    
      if (!video || !cameraPreview || !imagePreview || !capturedImage || !video.srcObject) return
    
      const canvas = document.createElement("canvas")
      canvas.width = video.videoWidth
      canvas.height = video.videoHeight
      canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height)
      const imageDataURL = canvas.toDataURL("image/jpeg")
    
      capturedImage.src = imageDataURL
      urdImageData = imageDataURL
    
      cameraPreview.classList.add("hidden")
      video.classList.add("hidden")
      imagePreview.classList.remove("hidden")
    
      if (currentStream) {
        currentStream.getTracks().forEach((track) => track.stop())
        currentStream = null
      }
    }
    
    function retakeUrdImage() {
      const imagePreview = document.getElementById("urdImagePreview")
      const capturedImage = document.getElementById("urdCapturedImage")
      if (imagePreview) imagePreview.classList.add("hidden")
      if (capturedImage) capturedImage.src = "#"
      urdImageData = null
      openUrdCamera()
    }
    
    function saveUrdItem() {
      const itemNameEl = document.getElementById("urdItemName")
      const grossWtEl = document.getElementById("urdGrossWeight")
    
      if (!itemNameEl?.value) {
        showToast("Please enter an item name", "error")
        return
      }
      if (!grossWtEl?.value || Number.parseFloat(grossWtEl.value) <= 0) {
        showToast("Please enter a valid gross weight", "error")
        return
      }
    
      calculateUrdValues("save")
    
      cart.urdDetails = currentUrdItem
      cart.urdAmount = currentUrdItem.totalAmount
    
      const urdAmountInputDisp = document.getElementById("urdAmountInputDisplay")
      if (urdAmountInputDisp) urdAmountInputDisp.value = currentUrdItem.totalAmount.toFixed(2)
    
      updateCartViewSummary()
      closeUrdModal()
      showToast("URD item details saved and amount applied.")
    }
    
    // Fixes for linting errors
    if (typeof Html5Qrcode === "undefined") {
      console.warn("Html5Qrcode is not defined. Ensure the library is properly loaded.")
    }
    
    if (typeof cart === "undefined") {
      console.warn("cart is not defined. Ensure the cart object is properly initialized.")
    }

    function renderPaymentMethods() {
      const container = document.getElementById("paymentMethodsContainer");
      if (!container) return;
      container.innerHTML = "";

      paymentMethods.forEach((payment, idx) => {
        const methodDiv = document.createElement("div");
        if (payment.type === "advance_adjustment") {
          methodDiv.className = "bg-green-50 p-2 rounded-lg border border-green-200 relative mb-2";
          methodDiv.innerHTML = `
            <div class="flex justify-between items-center">
              <div>
                <div class="text-xs font-medium text-green-700">Advance Payment</div>
                <div class="text-[10px] text-gray-600 mt-0.5">${payment.orderNo || "Order"} - ${payment.itemName || "Item"}</div>
              </div>
              <div class="text-xs font-bold text-green-700">${formatCurrency(payment.amount)}</div>
            </div>
            <button onclick="removePaymentMethod('${payment.id}')" 
                    class="absolute top-1 right-1 text-red-400 hover:text-red-600 p-1 rounded-full text-xs leading-none"
                    aria-label="Remove advance payment">
                <i class="fas fa-times-circle"></i>
            </button>`;
        } else {
          methodDiv.className = "bg-gray-50 p-2 rounded-lg border border-gray-200 relative mb-2";
          const removeButtonHTML =
            paymentMethods.filter((pm) => pm.type !== "advance_adjustment").length > 1 ||
            paymentMethods.find((pm) => pm.type === "advance_adjustment")
              ? `<button onclick="removePaymentMethod('${payment.id}')" 
                           class="absolute top-1 right-1 text-red-400 hover:text-red-600 p-1 rounded-full text-xs leading-none"
                           aria-label="Remove payment method">
                       <i class="fas fa-times-circle"></i>
                   </button>`
              : "";

          methodDiv.innerHTML = `
            ${removeButtonHTML}
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Payment Method</label>
                <select onchange="updatePaymentMethod('${payment.id}', 'type', this.value)" 
                        class="w-full h-8 pl-2 pr-8 rounded-md border border-gray-200 text-xs">
                  <option value="cash" ${payment.type === "cash" ? "selected" : ""}>Cash</option>
                  <option value="card" ${payment.type === "card" ? "selected" : ""}>Card</option>
                  <option value="upi" ${payment.type === "upi" ? "selected" : ""}>UPI</option>
                  <option value="bank_transfer" ${payment.type === "bank_transfer" ? "selected" : ""}>Bank Transfer</option>
                  <option value="cheque" ${payment.type === "cheque" ? "selected" : ""}>Cheque</option>
                </select>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Amount</label>
                <input type="number" 
                       value="${payment.amount || 0}" 
                       step="0.01"
                       onchange="updatePaymentMethod('${payment.id}', 'amount', this.value, true);updatePaymentSummary();" 
                       oninput="updatePaymentMethod('${payment.id}', 'amount', this.value, false);updatePaymentSummary();"
                       class="w-full h-8 pl-2 pr-2 rounded-md border border-gray-200 text-xs" 
                       placeholder="Enter amount" />
              </div>
            </div>
            <div class="mt-2 ${payment.type === "cash" || payment.type === "advance_adjustment" ? "hidden" : ""}">
              <label class="text-xs font-medium text-gray-600 mb-1 block">Reference Number</label>
              <input type="text" 
                     value="${payment.reference || ""}" 
                     onchange="updatePaymentMethod('${payment.id}', 'reference', this.value)" 
                     class="w-full h-8 pl-2 pr-2 rounded-md border border-gray-200 text-xs" 
                     placeholder="Enter reference number" />
            </div>`;
        }
        container.appendChild(methodDiv);
      });

      setTimeout(() => {
        updatePaymentSummary();
      }, 5);
    }