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
</body>
</html>