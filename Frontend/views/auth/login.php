<!-- LOGIN VIEW -->
  <div class="app-wrapper" id="loginView">
    <div class="login-card">
      <div class="logo-area" style="justify-content: center; margin-bottom: 2rem; flex-direction: column; gap: 15px;">
        <div style="width: 250px; display: flex; align-items: center; justify-content: center;">
          <img src="assets/images/logo.png" alt="Pudheera Fashions Logo" style="width: 100%; height: auto; border-radius: 12px;">
        </div>
      </div>
      <!-- id="loginForm" added so JS can attach the submit listener -->
      <form id="loginForm">
        <div class="input-group">
          <label class="input-label"> Username</label>
          <input type="text" name="username" class="input-field" placeholder="Enter username" required>
        </div>
        <div class="input-group">
          <label class="input-label">Password</label>
          <input type="password" name="password" class="input-field" placeholder="Enter password" required>
        </div>
        <!-- id="loginError" added so JS can show error messages -->
        <div id="loginError" style="display:none; color:#e74c3c; margin-bottom:10px; font-size:0.9rem;"></div>
        <button type="submit" class="btn btn-primary btn-block">Login to System</button>
        <div class="RegisterForgot">
          <p>New User?<a href="index.php?action=register">Register here</a></p>
          <p><a href="index.php?action=ForgotPassword">Forgot Password?</a></p>
        </div>
      </form>
    </div>
  </div>
 <script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.querySelector('[name="username"]').value;
    const password = document.querySelector('[name="password"]').value;
    const errorDiv = document.getElementById('loginError');
    errorDiv.style.display = 'none';
    try {
          const response = await fetch('/api/login', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ username, password })
          });
          const data = await response.json();
          if (data.success) {
                    // Store JWT token in localStorage
                    localStorage.setItem('auth_token', data.token);
                    // Redirect to dashboard
                    window.location.href = '/index.php';
                } else {
                    errorDiv.style.display = 'block';
                    errorDiv.innerText = data.error || 'Invalid credentials';
                }
            } catch (err) {
                errorDiv.style.display = 'block';
                errorDiv.innerText = 'Network error. Please try again.';
                console.error(err);
            }
        });
</script>