        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
    integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
    crossorigin="anonymous"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"
    integrity="sha384-wziAfh6b/qT+3LrqebF9WeK4+J5sehS6FA10J1t3a866kJ/fvU5UwofWnQyzLtwu"
    crossorigin="anonymous"></script>
<script>
    if (typeof AOS !== 'undefined') {
        AOS.init();
    }

    // Responsive Mobile Sidebar Toggle (A5)
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('adminSidebar');
        var toggle = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');

        if (!toggle || !sidebar || !overlay) return;

        function setSidebar(open) {
            sidebar.classList.toggle('show', open);
            overlay.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (window.innerWidth < 992) {
                if (open) {
                    sidebar.removeAttribute('inert');
                } else {
                    sidebar.setAttribute('inert', '');
                }
            } else {
                sidebar.removeAttribute('inert');
            }
            document.body.classList.toggle('overflow-hidden', open);
        }

        // Initialize inert state on mobile devices
        if (window.innerWidth < 992) {
            sidebar.setAttribute('inert', '');
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                sidebar.removeAttribute('inert');
                document.body.classList.remove('overflow-hidden');
            } else if (!sidebar.classList.contains('show')) {
                sidebar.setAttribute('inert', '');
            }
        });

        toggle.addEventListener('click', function() {
            var isOpen = sidebar.classList.contains('show');
            setSidebar(!isOpen);
        });

        overlay.addEventListener('click', function() {
            setSidebar(false);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                setSidebar(false);
                toggle.focus();
            }
        });

        // Close on navigation link click when mobile drawer is open
        var sidebarLinks = sidebar.querySelectorAll('a, button');
        sidebarLinks.forEach(function(el) {
            el.addEventListener('click', function() {
                if (window.innerWidth < 992 && !el.classList.contains('theme-toggle-btn')) {
                    setSidebar(false);
                }
            });
        });
    });
</script>

<!-- Session Timeout Warning Modal (A15) -->
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" role="dialog" aria-labelledby="sessionTimeoutModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-weight-bold" id="sessionTimeoutModalLabel">⏱️ Session Timeout Warning</h5>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="mb-3 text-dark">Due to inactivity, your administrative session will expire in:</p>
                <div class="display-4 font-weight-bold text-danger mb-3" id="sessionCountdown">120</div>
                <p class="small text-muted mb-0">seconds. Unsaved form changes will be lost if your session expires.</p>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <a href="<?= BASE_URL ?>/homepage.php?login=admin&error=expired" class="btn btn-outline-secondary btn-sm">
                    Log Out Now
                </a>
                <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btnStayLoggedIn">
                    Stay Logged In
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Session Timeout Warning & Heartbeat (A15)
(function() {
    var idleLimit = <?= (int)(defined('SESSION_IDLE_SECONDS') ? SESSION_IDLE_SECONDS : 1800) ?>;
    var warnBefore = 120; // 2 minutes countdown
    var warningTimer = null;
    var countdownTimer = null;
    var secondsRemaining = warnBefore;
    var modal = document.getElementById('sessionTimeoutModal');
    var countdownEl = document.getElementById('sessionCountdown');
    var stayBtn = document.getElementById('btnStayLoggedIn');

    if (!modal) return;

    function startIdleTimer() {
        if (warningTimer) clearTimeout(warningTimer);
        if (countdownTimer) clearInterval(countdownTimer);

        var msUntilWarn = Math.max(1000, (idleLimit - warnBefore) * 1000);
        warningTimer = setTimeout(showWarningModal, msUntilWarn);
    }

    function showWarningModal() {
        secondsRemaining = warnBefore;
        if (countdownEl) countdownEl.textContent = secondsRemaining;
        if (window.jQuery) {
            window.jQuery('#sessionTimeoutModal').modal('show');
        }

        countdownTimer = setInterval(function() {
            secondsRemaining--;
            if (countdownEl) countdownEl.textContent = Math.max(0, secondsRemaining);
            if (secondsRemaining <= 0) {
                clearInterval(countdownTimer);
                window.location.href = '<?= BASE_URL ?>/homepage.php?login=admin&error=expired';
            }
        }, 1000);
    }

    if (stayBtn) {
        stayBtn.addEventListener('click', function() {
            fetch('<?= BASE_URL ?>/admin/api-heartbeat.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (data && data.ok) {
                    if (window.jQuery) {
                        window.jQuery('#sessionTimeoutModal').modal('hide');
                    }
                    startIdleTimer();
                }
            }).catch(function() {
                if (window.jQuery) {
                    window.jQuery('#sessionTimeoutModal').modal('hide');
                }
                startIdleTimer();
            });
        });
    }

    startIdleTimer();
})();
</script>
</body>
</html>