function loadStockStats() {
    fetch('stock_functions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json'
        },
        body: 'action=getStockStats'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                // Check if the response starts with HTML (error)
                if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<br')) {
                    throw new Error('Server returned HTML instead of JSON');
                }
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data.error) {
            console.error('Server error:', data.error);
            const container = document.querySelector('.stats-container');
            if (container) {
                container.innerHTML = `<div class="text-red-500 p-4">Error: ${data.error}</div>`;
            }
            return;
        }
        updateStatsDisplay(data);
        initializeStatsCards(); // Add click handlers to stats cards
    })
    .catch(error => {
        console.error('Error loading stock stats:', error);
        // Optionally show error to user
        const container = document.querySelector('.stats-container');
        if (container) {
            container.innerHTML = '<div class="text-red-500 p-4">Error loading stock statistics. Please try again later.</div>';
        }
    });
}

function updateStatsDisplay(stats) {
    const container = document.querySelector('.stats-container');
    if (!container) return;

    container.innerHTML = stats.map(stat => `
        <div class="stats-card cursor-pointer bg-white p-3 shadow-sm" 
             data-material="${stat.material_type}" 
             data-purity="${stat.purity}">
            <div class="text-sm font-medium text-gray-600">${stat.material_type} ${stat.purity}%</div>
            <div class="text-lg font-bold text-gray-800">${stat.total_stock}g</div>
            <div class="text-xs text-blue-600">Available: ${stat.remaining_stock}g</div>
            <div class="text-xs text-gray-500">${stat.total_items} items</div>
        </div>
    `).join('');
}

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