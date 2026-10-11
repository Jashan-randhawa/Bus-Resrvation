/**
 * Admin Panel Mobile Enhancements (Authoritative Navigation Controller)
 * - Sidebar Drawer open/close state machine with focus restoration
 * - Native keyboard accessibility (Escape closes, inert on closed drawer)
 * - Swipe gesture to close drawer on touch screens
 * - Viewport resize handling
 * - Auto-close mobile drawer on navigation link clicks
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('adminSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');
        var closeBtn = document.getElementById('sidebarCloseBtn');

        if (!sidebar) return;

        function setSidebar(open, restoreFocus) {
            sidebar.classList.toggle('show', open);
            if (overlay) {
                overlay.classList.toggle('show', open);
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

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

            if (!open && restoreFocus && toggle) {
                toggle.focus();
            }
        }

        function closeSidebar(restoreFocus) {
            setSidebar(false, restoreFocus);
        }

        // Initialize inert state on mobile devices when drawer is initially closed
        if (window.innerWidth < 992 && !sidebar.classList.contains('show')) {
            sidebar.setAttribute('inert', '');
        }

        // Viewport resize handling
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                sidebar.removeAttribute('inert');
                document.body.classList.remove('overflow-hidden');
            } else if (!sidebar.classList.contains('show')) {
                sidebar.setAttribute('inert', '');
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

        // Keyboard Escape key to close drawer and restore focus
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                closeSidebar(true);
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
    });
})();
