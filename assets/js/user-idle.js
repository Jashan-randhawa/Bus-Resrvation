/**
 * assets/js/user-idle.js
 * User Portal Idle Session Detection & Keep-Alive Modal (F14)
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('idleSessionModal');
        if (!modal) return;

        var stayBtn = document.getElementById('stay-signed-in-btn');
        var cdElem = document.getElementById('idle-countdown');
        var heartbeatUrl = modal.getAttribute('data-heartbeat-url') || '/user/api-heartbeat.php';
        var loginRedirect = modal.getAttribute('data-login-url') || '/homepage.php';

        var lastActivity = Date.now();
        var warningShown = false;
        var countdownVal = 120;
        var countdownInterval = null;
        var prevActiveElement = null;

        function getLoginRedirectUrl() {
            var sep = loginRedirect.indexOf('?') === -1 ? '?next=' : '&next=';
            return loginRedirect + sep + encodeURIComponent(window.location.pathname + window.location.search);
        }

        function resetIdle() {
            if (!warningShown) {
                lastActivity = Date.now();
            }
        }

        ['mousemove', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function(evt) {
            document.addEventListener(evt, resetIdle, { passive: true });
        });

        // Trap focus inside modal when active
        document.addEventListener('keydown', function(e) {
            if (warningShown && e.key === 'Tab') {
                var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable.length) {
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        last.focus();
                        e.preventDefault();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        first.focus();
                        e.preventDefault();
                    }
                }
            }
        });

        function checkIdleState() {
            var elapsedSec = Math.floor((Date.now() - lastActivity) / 1000);

            // If idle time already exceeds 1800s (30m session limit), redirect immediately to login
            if (elapsedSec >= 1800) {
                if (countdownInterval) clearInterval(countdownInterval);
                window.location.href = getLoginRedirectUrl();
                return;
            }

            // 28 minutes = 1680 seconds: show warning modal if not already shown
            if (elapsedSec >= 1680 && !warningShown) {
                warningShown = true;
                prevActiveElement = document.activeElement;
                if (typeof $ !== 'undefined' && $(modal).length) {
                    $(modal).modal('show');
                } else {
                    modal.classList.add('show', 'd-block');
                }
                if (stayBtn) {
                    setTimeout(function() { stayBtn.focus(); }, 100);
                }
                countdownVal = Math.max(1, 1800 - elapsedSec);
                if (cdElem) cdElem.textContent = countdownVal;

                if (countdownInterval) clearInterval(countdownInterval);
                countdownInterval = setInterval(function() {
                    var curElapsed = Math.floor((Date.now() - lastActivity) / 1000);
                    countdownVal = Math.max(0, 1800 - curElapsed);
                    if (cdElem) cdElem.textContent = countdownVal;
                    if (countdownVal <= 0 || curElapsed >= 1800) {
                        clearInterval(countdownInterval);
                        window.location.href = getLoginRedirectUrl();
                    }
                }, 1000);
            }
        }

        setInterval(checkIdleState, 1000);

        // Recalculate immediately when tab regains focus or becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                checkIdleState();
            }
        });

        if (stayBtn) {
            stayBtn.addEventListener('click', function() {
                fetch(heartbeatUrl)
                    .then(function(res) { return res.json(); })
                    .then(function() {
                        lastActivity = Date.now();
                        warningShown = false;
                        if (countdownInterval) clearInterval(countdownInterval);
                        if (typeof $ !== 'undefined' && $(modal).length) {
                            $(modal).modal('hide');
                        } else {
                            modal.classList.remove('show', 'd-block');
                        }
                        if (prevActiveElement && typeof prevActiveElement.focus === 'function') {
                            prevActiveElement.focus();
                        }
                    })
                    .catch(function() {
                        window.location.reload();
                    });
            });
        }
    });
})();
