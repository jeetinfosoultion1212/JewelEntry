    // Sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileToggle = document.getElementById('mobile-toggle');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const collapseToggle = document.getElementById('collapse-toggle');
        const mainContent = document.getElementById('main-content');
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');

        // Sidebar toggle for mobile
        function toggleSidebar() {
            sidebar.classList.toggle('sidebar-expanded');
            sidebarOverlay.classList.toggle('hidden');
        }

        // Sidebar collapse/expand
        function toggleCollapse() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        }

        // Notifications dropdown
        function toggleNotifications() {
            notificationsDropdown.classList.toggle('show');
        }

        // Profile menu toggle
        window.toggleProfileMenu = function(event) {
            event.stopPropagation();
            const profileMenu = document.getElementById('profileMenu');
            profileMenu.classList.toggle('hidden');
        }

        // Event listeners
        sidebarToggle.addEventListener('click', toggleSidebar);
        mobileToggle.addEventListener('click', toggleSidebar);
        collapseToggle.addEventListener('click', toggleCollapse);
        notificationsToggle.addEventListener('click', toggleNotifications);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const profileMenu = document.getElementById('profileMenu');
            if (!event.target.closest('.group')) {
                profileMenu.classList.add('hidden');
            }
            if (!event.target.closest('#notifications-toggle')) {
                notificationsDropdown.classList.remove('show');
            }
        });

        // Initialize sidebar state
        if (window.innerWidth <= 768) {
            sidebar.classList.add('hidden');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {                                                                                                                                                                                                                                                              
      console.log('Jewelry Management System initialized');
      // Global variables
      let currentItemId = null;
      let itemsData = [];
      let currentPage = 1;
      let itemsPerPage = 10;
      
      // Add preservation flags (similar to add.js)
      let keepSourceSelection = true;
      let keepMaterialSelection = true;
      let keepPuritySelection = true;
    
      // Toast notification system
      function showToast(message, type = 'info', duration = 3000) {
        const toastContainer = document.getElementById('toastContainer');
      
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
      
        let icon = '';
        if (type === 'success') {
          icon = '<i class="fas fa-check-circle mr-2"></i>';
        } else if (type === 'error') {
          icon = '<i class="fas fa-exclamation-circle mr-2"></i>';
        } else if (type === 'warning') {
          icon = '<i class="fas fa-exclamation-triangle mr-2"></i>';
        } else {
          icon = '<i class="fas fa-info-circle mr-2"></i>';
        }
      
        toast.innerHTML = `${icon}<span>${message}</span>`;
        toastContainer.appendChild(toast);
      
        // Auto remove after duration
        setTimeout(() => {
          toast.style.animation = 'fadeOut 0.3s ease-out forwards';
          setTimeout(() => {
            toastContainer.removeChild(toast);
          }, 300);
        }, duration);
      }
    
      // Source Type Dropdown Handling
      const sourceTypeSelect = document.getElementById('sourceTypeSelect');
      const sourceId = document.getElementById('sourceId');
      const sourceInfoDisplay = document.getElementById('sourceInfoDisplay');

      // Hidden fields
      const sourceTypeField = document.getElementById('sourceTypeHidden');
      const sourceNameField = document.getElementById('sourceName');
      const sourceLocationField = document.getElementById('sourceLocation');
      const sourceMaterialTypeField = document.getElementById('sourceMaterialType');
      const sourcePurityField = document.getElementById('sourcePurity');
      const sourceWeightField = document.getElementById('sourceWeight');
      const sourceInventoryIdField = document.getElementById('sourceInventoryId');

      // Display elements
      const sourceNameDisplay = document.getElementById('sourceNameDisplay');
      const sourceTypeDisplay = document.getElementById('sourceTypeDisplay');
      const sourceMaterialDisplay = document.getElementById('sourceMaterialDisplay');
      const sourcePurityDisplay = document.getElementById('sourcePurityDisplay');
      const sourceWeightDisplay = document.getElementById('sourceWeightDisplay');
      const sourceStatusDisplay = document.getElementById('sourceStatusDisplay');
      const sourceInvoiceNoDisplay = document.getElementById('sourceInvoiceNoDisplay');

      sourceTypeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        sourceTypeField.value = selectedType;
      
        // Reset fields
        sourceId.value = '';
        resetSourceInfo();
      
        if (selectedType === 'Manufacturing Order') {
          sourceId.placeholder = 'Search order ID...';
        } else if (selectedType === 'Purchase') {
          sourceId.placeholder = 'Search invoice/batch...';
        } else { // Others
          sourceId.placeholder = 'Enter source ID...';
        }
      });

      // --- Keyboard navigation and collapsible source section ---
      let sourceSuggestions = [];
      let highlightedIndex = -1;
      let selectedSourceData = null;
      let isSourceCollapsed = false;

      const sourceSection = document.querySelector('.source-section'); // Add class to your source section div if not present
      const sourceSummary = document.createElement('div');
      sourceSummary.className = 'bg-blue-50 border border-blue-200 rounded p-2 mt-2 mb-2 hidden';
      sourceSummary.style.cursor = 'pointer';
      sourceSummary.innerHTML = '<span class="font-semibold">Source:</span> <span id="sourceSummaryName"></span> <span class="ml-2 text-xs text-gray-500" id="sourceSummaryStock"></span> <button id="editSourceBtn" class="ml-2 text-blue-600 underline text-xs">Edit</button>';
      sourceSection.parentNode.insertBefore(sourceSummary, sourceSection.nextSibling);

      function showSourceSummary(data) {
        document.getElementById('sourceSummaryName').textContent = data.supplier_name || data.karigar_name || 'N/A';
        document.getElementById('sourceSummaryStock').textContent = data.remaining_stock ? `Stock: ${data.remaining_stock}g` : '';
        sourceSummary.classList.remove('hidden');
      }
      function hideSourceSummary() {
        sourceSummary.classList.add('hidden');
      }
      function collapseSourceSection() {
        sourceSection.classList.add('hidden');
        isSourceCollapsed = true;
      }
      function expandSourceSection() {
        sourceSection.classList.remove('hidden');
        isSourceCollapsed = false;
        hideSourceSummary();
      }
      sourceSummary.querySelector('#editSourceBtn').addEventListener('click', expandSourceSection);

      // Keyboard navigation for Source ID suggestions
      sourceId.addEventListener('keydown', function(e) {
        const suggestionsBox = document.getElementById('sourceIdSuggestions');
        const items = suggestionsBox.querySelectorAll('div.p-2');
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          highlightedIndex = (highlightedIndex + 1) % items.length;
          updateSuggestionHighlight(items);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          highlightedIndex = (highlightedIndex - 1 + items.length) % items.length;
          updateSuggestionHighlight(items);
        } else if (e.key === 'Enter') {
          e.preventDefault();
          if (highlightedIndex >= 0 && highlightedIndex < items.length) {
            items[highlightedIndex].click();
          }
        }
      });
      function updateSuggestionHighlight(items) {
        items.forEach((item, idx) => {
          if (idx === highlightedIndex) {
            item.classList.add('bg-blue-100');
          } else {
            item.classList.remove('bg-blue-100');
          }
        });
      }

      // Move the resetSourceBtn event handler here
      const resetSourceBtn = document.getElementById('resetSourceBtn');
      if (resetSourceBtn) {
        resetSourceBtn.addEventListener('click', function() {
          if (sourceTypeSelect) sourceTypeSelect.value = 'Manufacturing Order';
          resetSourceInfo();
          showToast('Source selection has been reset', 'info');
        });
      }

      function updateSourceInfo(data, type) {
        sourceInfoDisplay.classList.remove('hidden');
        
        // Show preserved indicator when source is selected
        const preservedIndicator = document.getElementById('sourcePreservedIndicator');
        if (preservedIndicator) preservedIndicator.classList.remove('hidden');
      
        if (type === 'Manufacturing Order') {
          // Update hidden fields
          sourceNameField.value = data.karigar_name;
          sourceLocationField.value = 'Manufacturing';
          sourceMaterialTypeField.value = 'Gold'; // Assuming gold, adjust if needed
          sourcePurityField.value = data.purity_out;
          sourceWeightField.value = data.expected_weight;
      
          // Update display
          sourceNameDisplay.textContent = data.karigar_name;
          sourceTypeDisplay.textContent = 'Karigar';
          sourceMaterialDisplay.textContent = 'Gold';
          sourcePurityDisplay.textContent = data.purity_out + '%';
          sourceWeightDisplay.textContent = data.expected_weight + 'g';
          sourceStatusDisplay.textContent = data.status;
      
          // Auto-fill material and purity fields
          document.getElementById('materialType').value = 'Gold';
          document.getElementById('purity').value = data.purity_out;
      
        } else if (type === 'Purchase') {
          // Update hidden fields
          sourceNameField.value = data.supplier_name;
          sourceLocationField.value = 'Purchase';
          sourceMaterialTypeField.value = data.material_type;
          sourcePurityField.value = data.purity;
          sourceWeightField.value = data.weight;
          sourceInventoryIdField.value = data.inventory_id;
      
          // Update display
          sourceNameDisplay.textContent = data.supplier_name;
          sourceTypeDisplay.textContent = 'Supplier';
          sourceMaterialDisplay.textContent = data.material_type;
          sourcePurityDisplay.textContent = data.purity + '%';
          sourceWeightDisplay.textContent = data.weight + 'g';
          sourceStatusDisplay.textContent = 'Available: ' + data.remaining_stock + 'g';
          sourceInvoiceNoDisplay.textContent = 'Invoice: ' + data.invoice_number;
      
          // Auto-fill material and purity fields
          document.getElementById('materialType').value = data.material_type;
          document.getElementById('purity').value = data.purity;
          // Set cost per gram field
          const costPerGramInput = document.getElementById('costPerGram');
          if (costPerGramInput && data.cost_price_per_gram !== undefined) {
            costPerGramInput.value = data.cost_price_per_gram;
          }
        }
      }

      // Source ID Autocomplete with intelligent filtering
      sourceId.addEventListener('input', function() {
        if (this.value.length > 0) {
          const sourceType = sourceTypeSelect.value;
          const purity = document.getElementById('purity').value;
      
          if (sourceType === 'Manufacturing Order') {
            fetchManufacturingOrders(this.value, purity);
          } else if (sourceType === 'Purchase') {
            fetchMetalPurchases(this.value, purity);
          } else {
            document.getElementById('sourceIdSuggestions').classList.add('hidden');
          }
        } else {
          document.getElementById('sourceIdSuggestions').innerHTML = '';
          document.getElementById('sourceIdSuggestions').classList.add('hidden');
        }
      });

      function fetchManufacturingOrders(search, purity = '') {
        const formData = new FormData();
        formData.append('action', 'get_manufacturing_orders');
        formData.append('search', search);
        if (purity) {
          formData.append('purity', purity);
        }
      
        fetch('add-product.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          const suggestions = document.getElementById('sourceIdSuggestions');
          highlightedIndex = -1;
          sourceSuggestions = data.data || [];
          if (data.success && data.data.length > 0) {
            suggestions.innerHTML = '';
            data.data.forEach((order, idx) => {
              const div = document.createElement('div');
              div.className = 'p-2 hover:bg-gray-100 cursor-pointer text-xs';
              let statusBadge = '';
              if (order.status === 'Completed') {
                statusBadge = '<span class="px-1 py-0.5 bg-green-100 text-green-800 rounded-full text-xs">Completed</span>';
              } else if (order.status === 'Pending') {
                statusBadge = '<span class="px-1 py-0.5 bg-yellow-100 text-yellow-800 rounded-full text-xs">Pending</span>';
              }
              div.innerHTML = `<div class="flex justify-between items-center">
                <strong>Order ID: ${order.id}</strong>
                ${statusBadge}
              </div>
              <div>${order.karigar_name} - ${order.expected_weight}g (${order.purity_out}%)</div>`;
              div.addEventListener('click', function() {
                sourceId.value = order.id;
                updateSourceInfo(order, 'Manufacturing Order');
                suggestions.classList.add('hidden');
                selectedSourceData = order;
                collapseSourceSection();
                showSourceSummary(order);
              });
              suggestions.appendChild(div);
            });
            suggestions.classList.remove('hidden');
          } else {
            suggestions.innerHTML = '';
            const div = document.createElement('div');
            div.className = 'p-2 text-xs text-gray-500';
            div.textContent = 'No matching orders found.';
            suggestions.appendChild(div);
            suggestions.classList.remove('hidden');
          }
        })
        .catch(error => {
          console.error('Error fetching manufacturing orders:', error);
        });
      }

      function fetchMetalPurchases(search, purity = '') {
        const formData = new FormData();
        formData.append('action', 'get_metal_purchases');
        formData.append('search', search);
        if (purity) {
          formData.append('purity', purity);
        }
      
        fetch('add-product.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          const suggestions = document.getElementById('sourceIdSuggestions');
          highlightedIndex = -1;
          sourceSuggestions = data.data || [];
          if (data.success && data.data.length > 0) {
            suggestions.innerHTML = '';
            data.data.forEach((purchase, idx) => {
              const div = document.createElement('div');
              div.className = 'p-2 hover:bg-gray-100 cursor-pointer text-xs';
              div.innerHTML = `<div class="flex justify-between items-center">
                <strong>${purchase.invoice_number || 'Batch: ' + purchase.purchase_id}</strong>
                <span class="px-1 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs">Stock: ${purchase.remaining_stock}g</span>
              </div>
              <div>${purchase.supplier_name} - ${purchase.material_type} - ${purchase.weight}g (${purchase.purity}%)</div>`;
              div.addEventListener('click', function() {
                sourceId.value = purchase.purchase_id;
                updateSourceInfo(purchase, 'Purchase');
                // Set cost per gram field
                const costPerGramInput = document.getElementById('costPerGram');
                if (costPerGramInput && purchase.cost_price_per_gram !== undefined) {
                  costPerGramInput.value = purchase.cost_price_per_gram;
                }
                suggestions.classList.add('hidden');
                selectedSourceData = purchase;
                collapseSourceSection();
                showSourceSummary(purchase);
              });
              suggestions.appendChild(div);
            });
            suggestions.classList.remove('hidden');
          } else {
            suggestions.innerHTML = '';
            const div = document.createElement('div');
            div.className = 'p-2 text-xs text-gray-500';
            div.textContent = 'No matching purchases with available stock found.';
            suggestions.appendChild(div);
            suggestions.classList.remove('hidden');
          }
        })
        .catch(error => {
          console.error('Error fetching metal purchases:', error);
        });
      }

      // Tray Number Suggestions
      const trayNo = document.getElementById('trayNo');
      const trayNoSuggestions = document.getElementById('trayNoSuggestions');

      trayNo.addEventListener('input', function() {
        if (this.value.length > 0) {
          fetchTraySuggestions(this.value);
        } else {
          trayNoSuggestions.innerHTML = '';
          trayNoSuggestions.classList.add('hidden');
        }
      });

      function fetchTraySuggestions(search) {
        const formData = new FormData();
        formData.append('action', 'get_tray_suggestions');
        formData.append('search', search);
      
        fetch('add-product.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data.length > 0) {
            trayNoSuggestions.innerHTML = '';
        
            data.data.forEach(tray => {
              const div = document.createElement('div');
              div.className = 'p-2 hover:bg-gray-100 cursor-pointer text-xs';
          
              div.innerHTML = `<div class="flex justify-between items-center">
                <strong>${tray.tray_number}</strong>
                <span class="px-1 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs">${tray.tray_type}</span>
              </div>
              <div>Location: ${tray.location} | Capacity: ${tray.capacity}</div>`;
          
              div.addEventListener('click', function() {
                trayNo.value = tray.tray_number;
                trayNoSuggestions.classList.add('hidden');
              });
          
              trayNoSuggestions.appendChild(div);
            });
        
            trayNoSuggestions.classList.remove('hidden');
          } else {
            trayNoSuggestions.innerHTML = '';
        
            const div = document.createElement('div');
            div.className = 'p-2 text-xs text-gray-500';
            div.textContent = 'No matching trays found.';
        
            suggestions.appendChild(div);
            trayNoSuggestions.classList.remove('hidden');
          }
        })
        .catch(error => {
          console.error('Error fetching tray suggestions:', error);
        });
      }

      // Update purity field to trigger source suggestions update
      document.getElementById('purity').addEventListener('change', function() {
        // If source ID has a value, refresh suggestions based on new purity
        if (sourceId.value.length > 0) {
          const sourceType = sourceTypeSelect.value;
          if (sourceType === 'Manufacturing Order') {
            fetchManufacturingOrders(sourceId.value, this.value);
          } else if (sourceType === 'Purchase') {
            fetchMetalPurchases(sourceId.value, this.value);
          }
        }
      });
    
      // Stone Type Toggle Logic
      const stoneType = document.getElementById('stoneType');
      const stoneWeight = document.getElementById('stoneWeight');
      const stoneQuality = document.getElementById('stoneQuality');
      const stonePrice = document.getElementById('stonePrice');
    
      stoneType.addEventListener('change', function() {
        if (this.value === 'None') {
          stoneWeight.disabled = true;
          stoneQuality.disabled = true;
          stonePrice.disabled = true;
          stoneWeight.value = '';
          stoneQuality.value = '';
          stonePrice.value = '';
        } else {
          stoneWeight.disabled = false;
          stoneQuality.disabled = false;
          stonePrice.disabled = false;
        }
      });
    
      // Net Weight Calculation
      const grossWeight = document.getElementById('grossWeight');
      const lessWeight = document.getElementById('lessWeight');
      const netWeight = document.getElementById('netWeight');
    
      [grossWeight, lessWeight].forEach(field => {
        field.addEventListener('input', calculateNetWeight);
      });
    
      function calculateNetWeight() {
        const gross = parseFloat(grossWeight.value) || 0;
        const less = parseFloat(lessWeight.value) || 0;
      
        if (gross >= less) {
          const net = gross - less;
          netWeight.value = net.toFixed(3);
        } else {
          netWeight.value = '0.000';
          showToast('Less weight cannot be greater than gross weight', 'warning');
        }
      }
      
       // Update less weight based on stone weight and unit
  function updateLessWeightFromStone() {
    if (stoneType.value === 'None' || !stoneWeight.value) {
      return;
    }
    
    const stoneWeightValue = parseFloat(stoneWeight.value) || 0;
    let stoneWeightInGrams = 0;
    
    // Convert stone weight to grams based on unit
    if (stoneUnit.value === 'ct') {
      // 1 carat = 0.2 grams
      stoneWeightInGrams = stoneWeightValue * 0.2;
    } else if (stoneUnit.value === 'ratti') {
      // 1 ratti = 0.18 grams (approximate)
      stoneWeightInGrams = stoneWeightValue * 0.18;
    }
    
    // Update less weight (add stone weight to existing less weight)
    const currentLessWeight = parseFloat(lessWeight.value) || 0;
    const newLessWeight = stoneWeightInGrams;
    
    // Only update if there's a change
    if (newLessWeight > 0) {
      lessWeight.value = newLessWeight.toFixed(3);
      calculateNetWeight();
    }
  }
    
      // Quick Note Toggle
      const quickNoteBtn = document.getElementById('quickNoteBtn');
      const quickNoteSection = document.getElementById('quickNoteSection');
      const closeQuickNote = document.getElementById('closeQuickNote');
    
      quickNoteBtn.addEventListener('click', function() {
        quickNoteSection.classList.remove('hidden');
        quickNoteSection.classList.add('active');
        document.getElementById('description').focus();
      });
    
      closeQuickNote.addEventListener('click', function() {
        quickNoteSection.classList.add('hidden');
        quickNoteSection.classList.remove('active');
      });
    
        // Weight calculation
    grossWeight.addEventListener('input', calculateNetWeight);
    lessWeight.addEventListener('input', calculateNetWeight);
    
    // Stone type change
    stoneType.addEventListener('change', function() {
      const isStoneSelected = this.value !== 'None';
      stoneWeight.disabled = !isStoneSelected;
      stoneUnit.disabled = !isStoneSelected;
      stoneColor.disabled = !isStoneSelected;
      stoneClarity.disabled = !isStoneSelected;
      stoneQuality.disabled = !isStoneSelected;
      stonePrice.disabled = !isStoneSelected;
      
      if (!isStoneSelected) {
        stoneWeight.value = '';
        stonePrice.value = '';
        stoneColor.value = '';
        stoneClarity.value = '';
        stoneQuality.value = '';
      }
    });
    
    // Stone weight and unit change
    stoneWeight.addEventListener('input', updateLessWeightFromStone);
    stoneUnit.addEventListener('change', updateLessWeightFromStone);
    
    
    
      // Image Upload Preview
      const productImages = document.getElementById('productImages');
      const imagePreview = document.getElementById('imagePreview');
    
      productImages.addEventListener('change', function(e) {
        for (const file of e.target.files) {
          if (file.type.match('image.*')) {
            const reader = new FileReader();
          
            reader.onload = function(e) {
              const imgContainer = document.createElement('div');
              imgContainer.className = 'relative';
          
              const img = document.createElement('img');
              img.src = e.target.result;
              img.className = 'w-16 h-16 object-cover rounded-md border border-gray-200';
          
              const removeBtn = document.createElement('button');
              removeBtn.className = 'absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-sm hover:bg-red-600';
              removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
              removeBtn.onclick = function() {
                imgContainer.remove();
              };
          
              imgContainer.appendChild(img);
              imgContainer.appendChild(removeBtn);
              imagePreview.appendChild(imgContainer);
            };
          
            reader.readAsDataURL(file);
          }
        }
      });
    
      // Alert Modal
      function showAlert(title, message, type = 'info') {
        const alertModal = document.getElementById('alertModal');
        const alertOverlay = document.getElementById('alertOverlay');
        const alertTitle = document.getElementById('alertTitle');
        const alertMessage = document.getElementById('alertMessage');
        const alertIcon = document.getElementById('alertIcon');
      
        alertTitle.textContent = title;
        alertMessage.innerHTML = message;
      
        // Set icon and color based on type
        alertModal.className = 'alert-modal alert-' + type;
      
        if (type === 'success') {
          alertIcon.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
        } else if (type === 'error') {
          alertIcon.innerHTML = '<i class="fas fa-exclamation-circle text-red-500"></i>';
        } else if (type === 'warning') {
          alertIcon.innerHTML = '<i class="fas fa-exclamation-triangle text-amber-500"></i>';
        } else {
          alertIcon.innerHTML = '<i class="fas fa-info-circle text-blue-500"></i>';
        }
      
        alertModal.classList.remove('hidden');
        alertOverlay.classList.remove('hidden');
      
        // Close alert on button click
        document.getElementById('alertClose').onclick = function() {
          alertModal.classList.add('hidden');
          alertOverlay.classList.add('hidden');
        };
      
        // Close alert on overlay click
        alertOverlay.onclick = function() {
          alertModal.classList.add('hidden');
          alertOverlay.classList.add('hidden');
        };
      }
    
      // Form Clear Button
      document.getElementById('clearForm').addEventListener('click', function() {
        clearForm(true); // Force reset everything including source selection
        showToast('Form has been completely reset', 'info');
      });
    
      // Modified clearForm function to respect field preservation
      function clearForm(forceReset = false) {
        // Reset form to add mode
        document.getElementById('itemId').value = '';
        document.getElementById('addItem').innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Add Item';
        
        // Clear original item data
        window.originalItemData = null;
      
        // Get current values to preserve if needed
        const currentSourceType = sourceTypeSelect ? sourceTypeSelect.value : '';
        const currentSourceId = sourceId ? sourceId.value : '';
        const currentSourceName = sourceNameField ? sourceNameField.value : '';
        const currentSourceMaterialType = sourceMaterialTypeField ? sourceMaterialTypeField.value : '';
        const currentSourcePurity = sourcePurityField ? sourcePurityField.value : '';
        const currentSourceWeight = sourceWeightField ? sourceWeightField.value : '';
        const currentSourceInventoryId = sourceInventoryIdField ? sourceInventoryIdField.value : '';
        const currentMaterialType = document.getElementById('materialType') ? document.getElementById('materialType').value : '';
        const currentPurity = document.getElementById('purity') ? document.getElementById('purity').value : '';
        
        // Re-enable source fields
        if (sourceTypeSelect) sourceTypeSelect.disabled = false;
        if (sourceId) sourceId.disabled = false;
        
        // Hide reset source button
        const resetSourceBtn = document.getElementById('resetSourceBtn');
        if (resetSourceBtn) {
          resetSourceBtn.classList.add('hidden');
          resetSourceBtn.style.display = 'none';
        }
        
        // Hide inventory update option
        const inventoryUpdateOption = document.getElementById('inventoryUpdateOption');
        if (inventoryUpdateOption) {
          inventoryUpdateOption.classList.add('hidden');
        }
      
        // Reset all form fields
        document.querySelectorAll('#jewelryForm input:not([type="hidden"]), #jewelryForm select, #jewelryForm textarea').forEach(element => {
          // Skip fields that should be preserved
          if (!forceReset) {
            // Skip source fields if keeping source selection
            if (keepSourceSelection && (element.id === 'sourceTypeSelect' || element.id === 'sourceId')) {
              return;
            }
            
            // Skip material field if keeping material selection
            if (keepMaterialSelection && element.id === 'materialType') {
              return;
            }
            
            // Skip purity field if keeping purity selection
            if (keepPuritySelection && element.id === 'purity') {
              return;
            }
          }
          
          if (element.type !== 'file') {
            if (element.id === 'quantity') {
              element.value = '1';
            } else {
              element.value = '';
            }
          }
        });
      
        // Reset dropdowns to first option (except those being preserved)
        const materialTypeField = document.getElementById('materialType');
        const stoneTypeField = document.getElementById('stoneType');
        const makingChargeTypeField = document.getElementById('makingChargeType');
        const statusField = document.getElementById('status');
      
        // Only reset if not preserving or force reset
        if (forceReset || !keepMaterialSelection) {
          if (materialTypeField) materialTypeField.value = 'Gold';
        }
        
        if (stoneTypeField) stoneTypeField.value = 'None';
        if (makingChargeTypeField) makingChargeTypeField.value = 'fixed';
        if (statusField) statusField.value = 'Available';
        
        // Only reset source type if not preserving or force reset
        if (forceReset || !keepSourceSelection) {
          if (sourceTypeSelect) sourceTypeSelect.value = 'Manufacturing Order';
        }
      
        // Reset source fields and display only if not preserving or force reset
        if (forceReset || !keepSourceSelection) {
          resetSourceInfo();
        }
      
        // Clear image previews
        imagePreview.innerHTML = '';
      
        // Hide quick note section
        quickNoteSection.classList.add('hidden');
      
        // Reset stone fields
        stoneWeight.disabled = true;
        stoneQuality.disabled = true;
        stonePrice.disabled = true;
      
        // Reset net weight
        netWeight.value = '';
        
        // Reset product ID
        const productIdDisplay = document.getElementById('productIdDisplay');
        if (productIdDisplay) productIdDisplay.value = '';
        
        // Restore preserved values if not force reset
        if (!forceReset) {
          // Restore source fields if keeping source selection
          if (keepSourceSelection) {
            if (sourceTypeSelect) sourceTypeSelect.value = currentSourceType;
            if (sourceId) sourceId.value = currentSourceId;
            
            // Restore source hidden fields
            if (sourceNameField) sourceNameField.value = currentSourceName;
            if (sourceMaterialTypeField) sourceMaterialTypeField.value = currentSourceMaterialType;
            if (sourcePurityField) sourcePurityField.value = currentSourcePurity;
            if (sourceWeightField) sourceWeightField.value = currentSourceWeight;
            if (sourceInventoryIdField) sourceInventoryIdField.value = currentSourceInventoryId;
            
            // Restore source info display
            if (sourceInfoDisplay && currentSourceName) {
              sourceInfoDisplay.classList.remove('hidden');
              
              // Update display fields based on source type
              if (currentSourceType === 'Purchase') {
                sourceNameDisplay.textContent = currentSourceName;
                sourceTypeDisplay.textContent = 'Supplier';
                sourceMaterialDisplay.textContent = currentSourceMaterialType;
                sourcePurityDisplay.textContent = currentSourcePurity + '%';
                sourceWeightDisplay.textContent = currentSourceWeight + 'g';
                sourceStatusDisplay.textContent = 'Available';
                sourceInvoiceNoDisplay.textContent = currentSourceId;
              } else if (currentSourceType === 'Manufacturing Order') {
                sourceNameDisplay.textContent = currentSourceName;
                sourceTypeDisplay.textContent = 'Karigar';
                sourceMaterialDisplay.textContent = currentSourceMaterialType;
                sourcePurityDisplay.textContent = currentSourcePurity + '%';
                sourceWeightDisplay.textContent = currentSourceWeight + 'g';
                sourceStatusDisplay.textContent = 'Available';
                sourceInvoiceNoDisplay.textContent = currentSourceId;
              }
              
              // Show preserved indicator
              const preservedIndicator = document.getElementById('sourcePreservedIndicator');
              if (preservedIndicator) preservedIndicator.classList.remove('hidden');
            }
          }
          
          // Restore material field if keeping material selection
          if (keepMaterialSelection && materialTypeField) {
            materialTypeField.value = currentMaterialType;
          }
          
          // Restore purity field if keeping purity selection
          if (keepPuritySelection && document.getElementById('purity')) {
            document.getElementById('purity').value = currentPurity;
          }
        }
      }
    
      // Jewelry Type Autocomplete
      const jewelryType = document.getElementById('jewelryType');
      const jewelryTypeSuggestions = document.getElementById('jewelryTypeSuggestions');
    
      jewelryType.addEventListener('input', function() {
        if (this.value.length > 1) {
          fetchJewelryTypes(this.value);
        } else {
          jewelryTypeSuggestions.innerHTML = '';
          jewelryTypeSuggestions.classList.add('hidden');
        }
      });
    
      function fetchJewelryTypes(search) {
        const formData = new FormData();
        formData.append('action', 'get_jewelry_types');
        formData.append('search', search);
      
        fetch('add-product.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data.length > 0) {
            jewelryTypeSuggestions.innerHTML = '';
        
           data.data.forEach(type => {
              const div = document.createElement('div');
              div.className = 'p-2 hover:bg-gray-100 cursor-pointer text-xs';
              div.textContent = type.name;
              div.addEventListener('click', function() {
                jewelryType.value = type.name;
                jewelryTypeSuggestions.classList.add('hidden');
              });
          
              jewelryTypeSuggestions.appendChild(div);
            });
            jewelryTypeSuggestions.classList.remove('hidden');
          } else {
            jewelryTypeSuggestions.innerHTML = '';
        
            const div = document.createElement('div');
            div.className = 'p-2 text-xs text-gray-500';
            div.textContent = 'No matches found. Type will be added as new.';
        
            jewelryTypeSuggestions.appendChild(div);
            jewelryTypeSuggestions.classList.remove('hidden');
          }
        })
        .catch(error => {
          console.error('Error fetching jewelry types:', error);
        });
      }
    
      // Validate Form
      function validateForm() {
        // Required fields
        const requiredFields = [
          { id: 'materialType', label: 'Material Type' },
          { id: 'purity', label: 'Purity' },
          { id: 'jewelryType', label: 'Jewelry Type' },
          { id: 'productName', label: 'Product Name' },
          { id: 'grossWeight', label: 'Gross Weight' },
          { id: 'sourceId', label: 'Source ID' }
        ];
      
        // Check stone fields if stone type is not None
        if (stoneType.value !== 'None') {
          requiredFields.push(
            { id: 'stoneWeight', label: 'Stone Weight' },
            { id: 'stoneQuality', label: 'Stone Quality' }
          );
        }
      
        let isValid = true;
        let errorMessages = [];
      
        // Check each required field
        requiredFields.forEach(field => {
          const element = document.getElementById(field.id);
          if (!element.value.trim()) {
            element.classList.add('border-red-500');
            errorMessages.push(`${field.label} is required`);
            isValid = false;
          } else {
            element.classList.remove('border-red-500');
          }
        });
      
        // Check if net weight is valid
        if (parseFloat(netWeight.value) <= 0) {
          netWeight.classList.add('border-red-500');
          errorMessages.push('Net weight must be greater than zero');
          isValid = false;
        } else {
          netWeight.classList.remove('border-red-500');
        }
      
        // For Purchase source type, check if there's enough stock
        if (sourceTypeSelect.value === 'Purchase' && sourceInventoryIdField.value) {
          const availableStock = parseFloat(sourceStatusDisplay.textContent.replace('Available: ', '').replace('g', ''));
          const requiredStock = parseFloat(netWeight.value);
        
          if (requiredStock > availableStock) {
            netWeight.classList.add('border-red-500');
            errorMessages.push(`Not enough stock available. Required: ${requiredStock}g, Available: ${availableStock}g`);
            isValid = false;
          }
        }
      
        // Show error message if validation fails
        if (!isValid) {
          showAlert('Validation Error', errorMessages.join('<br>'), 'error');
        }
      
        return isValid;
      }
    
      // Add/Update Item
      document.getElementById('addItem').addEventListener('click', function() {
        // Validate form
        if (!validateForm()) {
            return;
        }
      
        const form = document.getElementById('jewelryForm');
        const formData = new FormData(form);
      
        // Add action based on whether we're adding or updating
        const itemId = document.getElementById('itemId').value;
        formData.append('action', itemId ? 'update_item' : 'add_item');
        if (itemId) {
            formData.append('itemId', itemId);
        }
      
        // Add source information
        formData.append('sourceType', sourceTypeSelect.value);
        formData.append('sourceId', sourceId.value);
        
        // For Purchase source type, add inventory ID for stock deduction
        if (sourceTypeSelect.value === 'Purchase' && sourceInventoryIdField.value) {
            formData.append('inventoryId', sourceInventoryIdField.value);
        }
      
        // Add images if any
        const imageFiles = document.getElementById('productImages').files;
        if (imageFiles.length > 0) {
            for (let i = 0; i < imageFiles.length; i++) {
                formData.append('images[]', imageFiles[i]);
            }
        }
      
        // Submit form
        fetch('add-product.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                clearForm(false); // Don't force reset, preserve selections
                loadItems();
            } else {
                showToast(data.message || 'An error occurred.', 'error');
            }
        })
        .catch(error => {
            console.error('Error submitting form:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
      });

      // Load Items
      function logAjaxResponse(action, response) {
        console.log(`AJAX ${action} response:`, response);
      }

      function loadItems(page = 1) {
        currentPage = page;
      
        const formData = new FormData();
        formData.append('action', 'get_items');
        formData.append('page', page);
        formData.append('limit', itemsPerPage);
      
        // Add filters
        const search = document.getElementById('searchItems').value;
        const materialFilter = document.getElementById('filterMaterial').value;
        const typeFilter = document.getElementById('filterJewelryType').value;
        const sourceFilter = document.getElementById('filterSource').value;
        const statusFilter = document.getElementById('filterStatus').value;
      
        if (search) formData.append('search', search);
        if (materialFilter) formData.append('materialFilter', materialFilter);
        if (typeFilter) formData.append('typeFilter', typeFilter);
        if (sourceFilter) formData.append('sourceFilter', sourceFilter);
        if (statusFilter) formData.append('statusFilter', statusFilter);
      
        fetch('add-product.php', {
          method: 'POST',
          body: formData
        })
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          logAjaxResponse('get_items', data);
          if (data.success) {
            itemsData = data.data.items;
            renderItems(data.data);
          
            // Update inventory stats if available
            if (data.data.inventoryStats) {
              updateInventoryStats(data.data.inventoryStats);
            }
          } else {
            console.error('Error loading items:', data.message);
            showToast('Failed to load items: ' + (data.message || 'Unknown error'), 'error');
          }
        })
        .catch(error => {
          console.error('Error loading items:', error);
          showToast('Failed to load items: ' + error.message, 'error');
        });
      }
    
      function updateInventoryStats(stats) {
        const statsGrid = document.querySelector('.stats-grid');
        if (!statsGrid) return;
      
        statsGrid.innerHTML = '';
      
        if (Object.keys(stats).length === 0) {
          const statItem = document.createElement('div');
          statItem.className = 'stat-item';
          statItem.innerHTML = `
            <div class="stat-value">0.00g</div>
            <div class="stat-label">No inventory data</div>
          `;
          statsGrid.appendChild(statItem);
        } else {
          for (const [material, stock] of Object.entries(stats)) {
            const statItem = document.createElement('div');
            statItem.className = 'stat-item';
            statItem.innerHTML = `
              <div class="stat-value">${parseFloat(stock).toFixed(2)}g</div>
              <div class="stat-label">${material}</div>
            `;
            statsGrid.appendChild(statItem);
          }
        }
      }
    
      function renderItems(data) {
        const tbody = document.querySelector('#itemsTable tbody');
        tbody.innerHTML = '';
      
        if (data.items.length === 0) {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td colspan="6" class="py-4 text-center text-gray-500">No items found</td>
          `;
          tbody.appendChild(tr);
        } else {
          data.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-blue-50 transition-colors border-b border-gray-100";
          
            // Format date
            const createdDate = new Date(item.created_at);
            const formattedDate = createdDate.toLocaleDateString();
          
            // Determine source badge color
            let sourceBadgeClass = 'bg-gray-100 text-gray-800';
            if (item.source_type === 'Supplier') {
              sourceBadgeClass = 'bg-blue-100 text-blue-800';
            } else if (item.source_type === 'Karigar') {
              sourceBadgeClass = 'bg-orange-100 text-orange-800';
            }
          
            // Determine status badge color
            let statusBadgeClass = 'bg-gray-100 text-gray-800';
            if (item.status === 'Available') {
              statusBadgeClass = 'status-available';
            } else if (item.status === 'Pending') {
              statusBadgeClass = 'status-pending';
            } else if (item.status === 'Sold') {
              statusBadgeClass = 'status-sold';
            }
          
            tr.innerHTML = `
              <td class="py-1 px-2 text-xs">${item.product_id}</td>
              <td class="py-1 px-2">
                <div class="flex items-center">
                  <div class="w-6 h-6 bg-gray-200 rounded-md overflow-hidden mr-1.5">
                    <img src="${item.image_url || 'uploads/jewelry/no_images.png'}" alt="" class="w-full h-full object-cover">
                  </div>
                  <div>
                    <div class="text-xs font-medium">${item.product_name}</div>
                    <div class="text-xs text-gray-500">${item.material_type} | ${item.purity}%</div>
                </div>
              </div>
            </td>
            <td class="py-1 px-2 text-xs">${parseFloat(item.net_weight).toFixed(2)}g</td>
            <td class="py-1 px-2">
              <span class="text-xs px-1.5 py-0.5 ${sourceBadgeClass} rounded-full">${item.source_type}</span>
            </td>
            <td class="py-1 px-2">
              <span class="text-xs px-1.5 py-0.5 ${statusBadgeClass} rounded-full">${item.status}</span>
            </td>
            <td class="py-1 px-2 text-center">
              <div class="flex justify-center space-x-1">
                <button class="text-blue-500 hover:text-blue-700 edit-btn" data-id="${item.id}" title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="text-red-500 hover:text-red-700 delete-btn" data-id="${item.id}" title="Delete">
                  <i class="fas fa-trash-alt"></i>
                </button>
                <button class="text-purple-500 hover:text-purple-700 view-btn" data-id="${item.id}" title="View Details">
                  <i class="fas fa-eye"></i>
                </button>
                <button class="text-green-500 hover:text-green-700 print-btn" data-id="${item.id}" title="Print">
                  <i class="fas fa-print"></i>
                </button>
              </div>
            </td>
          `;
          
          tbody.appendChild(tr);
        });
        
        // Add event listeners to buttons
        document.querySelectorAll('.edit-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            editItem(itemId);
          });
        });
        
        document.querySelectorAll('.delete-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            showDeleteConfirmation(itemId);
          });
        });
        
        document.querySelectorAll('.view-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            viewItemDetails(itemId);
          });
        });
        
        document.querySelectorAll('.print-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            window.open(`https://jewelentry.prosenjittechhub.com/tag_print.php?id=${itemId}`, '_blank');
          });
        });
      }
      
      // Update item count
      document.getElementById('itemCount').textContent = `${data.total} items`;
      
      // Add pagination if needed
      if (data.totalPages > 1) {
        addPagination(data);
      }
    }
    
    function addPagination(data) {
      const paginationContainer = document.createElement('div');
      paginationContainer.className = 'flex justify-center mt-4 gap-1';
      
      // Previous button
      const prevButton = document.createElement('button');
      prevButton.className = 'px-2 py-1 rounded bg-gray-200 text-xs';
      prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
      prevButton.disabled = data.page === 1;
      prevButton.addEventListener('click', () => loadItems(data.page - 1));
      
      // Next button
      const nextButton = document.createElement('button');
      nextButton.className = 'px-2 py-1 rounded bg-gray-200 text-xs';
      nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
      nextButton.disabled = data.page === data.totalPages;
      nextButton.addEventListener('click', () => loadItems(data.page + 1));
      
      paginationContainer.appendChild(prevButton);
      
      // Page buttons
      for (let i = 1; i <= data.totalPages; i++) {
        const pageButton = document.createElement('button');
        pageButton.className = i === data.page 
          ? 'px-2 py-1 rounded bg-blue-500 text-white text-xs' 
          : 'px-2 py-1 rounded bg-gray-200 hover:bg-gray-300 text-xs';
        pageButton.textContent = i;
        pageButton.addEventListener('click', () => loadItems(i));
        paginationContainer.appendChild(pageButton);
      }
      
      paginationContainer.appendChild(nextButton);
      
      // Add pagination to table container
      const tableContainer = document.querySelector('.table-container');
      
      // Remove existing pagination if any
      const existingPagination = tableContainer.nextElementSibling;
      if (existingPagination && existingPagination.classList.contains('flex')) {
        existingPagination.remove();
      }
      
      tableContainer.insertAdjacentElement('afterend', paginationContainer);
    }
    
    // Edit Item
    function editItem(itemId) {
      const formData = new FormData();
      formData.append('action', 'get_item_details');
      formData.append('itemId', itemId);
      
      fetch('add-product.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          populateForm(data.data);
          showToast('Item loaded for editing', 'success');
        } else {
          showToast(data.message || 'An error occurred.', 'error');
        }
      })
      .catch(error => {
        console.error('Error fetching item details:', error);
        showToast('An error occurred. Please try again.', 'error');
      });
    }
    
    function populateForm(item) {
      console.log('Populating form for item:', item);
      // Set form to edit mode
      document.getElementById('itemId').value = item.id;
      document.getElementById('addItem').innerHTML = '<i class="fas fa-save mr-1"></i> Update Item';
      
      // Store original item data for inventory reversal
      window.originalItemData = {
        material_type: item.material_type,
        purity: item.purity,
        net_weight: item.net_weight,
        source_type: item.source_type,
        supplier_id: item.supplier_id,
        karigar_id: item.karigar_id
      };
      
      // Set source type - make it editable during edit mode
      if (item.source_type === 'Supplier') {
        document.getElementById('sourceTypeSelect').value = 'Purchase';
        document.getElementById('sourceTypeHidden').value = 'Purchase';
        document.getElementById('sourceId').value = item.supplier_id;
        
        // Create a mock purchase object for display
        const purchaseData = {
          supplier_name: item.supplier_name || 'Unknown Supplier',
          material_type: item.material_type,
          purity: item.purity,
          weight: item.net_weight,
          remaining_stock: item.net_weight, // Assuming same as item weight for edit
          invoice_number: 'N/A'
        };
        
        updateSourceInfo(purchaseData, 'Purchase');
        
      } else if (item.source_type === 'Karigar') {
        document.getElementById('sourceTypeSelect').value = 'Manufacturing Order';
        document.getElementById('sourceTypeHidden').value = 'Manufacturing Order';
        document.getElementById('sourceId').value = item.karigar_id;
        
        // Create a mock order object for display
        const orderData = {
          karigar_name: item.karigar_name || 'Unknown Karigar',
          purity_out: item.purity,
          expected_weight: item.net_weight,
          status: 'Completed'
        };
        
        updateSourceInfo(orderData, 'Manufacturing Order');
        
      } else {
        document.getElementById('sourceTypeSelect').value = 'Others';
        document.getElementById('sourceTypeHidden').value = 'Others';
        document.getElementById('sourceId').value = '';
        resetSourceInfo();
      }
      
      // Make source fields editable during edit mode
      if (sourceTypeSelect) sourceTypeSelect.disabled = false;
      if (sourceId) sourceId.disabled = false;
      
      // Show reset source button during edit mode
      const resetSourceBtn = document.getElementById('resetSourceBtn');
      if (resetSourceBtn) {
        resetSourceBtn.classList.remove('hidden');
        resetSourceBtn.style.display = 'inline-flex';
      }
      
      // Show inventory update option during edit mode
      const inventoryUpdateOption = document.getElementById('inventoryUpdateOption');
      if (inventoryUpdateOption) {
        inventoryUpdateOption.classList.remove('hidden');
      }
      
      // Set material details
      document.getElementById('materialType').value = item.material_type;
      document.getElementById('purity').value = item.purity;
      document.getElementById('jewelryType').value = item.jewelry_type;
      document.getElementById('productName').value = item.product_name;
      
      // Set weight details
      document.getElementById('grossWeight').value = item.gross_weight;
      document.getElementById('lessWeight').value = item.less_weight;
      document.getElementById('netWeight').value = item.net_weight;
      document.getElementById('trayNo').value = item.Tray_no;
      document.getElementById('huidCode').value = item.huid_code;
      
      // Set stone details
      document.getElementById('stoneType').value = item.stone_type;
      if (item.stone_type !== 'None') {
        document.getElementById('stoneWeight').disabled = false;
        document.getElementById('stoneQuality').disabled = false;
        document.getElementById('stonePrice').disabled = false;
        document.getElementById('stoneWeight').value = item.stone_weight;
        document.getElementById('stoneQuality').value = item.stone_quality;
        document.getElementById('stonePrice').value = item.stone_price;
      } else {
        document.getElementById('stoneWeight').disabled = true;
        document.getElementById('stoneQuality').disabled = true;
        document.getElementById('stonePrice').disabled = true;
      }
      
      // Set making details
      document.getElementById('makingCharge').value = item.making_charge;
      document.getElementById('makingChargeType').value = item.making_charge_type;
      document.getElementById('status').value = item.status;
      document.getElementById('quantity').value = item.quantity;
      
      // Set description
      if (item.description) {
        document.getElementById('description').value = item.description;
        document.getElementById('quickNoteSection').classList.remove('hidden');
      } else {
        document.getElementById('description').value = '';
        document.getElementById('quickNoteSection').classList.add('hidden');
      }
      
      // Clear image preview
      document.getElementById('imagePreview').innerHTML = '';
      
      // Add existing images to preview
      if (item.images && item.images.length > 0) {
        item.images.forEach(image => {
          const imgContainer = document.createElement('div');
          imgContainer.className = 'relative';
          
          const img = document.createElement('img');
          img.src = image.image_url;
          img.className = 'w-16 h-16 object-cover rounded-md border border-gray-200';
          
          const removeBtn = document.createElement('button');
          removeBtn.className = 'absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-sm hover:bg-red-600';
          removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
          removeBtn.onclick = function() {
            imgContainer.remove();
          };
          
          imgContainer.appendChild(img);
          imgContainer.appendChild(removeBtn);
          document.getElementById('imagePreview').appendChild(imgContainer);
        });
      }
      
      // Scroll to form
      document.querySelector('.form-container').scrollIntoView({ behavior: 'smooth' });
    }
    
    // View Item Details
    function viewItemDetails(itemId) {
      const formData = new FormData();
      formData.append('action', 'get_item_details');
      formData.append('itemId', itemId);
      
      fetch('add-product.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayItemDetailsModal(data.data);
        } else {
          showToast(data.message || 'An error occurred.', 'error');
        }
      })
      .catch(error => {
        console.error('Error fetching item details:', error);
        showToast('An error occurred. Please try again.', 'error');
      });
    }
    
    function displayItemDetailsModal(item) {
      // Store current item ID for edit button
      currentItemId = item.id;
      
      // Set item header
      document.getElementById('modalItemName').textContent = 'Item Details';
      document.getElementById('modalItemTitle').textContent = item.product_name;
      document.getElementById('modalItemId').textContent = `ID: ${item.product_id}`;
      
      const createdDate = new Date(item.created_at);
      document.getElementById('modalItemDate').textContent = `Added: ${createdDate.toLocaleDateString()}`;
      
      // Set status badge
      const statusElement = document.getElementById('modalItemStatus');
      let statusClass = 'bg-gray-100 text-gray-800';
      if (item.status === 'Available') {
        statusClass = 'status-available';
      } else if (item.status === 'Pending') {
        statusClass = 'status-pending';
      } else if (item.status === 'Sold') {
        statusClass = 'status-sold';
      }
      statusElement.className = `text-xs px-2 py-0.5 rounded-full ${statusClass}`;
      statusElement.textContent = item.status;
      
      // Set material details
      document.getElementById('modalMaterial').textContent = item.material_type;
      document.getElementById('modalPurity').textContent = `${item.purity}%`;
      document.getElementById('modalHUID').textContent = item.huid_code || 'N/A';
      
      // Set weight details
      document.getElementById('modalGrossWeight').textContent = `${parseFloat(item.gross_weight).toFixed(2)}g`;
      document.getElementById('modalLessWeight').textContent = `${parseFloat(item.less_weight).toFixed(2)}g`;
      document.getElementById('modalNetWeight').textContent = `${parseFloat(item.net_weight).toFixed(2)}g`;
      
      // Set source details
      document.getElementById('modalSourceType').textContent = item.source_type;
      
      if (item.source_type === 'Supplier') {
        document.getElementById('modalSourceName').textContent = item.supplier_name || 'N/A';
        document.getElementById('modalInvoiceNo').textContent = item.transaction_id || 'N/A';
      } else if (item.source_type === 'Karigar') {
        document.getElementById('modalSourceName').textContent = item.karigar_name || 'N/A';
        document.getElementById('modalInvoiceNo').textContent = item.manufacturing_order_id || 'N/A';
      } else {
        document.getElementById('modalSourceName').textContent = 'N/A';
        document.getElementById('modalInvoiceNo').textContent = item.transaction_id || 'N/A';
      }
      
      // Set stone details
      document.getElementById('modalStoneType').textContent = item.stone_type;
      document.getElementById('modalStoneWeight').textContent = item.stone_type !== 'None' ? `${parseFloat(item.stone_weight).toFixed(2)}ct` : 'N/A';
      document.getElementById('modalStoneQuality').textContent = item.stone_quality || 'N/A';
      document.getElementById('modalStonePrice').textContent = item.stone_price ? `₹${parseFloat(item.stone_price).toFixed(2)}` : 'N/A';
      
      // Set making details
      document.getElementById('modalMakingCharge').textContent = `₹${parseFloat(item.making_charge).toFixed(2)}`;
      document.getElementById('modalMakingType').textContent = item.making_charge_type === 'fixed' ? 'Fixed' : 'Percentage';
      document.getElementById('modalTrayNo').textContent = item.Tray_no || 'N/A';
      document.getElementById('modalQuantity').textContent = item.quantity;
      
      // Set notes
      document.getElementById('modalNotes').textContent = item.description || 'No notes available.';
      
      // Set images
      const imageGallery = document.getElementById('modalImageGallery');
      imageGallery.innerHTML = '';
      
      if (item.images && item.images.length > 0) {
        // Set main image
        const primaryImage = item.images.find(img => img.is_primary === '1') || item.images[0];
        document.getElementById('modalItemImage').src = primaryImage.image_url;
        
        // Add all images to gallery
        item.images.forEach(image => {
          const imgContainer = document.createElement('div');
          imgContainer.className = 'w-20 h-20 bg-white rounded-md overflow-hidden border border-gray-200';
          
          const img = document.createElement('img');
          img.src = image.image_url;
          img.alt = item.product_name;
          img.className = 'w-full h-full object-cover';
          
          imgContainer.appendChild(img);
          imageGallery.appendChild(imgContainer);
        });
      } else {
        document.getElementById('modalItemImage').src = '/placeholder.svg';
        
        const noImagesMsg = document.createElement('div');
        noImagesMsg.className = 'text-sm text-gray-500';
        noImagesMsg.textContent = 'No images available.';
        imageGallery.appendChild(noImagesMsg);
      }
      
      // Show modal without overlay
      document.getElementById('itemDetailsModal').classList.remove('hidden');
      
      // Add event listeners
      document.getElementById('modalCloseBtn').addEventListener('click', closeItemDetailsModal);
      document.getElementById('modalEditBtn').addEventListener('click', function() {
        closeItemDetailsModal();
        editItem(currentItemId);
      });
      document.getElementById('modalPrintBtn').addEventListener('click', function() {
        window.open(`https://jewelentry.prosenjittechhub.com/tag_print.php?id=${currentItemId}`, '_blank');
      });
    }
    
    function closeItemDetailsModal() {
      document.getElementById('itemDetailsModal').classList.add('hidden');
    }
    
    // Delete Item
    function showDeleteConfirmation(itemId) {
      currentItemId = itemId;
      document.getElementById('deleteModalOverlay').classList.remove('hidden');
      document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
      document.getElementById('deleteModalOverlay').classList.add('hidden');
      document.getElementById('deleteModal').classList.add('hidden');
      currentItemId = null;
    }

    document.getElementById('confirmDelete').addEventListener('click', function() {
      if (currentItemId) {
        deleteItem(currentItemId);
      }
    });

    document.getElementById('cancelDelete').addEventListener('click', closeDeleteModal);
    document.getElementById('deleteModalOverlay').addEventListener('click', closeDeleteModal);

    function deleteItem(itemId) {
      // Show loading state
      document.getElementById('confirmDelete').disabled = true;
      document.getElementById('confirmDelete').innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...';

      const formData = new FormData();
      formData.append('action', 'delete_item');
      formData.append('itemId', itemId);

      fetch('add-product.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => {
            throw new Error(err.message || 'Server returned ' + response.status);
          });
        }
        return response.json();
      })
      .then(data => {
        if (!data.success) {
          throw new Error(data.message || 'Failed to delete item');
        }
        
        closeDeleteModal();
        showToast(data.message || 'Item deleted successfully', 'success');
        loadItems(currentPage); // Maintain current page after deletion

        // Close details modal if the deleted item is currently displayed
        if (currentItemId === itemId && !document.getElementById('itemDetailsModal').classList.contains('hidden')) {
          closeItemDetailsModal();
        }
      })
      .catch(error => {
        console.error('Error deleting item:', error);
        showToast(error.message || 'An error occurred while deleting the item', 'error');
        closeDeleteModal();
      })
      .finally(() => {
        // Reset button state
        document.getElementById('confirmDelete').disabled = false;
        document.getElementById('confirmDelete').innerHTML = 'Delete';
      });
    }
    
    // Search and Filter
    document.getElementById('searchItems').addEventListener('input', debounce(function() {
      loadItems(1);
    }, 300));
    
    document.getElementById('filterMaterial').addEventListener('change', function() {
      loadItems(1);
    });
    
    document.getElementById('filterJewelryType').addEventListener('change', function() {
      loadItems(1);
    });
    
    document.getElementById('filterSource').addEventListener('change', function() {
      loadItems(1);
    });
    
    document.getElementById('filterStatus').addEventListener('change', function() {
      loadItems(1);
    });
    
    document.getElementById('resetFilters').addEventListener('click', function() {
      document.getElementById('searchItems').value = '';
      document.getElementById('filterMaterial').value = '';
      document.getElementById('filterJewelryType').value = '';
      document.getElementById('filterSource').value = '';
      document.getElementById('filterStatus').value = '';
      loadItems(1);
    });
    
    // Export to CSV
    document.getElementById('exportBtn').addEventListener('click', function() {
      if (itemsData.length === 0) {
        showToast('No items to export.', 'warning');
        return;
      }
      
      // Create CSV content
      let csvContent = 'Product ID,Product Name,Material,Purity,Gross Weight,Net Weight,Stone Type,Source Type,Status,Date\n';
      
      itemsData.forEach(item => {
        const row = [
          item.product_id,
          item.product_name,
          item.material_type,
          item.purity,
          item.gross_weight,
          item.net_weight,
          item.stone_type,
          item.source_type,
          item.status,
          new Date(item.created_at).toLocaleDateString()
        ];
        
        // Escape commas and quotes
        const escapedRow = row.map(cell => {
          if (cell === null || cell === undefined) return '';
          const cellStr = String(cell);
          if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
            return `"${cellStr.replace(/"/g, '""')}"`;
          }
          return cellStr;
        });
        
        csvContent += escapedRow.join(',') + '\n';
      });
      
      // Create download link
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `jewelry_items_${new Date().toISOString().slice(0, 10)}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
    
    // Utility function for debouncing
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }
    
    // Load items on page load
    loadItems();

    // Add JS to fetch and display product_id when jewelryType changes
    const jewelryTypeInput = document.getElementById('jewelryType');
    const productIdDisplay = document.getElementById('productIdDisplay');

    function fetchAndDisplayProductId() {
      const jewelryType = jewelryTypeInput.value.trim();
      if (!jewelryType) {
        if (productIdDisplay) productIdDisplay.value = '';
        return;
      }
      const formData = new FormData();
      formData.append('action', 'get_next_product_id');
      formData.append('jewelryType', jewelryType);
      fetch('add-product.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (productIdDisplay) productIdDisplay.value = data.product_id;
        } else {
          if (productIdDisplay) productIdDisplay.value = '';
        }
      })
      .catch(() => {
        if (productIdDisplay) productIdDisplay.value = '';
      });
    }

    jewelryTypeInput.addEventListener('input', fetchAndDisplayProductId);
    jewelryTypeInput.addEventListener('change', fetchAndDisplayProductId);

    // After defining fetchAndDisplayProductId and its event listeners:
    if (jewelryTypeInput.value.trim()) fetchAndDisplayProductId();
    // In clearForm(), after resetting jewelryType:
    fetchAndDisplayProductId();

    // --- Keyboard navigation for Jewelry Type suggestions ---
    let jewelryTypeHighlightedIndex = -1;

    jewelryTypeInput.addEventListener('keydown', function(e) {
      const items = Array.from(jewelryTypeSuggestions.children);
      if (!items.length || jewelryTypeSuggestions.classList.contains('hidden')) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        jewelryTypeHighlightedIndex = (jewelryTypeHighlightedIndex + 1) % items.length;
        updateJewelryTypeSuggestionHighlight(items);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        jewelryTypeHighlightedIndex = (jewelryTypeHighlightedIndex - 1 + items.length) % items.length;
        updateJewelryTypeSuggestionHighlight(items);
      } else if (e.key === 'Enter') {
        if (jewelryTypeHighlightedIndex >= 0 && jewelryTypeHighlightedIndex < items.length) {
          e.preventDefault();
          items[jewelryTypeHighlightedIndex].click();
          jewelryTypeSuggestions.classList.add('hidden');
        }
      } else {
        jewelryTypeHighlightedIndex = -1;
        updateJewelryTypeSuggestionHighlight(items);
      }
    });

    function updateJewelryTypeSuggestionHighlight(items) {
      items.forEach((item, idx) => {
        if (idx === jewelryTypeHighlightedIndex) {
          item.classList.add('selected-item');
          item.scrollIntoView({ block: 'nearest' });
        } else {
          item.classList.remove('selected-item');
        }
      });
    }

    // When rendering suggestions:
    function renderJewelryTypeSuggestions(suggestions) {
      jewelryTypeSuggestions.innerHTML = '';
      suggestions.forEach((type, idx) => {
        const div = document.createElement('div');
        div.textContent = type.name;
        div.classList.add('suggestion-item');
        div.addEventListener('click', function() {
          jewelryTypeInput.value = type.name;
          jewelryTypeSuggestions.classList.add('hidden');
          jewelryTypeHighlightedIndex = -1;
          updateJewelryTypeSuggestionHighlight([]);
        });
        div.addEventListener('mouseenter', function() {
          jewelryTypeHighlightedIndex = idx;
          updateJewelryTypeSuggestionHighlight(Array.from(jewelryTypeSuggestions.children));
        });
        jewelryTypeSuggestions.appendChild(div);
      });
      jewelryTypeSuggestions.classList.remove('hidden');
      jewelryTypeHighlightedIndex = -1;
      updateJewelryTypeSuggestionHighlight(Array.from(jewelryTypeSuggestions.children));
    }

    // Add missing resetSourceInfo function
    function resetSourceInfo() {
      // Reset hidden fields
      if (sourceNameField) sourceNameField.value = "";
      if (sourceLocationField) sourceLocationField.value = "";
      if (sourceMaterialTypeField) sourceMaterialTypeField.value = "";
      if (sourcePurityField) sourcePurityField.value = "";
      if (sourceWeightField) sourceWeightField.value = "";
      if (sourceInventoryIdField) sourceInventoryIdField.value = "";

      // Reset display
      if (sourceInfoDisplay) sourceInfoDisplay.classList.add("hidden");
      if (sourceNameDisplay) sourceNameDisplay.textContent = "-";
      if (sourceTypeDisplay) sourceTypeDisplay.textContent = "-";
      if (sourceMaterialDisplay) sourceMaterialDisplay.textContent = "-";
      if (sourcePurityDisplay) sourceMaterialDisplay.textContent = "-";
      if (sourceWeightDisplay) sourceWeightDisplay.textContent = "-";
      if (sourceStatusDisplay) sourceStatusDisplay.textContent = "-";
      if (sourceInvoiceNoDisplay) sourceInvoiceNoDisplay.textContent = "-";

      // Hide preserved indicator
      const preservedIndicator = document.getElementById('sourcePreservedIndicator');
      if (preservedIndicator) preservedIndicator.classList.add('hidden');

      // Clear source ID field
      if (sourceId) sourceId.value = "";
    }

          // Add reset source button functionality
      document.addEventListener('DOMContentLoaded', function() {
        const resetSourceBtn = document.getElementById('resetSourceBtn');
        if (resetSourceBtn) {
          resetSourceBtn.addEventListener('click', function() {
            console.log('Resetting source information...');
            resetSourceInfo();
            // Also reset the source type dropdown to default
            if (sourceTypeSelect) sourceTypeSelect.value = 'Manufacturing Order';
            if (sourceTypeField) sourceTypeField.value = 'Manufacturing Order';
            showToast('Source information reset successfully', 'info');
          });
        }
      });
  });
