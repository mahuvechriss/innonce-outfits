        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
<script>
var SITE_URL = '<?= SITE_URL ?>';

// Sidebar toggle
function toggleSidebar() {
    document.body.classList.toggle('admin-sidebar-collapsed');
    localStorage.setItem('admin-sidebar', document.body.classList.contains('admin-sidebar-collapsed') ? 'collapsed' : '');
}
(function() {
    if (localStorage.getItem('admin-sidebar') === 'collapsed') {
        document.body.classList.add('admin-sidebar-collapsed');
    }
})();
function showForm(type, action) {
    let el = document.getElementById(type + 'Form');
    if (el) {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    } else {
        let url = 'index.php?action=' + (action || type + 's') + '&show_form=1';
        window.location.href = url;
    }
}

// Admin notification polling
(function() {
    var bell = document.getElementById('adminNotifBell');
    if (!bell) return;
    function poll() {
        fetch(SITE_URL + '/actions/notifications.php?action=poll')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var badge = document.getElementById('adminNotifCount');
                var list = document.getElementById('adminNotifList');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                if (list) {
                    if (data.notifications && data.notifications.length > 0) {
                        var html = '';
                        data.notifications.forEach(function(n) {
                            var icon = n.type === 'order' ? 'fa-truck' : 'fa-info-circle';
                            var bg = n.is_read ? '' : 'bg-light';
                            html += '<a href="#" class="dropdown-item ' + bg + ' py-2" onclick="fetch(SITE_URL+\'/actions/notifications.php?action=mark_read&id=' + n.id + '\');event.preventDefault();this.parentElement.remove();">' +
                                '<div class="d-flex gap-2"><i class="fas ' + icon + ' mt-1 text-gold"></i>' +
                                '<div><strong class="small">' + n.title.replace(/</g,'&lt;') + '</strong>' +
                                '<p class="small text-muted mb-0">' + n.message.replace(/</g,'&lt;') + '</p></div></div></a>';
                        });
                        list.innerHTML = html;
                    } else {
                        list.innerHTML = '<div class="text-center text-muted small py-3">No notifications.</div>';
                    }
                }
            });
    }
    poll();
    setInterval(poll, 15000);
})();
</script>
</body>
</html>
<?php $db = null; ?>
