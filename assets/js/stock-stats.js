function loadStockStats() {
    fetch('stock_functions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=getStockStats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            console.error('Error:', data.error);
            return;
        }
        updateStatsDisplay(data);
        initializeStatsCards(); // Add click handlers to stats cards
    })
    .catch(error => console.error('Error:', error));
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