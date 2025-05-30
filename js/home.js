/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

// Add ripple effect animation to CSS
const styleElement = document.createElement('style');
styleElement.textContent = `
    @keyframes ripple {
        0% { transform: scale(0); opacity: 1; }
        100% { transform: scale(1); opacity: 0; }
    }
`;
document.head.appendChild(styleElement);

const TRIAL_DURATION_DAYS = 7; 
const TRIAL_START_DATE_KEY = 'jewelEntryTrialStartDate';
let trialUpdateInterval = null;

// Metal Rates Constants and Variables (No API related constants now)
const USER_SET_RATES_KEY = 'jewelEntryUserSetRates';
const TOLA_IN_GRAMS = 11.6638;
const VORI_IN_GRAMS = 11.664; // Often same as Tola

let userSetRates = {}; // Loaded from localStorage { XAU: { rate: 7000, unit: 'gram', perGramRate: 7000 }, ... }
let currentEditingMetalCode = null; // For the modal: 'XAU', 'XAG', 'XPT'

// DOM Elements for Metal Rates Modal
let customRateModal, customRateModalContent, customRateModalTitle, 
    customRateInput, customRateUnitSelect, saveCustomRateBtn, closeCustomRateModalBtn,
    currentRateSourceIndicator, clearManualRateBtnElement, customRateModalMetalNamePlaceholder;

const METALS = {
  Gold:    { purity: '99.99',   label: 'Gold 99.99' },
  Silver:  { purity: '999.9',   label: 'Silver 999.9' },
  Platinum:{ purity: '95',      label: 'Platinum 95' }
};

// This variable should be set by PHP in the HTML, e.g.:
// <script>window.canEditRates = <?php echo $canEditRates ? 'true' : 'false'; ?>;</script>

function showErrorNotification(msg) { alert(msg); }
function showSuccessNotification(msg) { alert(msg); }

