// Function to load current rates
async function loadJewelleryRates() {
    try {
        const response = await fetch('../api/manage_jewellery_rates.php');
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Failed to load rates');
        }
        
        if (!data.rates) {
            throw new Error('No rates data received');
        }
        
        updateRatesDisplay(data.rates);
    } catch (error) {
        console.error('Error loading rates:', error);
        showErrorNotification(error.message || 'Failed to load rates. Please try again.');
    }
}

// Function to update rates display
function updateRatesDisplay(rates) {
    const container = document.getElementById('jewelleryRatesContainer');
    if (!container) {
        console.error('Rates container not found');
        return;
    }

    if (!Array.isArray(rates) || rates.length === 0) {
        container.innerHTML = `
            <div class="w-full text-center py-4">
                <p class="text-gray-500">No rates configured yet</p>
                <button onclick="openRateModal('', '', '', '')" 
                        class="mt-2 text-sm text-purple-600 hover:text-purple-800">
                    <i class="fas fa-plus-circle mr-1"></i>Add First Rate
                </button>
            </div>
        `;
        return;
    }

    let html = '';
    rates.forEach(rate => {
        try {
            html += `
                <div class="stat-card min-w-[100px] stat-gradient-${getMaterialGradient(rate.material_type)} rounded-xl px-2 py-1.5 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center shadow-sm">
                            <i class="fas fa-coins text-${getMaterialColor(rate.material_type)} text-[11px]"></i>
                        </div>
                        <button onclick="openRateModal('${rate.material_type}', ${rate.purity}, '${rate.unit}', ${rate.rate})" 
                                class="text-xs text-gray-600 hover:text-gray-800">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mt-1">₹${formatNumber(rate.rate)}/${rate.unit}</p>
                    <p class="text-[11px] text-gray-600 font-medium">${rate.material_type} <span class="font-normal">${rate.purity}%</span></p>
                    <p class="text-[10px] text-gray-500">Updated: ${formatDate(rate.effective_date)}</p>
                </div>
            `;
        } catch (error) {
            console.error('Error rendering rate card:', error, rate);
        }
    });
    container.innerHTML = html;
}

// Function to open rate modal
function openRateModal(materialType, purity, unit, currentRate) {
    const modal = document.getElementById('rateModal');
    if (!modal) {
        console.error('Rate modal not found');
        return;
    }

    const materialInput = document.getElementById('materialType');
    const purityInput = document.getElementById('purity');
    const unitInput = document.getElementById('unit');
    const rateInput = document.getElementById('rate');

    if (!materialInput || !purityInput || !unitInput || !rateInput) {
        console.error('Required form inputs not found');
        return;
    }

    materialInput.value = materialType || '';
    purityInput.value = purity || '';
    unitInput.value = unit || '';
    rateInput.value = currentRate || '';

    modal.classList.remove('hidden');
}

// Function to close rate modal
function closeRateModal() {
    const modal = document.getElementById('rateModal');
    if (!modal) {
        console.error('Rate modal not found');
        return;
    }
    modal.classList.add('hidden');
}

// Function to save rate
async function saveRate(event) {
    event.preventDefault();
    
    try {
        const materialType = document.getElementById('materialType').value;
        const purity = parseFloat(document.getElementById('purity').value);
        const unit = document.getElementById('unit').value;
        const rate = parseFloat(document.getElementById('rate').value);

        // Validate inputs
        if (!materialType) throw new Error('Material type is required');
        if (isNaN(purity) || purity <= 0 || purity > 100) throw new Error('Purity must be between 0 and 100');
        if (!unit) throw new Error('Unit is required');
        if (isNaN(rate) || rate <= 0) throw new Error('Rate must be greater than 0');

        const formData = {
            material_type: materialType,
            purity: purity,
            unit: unit,
            rate: rate
        };

        const response = await fetch('../api/manage_jewellery_rates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Failed to save rate');
        }

        showSuccessNotification(data.message || 'Rate saved successfully');
        closeRateModal();
        loadJewelleryRates(); // Reload rates
    } catch (error) {
        console.error('Error saving rate:', error);
        showErrorNotification(error.message || 'Failed to save rate. Please try again.');
    }
}

// Helper function to get material gradient
function getMaterialGradient(materialType) {
    const gradients = {
        'Gold': 'amber',
        'Silver': 'slate',
        'Platinum': 'indigo',
        'Diamond': 'blue'
    };
    return gradients[materialType] || 'gray';
}

// Helper function to get material color
function getMaterialColor(materialType) {
    const colors = {
        'Gold': 'amber-500',
        'Silver': 'slate-500',
        'Platinum': 'indigo-500',
        'Diamond': 'blue-500'
    };
    return colors[materialType] || 'gray-500';
}

// Helper function to format number
function formatNumber(number) {
    try {
        return new Intl.NumberFormat('en-IN').format(number);
    } catch (error) {
        console.error('Error formatting number:', error);
        return number;
    }
}

// Helper function to format date
function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-IN', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        console.error('Error formatting date:', error);
        return dateString;
    }
}

// Function to show success notification
function showSuccessNotification(message) {
    // Implement your notification system here
    console.log('Success:', message);
    // Example using a simple alert
    alert(message);
}

// Function to show error notification
function showErrorNotification(message) {
    // Implement your notification system here
    console.error('Error:', message);
    // Example using a simple alert
    alert(message);
}

// Initialize when the page loads
document.addEventListener('DOMContentLoaded', () => {
    try {
        loadJewelleryRates();
        
        // Add event listener for rate form submission
        const rateForm = document.getElementById('rateForm');
        if (rateForm) {
            rateForm.addEventListener('submit', saveRate);
        } else {
            console.error('Rate form not found');
        }
    } catch (error) {
        console.error('Error initializing rates:', error);
        showErrorNotification('Failed to initialize rates. Please refresh the page.');
    }
}); 