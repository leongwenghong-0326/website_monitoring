document.addEventListener('click', function (e) {
    const toggle = e.target.closest('.toggle-pass');
    if (toggle) {
        const input = document.getElementById(toggle.getAttribute('data-target'));
        if (!input) return;
        const hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        toggle.textContent = hidden ? 'Hide' : 'Show';
    }
});

document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (!confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });
});

(function () {
    const loginForm = document.getElementById('login-form');
    if (!loginForm) return;

    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const rememberBox = document.getElementById('remember');
    const storageKey = 'wm_admin_login';

    function loadSavedLogin() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return;
            const saved = JSON.parse(raw);
            if (!saved || !saved.remember) return;
            if (usernameInput && saved.username) usernameInput.value = saved.username;
            if (passwordInput && saved.password) passwordInput.value = saved.password;
            if (rememberBox) rememberBox.checked = true;
        } catch (err) {}
    }

    function saveLogin() {
        if (!rememberBox || !rememberBox.checked) {
            localStorage.removeItem(storageKey);
            return;
        }
        localStorage.setItem(storageKey, JSON.stringify({
            remember: true,
            username: usernameInput ? usernameInput.value : '',
            password: passwordInput ? passwordInput.value : ''
        }));
    }

    loadSavedLogin();

    loginForm.addEventListener('submit', function () {
        saveLogin();
    });
})();

(function () {
    const btn = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    if (!btn) return;

    function closeMenu() {
        document.body.classList.remove('sidebar-open');
        btn.setAttribute('aria-expanded', 'false');
        if (overlay) overlay.setAttribute('aria-hidden', 'true');
    }

    function openMenu() {
        document.body.classList.add('sidebar-open');
        btn.setAttribute('aria-expanded', 'true');
        if (overlay) overlay.setAttribute('aria-hidden', 'false');
    }

    btn.addEventListener('click', function () {
        if (document.body.classList.contains('sidebar-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    if (overlay) {
        overlay.addEventListener('click', closeMenu);
    }

    document.querySelectorAll('.sidebar nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 900) closeMenu();
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) closeMenu();
    });
})();

(function () {
    const meta = document.querySelector('meta[name="auto-check-url"]');
    if (!meta) return;

    const url = meta.getAttribute('content');
    const intervalSec = Math.max(5, parseInt(meta.getAttribute('data-interval') || '5', 10));
    const statusEl = document.getElementById('auto-check-status');

    function setStatus(text, ok) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.classList.toggle('is-error', ok === false);
    }

    function runAutoCheck() {
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    setStatus('Auto-check: error', false);
                    return;
                }
                if (data.skipped) {
                    setStatus('Auto-check: ON (every ' + intervalSec + 's)', true);
                    return;
                }
                setStatus('Auto-check: checked ' + data.checked + '/' + data.total, true);
                if (data.alerts > 0) {
                    window.location.reload();
                }
            })
            .catch(function () {
                setStatus('Auto-check: offline', false);
            });
    }

    setStatus('Auto-check: ON (every ' + intervalSec + 's)', true);
    runAutoCheck();
    setInterval(runAutoCheck, intervalSec * 1000);
})();
