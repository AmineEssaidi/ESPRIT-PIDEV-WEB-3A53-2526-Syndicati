(function() {
  try {
    document.addEventListener('DOMContentLoaded', function(){
    // Inject CSS for pills
    var style = document.createElement('style');
    style.type = 'text/css';
    style.textContent = '\n' +
      '.admin-status-pill { display:inline-block; padding:6px 12px; border-radius:999px; font-weight:700; font-size:12px; margin-top:6px; margin-left:8px; color:#fff; white-space:nowrap; box-shadow:0 6px 12px rgba(0,0,0,.15); }' +
      '.admin-status-pill.online { background: linear-gradient(135deg, #00ff88, #00e676); box-shadow:0 0 8px #39ff14; }' +
      '.admin-status-pill.offline { background: linear-gradient(135deg, #ff3b30, #e53935); box-shadow:0 0 8px #ff2d2d; }';
    (document.head || document.body).appendChild(style);

    // Find admin sidebar links and append pills under Services tabs
    var sidebar = document.querySelector('.admin-sidebar') || document.body;
    var anchors = Array.from(sidebar.querySelectorAll('a[href]'));
    var pills = [];
    anchors.forEach(function(a){
      var href = (a.getAttribute('href') || '').toLowerCase();
      var text = (a.textContent || '').toLowerCase();
      if (href.indexOf('services') !== -1 || text.indexOf('services') !== -1 || text.indexOf('service') !== -1) {
        var pill = document.createElement('span');
        pill.className = 'admin-status-pill online';
        pill.textContent = 'Online';
        a.insertAdjacentElement('afterend', pill);
        pills.push(pill);
      }
    });
    function randomize(p) {
      var on = Math.random() > 0.5;
      p.textContent = on ? 'Online' : 'Offline';
      p.className = 'admin-status-pill ' + (on ? 'online' : 'offline');
    }
    pills.forEach(randomize);
    setInterval(function(){ pills.forEach(randomize); }, 3500);
  } catch(e) {
    // ignore
  }
  });
})();
