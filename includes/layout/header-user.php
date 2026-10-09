<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Customer Portal') ?> - Bus Reservation</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    
    <!-- Design System & User Styles (U-19 / U-20) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/seat-map.css">

    <!-- Global Theme Toggle (Head load to avoid FOUC) -->
    <script src="<?= BASE_URL ?>/assets/js/theme-toggle.js"></script>
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <!-- Brand Header -->
            <div class="admin-brand">
                <a href="<?= BASE_URL ?>/homepage.php" class="brand-link">
                    <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Bus Logo" class="brand-logo">
                    <span class="brand-title">Bus Service</span>
                </a>
                <span class="badge badge-info p-1 px-2 font-weight-bold" style="font-size:0.65rem;">User</span>
            </div>

            <!-- Profile Info -->
            <div class="admin-profile">
                <img src="<?= BASE_URL ?>/assets/images/userav-min.png" alt="User Avatar" class="profile-avatar">
                <div class="profile-info">
                    <div class="profile-name"><?= e($_SESSION['name'] ?? 'Passenger') ?></div>
                    <div class="profile-role">Registered Account</div>
                </div>
            </div>

            <!-- User Navigation -->
            <nav class="admin-nav">
                <?php
                $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
                ?>
                <div class="nav-group-header">My Trip Center</div>
                <ul class="nav-group-list">
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/index.php" class="nav-link <?= $current_script === 'index.php' || $current_script === 'booking.php' ? 'active' : '' ?>">
                            <span class="mr-2">🔍</span> Search & Book
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/my-bookings.php" class="nav-link <?= $current_script === 'my-bookings.php' ? 'active' : '' ?>">
                            <span class="mr-2">🎟️</span> My Reservations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/profile.php" class="nav-link <?= $current_script === 'profile.php' ? 'active' : '' ?>">
                            <span class="mr-2">👤</span> My Profile
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- User Footer -->
            <div class="admin-sidebar-footer">
                <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-sm btn-outline-light btn-block mb-2">
                    &larr; Public Homepage
                </a>
                <form action="<?= BASE_URL ?>/user/index.php" method="post" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" value="logout" name="logout" class="btn btn-sm btn-danger btn-block">
                        Log Out
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-main">
            <!-- Top bar with toggle & theme switch -->
            <div class="admin-topbar">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="sidebarToggle">
                    &#9776; Menu
                </button>
                <span class="font-weight-bold text-dark ml-2"><?= e($title ?? 'Customer Area') ?></span>
                
                <div class="ml-auto d-flex align-items-center">
                    <button type="button" class="theme-toggle-btn ml-2" aria-label="Toggle dark mode" title="Toggle dark mode">
                        <span class="theme-toggle-icon"></span>
                        <span class="theme-toggle-text d-none d-sm-inline ml-1">Theme</span>
                    </button>
                </div>
            </div>

            <div class="admin-content-wrap">
            <?php if (function_exists('flash_get')): ?>
                <?php foreach (flash_get() as $m): ?>
                    <div class="alert alert-<?= e($m['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                        <?= e($m['msg'] ?? '') ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Idle Session Warning Modal (U-14) -->
            <div class="modal fade" id="idleSessionModal" tabindex="-1" role="dialog" aria-labelledby="idleSessionModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title font-weight-bold" id="idleSessionModalLabel">⚠️ Session Timeout Warning</h5>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div style="font-size: 48px;" class="mb-2">⏱️</div>
                            <h5 class="font-weight-bold text-dark">Are you still there?</h5>
                            <p class="text-muted mb-0">Your session will expire in <strong id="idle-countdown">120</strong> seconds due to inactivity. Click below to stay signed in and keep your work.</p>
                        </div>
                        <div class="modal-footer justify-content-center bg-light">
                            <button type="button" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm" id="stay-signed-in-btn">
                                Stay Signed In
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // U-14, Phase 5.2, Issue 8: Accurate idle session detection using Date.now() and visibilitychange
            (function() {
                var lastActivity = Date.now();
                var warningShown = false;
                var countdownVal = 120;
                var countdownInterval = null;
                var prevActiveElement = null;

                function getLoginRedirectUrl() {
                    return '<?= BASE_URL ?>/homepage.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
                }

                function resetIdle() {
                    if (!warningShown) {
                        lastActivity = Date.now();
                    }
                }
                ['mousemove', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function(evt) {
                    document.addEventListener(evt, resetIdle, { passive: true });
                });

                var stayBtn = document.getElementById('stay-signed-in-btn');

                // Phase 5.2: Trap focus inside modal when active
                document.addEventListener('keydown', function(e) {
                    if (warningShown && e.key === 'Tab') {
                        var modal = document.getElementById('idleSessionModal');
                        if (modal) {
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
                        if (typeof $ !== 'undefined' && $('#idleSessionModal').length) {
                            $('#idleSessionModal').modal('show');
                        } else {
                            var m = document.getElementById('idleSessionModal');
                            if (m) m.classList.add('show', 'd-block');
                        }
                        if (stayBtn) {
                            setTimeout(function() { stayBtn.focus(); }, 100);
                        }
                        countdownVal = Math.max(1, 1800 - elapsedSec);
                        var cd = document.getElementById('idle-countdown');
                        if (cd) cd.textContent = countdownVal;

                        if (countdownInterval) clearInterval(countdownInterval);
                        countdownInterval = setInterval(function() {
                            var curElapsed = Math.floor((Date.now() - lastActivity) / 1000);
                            countdownVal = Math.max(0, 1800 - curElapsed);
                            if (cd) cd.textContent = countdownVal;
                            if (countdownVal <= 0 || curElapsed >= 1800) {
                                clearInterval(countdownInterval);
                                window.location.href = getLoginRedirectUrl();
                            }
                        }, 1000);
                    }
                }

                setInterval(checkIdleState, 1000);

                // Recalculate immediately when tab regains focus or becomes visible (Issue 8)
                document.addEventListener('visibilitychange', function() {
                    if (!document.hidden) {
                        checkIdleState();
                    }
                });

                if (stayBtn) {
                    stayBtn.addEventListener('click', function() {
                        fetch('<?= BASE_URL ?>/user/api-heartbeat.php')
                            .then(function(res) { return res.json(); })
                            .then(function(data) {
                                lastActivity = Date.now();
                                warningShown = false;
                                if (countdownInterval) clearInterval(countdownInterval);
                                if (typeof $ !== 'undefined' && $('#idleSessionModal').length) {
                                    $('#idleSessionModal').modal('hide');
                                } else {
                                    var m = document.getElementById('idleSessionModal');
                                    if (m) m.classList.remove('show', 'd-block');
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
            })();
            </script>