(function () {
    const el = document.getElementById('live-time');
    if (!el) return;

    const tz = el.getAttribute('data-timezone') || 'Asia/Kuala_Lumpur';

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
            el.textContent = new Date().toLocaleString();
        }
    }

    tick();
    setInterval(tick, 1000);
})();
