<!DOCTYPE html>
<html lang="en" data-theme-mode="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pudheera Retail Tracking System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Libre+Caslon+Text:ital,wght@0,400;0,700;1,400&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="public/assets/css/style.css?v=<?= time(); ?>">
  <script>
    function setTheme(mode) {
      document.documentElement.setAttribute('data-theme-mode', mode);
      localStorage.setItem('theme', mode);
      document.querySelectorAll('.theme-btn').forEach(btn => btn.classList.remove('active'));
      const activeBtn = document.querySelector(`.theme-btn[onclick="setTheme('${mode}')"]`);
      if (activeBtn) activeBtn.classList.add('active');
    }
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
      document.documentElement.setAttribute('data-theme-mode', savedTheme);
    }
    window.addEventListener('DOMContentLoaded', () => {
      const mode = localStorage.getItem('theme') || 'dark';
      document.querySelectorAll('.theme-btn').forEach(btn => btn.classList.remove('active'));
      const activeBtn = document.querySelector(`.theme-btn[onclick="setTheme('${mode}')"]`);
      if (activeBtn) activeBtn.classList.add('active');
    });
  </script>
</head>

<body>

  <!-- THEME SWITCHER -->
  <div class="theme-panel">
    <button class="theme-btn active" onclick="setTheme('dark')" title="Dark Mode">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
      </svg>
    </button>
    <button class="theme-btn" onclick="setTheme('light')" title="Light Mode">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="5"></circle>
        <line x1="12" y1="1" x2="12" y2="3"></line>
        <line x1="12" y1="21" x2="12" y2="23"></line>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
        <line x1="1" y1="12" x2="3" y2="12"></line>
        <line x1="21" y1="12" x2="23" y2="12"></line>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
      </svg>
    </button>
  </div>
