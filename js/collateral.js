// Collateral Items Management with compact table view
class CollateralManager {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.items = [];
        this.initialize();
    }

    initialize() {
        if (!this.container) {
            console.error('Collateral container not found');
            return;
        }

        this.render();
        this.attachEventListeners();
    }

    render() {
        this.container.innerHTML = `
            <div class="collateral-form bg-white p-4 rounded-lg shadow mb-4">
                <h3 class="text-lg font-semibold mb-3">Add New Collateral Item</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Material Type</label>
                        <select name="newMaterialType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm material-type" required>
                            <option value="">Select Material</option>
                            <option value="Gold">Gold</option>
                            <option value="Silver">Silver</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Purity (%)</label>
                        <input type="number" name="newPurity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm purity-input" placeholder="Enter purity" step="0.01" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Weight (grams)</label>
                        <input type="number" name="newWeight" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm weight-input" placeholder="Enter weight" step="0.001" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rate per gram</label>
                        <input type="number" name="newRatePerGram" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm rate-display" placeholder="Rate" step="0.01" min="0">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <input type="text" name="newDescription" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm" placeholder="Item description">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Image</label>
                        <div class="flex space-x-2">
                            <label class="flex-1 bg-white border border-gray-300 rounded-md py-2 px-3 cursor-pointer text-sm font-medium flex items-center justify-center">
                                <i class="fas fa-upload mr-2 text-green-500"></i> Choose File
                                <input type="file" name="newImage" accept="image/*" class="hidden collateral-image-input">
                            </label>
                            <button type="button" class="capture-image-btn bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2 px-4 rounded-md flex items-center justify-center">
                                <i class="fas fa-camera mr-2"></i> Capture
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" class="add-item-btn bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-md">
                        Add Item
                    </button>
                </div>
            </div>

            <!-- Collateral Items Table -->
            <div class="collateral-table bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Material</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate/Gram</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="collateralItemsTableBody">
                        <!-- Items will be added here dynamically -->
                    </tbody>
                </table>
            </div>
        `;
    }

    attachEventListeners() {
        const addItemBtn = this.container.querySelector('.add-item-btn');
        if (addItemBtn) {
            addItemBtn.addEventListener('click', () => this.handleAddItem());
        }

        // Initialize camera functionality
        this.initializeCameraFunctionality();
    }

    handleAddItem() {
        const form = this.container.querySelector('.collateral-form');
        const materialType = form.querySelector('[name="newMaterialType"]').value;
        const purity = form.querySelector('[name="newPurity"]').value;
        const weight = form.querySelector('[name="newWeight"]').value;
        const ratePerGram = form.querySelector('[name="newRatePerGram"]').value;
        const description = form.querySelector('[name="newDescription"]').value;
        const imageInput = form.querySelector('[name="newImage"]');
        
        if (!materialType || !purity || !weight || !ratePerGram) {
            this.showToast('Please fill in all required fields', 'error');
            return;
        }

        const item = {
            id: Date.now(), // Unique ID for the item
            materialType,
            purity,
            weight,
            ratePerGram,
            description,
            image: imageInput.files[0]
        };

        this.addItemToTable(item);
        this.items.push(item);
        form.reset();
        this.updateTotal();
    }

    addItemToTable(item) {
        const tableBody = document.getElementById('collateralItemsTableBody');
        if (!tableBody) return;

        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        row.dataset.itemId = item.id;
        
        // Create image preview URL if image exists
        let imagePreview = '';
        if (item.image) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = row.querySelector('.item-image-preview');
                if (img) img.src = e.target.result;
            };
            reader.readAsDataURL(item.image);
        }

        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.materialType}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.purity}%</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.weight}g</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₹${item.ratePerGram}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₹${(item.weight * item.ratePerGram).toFixed(2)}</td>
            <td class="px-6 py-4 text-sm text-gray-900">${item.description || '-'}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <img src="" alt="Item" class="item-image-preview h-10 w-10 rounded-full object-cover" style="display: ${item.image ? 'block' : 'none'}">
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button type="button" class="edit-item-btn text-green-600 hover:text-green-900 mr-3">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="remove-item-btn text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        // Add event listeners for edit and remove buttons
        const editBtn = row.querySelector('.edit-item-btn');
        const removeBtn = row.querySelector('.remove-item-btn');

        if (editBtn) {
            editBtn.addEventListener('click', () => this.handleEditItem(item.id));
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', () => this.handleRemoveItem(item.id));
        }

        tableBody.appendChild(row);
    }

    handleEditItem(itemId) {
        const item = this.items.find(i => i.id === itemId);
        if (!item) return;

        // Populate form with item data
        const form = this.container.querySelector('.collateral-form');
        form.querySelector('[name="newMaterialType"]').value = item.materialType;
        form.querySelector('[name="newPurity"]').value = item.purity;
        form.querySelector('[name="newWeight"]').value = item.weight;
        form.querySelector('[name="newRatePerGram"]').value = item.ratePerGram;
        form.querySelector('[name="newDescription"]').value = item.description || '';

        // Remove the item from the list and table
        this.items = this.items.filter(i => i.id !== itemId);
        const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
        if (row) row.remove();
    }

    handleRemoveItem(itemId) {
        this.items = this.items.filter(i => i.id !== itemId);
        const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
        if (row) row.remove();
        this.updateTotal();
    }

    updateTotal() {
        const total = this.items.reduce((sum, item) => {
            return sum + (parseFloat(item.weight) * parseFloat(item.ratePerGram));
        }, 0);

        // Dispatch event with total value
        const event = new CustomEvent('collateralTotalUpdated', { detail: { total } });
        document.dispatchEvent(event);
    }

    showToast(message, type = 'info') {
        // Implement your toast notification here
        console.log(`${type.toUpperCase()}: ${message}`);
    }

    initializeCameraFunctionality() {
        // Implement camera functionality here
        console.log('Camera functionality initialized');
    }

    getItems() {
        return this.items;
    }
}

// Export the class
window.CollateralManager = CollateralManager;
