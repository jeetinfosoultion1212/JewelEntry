document.addEventListener('DOMContentLoaded', function() {
    const addExpenseBtn = document.getElementById('addExpenseBtn');
    const addExpenseModal = document.getElementById('addExpenseModal');
    const closeModal = document.getElementById('closeModal');
    const cancelExpense = document.getElementById('cancelExpense');
    const expenseForm = document.getElementById('expenseForm');

    // Show modal
    addExpenseBtn.addEventListener('click', () => {
        addExpenseModal.classList.remove('hidden');
        addExpenseModal.classList.add('flex');
    });

    // Hide modal
    function hideModal() {
        addExpenseModal.classList.remove('flex');
        addExpenseModal.classList.add('hidden');
        expenseForm.reset();
    }

    closeModal.addEventListener('click', hideModal);
    cancelExpense.addEventListener('click', hideModal);

    // Close modal when clicking outside
    addExpenseModal.addEventListener('click', (e) => {
        if (e.target === addExpenseModal) {
            hideModal();
        }
    });

    // Handle form submission
    expenseForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(expenseForm);
        
        try {
            const response = await fetch('api/expenses.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Show success message
                showNotification('Expense added successfully', 'success');
                
                // Hide modal and reset form
                hideModal();
                
                // Reload the page to show new expense
                window.location.reload();
            } else {
                showNotification(data.message || 'Failed to add expense', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred while adding the expense', 'error');
        }
    });

    // Notification function
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        } text-white`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}); 