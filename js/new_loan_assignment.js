// Global variables for the new loan assignment workflow
let selectedCustomerId = null;
let collateralItemCounter = 0; // To keep track of unique IDs for collateral items
let addedCollateralItems = []; // Array to store all added collateral item data
let priceConfigData = {}; // Stores fetched price configurations

// Camera related global variables (reused from loans.js)
let mediaStream = null;
let currentImageInput = null;
let currentImagePreview = null;
let currentImagePreviewContainer = null;

document.addEventListener('DOMContentLoaded', async function() {
    // DOM Elements
    const customerNameSearch = document.getElementById('customerNameSearch');
    const customerSearchDropdown = document.getElementById('customerSearchDropdown');
    const selectedCustomerInfoDisplay = document.getElementById('selectedCustomerInfoDisplay');
    const customerNameDisplay = document.getElementById('customerNameDisplay');
    const customerPhoneDisplay = document.getElementById('customerPhoneDisplay');
    const selectedCustomerIdInput = document.getElementById('selectedCustomerId'); // Hidden input for customer ID

    const collateralDetailsSection = document.getElementById('collateralDetailsSection');
    const addCollateralItemInputBtn = document.getElementById('addCollateralItemInputBtn');
    const collateralItemInputArea = document.getElementById('collateralItemInputArea');
    const collateralItemsDisplayContainer = document.getElementById('collateralItemsDisplayContainer');
    const totalCollateralValueDisplay = document.getElementById('totalCollateralValueDisplay');

    const loanDetailsSection = document.getElementById('loanDetailsSection');
    const newLoanForm = document.getElementById('newLoanForm');
    const createLoanBtn = document.getElementById('createLoanBtn');

    // Camera Modal DOM Elements (reused from loans.js)
    const cameraModal = document.getElementById('cameraModal');
    const closeCameraModal = document.getElementById('closeCameraModal');
    const cameraFeed = document.getElementById('cameraFeed');
    const cameraCanvas = document.getElementById('cameraCanvas');
    const startCameraBtn = document.getElementById('startCamera');
    const takePictureBtn = document.getElementById('takePicture');
    const retakePictureBtn = document.getElementById('retakePicture');
    const savePictureBtn = document.getElementById('savePicture');

    // --- Utility Functions ---
    function showToast(type, message) {
        const toastContainer = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i><span>${message}</span>`;
        toastContainer.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Animate out and remove
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, 3000);
    }

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

    // --- Camera Functionality (Adapted from loans.js) ---
    function initializeCameraFunctionality() {
        if (!cameraModal) {
            console.warn('Camera modal not found');
            return;
        }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('capture-image-btn') || e.target.closest('.capture-image-btn')) {
                const button = e.target.classList.contains('capture-image-btn') ? e.target : e.target.closest('.capture-image-btn');
                const itemIndex = button.getAttribute('data-item-index');
                
                currentImageInput = document.getElementById(`collateralImage_${itemIndex}`);
                currentImagePreview = button.closest('.collateral-item-input-form').querySelector('.collateral-image-preview');
                currentImagePreviewContainer = button.closest('.collateral-item-input-form').querySelector('.collateral-image-preview-container');
                
                cameraModal.classList.remove('hidden');
                startCamera();
            }
        });

        if (closeCameraModal) {
            closeCameraModal.addEventListener('click', () => {
                closeCameraAndModal();
            });
        }
        if (startCameraBtn) {
            startCameraBtn.addEventListener('click', async () => {
                await startCamera();
            });
        }
        if (takePictureBtn) {
            takePictureBtn.addEventListener('click', () => {
                takePicture();
            });
        }
        if (retakePictureBtn) {
            retakePictureBtn.addEventListener('click', () => {
                retakePicture();
            });
        }
        if (savePictureBtn) {
            savePictureBtn.addEventListener('click', () => {
                savePicture();
            });
        }
    }

    async function startCamera() {
        try {
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
                cameraFeed.onloadedmetadata = () => {
                    if (startCameraBtn) startCameraBtn.classList.add('hidden');
                    if (takePictureBtn) takePictureBtn.classList.remove('hidden');
                    if (retakePictureBtn) retakePictureBtn.classList.add('hidden');
                    if (savePictureBtn) savePictureBtn.classList.add('hidden');
                    if (cameraCanvas) cameraCanvas.classList.add('hidden');
                };
            }
        } catch (err) {
            console.error('Error accessing camera:', err);
            showToast('error', 'Could not access camera. Please ensure you have granted camera permissions.');
            if (cameraModal) cameraModal.classList.add('hidden');
        }
    }

    function takePicture() {
        if (!cameraCanvas || !cameraFeed) {
            showToast('error', 'Camera elements not available');
            return;
        }
        if (cameraFeed.readyState !== 4) {
            showToast('error', 'Camera is not ready. Please wait.');
            return;
        }
        try {
            const context = cameraCanvas.getContext('2d');
            cameraCanvas.width = cameraFeed.videoWidth || 640;
            cameraCanvas.height = cameraFeed.videoHeight || 480;
            context.drawImage(cameraFeed, 0, 0, cameraCanvas.width, cameraCanvas.height);
            cameraFeed.classList.add('hidden');
            cameraCanvas.classList.remove('hidden');
            if (takePictureBtn) takePictureBtn.classList.add('hidden');
            if (retakePictureBtn) retakePictureBtn.classList.remove('hidden');
            if (savePictureBtn) savePictureBtn.classList.add('hidden');
        } catch (error) {
            console.error('Error taking picture:', error);
            showToast('error', 'Error taking picture. Please try again.');
        }
    }

    function retakePicture() {
        if (cameraFeed) cameraFeed.classList.remove('hidden');
        if (cameraCanvas) cameraCanvas.classList.add('hidden');
        if (takePictureBtn) takePictureBtn.classList.remove('hidden');
        if (retakePictureBtn) retakePictureBtn.classList.add('hidden');
        if (savePictureBtn) savePictureBtn.classList.add('hidden');
    }

    function savePicture() {
        if (!cameraCanvas) return;
        try {
            const imageDataUrl = cameraCanvas.toDataURL('image/jpeg', 0.8);
            if (currentImagePreview && currentImagePreviewContainer) {
                currentImagePreview.src = imageDataUrl;
                currentImagePreviewContainer.style.display = 'block';
            }
            const blob = dataURLtoBlob(imageDataUrl);
            if (blob && currentImageInput) {
                const file = new File([blob], "captured_image.jpeg", { type: "image/jpeg" });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                currentImageInput.files = dataTransfer.files;
            }
            closeCameraAndModal();
            showToast('success', 'Image captured successfully!');
        } catch (error) {
            console.error('Error saving picture:', error);
            showToast('error', 'Error saving picture. Please try again.');
        }
    }

    function closeCameraAndModal() {
        if (cameraModal) cameraModal.classList.add('hidden');
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            mediaStream = null;
        }
        if (cameraFeed) cameraFeed.classList.remove('hidden');
        if (cameraCanvas) cameraCanvas.classList.add('hidden');
        if (startCameraBtn) startCameraBtn.classList.remove('hidden');
        if (takePictureBtn) takePictureBtn.classList.add('hidden');
        if (retakePictureBtn) retakePictureBtn.classList.add('hidden');
        if (savePictureBtn) savePictureBtn.classList.add('hidden');
    }

    // --- Customer Search & Selection ---
    let customerSearchTimeout;
    customerNameSearch.addEventListener('input', (e) => {
        clearTimeout(customerSearchTimeout);
        customerSearchTimeout = setTimeout(() => {
            searchCustomers(e.target.value);
        }, 300);
    });

    function searchCustomers(query) {
        if (query.length < 2) {
            customerSearchDropdown.innerHTML = '';
            customerSearchDropdown.classList.add('hidden');
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
                    customerSearchDropdown.innerHTML = customers.map(customer => `
                        <div class="customer-item p-2 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-b-0" 
                             data-customer-id="${customer.id}"
                             data-customer-name="${customer.name}"
                             data-customer-phone="${customer.phone || 'No phone'}"
                             data-customer-address="${customer.address || 'No address'}">
                            <div class="font-medium text-sm text-gray-800">${customer.name}</div>
                            <div class="text-xs text-gray-600">${customer.phone || 'No phone'}</div>
                            <div class="text-xs text-gray-500">${customer.address || 'No address'}</div>
                        </div>
                    `).join('');
                    customerSearchDropdown.classList.remove('hidden');
                } else {
                    customerSearchDropdown.innerHTML = '<div class="p-2 text-sm text-gray-500">No customers found</div>';
                    customerSearchDropdown.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error searching customers:', error);
                showToast('error', 'Error searching customers');
                customerSearchDropdown.classList.add('hidden');
            });
    }

    customerSearchDropdown.addEventListener('click', (e) => {
        const customerItem = e.target.closest('.customer-item');
        if (customerItem) {
            selectedCustomerId = customerItem.dataset.customerId;
            const customerName = customerItem.dataset.customerName;
            const customerPhone = customerItem.dataset.customerPhone;
            
            customerNameSearch.value = customerName;
            customerSearchDropdown.classList.add('hidden');
            
            customerNameDisplay.textContent = customerName;
            customerPhoneDisplay.textContent = customerPhone;
            selectedCustomerInfoDisplay.classList.remove('hidden');
            selectedCustomerIdInput.value = selectedCustomerId; // Update hidden input

            // Enable next section
            collateralDetailsSection.classList.remove('disabled');
            showToast('success', 'Customer selected. Proceed to collateral details.');
        }
    });

    document.addEventListener('click', (e) => {
        if (!customerNameSearch.contains(e.target) && !customerSearchDropdown.contains(e.target)) {
            customerSearchDropdown.classList.add('hidden');
        }
    });

    // --- Collateral Item Management ---
    addCollateralItemInputBtn.addEventListener('click', () => {
        renderCollateralItemInputForm();
    });

    function renderCollateralItemInputForm(item = {}) {
        collateralItemInputArea.innerHTML = `
            <div class="collateral-item-input-form border border-blue-200 rounded-md p-2 mb-2 relative" data-item-index="${collateralItemCounter}">
                <div class="field-grid grid-cols-2 gap-2">
                    <div class="field-col">
                        <div class="field-label">Material Type</div>
                        <div class="field-container">
                            <select name="currentCollateral[materialType]" class="input-field font-xs font-bold py-1 pl-6 pr-1 appearance-none bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md w-full material-type" required>
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
                                id="purity_${collateralItemCounter}"
                                name="currentCollateral[purity]"
                                class="input-field font-xs font-bold py-1 pl-6 pr-1 bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md w-full purity-input"
                                placeholder="Enter purity" step="0.01" required>
                            <i class="fas fa-percentage field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col">
                        <div class="field-label">Weight (grams)</div>
                        <div class="field-container">
                            <input type="number" name="currentCollateral[weight]" class="input-field font-xs font-bold py-1 pl-6 bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md w-full weight-input" placeholder="Enter weight" step="0.001" required>
                            <i class="fas fa-weight-hanging field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col">
                        <div class="field-label">Rate per gram</div>
                        <div class="field-container">
                            <input type="number" name="currentCollateral[ratePerGram]" class="input-field font-xs font-bold py-1 pl-6 bg-white border border-green-200 rounded-md w-full rate-display" placeholder="Rate" step="0.01" min="0">
                            <i class="fas fa-rupee-sign field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col col-span-2">
                        <div class="field-label">Description</div>
                        <div class="field-container">
                            <textarea name="currentCollateral[description]" class="input-field font-xs font-bold py-1 pl-6 pr-1 w-full bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md description" placeholder="e.g., 22k Gold necklace with 5 grams"></textarea>
                            <i class="fas fa-info-circle field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col col-span-2">
                        <div class="field-label">Image</div>
                        <div class="field-container flex items-center">
                            <label for="collateralImage_${collateralItemCounter}" class="flex-1 bg-white border border-green-200 hover:border-green-300 focus:border-green-400 rounded-md py-1 px-2 cursor-pointer text-xs font-bold flex items-center justify-center h-7">
                                <i class="fas fa-upload mr-2 text-green-500"></i> Choose File
                                <input type="file" id="collateralImage_${collateralItemCounter}" name="currentCollateral[image]" accept="image/*" class="hidden collateral-image-input" data-item-index="${collateralItemCounter}">
                            </label>
                            <button type="button" class="capture-image-btn ml-2 bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-3 rounded-md h-7 flex items-center justify-center" data-item-index="${collateralItemCounter}">
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
                            <input type="text" name="currentCollateral[calculatedValue]" class="input-field font-xs font-bold py-1 pl-6 bg-white border border-green-200 rounded-md w-full calculated-value" placeholder="Calculated Value" readonly tabindex="-1">
                            <i class="fas fa-rupee-sign field-icon text-green-500"></i>
                        </div>
                    </div>
                    <div class="field-col col-span-2 text-right mt-2">
                        <button type="button" id="addItemToLoanBtn" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-1 px-3 rounded-md flex items-center justify-center ml-auto">
                            <i class="fas fa-plus mr-1"></i> Add Item to Loan
                        </button>
                    </div>
                </div>
            </div>
        `;
        const currentCollateralForm = collateralItemInputArea.querySelector('.collateral-item-input-form');
        addCollateralItemFormEventListeners(currentCollateralForm);

        // Populate if editing an existing item
        if (Object.keys(item).length > 0) {
            currentCollateralForm.querySelector('.material-type').value = item.materialType || '';
            currentCollateralForm.querySelector('.purity-input').value = item.purity || '';
            currentCollateralForm.querySelector('.weight-input').value = item.weight || '';
            currentCollateralForm.querySelector('.rate-display').value = item.ratePerGram || '';
            currentCollateralForm.querySelector('.description').value = item.description || '';
            currentCollateralForm.querySelector('.calculated-value').value = item.calculatedValue || '';
            if (item.image) {
                const preview = currentCollateralForm.querySelector('.collateral-image-preview');
                const container = currentCollateralForm.querySelector('.collateral-image-preview-container');
                preview.src = item.image;
                container.style.display = 'block';
            }
            // Trigger change/input events to recalculate if necessary
            currentCollateralForm.querySelector('.material-type').dispatchEvent(new Event('change'));
            currentCollateralForm.querySelector('.purity-input').dispatchEvent(new Event('input'));
            currentCollateralForm.querySelector('.weight-input').dispatchEvent(new Event('input'));
            currentCollateralForm.querySelector('.rate-display').dispatchEvent(new Event('input'));
        } else {
            // Set default values for new item
            const newMaterialTypeSelect = currentCollateralForm.querySelector('.material-type');
            const newPurityInput = currentCollateralForm.querySelector('.purity-input');
            
            if (newMaterialTypeSelect && newPurityInput) {
                newMaterialTypeSelect.value = 'Gold';
                newMaterialTypeSelect.dispatchEvent(new Event('change')); // Trigger change for initial rate calculation

                newPurityInput.value = '99.99';
                newPurityInput.dispatchEvent(new Event('input')); // Trigger input for initial rate calculation
            }
        }
    }

    function addCollateralItemFormEventListeners(formElement) {
        if (!formElement) return;

        const materialTypeSelect = formElement.querySelector('.material-type');
        const purityInput = formElement.querySelector('.purity-input');
        const weightInput = formElement.querySelector('.weight-input');
        const rateDisplay = formElement.querySelector('.rate-display');
        const calculatedValueDisplay = formElement.querySelector('.calculated-value');
        const imageInput = formElement.querySelector('.collateral-image-input');
        const imagePreviewContainer = formElement.querySelector('.collateral-image-preview-container');
        const imagePreview = formElement.querySelector('.collateral-image-preview');
        const addItemToLoanBtn = formElement.querySelector('#addItemToLoanBtn');

        materialTypeSelect.addEventListener('change', () => {
            calculateCurrentCollateralValue(materialTypeSelect.value, purityInput.value, weightInput.value, rateDisplay, calculatedValueDisplay);
        });
        purityInput.addEventListener('input', () => {
            calculateCurrentCollateralValue(materialTypeSelect.value, purityInput.value, weightInput.value, rateDisplay, calculatedValueDisplay);
        });
        weightInput.addEventListener('input', () => {
            calculateCurrentCollateralValue(materialTypeSelect.value, purityInput.value, weightInput.value, rateDisplay, calculatedValueDisplay);
        });
        rateDisplay.addEventListener('input', () => {
            const weight = parseFloat(weightInput.value || 0);
            const rate = parseFloat(rateDisplay.value || 0);
            const calculatedValue = weight * rate;
            calculatedValueDisplay.value = calculatedValue.toFixed(2);
        });
        
        rateDisplay.addEventListener('focus', () => {
            rateDisplay.setAttribute('data-user-editing', 'true');
        });
        rateDisplay.addEventListener('blur', () => {
            rateDisplay.removeAttribute('data-user-editing');
        });

        imageInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file && imagePreview && imagePreviewContainer) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    container.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else if (imagePreview && imagePreviewContainer) {
                imagePreview.src = '';
                imagePreviewContainer.style.display = 'none';
            }
        });

        addItemToLoanBtn.addEventListener('click', () => {
            const currentItem = {
                materialType: materialTypeSelect.value,
                purity: parseFloat(purityInput.value),
                weight: parseFloat(weightInput.value),
                ratePerGram: parseFloat(rateDisplay.value),
                description: formElement.querySelector('.description').value,
                calculatedValue: parseFloat(calculatedValueDisplay.value)
            };

            // Image handling: Convert to Base64 if a file is selected
            const file = imageInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    currentItem.image = e.target.result; // Store Base64 string
                    addCollateralItemToLoan(currentItem);
                };
                reader.readAsDataURL(file);
            } else {
                currentItem.image = ''; // No image
                addCollateralItemToLoan(currentItem);
            }
        });
    }

    function calculateCurrentCollateralValue(materialType, purity, weight, rateDisplayElement, calculatedValueElement) {
        if (!rateDisplayElement || !calculatedValueElement) return;
        
        const userEditing = rateDisplayElement.hasAttribute('data-user-editing');
        let rate = parseFloat(rateDisplayElement.value) || 0;

        if (!userEditing && priceConfigData[materialType] && priceConfigData[materialType][purity]) {
            rate = parseFloat(priceConfigData[materialType][purity].rate);
            rateDisplayElement.value = rate.toFixed(2);
        } else if (!userEditing && rate === 0) {
            rate = parseFloat(rateDisplayElement.value) || 0;
        }
        
        const calculatedValue = parseFloat(weight || 0) * rate;
        calculatedValueElement.value = calculatedValue.toFixed(2);
    }

    function addCollateralItemToLoan(itemData) {
        if (!itemData.materialType || !itemData.purity || !itemData.weight || isNaN(itemData.calculatedValue) || itemData.calculatedValue <= 0) {
            showToast('error', 'Please fill all required collateral fields correctly and ensure calculated value is greater than 0.');
            return;
        }

        // Assign a temporary unique ID for display/removal
        itemData.tempId = `collateral_item_${collateralItemCounter++}`;
        addedCollateralItems.push(itemData);
        displayCollateralItems();
        updateTotalCollateralValue();
        collateralItemInputArea.innerHTML = '<p class="text-gray-500 text-center">Add another item or proceed to loan details.</p>';
        loanDetailsSection.classList.remove('disabled'); // Enable loan details section
        showToast('success', 'Collateral item added. You can add more or proceed to loan details.');
    }

    function displayCollateralItems() {
        if (addedCollateralItems.length === 0) {
            collateralItemsDisplayContainer.innerHTML = '<p class="text-gray-500 text-center">No collateral items added yet.</p>';
            return;
        }
        collateralItemsDisplayContainer.innerHTML = addedCollateralItems.map((item, index) => `
            <div class="collateral-item-display" data-temp-id="${item.tempId}">
                <span>${item.materialType} (${item.purity}% Purity, ${item.weight}g) - Rs. ${item.calculatedValue.toFixed(2)}</span>
                <button type="button" class="remove-display-item" data-index="${index}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');

        // Add event listeners for removal
        collateralItemsDisplayContainer.querySelectorAll('.remove-display-item').forEach(button => {
            button.addEventListener('click', (e) => {
                const indexToRemove = parseInt(e.target.closest('.remove-display-item').dataset.index);
                addedCollateralItems.splice(indexToRemove, 1);
                displayCollateralItems();
                updateTotalCollateralValue();
                if (addedCollateralItems.length === 0) {
                    loanDetailsSection.classList.add('disabled'); // Disable loan details if no items
                }
                showToast('info', 'Collateral item removed.');
            });
        });
    }

    function updateTotalCollateralValue() {
        let total = addedCollateralItems.reduce((sum, item) => sum + item.calculatedValue, 0);
        totalCollateralValueDisplay.textContent = `Rs. ${total.toFixed(2)}`;
        return total; // Return total for submission
    }

    async function fetchPriceConfig() {
        try {
            const response = await fetch('api/get_price_config.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            priceConfigData = await response.json();
            console.log('Price config loaded:', priceConfigData);
        } catch (error) {
            console.error('Error fetching price config:', error);
            showToast('error', 'Error loading price configurations. Manual rate entry may be required.');
        }
    }

    // --- Main Form Submission ---
    newLoanForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!selectedCustomerId) {
            showToast('error', 'Please select a customer first.');
            return;
        }
        if (addedCollateralItems.length === 0) {
            showToast('error', 'Please add at least one collateral item.');
            return;
        }

        const loanAmount = document.getElementById('loanAmount').value;
        const interestRate = document.getElementById('interestRate').value;
        const loanDuration = document.getElementById('loanDuration').value;
        const startDate = document.getElementById('startDate').value;

        if (!loanAmount || !interestRate || !loanDuration || !startDate) {
            showToast('error', 'Please fill all loan details.');
            return;
        }

        // Prepare form data for submission
        const formData = new FormData();
        formData.append('action', 'add_loan');
        formData.append('customerId', selectedCustomerId);
        formData.append('loanAmount', loanAmount);
        formData.append('interestRate', interestRate);
        formData.append('loanDuration', loanDuration);
        formData.append('startDate', startDate);
        formData.append('totalCollateralValue', updateTotalCollateralValue()); // Ensure this is sent if needed backend
        
        // Append collateral items as a JSON string to preserve structure
        formData.append('collateralItems', JSON.stringify(addedCollateralItems));

        // Send AJAX request
        const submitBtn = document.getElementById('createLoanBtn');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Loan...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/add_loan.php', { // Corrected API endpoint
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            // Reset button state
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;

            if (data.success) {
                showToast('success', data.message);
                document.getElementById('newLoanForm').reset();
                // Clear customer info display
                document.getElementById('selectedCustomerInfoDisplay').classList.add('hidden');
                document.getElementById('selectedCustomerId').value = '';
                // Clear collateral items display and input area
                document.getElementById('collateralItemsDisplayContainer').innerHTML = '<p class="text-gray-500 text-center">No collateral items added yet.</p>';
                document.getElementById('collateralItemInputArea').innerHTML = '';
                document.getElementById('totalCollateralValueDisplay').textContent = 'Rs. 0.00';
                addedCollateralItems = []; // Reset collateral items array

                // Disable steps 2 and 3 again
                document.getElementById('collateralDetailsSection').classList.add('disabled');
                document.getElementById('loanDetailsSection').classList.add('disabled');

            } else {
                showToast('error', data.message);
            }
        } catch (error) {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            showToast('error', 'An error occurred while creating the loan. Check console for details.');
            console.error('Error creating loan:', error);
        }
    });

    // --- Initialization ---
    async function initialize() {
        console.log('Initializing new loan assignment application...');
        initializeCameraFunctionality();
        await fetchPriceConfig();
        // Initially disable collateral and loan details sections
        collateralDetailsSection.classList.add('disabled');
        loanDetailsSection.classList.add('disabled');
        // Render initial empty collateral input form
        renderCollateralItemInputForm();
        console.log('New loan assignment application initialized.');
    }

    initialize();
}); 