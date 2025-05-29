
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

document.addEventListener('DOMContentLoaded', () => {
    // Enhanced menu card interactions
    document.querySelectorAll('.menu-card').forEach(cardElement => {
        const card = cardElement as HTMLElement;
        card.addEventListener('click', function() {
            // Add ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'absolute inset-0 bg-white bg-opacity-30 rounded-2xl';
            ripple.style.animation = 'ripple 0.6s ease-out';
            this.style.position = 'relative';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
            
            // Scale animation
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            }, 100);
        });
    });

    // Enhanced navigation interactions
    document.querySelectorAll('.nav-btn').forEach(btnElement => {
        const btn = btnElement as HTMLElement;
        btn.addEventListener('click', function(this: HTMLElement) {
            // Remove active state from all buttons
            document.querySelectorAll('.nav-btn').forEach(bElement => {
                const b = bElement as HTMLElement;
                const iconDiv = b.querySelector('div') as HTMLElement;
                const textSpan = b.querySelector('span') as HTMLElement;
                const iconI = b.querySelector('i') as HTMLElement;
                
                if (iconDiv) {
                    iconDiv.className = 'w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center';
                }
                if (iconI) {
                    iconI.classList.remove('text-white');
                    iconI.classList.add('text-gray-400');
                }
                if (textSpan) {
                    textSpan.className = 'text-xs text-gray-400 font-medium';
                }
            });
            
            // Add active state to clicked button
            const currentIconDiv = this.querySelector('div') as HTMLElement;
            const currentTextSpan = this.querySelector('span') as HTMLElement;
            const currentIconI = this.querySelector('i') as HTMLElement;
            
            let colorName = 'blue'; // Default color name
            if (currentIconI) {
                if (currentIconI.classList.contains('fa-home')) colorName = 'blue';
                else if (currentIconI.classList.contains('fa-search')) colorName = 'green';
                else if (currentIconI.classList.contains('fa-plus-circle')) colorName = 'purple';
                else if (currentIconI.classList.contains('fa-bell')) colorName = 'red';
                else if (currentIconI.classList.contains('fa-user-circle')) colorName = 'amber';
            }
            
            if (currentIconDiv) {
                currentIconDiv.className = `w-8 h-8 bg-gradient-to-br from-${colorName}-500 to-${colorName}-600 rounded-lg flex items-center justify-center shadow-lg`;
            }
            if (currentIconI) {
                currentIconI.classList.remove('text-gray-400');
                currentIconI.classList.add('text-white');
            }
            if (currentTextSpan) {
                currentTextSpan.className = `text-xs text-${colorName}-600 font-bold`;
            }
            
            // Add bounce animation
            this.style.transform = 'translateY(-5px)';
            setTimeout(() => {
                this.style.transform = 'translateY(0)';
            }, 200);
        });
    });

    // Live updates simulation
    const salesElement = document.getElementById('today-sales-value') as HTMLElement | null;
    if (salesElement) {
        setInterval(() => {
            const currentValueText = salesElement.textContent || "₹0L"; // Default to "₹0L" if textContent is null
            const currentValue = parseFloat(currentValueText.replace('₹', '').replace('L', ''));
            const newValue = (currentValue + Math.random() * 0.1).toFixed(1);
            salesElement.textContent = `₹${newValue}L`;
        }, 5000);
    }
});

// Stat cards animation on load
window.addEventListener('load', function() {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((cardElement, index) => {
        const card = cardElement as HTMLElement;
        setTimeout(() => {
            card.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                card.style.transform = 'translateY(0)';
            }, 300);
        }, index * 100);
    });
});
