/**
 * assets/js/dep-carousel.js
 * Departure Schedule Carousel - Admin Dashboard (A12)
 * Smooth multi-card carousel with auto-sliding, accessible controls, pause guards & touch swipe.
 */
(function() {
    'use strict';

    function initDepCarousel() {
        var carousel = document.querySelector('.dep-carousel');
        if (!carousel) return;

        var viewport = carousel.querySelector('.dep-viewport');
        var track = carousel.querySelector('.dep-track');
        var cards = carousel.querySelectorAll('.dep-card');
        var controls = carousel.querySelector('.dep-carousel-controls');
        var prevBtn = carousel.querySelector('.dep-prev');
        var nextBtn = carousel.querySelector('.dep-next');
        var dotsContainer = carousel.querySelector('.dep-dots');
        var pauseBtn = carousel.querySelector('.dep-toggle-pause');

        var totalCards = cards.length;
        if (totalCards === 0) return;

        var intervalMs = parseInt(carousel.getAttribute('data-interval'), 10) || 3000;
        var currentIndex = 0;
        var visibleCount = 1;
        var maxIndex = 0;
        var timer = null;
        var isPlaying = true;
        var isHovered = false;
        var isFocused = false;
        var isTabHidden = false;
        var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        var isReducedMotion = motionQuery.matches;

        function getVisibleCount() {
            var val = parseInt(window.getComputedStyle(carousel).getPropertyValue('--dep-visible'), 10);
            return Math.max(1, isNaN(val) ? 1 : val);
        }

        function getStepWidth() {
            if (!cards.length) return 0;
            var cardWidth = cards[0].getBoundingClientRect().width;
            var gap = parseFloat(window.getComputedStyle(track).gap) || 16;
            return cardWidth + gap;
        }

        function updatePosition() {
            var step = getStepWidth();
            track.style.transform = 'translateX(-' + (currentIndex * step) + 'px)';
        }

        function updateDots() {
            if (!dotsContainer) return;
            var dots = dotsContainer.querySelectorAll('.dep-dot');
            dots.forEach(function(dot, idx) {
                var active = idx === currentIndex;
                dot.classList.toggle('active', active);
                if (active) {
                    dot.setAttribute('aria-selected', 'true');
                } else {
                    dot.removeAttribute('aria-selected');
                }
            });
        }

        function buildDots() {
            if (!dotsContainer) return;
            dotsContainer.innerHTML = '';
            for (var i = 0; i <= maxIndex; i++) {
                (function(idx) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'dep-dot' + (idx === currentIndex ? ' active' : '');
                    dot.setAttribute('aria-label', 'Go to departures slide ' + (idx + 1));
                    dot.setAttribute('role', 'tab');
                    if (idx === currentIndex) {
                        dot.setAttribute('aria-selected', 'true');
                    }
                    dot.addEventListener('click', function() {
                        goTo(idx, true);
                    });
                    dotsContainer.appendChild(dot);
                })(i);
            }
        }

        function shouldAutoAdvance() {
            return isPlaying && !isHovered && !isFocused && !isTabHidden && !isReducedMotion && totalCards > visibleCount;
        }

        function stopTimer() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function startTimer() {
            stopTimer();
            if (shouldAutoAdvance()) {
                timer = setInterval(function() {
                    next(false);
                }, intervalMs);
            }
        }

        function restartTimer() {
            startTimer();
        }

        function goTo(index, restart) {
            if (totalCards <= visibleCount) return;
            if (index > maxIndex) {
                currentIndex = 0;
            } else if (index < 0) {
                currentIndex = maxIndex;
            } else {
                currentIndex = index;
            }
            updatePosition();
            updateDots();
            if (restart !== false) {
                restartTimer();
            }
        }

        function next(restart) {
            goTo(currentIndex + 1, restart);
        }

        function prev(restart) {
            goTo(currentIndex - 1, restart);
        }

        function updatePauseButton() {
            if (!pauseBtn) return;
            if (isPlaying) {
                pauseBtn.setAttribute('aria-label', 'Pause automatic sliding');
                pauseBtn.setAttribute('title', 'Pause automatic sliding');
                pauseBtn.innerHTML = '<span class="dep-pause-icon" aria-hidden="true">⏸</span>';
            } else {
                pauseBtn.setAttribute('aria-label', 'Resume automatic sliding');
                pauseBtn.setAttribute('title', 'Resume automatic sliding');
                pauseBtn.innerHTML = '<span class="dep-pause-icon" aria-hidden="true">▶</span>';
            }
        }

        function updateLayout() {
            visibleCount = getVisibleCount();
            maxIndex = Math.max(0, totalCards - visibleCount);

            if (totalCards <= visibleCount) {
                if (controls) controls.style.display = 'none';
                stopTimer();
                currentIndex = 0;
                track.style.transform = 'translateX(0px)';
            } else {
                if (controls) controls.style.display = 'flex';
                if (currentIndex > maxIndex) {
                    currentIndex = maxIndex;
                }
                buildDots();
                updatePosition();
                if (shouldAutoAdvance() && !timer) {
                    startTimer();
                }
            }
        }

        // Attach event listeners
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                prev(true);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                next(true);
            });
        }

        if (pauseBtn) {
            pauseBtn.addEventListener('click', function() {
                isPlaying = !isPlaying;
                updatePauseButton();
                if (isPlaying) {
                    startTimer();
                } else {
                    stopTimer();
                }
            });
        }

        // Pause on mouse hover
        carousel.addEventListener('mouseenter', function() {
            isHovered = true;
            stopTimer();
        });
        carousel.addEventListener('mouseleave', function() {
            isHovered = false;
            startTimer();
        });

        // Pause on keyboard focus
        carousel.addEventListener('focusin', function() {
            isFocused = true;
            stopTimer();
        });
        carousel.addEventListener('focusout', function(e) {
            if (!carousel.contains(e.relatedTarget)) {
                isFocused = false;
                startTimer();
            }
        });

        // Keyboard navigation (ArrowLeft / ArrowRight)
        carousel.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prev(true);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                next(true);
            }
        });

        // Pause when browser tab is hidden
        document.addEventListener('visibilitychange', function() {
            isTabHidden = document.hidden;
            if (isTabHidden) {
                stopTimer();
            } else {
                startTimer();
            }
        });

        // Listen for reduced motion changes
        if (motionQuery.addEventListener) {
            motionQuery.addEventListener('change', function(e) {
                isReducedMotion = e.matches;
                if (isReducedMotion) {
                    stopTimer();
                } else {
                    startTimer();
                }
            });
        }

        // Touch swipe on mobile
        var touchStartX = 0;
        var touchStartY = 0;
        carousel.addEventListener('touchstart', function(e) {
            if (e.touches && e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }
        }, { passive: true });

        carousel.addEventListener('touchend', function(e) {
            if (e.changedTouches && e.changedTouches.length === 1) {
                var touchEndX = e.changedTouches[0].clientX;
                var touchEndY = e.changedTouches[0].clientY;
                var deltaX = touchStartX - touchEndX;
                var deltaY = Math.abs(touchStartY - touchEndY);
                if (Math.abs(deltaX) > 40 && deltaY < 80) {
                    if (deltaX > 0) {
                        next(true);
                    } else {
                        prev(true);
                    }
                }
            }
        }, { passive: true });

        // Responsive resize
        window.addEventListener('resize', updateLayout);

        // Initial setup
        updateLayout();
        updatePauseButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDepCarousel);
    } else {
        initDepCarousel();
    }
})();
