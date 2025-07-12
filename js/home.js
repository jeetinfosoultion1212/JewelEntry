/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

// Add ripple effect animation to CSS
const styleElement = document.createElement("style")
styleElement.textContent = `
    @keyframes ripple {
        0% { transform: scale(0); opacity: 1; }
        100% { transform: scale(1); opacity: 0; }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .locked-feature {
        position: relative;
        overflow: hidden;
    }
    
    .locked-feature::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.1);
        z-index: 1;
    }
    
    .locked-feature::after {
        content: '🔒';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 2;
        font-size: 1.2rem;
    }
    
    .feature-locked-shake {
        animation: shake 0.5s ease-in-out;
    }
    
    .modal-input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        font-size: 0.875rem;
    }
    
    .modal-input:focus {
        outline: none;
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }
`
document.head.appendChild(styleElement)

const TRIAL_DURATION_DAYS = 7
const TRIAL_START_DATE_KEY = "jewelEntryTrialStartDate"
const USER_SET_RATES_KEY = "jewelEntryUserSetRates"
const TOLA_IN_GRAMS = 11.6638
const VORI_IN_GRAMS = 11.664 // Often same as Tola

const trialUpdateInterval = null
const userSetRates = {} // Loaded from localStorage { XAU: { rate: 7000, unit: 'gram', perGramRate: 7000 }, ... }
let currentEditingMetalCode = null // For the modal: 'XAU', 'XAG', 'XPT'

// DOM Elements for Metal Rates Modal
let customRateModal,
  customRateModalContent,
  customRateModalTitle,
  customRateInput,
  customRateUnitSelect,
  saveCustomRateBtn,
  closeCustomRateModalBtn,
  currentRateSourceIndicator,
  clearManualRateBtnElement,
  customRateModalMetalNamePlaceholder

const METALS = {
  Gold: { purity: "99.99", label: "Gold 99.99" },
  Silver: { purity: "999.9", label: "Silver 999.9" },
  Platinum: { purity: "95", label: "Platinum 95" },
}

// Global variables from PHP
const hasFeatureAccess = window.hasFeatureAccess || false
const subscriptionStatus = window.subscriptionStatus || "none"
const isTrialUser = window.isTrialUser || false
const isPremiumUser = window.isPremiumUser || false
const isExpired = window.isExpired || false
const daysRemaining = window.daysRemaining || 0

// Enhanced notification functions
function showErrorNotification(msg) {
  showToast(msg, "error")
}

function showSuccessNotification(msg) {
  showToast(msg, "success")
}

function showToast(msg, type = "success") {
  let toast = document.getElementById("toast")
  if (!toast) {
    toast = document.createElement("div")
    toast.id = "toast"
    toast.style.cssText = `
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: none;
            min-width: 200px;
            max-width: 90vw;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
            transition: opacity 0.3s ease;
        `
    document.body.appendChild(toast)
  }

  toast.textContent = msg
  toast.style.background = type === "success" ? "#22c55e" : "#ef4444"
  toast.style.color = "#fff"
  toast.style.display = "block"
  toast.style.opacity = "1"

  setTimeout(() => {
    toast.style.opacity = "0"
    setTimeout(() => {
      toast.style.display = "none"
    }, 400)
  }, 3000)
}

// Enhanced feature access control
function checkFeatureAccess(featureName = "this feature") {
  if (!hasFeatureAccess) {
    if (subscriptionStatus === "trial_expired") {
      showToast(`Trial expired! Upgrade to access ${featureName}`, "error")
    } else if (subscriptionStatus === "premium_expired") {
      showToast(`Subscription expired! Renew to access ${featureName}`, "error")
    } else {
      showToast(`Premium feature! Upgrade to access ${featureName}`, "error")
    }
    return false
  }
  return true
}

