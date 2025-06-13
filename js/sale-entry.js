// Function to update material details title based on selected material type
function updateMaterialDetailsTitle() {
    const materialType = document.getElementById('materialType').value;
    const materialDetailsTitle = document.getElementById('materialDetailsTitle');
    if (materialDetailsTitle) {
        materialDetailsTitle.textContent = `Material Details (${materialType})`;
    }
}

// Add event listener for material type change
document.addEventListener('DOMContentLoaded', function() {
    const materialTypeSelect = document.getElementById('materialType');
    if (materialTypeSelect) {
        materialTypeSelect.addEventListener('change', function() {
            updateMaterialDetailsTitle();
            calculatePurityRate();
        });
        // Set initial title
        updateMaterialDetailsTitle();
    }
}); 