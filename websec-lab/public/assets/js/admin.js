(function () {
  var routes = {
    stats: '/api/admin/stats',
    legacyNotes: '/api/admin/notes'
  };
  var button = document.getElementById('admin-stats');
  var output = document.getElementById('admin-out');
  if (!button || !output) {
    return;
  }
  button.addEventListener('click', function () {
    fetch(routes.stats, { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        output.textContent = JSON.stringify(data, null, 2);
      })
      .catch(function () {
        output.textContent = 'درخواست آمار ناموفق بود.';
      });
  });
})();
