(function () {
    const seconds = parseInt(document.body.getAttribute('data-refresh') || '60', 10);
    const countdownEl = document.getElementById('countdown');
    let remaining = seconds;

    setInterval(function () {
        remaining -= 1;
        if (countdownEl) countdownEl.textContent = Math.max(remaining, 0);
        if (remaining <= 0) window.location.reload();
    }, 1000);

    const fsBtn = document.getElementById('btn-fullscreen');
    if (fsBtn) {
        fsBtn.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function () {});
            } else {
                document.exitFullscreen();
            }
        });
    }

    const soundBtn = document.getElementById('btn-sound');
    let soundOn = false;
    function beep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.value = 0.05;
            osc.start();
            setTimeout(function () { osc.stop(); ctx.close(); }, 180);
        } catch (err) {}
    }
    if (soundBtn) {
        soundBtn.addEventListener('click', function () {
            soundOn = !soundOn;
            soundBtn.setAttribute('data-on', soundOn ? '1' : '0');
            soundBtn.textContent = soundOn ? 'Alert sound on' : 'Alert sound off';
            if (soundOn && document.body.getAttribute('data-has-down') === '1') beep();
        });
        if (document.body.getAttribute('data-has-down') === '1') {
            const previous = sessionStorage.getItem('status-down');
            if (previous === '0') {
                /* became down since last visit */
            }
        }
        sessionStorage.setItem('status-down', document.body.getAttribute('data-has-down'));
    }
})();
