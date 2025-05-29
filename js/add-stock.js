
    // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Get all tab buttons and content sections
      const tabButtons = document.querySelectorAll('.tab-btn');
      const tabContents = document.querySelectorAll('.tab-content');

      // Function to switch tabs
      function switchTab(tabId) {
        // Hide all tab contents
        tabContents.forEach(content => {
          content.classList.remove('active');
        });

        // Remove active class from all tab buttons
        tabButtons.forEach(button => {
          button.classList.remove('active');
        });

        // Show selected tab content
        const selectedTab = document.getElementById(tabId);
        if (selectedTab) {
          selectedTab.classList.add('active');
        }

        // Add active class to selected tab button
        const selectedButton = document.querySelector(`[data-tab="${tabId}"]`);
        if (selectedButton) {
          selectedButton.classList.add('active');
        }
        
        // Load tab-specific data
        if (tabId === 'items-list') {
          loadJewelryItems();
        } else if (tabId === 'entry-form') {
          initJewelryTypeSuggestions();
        } else if (tabId === 'add-stock') {
          loadStockStats();
        }
      }

      // Add click event listeners to tab buttons
      tabButtons.forEach(button => {
        button.addEventListener('click', () => {
          const tabId = button.getAttribute('data-tab');
          switchTab(tabId);
        });
      });

      // Initialize with add-stock tab active
      switchTab('add-stock');
      
      // Make switchTab function globally available
      window.switchTab = switchTab;
    });

    // Collapsible sections
    function toggleSection(sectionId) {
      const content = document.getElementById(sectionId);
      const toggle = content.previousElementSibling;
      
      content.classList.toggle('expanded');
      toggle.classList.toggle('expanded');
    }
    
    // Calculate net weight
    function calculateNetWeight() {
      const grossWeight = parseFloat(document.getElementById('grossWeight').value) || 0;
      const lessWeight = parseFloat(document.getElementById('lessWeight').value) || 0;
      const netWeight = grossWeight - lessWeight;
      
      document.getElementById('netWeight').value = netWeight > 0 ? netWeight.toFixed(3) : 0;
    }
    
    // Toggle stone fields based on stone type selection
    function toggleStoneFields() {
      const stoneType = document.getElementById('stoneType').value;
      const stoneFields = ['stoneWeight', 'stoneQuality', 'stonePrice'];
      
      stoneFields.forEach(field => {
        const element = document.getElementById(field);
        if (stoneType === 'None') {
          element.disabled = true;
          element.value = '';
        } else {
          element.disabled = false;
        }
      });
      
      // Expand stone section if a stone type is selected
      if (stoneType !== 'None') {
        document.getElementById('stoneSection').classList.add('expanded');
        document.querySelector('.collapsible-toggle').classList.add('expanded');
      }
    }
    
    // Source type change handler
    function handleSourceTypeChange() {
      const sourceType = document.getElementById('sourceType').value;
      const sourceIdInput = document.getElementById('sourceId');
      const sourceIdSuggestions = document.getElementById('sourceIdSuggestions');
      
      // Clear previous values
      sourceIdInput.value = '';
      sourceIdSuggestions.innerHTML = '';
      sourceIdSuggestions.classList.add('hidden');
      
      // Set placeholder based on source type
      if (sourceType === 'Purchase') {
        sourceIdInput.placeholder = "Type supplier ID or purity...";
        fetchPurchaseSources();
      } else if (sourceType === 'Manufacture') {
        sourceIdInput.placeholder = "Type order ID or purity...";
        fetchManufactureSources();
      } else {
        sourceIdInput.placeholder = "Type source ID...";
      }
    }
    
    // Fetch purchase sources
    function fetchPurchaseSources() {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getPurchaseSources'
      })
      .then(response => response.json())
      .then(data => {
        if (Array.isArray(data)) {
          setupSourceSuggestions(data);
        }
      })
      .catch(error => console.error('Error fetching purchase sources:', error));
    }
    
    // Fetch manufacture sources
    function fetchManufactureSources() {
      fetch('stock_functions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getManufactureSources'
      })
      .then(response => response.json())
      .then(data => {
        if (Array.isArray(data)) {
          setupSourceSuggestions(data);
        }
      })
      .catch(error => console.error('Error fetching manufacture sources:', error));
    }
    
    // Setup source suggestions
    function setupSourceSuggestions(sources) {
      const sourceIdInput = document.getElementById('sourceId');
      const sourceIdSuggestions = document.getElementById('sourceIdSuggestions');
      
      sourceIdInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        if (searchTerm.length < 2) {
          sourceIdSuggestions.classList.add('hidden');
          return;
        }
        
        const filteredSources = sources.filter(source => {
          return source.id.toLowerCase().includes(searchTerm) || 
                 source.name.toLowerCase().includes(searchTerm) ||
                 source.purity.toString().includes(searchTerm);
        });
        
        if (filteredSources.length > 0) {
          renderSourceSuggestions(filteredSources);
          sourceIdSuggestions.classList.remove('hidden');
        } else {
          sourceIdSuggestions.classList.add('hidden');
        }
      });
    }
    
    // Render source suggestions
    function renderSourceSuggestions(sources) {
      const sourceIdSuggestions = document.getElementById('sourceIdSuggestions');
      sourceIdSuggestions.innerHTML = '';
      
      sources.forEach(source => {
        const div = document.createElement('div');
        div.className = 'p-2 hover:bg-gray-100 cursor-pointer';
        div.innerHTML = `
          <div class="font-medium">${source.id} - ${source.name}</div>
          <div class="text-xs text-gray-500">Purity: ${source.purity}% | Remaining: ${source.remaining}g</div>
        `;
        
        div.addEventListener('click', function() {
          document.getElementById('sourceId').value = source.id;
          document.getElementById('purity').value = source.purity;
          sourceIdSuggestions.classList.add('hidden');
        });
        
        sourceIdSuggestions.appendChild(div);
      });
    }
   
    
    
  
    // Stock Management Functions
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
        const buyingPurity = document.getElementById('buyingPurity');
        const paidAmount = document.getElementById('paidAmount');
        const paymentMode = document.getElementById('paymentMode');
        const paymentStatus = document.getElementById('paymentStatus');
        const invoiceNumber = document.getElementById('invoiceNumber');
        const addStockBtn = document.getElementById('addStock');
        const stockQuantity = document.getElementById('stockQuantity');

        // Material Type Change Handler
        stockMetalType.addEventListener('change', function() {
            stockName.value = '';
            stockPurity.value = '';
            customPurity.value = '';
            customPurity.classList.add('hidden');
            resetStockDetails();
            loadStockNames();
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
        });

        // Custom Purity Input Handler
        customPurity.addEventListener('input', function() {
            if(stockMetalType.value && stockName.value && this.value) {
                updatePurityDetails(stockMetalType.value, this.value);
            }
        });

        // Weight and Rate Change Handlers
        stockWeight.addEventListener('input', calculatePurchaseAmount);
        stockRate.addEventListener('input', calculatePurchaseAmount);

        // Purchase Checkbox Handler
        isPurchase.addEventListener('change', function() {
            purchaseFields.style.display = this.checked ? 'block' : 'none';
            if(this.checked) {
                loadSuppliers();
            }
        });

        // Paid Amount Handler
        paidAmount.addEventListener('input', calculatePurchaseAmount);

        // Buying Purity Handler
        buyingPurity.addEventListener('input', calculatePurchaseAmount);

        // Add Stock Button Handler
        addStockBtn.addEventListener('click', handleAddStock);

        // Load stock stats on page load and after successful stock addition
        loadStockStats();

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
          if (!total || !available || total <= 0) return 0;
          const percentage = (parseFloat(available) / parseFloat(total)) * 100;
          return Math.min(Math.max(percentage, 0), 100); // Clamp between 0-100
        }

        function updateStockStats(stats) {
          const container = document.getElementById('stockStatsSummary');
          container.innerHTML = '';

          const purityColors = {
        '99.99': {
            bg: 'bg-rose-50',
            text: 'text-rose-700',
            border: 'border-rose-200'
        },
        '92.00': {
            bg: 'bg-amber-50',
            text: 'text-amber-700',
            border: 'border-amber-200'
        },
        '84.00': {
            bg: 'bg-orange-50',
            text: 'text-orange-700',
            border: 'border-orange-200'
        },
        '76.00': {
            bg: 'bg-yellow-50',
            text: 'text-yellow-700',
            border: 'border-yellow-200'
        },
        '59.00': {
            bg: 'bg-green-50',
            text: 'text-green-700',
            border: 'border-green-200'
        },
        'default': {
            bg: 'bg-blue-50',
            text: 'text-blue-700',
            border: 'border-blue-200'
        }
    };

    // Material type specific styles
    const materialStyles = {
        'Gold': {
            icon: '<i class="fas fa-coins"></i>',
            iconColor: 'text-amber-500'
        },
        'Silver': {
            icon: '<i class="fas fa-circle"></i>',
            iconColor: 'text-slate-400'
        },
        'Platinum': {
            icon: '<i class="fas fa-dice-d20"></i>',
            iconColor: 'text-zinc-600'
        },
        'default': {
            icon: '<i class="fas fa-circle"></i>',
            iconColor: 'text-blue-500'
        }
    };

    stats.forEach(stat => {
        const material = materialStyles[stat.material_type] || materialStyles.default;
        const purity = purityColors[stat.purity] || purityColors.default;
        
        const card = document.createElement('div');
        card.className = `stats-card min-w-[120px] rounded-lg border ${purity.border} ${purity.bg}`;
        card.dataset.material = stat.material_type;
        card.dataset.purity = stat.purity;
        card.innerHTML = `
            <div class="p-1.5">
                <!-- Header -->
                <div class="flex items-center gap-1 mb-1">
                    <div class="w-4 h-4 flex items-center justify-center ${material.iconColor} text-[10px]">
                        ${material.icon}
                    </div>
                    <div class="text-[10px] font-medium ${purity.text} tracking-tight line-clamp-1">
                        ${stat.material_type}
                    </div>
                    <div class="ml-auto px-1 py-0.5 text-[12px] font-medium bg-white/60 rounded border ${purity.border} ${purity.text}">
                        ${stat.purity}%
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-1 bg-white/50 rounded p-1 border ${purity.border}">
                    <div class="flex flex-col">
                        <div class="text-[8px] font-medium text-slate-500">Total</div>
                        <div class="text-[10px] font-bold ${purity.text}">${stat.total_stock}g</div>
                    </div>
                    <div class="flex flex-col">
                        <div class="text-[8px] font-medium text-slate-500">Available</div>
                        <div class="text-[10px] font-bold text-green-600">${stat.remaining_stock}g</div>
                    </div>
                  
                </div>

                <!-- Progress Bar -->
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
        }

        // Helper Functions
        function loadStockNames() {
            const materialType = stockMetalType.value;
            if(!materialType) return;

            fetch('stock_functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
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
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
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
            if(stockPurity.value === 'custom') {
                customPurity.classList.remove('hidden');
                customPurity.focus();
            } else {
                customPurity.classList.add('hidden');
                customPurity.value = '';
                if(stockMetalType.value && stockPurity.value) {
                    updatePurityDetails(stockMetalType.value, stockPurity.value);
                }
            }
        }

        function updatePurityDetails(materialType, purity) {
            fetch('stock_functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=getPurityStockDetails&material_type=${encodeURIComponent(materialType)}&purity=${encodeURIComponent(purity)}`
            })
            .then(handleFetchResponse)
            .then(data => {
                if(!data.error) {
                    document.getElementById('currentStock').textContent = (data.total_current_stock || 0) + 'g';
                    document.getElementById('remainingStock').textContent = (data.total_remaining_stock || 0) + 'g';
                    if(data.avg_rate > 0) {
                        stockRate.value = data.avg_rate;
                        calculatePurchaseAmount();
                    }
                }
            })
            .catch(handleError);
        }

        function calculatePurchaseAmount() {
            const weight = parseFloat(stockWeight.value) || 0;
            const rate = parseFloat(stockRate.value) || 0;
            const buyingPurityValue = parseFloat(buyingPurity.value) || 0;
            const paidAmountValue = parseFloat(paidAmount.value) || 0;

            if(weight > 0 && rate > 0 && buyingPurityValue > 0) {
                // Calculate total based on weight only, not quantity
                const total = weight * rate * (buyingPurityValue / 99.99);
                
                document.getElementById('stockMaterialCost').textContent = '₹' + total.toFixed(2);
                document.getElementById('stockTotalPrice').textContent = '₹' + total.toFixed(2);

                // Update payment status
                let status = 'Due';
                if(paidAmountValue > 0) {
                    status = paidAmountValue >= total ? 'Paid' : 'Partial';
                }
                paymentStatus.value = status;

                if(isPurchase.checked) {
                    const balance = total - paidAmountValue;
                    document.getElementById('balanceContainer').style.display = 'flex';
                    document.getElementById('balanceAmount').textContent = '₹' + balance.toFixed(2);
                }
            }
        }

        function handleAddStock() {
            if(!validateFields()) return;

            const formData = new FormData();
            formData.append('action', 'addStock');
            formData.append('firm_id', '1');
            formData.append('material_type', stockMetalType.value);
            formData.append('stock_name', stockName.value);
            formData.append('purity', stockPurity.value === 'custom' ? customPurity.value : stockPurity.value);
            formData.append('weight', stockWeight.value);
            formData.append('rate', stockRate.value);
            // Quantity is kept as 1 since we're not using it for price calculation
            formData.append('quantity', '1');
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
                    resetStockForm();
                    loadStockStats();
                    const purity = stockPurity.value === 'custom' ? customPurity.value : stockPurity.value;
                    if(purity) {
                        updatePurityDetails(stockMetalType.value, purity);
                    }
                } else {
                    throw new Error(data.error || 'Failed to add stock');
                }
            })
            .catch(handleError);
        }

        // Utility Functions
        function handleFetchResponse(response) {
            if(!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server error: ${text}`);
                });
            }
            return response.json().then(data => {
                if(!data) {
                    throw new Error('Empty response from server');
                }
                return data;
            });
        }

        function handleError(error) {
            console.error('Operation failed:', error);
            alert(error.message || 'Operation failed. Please try again.');
        }

        function validateFields() {
            if(!stockMetalType.value || !stockName.value) {
                alert('Please select material type and enter stock name');
                return false;
            }

            const purity = stockPurity.value === 'custom' ? customPurity.value : stockPurity.value;
            if(!purity) {
                alert('Please select or enter purity');
                return false;
            }

            if(!stockWeight.value || !stockRate.value) {
                alert('Please enter weight and rate');
                return false;
            }

            if(isPurchase.checked) {
                if(!supplier.value) {
                    alert('Please select a supplier');
                    return false;
                }
                if(!buyingPurity.value) {
                    alert('Please enter buying purity');
                    return false;
                }
                if(parseFloat(paidAmount.value) > 0 && !paymentMode.value) {
                    alert('Please select payment mode');
                    return false;
                }
            }

            return true;
        }

        function appendPurchaseData(formData) {
            const weight = parseFloat(stockWeight.value) || 0;
            const rate = parseFloat(stockRate.value) || 0;
            const buyingPurityValue = parseFloat(buyingPurity.value) || 0;
            const total = weight * rate * (buyingPurityValue / 99.99);
            const paid = parseFloat(paidAmount.value) || 0;

            formData.append('supplier_id', supplier.value);
            formData.append('total_amount', total.toFixed(2));
            formData.append('paid_amount', paid.toFixed(2));
            if(paid > 0) {
                formData.append('payment_mode', paymentMode.value);
            }
            formData.append('invoice_number', invoiceNumber.value);
        }

        function updateStockNamesList(names) {
            const datalist = document.createElement('datalist');
            datalist.id = 'stockNamesList';
            names.forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                datalist.appendChild(option);
            });
            
            const existingDatalist = document.getElementById('stockNamesList');
            if(existingDatalist) {
                existingDatalist.remove();
            }
            
            document.body.appendChild(datalist);
            stockName.setAttribute('list', 'stockNamesList');
        }

        function updateSuppliersList(suppliers) {
            supplier.innerHTML = '<option value="">Select Supplier</option>';
            suppliers.forEach(s => {
                supplier.innerHTML += `<option value="${s.id}">${s.name}</option>`;
            });
        }

        function resetStockForm() {
            stockName.value = '';
            stockPurity.value = '';
            customPurity.value = '';
            customPurity.classList.add('hidden');
            stockWeight.value = '';
            stockRate.value = '';
                       isPurchase.checked = false;
            purchaseFields.style.display = 'none';
            buyingPurity.value = '';
            supplier.value = '';
            invoiceNumber.value = '';
            paidAmount.value = '';
            paymentMode.value = '';
            paymentStatus.value = '';
            resetStockDetails();
        }

        function resetStockDetails() {
            document.getElementById('currentStock').textContent = '0.00g';
            document.getElementById('remainingStock').textContent = '0.00g';
            document.getElementById('stockMaterialCost').textContent = '₹0.00';
            document.getElementById('stockTotalPrice').textContent = '₹0.00';
            document.getElementById('balanceContainer').style.display = 'none';
        }

        // Initialize
        stockName.setAttribute('autocomplete', 'off');
        stockName.setAttribute('list', 'stockNamesList');
    });

    // Initialize image upload and cropping functionality
    document.addEventListener('DOMContentLoaded', function() {
      const productImages = document.getElementById('productImages');
      const imagePreview = document.getElementById('imagePreview');
      const captureBtn = document.getElementById('captureBtn');
      const cropBtn = document.getElementById('cropBtn');
      const cropperModal = document.getElementById('cropperModal');
      const cropperImage = document.getElementById('cropperImage');
      const applyCropBtn = document.getElementById('applyCrop');
      const cancelCropBtn = document.getElementById('cancelCrop');
      
      let cropper;
      let currentImageIndex = -1;
      
      // Handle file selection
      productImages.addEventListener('change', function(e) {
        const files = e.target.files;
        
        if (files.length > 0) {
          Array.from(files).forEach(file => {
            if (file.type.match('image.*')) {
              const reader = new FileReader();
              
              reader.onload = function(e) {
                addImageToPreview(e.target.result);
              };
              
              reader.readAsDataURL(file);
            }
          });
        }
      });
      
   
      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        if (event.target === cropperModal) {
          if (cropper) {
            cropper.destroy();
            cropper = null;
          }
          cropperModal.style.display = 'none';
        }
      });
    });

    function initializeStatsCards() {
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach(card => {
        card.addEventListener('click', function() {
            const materialType = this.dataset.material;
            const purity = this.dataset.purity;
            showPurityHistory(materialType, purity);
        });
    });
}

function showPurityHistory(materialType, purity) {
    fetch('stock_functions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=getPurityHistory&material_type=${encodeURIComponent(materialType)}&purity=${encodeURIComponent(purity)}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.error) {
            showHistoryModal(data, materialType, purity);
        }
    })
    .catch(error => console.error('Error:', error));
}

function showHistoryModal(data, materialType, purity) {
    // Parse summary values with fallbacks
    const summary = {
        current_stock: parseFloat(data.summary.current_stock) || 0,
        remaining_stock: parseFloat(data.summary.remaining_stock) || 0,
        avg_rate: parseFloat(data.summary.avg_rate) || 0,
        total_purchases: parseInt(data.summary.total_purchases) || 0,
        total_transactions: parseInt(data.summary.total_transactions) || 0
    };

    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/30 backdrop-blur-sm flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-white/95 rounded-xl max-w-2xl w-full max-h-[85vh] overflow-hidden shadow-xl border border-gray-100">
            <!-- Compact Header -->
            <div class="bg-gradient-to-r from-slate-50 to-blue-50 px-3 py-2 flex justify-between items-center border-b border-blue-100">
                <div class="flex items-center gap-2">
                    <div class="bg-blue-100 text-blue-600 p-1 rounded-md">
                        <i class="fas fa-coins text-xs"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-700">${materialType}</h3>
                        <span class="text-xs text-blue-600 font-medium">${purity}% Purity</span>
                    </div>
                </div>
                <button class="text-gray-400 hover:text-gray-600" onclick="this.closest('.fixed').remove()">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Compact Stats Grid -->
            <div class="grid grid-cols-3 gap-2 p-2 bg-gray-50/50">
                <div class="bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
                        <div class="text-[10px] font-medium text-gray-400">Current Stock</div>
                    </div>
                    <div class="mt-1">
                        <span class="text-sm font-bold text-gray-700">${summary.current_stock.toFixed(3)}g</span>
                        <span class="text-[10px] text-emerald-500 block">Available: ${summary.remaining_stock.toFixed(3)}g</span>
                    </div>
                </div>
                <div class="bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                        <div class="text-[10px] font-medium text-gray-400">Average Rate</div>
                    </div>
                    <div class="mt-1">
                        <span class="text-sm font-bold text-gray-700">₹${summary.avg_rate.toFixed(2)}</span>
                        <span class="text-[10px] text-blue-500 block">${summary.total_purchases} purchases</span>
                    </div>
                </div>
                <div class="bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full bg-violet-400"></div>
                        <div class="text-[10px] font-medium text-gray-400">Transactions</div>
                    </div>
                    <div class="mt-1">
                        <span class="text-sm font-bold text-gray-700">${summary.total_transactions}</span>
                        <span class="text-[10px] text-violet-500 block">Total activities</span>
                    </div>
                </div>
            </div>

            <!-- Scrollable Transactions List -->
            <div class="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-gray-50" style="max-height: calc(85vh - 140px);">
                <table class="w-full border-collapse">
                    <thead class="sticky top-0 bg-gray-50/95 backdrop-blur-sm">
                        <tr class="text-[10px] font-medium text-gray-500">
                            <th class="px-2 py-1.5 text-left">Date</th>
                            <th class="px-2 py-1.5 text-left">Stock Name</th>
                            <th class="px-2 py-1.5 text-left">Type</th>
                            <th class="px-2 py-1.5 text-right">Quantity</th>
                            <th class="px-2 py-1.5 text-right">Rate</th>
                            <th class="px-2 py-1.5 text-right">Amount</th>
                            <th class="px-2 py-1.5 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        ${data.transactions.map(t => {
                            const quantity = parseFloat(t.quantity) || 0;
                            const rate = parseFloat(t.rate) || 0;
                            const amount = parseFloat(t.amount) || 0;
                            
                            return `
                                <tr class="text-[11px] hover:bg-blue-50/50">
                                    <td class="px-2 py-1.5 text-gray-600">${new Date(t.date).toLocaleDateString('en-IN')}</td>
                                    <td class="px-2 py-1.5 font-medium text-gray-700">${t.stock_name}</td>
                                    <td class="px-2 py-1.5">
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium ${t.type === 'IN' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}">
                                            ${t.type}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-medium">${quantity.toFixed(3)}g</td>
                                    <td class="px-2 py-1.5 text-right">₹${rate > 0 ? rate.toFixed(2) : '-'}</td>
                                    <td class="px-2 py-1.5 text-right font-medium">₹${amount > 0 ? amount.toFixed(2) : '-'}</td>
                                    <td class="px-2 py-1.5">
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium ${getStatusStyle(t.payment_status)}">
                                            ${t.payment_status || '-'}
                                        </span>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
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
        default: return 'bg-gray-50 text-gray-600';
    }
}

