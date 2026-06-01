/* login.js — Handles the login form submission and token storage */

(function () {
    'use strict';

    const form       = document.getElementById('loginForm');
    const submitBtn  = document.getElementById('submitBtn');
    const messageBox = document.getElementById('messageBox');

    // If already logged in, skip straight to dashboard
    if (localStorage.getItem('auth_token')) {
        window.location.href = '/index.php';
    }

    // Show success banner if redirected from registration
    const params = new URLSearchParams(window.location.search);
    if (params.get('registered') === '1') {
        showMessage('success', 'Account created successfully! You can now log in.');
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
        submitBtn.innerText = loading ? 'Logging in...' : 'Login';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessage();

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        if (!username || !password) {
            showMessage('error', 'Username and password are required.');
            return;
        }

        setLoading(true);

        try {
            const apiBase = `${window.location.protocol}//${window.location.hostname}:8081`;
            const response = await fetch(apiBase + '/api/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Sync session with the frontend container locally
                await fetch('/index.php?action=set_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: data.user.id, username: data.user.username })
                });
                localStorage.setItem('auth_token', data.token);
                if (data.user) {
                    localStorage.setItem('auth_user', JSON.stringify(data.user));
                }
                showMessage('success', 'Login successful! Redirecting...');
                setTimeout(() => {
                    window.location.href = '/index.php';
                }, 1000);
            } else {
                showMessage('error', data.error || 'Invalid credentials. Please try again.');
            }
        } catch (err) {
            console.error('Login error:', err);
            showMessage('error', 'Network error. Please check your connection and try again.');
        } finally {
            setLoading(false);
        }
    });
})();
