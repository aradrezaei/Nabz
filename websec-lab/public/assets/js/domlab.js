(function () {
  var params = new URLSearchParams(location.search);
  var raw = params.get('dom');
  var safe = document.getElementById('dom-safe');
  var box = document.getElementById('dom-preview');
  var score = document.getElementById('dom-score');
  if (safe) {
    safe.textContent = raw || '';
  }
  if (!box || raw === null) {
    return;
  }
  box.innerHTML = raw;
  fetch('/api/claim/xss-dom', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ sink: box.innerHTML })
  }).then(function (response) {
    return response.json();
  }).then(function (data) {
    if (score && data && data.flag) {
      score.textContent = data.flag;
    }
  }).catch(function () {});
})();
