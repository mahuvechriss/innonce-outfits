// ===== DARK/LIGHT MODE TOGGLE =====
function toggleTheme() {
    var html = document.documentElement;
    var current = html.getAttribute('data-theme');
    var newTheme = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('innonce-theme', newTheme);
    updateThemeIcon(newTheme);
    updateThemeColor(newTheme);
}

function updateThemeIcon(theme) {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.innerHTML = theme === 'dark'
        ? '<i class="fas fa-sun"></i>'
        : '<i class="fas fa-moon"></i>';
}

function updateThemeColor(theme) {
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.content = theme === 'dark' ? '#121212' : '#FF8C00';
    }
}

function loadTheme() {
    var saved = localStorage.getItem('innonce-theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var theme = saved || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
    updateThemeIcon(theme);
    updateThemeColor(theme);
}

loadTheme();

// ===== SERVICE WORKER REGISTRATION (PWA Offline Support) =====
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/innonce-outfits/sw.js')
            .then(function(registration) {
                console.log('[SW] Registered. Scope:', registration.scope);

                // Auto-update: skip waiting and reload when new SW detected
                if (registration.waiting) {
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
                registration.addEventListener('updatefound', function() {
                    var newWorker = registration.installing;
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            newWorker.postMessage({ type: 'SKIP_WAITING' });
                        }
                    });
                });
                navigator.serviceWorker.addEventListener('controllerchange', function() {
                    window.location.reload();
                });
            })
            .catch(function(error) {
                console.error('[SW] Registration failed:', error);
            });

        if (!navigator.onLine) {
            showOfflineIndicator();
        }
    });

    window.addEventListener('online', function() {
        hideOfflineIndicator();
        showConnectionStatus('<i class="fas fa-check-circle me-2"></i> Connection restored — you are back online', 'online');
    });

    window.addEventListener('offline', function() {
        showOfflineIndicator();
    });
}

// ===== OFFLINE INDICATOR =====
function showOfflineIndicator() {
    var existing = document.getElementById('offlineIndicator');
    if (existing) return;

    var div = document.createElement('div');
    div.id = 'offlineIndicator';
    div.className = 'offline-indicator';
    div.innerHTML = '<i class=\"fas fa-wifi-slash me-2\"></i> You are offline — viewing cached content';
    document.body.prepend(div);

    // Hide flash messages if any
    document.querySelectorAll('.alert').forEach(function(el) { el.style.display = 'none'; });
}

function hideOfflineIndicator() {
    var el = document.getElementById('offlineIndicator');
    if (el) {
        el.classList.add('offline-indicator-hiding');
        setTimeout(function() { el.remove(); }, 400);
    }
}

// ===== CONNECTION STATUS TOAST =====
function showConnectionStatus(message, type) {
    var existing = document.getElementById('connectionToast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.id = 'connectionToast';
    toast.className = 'connection-toast connection-toast-' + type;
    toast.innerHTML = message;
    document.body.appendChild(toast);

    setTimeout(function() {
        toast.classList.add('connection-toast-hiding');
        setTimeout(function() { toast.remove(); }, 400);
    }, 4000);
}

// ===== UPDATE NOTIFICATION =====
function showUpdateNotification() {
    var existing = document.getElementById('updateToast');
    if (existing) return;

    var toast = document.createElement('div');
    toast.id = 'updateToast';
    toast.className = 'connection-toast connection-toast-update';
    toast.innerHTML = '<i class=\"fas fa-download me-2\"></i> New version available! ' +
        '<button onclick="applyUpdate()" class=\"btn btn-sm btn-gold ms-2\">' +
        '<i class=\"fas fa-sync-alt me-1\"></i>Update</button>' +
        '<button onclick="dismissUpdate()" class=\"btn btn-sm btn-outline-light ms-1\">Dismiss</button>';
    document.body.appendChild(toast);
}

function applyUpdate() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('controllerchange', function() {
            window.location.reload();
        });
        navigator.serviceWorker.ready.then(function(registration) {
            registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        });
    }
}