function handleFeatureClick(element, featureName) {
  if (!hasFeatureAccess) {
    element.classList.add("feature-locked-shake")
    setTimeout(() => {
      element.classList.remove("feature-locked-shake")
    }, 500)

    setTimeout(() => {
      showFeatureLockedModal()
    }, 200)

    return false
  }
  return true
}

// Enhanced modal functions
function showFeatureLockedModal() {
  const modal = document.getElementById("featureLockedModal")
  if (modal) {
    modal.classList.remove("hidden")
    document.body.style.overflow = "hidden"
  }
}

function closeFeatureLockedModal() {
  const modal = document.getElementById("featureLockedModal")
  if (modal) {
    modal.classList.add("hidden")
    document.body.style.overflow = "auto"
  }
}

function showUpgradeModal() {
  const modal = document.getElementById("upgradeModal")
  if (modal) {
    modal.classList.remove("hidden")
    document.body.style.overflow = "hidden"
  }
}

function closeUpgradeModal() {
  const modal = document.getElementById("upgradeModal")
  if (modal) {
    modal.classList.add("hidden")
    document.body.style.overflow = "auto"
  }
}

function openRateModal(materialType, purity, currentRate) {
  if (!checkFeatureAccess("rate management")) {
    return
  }

  if (typeof window.canEditRates !== "undefined" && !window.canEditRates) {
    showToast("You do not have permission to manage rates.", "error")
    return
  }

  const metal = METALS[materialType]
  if (!metal) return

  document.getElementById("customRateModalTitle").textContent = `Set ${metal.label} Rate`
  document.getElementById("customRateModalMetalNamePlaceholder").textContent = metal.label
  document.getElementById("customRateInput").value = currentRate || ""
  document.getElementById("customRateUnitSelect").value = "gram"
  document.getElementById("modalMaterialType").value = materialType
  document.getElementById("modalPurity").value = metal.purity

  currentEditingMetalCode = materialType

  const modal = document.getElementById("customRateModal")
  const modalContent = document.getElementById("customRateModalContent")
  modal.classList.remove("hidden")
  setTimeout(() => {
    modal.classList.remove("opacity-0")
    modalContent.classList.remove("scale-95", "opacity-0")
    modalContent.classList.add("scale-100", "opacity-100")
  }, 10)
}

function closeCustomRateModal() {
  const modal = document.getElementById("customRateModal")
  const modalContent = document.getElementById("customRateModalContent")
  if (!modal || !modalContent) return

  modalContent.classList.remove("scale-100", "opacity-100")
  modalContent.classList.add("scale-95", "opacity-0")
  setTimeout(() => {
    modal.classList.add("hidden", "opacity-0")
    currentEditingMetalCode = null
  }, 300)
}

async function saveCustomRateAJAX() {
  const metal = METALS[currentEditingMetalCode]
  if (!metal) return

  const rate = Number.parseFloat(document.getElementById("customRateInput").value)
  const unit = document.getElementById("customRateUnitSelect").value

  if (isNaN(rate) || rate <= 0) {
    showToast("Please enter a valid rate", "error")
    return
  }

  try {
    const response = await fetch("home.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        action: "save",
        material_type: currentEditingMetalCode,
        unit: unit,
        rate: rate,
      }),
    })

    const data = await response.json()
    if (data.success) {
      showToast(data.message, "success")
      closeCustomRateModal()
      setTimeout(() => location.reload(), 1200)
    } else {
      showToast(data.message, "error")
    }
  } catch (error) {
    showToast("Failed to save rate", "error")
  }
}

async function clearManualRateAJAX() {
  const metal = METALS[currentEditingMetalCode]
  if (!metal) return

  try {
    const response = await fetch("home.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        action: "clear",
        material_type: currentEditingMetalCode,
      }),
    })

    const data = await response.json()
    if (data.success) {
      showToast(data.message, "success")
      closeCustomRateModal()
      setTimeout(() => location.reload(), 1200)
    } else {
      showToast(data.message, "error")
    }
  } catch (error) {
    showToast("Failed to clear rate", "error")
  }
}

