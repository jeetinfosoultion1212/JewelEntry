
/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

const FIRM_DETAILS_KEY = 'jewelryFirmDetails';
const STAFF_LIST_KEY = 'jewelryStaffList';
const DEFAULT_LOGO_SRC = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj48Y2lyY2xlIGZpbGw9IiNDQ0MiIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjxwYXRoIGZpbGw9IiNGRkYiIGQ9Ik01MCA1OWMtOC4yODQgMC0xNS02LjcxNi0xNS0xNXM2LjcxNi0xNSAxNS0xNCAxNSA2LjcxNiAxNSAxNS02LjcxNiAxNS0xNSAxNXptMC0yNWMtNS41MjMgMC0xMCA0LjQ3Ny0xMCAxMHM0LjQ3NyAxMCAxMCAxMCAxMC00LjQ3NyAxMC0xMC00LjQ3Ny0xMC0xMC0xMHoiLz48cGF0aCBmaWxsPSIjRkZGIiBkPSJNNzIgNzJoLTEuNWMtMS4xNi0xLjMxLS44My0yLjY2LS41LTMuNWwuNjYtMS42N2MtLjQ5LTEuMy0xLjYxLTIuODEtMy4zNi0zLjg2YTE3LjQ0IDE3LjQ0IDAgMCAxLTIuNzQtMS42NWMtNC4zMy0yLjA1LTEwLjM2LTIuMDUtMTQuNzYgMCAxLjAxMy40NDMgMS45MjIgMS4wMzQgMi43MyAxLjY1IDIgMS4xNiAzLjE5IDIuNzkgMy4zOCA0LjA0bC42NiAxLjY2Yy4zMy44NC42NiAyLjE5LS41IDMuNWgtMS41Yy0xMSAwLTIwLjI1LTguNjMtMjAuMjUtMTkuNUMzMSA0MS4yNyA0MC4yNyAzMiA1MS41IDMyIDYyLjczIDMyIDcyIDQxLjI3IDcyIDUyLjVjMCAxMC44My05LjI5IDE5LjUtMjAgMTkuNWgtMXoiLz48L2c+PC9zdmc+'; // Default placeholder

let staffList = [];
let currentlyEditingStaffIndex = null;

// DOM Elements
let firmLogoPreview, firmLogoInput, firmDetailsForm;
let staffListContainer, staffModal, staffModalContent, staffModalTitle, staffForm, staffEditIndexInput, noStaffMessage;

const setActiveNavButtonGlobal = (activeNavId) => {
    document.querySelectorAll('.nav-btn').forEach(bElement => {
        const b = bElement;
        const iconDiv = b.querySelector('div');
        const textSpan = b.querySelector('span');
        const iconI = b.querySelector('i');
        const navId = b.dataset.navId;

        if (iconDiv) {
            iconDiv.className = 'w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center';
        }
        if (iconI) {
            iconI.classList.remove('text-white');
            ['text-blue-500', 'text-green-500', 'text-purple-500', 'text-red-500', 'text-amber-500'].forEach(cls => iconI.classList.remove(cls));
            iconI.classList.add('text-gray-400');
        }
        if (textSpan) {
            textSpan.className = 'text-xs text-gray-400 font-medium';
        }

        if (navId === activeNavId) {
            let colorName = 'amber'; // Default for profile
            if (iconI) {
                if (iconI.classList.contains('fa-home')) colorName = 'blue';
                else if (iconI.classList.contains('fa-search')) colorName = 'green';
                else if (iconI.classList.contains('fa-plus-circle')) colorName = 'purple';
                else if (iconI.classList.contains('fa-bell')) colorName = 'red';
                else if (iconI.classList.contains('fa-user-circle')) colorName = 'amber';
            }
            
            if (iconDiv) {
                iconDiv.className = `w-8 h-8 bg-gradient-to-br from-${colorName}-500 to-${colorName}-600 rounded-lg flex items-center justify-center shadow-lg`;
            }
            if (iconI) {
                iconI.classList.remove('text-gray-400');
                iconI.classList.add('text-white');
            }
            if (textSpan) {
                textSpan.className = `text-xs text-${colorName}-600 font-bold`;
            }
        }
    });
};


// Firm Details Functions
function loadFirmDetails() {
    const details = JSON.parse(localStorage.getItem(FIRM_DETAILS_KEY) || '{}');
    firmDetailsForm.firmName.value = details.name || '';
    firmDetailsForm.firmTagline.value = details.tagline || '';
    firmDetailsForm.firmAddress1.value = details.address1 || '';
    firmDetailsForm.firmAddress2.value = details.address2 || '';
    firmDetailsForm.firmCity.value = details.city || '';
    firmDetailsForm.firmPincode.value = details.pincode || '';
    firmDetailsForm.firmPhone.value = details.phone || '';
    firmDetailsForm.firmEmail.value = details.email || '';
    firmDetailsForm.firmGST.value = details.gst || '';
    firmLogoPreview.src = details.logo || DEFAULT_LOGO_SRC;
}

function saveFirmDetails(event) {
    event.preventDefault();
    const details = {
        name: firmDetailsForm.firmName.value,
        tagline: firmDetailsForm.firmTagline.value,
        address1: firmDetailsForm.firmAddress1.value,
        address2: firmDetailsForm.firmAddress2.value,
        city: firmDetailsForm.firmCity.value,
        pincode: firmDetailsForm.firmPincode.value,
        phone: firmDetailsForm.firmPhone.value,
        email: firmDetailsForm.firmEmail.value,
        gst: firmDetailsForm.firmGST.value,
        logo: firmLogoPreview.src // Already base64 from handleLogoChange
    };
    localStorage.setItem(FIRM_DETAILS_KEY, JSON.stringify(details));
    alert('Firm details saved!');
}

