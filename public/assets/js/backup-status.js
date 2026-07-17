(function(){
  var el = document.getElementById('backup-status-overlay');
  var msgEl = document.getElementById('backup-status-msg');
  var hideTimer = null;
  var minShow = 10000;

  function show(msg) {
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
    msgEl.textContent = msg;
    el.style.display = 'block';
  }

  function hide() {
    if (hideTimer) return;
    hideTimer = setTimeout(function() {
      el.style.display = 'none';
      hideTimer = null;
    }, minShow);
  }

  function poll() {
    window.apiRequest('/api/backup/status').then(function(data) {
      if (data.running) {
        show(data.progress || 'Running\u2026');
      } else {
        hide();
      }
    });
  }

  poll();
  setInterval(poll, 5000);
})();
