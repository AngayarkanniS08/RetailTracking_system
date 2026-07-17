/* reset-password.js — Reads token from URL and handles password reset submission */

(function () {
    'use strict';

    const form               = document.getElementById('resetForm');
    const submitBtn          = document.getElementById('submitBtn');
    const messageBox         = document.getElementById('messageBox');
    const tokenMissingNotice = document.getElementById('tokenMissingNotice');

    // Extract reset token from URL query: /reset-password.html?token=abc123
    const urlParams = new URLSearchParams(window.location.search);
    const token     = urlParams.get('token') || '';

    if (!token) {
        tokenMissingNotice.style.display = 'block';
        form.style.display = 'none';
    } else {
        document.getElementById('resetToken').value = token;
    }

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
        submitBtn.innerText = loading ? 'Resetting...' : 'Reset Password';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessage();

        const token                = document.getElementById('resetToken').value;
        const email                = document.getElementById('resetEmail').value.trim();
        const password             = document.getElementById('resetPassword').value;
        const passwordConfirmation = document.getElementById('resetPasswordConfirm').value;

        if (!email || !password || !passwordConfirmation) {
            showMessage('error', 'All fields are required.');
            return;
        }
        if (password.length < 6) {
            showMessage('error', 'Password must be at least 6 characters.');
            return;
        }
        if (password !== passwordConfirmation) {
            showMessage('error', 'Passwords do not match.');
            return;
        }

        setLoading(true);

        try {
            const apiBase = `${window.location.protocol}//${window.location.hostname}:8081`;
            const response = await fetch(apiBase + '/api/reset-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token, email, password, password_confirmation: passwordConfirmation })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showMessage('success', data.message || 'Password reset successfully!');
                form.reset();
                setTimeout(() => {
                    window.location.href = '/login';
                }, 2000);
            } else {
                showMessage('error', data.error || 'Something went wrong. Please try again.');
            }
        } catch (err) {
            console.error('Reset password error:', err);
            showMessage('error', 'Network error. Please check your connection and try again.');
        } finally {
            setLoading(false);
        }
    });
})();
