        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
    integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
    crossorigin="anonymous"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    if (typeof AOS !== 'undefined') {
        AOS.init();
    }

    // Responsive Mobile Sidebar Toggle
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('adminSidebar');
        var toggle = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');

        if (toggle && sidebar && overlay) {
            toggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
    });
</script>
</body>
</html>