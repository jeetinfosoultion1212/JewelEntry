document.addEventListener('DOMContentLoaded', function() {
    const settingsForm = document.getElementById('settingsForm');

    settingsForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(settingsForm);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('api/update_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                if (result.coupon_action === 'inserted') {
                    alert('Welcome coupon was not found in the coupons table, so a new record has been created.');
                }
            } else {
                showNotification(result.message || 'Failed to update settings', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred while updating settings', 'error');
        }
    });
});

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        'bg-blue-500'
    } text-white`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
} 