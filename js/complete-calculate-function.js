    function calculatePurchaseAmount() {
        const weight = parseFloat(stockWeight.value) || 0;
        const marketRate = parseFloat(stockRate.value) || 0;
        const actualPurityPercent = getCurrentPurityValue();
        const itemBuyingRatePerGram = parseFloat(buyingPurity.value) || 0; // This is a rate, not purity
        const paidAmountValue = parseFloat(paidAmount.value) || 0;
        const isPurchaseChecked = isPurchase.checked;
        const currentMetalType = stockMetalType.value;

        let calculatedTotalValue = 0;
        let materialCost = 0;

        if (weight > 0) {
            if (isPurchaseChecked) {
                if (itemBuyingRatePerGram > 0) {
                    materialCost = weight * itemBuyingRatePerGram;
                    // Round to nearest whole number
                    materialCost = Math.round(materialCost);
                }
            } else { // Not a purchase, calculate based on market rate and purity
                if (marketRate > 0 && actualPurityPercent > 0) {
                    let finePurityStandard = 0;
                    if (currentMetalType === 'Gold') {
                        finePurityStandard = 99.99;
                    } else if (currentMetalType === 'Silver') {
                        finePurityStandard = 999.9; // Standard for silver, can be 999
                    } else if (currentMetalType === 'Platinum') {
                        finePurityStandard = 99.95; // Common for platinum, adjust as needed
                    }
                    if (finePurityStandard > 0) {
                        materialCost = weight * marketRate * (actualPurityPercent / finePurityStandard);
                        // Round to nearest whole number for consistency
                        materialCost = Math.round(materialCost);
                    }
                }
            }
        }
        calculatedTotalValue = materialCost;

        document.getElementById('stockMaterialCost').textContent = '₹' + materialCost.toFixed(2);
        document.getElementById('stockTotalPrice').textContent = '₹' + calculatedTotalValue.toFixed(2);

        const balanceContainer = document.getElementById('balanceContainer');
        const balanceAmount = document.getElementById('balanceAmount');

        if (isPurchaseChecked) {
            purchaseFields.style.display = 'block';
            let status = 'Due';

            // Define a small tolerance for floating point comparison (0.01 rupees)
            const EPSILON = 0.01;
            const balance = calculatedTotalValue - paidAmountValue;
            const isEffectivelyZero = Math.abs(balance) < EPSILON;

            if (calculatedTotalValue <= 0) {
                status = (paidAmountValue > 0) ? 'Overpaid' : 'Due';
            } else {
                if (paidAmountValue <= 0) {
                    status = 'Due';
                } else if (paidAmountValue >= calculatedTotalValue || isEffectivelyZero) {
                    // Consider balance effectively zero if within tolerance
                    status = 'Paid';
                } else {
                    status = 'Partial';
                }
            }
            paymentStatus.value = status;

            balanceContainer.style.display = 'block';
            
            // Display zero if balance is effectively zero
            if (isEffectivelyZero) {
                balanceAmount.textContent = '₹0.00';
            } else {
                balanceAmount.textContent = '₹' + balance.toFixed(2);
                if (balance < 0) {
                    balanceAmount.textContent += ' (Credit)';
                }
            }
        } else {
            purchaseFields.style.display = 'none';
            paymentStatus.value = '';
            balanceContainer.style.display = 'none';
        }
    }
