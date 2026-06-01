/* register.js — Handles the registration form submission */

(function () {
    'use strict';

    const form       = document.getElementById('registerForm');
    const submitBtn  = document.getElementById('submitBtn');
    const messageBox = document.getElementById('messageBox');

    function showMessage(type, text) {
        messageBox.className = 'auth-message ' + type;
        messageBox.innerText = text;
        messageBox.style.display = 'block';
    }

    function hideMessage() {
        messageBox.style.display = 'none';
        messageBox.innerText = '';
    }

    function setLoading(loading) {
        submitBtn.disabled = loading;
        submitBtn.innerText = loading ? 'Creating account...' : 'Sign Up';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessage();

        const username = document.getElementById('username').value.trim();
        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirm  = document.getElementById('confirmPassword').value;

        // Client-side validation
        if (!username || !email || !password || !confirm) {
            showMessage('error', 'All fields are required.');
            return;
        }
        if (password.length < 6) {
            showMessage('error', 'Password must be at least 6 characters.');
            return;
        }
        if (password !== confirm) {
            showMessage('error', 'Passwords do not match.');
            return;
        }
        if (!email.includes('@') || !email.includes('.')) {
            showMessage('error', 'Please enter a valid email address.');
            return;
        }

        setLoading(true);

        try {
            const apiBase = `${window.location.protocol}//${window.location.hostname}:8081`;
            const response = await fetch(apiBase + '/api/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, email, password })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Optionally store token for auto-login
                if (data.token) {
                    localStorage.setItem('auth_token', data.token);
                }
                showMessage('success', 'Account created! Redirecting to login...');
                setTimeout(() => {
                    window.location.href = '/index.php?action=login&registered=1';
                }, 2000);
            } else {
                showMessage('error', data.error || 'Registration failed. Please try again.');
            }
        } catch (err) {
            console.error('Registration error:', err);
            showMessage('error', 'Network error. Please check your connection and try again.');
        } finally {
            setLoading(false);
        }
    });
})();
