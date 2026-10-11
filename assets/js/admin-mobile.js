/**
 * Admin Panel Mobile Navigation Controller (Section 3.4 Specification)
 * - Sidebar Drawer open/close state machine with focus management
 * - Native keyboard accessibility (Escape closes, inert on closed drawer / inert on admin-main when open)
 * - Traps focus inside drawer when open with fallback loop
 * - Swipe gesture to close drawer on touch screens
 * - Viewport resize handling (auto-close & state clear >= 992px)
 * - Bootstrap modal coordination (closes drawer before modal opens; scroll lock coordination)
 * - Guard against duplicate initialization
 */
(function() {
    'use strict';

    if (window.__adminMobileNavInitialized) return;
    window.__adminMobileNavInitialized = true;

    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('adminSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');
        var closeBtn = document.getElementById('sidebarCloseBtn');
        var adminMain = document.querySelector('.admin-main') || document.getElementById('adminMain');

        if (!sidebar) return;

        function getFocusableElements(container) {
            if (!container) return [];
            return Array.prototype.slice.call(
                container.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
            ).filter(function(el) {
                return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
            });
        }

        function setSidebar(open, restoreFocus) {
            var isMobile = window.innerWidth < 992;
            sidebar.classList.toggle('show', open);
            if (overlay) {
                overlay.classList.toggle('show', open);
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            if (isMobile) {
                if (open) {
                    sidebar.removeAttribute('inert');
                    sidebar.setAttribute('role', 'dialog');
                    sidebar.setAttribute('aria-modal', 'true');
                    sidebar.setAttribute('aria-label', 'Navigation drawer');
                    if (adminMain) {
                        adminMain.setAttribute('inert', '');
                    }
                    // Move focus to close button inside drawer
                    if (closeBtn) {
                        setTimeout(function() { closeBtn.focus(); }, 50);
                    }
                } else {
                    sidebar.setAttribute('inert', '');
                    sidebar.removeAttribute('role');
                    sidebar.removeAttribute('aria-modal');
                    sidebar.removeAttribute('aria-label');
                    if (adminMain) {
                        adminMain.removeAttribute('inert');
                    }
                }
            } else {
                sidebar.removeAttribute('inert');
                if (adminMain) {
                    adminMain.removeAttribute('inert');
                }
            }

            // Coordinate scroll lock with Bootstrap modal
            if (open) {
                document.body.classList.add('overflow-hidden');
            } else {
                if (!document.body.classList.contains('modal-open')) {
                    document.body.classList.remove('overflow-hidden');
                }
            }

            if (!open && restoreFocus && toggle) {
                toggle.focus();
            }
        }

        function closeSidebar(restoreFocus) {
            setSidebar(false, restoreFocus);
        }

        // Initial mobile inert state
        if (window.innerWidth < 992 && !sidebar.classList.contains('show')) {
            sidebar.setAttribute('inert', '');
        }

        // Viewport resize handling (>= 992px closes drawer and clears inert)
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
                sidebar.removeAttribute('inert');
                sidebar.removeAttribute('role');
                sidebar.removeAttribute('aria-modal');
                sidebar.removeAttribute('aria-label');
                if (adminMain) adminMain.removeAttribute('inert');
                if (!document.body.classList.contains('modal-open')) {
                    document.body.classList.remove('overflow-hidden');
                }
            } else if (!sidebar.classList.contains('show')) {
                sidebar.setAttribute('inert', '');
                if (adminMain) adminMain.removeAttribute('inert');
            }
        });

        // Toggle button click
        if (toggle) {
            toggle.addEventListener('click', function() {
                var isOpen = sidebar.classList.contains('show');
                setSidebar(!isOpen, isOpen);
            });
        }

        // Dedicated drawer close button click
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeSidebar(true);
            });
        }

        // Backdrop overlay click
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeSidebar(true);
            });
        }

        // Keyboard handling: Escape key closes; Tab key focus trap fallback
        document.addEventListener('keydown', function(e) {
            if (!sidebar.classList.contains('show')) return;

            if (e.key === 'Escape') {
                closeSidebar(true);
                return;
            }

            if (e.key === 'Tab') {
                var focusables = getFocusableElements(sidebar);
                if (focusables.length === 0) return;
                var first = focusables[0];
                var last = focusables[focusables.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === first || !sidebar.contains(document.activeElement)) {
                        e.preventDefault();
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last || !sidebar.contains(document.activeElement)) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }
        });

        // Swipe gesture to close drawer (swiping left > 50px with vertical drift < 100px)
        var touchStartX = 0;
        var touchStartY = 0;

        document.addEventListener('touchstart', function(e) {
            if (e.touches && e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            if (!sidebar.classList.contains('show')) return;
            if (e.changedTouches && e.changedTouches.length === 1) {
                var touchEndX = e.changedTouches[0].clientX;
                var touchEndY = e.changedTouches[0].clientY;
                var deltaX = touchStartX - touchEndX;
                var deltaY = Math.abs(touchStartY - touchEndY);

                if (deltaX > 50 && deltaY < 100) {
                    closeSidebar(true);
                }
            }
        }, { passive: true });

        // Auto-close sidebar on mobile navigation clicks (excluding theme toggle)
        var navLinks = sidebar.querySelectorAll('.admin-nav a, .admin-sidebar-footer a, .admin-sidebar-footer button');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992 && !link.classList.contains('theme-toggle-btn')) {
                    closeSidebar(false);
                }
            });
        });

        // Bootstrap modal coordination: if a modal opens, close the drawer first
        if (window.jQuery) {
            window.jQuery(document).on('show.bs.modal', function() {
                if (sidebar.classList.contains('show')) {
                    closeSidebar(false);
                }
            });
        }
    });
})();
