<!-- Forgot Password VIEW -->
<link rel="stylesheet" href="public/assets/css/auth.css">
  <div class="app-wrapper" id="loginView">
    <div class="login-card">
      <div class="logo-area" style="justify-content: center; margin-bottom: 2rem; flex-direction: column; gap: 15px;">
        <div style="width: 250px; display: flex; align-items: center; justify-content: center;">
          <img src="public/assets/images/logo.png" alt="Pudheera Fashions Logo" style="width: 100%; height: auto; border-radius: 12px;">
        </div>
      </div>
      <h2 style="text-align: center; color: white; margin-bottom: 1.5rem;">Forgot Password</h2>
      <form id="forgotForm">
        <div class="input-group">
          <label class="input-label">Email Id</label>
          <input type="email" id="email" name="email" class="input-field" placeholder="Enter email id" required>
        </div>
        <!-- messageBox with auth-message class so JS can show success/error messages -->
        <div id="messageBox" class="auth-message" style="display:none; margin-bottom:10px; font-size:0.9rem;"></div>
        <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Send Reset Link</button>
      </form>
      <p style="text-align: center; margin-top: 1.5rem;"><a href="index.php?action=login">Back to Login</a></p>
    </div>
  </div>
<script src="public/assets/js/forgot-password.js"></script>