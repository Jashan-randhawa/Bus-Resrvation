        </main>
    </div>
</div>
<!-- Bootstrap & jQuery without unused external AOS dependencies (U-19 / U-20) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
    integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
    crossorigin="anonymous"></script>
<script>
    // Responsive Mobile Sidebar Toggle with aria-expanded & Escape key handling (Issue 10)
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('adminSidebar');
        var toggle = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');

        if (!toggle || !sidebar || !overlay) return;

        function setSidebar(open) {
            sidebar.classList.toggle('show', open);
            overlay.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('overflow-hidden', open);
        }

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
    });
</script>
</body>
</html>