function dismissUpdate() {
    var el = document.getElementById('updateToast');
    if (el) {
        el.classList.add('connection-toast-hiding');
        setTimeout(function() { el.remove(); }, 400);
    }
}

// ===== AUTO-SUBMIT FILTER WITH DEBOUNCE =====
var autoSubmitTimer;
function autoSubmit() {
    clearTimeout(autoSubmitTimer);
    autoSubmitTimer = setTimeout(function() {
        var form = document.getElementById('filterForm');
        if (form) form.submit();
    }, 400);
}

// ===== FILTER CATEGORY LIST =====
function filterCategoryList(input) {
    var list = document.getElementById('categoryList');
    if (!list) return;
    var items = list.querySelectorAll('.category-list-item');
    var query = input.value.toLowerCase().trim();
    items.forEach(function(item) {
        var name = item.querySelector('span').textContent.toLowerCase();
        item.style.display = (!query || name.indexOf(query) !== -1) ? '' : 'none';
    });
}

// ===== FILTER PANEL =====
function openFilterPanel() {
    var panel = document.getElementById('filterPanel');
    if (!panel) return;
    panel.classList.remove('has-active');
    panel.classList.add('show');
}

// ===== DOM CONTENT LOADED =====
document.addEventListener('DOMContentLoaded', function() {
    // Manual Bootstrap dropdown initialization
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(el) {
        if (typeof bootstrap !== 'undefined') {
            try { new bootstrap.Dropdown(el); } catch(e) {}
        }
    });
    // Size selector highlighting
    document.querySelectorAll('.size-option').forEach(function(el) {
        el.parentElement.addEventListener('click', function() {
            this.closest('.d-flex').querySelectorAll('.border').forEach(function(b) {
                b.classList.remove('border-dark', 'bg-dark', 'text-white');
            });
            this.classList.add('border-dark', 'bg-dark', 'text-white');
        });
    });

    // Click outside filter panel to close
    var panel = document.getElementById('filterPanel');
    var searchInput = document.querySelector('.search-trigger');
    document.addEventListener('click', function(e) {
        if (!panel || !searchInput) return;
        if (!panel.contains(e.target) && e.target !== searchInput && !searchInput.contains(e.target)) {
            panel.classList.remove('show');
        }
    });

    // Auto-show panel if search has value
    if (panel && searchInput && searchInput.value.trim()) {
        panel.classList.add('show');
    }

    // Close filter panel on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && panel) panel.classList.remove('show');
    });

    // ===== NOTIFICATION POLLING =====
    function pollNotifications() {
        fetch(SITE_URL + '/actions/notifications.php?action=poll')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var badge = document.getElementById('notifCount');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(function() {});
    }

    // Poll every 15 seconds
    if (document.getElementById('notifCount')) {
        pollNotifications();
        setInterval(pollNotifications, 15000);
    }

    // ===== NOTIFICATION TOAST (for real-time in-app) =====
    window.showNotificationToast = function(title, message) {
        var existing = document.getElementById('notifToast');
        if (existing) existing.remove();
        var toast = document.createElement('div');
        toast.id = 'notifToast';
        toast.className = 'connection-toast connection-toast-update';
        toast.innerHTML = '<i class="fas fa-bell me-2"></i><strong>' + escapeHtml(title) + '</strong><br><small>' + escapeHtml(message) + '</small>' +
            '<button onclick="this.parentElement.remove()" class="btn btn-sm btn-outline-light ms-2 float-end">&times;</button>';
        document.body.appendChild(toast);
        setTimeout(function() { var el = document.getElementById('notifToast'); if (el) { el.classList.add('connection-toast-hiding'); setTimeout(function() { if (el) el.remove(); }, 400); } }, 5000);
    };
});

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