// Make togglePlans globally available
window.togglePlans = function() {
    const plansSection = document.getElementById('pricingPlansSection');
    if (plansSection) {
        plansSection.classList.toggle('hidden');
        // Add smooth animation
        if (!plansSection.classList.contains('hidden')) {
            plansSection.style.opacity = '0';
            plansSection.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                plansSection.style.opacity = '1';
                plansSection.style.transform = 'translateY(0)';
            }, 10);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Metal Rate Modal Elements
    customRateModal = document.getElementById('customRateModal');
    customRateModalContent = document.getElementById('customRateModalContent');
    customRateModalTitle = document.getElementById('customRateModalTitle');
    customRateModalMetalNamePlaceholder = document.getElementById('customRateModalMetalNamePlaceholder');
    customRateInput = document.getElementById('customRateInput');
    customRateUnitSelect = document.getElementById('customRateUnitSelect');
    saveCustomRateBtn = document.getElementById('saveCustomRateBtn');
    closeCustomRateModalBtn = document.getElementById('closeCustomRateModalBtn');
    currentRateSourceIndicator = document.getElementById('currentRateSourceIndicator');
    clearManualRateBtnElement = document.getElementById('clearManualRateBtn');
    
    loadUserSetRates();
    displayAllMetalRates();
    initializeMetalRateStatCardListeners();
    initializeCustomRateModalListeners();
    updateMarqueeWithStaticText();

    // Enhanced menu card interactions
    document.querySelectorAll('.menu-card').forEach(cardElement => {
        const card = cardElement;
        const favoriteButton = card.querySelector('.favorite-btn');

        card.addEventListener('click', function(event) {
            if (favoriteButton && (favoriteButton === event.target || favoriteButton.contains(event.target))) {
                return;
            }
            const ripple = document.createElement('div');
            ripple.className = 'absolute inset-0 bg-white bg-opacity-30 rounded-2xl';
            ripple.style.animation = 'ripple 0.6s ease-out';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Enhanced navigation interactions
    const navButtons = document.querySelectorAll('.nav-btn');
    const setActiveNavButton = (activeButton) => {
        navButtons.forEach(bElement => {
            const b = bElement;
            const iconDiv = b.querySelector('div');
            const textSpan = b.querySelector('span');
            const iconI = b.querySelector('i');

            b.style.transform = 'translateY(0)';
            if (iconDiv) {
                iconDiv.className = 'w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center transition-all duration-200';
            }
            if (iconI) {
                iconI.classList.remove('text-white');
                ['text-blue-500', 'text-green-500', 'text-purple-500', 'text-red-500', 'text-amber-500'].forEach(cls => iconI.classList.remove(cls));
                iconI.classList.add('text-gray-400');
            }
            if (textSpan) {
                textSpan.className = 'text-xs text-gray-400 font-medium transition-all duration-200';
            }
        });

        if (!activeButton) return;

        const currentIconDiv = activeButton.querySelector('div');
        const currentTextSpan = activeButton.querySelector('span');
        const currentIconI = activeButton.querySelector('i');
        const navId = activeButton.dataset.navId;

        let colorName = 'blue';
        if (navId === 'home') colorName = 'blue';
        else if (navId === 'search') colorName = 'green';
        else if (navId === 'add') colorName = 'purple';
        else if (navId === 'alerts_nav') colorName = 'red';
        else if (navId === 'profile') colorName = 'amber';

        if (currentIconDiv) {
            currentIconDiv.className = `w-8 h-8 bg-gradient-to-br from-${colorName}-500 to-${colorName}-600 rounded-lg flex items-center justify-center shadow-lg transition-all duration-200`;
        }
        if (currentIconI) {
            currentIconI.classList.remove('text-gray-400');
            currentIconI.classList.add('text-white');
        }
        if (currentTextSpan) {
            currentTextSpan.className = `text-xs text-${colorName}-600 font-bold transition-all duration-200`;
        }
        activeButton.style.transform = 'translateY(-5px)';
    };

    navButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            setActiveNavButton(this);
        });
    });

    const currentPath = window.location.pathname.split('/').pop();
    if (currentPath === 'index.html' || currentPath === '') {
        const homeButton = document.querySelector('.nav-btn[data-nav-id="home"]');
        if (homeButton) setActiveNavButton(homeButton);
        initializeTrial();
    } else if (currentPath === 'profile.html') {
        const profileButton = document.querySelector('.nav-btn[data-nav-id="profile"]');
        if (profileButton) setActiveNavButton(profileButton);
    }

    const favoriteButtonsDOM = document.querySelectorAll('.favorite-btn');
    const FAVORITE_PREFIX = 'favorite_module_';

    const updateFavoriteStar = (button, isFavorited) => {
        const icon = button.querySelector('i');
        if (icon) {
            if (isFavorited) {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-yellow-500');
                button.classList.add('favorited');
                button.classList.remove('text-gray-400');
                button.setAttribute('aria-pressed', 'true');
            } else {
                icon.classList.remove('fas', 'text-yellow-500');
                icon.classList.add('far');
                button.classList.remove('favorited');
                button.classList.add('text-gray-400');
                button.setAttribute('aria-pressed', 'false');
            }
        }
    };

    favoriteButtonsDOM.forEach(btn => {
        const button = btn;
        const menuCard = button.closest('.menu-card');
        if (!menuCard) return;
        const moduleId = menuCard.dataset.moduleId;
        if (!moduleId) return;

        const isFavorited = localStorage.getItem(FAVORITE_PREFIX + moduleId) === 'true';
        updateFavoriteStar(button, isFavorited);

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            let currentIsFavorited = localStorage.getItem(FAVORITE_PREFIX + moduleId) === 'true';
            currentIsFavorited = !currentIsFavorited;
            localStorage.setItem(FAVORITE_PREFIX + moduleId, currentIsFavorited.toString());
            updateFavoriteStar(button, currentIsFavorited);
        });
    });

    // Hide or disable rate edit buttons if not allowed
    if (typeof window.canEditRates !== 'undefined' && !window.canEditRates) {
        document.querySelectorAll('.rate-edit-btn').forEach(btn => {
            btn.style.display = 'none';
        });
    }

    // Store Management menu navigation for all modules
    const storeMenuMap = {
        'inventory': 'add.php',
        'sales': 'sale-entry.php',
        'customers': 'customer.php',
        'catalog': 'catalog.html',
        'billing': 'billing.php',
        'repairs': 'repairs.php',
        'analytics': 'analytics.php',
        'staff': 'staff.php',
        'suppliers': 'suppliers.php',
        'testing': 'testing.php',
        'security': 'security.php',
        'loans': 'loans.php',
        'bookings': 'bookings.php',
        'alerts': 'alerts.php',
        'settings': 'settings.php'
    };
    document.querySelectorAll('.menu-card').forEach(card => {
        const moduleId = card.getAttribute('data-module-id');
        // Skip if this is an <a> tag (let default link work)
        if (storeMenuMap[moduleId] && card.tagName !== 'A') {
            card.addEventListener('click', function(event) {
                // Prevent navigation if favorite button is clicked
                if (event.target.closest('.favorite-btn')) return;
                window.location.href = storeMenuMap[moduleId];
            });
        }
    });
});

