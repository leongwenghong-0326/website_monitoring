(function () {
    const el = document.getElementById('live-time');
    if (!el) return;

    const tz = el.getAttribute('data-timezone') || 'Asia/Kuala_Lumpur';

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function tick() {
        try {
            el.textContent = new Date().toLocaleString('en-GB', {
                timeZone: tz,
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        } catch (err) {
            const now = new Date();
            el.textContent = pad(now.getDate()) + ' ' + now.toLocaleString('en-GB', { month: 'short' }) + ' ' +
                now.getFullYear() + ', ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        }
    }

    tick();
    setInterval(tick, 1000);
})();