function handleLogoChange(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            firmLogoPreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// Staff Management Functions
function loadStaffList() {
    staffList = JSON.parse(localStorage.getItem(STAFF_LIST_KEY) || '[]');
    renderStaffList();
}

function saveStaffList() {
    localStorage.setItem(STAFF_LIST_KEY, JSON.stringify(staffList));
}

function renderStaffList() {
    staffListContainer.innerHTML = ''; // Clear existing
    if (staffList.length === 0) {
        noStaffMessage.classList.remove('hidden');
        return;
    }
    noStaffMessage.classList.add('hidden');

    staffList.forEach((staff, index) => {
        const staffCard = document.createElement('div');
        staffCard.className = 'bg-white bg-opacity-80 p-3 rounded-lg shadow-sm mb-2 flex justify-between items-center';
        
        const infoDiv = document.createElement('div');
        const nameEl = document.createElement('p');
        nameEl.className = 'text-sm font-semibold text-gray-700';
        nameEl.textContent = staff.name;
        const roleEl = document.createElement('p');
        roleEl.className = 'text-xs text-gray-500';
        roleEl.textContent = staff.role;
        infoDiv.appendChild(nameEl);
        infoDiv.appendChild(roleEl);

        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'space-x-2';

        const editBtn = document.createElement('button');
        editBtn.className = 'text-purple-600 hover:text-purple-800 text-sm p-1';
        editBtn.innerHTML = '<i class="fas fa-edit"></i>';
        editBtn.onclick = () => openStaffModal(staff, index);
        
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'text-red-500 hover:text-red-700 text-sm p-1';
        deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
        deleteBtn.onclick = () => deleteStaff(index);

        actionsDiv.appendChild(editBtn);
        actionsDiv.appendChild(deleteBtn);
        staffCard.appendChild(infoDiv);
        staffCard.appendChild(actionsDiv);
        staffListContainer.appendChild(staffCard);
    });
}

function openStaffModal(staffToEdit = null, index = -1) {
    staffForm.reset();
    currentlyEditingStaffIndex = index;
    staffEditIndexInput.value = index;

    if (staffToEdit) {
        staffModalTitle.textContent = 'Edit Staff Member';
        staffForm.staffName.value = staffToEdit.name;
        staffForm.staffRole.value = staffToEdit.role;
        staffForm.staffPhone.value = staffToEdit.phone || '';
        staffForm.staffEmail.value = staffToEdit.email || '';
    } else {
        staffModalTitle.textContent = 'Add New Staff Member';
    }
    staffModal.classList.remove('hidden');
    setTimeout(() => staffModalContent.classList.remove('scale-95', 'opacity-0'), 10); // For transition
}

function closeStaffModal() {
    staffModalContent.classList.add('scale-95', 'opacity-0');
    setTimeout(() => staffModal.classList.add('hidden'), 150);
}

function handleSaveStaff(event) {
    event.preventDefault();
    const name = staffForm.staffName.value.trim();
    const role = staffForm.staffRole.value.trim();
    const phone = staffForm.staffPhone.value.trim();
    const email = staffForm.staffEmail.value.trim();

    if (!name || !role) {
        alert('Staff name and role are required.');
        return;
    }

    const staffData = { name, role, phone, email };
    
    if (currentlyEditingStaffIndex > -1) {
        staffList[currentlyEditingStaffIndex] = staffData;
    } else {
        staffList.push(staffData);
    }
    
    saveStaffList();
    renderStaffList();
    closeStaffModal();
}

function deleteStaff(index) {
    if (confirm(`Are you sure you want to delete ${staffList[index].name}?`)) {
        staffList.splice(index, 1);
        saveStaffList();
        renderStaffList();
    }
}


document.addEventListener('DOMContentLoaded', () => {
    // Initialize DOM Element Variables
    firmLogoPreview = document.getElementById('firmLogoPreview');
    firmLogoInput = document.getElementById('firmLogoInput');
    firmDetailsForm = document.getElementById('firmDetailsForm');
    
    staffListContainer = document.getElementById('staffListContainer');
    noStaffMessage = document.getElementById('noStaffMessage');
    staffModal = document.getElementById('staffModal');
    staffModalContent = document.getElementById('staffModalContent');
    staffModalTitle = document.getElementById('staffModalTitle');
    staffForm = document.getElementById('staffForm');
    staffEditIndexInput = document.getElementById('staffEditIndex');
    
    const addNewStaffBtn = document.getElementById('addNewStaffBtn');
    const closeStaffModalBtn = document.getElementById('closeStaffModalBtn');
    const cancelStaffModalBtn = document.getElementById('cancelStaffModalBtn');

    // Set active nav
    setActiveNavButtonGlobal('profile');

    // Load initial data
    loadFirmDetails();
    loadStaffList();

    // Event Listeners
    firmDetailsForm.addEventListener('submit', saveFirmDetails);
    firmLogoInput.addEventListener('change', handleLogoChange);
    
    addNewStaffBtn.addEventListener('click', () => openStaffModal());
    closeStaffModalBtn.addEventListener('click', closeStaffModal);
    cancelStaffModalBtn.addEventListener('click', closeStaffModal);
    staffForm.addEventListener('submit', handleSaveStaff);

    // Close modal on escape key
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !staffModal.classList.contains('hidden')) {
            closeStaffModal();
        }
    });
    // Close modal on outside click
    staffModal.addEventListener('click', (event) => {
        if (event.target === staffModal) {
            closeStaffModal();
        }
    });
});
