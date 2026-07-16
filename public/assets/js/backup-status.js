(function(){
  var banner = null;

  function ensureBanner() {
    if (banner) return;
    banner = document.createElement('div');
    banner.id = 'backup-status-banner';
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#ffc107;color:#333;text-align:center;padding:8px 16px;font-size:14px;font-weight:500;display:none';
    document.body.insertBefore(banner, document.body.firstChild);
  }

  function show(msg) {
    ensureBanner();
    banner.textContent = '\u{1F504} ' + msg;
    banner.style.display = 'block';
  }

  function hide() {
    if (banner) banner.style.display = 'none';
  }

  function poll() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'api/backup/status', true);
    xhr.onload = function() {
      if (xhr.status === 200) {
        var data = JSON.parse(xhr.responseText);
        if (data.running) {
          show(data.progress || 'Scheduled backup in progress\u2026');
        } else {
          hide();
        }
      }
    };
    xhr.send();
  }

  poll();
  setInterval(poll, 5000);
})();
