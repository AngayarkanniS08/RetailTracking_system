<!-- Reset Password View -->

<div class="forgot-page">

    <header class="auth-header">
        <div class="logo-area">
            <img src="/Core/Asset/images/default-logo.png" alt="Logo" class="logo">

            <span class="system-name">
                <?= htmlspecialchars($systemName ?? 'Inventory & Billing System') ?>
            </span>
        </div>

        <a href="index.php?action=login" class="back-link">
            ← Back to Login
        </a>
    </header>

    <div class="forgot-container">

        <h2>Create New Password</h2>

        <form id="resetForm">

            <!-- Token is read from the URL query string via JS -->
            <input type="hidden" id="resetToken" name="token">

            <div class="input-group">
                <label class="input-label">Email Id</label>

                <input
                    type="email"
                    id="resetEmail"
                    name="email"
                    class="input-field"
                    placeholder="Enter Email Id"
                    required
                >
            </div>

            <div class="input-group">
                <label class="input-label">New Password</label>

                <input
                    type="password"
                    id="resetPassword"
                    name="password"
                    class="input-field"
                    placeholder="Enter new password"
                    required
                >
            </div>

            <div class="input-group">
                <label class="input-label">Confirm Password</label>

                <input
                    type="password"
                    id="resetPasswordConfirm"
                    name="password_confirmation"
                    class="input-field"
                    placeholder="Confirm new password"
                    required
                >
            </div>

            <button type="submit" class="btn-primary">
                Create Password
            </button>

            <div id="resetMessage" style="margin-top:15px; text-align:center; font-size:14px;"></div>

        </form>

    </div>

    <footer class="auth-footer">
        <p><?= date('Y') ?> &copy; VortexFleetz. All rights reserved.</p>

        <p>
            <a href="mailto:support@example.com">
                support@example.com
            </a>
        </p>
    </footer>

</div>

<script>
    // Extract the token from the current page URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token') || '';
    document.getElementById('resetToken').value = token;

    document.getElementById('resetForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const msgEl = document.getElementById('resetMessage');
        msgEl.style.color = '#f87171';
        msgEl.innerText = '';

        const token    = document.getElementById('resetToken').value;
        const email    = document.getElementById('resetEmail').value.trim();
        const password = document.getElementById('resetPassword').value;
        const passwordConfirmation = document.getElementById('resetPasswordConfirm').value;

        if (!token) {
            msgEl.innerText = 'Invalid or missing reset token. Please request a new password reset link.';
            return;
        }

        if (password !== passwordConfirmation) {
            msgEl.innerText = 'Passwords do not match.';
            return;
        }

        try {
            const apiBase = `${window.location.protocol}//${window.location.hostname}:8081`;
            const res = await fetch(apiBase + '/api/reset-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token, email, password, password_confirmation: passwordConfirmation })
            });

            const data = await res.json();

            if (res.ok && data.success) {
                msgEl.style.color = '#4ade80';
                msgEl.innerText = data.message || 'Password reset successfully!';

                // Redirect to login after a short delay
                setTimeout(() => {
                    window.location.href = 'index.php?action=login';
                }, 2000);
            } else {
                msgEl.innerText = data.error || 'Something went wrong. Please try again.';
            }
        } catch (err) {
            msgEl.innerText = 'Network error. Please try again.';
        }
    });
</script>