// Enhanced navigation functions
function setActiveNavButton(activeButton) {
  const navButtons = document.querySelectorAll(".nav-btn")

  navButtons.forEach((btn) => {
    const iconDiv = btn.querySelector("div")
    const textSpan = btn.querySelector("span")
    const iconI = btn.querySelector("i")

    btn.style.transform = "translateY(0)"
    if (iconDiv) {
      iconDiv.className = "w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center transition-all duration-200"
    }
    if (iconI) {
      iconI.classList.remove("text-white")
      ;["text-blue-500", "text-green-500", "text-purple-500", "text-red-500", "text-amber-500"].forEach((cls) =>
        iconI.classList.remove(cls),
      )
      iconI.classList.add("text-gray-400")
    }
    if (textSpan) {
      textSpan.className = "text-xs text-gray-400 font-medium transition-all duration-200"
    }
  })

  if (!activeButton) return

  const currentIconDiv = activeButton.querySelector("div")
  const currentTextSpan = activeButton.querySelector("span")
  const currentIconI = activeButton.querySelector("i")
  const navId = activeButton.dataset.navId

  let colorName = "blue"
  if (navId === "home") colorName = "blue"
  else if (navId === "search") colorName = "green"
  else if (navId === "add") colorName = "purple"
  else if (navId === "alerts_nav") colorName = "red"
  else if (navId === "profile") colorName = "amber"

  if (currentIconDiv) {
    currentIconDiv.className = `w-8 h-8 bg-gradient-to-br from-${colorName}-500 to-${colorName}-600 rounded-lg flex items-center justify-center shadow-lg transition-all duration-200`
  }
  if (currentIconI) {
    currentIconI.classList.remove("text-gray-400")
    currentIconI.classList.add("text-white")
  }
  if (currentTextSpan) {
    currentTextSpan.className = `text-xs text-${colorName}-600 font-bold transition-all duration-200`
  }
  activeButton.style.transform = "translateY(-5px)"
}

// Enhanced initialization
function initializeApp() {
  // Initialize modal elements
  customRateModal = document.getElementById("customRateModal")
  customRateModalContent = document.getElementById("customRateModalContent")
  customRateModalTitle = document.getElementById("customRateModalTitle")
  customRateModalMetalNamePlaceholder = document.getElementById("customRateModalMetalNamePlaceholder")
  customRateInput = document.getElementById("customRateInput")
  customRateUnitSelect = document.getElementById("customRateUnitSelect")
  saveCustomRateBtn = document.getElementById("saveCustomRateBtn")
  closeCustomRateModalBtn = document.getElementById("closeCustomRateModalBtn")
  currentRateSourceIndicator = document.getElementById("currentRateSourceIndicator")
  clearManualRateBtnElement = document.getElementById("clearManualRateBtn")

  // Initialize event listeners
  initializeEventListeners()

  // Initialize navigation
  initializeNavigation()

  // Initialize favorites
  initializeFavorites()

  // Initialize feature access controls
  initializeFeatureControls()

  // Show subscription alerts if needed
  showSubscriptionAlerts()
}

