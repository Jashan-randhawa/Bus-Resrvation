    <script src="<?= BASE_URL ?>/assets/js/vendor/jquery.slim.min.js?v=3.5.1"></script>
    <script src="<?= BASE_URL ?>/assets/js/vendor/bootstrap.bundle.min.js?v=4.6.2"></script>
    <script>

      // Smooth scroll for in-page anchors excluding Bootstrap tabs/modals (P5, P6)
      document.querySelectorAll('a[href^="#"]:not([data-toggle])').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
          var targetId = this.getAttribute('href');
          if (targetId && targetId !== '#') {
            var target = document.querySelector(targetId);
            if (target) {
              e.preventDefault();
              var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
              target.scrollIntoView({
                behavior: reduceMotion ? 'auto' : 'smooth'
              });
              target.setAttribute('tabindex', '-1');
              target.focus({ preventScroll: true });
              if (window.history && window.history.pushState) {
                window.history.pushState(null, '', targetId);
              }
              // Auto-close mobile navigation menu after link tap (P5)
              var nav = document.getElementById('mycollapsediv');
              if (nav && nav.classList.contains('show') && window.jQuery) {
                $(nav).collapse('hide');
              }
            }
          }
        });
      });
    </script>
  </body>
</html>