/* forgot-password.js — Handles forgot password form submission */

(function () {
    'use strict';

    const form       = document.getElementById('forgotForm');
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
        submitBtn.innerText = loading ? 'Sending...' : 'Send Reset Link';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessage();

        const email = document.getElementById('email').value.trim();

        if (!email) {
            showMessage('error', 'Email is required.');
            return;
        }
        if (!email.includes('@') || !email.includes('.')) {
            showMessage('error', 'Please enter a valid email address.');
            return;
        }

        setLoading(true);

        try {
            const response = await fetch('/api/forgot-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showMessage('success', data.message || 'If that email exists, a reset link has been sent.');
                form.reset();
            } else {
                showMessage('error', data.error || 'Something went wrong. Please try again.');
            }
        } catch (err) {
            console.error('Forgot password error:', err);
            showMessage('error', 'Network error. Please check your connection and try again.');
        } finally {
            setLoading(false);
        }
    });
})();
