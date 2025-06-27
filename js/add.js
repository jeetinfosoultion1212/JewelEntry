document.addEventListener("DOMContentLoaded", () => {
  console.log("Jewelry Management System JavaScript initialized")

  // Global variables
  let currentItemId = null
  let itemsData = []
  let currentPage = 1
  const itemsPerPage = 10
  let stream = null
  let currentFacingMode = "environment" // Default to back camera
  
  // Add variables to track which fields should be preserved
  let keepSourceSelection = true
  let keepMaterialSelection = true
  let keepPuritySelection = true

  // Toast notification system
  function showToast(message, type = "info", duration = 3000) {
    console.log(`Toast: ${type} - ${message}`)
    const toastContainer = document.getElementById("toastContainer")

    if (!toastContainer) {
      const container = document.createElement("div")
      container.id = "toastContainer"
      container.className = "toast-container"
      document.body.appendChild(container)
    }

    const toast = document.createElement("div")
    toast.className = `toast toast-${type}`

    let icon = ""
    if (type === "success") {
      icon = '<i class="fas fa-check-circle mr-2"></i>'
    } else if (type === "error") {
      icon = '<i class="fas fa-exclamation-circle mr-2"></i>'
    } else if (type === "warning") {
      icon = '<i class="fas fa-exclamation-triangle mr-2"></i>'
    } else {
      icon = '<i class="fas fa-info-circle mr-2"></i>'
    }

    toast.innerHTML = `${icon}<span>${message}</span>`
    document.getElementById("toastContainer").appendChild(toast)

    // Auto remove after duration
    setTimeout(() => {
      toast.style.animation = "fadeOut 0.3s ease-out forwards"
      setTimeout(() => {
        if (document.getElementById("toastContainer").contains(toast)) {
          document.getElementById("toastContainer").removeChild(toast)
        }
      }, 300)
    }, duration)
  }
  
  // Collapsible sections
  document.querySelectorAll('.section-collapse').forEach(header => {
    header.addEventListener('click', () => {
      const section = header.closest('.section-card');
      section.classList.toggle('collapsed');
    });
  });
    
  // Quick note toggle
  document.getElementById('quickNoteBtn').addEventListener('click', () => {
    document.getElementById('quickNoteSection').classList.toggle('hidden');
  });
    
  document.getElementById('closeQuickNote').addEventListener('click', () => {
    document.getElementById('quickNoteSection').classList.add('hidden');
  });
    
  // Image upload button
  document.getElementById('addImagesBtn').addEventListener('click', () => {
    document.getElementById('productImages').click();
  });
    
  // Add images preview functionality
  document.getElementById('productImages').addEventListener('change', function(event) {
    const container = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
      
    container.classList.remove('hidden');
      
    Array.from(event.target.files).forEach(file => {
      const reader = new FileReader();
      reader.onload = function(e) {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.className = 'h-12 w-12 object-cover rounded border border-gray-200';
        preview.appendChild(img);
      }
      reader.readAsDataURL(file);
    });
  });
    
  // Clear images
  document.getElementById('clearImages').addEventListener('click', () => {
    document.getElementById('imagePreview').innerHTML = '';
    document.getElementById('imagePreviewContainer').classList.add('hidden');
    document.getElementById('productImages').value = '';
  });
  


  
  // Source Type Dropdown Handling and Source Info Elements
  const sourceTypeSelect = document.getElementById("sourceTypeSelect")
  const sourceId = document.getElementById("sourceId")
  const sourceInfoDisplay = document.getElementById("sourceInfoDisplay")
  const sourceResetBtn = document.getElementById("sourceResetBtn")

  // Hidden fields
  const sourceTypeField = document.getElementById("sourceTypeHidden")
  const sourceNameField = document.getElementById("sourceName")
  const sourceLocationField = document.getElementById("sourceLocation")
  const sourceMaterialTypeField = document.getElementById("sourceMaterialType")
  const sourcePurityField = document.getElementById("sourcePurity")
  const sourceWeightField = document.getElementById("sourceWeight")
  const sourceInventoryIdField = document.getElementById("sourceInventoryId")

  // Display elements
  const sourceNameDisplay = document.getElementById("sourceNameDisplay")
  const sourceTypeDisplay = document.getElementById("sourceTypeDisplay")
  const sourceMaterialDisplay = document.getElementById("sourceMaterialDisplay")
  const sourcePurityDisplay = document.getElementById("sourcePurityDisplay")
  const sourceWeightDisplay = document.getElementById("sourceWeightDisplay")
  const sourceStatusDisplay = document.getElementById("sourceStatusDisplay")
  const sourceInvoiceNoDisplay = document.getElementById("sourceInvoiceNoDisplay")
  const minimizeSourceInfoBtn = document.getElementById("minimizeSourceInfoBtn")
  const expandSourceInfoBtn = document.getElementById("expandSourceInfoBtn")
  const sourceInfoMinimizedBar = document.getElementById("sourceInfoMinimizedBar")
  const sourceWeightMinimizedValue = document.getElementById("sourceWeightMinimizedValue")
  const sourceWeightMinimizedLabel = document.getElementById("sourceWeightMinimizedLabel")

  // Debug: Log if any important element is missing
  const debugElements = [
    ["sourceTypeSelect", sourceTypeSelect],
    ["sourceId", sourceId],
    ["sourceInfoDisplay", sourceInfoDisplay],
    ["sourceResetBtn", sourceResetBtn],
    ["sourceTypeField", sourceTypeField],
    ["sourceNameField", sourceNameField],
    ["sourceLocationField", sourceLocationField],
    ["sourceMaterialTypeField", sourceMaterialTypeField],
    ["sourcePurityField", sourcePurityField],
    ["sourceWeightField", sourceWeightField],
    ["sourceInventoryIdField", sourceInventoryIdField],
    ["sourceNameDisplay", sourceNameDisplay],
    ["sourceTypeDisplay", sourceTypeDisplay],
    ["sourceMaterialDisplay", sourceMaterialDisplay],
    ["sourcePurityDisplay", sourcePurityDisplay],
    ["sourceWeightDisplay", sourceWeightDisplay],
    ["sourceStatusDisplay", sourceStatusDisplay],
    ["sourceInvoiceNoDisplay", sourceInvoiceNoDisplay],
    ["minimizeSourceInfoBtn", minimizeSourceInfoBtn],
    ["expandSourceInfoBtn", expandSourceInfoBtn],
    ["sourceInfoMinimizedBar", sourceInfoMinimizedBar],
    ["sourceWeightMinimizedValue", sourceWeightMinimizedValue],
    ["sourceWeightMinimizedLabel", sourceWeightMinimizedLabel],
  ];
  for (var i = 0; i < debugElements.length; i++) {
    var name = debugElements[i][0];
    var el = debugElements[i][1];
    if (!el) console.warn("Element not found: " + name);
  }

  // Source reset button handler
  if (sourceResetBtn) {
    sourceResetBtn.addEventListener("click", function() {
      // Reset all selections
      keepSourceSelection = false
      keepMaterialSelection = false
      keepPuritySelection = false
      
      // Reset form completely
      clearForm(true); // true means force reset everything
      
      showToast("All selections have been reset", "info");
    });
  }

  if (sourceTypeSelect) {
    sourceTypeSelect.addEventListener("change", function () {
      const selectedType = this.value
      if (sourceTypeField) sourceTypeField.value = selectedType

      // Only reset fields if not keeping source selection
      if (!keepSourceSelection) {
        if (sourceId) sourceId.value = ""
        resetSourceInfo()
      }

      if (selectedType === "Manufacturing Order") {
        if (sourceId) sourceId.placeholder = "Search order ID..."
      } else if (selectedType === "Purchase") {
        if (sourceId) sourceId.placeholder = "Search invoice/batch..."
      } else {
        // Others
        if (sourceId) sourceId.placeholder = "Enter source ID..."
      }
    })
  } else {
    console.error("sourceTypeSelect element not found")
  }

  function resetSourceInfo() {
    // Reset hidden fields
    if (sourceNameField) sourceNameField.value = ""
    if (sourceLocationField) sourceLocationField.value = ""
    if (sourceMaterialTypeField) sourceMaterialTypeField.value = ""
    if (sourcePurityField) sourcePurityField.value = ""
    if (sourceWeightField) sourceWeightField.value = ""
    if (sourceInventoryIdField) sourceInventoryIdField.value = ""

    // Reset display
    if (sourceInfoDisplay) sourceInfoDisplay.classList.add("hidden")
    if (sourceNameDisplay) sourceNameDisplay.textContent = "-"
    if (sourceTypeDisplay) sourceTypeDisplay.textContent = "-"
    if (sourceMaterialDisplay) sourceMaterialDisplay.textContent = "-"
    if (sourcePurityDisplay) sourcePurityDisplay.textContent = "-"
    if (sourceWeightDisplay) sourceWeightDisplay.textContent = "-"
    if (sourceStatusDisplay) sourceStatusDisplay.textContent = "-"
    if (sourceInvoiceNoDisplay) sourceInvoiceNoDisplay.textContent = "-"
  }

  function updateSourceInfo(data, type) {
    if (!sourceInfoDisplay) {
      console.error("sourceInfoDisplay element not found")
      return
    }

    // Always show full info and hide minimized bar initially
    sourceInfoDisplay.classList.remove("hidden")
    if (sourceInfoMinimizedBar) sourceInfoMinimizedBar.classList.add("hidden")

    let remaining = null
    let weightLabel = "Weight Left:"

    if (type === "Manufacturing Order") {
      // Use net_weight or gross_weight as appropriate
      let weight = (typeof data.net_weight !== 'undefined') ? data.net_weight : data.gross_weight;
      // Update hidden fields
      if (sourceNameField) sourceNameField.value = data.karigar_name
      if (sourceLocationField) sourceLocationField.value = "Manufacturing"
      if (sourceMaterialTypeField) sourceMaterialTypeField.value = data.metal_type || "Gold" // Use actual metal_type if available
      if (sourcePurityField) sourcePurityField.value = data.purity
      if (sourceWeightField) sourceWeightField.value = weight

      // Update display
      if (sourceNameDisplay) sourceNameDisplay.textContent = data.karigar_name
      if (sourceTypeDisplay) sourceTypeDisplay.textContent = "Karigar"
      if (sourceMaterialDisplay) sourceMaterialDisplay.textContent = data.metal_type || "Gold"
      if (sourcePurityDisplay) sourcePurityDisplay.textContent = (data.purity !== undefined ? data.purity + "%" : "-")
      if (sourceWeightDisplay) sourceWeightDisplay.textContent = (weight !== undefined ? weight + "g" : "-")
      if (sourceStatusDisplay) sourceStatusDisplay.textContent = data.status || data.item_status || "-"

      // Auto-fill material and purity fields
      const materialType = document.getElementById("materialType")
      const purity = document.getElementById("purity")
      if (materialType && data.metal_type) materialType.value = data.metal_type
      if (purity && data.purity !== undefined) purity.value = data.purity
      
      // Set flags to keep these selections
      keepSourceSelection = true
      keepMaterialSelection = true
      keepPuritySelection = true
    } else if (type === "Purchase") {
      remaining = data.remaining_stock
      // Update hidden fields
      if (sourceNameField) sourceNameField.value = data.supplier_name
      if (sourceLocationField) sourceLocationField.value = "Purchase"
      if (sourceMaterialTypeField) sourceMaterialTypeField.value = data.material_type
      if (sourcePurityField) sourcePurityField.value = data.purity
      if (sourceWeightField) sourceWeightField.value = remaining
      if (sourceInventoryIdField) sourceInventoryIdField.value = data.inventory_id

      // Update display
      if (sourceNameDisplay) sourceNameDisplay.textContent = data.supplier_name
      if (sourceTypeDisplay) sourceTypeDisplay.textContent = "Supplier"
      if (sourceMaterialDisplay) sourceMaterialDisplay.textContent = data.material_type
      if (sourcePurityDisplay) sourcePurityDisplay.textContent = data.purity + "%"
      if (sourceWeightDisplay) sourceWeightDisplay.textContent = remaining + "g"
      if (sourceStatusDisplay) sourceStatusDisplay.textContent = "Available: " + remaining + "g"
      if (sourceInvoiceNoDisplay) sourceInvoiceNoDisplay.textContent = "Invoice: " + data.invoice_number

      // Auto-fill material and purity fields
      const materialType = document.getElementById("materialType")
      const purity = document.getElementById("purity")
      if (materialType) materialType.value = data.material_type
      if (purity) purity.value = data.purity
      
      // Set flags to keep these selections
      keepSourceSelection = true
      keepMaterialSelection = true
      keepPuritySelection = true
    }

    // Update minimized bar value
    if (sourceWeightMinimizedValue) sourceWeightMinimizedValue.textContent = (remaining !== null ? remaining + "g" : "-")
    if (sourceWeightMinimizedLabel) sourceWeightMinimizedLabel.textContent = weightLabel
  }

  // Collapsible logic for Source Info
  if (minimizeSourceInfoBtn && sourceInfoDisplay && sourceInfoMinimizedBar) {
    minimizeSourceInfoBtn.addEventListener("click", function() {
      sourceInfoDisplay.classList.add("hidden")
      sourceInfoMinimizedBar.classList.remove("hidden")
    })
  }
  if (expandSourceInfoBtn && sourceInfoDisplay && sourceInfoMinimizedBar) {
    expandSourceInfoBtn.addEventListener("click", function() {
      sourceInfoDisplay.classList.remove("hidden")
      sourceInfoMinimizedBar.classList.add("hidden")
    })
  }

  // Tab Switching Logic
  const formTab = document.getElementById("formTab")
  const listTab = document.getElementById("listTab")
  const formSection = document.getElementById("formSection")
  const listSection = document.getElementById("listSection")

  function switchTab(activeTab, activeSection, inactiveTab, inactiveSection) {
    if (!activeTab || !activeSection || !inactiveTab || !inactiveSection) {
      console.error("One or more tab elements not found")
      return
    }

    // Update tab styles
    activeTab.classList.add("tab-active")
    activeTab.classList.remove("text-gray-500")
    inactiveTab.classList.remove("tab-active")
    inactiveTab.classList.add("text-gray-500")

    // Show/hide sections with animation
    activeSection.classList.remove("hidden")
    inactiveSection.classList.add("hidden")

    // Refresh the items list if switching to list view
    if (activeSection === listSection) {
      loadItems()
    }
  }

  if (formTab && listTab) {
    formTab.addEventListener("click", () => {
      switchTab(formTab, formSection, listTab, listSection)
    })

    listTab.addEventListener("click", () => {
      switchTab(listTab, listSection, formTab, formSection)
    })
  } else {
    console.error("Tab elements not found")
  }

  // Source ID Autocomplete with intelligent filtering
  if (sourceId) {
    sourceId.addEventListener("input", function () {
      if (this.value.length > 0) {
        const sourceType = sourceTypeSelect ? sourceTypeSelect.value : ""
        const purity = document.getElementById("purity") ? document.getElementById("purity").value : ""

        if (sourceType === "Manufacturing Order") {
          fetchManufacturingOrders(this.value, purity)
        } else if (sourceType === "Purchase") {
          fetchMetalPurchases(this.value, purity)
        } else {
          const sourceIdSuggestions = document.getElementById("sourceIdSuggestions")
          if (sourceIdSuggestions) sourceIdSuggestions.classList.add("hidden")
        }
      } else {
        const sourceIdSuggestions = document.getElementById("sourceIdSuggestions")
        if (sourceIdSuggestions) {
          sourceIdSuggestions.innerHTML = ""
          sourceIdSuggestions.classList.add("hidden")
        }
      }
    })
  }

  function fetchManufacturingOrders(search, purity = "") {
    const formData = new FormData()
    formData.append("action", "get_manufacturing_orders")
    formData.append("search", search)
    if (purity) {
      formData.append("purity", purity)
    }

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        const suggestions = document.getElementById("sourceIdSuggestions")
        if (!suggestions) {
          console.error("sourceIdSuggestions element not found")
          return
        }

        if (data.success && data.data.length > 0) {
          suggestions.innerHTML = ""

          data.data.forEach((order) => {
            const div = document.createElement("div")
            div.className = "p-2 hover:bg-gray-100 cursor-pointer text-xs"

            // Create a badge for the status
            let statusBadge = ""
            if (order.status === "Completed") {
              statusBadge =
                '<span class="px-1 py-0.5 bg-green-100 text-green-800 rounded-full text-xs">Completed</span>'
            } else if (order.status === "Pending") {
              statusBadge =
                '<span class="px-1 py-0.5 bg-yellow-100 text-yellow-800 rounded-full text-xs">Pending</span>'
            }

            div.innerHTML = `<div class="flex justify-between items-center">
                <strong>Order ID: ${order.id}</strong>
                ${statusBadge}
              </div>
              <div>${order.karigar_name} - ${order.expected_weight}g (${order.purity_out}%)</div>`

            div.addEventListener("click", () => {
              if (sourceId) sourceId.value = order.id
              updateSourceInfo(order, "Manufacturing Order")
              suggestions.classList.add("hidden")
            })

            suggestions.appendChild(div)
          })

          suggestions.classList.remove("hidden")
        } else {
          suggestions.innerHTML = ""

          const div = document.createElement("div")
          div.className = "p-2 text-xs text-gray-500"
          div.textContent = "No matching orders found."

          suggestions.appendChild(div)
          suggestions.classList.remove("hidden")
        }
      })
      .catch((error) => {
        console.error("Error fetching manufacturing orders:", error)
      })
  }

  function fetchMetalPurchases(search, purity = "") {
    const formData = new FormData()
    formData.append("action", "get_metal_purchases")
    formData.append("search", search)
    if (purity) {
      formData.append("purity", purity)
    }

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        const suggestions = document.getElementById("sourceIdSuggestions")
        if (!suggestions) {
          console.error("sourceIdSuggestions element not found")
          return
        }

        if (data.success && data.data.length > 0) {
          suggestions.innerHTML = ""

          data.data.forEach((purchase) => {
            const div = document.createElement("div")
            div.className = "p-2 hover:bg-gray-100 cursor-pointer text-xs"

            div.innerHTML = `<div class="flex justify-between items-center">
                <strong>${purchase.invoice_number || "Batch: " + purchase.purchase_id}</strong>
                <span class="px-1 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs">Stock: ${purchase.remaining_stock}g</span>
              </div>
              <div>${purchase.supplier_name} - ${purchase.material_type} - ${purchase.weight}g (${purchase.purity}%)</div>`

            div.addEventListener("click", () => {
              if (sourceId) sourceId.value = purchase.purchase_id
              updateSourceInfo(purchase, "Purchase")
              suggestions.classList.add("hidden")
            })

            suggestions.appendChild(div)
          })

          suggestions.classList.remove("hidden")
        } else {
          suggestions.innerHTML = ""

          const div = document.createElement("div")
          div.className = "p-2 text-xs text-gray-500"
          div.textContent = "No matching purchases with available stock found."

          suggestions.appendChild(div)
          suggestions.classList.remove("hidden")
        }
      })
      .catch((error) => {
        console.error("Error fetching metal purchases:", error)
      })
  }

  // Tray Number Suggestions
  const trayNo = document.getElementById("trayNo")
  const trayNoSuggestions = document.getElementById("trayNoSuggestions")

  if (trayNo && trayNoSuggestions) {
    trayNo.addEventListener("input", function () {
      if (this.value.length > 0) {
        fetchTraySuggestions(this.value)
      } else {
        trayNoSuggestions.innerHTML = ""
        trayNoSuggestions.classList.add("hidden")
      }
    })
  }

  function fetchTraySuggestions(search) {
    const formData = new FormData()
    formData.append("action", "get_tray_suggestions")
    formData.append("search", search)

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (!trayNoSuggestions) {
          console.error("trayNoSuggestions element not found")
          return
        }

        if (data.success && data.data.length > 0) {
          trayNoSuggestions.innerHTML = ""

          data.data.forEach((tray) => {
            const div = document.createElement("div")
            div.className = "p-2 hover:bg-gray-100 cursor-pointer text-xs"

            div.innerHTML = `<div class="flex justify-between items-center">
                <strong>${tray.tray_number}</strong>
                <span class="px-1 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs">${tray.tray_type}</span>
              </div>
              <div>Location: ${tray.location} | Capacity: ${tray.capacity}</div>`

            div.addEventListener("click", () => {
              if (trayNo) trayNo.value = tray.tray_number
              trayNoSuggestions.classList.add("hidden")
            })

            trayNoSuggestions.appendChild(div)
          })

          trayNoSuggestions.classList.remove("hidden")
        } else {
          trayNoSuggestions.innerHTML = ""

          const div = document.createElement("div")
          div.className = "p-2 text-xs text-gray-500"
          div.textContent = "No matching trays found."

          trayNoSuggestions.appendChild(div)
          trayNoSuggestions.classList.remove("hidden")
        }
      })
      .catch((error) => {
        console.error("Error fetching tray suggestions:", error)
      })
  }

  // Update purity field to trigger source suggestions update
  const purityField = document.getElementById("purity")
  if (purityField) {
    purityField.addEventListener("change", function () {
      // If source ID has a value, refresh suggestions based on new purity
      if (sourceId && sourceId.value.length > 0) {
        const sourceType = sourceTypeSelect ? sourceTypeSelect.value : ""
        if (sourceType === "Manufacturing Order") {
          fetchManufacturingOrders(sourceId.value, this.value)
        } else if (sourceType === "Purchase") {
          fetchMetalPurchases(sourceId.value, this.value)
        }
      }
    })
  }

  // Stone Type Toggle Logic
  const stoneType = document.getElementById("stoneType")
  const stoneWeight = document.getElementById("stoneWeight")
  const stoneUnit = document.getElementById("stoneUnit")
  const stoneColor = document.getElementById("stoneColor")
  const stoneClarity = document.getElementById("stoneClarity")
  const stoneQuality = document.getElementById("stoneQuality")
  const stonePrice = document.getElementById("stonePrice")

  if (stoneType) {
    stoneType.addEventListener("change", function () {
      if (this.value === "None") {
        if (stoneWeight) {
          stoneWeight.disabled = true
          stoneWeight.value = ""
        }
        if (stoneUnit) {
          stoneUnit.disabled = true
          stoneUnit.value = "ct"
        }
        if (stoneColor) {
          stoneColor.disabled = true
          stoneColor.value = ""
        }
        if (stoneClarity) {
          stoneClarity.disabled = true
          stoneClarity.value = ""
        }
        if (stoneQuality) {
          stoneQuality.disabled = true
          stoneQuality.value = ""
        }
        if (stonePrice) {
          stonePrice.disabled = true
          stonePrice.value = ""
        }
      } else {
        if (stoneWeight) stoneWeight.disabled = false
        if (stoneUnit) stoneUnit.disabled = false
        if (stoneColor) stoneColor.disabled = false
        if (stoneClarity) stoneClarity.disabled = false
        if (stoneQuality) stoneQuality.disabled = false
        if (stonePrice) stonePrice.disabled = false
      }
    })
  }

  // Net Weight Calculation
  const grossWeight = document.getElementById("grossWeight")
  const lessWeight = document.getElementById("lessWeight")
  const netWeight = document.getElementById("netWeight")

  if (grossWeight && lessWeight) {
    ;[grossWeight, lessWeight].forEach((field) => {
      field.addEventListener("input", calculateNetWeight)
    })
  }

  function calculateNetWeight() {
    if (!grossWeight || !lessWeight || !netWeight) return

    const gross = Number.parseFloat(grossWeight.value) || 0
    const less = Number.parseFloat(lessWeight.value) || 0

    if (gross >= less) {
      const net = gross - less
      netWeight.value = net.toFixed(3)
    } else {
      netWeight.value = "0.000"
      showToast("Less weight cannot be greater than gross weight", "warning")
    }
  }

  // Update less weight based on stone weight and unit
  function updateLessWeightFromStone() {
    if (!stoneType || !stoneWeight || !stoneUnit || !lessWeight) return

    if (stoneType.value === "None" || !stoneWeight.value) {
      return
    }

    const stoneWeightValue = Number.parseFloat(stoneWeight.value) || 0
    let stoneWeightInGrams = 0

    // Convert stone weight to grams based on unit
    if (stoneUnit.value === "ct") {
      // 1 carat = 0.2 grams
      stoneWeightInGrams = stoneWeightValue * 0.2
    } else if (stoneUnit.value === "ratti") {
      // 1 ratti = 0.18 grams (approximate)
      stoneWeightInGrams = stoneWeightValue * 0.18
    }

    // Update less weight (add stone weight to existing less weight)
    const newLessWeight = stoneWeightInGrams

    // Only update if there's a change
    if (newLessWeight > 0) {
      lessWeight.value = newLessWeight.toFixed(3)
      calculateNetWeight()
    }
  }

  // Quick Note Toggle
  const quickNoteBtn = document.getElementById("quickNoteBtn")
  const quickNoteSection = document.getElementById("quickNoteSection")
  const closeQuickNote = document.getElementById("closeQuickNote")
  const description = document.getElementById("description")

  if (quickNoteBtn && quickNoteSection && closeQuickNote) {
    quickNoteBtn.addEventListener("click", () => {
      quickNoteSection.classList.remove("hidden")
      quickNoteSection.classList.add("active")
      if (description) description.focus()
    })

    closeQuickNote.addEventListener("click", () => {
      quickNoteSection.classList.add("hidden")
      quickNoteSection.classList.remove("active")
    })
  }

  // Weight calculation
  if (grossWeight && lessWeight) {
    grossWeight.addEventListener("input", calculateNetWeight)
    lessWeight.addEventListener("input", calculateNetWeight)
  }

  // Stone weight and unit change
  if (stoneWeight && stoneUnit) {
    stoneWeight.addEventListener("input", updateLessWeightFromStone)
    stoneUnit.addEventListener("change", updateLessWeightFromStone)
  }

  // Image Upload Preview
  const productImages = document.getElementById("productImages")
  const imagePreview = document.getElementById("imagePreview")
  const clearImagesBtn = document.getElementById("clearImages")
  const capturedImageField = document.getElementById("capturedImage")

  if (productImages && imagePreview) {
    productImages.addEventListener("change", (e) => {
      for (const file of e.target.files) {
        if (file.type.match("image.*")) {
          const reader = new FileReader()

          reader.onload = (e) => {
            const imgContainer = document.createElement("div")
            imgContainer.className = "relative"

            const img = document.createElement("img")
            img.src = e.target.result
            img.className = "w-16 h-16 object-cover rounded-md border border-gray-200"

            const removeBtn = document.createElement("button")
            removeBtn.className =
              "absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-sm hover:bg-red-600"
            removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>'
            removeBtn.onclick = (e) => {
              e.preventDefault()
              imgContainer.remove()
            }

            imgContainer.appendChild(img)
            imgContainer.appendChild(removeBtn)
            imagePreview.appendChild(imgContainer)
          }

          reader.readAsDataURL(file)
        }
      }
    })
  }

  // Clear all images
  if (clearImagesBtn && imagePreview && productImages) {
    clearImagesBtn.addEventListener("click", () => {
      imagePreview.innerHTML = ""
      productImages.value = ""
      if (capturedImageField) capturedImageField.value = ""
    })
  }



  
  // Camera Capture Functionality
  const cameraModal = document.getElementById("cameraModal")
  const cameraFeed = document.getElementById("cameraFeed")
  const captureCanvas = document.getElementById("captureCanvas")
  const capturePreview = document.getElementById("capturePreview")
  const captureBtn = document.getElementById("captureBtn")
  const acceptCaptureBtn = document.getElementById("acceptCaptureBtn")
  const retakeCaptureBtn = document.getElementById("retakeCaptureBtn")
  const closeCameraBtn = document.getElementById("closeCameraBtn")
  const switchCameraBtn = document.getElementById("switchCameraBtn")
  const captureImageBtn = document.getElementById("captureImageBtn")

  // Open camera modal
  if (captureImageBtn) {
    captureImageBtn.addEventListener("click", () => {
      openCamera()
    })
  }

  // Close camera modal
  if (closeCameraBtn) {
    closeCameraBtn.addEventListener("click", () => {
      closeCamera()
    })
  }

  // Switch camera (front/back)
  if (switchCameraBtn) {
    switchCameraBtn.addEventListener("click", () => {
      currentFacingMode = currentFacingMode === "environment" ? "user" : "environment"
      closeCamera()
      openCamera()
    })
  }

  // Capture image
  if (captureBtn) {
    captureBtn.addEventListener("click", () => {
      captureImage()
    })
  }

  // Retake image
  if (retakeCaptureBtn && cameraFeed && captureBtn && capturePreview && acceptCaptureBtn) {
    retakeCaptureBtn.addEventListener("click", () => {
      // Show video feed and capture button
      cameraFeed.classList.remove("hidden")
      captureBtn.classList.remove("hidden")

      // Hide preview and accept/retake buttons
      capturePreview.classList.add("hidden")
      acceptCaptureBtn.classList.add("hidden")
      retakeCaptureBtn.classList.add("hidden")
    })
  }

  // Accept captured image
  if (acceptCaptureBtn && captureCanvas && capturedImageField && imagePreview) {
    acceptCaptureBtn.addEventListener("click", () => {
      const imageData = captureCanvas.toDataURL("image/jpeg")
      capturedImageField.value = imageData

      // Add to image preview
      const imgContainer = document.createElement("div")
      imgContainer.className = "relative"

      const img = document.createElement("img")
      img.src = imageData
      img.className = "w-16 h-16 object-cover rounded-md border border-gray-200"

      const removeBtn = document.createElement("button")
      removeBtn.className =
        "absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-sm hover:bg-red-600"
      removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>'
      removeBtn.onclick = (e) => {
        e.preventDefault()
        imgContainer.remove()
        capturedImageField.value = ""
      }

      const captureLabel = document.createElement("div")
      captureLabel.className =
        "absolute -bottom-1 -right-1 bg-purple-500 text-white rounded-full text-xs px-1 flex items-center justify-center shadow-sm"
      captureLabel.innerHTML = '<i class="fas fa-camera text-xs"></i>'

      imgContainer.appendChild(img)
      imgContainer.appendChild(removeBtn)
      imgContainer.appendChild(captureLabel)
      imagePreview.appendChild(imgContainer)

      closeCamera()
      showToast("Image captured successfully!", "success")
    })
  }

  // Open camera
  function openCamera() {
    if (!cameraModal || !cameraFeed || !captureBtn || !capturePreview || !acceptCaptureBtn || !retakeCaptureBtn) {
      console.error("Camera elements not found")
      return
    }

    cameraModal.classList.remove("hidden")

    // Show video feed and capture button
    cameraFeed.classList.remove("hidden")
    captureBtn.classList.remove("hidden")

    // Hide preview and accept/retake buttons
    capturePreview.classList.add("hidden")
    acceptCaptureBtn.classList.add("hidden")
    retakeCaptureBtn.classList.add("hidden")

    // Start camera
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      navigator.mediaDevices
        .getUserMedia({
          video: {
            facingMode: currentFacingMode,
            width: { ideal: 1280 },
            height: { ideal: 720 },
          },
          audio: false,
        })
        .then((mediaStream) => {
          stream = mediaStream
          cameraFeed.srcObject = mediaStream
          cameraFeed.play()
        })
        .catch((error) => {
          console.error("Error accessing camera:", error)
          showToast("Error accessing camera: " + error.message, "error")
          closeCamera()
        })
    } else {
      showToast("Your browser doesn't support camera access", "error")
      closeCamera()
    }
  }

  // Close camera
  function closeCamera() {
    if (!cameraModal) return

    cameraModal.classList.add("hidden")

    // Stop all tracks
    if (stream) {
      stream.getTracks().forEach((track) => track.stop())
      stream = null
    }
  }

  // Capture image
  function captureImage() {
    if (
      !captureCanvas ||
      !cameraFeed ||
      !capturePreview ||
      !cameraFeed ||
      !captureBtn ||
      !acceptCaptureBtn ||
      !retakeCaptureBtn
    ) {
      console.error("Capture elements not found")
      return
    }

    // Set canvas dimensions to match video
    captureCanvas.width = cameraFeed.videoWidth
    captureCanvas.height = cameraFeed.videoHeight

    // Draw video frame to canvas
    const context = captureCanvas.getContext("2d")
    context.drawImage(cameraFeed, 0, 0, captureCanvas.width, captureCanvas.height)

    // Show preview
    capturePreview.src = captureCanvas.toDataURL("image/jpeg")
    capturePreview.classList.remove("hidden")

    // Hide video feed and capture button
    cameraFeed.classList.add("hidden")
    captureBtn.classList.add("hidden")

    // Show accept/retake buttons
    acceptCaptureBtn.classList.remove("hidden")
    retakeCaptureBtn.classList.remove("hidden")
  }

  // Alert Modal
  function showAlert(title, message, type = "info") {
    const alertModal = document.getElementById("alertModal")
    const alertOverlay = document.getElementById("alertOverlay")
    const alertTitle = document.getElementById("alertTitle")
    const alertMessage = document.getElementById("alertMessage")
    const alertIcon = document.getElementById("alertIcon")
    const alertClose = document.getElementById("alertClose")

    if (!alertModal || !alertOverlay || !alertTitle || !alertMessage || !alertIcon) {
      console.error("Alert modal elements not found")
      return
    }

    alertTitle.textContent = title
    alertMessage.innerHTML = message

    // Set icon and color based on type
    if (type === "success") {
      alertIcon.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>'
    } else if (type === "error") {
      alertIcon.innerHTML = '<i class="fas fa-exclamation-circle text-red-500"></i>'
    } else if (type === "warning") {
      alertIcon.innerHTML = '<i class="fas fa-exclamation-triangle text-amber-500"></i>'
    } else {
      alertIcon.innerHTML = '<i class="fas fa-info-circle text-blue-500"></i>'
    }

    alertModal.classList.remove("hidden")
    alertOverlay.classList.remove("hidden")

    // Close alert on button click
    if (alertClose) {
      alertClose.onclick = () => {
        alertModal.classList.add("hidden")
        alertOverlay.classList.add("hidden")
      }
    }

    // Close alert on overlay click
    alertOverlay.onclick = () => {
      alertModal.classList.add("hidden")
      alertOverlay.classList.add("hidden")
    }
  }

  // Form Clear Button
  const clearFormBtn = document.getElementById("clearForm")
  if (clearFormBtn) {
    clearFormBtn.addEventListener("click", () => {
      clearForm()
    })
  }

  // Modified clearForm function to respect field preservation
  function clearForm(forceReset = false) {
    // Reset form to add mode
    const itemIdField = document.getElementById("itemId")
    const addItemBtn = document.getElementById("addItem")

    if (itemIdField) itemIdField.value = ""
    if (capturedImageField) capturedImageField.value = ""
    if (addItemBtn) addItemBtn.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> Add Item'

    // Get current values to preserve if needed
    const currentSourceType = sourceTypeSelect ? sourceTypeSelect.value : "";
    const currentSourceId = sourceId ? sourceId.value : "";
    const currentMaterialType = document.getElementById("materialType") ? document.getElementById("materialType").value : "";
    const currentPurity = document.getElementById("purity") ? document.getElementById("purity").value : "";

    // Reset all form fields
    document
      .querySelectorAll('#jewelryForm input:not([type="hidden"]), #jewelryForm select, #jewelryForm textarea')
      .forEach((element) => {
        // Skip fields that should be preserved
        if (!forceReset) {
          // Skip source fields if keeping source selection
          if (keepSourceSelection && (element.id === "sourceTypeSelect" || element.id === "sourceId")) {
            return;
          }
          
          // Skip material field if keeping material selection
          if (keepMaterialSelection && element.id === "materialType") {
            return;
          }
          
          // Skip purity field if keeping purity selection
          if (keepPuritySelection && element.id === "purity") {
            return;
          }
        }
        
        if (element.type !== "file") {
          if (element.id === "quantity") {
            element.value = "1"
          } else {
            element.value = ""
          }
        }
      })

    // Reset dropdowns to first option (except those being preserved)
    const materialTypeField = document.getElementById("materialType")
    const stoneTypeField = document.getElementById("stoneType")
    const makingChargeTypeField = document.getElementById("makingChargeType")
    const statusField = document.getElementById("status")
    const updateInventoryField = document.getElementById("updateInventory")

    // Only reset if not preserving or force reset
    if (forceReset || !keepMaterialSelection) {
      if (materialTypeField) materialTypeField.value = "Gold"
    }
    
    if (stoneTypeField) stoneTypeField.value = "None"
    if (makingChargeTypeField) makingChargeTypeField.value = "fixed"
    if (statusField) statusField.value = "Available"
    
    // Only reset source type if not preserving or force reset
    if (forceReset || !keepSourceSelection) {
      if (sourceTypeSelect) sourceTypeSelect.value = "Manufacturing Order"
    }
    
    if (updateInventoryField) updateInventoryField.checked = false

    // Reset source fields and display only if not preserving or force reset
    if (forceReset || !keepSourceSelection) {
      resetSourceInfo()
    }

    // Clear image previews
    if (imagePreview) imagePreview.innerHTML = ""
    if (productImages) productImages.value = ""

    // Hide quick note section
    if (quickNoteSection) quickNoteSection.classList.add("hidden")

    // Reset stone fields
    if (stoneWeight) stoneWeight.disabled = true
    if (stoneUnit) stoneUnit.disabled = true
    if (stoneColor) stoneColor.disabled = true
    if (stoneClarity) stoneClarity.disabled = true
    if (stoneQuality) stoneQuality.disabled = true
    if (stonePrice) stonePrice.disabled = true

    // Reset net weight
    if (netWeight) netWeight.value = ""

    // Reset product ID
    const productIdDisplay = document.getElementById("productIdDisplay")
    if (productIdDisplay) productIdDisplay.value = ""
    
    // Restore preserved values if not force reset
    if (!forceReset) {
      // Restore source fields if keeping source selection
      if (keepSourceSelection) {
        if (sourceTypeSelect) sourceTypeSelect.value = currentSourceType;
        if (sourceId) sourceId.value = currentSourceId;
        // Don't hide source info display if we're keeping the source
        if (sourceInfoDisplay) sourceInfoDisplay.classList.remove("hidden");
      }
      
      // Restore material field if keeping material selection
      if (keepMaterialSelection && materialTypeField) {
        materialTypeField.value = currentMaterialType;
      }
      
      // Restore purity field if keeping purity selection
      if (keepPuritySelection && purityField) {
        purityField.value = currentPurity;
      }
    }
  }

  // Jewelry Type Autocomplete
  const jewelryType = document.getElementById("jewelryType")
  const jewelryTypeSuggestions = document.getElementById("jewelryTypeSuggestions")
  const productIdDisplay = document.getElementById("productIdDisplay")

  if (jewelryType && jewelryTypeSuggestions) {
    jewelryType.addEventListener("input", function () {
      if (this.value.length > 1) {
        fetchJewelryTypes(this.value)
      } else {
        jewelryTypeSuggestions.innerHTML = ""
        jewelryTypeSuggestions.classList.add("hidden")
      }
    })
  }

  function fetchJewelryTypes(search) {
    const formData = new FormData()
    formData.append("action", "get_jewelry_types")
    formData.append("search", search)

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (!jewelryTypeSuggestions) {
          console.error("jewelryTypeSuggestions element not found")
          return
        }

        if (data.success && data.data.length > 0) {
          jewelryTypeSuggestions.innerHTML = ""

          data.data.forEach((type) => {
            const div = document.createElement("div")
            div.className = "p-2 hover:bg-gray-100 cursor-pointer text-xs"
            div.textContent = type.name
            div.addEventListener("click", () => {
              if (jewelryType) jewelryType.value = type.name
              jewelryTypeSuggestions.classList.add("hidden")
              fetchAndDisplayProductId()
            })

            jewelryTypeSuggestions.appendChild(div)
          })
          jewelryTypeSuggestions.classList.remove("hidden")
        } else {
          jewelryTypeSuggestions.innerHTML = ""

          const div = document.createElement("div")
          div.className = "p-2 text-xs text-gray-500"
          div.textContent = "No matches found. Type will be added as new."

          jewelryTypeSuggestions.appendChild(div)
          jewelryTypeSuggestions.classList.remove("hidden")
        }
      })
      .catch((error) => {
        console.error("Error fetching jewelry types:", error)
      })
  }

  // Validate Form
  function validateForm() {
    // Required fields
    const requiredFields = [
      { id: "materialType", label: "Material Type" },
      { id: "purity", label: "Purity" },
      { id: "jewelryType", label: "Jewelry Type" },
      { id: "productName", label: "Product Name" },
      { id: "grossWeight", label: "Gross Weight" },
      { id: "sourceId", label: "Source ID" },
    ]

    // Check stone fields if stone type is not None
    if (stoneType && stoneType.value !== "None") {
      requiredFields.push({ id: "stoneWeight", label: "Stone Weight" }, { id: "stoneQuality", label: "Stone Quality" })
    }

    let isValid = true
    const errorMessages = []

    // Check each required field
    requiredFields.forEach((field) => {
      const element = document.getElementById(field.id)
      if (element && !element.value.trim()) {
        element.classList.add("border-red-500")
        errorMessages.push(`${field.label} is required`)
        isValid = false
      } else if (element) {
        element.classList.remove("border-red-500")
      }
    })

    // Check if net weight is valid
    if (netWeight && Number.parseFloat(netWeight.value) <= 0) {
      netWeight.classList.add("border-red-500")
      errorMessages.push("Net weight must be greater than zero")
      isValid = false
    } else if (netWeight) {
      netWeight.classList.remove("border-red-500")
    }

    // For Purchase source type, check if there's enough stock
    if (
      sourceTypeSelect &&
      sourceTypeSelect.value === "Purchase" &&
      sourceInventoryIdField &&
      sourceInventoryIdField.value &&
      sourceStatusDisplay
    ) {
      const availableStockText = sourceStatusDisplay.textContent.replace("Available: ", "").replace("g", "")
      const availableStock = Number.parseFloat(availableStockText)
      const requiredStock = netWeight ? Number.parseFloat(netWeight.value) : 0

      if (requiredStock > availableStock) {
        if (netWeight) netWeight.classList.add("border-red-500")
        errorMessages.push(`Not enough stock available. Required: ${requiredStock}g, Available: ${availableStock}g`)
        isValid = false
      }
    }

    // Show error message if validation fails
    if (!isValid) {
      showAlert("Validation Error", errorMessages.join("<br>"), "error")
    }

    return isValid
  }

  // Add/Update Item
  const addItemBtn = document.getElementById("addItem")
  if (addItemBtn) {
    addItemBtn.addEventListener("click", () => {
      // Validate form
      if (!validateForm()) {
        return
      }

      const form = document.getElementById("jewelryForm")
      if (!form) {
        console.error("jewelryForm element not found")
        return
      }

      const formData = new FormData(form)

      // Add action based on whether we're adding or updating
      const itemId = document.getElementById("itemId")
      formData.append("action", itemId && itemId.value ? "update_item" : "add_item")
      if (itemId && itemId.value) {
        formData.append("itemId", itemId.value)
      }

      // For Purchase source type, add inventory ID for stock deduction
      if (
        sourceTypeSelect &&
        sourceTypeSelect.value === "Purchase" &&
        sourceInventoryIdField &&
        sourceInventoryIdField.value
      ) {
        formData.append("inventoryId", sourceInventoryIdField.value)
      }

      // Add images if any
      if (productImages && productImages.files.length > 0) {
        for (let i = 0; i < productImages.files.length; i++) {
          formData.append("images[]", productImages.files[i])
        }
      }

      // Submit form
      fetch("add.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`)
          }
          return response.json()
        })
        .then((data) => {
          if (data.success) {
            showToast(data.message, "success")
            
            // Clear form but preserve selected fields
            clearForm(false)
            
            loadItems()
          } else {
            showToast(data.message || "An error occurred.", "error")
          }
        })
        .catch((error) => {
          console.error("Error submitting form:", error)
          showToast("An error occurred. Please try again.", "error")
        })
    })
  }

  // Load Items
  function logAjaxResponse(action, response) {
    console.log(`AJAX ${action} response:`, response)
  }

  function loadItems(page = 1) {
    currentPage = page

    const formData = new FormData()
    formData.append("action", "get_items")
    formData.append("page", page)
    formData.append("limit", itemsPerPage)

    // Add filters
    const searchItems = document.getElementById("searchItems")
    const filterMaterial = document.getElementById("filterMaterial")
    const filterJewelryType = document.getElementById("filterJewelryType")
    const filterSource = document.getElementById("filterSource")
    const filterStatus = document.getElementById("filterStatus")

    if (searchItems && searchItems.value) formData.append("search", searchItems.value)
    if (filterMaterial && filterMaterial.value) formData.append("materialFilter", filterMaterial.value)
    if (filterJewelryType && filterJewelryType.value) formData.append("typeFilter", filterJewelryType.value)
    if (filterSource && filterSource.value) formData.append("sourceFilter", filterSource.value)
    if (filterStatus && filterStatus.value) formData.append("statusFilter", filterStatus.value)

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`)
        }
        return response.json()
      })
      .then((data) => {
        logAjaxResponse("get_items", data)
        if (data.success) {
          itemsData = data.data.items
          renderItems(data.data)

          // Update inventory stats if available
          if (data.data.inventoryStats) {
            updateInventoryStats(data.data.inventoryStats)
          }
        } else {
          console.error("Error loading items:", data.message)
          showToast("Failed to load items: " + (data.message || "Unknown error"), "error")
        }
      })
      .catch((error) => {
        console.error("Error loading items:", error)
        showToast("Failed to load items: " + error.message, "error")
      })
  }

 function updateInventoryStats(stats) {
  const statsScroll = document.querySelector(".stats-scroll")
  if (!statsScroll) return

  statsScroll.innerHTML = ""

  if (Object.keys(stats).length === 0) {
    const statItem = document.createElement("div")
    statItem.className = "stat-item px-3 py-1 bg-white"
    statItem.innerHTML = `
      <div class="stat-value font-medium text-indigo-800">0.00g</div>
      <div class="stat-label text-xs text-gray-600">No inventory</div>
    `
    statsScroll.appendChild(statItem)
  } else {
    for (const [material, stock] of Object.entries(stats)) {
      const statItem = document.createElement("div")
      statItem.className = "stat-item px-3 py-1 bg-white border-r border-indigo-100"
      statItem.innerHTML = `
        <div class="stat-value font-medium text-indigo-800">${Number.parseFloat(stock).toFixed(2)}g</div>
        <div class="stat-label text-xs text-gray-600">${material}</div>
      `
      statsScroll.appendChild(statItem)
    }
  }
}

function renderItems(data) {
  const tbody = document.querySelector("#itemsTable tbody")
  if (!tbody) {
    console.error("itemsTable tbody element not found")
    return
  }

  tbody.innerHTML = ""

  if (data.items.length === 0) {
    const tr = document.createElement("tr")
    tr.innerHTML = `
      <td colspan="5" class="py-4 text-center text-gray-500">No items found</td>
    `
    tbody.appendChild(tr)
  } else {
    data.items.forEach((item) => {
      const tr = document.createElement("tr")
      tr.className = "hover:bg-blue-50 transition-colors border-b border-gray-100"

      // Determine source badge color
      let sourceBadgeClass = "bg-gray-100 text-gray-800"
      if (item.source_type === "Supplier") {
        sourceBadgeClass = "bg-blue-100 text-blue-800"
      } else if (item.source_type === "Karigar") {
        sourceBadgeClass = "bg-orange-100 text-orange-800"
      }

      tr.innerHTML = `
        <td class="py-2 px-2 text-xs">${item.product_id}</td>
        <td class="py-2 px-2">
          <div class="flex items-center">
            <div class="w-6 h-6 bg-gray-200 rounded-md overflow-hidden mr-1 flex-shrink-0">
              <img src="${item.image_url || "uploads/jewelry/no_images.png"}" alt="" class="w-full h-full object-cover">
            </div>
            <div>
              <div class="text-xs font-medium">${item.product_name}</div>
              <div class="text-xs text-gray-500">${item.material_type} | ${item.purity}%</div>
            </div>
          </div>
        </td>
        <td class="py-2 px-2 text-xs">${Number.parseFloat(item.net_weight).toFixed(2)}g</td>
        <td class="py-2 px-2">
          <span class="text-2xs px-1 py-0.5 ${sourceBadgeClass} rounded-full">${item.source_type}</span>
        </td>
        <td class="py-2 px-2">
          <div class="flex justify-center space-x-1">
            <button class="text-blue-500 hover:bg-blue-50 p-1 rounded action-btn edit-btn" data-id="${item.id}" title="Edit">
              <i class="fas fa-edit"></i>
            </button>
            <button class="text-red-500 hover:bg-red-50 p-1 rounded action-btn delete-btn" data-id="${item.id}" title="Delete">
              <i class="fas fa-trash-alt"></i>
            </button>
            <button class="text-purple-500 hover:bg-purple-50 p-1 rounded action-btn view-btn" data-id="${item.id}" title="View">
              <i class="fas fa-eye"></i>
            </button>
            <button class="text-green-500 hover:text-green-700 print-btn" data-id="${item.id}" title="Print">
                  <i class="fas fa-print"></i>
                </button>
          </div>
        </td>
      `

      tbody.appendChild(tr)
    })

    // Add event listeners to buttons
    document.querySelectorAll(".edit-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        const itemId = this.getAttribute("data-id")
        editItem(itemId)
      })
    })

    document.querySelectorAll(".delete-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        const itemId = this.getAttribute("data-id")
        showDeleteConfirmation(itemId)
      })
    })


document.querySelectorAll(".print-btn").forEach((btn) => {
        btn.addEventListener("click", function () {
          const itemId = this.getAttribute("data-id")
          window.open(`print_tag.php?id=${itemId}`, "_blank")
        })
      })
    
    document.querySelectorAll(".view-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        const itemId = this.getAttribute("data-id")
        viewItemDetails(itemId)
      })
    })
  }


  // Update item count
  const itemCount = document.getElementById("itemCount")
  if (itemCount) itemCount.textContent = `${data.total} items`

  // Add pagination if needed
  if (data.totalPages > 1) {
    addPagination(data)
  }
}

function addPagination(data) {
  const paginationContainer = document.createElement("div")
  paginationContainer.className = "flex justify-center py-2 gap-1 border-t"

  // Previous button
  const prevButton = document.createElement("button")
  prevButton.className = "px-2 py-1 rounded bg-gray-200 text-xs"
  prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>'
  prevButton.disabled = data.page === 1
  if (prevButton.disabled) prevButton.classList.add("opacity-50")
  prevButton.addEventListener("click", () => loadItems(data.page - 1))

  // Next button
  const nextButton = document.createElement("button")
  nextButton.className = "px-2 py-1 rounded bg-gray-200 text-xs"
  nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>'
  nextButton.disabled = data.page === data.totalPages
  if (nextButton.disabled) nextButton.classList.add("opacity-50")
  nextButton.addEventListener("click", () => loadItems(data.page + 1))

  paginationContainer.appendChild(prevButton)

  // Page buttons - show limited number on mobile
  const maxPageButtons = window.innerWidth < 640 ? 3 : data.totalPages
  const startPage = Math.max(1, data.page - Math.floor(maxPageButtons / 2))
  const endPage = Math.min(data.totalPages, startPage + maxPageButtons - 1)
  
  if (startPage > 1) {
    const firstPageButton = document.createElement("button")
    firstPageButton.className = "px-2 py-1 rounded bg-gray-200 hover:bg-gray-300 text-xs"
    firstPageButton.textContent = "1"
    firstPageButton.addEventListener("click", () => loadItems(1))
    paginationContainer.appendChild(firstPageButton)
    
    if (startPage > 2) {
      const ellipsis = document.createElement("span")
      ellipsis.className = "px-1 text-gray-500 text-xs self-center"
      ellipsis.textContent = "..."
      paginationContainer.appendChild(ellipsis)
    }
  }

  for (let i = startPage; i <= endPage; i++) {
    const pageButton = document.createElement("button")
    pageButton.className = i === data.page
      ? "px-2 py-1 rounded bg-indigo-500 text-white text-xs"
      : "px-2 py-1 rounded bg-gray-200 hover:bg-gray-300 text-xs"
    pageButton.textContent = i
    pageButton.addEventListener("click", () => loadItems(i))
    paginationContainer.appendChild(pageButton)
  }
  
  if (endPage < data.totalPages) {
    if (endPage < data.totalPages - 1) {
      const ellipsis = document.createElement("span")
      ellipsis.className = "px-1 text-gray-500 text-xs self-center"
      ellipsis.textContent = "..."
      paginationContainer.appendChild(ellipsis)
    }
    
    const lastPageButton = document.createElement("button")
    lastPageButton.className = "px-2 py-1 rounded bg-gray-200 hover:bg-gray-300 text-xs"
    lastPageButton.textContent = data.totalPages
    lastPageButton.addEventListener("click", () => loadItems(data.totalPages))
    paginationContainer.appendChild(lastPageButton)
  }

  paginationContainer.appendChild(nextButton)

  // Add pagination to table container
  const tableContainer = document.querySelector(".table-container")
  if (!tableContainer) {
    console.error("table-container element not found")
    return
  }

  // Remove existing pagination if any
  const existingPagination = tableContainer.nextElementSibling
  if (existingPagination && existingPagination.classList.contains("flex")) {
    existingPagination.remove()
  }

  tableContainer.insertAdjacentElement("afterend", paginationContainer)
}

// Add responsive behavior for filters
document.addEventListener('DOMContentLoaded', function() {
  // Make sure filters and search expand properly on mobile
  const searchInput = document.getElementById('searchItems');
  if (searchInput) {
    searchInput.addEventListener('focus', function() {
      this.classList.add('w-full');
    });
    
    searchInput.addEventListener('blur', function() {
      if (this.value === '') {
        this.classList.remove('w-full');
      }
    });
  }
  
  // Initialize horizontal scroll indicators if needed
  const filterContainer = document.querySelector('.filter-scroll-container');
  const statsWrapper = document.querySelector('.stats-wrapper');
  
  [filterContainer, statsWrapper].forEach(container => {
    if (container) {
      // Check if scroll is needed and add visual indicator
      const checkScroll = () => {
        if (container.scrollWidth > container.clientWidth) {
          container.classList.add('scroll-indicator');
        } else {
          container.classList.remove('scroll-indicator');
        }
      };
      
      // Check on load and resize
      checkScroll();
      window.addEventListener('resize', checkScroll);
    }
  });
});

  // Edit Item
  function editItem(itemId) {
    const formData = new FormData()
    formData.append("action", "get_item_details")
    formData.append("itemId", itemId)

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          populateForm(data.data)
          showToast("Item loaded for editing", "success")

          // Switch to form tab
          if (formTab && formSection && listTab && listSection) {
            switchTab(formTab, formSection, listTab, listSection)
          }
        } else {
          showToast(data.message || "An error occurred.", "error")
        }
      })
      .catch((error) => {
        console.error("Error fetching item details:", error)
        showToast("An error occurred. Please try again.", "error")
      })
  }

  function populateForm(item) {
    // Set form to edit mode
    const itemIdField = document.getElementById("itemId")
    const addItemBtn = document.getElementById("addItem")

    if (itemIdField) itemIdField.value = item.id
    if (addItemBtn) addItemBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Update Item'

    // Set source type
    if (item.source_type === "Supplier") {
      if (sourceTypeSelect) sourceTypeSelect.value = "Purchase"
      if (sourceTypeField) sourceTypeField.value = "Purchase"
      if (sourceId) sourceId.value = item.supplier_id

      // Create a mock purchase object for display
      const purchaseData = {
        supplier_name: item.supplier_name || "Unknown Supplier",
        material_type: item.material_type,
        purity: item.purity,
        weight: item.net_weight,
        remaining_stock: item.net_weight, // Assuming same as item weight for edit
        invoice_number: "N/A",
      }

      updateSourceInfo(purchaseData, "Purchase")
    } else if (item.source_type === "Karigar") {
      if (sourceTypeSelect) sourceTypeSelect.value = "Manufacturing Order"
      if (sourceTypeField) sourceTypeField.value = "Manufacturing Order"
      if (sourceId) sourceId.value = item.karigar_id

      // Create a mock order object for display
      const orderData = {
        karigar_name: item.karigar_name || "Unknown Karigar",
        purity_out: item.purity,
        expected_weight: item.net_weight,
        status: "Completed",
      }

      updateSourceInfo(orderData, "Manufacturing Order")
    } else {
      if (sourceTypeSelect) sourceTypeSelect.value = "Others"
      if (sourceTypeField) sourceTypeField.value = "Others"
      if (sourceId) sourceId.value = ""
      resetSourceInfo()
    }

    // Set material details
    const materialType = document.getElementById("materialType")
    const purity = document.getElementById("purity")
    const jewelryTypeField = document.getElementById("jewelryType")
    const productName = document.getElementById("productName")
    const productIdDisplayField = document.getElementById("productIdDisplay")

    if (materialType) materialType.value = item.material_type
    if (purity) purity.value = item.purity
    if (jewelryTypeField) jewelryTypeField.value = item.jewelry_type
    if (productName) productName.value = item.product_name
    if (productIdDisplayField) productIdDisplayField.value = item.product_id

    // Set weight details
    if (grossWeight) grossWeight.value = item.gross_weight
    if (lessWeight) lessWeight.value = item.less_weight
    if (netWeight) netWeight.value = item.net_weight
    if (trayNo) trayNo.value = item.Tray_no
    const huidCode = document.getElementById("huidCode")
    if (huidCode) huidCode.value = item.huid_code

    // Set stone details
    if (stoneType) stoneType.value = item.stone_type
    if (item.stone_type !== "None") {
      if (stoneWeight) {
        stoneWeight.disabled = false
        stoneWeight.value = item.stone_weight
      }
      if (stoneUnit) {
        stoneUnit.disabled = false
        stoneUnit.value = item.stone_unit || "ct"
      }
      if (stoneColor) {
        stoneColor.disabled = false
        stoneColor.value = item.stone_color || ""
      }
      if (stoneClarity) {
        stoneClarity.disabled = false
        stoneClarity.value = item.stone_clarity || ""
      }
      if (stoneQuality) {
        stoneQuality.disabled = false
        stoneQuality.value = item.stone_quality || ""
      }
      if (stonePrice) {
        stonePrice.disabled = false
        stonePrice.value = item.stone_price
      }
    } else {
      if (stoneWeight) stoneWeight.disabled = true
      if (stoneUnit) stoneUnit.disabled = true
      if (stoneColor) stoneColor.disabled = true
      if (stoneClarity) stoneClarity.disabled = true
      if (stoneQuality) stoneQuality.disabled = true
      if (stonePrice) stonePrice.disabled = true
    }

    // Set making details
    const makingCharge = document.getElementById("makingCharge")
    const makingChargeType = document.getElementById("makingChargeType")
    const status = document.getElementById("status")
    const quantity = document.getElementById("quantity")

    if (makingCharge) makingCharge.value = item.making_charge
    if (makingChargeType) makingChargeType.value = item.making_charge_type
    if (status) status.value = item.status
    if (quantity) quantity.value = item.quantity

    // Set description
    if (description && item.description) {
      description.value = item.description
      if (quickNoteSection) quickNoteSection.classList.remove("hidden")
    } else if (description) {
      description.value = ""
      if (quickNoteSection) quickNoteSection.classList.add("hidden")
    }

    // Clear image preview
    if (imagePreview) imagePreview.innerHTML = ""

    // Add existing images to preview
    if (item.images && item.images.length > 0 && imagePreview) {
      item.images.forEach((image) => {
        const imgContainer = document.createElement("div")
        imgContainer.className = "relative"

        const img = document.createElement("img")
        img.src = image.image_url
        img.className = "w-16 h-16 object-cover rounded-md border border-gray-200"

        const removeBtn = document.createElement("button")
        removeBtn.className =
          "absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center shadow-sm hover:bg-red-600"
        removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>'
        removeBtn.onclick = (e) => {
          e.preventDefault()
          imgContainer.remove()
        }

        imgContainer.appendChild(img)
        imgContainer.appendChild(removeBtn)
        imagePreview.appendChild(imgContainer)
      })
    }

    // Scroll to form
    const formContainer = document.querySelector(".form-container")
    if (formContainer) formContainer.scrollIntoView({ behavior: "smooth" })
  }

  // View Item Details
  function viewItemDetails(itemId) {
    const formData = new FormData()
    formData.append("action", "get_item_details")
    formData.append("itemId", itemId)

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          displayItemDetailsModal(data.data)
        } else {
          showToast(data.message || "An error occurred.", "error")
        }
      })
      .catch((error) => {
        console.error("Error fetching item details:", error)
        showToast("An error occurred. Please try again.", "error")
      })
  }

  function displayItemDetailsModal(item) {
    // Store current item ID for edit button
    currentItemId = item.id

    const modalItemName = document.getElementById("modalItemName")
    const modalItemTitle = document.getElementById("modalItemTitle")
    const modalItemId = document.getElementById("modalItemId")
    const modalItemDate = document.getElementById("modalItemDate")
    const modalItemStatus = document.getElementById("modalItemStatus")
    const modalMaterial = document.getElementById("modalMaterial")
    const modalPurity = document.getElementById("modalPurity")
    const modalHUID = document.getElementById("modalHUID")
    const modalGrossWeight = document.getElementById("modalGrossWeight")
    const modalLessWeight = document.getElementById("modalLessWeight")
    const modalNetWeight = document.getElementById("modalNetWeight")
    const modalSourceType = document.getElementById("modalSourceType")
    const modalSourceName = document.getElementById("modalSourceName")
    const modalInvoiceNo = document.getElementById("modalInvoiceNo")
    const modalStoneType = document.getElementById("modalStoneType")
    const modalStoneWeight = document.getElementById("modalStoneWeight")
    const modalStoneQuality = document.getElementById("modalStoneQuality")
    const modalStonePrice = document.getElementById("modalStonePrice")
    const modalMakingCharge = document.getElementById("modalMakingCharge")
    const modalMakingType = document.getElementById("modalMakingType")
    const modalTrayNo = document.getElementById("modalTrayNo")
    const modalQuantity = document.getElementById("modalQuantity")
    const modalNotes = document.getElementById("modalNotes")
    const modalItemImage = document.getElementById("modalItemImage")
    const modalImageGallery = document.getElementById("modalImageGallery")
    const itemDetailsModal = document.getElementById("itemDetailsModal")
    const itemDetailsOverlay = document.getElementById("itemDetailsOverlay")
    const modalCloseBtn = document.getElementById("modalCloseBtn")
    const modalEditBtn = document.getElementById("modalEditBtn")
    const modalPrintBtn = document.getElementById("modalPrintBtn")

    if (!itemDetailsModal || !itemDetailsOverlay) {
      console.error("Item details modal elements not found")
      return
    }

    // Set item header
    if (modalItemName) modalItemName.textContent = "Item Details"
    if (modalItemTitle) modalItemTitle.textContent = item.product_name
    if (modalItemId) modalItemId.textContent = `ID: ${item.product_id}`

    const createdDate = new Date(item.created_at)
    if (modalItemDate) modalItemDate.textContent = `Added: ${createdDate.toLocaleDateString()}`

    // Set status badge
    if (modalItemStatus) {
      let statusClass = "bg-gray-100 text-gray-800"
      if (item.status === "Available") {
        statusClass = "status-available"
      } else if (item.status === "Pending") {
        statusClass = "status-pending"
      } else if (item.status === "Sold") {
        statusClass = "status-sold"
      }
      modalItemStatus.className = `text-xs px-2 py-0.5 rounded-full ${statusClass}`
      modalItemStatus.textContent = item.status
    }

    // Set material details
    if (modalMaterial) modalMaterial.textContent = item.material_type
    if (modalPurity) modalPurity.textContent = `${item.purity}%`
    if (modalHUID) modalHUID.textContent = item.huid_code || "N/A"

    // Set weight details
    if (modalGrossWeight) modalGrossWeight.textContent = `${Number.parseFloat(item.gross_weight).toFixed(2)}g`
    if (modalLessWeight) modalLessWeight.textContent = `${Number.parseFloat(item.less_weight).toFixed(2)}g`
    if (modalNetWeight) modalNetWeight.textContent = `${Number.parseFloat(item.net_weight).toFixed(2)}g`

    // Set source details
    if (modalSourceType) modalSourceType.textContent = item.source_type

    if (item.source_type === "Supplier") {
      if (modalSourceName) modalSourceName.textContent = item.supplier_name || "N/A"
      if (modalInvoiceNo) modalInvoiceNo.textContent = item.transaction_id || "N/A"
    } else if (item.source_type === "Karigar") {
      if (modalSourceName) modalSourceName.textContent = item.karigar_name || "N/A"
      if (modalInvoiceNo) modalInvoiceNo.textContent = item.manufacturing_order_id || "N/A"
    } else {
      if (modalSourceName) modalSourceName.textContent = "N/A"
      if (modalInvoiceNo) modalInvoiceNo.textContent = item.transaction_id || "N/A"
    }

    // Set stone details
    if (modalStoneType) modalStoneType.textContent = item.stone_type
    if (modalStoneWeight) {
      modalStoneWeight.textContent =
        item.stone_type !== "None"
          ? `${Number.parseFloat(item.stone_weight).toFixed(2)} ${item.stone_unit || "ct"}`
          : "N/A"
    }
    if (modalStoneQuality) modalStoneQuality.textContent = item.stone_quality || "N/A"
    if (modalStonePrice) {
      modalStonePrice.textContent = item.stone_price ? `₹${Number.parseFloat(item.stone_price).toFixed(2)}` : "N/A"
    }

    // Set making details
    if (modalMakingCharge) modalMakingCharge.textContent = `₹${Number.parseFloat(item.making_charge).toFixed(2)}`
    if (modalMakingType) modalMakingType.textContent = item.making_charge_type === "fixed" ? "Fixed" : "Percentage"
    if (modalTrayNo) modalTrayNo.textContent = item.Tray_no || "N/A"
    if (modalQuantity) modalQuantity.textContent = item.quantity

    // Set notes
    if (modalNotes) modalNotes.textContent = item.description || "No notes available."

    // Set images
    if (modalImageGallery) {
      modalImageGallery.innerHTML = ""

      if (item.images && item.images.length > 0) {
        // Set main image
        const primaryImage = item.images.find((img) => img.is_primary === "1") || item.images[0]
        if (modalItemImage) modalItemImage.src = primaryImage.image_url

        // Add all images to gallery
        item.images.forEach((image) => {
          const imgContainer = document.createElement("div")
          imgContainer.className = "w-20 h-20 bg-white rounded-md overflow-hidden border border-gray-200"

          const img = document.createElement("img")
          img.src = image.image_url
          img.alt = item.product_name
          img.className = "w-full h-full object-cover"

          imgContainer.appendChild(img)
          modalImageGallery.appendChild(imgContainer)
        })
      } else {
        if (modalItemImage) modalItemImage.src = "/placeholder.svg"

        const noImagesMsg = document.createElement("div")
        noImagesMsg.className = "text-sm text-gray-500"
        noImagesMsg.textContent = "No images available."
        modalImageGallery.appendChild(noImagesMsg)
      }
    }

    // Show modal
    itemDetailsModal.classList.remove("hidden")
    itemDetailsOverlay.classList.remove("hidden")

    // Add event listeners
    if (modalCloseBtn) {
      modalCloseBtn.addEventListener("click", closeItemDetailsModal)
    }

    if (itemDetailsOverlay) {
      itemDetailsOverlay.addEventListener("click", closeItemDetailsModal)
    }

    if (modalEditBtn) {
      modalEditBtn.addEventListener("click", () => {
        closeItemDetailsModal()
        editItem(currentItemId)
      })
    }

    if (modalPrintBtn) {
      modalPrintBtn.addEventListener("click", () => {
        window.open(`print_tag.php?id=${currentItemId}`, "_blank")
      })
    }
  }

  function closeItemDetailsModal() {
    const itemDetailsModal = document.getElementById("itemDetailsModal")
    const itemDetailsOverlay = document.getElementById("itemDetailsOverlay")

    if (itemDetailsModal) itemDetailsModal.classList.add("hidden")
    if (itemDetailsOverlay) itemDetailsOverlay.classList.add("hidden")
  }

  // Delete Item
  function showDeleteConfirmation(itemId) {
    currentItemId = itemId
    const deleteModalOverlay = document.getElementById("deleteModalOverlay")
    const deleteModal = document.getElementById("deleteModal")

    if (deleteModalOverlay) deleteModalOverlay.classList.remove("hidden")
    if (deleteModal) deleteModal.classList.remove("hidden")
  }

  function closeDeleteModal() {
    const deleteModalOverlay = document.getElementById("deleteModalOverlay")
    const deleteModal = document.getElementById("deleteModal")

    if (deleteModalOverlay) deleteModalOverlay.classList.add("hidden")
    if (deleteModal) deleteModal.classList.add("hidden")
    currentItemId = null
  }

  const confirmDeleteBtn = document.getElementById("confirmDelete")
  const cancelDeleteBtn = document.getElementById("cancelDelete")
  const deleteModalOverlay = document.getElementById("deleteModalOverlay")

  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener("click", () => {
      if (currentItemId) {
        deleteItem(currentItemId)
      }
    })
  }

  if (cancelDeleteBtn) {
    cancelDeleteBtn.addEventListener("click", closeDeleteModal)
  }

  if (deleteModalOverlay) {
    deleteModalOverlay.addEventListener("click", closeDeleteModal)
  }

  function deleteItem(itemId) {
    // Show loading state
    const confirmDeleteBtn = document.getElementById("confirmDelete")
    if (confirmDeleteBtn) {
      confirmDeleteBtn.disabled = true
      confirmDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...'
    }

    const formData = new FormData()
    formData.append("action", "delete_item")
    formData.append("itemId", itemId)

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          return response.json().then((err) => {
            throw new Error(err.message || "Server returned " + response.status)
          })
        }
        return response.json()
      })
      .then((data) => {
        if (!data.success) {
          throw new Error(data.message || "Failed to delete item")
        }

        closeDeleteModal()
        showToast(data.message || "Item deleted successfully", "success")
        loadItems(currentPage) // Maintain current page after deletion

        // Close details modal if the deleted item is currently displayed
        const itemDetailsModal = document.getElementById("itemDetailsModal")
        if (currentItemId === itemId && itemDetailsModal && !itemDetailsModal.classList.contains("hidden")) {
          closeItemDetailsModal()
        }
      })
      .catch((error) => {
        console.error("Error deleting item:", error)
        showToast(error.message || "An error occurred while deleting the item", "error")
        closeDeleteModal()
      })
      .finally(() => {
        // Reset button state
        if (confirmDeleteBtn) {
          confirmDeleteBtn.disabled = false
          confirmDeleteBtn.innerHTML = "Delete"
        }
      })
  }

  // Search and Filter
  const searchItemsField = document.getElementById("searchItems")
  if (searchItemsField) {
    searchItemsField.addEventListener(
      "input",
      debounce(() => {
        loadItems(1)
      }, 300),
    )
  }

  const filterMaterial = document.getElementById("filterMaterial")
  if (filterMaterial) {
    filterMaterial.addEventListener("change", () => {
      loadItems(1)
    })
  }

  const filterJewelryType = document.getElementById("filterJewelryType")
  if (filterJewelryType) {
    filterJewelryType.addEventListener("change", () => {
      loadItems(1)
    })
  }

  const filterSource = document.getElementById("filterSource")
  if (filterSource) {
    filterSource.addEventListener("change", () => {
      loadItems(1)
    })
  }

  const filterStatus = document.getElementById("filterStatus")
  if (filterStatus) {
    filterStatus.addEventListener("change", () => {
      loadItems(1)
    })
  }

  const resetFiltersBtn = document.getElementById("resetFilters")
  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener("click", () => {
      if (searchItemsField) searchItemsField.value = ""
      if (filterMaterial) filterMaterial.value = ""
      if (filterJewelryType) filterJewelryType.value = ""
      if (filterSource) filterSource.value = ""
      if (filterStatus) filterStatus.value = ""
      loadItems(1)
    })
  }

  // Export to CSV
  const exportBtn = document.getElementById("exportBtn")
  if (exportBtn) {
    exportBtn.addEventListener("click", () => {
      if (itemsData.length === 0) {
        showToast("No items to export.", "warning")
        return
      }

      // Create CSV content
      let csvContent =
        "Product ID,Product Name,Material,Purity,Gross Weight,Net Weight,Stone Type,Source Type,Status,Date\n"

      itemsData.forEach((item) => {
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
          new Date(item.created_at).toLocaleDateString(),
        ]

        // Escape commas and quotes
        const escapedRow = row.map((cell) => {
          if (cell === null || cell === undefined) return ""
          const cellStr = String(cell)
          if (cellStr.includes(",") || cellStr.includes('"') || cellStr.includes("\n")) {
            return `"${cellStr.replace(/"/g, '""')}"`
          }
          return cellStr
        })

        csvContent += escapedRow.join(",") + "\n"
      })

      // Create download link
      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" })
      const url = URL.createObjectURL(blob)
      const link = document.createElement("a")
      link.setAttribute("href", url)
      link.setAttribute("download", `jewelry_items_${new Date().toISOString().slice(0, 10)}.csv`)
      link.style.visibility = "hidden"
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    })
  }

  // Utility function for debouncing
  function debounce(func, wait) {
    let timeout
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout)
        func(...args)
      }
      clearTimeout(timeout)
      timeout = setTimeout(later, wait)
    }
  }

  // Add JS to fetch and display product_id when jewelryType changes
  function fetchAndDisplayProductId() {
    if (!jewelryType || !productIdDisplay) return

    const jewelryTypeValue = jewelryType.value.trim()
    if (!jewelryTypeValue) {
      productIdDisplay.value = ""
      return
    }

    const formData = new FormData()
    formData.append("action", "get_next_product_id")
    formData.append("jewelryType", jewelryTypeValue)

    fetch("add.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          productIdDisplay.value = data.product_id
        } else {
          productIdDisplay.value = ""
        }
      })
      .catch(() => {
        productIdDisplay.value = ""
      })
  }

  if (jewelryType) {
    jewelryType.addEventListener("input", fetchAndDisplayProductId)
    jewelryType.addEventListener("change", fetchAndDisplayProductId)

    // After defining fetchAndDisplayProductId and its event listeners:
    if (jewelryType.value.trim()) fetchAndDisplayProductId()
  }

  // Load items on page load
  loadItems()

  let cropper = null;
  let currentCropImg = null;
  const cropperModal = document.getElementById('cropperModal');
  const cropperImage = document.getElementById('cropperImage');
  const cropperCropBtn = document.getElementById('cropperCropBtn');
  const cropperRotateBtn = document.getElementById('cropperRotateBtn');
  const cropperFlipBtn = document.getElementById('cropperFlipBtn');
  const cropperCancelBtn = document.getElementById('cropperCancelBtn');
  let cropperFlipped = false;

  // Image Preview Crop Handler
  if (imagePreview) {
    imagePreview.addEventListener('click', function(e) {
      if (e.target.tagName === 'IMG') {
        // Open cropper modal with clicked image
        currentCropImg = e.target;
        cropperImage.src = currentCropImg.src;
        cropperModal.classList.remove('hidden');
        // Destroy previous cropper if any
        if (cropper) { cropper.destroy(); cropper = null; }
        cropperFlipped = false;
        // Wait for image to load before initializing cropper
        cropperImage.onload = function() {
          cropper = new Cropper(cropperImage, {
            viewMode: 1,
            autoCropArea: 1,
            movable: true,
            zoomable: true,
            scalable: true,
            rotatable: true,
            responsive: true,
            background: false,
            modal: true,
            guides: true,
            highlight: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            dragMode: 'crop',
            aspectRatio: NaN // Free crop
          });
        };
      }
    });
  }

  // Cropper Modal Controls
  if (cropperCropBtn) {
    cropperCropBtn.addEventListener('click', function() {
      if (cropper && currentCropImg) {
        const canvas = cropper.getCroppedCanvas();
        if (canvas) {
          currentCropImg.src = canvas.toDataURL('image/jpeg');
        }
        cropperModal.classList.add('hidden');
        cropper.destroy();
        cropper = null;
        currentCropImg = null;
      }
    });
  }
  if (cropperRotateBtn) {
    cropperRotateBtn.addEventListener('click', function() {
      if (cropper) {
        cropper.rotate(90);
      }
    });
  }
  if (cropperFlipBtn) {
    cropperFlipBtn.addEventListener('click', function() {
      if (cropper) {
        cropperFlipped = !cropperFlipped;
        cropper.scaleX(cropperFlipped ? -1 : 1);
      }
    });
  }
  if (cropperCancelBtn) {
    cropperCancelBtn.addEventListener('click', function() {
      cropperModal.classList.add('hidden');
      if (cropper) {
        cropper.destroy();
        cropper = null;
      }
      currentCropImg = null;
    });
  }
})