function initializeEventListeners() {
  // Rate modal listeners
  if (saveCustomRateBtn) saveCustomRateBtn.onclick = saveCustomRateAJAX
  if (clearManualRateBtnElement) clearManualRateBtnElement.onclick = clearManualRateAJAX
  if (closeCustomRateModalBtn) closeCustomRateModalBtn.addEventListener("click", closeCustomRateModal)

  // Modal backdrop listeners
  if (customRateModal) {
    customRateModal.addEventListener("click", (event) => {
      if (event.target === customRateModal) closeCustomRateModal()
    })
  }

  // Keyboard listeners
  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (customRateModal && !customRateModal.classList.contains("hidden")) {
        closeCustomRateModal()
      }
      if (
        document.getElementById("featureLockedModal") &&
        !document.getElementById("featureLockedModal").classList.contains("hidden")
      ) {
        closeFeatureLockedModal()
      }
      if (
        document.getElementById("upgradeModal") &&
        !document.getElementById("upgradeModal").classList.contains("hidden")
      ) {
        closeUpgradeModal()
      }
    }
  })

  // Enhanced menu card interactions
  document.querySelectorAll(".menu-card").forEach((card) => {
    const favoriteButton = card.querySelector(".favorite-btn")

    card.addEventListener("click", function (event) {
      if (favoriteButton && (favoriteButton === event.target || favoriteButton.contains(event.target))) {
        return
      }

      // Check feature access for locked modules
      if (!hasFeatureAccess && card.classList.contains("opacity-50")) {
        handleFeatureClick(card, "this module")
        return
      }

      // Add ripple effect
      const ripple = document.createElement("div")
      ripple.className = "absolute inset-0 bg-white bg-opacity-30 rounded-2xl"
      ripple.style.animation = "ripple 0.6s ease-out"
      this.appendChild(ripple)
      setTimeout(() => ripple.remove(), 600)
    })
  })
}

function initializeNavigation() {
  const navButtons = document.querySelectorAll(".nav-btn")

  navButtons.forEach((btn) => {
    btn.addEventListener("click", function (event) {
      // Check feature access for navigation items
      if (
        !hasFeatureAccess &&
        (this.dataset.navId === "search" || this.dataset.navId === "add" || this.dataset.navId === "alerts_nav")
      ) {
        event.preventDefault()
        handleFeatureClick(this, "navigation")
        return
      }

      setActiveNavButton(this)
    })
  })

  // Set active navigation based on current page
  const currentPath = window.location.pathname.toLowerCase();
  if (currentPath.endsWith('/home') || currentPath.endsWith('/home.php')) {
    const homeButton = document.querySelector('.nav-btn[data-nav-id="home"]');
    if (homeButton) setActiveNavButton(homeButton);
  } else if (currentPath.endsWith('/sale-entry') || currentPath.endsWith('/sale-entry.php')) {
    const salesButton = document.querySelector('.nav-btn[data-nav-id="search"]');
    if (salesButton) setActiveNavButton(salesButton);
  } else if (currentPath.endsWith('/profile') || currentPath.endsWith('/profile.php')) {
    const profileButton = document.querySelector('.nav-btn[data-nav-id="profile"]');
    if (profileButton) setActiveNavButton(profileButton);
  }
}

function initializeFavorites() {
  const favoriteButtons = document.querySelectorAll(".favorite-btn")
  const FAVORITE_PREFIX = "favorite_module_"

  const updateFavoriteStar = (button, isFavorited) => {
    const icon = button.querySelector("i")
    if (icon) {
      if (isFavorited) {
        icon.classList.remove("far")
        icon.classList.add("fas", "text-yellow-500")
        button.classList.add("favorited")
        button.classList.remove("text-gray-400")
        button.setAttribute("aria-pressed", "true")
      } else {
        icon.classList.remove("fas", "text-yellow-500")
        icon.classList.add("far")
        button.classList.remove("favorited")
        button.classList.add("text-gray-400")
        button.setAttribute("aria-pressed", "false")
      }
    }
  }

  favoriteButtons.forEach((btn) => {
    const menuCard = btn.closest(".menu-card")
    if (!menuCard) return
    const moduleId = menuCard.dataset.moduleId
    if (!moduleId) return

    const isFavorited = localStorage.getItem(FAVORITE_PREFIX + moduleId) === "true"
    updateFavoriteStar(btn, isFavorited)

    btn.addEventListener("click", (event) => {
      event.stopPropagation()
      let currentIsFavorited = localStorage.getItem(FAVORITE_PREFIX + moduleId) === "true"
      currentIsFavorited = !currentIsFavorited
      localStorage.setItem(FAVORITE_PREFIX + moduleId, currentIsFavorited.toString())
      updateFavoriteStar(btn, currentIsFavorited)
    })
  })
}

