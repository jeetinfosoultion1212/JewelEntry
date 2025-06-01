document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    const stockMetalType = document.getElementById('stockMetalType');
    const stockName = document.getElementById('stockName');
    const stockPurity = document.getElementById('stockPurity');
    const customPurity = document.getElementById('customPurity');
    const stockWeight = document.getElementById('stockWeight');
    const stockRate = document.getElementById('stockRate');
    const isPurchase = document.getElementById('isPurchase');
    const purchaseFields = document.getElementById('purchaseFields');
    const supplier = document.getElementById('supplier');
    const buyingPurity = document.getElementById('buyingPurity'); // Note: This field stores a rate, not a purity percentage.
    const paidAmount = document.getElementById('paidAmount');
    const paymentMode = document.getElementById('paymentMode');
    const paymentStatus = document.getElementById('paymentStatus');
    const invoiceNumber = document.getElementById('invoiceNumber');
    const addStockBtn = document.getElementById('addStock');
    // Variable to store the original market rate
    let originalMarketRate = 0;
    // const stockQuantity = document.getElementById('stockQuantity'); // Declared but not directly used for calculations, formData quantity is '1'

    // Material Type Change Handler
    stockMetalType.addEventListener('change', function() {
        stockName.value = '';
        stockPurity.value = '';
        customPurity.value = '';
        customPurity.classList.add('hidden');
        resetStockDetails();
        loadStockNames();
        loadCurrentRate(this.value); // Load rate for new material type
    });

    // Stock Name Input Handler
    stockName.addEventListener('input', function() {
        if(stockMetalType.value && this.value) {
            stockPurity.value = '';
            customPurity.value = '';
            customPurity.classList.add('hidden');
            resetStockDetails();
        }
    });

    // Purity Selection Handler
    stockPurity.addEventListener('change', function() {
        handlePuritySelection();
        calculateBuyingRate(); // Recalculate buying rate when purity changes
    });

    // Custom Purity Input Handler
    customPurity.addEventListener('input', function() {
        if(stockMetalType.value && stockName.value && this.value) {
            updatePurityDetails(stockMetalType.value, this.value);
        }
        calculateBuyingRate(); // Recalculate buying rate when custom purity changes
    });

    // Weight and Rate Change Handlers
    stockWeight.addEventListener('input', calculatePurchaseAmount);
    stockRate.addEventListener('input', function() {
        calculatePurchaseAmount();
        calculateBuyingRate(); // Recalculate buying rate if base rate changes
    });

    // Purchase Checkbox Handler
    isPurchase.addEventListener('change', function() {
        purchaseFields.style.display = this.checked ? 'block' : 'none';
        if(this.checked) {
            loadSuppliers();
            // Store the current market rate when checkbox is checked
            originalMarketRate = parseFloat(stockRate.value) || 0;
            // Switch to buying rate if available
            if(parseFloat(buyingPurity.value) > 0) {
                stockRate.value = buyingPurity.value;
            }
        } else {
            // Revert to market rate when unchecked
            if(originalMarketRate > 0) {
                stockRate.value = originalMarketRate.toFixed(2);
            }
        }
        calculatePurchaseAmount(); // Recalculate amounts when purchase status changes
    });

    // Paid Amount Handler
    paidAmount.addEventListener('input', calculatePurchaseAmount);

    // Buying Purity Handler (Manual Override)
    buyingPurity.addEventListener('input', function() {
        // User is manually entering buying rate, so recalculate purchase amount.
        // No need to call calculateBuyingRate() here as it would overwrite manual input.
        
        // If purchase is checked, update the displayed rate to the manually entered buying rate
        if(isPurchase.checked) {
            stockRate.value = this.value;
        }
        
        calculatePurchaseAmount();
    });


    // Add Stock Button Handler
    addStockBtn.addEventListener('click', handleAddStock);

    // Load stock stats on page load
    loadStockStats();
    loadCurrentRate(stockMetalType.value || 'Gold'); // Load initial rate

    function loadStockStats() {
        fetch('stock_functions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=getStockStats'
        })
        .then(handleFetchResponse)
        .then(data => {
            if(Array.isArray(data)) {
                updateStockStats(data);
            }
        })
        .catch(handleError);
    }

    function calculateAvailablePercentage(available, total) {
      const availNum = parseFloat(available);
      const totalNum = parseFloat(total);

      if (isNaN(availNum) || isNaN(totalNum) || totalNum <= 0 || availNum < 0) {
        return 0;
      }
      const percentage = (availNum / totalNum) * 100;
      return Math.min(Math.max(percentage, 0), 100); // Clamp between 0-100
    }

    function updateStockStats(stats) {
      const container = document.getElementById('stockStatsSummary');
      container.innerHTML = '';

      const purityColors = {
            '99.99': { bg: 'bg-rose-50', text: 'text-rose-700', border: 'border-rose-200' },
            '92.00': { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200' },
            '84.00': { bg: 'bg-orange-50', text: 'text-orange-700', border: 'border-orange-200' },
            '76.00': { bg: 'bg-yellow-50', text: 'text-yellow-700', border: 'border-yellow-200' },
            '59.00': { bg: 'bg-green-50', text: 'text-green-700', border: 'border-green-200' },
            'default': { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' }
      };
      const materialStyles = {
            'Gold': { icon: '<i class="fas fa-coins"></i>', iconColor: 'text-amber-500' },
            'Silver': { icon: '<i class="fas fa-circle"></i>', iconColor: 'text-slate-400' },
            'Platinum': { icon: '<i class="fas fa-dice-d20"></i>', iconColor: 'text-zinc-600' },
            'default': { icon: '<i class="fas fa-circle"></i>', iconColor: 'text-blue-500' }
      };

        stats.forEach(stat => {
            const material = materialStyles[stat.material_type] || materialStyles.default;
            const purityStyle = purityColors[stat.purity] || purityColors.default; // Changed variable name to avoid conflict
            
            const card = document.createElement('div');
            card.className = `stats-card min-w-[120px] rounded-lg border ${purityStyle.border} ${purityStyle.bg}`;
            card.dataset.material = stat.material_type;
            card.dataset.purity = stat.purity;
            card.innerHTML = `
                <div class="p-1.5">
                    <div class="flex items-center gap-1 mb-1">
                        <div class="w-4 h-4 flex items-center justify-center ${material.iconColor} text-[10px]">
                            ${material.icon}
                        </div>
                        <div class="text-[10px] font-medium ${purityStyle.text} tracking-tight line-clamp-1">
                            ${stat.material_type}
                        </div>
                        <div class="ml-auto px-1 py-0.5 text-[12px] font-medium bg-white/60 rounded border ${purityStyle.border} ${purityStyle.text}">
                            ${stat.purity}%
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-1 bg-white/50 rounded p-1 border ${purityStyle.border}">
                        <div class="flex flex-col">
                            <div class="text-[8px] font-medium text-slate-500">Total</div>
                            <div class="text-[10px] font-bold ${purityStyle.text}">${stat.total_stock}g</div>
                        </div>
                        <div class="flex flex-col">
                            <div class="text-[8px] font-medium text-slate-500">Available</div>
                            <div class="text-[10px] font-bold text-green-600">${stat.remaining_stock}g</div>
                        </div>
                    </div>
                    <div class="mt-1 h-1.5 bg-white/40 rounded-full overflow-hidden flex">
                        <div class="h-full bg-green-500 rounded-l-full" 
                             style="width: ${calculateAvailablePercentage(stat.remaining_stock, stat.total_stock)}%">
                        </div>
                        <div class="h-full bg-orange-500 rounded-r-full" 
                             style="width: ${calculateAvailablePercentage(stat.issue_stock, stat.total_stock)}%">
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
        initializeStatsCards(); // Call after cards are added to the DOM
    }

    function loadStockNames() {
        const materialType = stockMetalType.value;
        if(!materialType) return;

        fetch('stock_functions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=searchStockNames&material_type=${encodeURIComponent(materialType)}&search_term=`
        })
        .then(handleFetchResponse)
        .then(data => {
            if(Array.isArray(data)) {
                updateStockNamesList(data);
            }
        })
        .catch(handleError);
    }

    function loadSuppliers() {
        fetch('stock_functions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=getSuppliers'
        })
        .then(handleFetchResponse)
        .then(data => {
            if(Array.isArray(data)) {
                updateSuppliersList(data);
            }
        })
        .catch(handleError);
    }

    function handlePuritySelection() {
      const selectedPurityValue = stockPurity.value;
      if(selectedPurityValue === 'custom') {
          customPurity.classList.remove('hidden');
          customPurity.focus();
          // Don't call updatePurityDetails here, let customPurity input handler do it
      } else {
          customPurity.classList.add('hidden');
          customPurity.value = '';
          if(stockMetalType.value && selectedPurityValue) {
              updatePurityDetails(stockMetalType.value, selectedPurityValue);
          }
      }
    }

    function updatePurityDetails(materialType, purityValue) {
      fetch('stock_functions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=getPurityStockDetails&material_type=${encodeURIComponent(materialType)}&purity=${encodeURIComponent(purityValue)}`
      })
      .then(handleFetchResponse)
      .then(data => {
          if(!data.error) {
              document.getElementById('currentStock').textContent = (data.total_current_stock || 0) + 'g';
              document.getElementById('remainingStock').textContent = (data.total_remaining_stock || 0) + 'g';
              if(data.avg_rate !== undefined && data.avg_rate !== null && parseFloat(data.avg_rate) > 0) { // More robust check
                  stockRate.value = parseFloat(data.avg_rate).toFixed(2);
                  calculatePurchaseAmount();
                  calculateBuyingRate(); // Also update buying rate if stockRate changes
              } else {
                 // Optionally clear or reset stockRate if no avg_rate is returned
                 // stockRate.value = ''; // Or a default rate
                 calculatePurchaseAmount(); // Still recalculate, might be 0 rate
                 calculateBuyingRate();
              }
          }
      })
      .catch(handleError);
    }

    function getCurrentPurityValue() {
        const selectedPurity = stockPurity.value;
        if (selectedPurity === 'custom') {
            return parseFloat(customPurity.value) || 0;
        }
        return parseFloat(selectedPurity) || 0;
    }

    function calculatePurchaseAmount() {
        const weight = parseFloat(stockWeight.value) || 0;
        const marketRate = parseFloat(stockRate.value) || 0;
        const actualPurityPercent = getCurrentPurityValue();
        const itemBuyingRatePerGram = parseFloat(buyingPurity.value) || 0; // This is a rate, not purity
        const paidAmountValue = parseFloat(paidAmount.value) || 0;
        const isPurchaseChecked = isPurchase.checked;
        const currentMetalType = stockMetalType.value;

        let calculatedTotalValue = 0;
        let materialCost = 0;

        if (weight > 0) {
            if (isPurchaseChecked) {
                if (itemBuyingRatePerGram > 0) {
                    materialCost = weight * itemBuyingRatePerGram;
                    // Round to nearest whole number
                    materialCost = Math.round(materialCost);
                }
            } else { // Not a purchase, calculate based on market rate and purity
                if (marketRate > 0 && actualPurityPercent > 0) {
                    let finePurityStandard = 0;
                    if (currentMetalType === 'Gold') {
                        finePurityStandard = 99.99;
                    } else if (currentMetalType === 'Silver') {
                        finePurityStandard = 999.9; // Standard for silver, can be 999
                    } else if (currentMetalType === 'Platinum') {
                        finePurityStandard = 99.95; // Common for platinum, adjust as needed
                    }
                    if (finePurityStandard > 0) {
                        materialCost = weight * marketRate * (actualPurityPercent / finePurityStandard);
                        // Round to nearest whole number for consistency
                        materialCost = Math.round(materialCost);
                    }
                }
            }
        }
        calculatedTotalValue = materialCost;

        document.getElementById('stockMaterialCost').textContent = '₹' + materialCost.toFixed(2);
        document.getElementById('stockTotalPrice').textContent = '₹' + calculatedTotalValue.toFixed(2);

        const balanceContainer = document.getElementById('balanceContainer');
        const balanceAmount = document.getElementById('balanceAmount');

        if (isPurchaseChecked) {
            purchaseFields.style.display = 'block';
            let status = 'Due';

            // Define a small tolerance for floating point comparison (0.01 rupees)
            const EPSILON = 0.01;
            const balance = calculatedTotalValue - paidAmountValue;
            const isEffectivelyZero = Math.abs(balance) < EPSILON;

            if (calculatedTotalValue <= 0) {
                status = (paidAmountValue > 0) ? 'Overpaid' : 'Due';
            } else {
                if (paidAmountValue <= 0) {
                    status = 'Due';
                } else if (paidAmountValue >= calculatedTotalValue || isEffectivelyZero) {
                    // Consider balance effectively zero if within tolerance
                    status = 'Paid';
                } else {
                    status = 'Partial';
                }
            }
            paymentStatus.value = status;

            balanceContainer.style.display = 'block';
            
            // Display zero if balance is effectively zero
            if (isEffectivelyZero) {
                balanceAmount.textContent = '₹0.00';
            } else {
                balanceAmount.textContent = '₹' + balance.toFixed(2);
                if (balance < 0) {
                    balanceAmount.textContent += ' (Credit)';
                }
            }
        } else {
            purchaseFields.style.display = 'none';
            paymentStatus.value = '';
            balanceContainer.style.display = 'none';
        }
    }
    
    function handleAddStock() {
        if(!validateFields()) return;

        const formData = new FormData();
        formData.append('action', 'addStock');
        formData.append('firm_id', '1'); // Consider making firm_id dynamic if needed
        formData.append('material_type', stockMetalType.value);
        formData.append('stock_name', stockName.value);
        formData.append('purity', getCurrentPurityValue().toString());
        formData.append('weight', stockWeight.value);
        formData.append('rate', stockRate.value);
        formData.append('quantity', '1'); // Hardcoded as per original
        formData.append('is_purchase', isPurchase.checked ? '1' : '0');

        if(isPurchase.checked) {
            appendPurchaseData(formData);
        }

        fetch('stock_functions.php', {
            method: 'POST',
            body: formData
        })
        .then(handleFetchResponse)
        .then(data => {
            if(data.success) {
                alert('Stock added successfully!');
                const previousMetalType = stockMetalType.value;
                const previousPurity = getCurrentPurityValue();
                resetStockForm();
                loadStockStats(); // Reload stats which includes summary cards
                // If form resets metal type, restore it to update purity details correctly
                if (previousMetalType && previousPurity) {
                    stockMetalType.value = previousMetalType; // Restore if reset by resetStockForm
                     updatePurityDetails(previousMetalType, previousPurity.toString());
                } else if (stockMetalType.value && getCurrentPurityValue()) {
                    updatePurityDetails(stockMetalType.value, getCurrentPurityValue().toString());
                }
            } else {
                throw new Error(data.error || 'Failed to add stock');
            }
        })
        .catch(handleError);
    }

    function handleFetchResponse(response) {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`Server error: ${response.status} ${text}`);
            });
        }
        
        // Clone the response before reading it to avoid 'body stream already read' error
        const clonedResponse = response.clone();
        
        return clonedResponse.json().catch(error => {
            console.error('Error parsing JSON:', error);
            return response.text().then(text => {
                try {
                    // Try to parse it as JSON anyway
                    return JSON.parse(text);
                } catch (e) {
                    // If it's not valid JSON, return an error object
                    console.error('Invalid JSON response:', text.substring(0, 100));
                    return { success: false, error: 'Invalid server response' };
                }
            });
        });
    }

    function handleError(error) {
        console.error('Operation failed:', error);
        // Check if error.message is already user-friendly from server
        const message = (error.message && error.message.startsWith("Server error:")) || (error.message && error.message.startsWith("Received non-JSON"))
            ? error.message
            : 'Operation failed. Please check console or try again.';
        alert(message);
    }

    function validateFields() {
        if(!stockMetalType.value || !stockName.value) {
            alert('Please select material type and enter stock name');
            return false;
        }
        if(!getCurrentPurityValue()) {
            alert('Please select or enter a valid purity');
            return false;
        }
        if(!stockWeight.value || parseFloat(stockWeight.value) <= 0) {
            alert('Please enter a valid weight');
            return false;
        }
        if(!stockRate.value || parseFloat(stockRate.value) <= 0) {
            // Allow 0 rate for non-purchase if needed, but typically a rate is expected.
            // For now, require a positive rate.
            alert('Please enter a valid rate');
            return false;
        }

        if(isPurchase.checked) {
            if(!supplier.value) {
                alert('Please select a supplier');
                return false;
            }
            if(!buyingPurity.value || parseFloat(buyingPurity.value) <= 0) { // This is buying RATE
                alert('Please enter a valid buying rate per gram');
                return false;
            }
            if(parseFloat(paidAmount.value) > 0 && !paymentMode.value) {
                alert('Please select payment mode if amount is paid');
                return false;
            }
        }
        return true;
    }
    
    function appendPurchaseData(formData) {
        const weight = parseFloat(stockWeight.value) || 0;
        const buyingRatePerGram = parseFloat(buyingPurity.value) || 0; // This is a rate
        const totalAmount = weight * buyingRatePerGram;
        const paid = parseFloat(paidAmount.value) || 0;

        formData.append('supplier_id', supplier.value);
        formData.append('total_amount', totalAmount.toFixed(2));
        formData.append('paid_amount', paid.toFixed(2));
        
        if (paid > 0 && paymentMode.value) {
            formData.append('payment_mode', paymentMode.value);
        } else if (paid > 0 && !paymentMode.value) {
            // This case should be caught by validation, but as a safeguard:
            console.warn("Paid amount entered but no payment mode selected. Payment mode not appended.");
        }
        formData.append('invoice_number', invoiceNumber.value.trim());
    }
    
    function updateStockNamesList(names) {
        const datalistId = 'stockNamesList';
        let datalist = document.getElementById(datalistId);
        if(!datalist) {
            datalist = document.createElement('datalist');
            datalist.id = datalistId;
            document.body.appendChild(datalist); // Append only once
        }
        datalist.innerHTML = ''; // Clear existing options
        names.forEach(name => {
            const option = document.createElement('option');
            option.value = name;
            datalist.appendChild(option);
        });
        stockName.setAttribute('list', datalistId);
    }

    function updateSuppliersList(suppliersData) {
        supplier.innerHTML = '<option value="">Select Supplier</option>';
        suppliersData.forEach(s => {
            supplier.innerHTML += `<option value="${s.id}">${s.name}</option>`;
        });
    }

    function resetStockForm() {
        // Retain material type if user wants to add multiple items of same type
        // stockMetalType.value = ''; 
        stockName.value = '';
        stockPurity.value = '';
        customPurity.value = '';
        customPurity.classList.add('hidden');
        stockWeight.value = '';
        // stockRate.value = ''; // Don't reset rate, it might be based on material type
        isPurchase.checked = false;
        purchaseFields.style.display = 'none';
        buyingPurity.value = '';
        supplier.value = '';
        invoiceNumber.value = '';
        paidAmount.value = '';
        paymentMode.value = '';
        paymentStatus.value = '';
        resetStockDetails();
        // Recalculate amounts, which should effectively zero out based on empty weight
        calculatePurchaseAmount(); 
        // Recalculate buying rate if rate and purity are still set
        if (stockRate.value && getCurrentPurityValue()) {
            calculateBuyingRate();
        } else {
            buyingPurity.value = ''; // Clear it if cannot be calculated
        }
    }

    function resetStockDetails() {
        document.getElementById('currentStock').textContent = '0.00g';
        document.getElementById('remainingStock').textContent = '0.00g';
        document.getElementById('stockMaterialCost').textContent = '₹0.00';
        document.getElementById('stockTotalPrice').textContent = '₹0.00';
        const balanceContainer = document.getElementById('balanceContainer');
        if (balanceContainer) balanceContainer.style.display = 'none';
    }

    // Functions previously global, now inside DOMContentLoaded for scope
    function initializeStatsCards() {
        const statsCards = document.querySelectorAll('.stats-card');
        statsCards.forEach(card => {
            // Remove old listener before adding new one to prevent duplicates if called multiple times
            card.removeEventListener('click', handleStatsCardClick);
            card.addEventListener('click', handleStatsCardClick);
        });
    }
    
    function handleStatsCardClick() { // Wrapper to access dataset
        const materialType = this.dataset.material;
        const purityValue = this.dataset.purity;
        showPurityHistory(materialType, purityValue);
    }

    function showPurityHistory(materialType, purityValue) {
        fetch('stock_functions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=getPurityHistory&material_type=${encodeURIComponent(materialType)}&purity=${encodeURIComponent(purityValue)}`
        })
        .then(handleFetchResponse) // Use the improved fetch handler
        .then(data => {
            if (!data.error) { // Assuming data.error for errors from this endpoint
                showHistoryModal(data, materialType, purityValue);
            } else {
                throw new Error(data.error || "Failed to load purity history.");
            }
        })
        .catch(handleError);
    }

    function showHistoryModal(data, materialType, purityValue) {
        const summary = {
            current_stock: parseFloat(data.summary?.current_stock) || 0,
            remaining_stock: parseFloat(data.summary?.remaining_stock) || 0,
            avg_rate: parseFloat(data.summary?.avg_rate) || 0,
            total_purchases: parseInt(data.summary?.total_purchases) || 0,
            total_transactions: parseInt(data.summary?.total_transactions) || 0
        };

        const modalId = 'purityHistoryModal';
        let modal = document.getElementById(modalId);
        if (modal) modal.remove(); // Remove existing modal

        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'fixed inset-0 bg-black/30 backdrop-blur-sm flex items-center justify-center z-50 p-4';
        modal.innerHTML = `
            <div class="bg-white/95 rounded-xl max-w-2xl w-full max-h-[85vh] overflow-hidden shadow-xl border border-gray-100 flex flex-col">
                <div class="bg-gradient-to-r from-slate-50 to-blue-50 px-3 py-2 flex justify-between items-center border-b border-blue-100">
                    <div class="flex items-center gap-2">
                        <div class="bg-blue-100 text-blue-600 p-1 rounded-md">
                            <i class="fas fa-coins text-xs"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-700">${materialType}</h3>
                            <span class="text-xs text-blue-600 font-medium">${purityValue}% Purity</span>
                        </div>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600" onclick="document.getElementById('${modalId}').remove()">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-2 p-2 bg-gray-50/50">
                    <div class="bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-emerald-400"></div><div class="text-[10px] font-medium text-gray-400">Current Stock</div></div>
                        <div class="mt-1"><span class="text-sm font-bold text-gray-700">${summary.current_stock.toFixed(3)}g</span><span class="text-[10px] text-emerald-500 block">Available: ${summary.remaining_stock.toFixed(3)}g</span></div>
                    </div>
                    <div class="bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-400"></div><div class="text-[10px] font-medium text-gray-400">Average Rate</div></div>
                        <div class="mt-1"><span class="text-sm font-bold text-gray-700">₹${summary.avg_rate.toFixed(2)}</span><span class="text-[10px] text-blue-500 block">${summary.total_purchases} purchases</span></div>
                    </div>
                    <div class="bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-violet-400"></div><div class="text-[10px] font-medium text-gray-400">Transactions</div></div>
                        <div class="mt-1"><span class="text-sm font-bold text-gray-700">${summary.total_transactions}</span><span class="text-[10px] text-violet-500 block">Total activities</span></div>
                    </div>
                </div>
                <div class="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50 flex-grow">
                    <table class="w-full border-collapse">
                        <thead class="sticky top-0 bg-gray-50/95 backdrop-blur-sm z-10">
                            <tr class="text-[10px] font-medium text-gray-500">
                                <th class="px-2 py-1.5 text-left">Date</th><th class="px-2 py-1.5 text-left">Stock Name</th><th class="px-2 py-1.5 text-left">Type</th>
                                <th class="px-2 py-1.5 text-right">Quantity</th><th class="px-2 py-1.5 text-right">Rate</th><th class="px-2 py-1.5 text-right">Amount</th>
                                <th class="px-2 py-1.5 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            ${(data.transactions || []).map(t => {
                                const quantity = parseFloat(t.quantity) || 0;
                                const rate = parseFloat(t.rate) || 0;
                                const amount = parseFloat(t.amount) || 0;
                                return `
                                    <tr class="text-[11px] hover:bg-blue-50/50">
                                        <td class="px-2 py-1.5 text-gray-600">${new Date(t.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric'})}</td>
                                        <td class="px-2 py-1.5 font-medium text-gray-700">${t.stock_name}</td>
                                        <td class="px-2 py-1.5"><span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium ${t.type === 'IN' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}">${t.type}</span></td>
                                        <td class="px-2 py-1.5 text-right font-medium">${quantity.toFixed(3)}g</td>
                                        <td class="px-2 py-1.5 text-right">₹${rate > 0 ? rate.toFixed(2) : '-'}</td>
                                        <td class="px-2 py-1.5 text-right font-medium">₹${amount > 0 ? amount.toFixed(2) : '-'}</td>
                                        <td class="px-2 py-1.5"><span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium ${getStatusStyle(t.payment_status)}">${t.payment_status || '-'}</span></td>
                                    </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                    ${(data.transactions || []).length === 0 ? '<p class="text-center text-gray-500 py-4">No transactions found.</p>' : ''}
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function getStatusStyle(status) {
        switch (status) {
            case 'Paid': return 'bg-emerald-50 text-emerald-600';
            case 'Partial': return 'bg-amber-50 text-amber-600';
            case 'Due': return 'bg-rose-50 text-rose-600';
            case 'Overpaid': return 'bg-blue-50 text-blue-600';
            default: return 'bg-gray-50 text-gray-600';
        }
    }

    function loadCurrentRate(materialTypeValue = 'Gold') {
        if (!materialTypeValue) return; // Don't fetch if no material type
        fetch('stock_functions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=getCurrentRate&material_type=${encodeURIComponent(materialTypeValue)}`
        })
        .then(handleFetchResponse)
        .then(data => {
            if(data.rate !== undefined && data.rate !== null) { // Check for explicit rate
                const newRate = parseFloat(data.rate).toFixed(2);
                stockRate.value = newRate;
                // Store the market rate
                originalMarketRate = parseFloat(newRate);
                // After rate is loaded, recalculate dependent values
                calculateBuyingRate();
                // If purchase is checked, use buying rate instead of market rate
                if(isPurchase.checked && parseFloat(buyingPurity.value) > 0) {
                    stockRate.value = buyingPurity.value;
                }
                calculatePurchaseAmount();
            } else {
                // If no rate is returned, you might want to clear it or set a default
                // stockRate.value = ''; // Or keep existing if preferred
                // console.warn(`No rate returned for material: ${materialTypeValue}`);
            }
        })
        .catch(error => {
            handleError(error);
            // stockRate.value = ''; // Clear rate on error or keep old one?
        });
    }

    function calculateBuyingRate() {
      // Always use the original market rate for calculation, not the potentially modified stockRate.value
      const baseRate = originalMarketRate || parseFloat(stockRate.value) || 0;
      const purityPercent = getCurrentPurityValue();
      const materialTypeValue = stockMetalType.value;

      if(baseRate > 0 && purityPercent > 0 && materialTypeValue) {
          let finePurityStandard = 0;
          if (materialTypeValue === 'Gold') {
              finePurityStandard = 99.99;
          } else if (materialTypeValue === 'Silver') {
              finePurityStandard = 999.9; // Or 999, ensure consistency
          } else if (materialTypeValue === 'Platinum') {
              finePurityStandard = 99.95; // Example for platinum
          }
          // Add other material types as needed

          if (finePurityStandard > 0) {
              const calculatedBuyingRate = baseRate * (purityPercent / finePurityStandard);
              buyingPurity.value = calculatedBuyingRate.toFixed(2);
              
              // If purchase is checked, update the displayed rate to buying rate
              if(isPurchase.checked) {
                  stockRate.value = calculatedBuyingRate.toFixed(2);
              }
          } else {
               buyingPurity.value = ''; // Clear if standard is not defined for the material
          }
      } else {
          buyingPurity.value = ''; // Clear if inputs are invalid
      }
    }

    // Initialize form elements
    stockName.setAttribute('autocomplete', 'off');
    // stockName list is set dynamically by updateStockNamesList

    // Initial calculation calls after DOM is ready and elements are available
    calculatePurchaseAmount();
    calculateBuyingRate();

    // Supplier Modal Functionality
    const addSupplierBtn = document.getElementById('addSupplierBtn');
    const addSupplierModal = document.getElementById('addSupplierModal');
    const addSupplierForm = document.getElementById('addSupplierForm');
    const closeModalBtn = addSupplierModal.querySelector('.close');
    
    // Show modal when add supplier button is clicked
    addSupplierBtn.addEventListener('click', function(e) {
        e.preventDefault();
        addSupplierModal.style.display = 'block';
        addSupplierForm.reset();
    });
    
    // Close modal when X is clicked
    closeModalBtn.addEventListener('click', function() {
        addSupplierModal.style.display = 'none';
    });
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(e) {
        if (e.target === addSupplierModal) {
            addSupplierModal.style.display = 'none';
        }
    });
    
    // Handle supplier form submission
    addSupplierForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form - ensure supplier name is provided
        const supplierNameInput = document.getElementById('supplierName');
        if (!supplierNameInput.value.trim()) {
            showToast('Supplier name is required', false);
            supplierNameInput.focus();
            return;
        }
        
        // Disable the submit button to prevent multiple submissions
        const submitButton = addSupplierForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
        
        // Collect form data
        const formData = new FormData(addSupplierForm);
        formData.append('action', 'addSupplier');
        
        // Convert FormData to URL-encoded string
        const urlEncodedData = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            urlEncodedData.append(key, value);
        }
        
        fetch('stock_functions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: urlEncodedData.toString()
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server error: ${response.status} ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            // Re-enable the submit button
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Save Supplier';
            
            if (data.success) {
                // Hide modal
                addSupplierModal.style.display = 'none';
                
                // Reset the form
                addSupplierForm.reset();
                
                // Show success toast
                showToast(data.message);
                
                // Add new supplier to the list and select it
                if (data.supplier) {
                    // Reload suppliers list to ensure proper sorting
                    loadSuppliers();
                    
                    // Select the newly added supplier
                    setTimeout(() => {
                        supplier.value = data.supplier.id;
                    }, 100);
                }
            } else {
                // Show error toast
                showToast(data.error || 'Failed to add supplier', false);
            }
        })
        .catch(error => {
            // Re-enable the submit button
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Save Supplier';
            
            console.error('Error adding supplier:', error);
            showToast('Error: ' + error.message, false);
        });
    });
    
    // Show toast notification function
    function showToast(message, isSuccess = true) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        
        // Set message and color based on success/error
        toastMessage.textContent = message;
        
        // Remove existing color classes
        if (toast.classList.contains('bg-green-500')) {
            toast.classList.remove('bg-green-500');
        }
        if (toast.classList.contains('bg-red-500')) {
            toast.classList.remove('bg-red-500');
        }
        
        // Add appropriate color class
        toast.classList.add(isSuccess ? 'bg-green-500' : 'bg-red-500');
        
        // Show the toast
        toast.classList.remove('translate-x-full');
        
        // Hide after 3 seconds
        setTimeout(() => {
            toast.classList.add('translate-x-full');
        }, 3000);
    }
});
    