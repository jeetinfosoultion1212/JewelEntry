
/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

const USERS_KEY = 'jewelEntryUsers';

document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const registerButton = document.getElementById('registerButton');
    const errorMessageContainer = document.getElementById('errorMessageContainer');

    if (registerForm) {
        registerForm.addEventListener('submit', (event) => {
            event.preventDefault();
            errorMessageContainer.innerHTML = ''; // Clear previous errors
            if (registerButton) registerButton.disabled = true;

            const fullName = registerForm.fullName.value.trim();
            const email = registerForm.email.value.trim();
            const password = registerForm.password.value;
            const confirmPassword = registerForm.confirmPassword.value;

            if (!fullName || !email || !password || !confirmPassword) {
                displayError("All fields are required.");
                if (registerButton) registerButton.disabled = false;
                return;
            }

            if (password.length < 6) {
                displayError("Password must be at least 6 characters long.");
                if (registerButton) registerButton.disabled = false;
                return;
            }

            if (password !== confirmPassword) {
                displayError("Passwords do not match.");
                if (registerButton) registerButton.disabled = false;
                return;
            }

            const users = JSON.parse(localStorage.getItem(USERS_KEY) || '[]');
            const existingUser = users.find(user => user.email === email);

            // Simulate backend delay
            setTimeout(() => {
                if (existingUser) {
                    displayError("An account with this email already exists.");
                    if (registerButton) registerButton.disabled = false;
                } else {
                    users.push({ name: fullName, email: email, password: password }); // In real app, hash password
                    localStorage.setItem(USERS_KEY, JSON.stringify(users));
                    alert('Registration successful! Please login.');
                    window.location.href = 'login.html';
                }
            }, 500);
        });
    }
    function displayError(message) {
        errorMessageContainer.innerHTML = `<div class="error-message">${message}</div>`;
    }
});
