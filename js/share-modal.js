// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get the share modal element
    const shareModal = document.getElementById('shareModal');
    
    // Only add event listener if the element exists
    if (shareModal) {
        shareModal.addEventListener('click', function() {
            // Your existing share modal code here
            // Assuming the modal has a close button with class 'close-modal'
            const closeModal = shareModal.querySelector('.close-modal');
            if (closeModal) {
                closeModal.addEventListener('click', () => {
                    shareModal.classList.add('hidden');
                });
            }

            // Close modal when clicking outside
            shareModal.addEventListener('click', (event) => {
                if (event.target === shareModal) {
                    shareModal.classList.add('hidden');
                }
            });
        });
    }
    // No else needed - we just don't add the listener if the element doesn't exist
}); 