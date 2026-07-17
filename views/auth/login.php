<!-- LOGIN VIEW -->
<link rel="stylesheet" href="public/assets/css/auth.css">
  <div class="app-wrapper" id="loginView">
    <div class="login-card">
      <div class="logo-area" style="justify-content: center; margin-bottom: 2rem; flex-direction: column; gap: 15px;">
        <div style="width: 250px; display: flex; align-items: center; justify-content: center;">
          <img src="public/assets/images/logo.png" alt="Pudheera Fashions Logo" style="width: 100%; height: auto; border-radius: 12px;">
        </div>
      </div>
      <!-- id="loginForm" added so JS can attach the submit listener -->
      <form id="loginForm">
        <div class="input-group">
          <label class="input-label">Username</label>
          <input type="text" id="username" name="username" class="input-field" placeholder="Enter username" required>
        </div>
        <div class="input-group">
          <label class="input-label">Password</label>
          <input type="password" id="password" name="password" class="input-field" placeholder="Enter password" required>
        </div>
        <!-- messageBox with auth-message class so JS can show success/error messages -->
        <div id="messageBox" class="auth-message" style="display:none; margin-bottom:10px; font-size:0.9rem;"></div>
        <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Login to System</button>
        <div class="RegisterForgot">
          <p>New User?<a href="/register">Register here</a></p>
          <p><a href="/forgot-password">Forgot Password?</a></p>
        </div>
      </form>
    </div>
  </div>
<script src="public/assets/js/login.js?v=<?= time(); ?>"></script>