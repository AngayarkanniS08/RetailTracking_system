<!-- Register VIEW -->
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
          <input type="text" name="username" class="input-field" placeholder="Enter username" required>
        </div>
        <div class="input-group">
          <label class="input-label">Email Id</label>
          <input type="email" name="email" class="input-field" placeholder="Enter email id" required>
        </div>
        <div class="input-group">
          <label class="input-label">Password</label>
          <input type="password" name="password" class="input-field" placeholder="Enter password" required>
        </div>
        <div class="input-group">
          <label class="input-label">Repeat Password</label>
          <input type="password" name="password_repeat" class="input-field" placeholder="Password" required>
        </div>
        <!-- id="registerError" added so JS can show error messages -->
        <div id="registerError" style="display:none; color:#e74c3c; margin-bottom:10px; font-size:0.9rem;"></div>
        <button type="submit" class="btn btn-primary btn-block">SignUp</button>
      </form>
      <p>Already have an account? <a href="index.php?action=login">Login here</a></p>
      <p class="text-center mt-1" style="color: var(--muted); font-size: 0.8rem;"></p>
    </div>
  </div>
<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.querySelector('[name="username"]').value;
    const email = document.querySelector('[name="email"]').value;
    const password = document.querySelector('[name="password"]').value;
    const passwordRepeat = document.querySelector('[name="password_repeat"]').value;
    const errorDiv = document.getElementById('registerError');
    errorDiv.style.display = 'none';

    if (password !== passwordRepeat) {
        errorDiv.style.display = 'block';
        errorDiv.innerText = 'Passwords do not match.';
        return;
    }

    try {
        const apiBase = `${window.location.protocol}//${window.location.hostname}:8081`;
        const response = await fetch(apiBase + '/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password })
        });
        const data = await response.json();
        if (data.success) {
            alert('Account created successfully! Redirecting to login...');
            window.location.href = 'index.php?action=login';
        } else {
            errorDiv.style.display = 'block';
            errorDiv.innerText = data.error || 'Registration failed';
        }
    } catch (err) {
        errorDiv.style.display = 'block';
        errorDiv.innerText = 'Network error. Please try again.';
        console.error(err);
    }
});
</script>