window.addEventListener('load', function() {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((cardElement, index) => {
        if (!cardElement.dataset.metalCode) {
            const card = cardElement;
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease-out, transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50 + index * 70);
        }
    });
});

// Trial Management Logic
function initializeTrial() {
    let trialStartDateString = localStorage.getItem(TRIAL_START_DATE_KEY);
    if (!trialStartDateString) {
        trialStartDateString = new Date().toISOString();
        localStorage.setItem(TRIAL_START_DATE_KEY, trialStartDateString);
    }
    const trialStatusContainer = document.getElementById('trialStatusContainer');
    if (!trialStatusContainer) {
        console.warn("Trial status container not found. Skipping trial initialization.");
        return;
    }
    updateTrialStatus();
    if (trialUpdateInterval) clearInterval(trialUpdateInterval);
    trialUpdateInterval = setInterval(updateTrialStatus, 1000);
}

function updateTrialStatus() {
    const trialStatusContainer = document.getElementById('trialStatusContainer');
    if (!trialStatusContainer) {
         if(trialUpdateInterval) clearInterval(trialUpdateInterval);
        return;
    }
    const trialStartDateString = localStorage.getItem(TRIAL_START_DATE_KEY);
    if (!trialStartDateString) {
        console.warn("Trial start date key not found in updateTrialStatus. Stopping timer.");
        trialStatusContainer.innerHTML = `<div class="bg-yellow-50 border border-yellow-300 p-4 rounded-lg shadow-md text-center"><i class="fas fa-info-circle text-yellow-500 text-xl mb-2"></i><p class="text-sm text-yellow-700 font-semibold">Could not determine trial status.</p><p class="text-xs text-gray-600 mb-2">Trial start date missing.</p><button onclick="resetJewelEntryTrial()" class="text-xs bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-md shadow">Reset Trial</button></div>`;
        if (trialUpdateInterval) clearInterval(trialUpdateInterval);
        return;
    }
    const trialStartDate = new Date(trialStartDateString);
    if (isNaN(trialStartDate.getTime())) {
        console.error("Invalid trial start date found:", trialStartDateString);
        localStorage.removeItem(TRIAL_START_DATE_KEY);
        trialStatusContainer.innerHTML = `<div class="bg-red-50 border border-red-300 p-4 rounded-lg shadow-md text-center"><i class="fas fa-exclamation-circle text-red-500 text-xl mb-2"></i><p class="text-sm text-red-700 font-semibold">Error loading trial status.</p><p class="text-xs text-gray-600 mb-2">Invalid date data. Trial data cleared.</p><button onclick="resetJewelEntryTrial()" class="text-xs bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-md shadow">Reset & Start New Trial</button></div>`;
        if (trialUpdateInterval) clearInterval(trialUpdateInterval);
        return;
    }
    const trialEndDate = new Date(trialStartDate);
    trialEndDate.setDate(trialStartDate.getDate() + TRIAL_DURATION_DAYS);
    const currentDate = new Date();
    const timeLeft = trialEndDate.getTime() - currentDate.getTime();
    let htmlContent = '';
    let timerColorClass = 'text-green-600';

    if (timeLeft > 0) {
        const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
        if (days < 1 && timeLeft > 0) timerColorClass = 'text-yellow-500';
        else if (days >=1 ) timerColorClass = 'text-green-600';
        else timerColorClass = 'text-red-600';
        htmlContent = `<div class="bg-white/80 p-3.5 rounded-lg shadow-lg border border-slate-200"><div class="flex items-center justify-center mb-2"><i class="fas fa-hourglass-half mr-2.5 text-xl text-purple-600"></i><h3 class="text-base font-semibold text-purple-700">Trial Version Active</h3></div><div id="trialCountdownTimer" class="text-center mb-2.5" aria-live="polite"><span class="text-2xl font-bold ${timerColorClass}">${String(days).padStart(2,'0')}</span><span class="text-xs ${timerColorClass} opacity-80">days</span> <span class="text-2xl font-bold ${timerColorClass}">${String(hours).padStart(2,'0')}</span><span class="text-xs ${timerColorClass} opacity-80">hr</span> <span class="text-2xl font-bold ${timerColorClass}">${String(minutes).padStart(2,'0')}</span><span class="text-xs ${timerColorClass} opacity-80">min</span> <span class="text-2xl font-bold ${timerColorClass}">${String(seconds).padStart(2,'0')}</span><span class="text-xs ${timerColorClass} opacity-80">sec</span><p class="text-[11px] ${timerColorClass} opacity-90 font-medium mt-0.5">remaining</p></div><p class="text-xs text-gray-600 mb-3 text-center">Explore all premium features during your trial period.</p><div class="flex justify-center space-x-2.5"><button class="text-xs bg-blue-500 hover:bg-blue-600 text-white px-3.5 py-2 rounded-md shadow transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300 font-medium"><i class="fas fa-headset mr-1.5"></i> Contact Support</button><button class="text-xs bg-green-500 hover:bg-green-600 text-white px-3.5 py-2 rounded-md shadow transition-colors focus:outline-none focus:ring-2 focus:ring-green-300 font-medium"><i class="fas fa-file-signature mr-1.5"></i> Contact Sales</button></div></div>`;
    } else {
        htmlContent = `<div class="bg-red-50 border-2 border-red-400 p-4 rounded-lg shadow-lg text-center"><div class="flex items-center justify-center mb-2"><i class="fas fa-exclamation-triangle mr-2.5 text-2xl text-red-600"></i><h3 class="text-base font-semibold text-red-700">Your Trial Has Expired!</h3></div><p class="text-sm text-gray-700 mb-3">To continue enjoying JewelEntry, please upgrade your plan.</p><button onclick="document.getElementById('pricingPlansSection').scrollIntoView({ behavior: 'smooth' });" class="w-full max-w-xs mx-auto bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition-colors focus:outline-none focus:ring-2 focus:ring-amber-300"><i class="fas fa-rocket mr-2"></i> View Pricing & Upgrade</button></div>`;
        if (trialUpdateInterval) clearInterval(trialUpdateInterval);
    }
    trialStatusContainer.innerHTML = htmlContent;
}

