<!-- LOGIN VIEW -->
<div class="auth-wrapper" id="loginView">
  <div class="auth-card">
    <div class="logo-area">
      <img src="public/assets/images/logo.png" alt="Pudheera Fashions Logo">
      <div class="auth-title">Welcome back</div>
      <div class="auth-subtitle">Sign in to your retail tracking system</div>
    </div>
    <form id="loginForm">
      <div class="input-group">
        <label class="input-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="input-field" placeholder="Enter username" autocomplete="username" required>
      </div>
      <div class="input-group">
        <label class="input-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="input-field" placeholder="Enter password" autocomplete="current-password" required>
      </div>
      <div id="messageBox" class="auth-message"></div>
      <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Login to System</button>
      <div class="RegisterForgot">
        <p>New User? <a href="/register">Register here</a></p>
        <p><a href="/forgot-password">Forgot Password?</a></p>
      </div>
    </form>
  </div>
</div>