function initializeFeatureControls() {
  // Add feature access controls to stat cards
  document.querySelectorAll(".stat-card").forEach((card) => {
    if (!hasFeatureAccess && card.classList.contains("opacity-50")) {
      card.style.cursor = "not-allowed"
      card.addEventListener("click", (event) => {
        event.preventDefault()
        handleFeatureClick(card, "statistics")
      })
    }
  })

  // Store management menu navigation
  const storeMenuMap = {
    inventory: "add.php",
    sales: "sale-entry.php",
    customers: "customer.php",
    catalog: "catalog.html",
    billing: "billing.php",
    repairs: "repairs.php",
    analytics: "analytics.php",
    staff: "staff.php",
    suppliers: "suppliers.php",
    testing: "testing.php",
    security: "security.php",
    bookings: "bookings.php",
    alerts: "alerts.php",
    settings: "settings.php",
  }

  document.querySelectorAll(".menu-card").forEach((card) => {
    const moduleId = card.getAttribute("data-module-id")
    if (storeMenuMap[moduleId] && card.tagName !== "A") {
      card.addEventListener("click", (event) => {
        if (event.target.closest(".favorite-btn")) return

        if (hasFeatureAccess) {
          window.location.href = storeMenuMap[moduleId]
        }
      })
    }
  })
}

function showSubscriptionAlerts() {
  // Auto-show upgrade modal for expired users after a delay
  if (subscriptionStatus === "trial_expired" || subscriptionStatus === "premium_expired") {
    setTimeout(() => {
      showUpgradeModal()
    }, 3000)
  }

  // Show trial warning for users with less than 2 days remaining
  if (isTrialUser && !isExpired && daysRemaining <= 2) {
    setTimeout(() => {
      showToast(
        `Trial expires in ${daysRemaining} day${daysRemaining !== 1 ? "s" : ""}! Upgrade now to continue.`,
        "error",
      )
    }, 1000)
  }
}

// Global functions (accessible from HTML)
window.showFeatureLockedModal = showFeatureLockedModal
window.closeFeatureLockedModal = closeFeatureLockedModal
window.showUpgradeModal = showUpgradeModal
window.closeUpgradeModal = closeUpgradeModal
window.openRateModal = openRateModal
window.togglePlans = () => {
  const plansSection = document.getElementById("pricingPlansSection")
  if (plansSection) {
    plansSection.classList.toggle("hidden")
    if (!plansSection.classList.contains("hidden")) {
      plansSection.style.opacity = "0"
      plansSection.style.transform = "translateY(-10px)"
      setTimeout(() => {
        plansSection.style.opacity = "1"
        plansSection.style.transform = "translateY(0)"
      }, 10)
    }
  }
}

window.selectPlan = (planId) => {
  window.location.href = `subscription.php?plan=${planId}`
}

// Enhanced DOM ready and load events
document.addEventListener("DOMContentLoaded", initializeApp)

window.addEventListener("load", () => {
  // Animate stat cards on load
  const statCards = document.querySelectorAll(".stat-card")
  statCards.forEach((card, index) => {
    if (!card.dataset.metalCode) {
      card.style.opacity = "0"
      card.style.transform = "translateY(20px)"

      setTimeout(
        () => {
          card.style.transition = "opacity 0.5s ease-out, transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)"
          card.style.opacity = "1"
          card.style.transform = "translateY(0)"
        },
        50 + index * 70,
      )
    }
  })

  // Initialize marquee animation
  const marquee = document.getElementById("liveRatesMarquee")
  if (marquee && !marquee.classList.contains("pulse-animation")) {
    marquee.classList.add("pulse-animation")
  }
})

// Enhanced error handling
window.addEventListener("error", (event) => {
  console.error("JavaScript Error:", event.error)
  showToast("An error occurred. Please refresh the page.", "error")
})

// Enhanced unload handling
window.addEventListener("beforeunload", () => {
  document.body.style.overflow = "auto"
})