window.resetJewelEntryTrial = function() {
    localStorage.removeItem(TRIAL_START_DATE_KEY);
    if (trialUpdateInterval) clearInterval(trialUpdateInterval);
    const trialStatusContainer = document.getElementById('trialStatusContainer');
    if (trialStatusContainer) {
        trialStatusContainer.innerHTML = `<p class="text-center text-gray-500 text-sm py-3"><i class="fas fa-spinner fa-spin mr-2"></i>Resetting trial...</p>`;
    }
    setTimeout(() => initializeTrial(), 50);
    console.log('JewelEntry trial reset. UI should update.');
};

// Metal Rates Functions (API Fetching Removed)

function updateMarqueeWithStaticText() {
    const marquee = document.getElementById('liveRatesMarquee');
    if (marquee) {
        if (!marquee.classList.contains('pulse-animation')) { // Ensure animation if it was removed
            marquee.classList.add('pulse-animation');
        }
    }
}

function displayAllMetalRates() {
    const metalsToDisplay = [
        { code: 'XAU', name: 'Gold 24K', rateElId: 'gold24kRate', unitElId: 'gold24kRateUnit', manualIndicatorId: 'gold24kManualIndicator' },
        { code: 'XAG', name: 'Silver', rateElId: 'silverRate', unitElId: 'silverRateUnit', manualIndicatorId: 'silverManualIndicator' },
        { code: 'XPT', name: 'Platinum', rateElId: 'platinumRate', unitElId: 'platinumRateUnit', manualIndicatorId: 'platinumManualIndicator' }
    ];
    
    metalsToDisplay.forEach(metal => {
        updateSingleStatCard(metal.code, metal.rateElId, metal.unitElId, metal.manualIndicatorId);
    });
}

