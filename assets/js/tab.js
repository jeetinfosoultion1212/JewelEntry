function switchTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Deactivate all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content and activate button
    const selectedTab = document.getElementById(tabId);
    const selectedBtn = document.querySelector(`[data-tab="${tabId}"]`);
    
    if (selectedTab) selectedTab.classList.add('active');
    if (selectedBtn) selectedBtn.classList.add('active');
    
    // Load data based on tab
    if (tabId === 'add-stock') {
        loadStockStats();
    }
}