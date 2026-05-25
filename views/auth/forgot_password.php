<!-- Forgot Password View -->

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

        <form action="index.php?action=login" method="POST">

            <div class="input-group">
                <label class="input-label">Email Id</label>

                <input
                    type="email"
                    name="username"
                    class="input-field"
                    placeholder="Enter Email Id"
                    required
                >
            </div>

            <div class="input-group">
                <label class="input-label">New Password</label>

                <input
                    type="password"
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
                    name="confirm_password"
                    class="input-field"
                    placeholder="Confirm new password"
                    required
                >
            </div>

            <button type="submit" class="btn-primary">
                Create Password
            </button>

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