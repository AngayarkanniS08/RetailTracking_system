<!-- Register VIEW -->
<link rel="stylesheet" href="public/assets/css/auth.css">
  <div class="app-wrapper" id="loginView">
    <div class="login-card">
      <div class="logo-area" style="justify-content: center; margin-bottom: 2rem; flex-direction: column; gap: 15px;">
        <div style="width: 250px; display: flex; align-items: center; justify-content: center;">
          <img src="public/assets/images/logo.png" alt="Pudheera Fashions Logo" style="width: 100%; height: auto; border-radius: 12px;">
        </div>
      </div>
      <form id="registerForm">
        <div class="input-group">
          <label class="input-label">Username</label>
          <input type="text" id="username" name="username" class="input-field" placeholder="Enter username" required>
        </div>
        <div class="input-group">
          <label class="input-label">Email Id</label>
          <input type="email" id="email" name="email" class="input-field" placeholder="Enter email id" required>
        </div>
        <div class="input-group">
          <label class="input-label">Password</label>
          <input type="password" id="password" name="password" class="input-field" placeholder="Enter password" required>
        </div>
        <div class="input-group">
          <label class="input-label">Repeat Password</label>
          <input type="password" id="confirmPassword" name="password_repeat" class="input-field" placeholder="Password" required>
        </div>
        <!-- id="messageBox" with auth-message class so JS can show success/error messages -->
        <div id="messageBox" class="auth-message" style="display:none; margin-bottom:10px; font-size:0.9rem;"></div>
        <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Sign Up</button>
      </form>
      <p>Already have an account? <a href="index.php?action=login">Login here</a></p>
      <p class="text-center mt-1" style="color: var(--muted); font-size: 0.8rem;"></p>
    </div>
  </div>
<script src="public/assets/js/register.js?v=<?= time(); ?>"></script>