/**
 * Admin Panel Mobile Enhancements
 * - Swipe gesture to close sidebar on touch screens
 * - Auto-close mobile sidebar drawer on navigation clicks
 * - Viewport resize handler
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('adminSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('sidebarToggle');

        if (!sidebar) return;

        function closeSidebar() {
            sidebar.classList.remove('show');
            if (overlay) {
                overlay.classList.remove('show');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            if (window.innerWidth < 992) {
                sidebar.setAttribute('inert', '');
            }
            document.body.classList.remove('overflow-hidden');
        }

        // Swipe gesture to close sidebar (Swiping left > 50px)
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

                // Check horizontal swipe left > 50px with limited vertical drift
                if (deltaX > 50 && deltaY < 100) {
                    closeSidebar();
                }
            }
        }, { passive: true });

        // Auto-close sidebar on mobile navigation clicks
        var navLinks = sidebar.querySelectorAll('.admin-nav .nav-link, .sidebar-footer a');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });
    });
})();