/* New function to load current rate based on material type */
function loadCurrentRate(materialType = 'Gold') {
    fetch('stock_functions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=getCurrentRate&material_type=${materialType}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.rate) {
            stockRate.value = data.rate;
            calculateBuyingRate();
        }
    })
    .catch(error => console.error('Error:', error));
}

function calculateBuyingRate() {
    const baseRate = parseFloat(stockRate.value) || 0;
    const purity = parseFloat(stockPurity.value === 'custom' ? customPurity.value : stockPurity.value) || 0;
    
    if(baseRate > 0 && purity > 0) {
        // Calculate buying rate based on purity ratio
        const buyingRate = baseRate * (purity / 24); // For gold (24K base)
        buyingPurity.value = buyingRate.toFixed(2);
    }
}

// Add these to your existing DOMContentLoaded event
stockMetalType.addEventListener('change', (e) => {
    loadCurrentRate(e.target.value);
});

stockPurity.addEventListener('change', calculateBuyingRate);
customPurity.addEventListener('input', calculateBuyingRate);
stockRate.addEventListener('input', calculateBuyingRate);

// Allow manual override of buying rate
buyingPurity.addEventListener('input', (e) => {
    // User is manually entering buying rate
    // No need to recalculate
});
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            switchTab(this.dataset.tab);
        });
    });

    // Load initial stats
    loadStockStats();
});