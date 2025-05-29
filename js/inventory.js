// Function to load all inventory data
async function loadInventoryData() {
    try {
        const response = await fetch('../api/load_inventory_data.php');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.json();
        
        // Update UI with the loaded data
        updateInventoryMetals(data.inventory_metals);
        updateJewelleryItems(data.jewellery_items);
        updateSalesData(data.jewellery_sales);
        updateCustomerOrders(data.customer_orders);
        updateCustomers(data.customers);
        updateKarigars(data.karigars);
        
        return data;
    } catch (error) {
        console.error('Error loading inventory data:', error);
        showErrorNotification('Failed to load inventory data. Please try again.');
    }
}

// Function to update inventory metals section
function updateInventoryMetals(metals) {
    const container = document.getElementById('inventoryMetalsContainer');
    if (!container) return;

    let html = '';
    metals.forEach(metal => {
        html += `
            <div class="stat-card min-w-[100px] stat-gradient-${getMetalGradient(metal.material_type)} rounded-xl px-2 py-1.5 shadow-md">
                <div class="flex items-center justify-between">
                    <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                        <i class="fas fa-coins text-${getMetalColor(metal.material_type)} text-[11px]"></i>
                    </div>
                </div>
                <p class="text-sm font-bold text-gray-800 mt-1">${formatWeight(metal.current_stock)} ${metal.unit_measurement}</p>
                <p class="text-[11px] text-gray-600 font-medium">${metal.stock_name} <span class="font-normal">${metal.purity}%</span></p>
            </div>
        `;
    });
    container.innerHTML = html;
}

// Function to update jewellery items section
function updateJewelleryItems(items) {
    const container = document.getElementById('jewelleryItemsContainer');
    if (!container) return;

    let html = '';
    items.forEach(item => {
        html += `
            <div class="menu-card menu-gradient-${getJewelleryGradient(item.jewelry_type)} rounded-2xl p-2 shadow-lg">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-${getJewelleryIcon(item.jewelry_type)} text-${getJewelleryColor(item.jewelry_type)} text-xs"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-800">${item.product_name}</h3>
                        <p class="text-xs text-gray-600">${formatWeight(item.net_weight)}g | ${item.material_type} ${item.purity}%</p>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

// Helper function to get metal gradient
function getMetalGradient(materialType) {
    const gradients = {
        'Gold': 'amber',
        'Silver': 'slate',
        'Platinum': 'indigo',
        'Diamond': 'blue'
    };
    return gradients[materialType] || 'gray';
}

// Helper function to get metal color
function getMetalColor(materialType) {
    const colors = {
        'Gold': 'amber-500',
        'Silver': 'slate-500',
        'Platinum': 'indigo-500',
        'Diamond': 'blue-500'
    };
    return colors[materialType] || 'gray-500';
}

// Helper function to get jewellery gradient
function getJewelleryGradient(jewelryType) {
    const gradients = {
        'Ring': 'purple',
        'Necklace': 'blue',
        'Earring': 'pink',
        'Bracelet': 'green',
        'Chain': 'yellow'
    };
    return gradients[jewelryType] || 'gray';
}

// Helper function to get jewellery icon
function getJewelleryIcon(jewelryType) {
    const icons = {
        'Ring': 'ring',
        'Necklace': 'link',
        'Earring': 'gem',
        'Bracelet': 'circle',
        'Chain': 'link'
    };
    return icons[jewelryType] || 'gem';
}

// Helper function to get jewellery color
function getJewelleryColor(jewelryType) {
    const colors = {
        'Ring': 'purple-600',
        'Necklace': 'blue-600',
        'Earring': 'pink-600',
        'Bracelet': 'green-600',
        'Chain': 'yellow-600'
    };
    return colors[jewelryType] || 'gray-600';
}

// Helper function to format weight
function formatWeight(weight) {
    return parseFloat(weight).toFixed(3);
}

// Function to show error notification
function showErrorNotification(message) {
    // Implement your notification system here
    console.error(message);
    // Example using a simple alert
    alert(message);
}

// Initialize data loading when the page loads
document.addEventListener('DOMContentLoaded', () => {
    loadInventoryData();
}); 