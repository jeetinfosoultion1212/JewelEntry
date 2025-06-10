document.addEventListener('DOMContentLoaded', async function() {
    // Global variables
    let mediaStream = null;
    let collateralItemIndex = 0;
    let priceConfigData = {};
    let currentImageInput = null;
    let currentImagePreview = null;
    let currentImagePreviewContainer = null;
    let selectedCustomerId = null; // Revert to let, scoped to DOMContentLoaded

    // DOM Elements - Camera
    const cameraModal = document.getElementById('cameraModal');
    const captureImageBtn = document.getElementById('captureImageBtn');
    const closeCameraModal = document.getElementById('closeCameraModal');
    const cameraFeed = document.getElementById('cameraFeed');
    const cameraCanvas = document.getElementById('cameraCanvas');
    const startCameraBtn = document.getElementById('startCamera');
    const takePictureBtn = document.getElementById('takePicture');
    const retakePictureBtn = document.getElementById('retakePicture');
    const savePictureBtn = document.getElementById('savePicture');

    // DOM Elements - Tabs
    const formTab = document.getElementById('formTab');
    const listTab = document.getElementById('listTab');
    const newLoanContent = document.getElementById('new-loan');
    const loanListContent = document.getElementById('loan-list');

    // DOM Elements - Collateral
    const collateralItemsContainer = document.getElementById('collateralItemsContainer');
    const addCollateralItemBtn = document.getElementById('addCollateralItemBtn');

    // Customer Search and Dropdown Functionality
    let customerSearchTimeout;
    const customerNameInput = document.getElementById('customerName');
    const customerDropdown = document.getElementById('customerDropdown');

    // New: Create and append hidden customer ID input
    const customerIdHiddenInput = document.createElement('input');
    customerIdHiddenInput.type = 'hidden';
    customerIdHiddenInput.name = 'customer_id'; // This name must match what the backend expects
    customerIdHiddenInput.id = 'selectedCustomerIdInput'; // Give it an ID for easy access later

    if (newLoanContent) {
        newLoanContent.appendChild(customerIdHiddenInput);
        console.log('Hidden customer_id input appended to newLoanContent.');
    } else {
        console.warn('newLoanContent (ID: new-loan) not found. Cannot append hidden customer_id input.');
    }

    // Utility Functions
    function dataURLtoBlob(dataurl) {
        try {
            const arr = dataurl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            const n = bstr.length;
            const u8arr = new Uint8Array(n);
            for (let i = 0; i < n; i++) {
                u8arr[i] = bstr.charCodeAt(i);
            }
            return new Blob([u8arr], {type: mime});
        } catch (error) {
            console.error('Error converting data URL to blob:', error);
            return null;
        }
    }

    function showToast(message, type = 'info') {
        console.log(`${type.toUpperCase()}: ${message}`);
        alert(message);
    }

    // FIXED: Camera Functionality with proper event delegation
    function initializeCameraFunctionality() {
        console.log('Initializing camera functionality...');
        
        if (!cameraModal) {
            console.warn('Camera modal not found');
            return;
        }

        // Use event delegation for dynamically created capture buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('capture-image-btn') || e.target.closest('.capture-image-btn')) {
                const button = e.target.classList.contains('capture-image-btn') ? e.target : e.target.closest('.capture-image-btn');
                const itemIndex = button.getAttribute('data-item-index');
                
                // Set current image elements
                currentImageInput = document.getElementById(`collateralImage_${itemIndex}`);
                currentImagePreview = button.closest('.collateral-item').querySelector('.collateral-image-preview');
                currentImagePreviewContainer = button.closest('.collateral-item').querySelector('.collateral-image-preview-container');
                
                // Open camera modal
                cameraModal.classList.remove('hidden');
                startCamera();
            }
        });

        // Close camera modal
        if (closeCameraModal) {
            closeCameraModal.addEventListener('click', () => {
                closeCameraAndModal();
            });
        }

        // Start camera button
        if (startCameraBtn) {
            startCameraBtn.addEventListener('click', async () => {
                await startCamera();
            });
        }

        // FIXED: Take picture button with proper error handling
        if (takePictureBtn) {
            takePictureBtn.addEventListener('click', () => {
                console.log('Take picture button clicked');
                takePicture();
            });
        }

        // Retake picture button
        if (retakePictureBtn) {
            retakePictureBtn.addEventListener('click', () => {
                retakePicture();
            });
        }

        // Save picture button
        if (savePictureBtn) {
            savePictureBtn.addEventListener('click', () => {
                savePicture();
            });
        }
    }

    async function startCamera() {
        console.log('Starting camera...');
        try {
            // Stop any existing stream first
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
            }

            mediaStream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                } 
            });
            
            if (cameraFeed) {
                cameraFeed.srcObject = mediaStream;
                cameraFeed.classList.remove('hidden');
                
                // Wait for video to load before showing controls
                cameraFeed.onloadedmetadata = () => {
                    console.log('Camera feed ready');
                    if (startCameraBtn) startCameraBtn.classList.add('hidden');
                    if (takePictureBtn) takePictureBtn.classList.remove('hidden');
                    if (retakePictureBtn) retakePictureBtn.classList.add('hidden');
                    if (savePictureBtn) savePictureBtn.classList.add('hidden');
                    if (cameraCanvas) cameraCanvas.classList.add('hidden');
                };
            }
            
        } catch (err) {
            console.error('Error accessing camera:', err);
            showToast('Could not access camera. Please ensure you have granted camera permissions.', 'error');
            if (cameraModal) cameraModal.classList.add('hidden');
        }
    }

    function takePicture() {
        console.log('Taking picture...');
        
        if (!cameraCanvas || !cameraFeed) {
            console.error('Camera canvas or feed not found');
            showToast('Camera elements not available', 'error');
            return;
        }

        if (cameraFeed.readyState !== 4) {
            console.error('Camera feed not ready');
            showToast('Camera is not ready. Please wait.', 'error');
            return;
        }

        try {
            const context = cameraCanvas.getContext('2d');
            cameraCanvas.width = cameraFeed.videoWidth || 640;
            cameraCanvas.height = cameraFeed.videoHeight || 480;
            
            context.drawImage(cameraFeed, 0, 0, cameraCanvas.width, cameraCanvas.height);
            
            // Hide video, show canvas
            cameraFeed.classList.add('hidden');
            cameraCanvas.classList.remove('hidden');
            
            // Update button visibility
            if (takePictureBtn) takePictureBtn.classList.add('hidden');
            if (retakePictureBtn) retakePictureBtn.classList.remove('hidden');
            if (savePictureBtn) savePictureBtn.classList.add('hidden');
            
            console.log('Picture taken successfully');
        } catch (error) {
            console.error('Error taking picture:', error);
            showToast('Error taking picture. Please try again.', 'error');
        }
    }

    function retakePicture() {
        console.log('Retaking picture...');
        if (cameraFeed) cameraFeed.classList.remove('hidden');
        if (cameraCanvas) cameraCanvas.classList.add('hidden');
        if (takePictureBtn) takePictureBtn.classList.remove('hidden');
        if (retakePictureBtn) retakePictureBtn.classList.add('hidden');
        if (savePictureBtn) savePictureBtn.classList.add('hidden');
    }

    function savePicture() {
        console.log('Saving picture...');
        if (!cameraCanvas) {
            console.error('Camera canvas not found');
            return;
        }

        try {
            const imageDataUrl = cameraCanvas.toDataURL('image/jpeg', 0.8);
            
            if (currentImagePreview && currentImagePreviewContainer) {
                currentImagePreview.src = imageDataUrl;
                currentImagePreviewContainer.style.display = 'block';
            }
            
            // Convert data URL to a Blob and then to a File object
            const blob = dataURLtoBlob(imageDataUrl);
            if (blob && currentImageInput) {
                const file = new File([blob], "captured_image.jpeg", { type: "image/jpeg" });
                
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                
                currentImageInput.files = dataTransfer.files;
            }
            
            closeCameraAndModal();
            showToast('Image captured successfully!', 'success');
        } catch (error) {
            console.error('Error saving picture:', error);
            showToast('Error saving picture. Please try again.', 'error');
        }
    }

    function closeCameraAndModal() {
        if (cameraModal) cameraModal.classList.add('hidden');
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            mediaStream = null;
        }
        
        // Reset camera UI
        if (cameraFeed) cameraFeed.classList.remove('hidden');
        if (cameraCanvas) cameraCanvas.classList.add('hidden');
        if (startCameraBtn) startCameraBtn.classList.remove('hidden');
        if (takePictureBtn) takePictureBtn.classList.add('hidden');
        if (retakePictureBtn) retakePictureBtn.classList.add('hidden');
        if (savePictureBtn) savePictureBtn.classList.add('hidden');
    }

    // Tab Switching Logic
    function initializeTabSwitching() {
        if (!formTab || !listTab || !newLoanContent || !loanListContent) {
            console.warn('Tab elements not found');
            return;
        }

        console.log('Tabs initialized:', { formTab, listTab, newLoanContent, loanListContent });

        function switchTab(activeTab) {
            console.log('Switching to tab:', activeTab);
            if (activeTab === 'form') {
                formTab.classList.add('tab-active');
                listTab.classList.remove('tab-active');
                newLoanContent.removeAttribute('hidden');
                loanListContent.setAttribute('hidden', '');
                console.log('New Loan tab activated.');
            } else if (activeTab === 'list') {
                listTab.classList.add('tab-active');
                formTab.classList.remove('tab-active');
                loanListContent.removeAttribute('hidden');
                newLoanContent.setAttribute('hidden', '');
                console.log('Loan List tab activated.');
            }
        }

        formTab.addEventListener('click', () => switchTab('form'));
        listTab.addEventListener('click', () => switchTab('list'));

        switchTab('form');
        console.log('Initial tab state set to form.');
    }

    // FIXED: Collateral Items Management with proper rate input handling
    function addCollateralItem(item = {}) {
        if (!collateralItemsContainer) {
            console.error('Collateral items container not found');
            return;
        }

        console.log('Adding collateral item...');
        
        const newItemHtml = `
            <div class="collateral-item border border-green-200 rounded-md p-2 mb-2 relative" data-item-index="${collateralItemIndex}">
                <button type="button" class="remove-item-btn absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
                <div class="field-grid grid-cols-2 gap-2">
                    <div class="field-col">
                        <div class="field-label">Material Type</div>
                        <div class="field-container">
                            <select name="collateralItems[${collateralItemIndex}][materialType]" class="input-field font-xs font-bold py-1 pl-6 pr-1 appearance-none bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md w-full material-type" required>
                                <option value="">Select Material</option>
                                <option value="Gold">Gold</option>
                                <option value="Silver">Silver</option>
                            </select>
                            <i class="fas fa-pallet field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col">
                        <div class="field-label">Purity (%)</div>
                        <div class="field-container">
                            <input type="number"
                                id="purity_${collateralItemIndex}"
                                name="collateralItems[${collateralItemIndex}][purity]"
                                class="input-field font-xs font-bold py-1 pl-6 pr-1 bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md w-full purity-input"
                                placeholder="Enter purity" step="0.01" required>
                            <i class="fas fa-percentage field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col">
                        <div class="field-label">Weight (grams)</div>
                        <div class="field-container">
                            <input type="number" name="collateralItems[${collateralItemIndex}][weight]" class="input-field font-xs font-bold py-1 pl-6 bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md w-full weight-input" placeholder="Enter weight" step="0.001" required>
                            <i class="fas fa-weight-hanging field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col">
                        <div class="field-label">Rate per gram</div>
                        <div class="field-container">
                            <input type="number" name="collateralItems[${collateralItemIndex}][ratePerGram]" class="input-field font-xs font-bold py-1 pl-6 bg-white border border-green-200 rounded-md w-full rate-display" placeholder="Rate" step="0.01" min="0">
                            <i class="fas fa-rupee-sign field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col col-span-2">
                        <div class="field-label">Description</div>
                        <div class="field-container">
                            <textarea name="collateralItems[${collateralItemIndex}][description]" class="input-field font-xs font-bold py-1 pl-6 pr-1 w-full bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md description" placeholder="e.g., 22k Gold necklace with 5 grams"></textarea>
                            <i class="fas fa-info-circle field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col col-span-2">
                        <div class="field-label">Image</div>
                        <div class="field-container flex items-center">
                            <label for="collateralImage_${collateralItemIndex}" class="flex-1 bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md py-1 px-2 cursor-pointer text-xs font-bold flex items-center justify-center h-7">
                                <i class="fas fa-upload mr-2 text-green-500"></i> Choose File
                                <input type="file" id="collateralImage_${collateralItemIndex}" name="collateralItems[${collateralItemIndex}][image]" accept="image/*" class="hidden collateral-image-input" data-item-index="${collateralItemIndex}">
                            </label>
                            <button type="button" class="capture-image-btn ml-2 bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-3 rounded-md h-7 flex items-center justify-center" data-item-index="${collateralItemIndex}">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <div class="collateral-image-preview-container mt-2" style="display:none;">
                            <img src="" alt="Image Preview" class="collateral-image-preview w-32 h-32 object-cover rounded-md border border-gray-300"/>
                        </div>
                    </div>
                    <div class="field-col col-span-2">
                        <div class="field-label">Calculated Value</div>
                        <div class="field-container">
                            <input type="text" name="collateralItems[${collateralItemIndex}][calculatedValue]" class="input-field font-xs font-bold py-1 pl-6 bg-white border border-green-200 rounded-md w-full calculated-value" placeholder="Calculated Value" readonly tabindex="-1">
                            <i class="fas fa-rupee-sign field-icon text-green-500"></i>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        collateralItemsContainer.insertAdjacentHTML('beforeend', newItemHtml);
        const newItem = collateralItemsContainer.lastElementChild;
        collateralItemIndex++;
        
        addCollateralItemEventListeners(newItem);

        // Set default values
        const newMaterialTypeSelect = newItem.querySelector('.material-type');
        const newPurityInput = newItem.querySelector('.purity-input');
        
        if (newMaterialTypeSelect && newPurityInput) {
            newMaterialTypeSelect.value = item.materialType || 'Gold';
            newMaterialTypeSelect.dispatchEvent(new Event('change'));

            if (!item.purity) {
                if (newMaterialTypeSelect.value === 'Gold') {
                    newPurityInput.value = '99.99';
                } else if (newMaterialTypeSelect.value === 'Silver') {
                    newPurityInput.value = '999.9';
                }
            } else {
                newPurityInput.value = item.purity;
            }
            
            newPurityInput.dispatchEvent(new Event('input'));
        }
    }

    function addCollateralItemEventListeners(itemElement) {
        if (!itemElement) return;

        const removeBtn = itemElement.querySelector('.remove-item-btn');
        const materialTypeSelect = itemElement.querySelector('.material-type');
        const purityInput = itemElement.querySelector('.purity-input');
        const weightInput = itemElement.querySelector('.weight-input');
        const rateDisplay = itemElement.querySelector('.rate-display');
        const calculatedValueDisplay = itemElement.querySelector('.calculated-value');
        const imageInput = itemElement.querySelector('.collateral-image-input');
        const imagePreviewContainer = itemElement.querySelector('.collateral-image-preview-container');
        const imagePreview = itemElement.querySelector('.collateral-image-preview');

        // Remove item functionality
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                itemElement.remove();
                updateCollateralTotal();
            });
        }

        // Material type change
        if (materialTypeSelect) {
            materialTypeSelect.addEventListener('change', () => {
                calculateCollateralValue(materialTypeSelect.value, purityInput.value, weightInput.value, rateDisplay, calculatedValueDisplay);
            });
        }

        // Purity input change
        if (purityInput) {
            purityInput.addEventListener('input', () => {
                calculateCollateralValue(materialTypeSelect.value, purityInput.value, weightInput.value, rateDisplay, calculatedValueDisplay);
            });
        }

        // Weight input
        if (weightInput) {
            weightInput.addEventListener('input', () => {
                calculateCollateralValue(materialTypeSelect.value, purityInput.value, weightInput.value, rateDisplay, calculatedValueDisplay);
            });
        }

        // FIXED: Rate per gram input - allow manual entry and recalculation
        if (rateDisplay) {
            rateDisplay.addEventListener('input', () => {
                // Allow manual rate entry and recalculate value
                const weight = parseFloat(weightInput.value || 0);
                const rate = parseFloat(rateDisplay.value || 0);
                const calculatedValue = weight * rate;
                calculatedValueDisplay.value = calculatedValue.toFixed(2);
                updateCollateralTotal();
            });
            
            // Prevent the automatic rate override when user is typing
            rateDisplay.addEventListener('focus', () => {
                rateDisplay.setAttribute('data-user-editing', 'true');
            });
            
            rateDisplay.addEventListener('blur', () => {
                rateDisplay.removeAttribute('data-user-editing');
            });
        }

        // Image preview
        if (imageInput) {
            imageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file && imagePreview && imagePreviewContainer) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else if (imagePreview && imagePreviewContainer) {
                    imagePreview.src = '';
                    imagePreviewContainer.style.display = 'none';
                }
            });
        }
    }

    // Price Configuration and Calculation
    async function fetchPriceConfig() {
        try {
            const response = await fetch('api/get_price_config.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            priceConfigData = await response.json();
            console.log('Price config loaded:', priceConfigData);
            
            updateCollateralTotal();
        } catch (error) {
            console.error('Error fetching price config:', error);
            showToast('Error loading price configurations.', 'error');
        }
    }

    function calculateCollateralValue(materialType, purity, weight, rateDisplayElement, calculatedValueElement) {
        if (!rateDisplayElement || !calculatedValueElement) return;
        
        // Check if user is currently editing the rate field
        const userEditing = rateDisplayElement.hasAttribute('data-user-editing');
        let rate = parseFloat(rateDisplayElement.value) || 0;

        // Only auto-fill rate if user is not editing and rate is 0
        if (!userEditing && rate === 0 && priceConfigData[materialType] && priceConfigData[materialType][purity]) {
            rate = parseFloat(priceConfigData[materialType][purity].rate);
            rateDisplayElement.value = rate.toFixed(2);
        } else if (!userEditing && rate === 0) {
            // If no config data and no user input, keep current rate
            rate = parseFloat(rateDisplayElement.value) || 0;
        }
        
        const calculatedValue = parseFloat(weight || 0) * rate;
        calculatedValueElement.value = calculatedValue.toFixed(2);
        updateCollateralTotal();
    }

    function updateCollateralTotal() {
        let totalCollateralValue = 0;
        document.querySelectorAll('.collateral-item').forEach(itemElement => {
            const calculatedValueInput = itemElement.querySelector('.calculated-value');
            if (calculatedValueInput) {
                totalCollateralValue += parseFloat(calculatedValueInput.value || 0);
            }
        });
        
        const collateralValueElement = document.getElementById('collateralValue');
        if (collateralValueElement) {
            collateralValueElement.value = totalCollateralValue.toFixed(2);
        }
    }

    // Customer Search and Dropdown Functionality
    function searchCustomers(query) {
        if (query.length < 2) {
            customerDropdown.innerHTML = '';
            customerDropdown.classList.add('hidden');
            return;
        }

        fetch(`api/search_customers.php?term=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(customers => {
                if (customers.length > 0) {
                    customerDropdown.innerHTML = customers.map(customer => `
                        <div class="customer-item p-2 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-b-0" 
                             data-customer-id="${customer.id}">
                            <div class="font-medium text-sm text-gray-800">${customer.name}</div>
                            <div class="text-xs text-gray-600">${customer.phone || 'No phone'}</div>
                            <div class="text-xs text-gray-500">${customer.address || 'No address'}</div>
                        </div>
                    `).join('');
                    customerDropdown.classList.remove('hidden');
                } else {
                    customerDropdown.innerHTML = '<div class="p-2 text-sm text-gray-500">No customers found</div>';
                    customerDropdown.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error searching customers:', error);
                showToast('Error searching customers', 'error');
                customerDropdown.classList.add('hidden');
            });
    }

    function selectCustomer(customerId, customerName, customerPhone, customerAddress) {
        selectedCustomerId = customerId;
        customerNameInput.value = customerName;
        customerDropdown.classList.add('hidden');
        
        // Update customer info display
        document.getElementById('customerNameDisplay').textContent = customerName;
        document.getElementById('customerPhoneDisplay').textContent = customerPhone || 'N/A';
        document.getElementById('customerInfoDisplay').classList.remove('hidden');

        // Update the hidden input field with the selected customer ID
        const hiddenInput = document.getElementById('selectedCustomerIdInput');
        if (hiddenInput) {
            hiddenInput.value = selectedCustomerId;
            console.log('Hidden customer ID input updated:', hiddenInput.value);
        } else {
            console.error('Hidden customer ID input not found (ID: selectedCustomerIdInput). Cannot update its value.');
        }

        // Add console logging for customer selection
        console.log('Customer Selected:', {
            id: customerId,
            name: customerName,
            phone: customerPhone,
            address: customerAddress
        });
    }

    // Event Listeners for Customer Search
    customerNameInput.addEventListener('input', (e) => {
        clearTimeout(customerSearchTimeout);
        customerSearchTimeout = setTimeout(() => {
            searchCustomers(e.target.value);
        }, 300);
    });

    customerDropdown.addEventListener('click', (e) => {
        const customerItem = e.target.closest('.customer-item');
        if (customerItem) {
            const customerId = customerItem.dataset.customerId;
            const customerName = customerItem.querySelector('.font-medium').textContent;
            const customerPhone = customerItem.querySelector('.text-gray-600').textContent;
            const customerAddress = customerItem.querySelector('.text-gray-500').textContent;
            selectCustomer(customerId, customerName, customerPhone, customerAddress);
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!customerNameInput.contains(e.target) && !customerDropdown.contains(e.target)) {
            customerDropdown.classList.add('hidden');
        }
    });
    document.getElementById('loanForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        // Get form data
        const formData = new FormData(this);
        formData.append('action', 'add_loan');
        
        // Show loading state
        const submitBtn = document.getElementById('createLoanBtn');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Loan...';
        submitBtn.disabled = true;
        
        // Send AJAX request
        fetch('loans.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            
            // Show response
            if (data.success) {
                // Show success message
                showToast('success', data.message);
                // Reset form
                this.reset();
                // Clear customer info display
                document.getElementById('customerInfoDisplay').classList.add('hidden');
                // Clear collateral items
                document.getElementById('collateralItemsContainer').innerHTML = '';
            } else {
                // Show error message
                showToast('error', data.message);
            }
        })
        .catch(error => {
            // Reset button state
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            // Show error message
            showToast('error', 'An error occurred while creating the loan');
            console.error('Error:', error);
        });
    });

    // Toast notification function
    function showToast(type, message) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        
        // Set message and type
        toastMessage.textContent = message;
        toast.className = `toast ${type} show`;
        
        // Hide after 3 seconds
        setTimeout(() => {
            toast.className = 'toast hidden';
        }, 3000);
    }





    // Initialize everything
    async function initialize() {
        console.log('Initializing application...');
        
        initializeCameraFunctionality();
        initializeTabSwitching();
        
        await fetchPriceConfig();

        if (collateralItemsContainer) {
            addCollateralItem();
        }
        
        if (addCollateralItemBtn) {
            addCollateralItemBtn.addEventListener('click', () => {
                console.log('Add Collateral Item Button clicked.');
                addCollateralItem();
            });
        } else {
            console.warn('Add Collateral Item Button not found');
        }
        
        console.log('Application initialized successfully');
    }

    // Start the application
    initialize();
});