function updateSingleStatCard(metalCode, rateElId, unitElId, manualIndicatorId) {
    const rateElement = document.getElementById(rateElId);
    const unitElement = document.getElementById(unitElId);
    const manualIndicatorElement = document.getElementById(manualIndicatorId);

    if (!rateElement || !unitElement || !manualIndicatorElement) return;

    const userRateInfo = userSetRates[metalCode];

    if (userRateInfo && typeof userRateInfo.rate === 'number') {
        rateElement.textContent = `₹${userRateInfo.rate.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        unitElement.textContent = `/${getUnitDisplayName(userRateInfo.unit)}`;
        manualIndicatorElement.classList.remove('hidden');
    } else { 
        rateElement.textContent = "Set Rate"; 
        unitElement.textContent = ""; 
        manualIndicatorElement.classList.add('hidden');
    }
    // Percentage change element logic removed
}


function getUnitDisplayName(unitKey) {
    switch (unitKey) {
        case 'gram': return 'gram';
        case '10gram': return '10g';
        case 'tola': return 'Tola';
        case 'vori': return 'Vori';
        default: return unitKey;
    }
}

function loadUserSetRates() {
    const storedRates = localStorage.getItem(USER_SET_RATES_KEY);
    if (storedRates) {
        userSetRates = JSON.parse(storedRates);
    }
}

function saveUserSetRates() {
    localStorage.setItem(USER_SET_RATES_KEY, JSON.stringify(userSetRates));
}

function initializeMetalRateStatCardListeners() {
    const statCards = document.querySelectorAll('.stat-card[data-metal-code]'); // Select only cards with metal code
    statCards.forEach(card => {
        card.addEventListener('click', () => {
            const materialType = card.dataset.metalName.split(' ')[0]; // Extract 'Gold', 'Silver', 'Platinum'
            let purity = '';
            const metalName = card.dataset.metalName; // e.g., "Gold 24K"
            if (metalName.includes('99.99')) purity = '99.99';
            else if (metalName.includes('999.9')) purity = '999.9';
            else if (metalName.includes('95')) purity = '95';
            // Add more purity checks if needed

            // Attempt to get the current rate and unit displayed on the card
            const rateTextElement = card.querySelector('.rate-text');
            const unitTextElement = card.querySelector('span[id$="RateUnit"]');
            
            let currentRate = null;
            if (rateTextElement && rateTextElement.textContent && rateTextElement.textContent !== 'Set Rate') {
                 currentRate = parseFloat(rateTextElement.textContent.replace('₹', '').replace(',', ''));
            }

            let currentUnit = 'gram'; // Default unit
            if (unitTextElement && unitTextElement.textContent) {
                 const unitDisplay = unitTextElement.textContent.replace('/', '').trim();
                 // Convert display name back to key if necessary
                 if (unitDisplay === '10g') currentUnit = '10gram';
                 else if (unitDisplay === 'Tola') currentUnit = 'tola';
                 else if (unitDisplay === 'Vori') currentUnit = 'vori';
                 else if (unitDisplay === 'gram') currentUnit = 'gram';
                 // Add other unit conversions
            }

            openRateModal(materialType, purity, currentUnit, currentRate);
        });
    });
}

function openRateModal(materialType, unit, currentRate) {
    if (typeof window.canEditRates !== 'undefined' && !window.canEditRates) {
        showToast('You do not have permission to manage rates.', 'error');
        return;
    }
    const metal = METALS[materialType];
    if (!metal) return;

    document.getElementById('customRateModalTitle').textContent = `Set ${metal.label} Rate`;
    document.getElementById('customRateModalMetalNamePlaceholder').textContent = metal.label;
    document.getElementById('customRateInput').value = currentRate || '';
    document.getElementById('customRateUnitSelect').value = unit || 'gram';
    document.getElementById('modalMaterialType').value = materialType;
    document.getElementById('modalPurity').value = metal.purity;

    currentEditingMetalCode = materialType;

    // Show modal
    const modal = document.getElementById('customRateModal');
    const modalContent = document.getElementById('customRateModalContent');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeCustomRateModal() {
    const modal = document.getElementById('customRateModal');
    const modalContent = document.getElementById('customRateModalContent');
    if (!modal || !modalContent) return;
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden', 'opacity-0');
        currentEditingMetalCode = null;
    }, 300);
}

function handleClearManualRate() {
    // Set form fields for clear action and submit
    const metal = METALS[currentEditingMetalCode];
    if (!metal) return;
    document.getElementById('modalMaterialType').value = currentEditingMetalCode;
    document.getElementById('modalPurity').value = metal.purity;
    const form = document.getElementById('customRateModalContent');
    if (form) {
        form.querySelector('input[name="rate_action"]').value = 'clear_rate';
        form.submit();
    }
}

// Toast utility
function showToast(msg, type='success') {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.style.position = 'fixed';
        toast.style.bottom = '30px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.zIndex = '9999';
        toast.style.display = 'none';
        toast.style.minWidth = '200px';
        toast.style.maxWidth = '90vw';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '6px';
        toast.style.fontWeight = 'bold';
        toast.style.textAlign = 'center';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.background = type === 'success' ? '#22c55e' : '#ef4444';
    toast.style.color = '#fff';
    toast.style.display = 'block';
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => { toast.style.display = 'none'; }, 400);
    }, 2000);
}

async function saveCustomRateAJAX() {
    const metal = METALS[currentEditingMetalCode];
    if (!metal) return;

    const rate = parseFloat(document.getElementById('customRateInput').value);
    const unit = document.getElementById('customRateUnitSelect').value;

    if (isNaN(rate) || rate <= 0) {
        showToast('Please enter a valid rate', 'error');
        return;
    }

    try {
        const response = await fetch('home.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                action: 'save',
                material_type: currentEditingMetalCode,
                unit: unit,
                rate: rate
            })
        });
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeCustomRateModal();
            setTimeout(() => location.reload(), 1200); // Or update DOM directly for smoother UX
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Failed to save rate', 'error');
    }
}

async function clearManualRateAJAX() {
    const metal = METALS[currentEditingMetalCode];
    if (!metal) return;

    try {
        const response = await fetch('home.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                action: 'clear',
                material_type: currentEditingMetalCode
            })
        });
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeCustomRateModal();
            setTimeout(() => location.reload(), 1200); // Or update DOM directly for smoother UX
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Failed to clear rate', 'error');
    }
}

function initializeCustomRateModalListeners() {
    const clearManualRateBtn = document.getElementById('clearManualRateBtn');
    const closeCustomRateModalBtn = document.getElementById('closeCustomRateModalBtn');
    const saveCustomRateBtn = document.getElementById('saveCustomRateBtn');
    if (clearManualRateBtn) clearManualRateBtn.onclick = clearManualRateAJAX;
    if (saveCustomRateBtn) saveCustomRateBtn.onclick = saveCustomRateAJAX;
    if (closeCustomRateModalBtn) closeCustomRateModalBtn.addEventListener('click', closeCustomRateModal);
    const customRateModal = document.getElementById('customRateModal');
    if (customRateModal) {
        customRateModal.addEventListener('click', (event) => {
            if (event.target === customRateModal) closeCustomRateModal();
        });
    }
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && customRateModal && !customRateModal.classList.contains('hidden')) {
            closeCustomRateModal();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initializeCustomRateModalListeners();
    // ... existing code ...
});
