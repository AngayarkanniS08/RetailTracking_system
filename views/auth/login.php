<!-- LOGIN VIEW -->
  <div class="app-wrapper" id="loginView">
    <div class="login-card">
      <div class="logo-area" style="justify-content: center; margin-bottom: 2rem; flex-direction: column; gap: 15px;">
        <div style="width: 250px; display: flex; align-items: center; justify-content: center;">
          <img src="assets/images/logo.png" alt="Pudheera Fashions Logo" style="width: 100%; height: auto; border-radius: 12px;">
        </div>
      </div>
      <form action="index.php?action=login" method="POST">
        <div class="input-group">
          <label class="input-label"> Username</label>
          <input type="text" name="username" class="input-field" placeholder="Enter username" required>
        </div>
        <div class="input-group">
          <label class="input-label">Password</label>
          <input type="password" name="password" class="input-field" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Login to System</button>
        <div class = "RegisterForgot">
        <p>New User?<a href="index.php?action=register">Register here</a></p>
        <p><a href="index.php?action=forgot_password">Forgot Password?</a></p>
        </div>
      </form>
    </div>
  </div>