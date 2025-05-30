// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get the share modal element
    const shareModal = document.getElementById('shareModal');
    
    // Only add event listener if the element exists
    if (shareModal) {
        shareModal.addEventListener('click', function() {
            // Your existing share modal code here
        });
    }
    // No else needed - we just don't add the listener if the element doesn't exist
}); 