<!-- Reset Password VIEW -->
<link rel="stylesheet" href="public/assets/css/auth.css">
  <div class="app-wrapper" id="loginView">
    <div class="login-card">
      <div class="logo-area" style="justify-content: center; margin-bottom: 2rem; flex-direction: column; gap: 15px;">
        <div style="width: 250px; display: flex; align-items: center; justify-content: center;">
          <img src="public/assets/images/logo.png" alt="Pudheera Fashions Logo" style="width: 100%; height: auto; border-radius: 12px;">
        </div>
      </div>
      <h2 style="text-align: center; color: white; margin-bottom: 1.5rem;">Create New Password</h2>

      <!-- Notice shown if token is missing in the URL query string -->
      <div id="tokenMissingNotice" class="auth-message error" style="display:none; margin-bottom:15px;">
        Invalid or missing reset token. Please request a new password reset link.
      </div>

      <form id="resetForm">
        <!-- Token is read from the URL query string via JS -->
        <input type="hidden" id="resetToken" name="token">

        <div class="input-group">
          <label class="input-label">Email Id</label>
          <input type="email" id="resetEmail" name="email" class="input-field" placeholder="Enter Email Id" required>
        </div>

        <div class="input-group">
          <label class="input-label">New Password</label>
          <input type="password" id="resetPassword" name="password" class="input-field" placeholder="Enter new password" required>
        </div>

        <div class="input-group">
          <label class="input-label">Confirm Password</label>
          <input type="password" id="resetPasswordConfirm" name="password_repeat" class="input-field" placeholder="Confirm new password" required>
        </div>

        <!-- messageBox with auth-message class so JS can show success/error messages -->
        <div id="messageBox" class="auth-message" style="display:none; margin-bottom:10px; font-size:0.9rem;"></div>
        <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Create Password</button>
      </form>
      <p style="text-align: center; margin-top: 1.5rem;"><a href="index.php?action=login">Back to Login</a></p>
    </div>
  </div>
<script src="public/assets/js/reset-password.js?v=<?= time(); ?>"></script>