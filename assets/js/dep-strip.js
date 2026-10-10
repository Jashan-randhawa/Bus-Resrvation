/**
 * assets/js/dep-strip.js
 * Today's Departures 5-Second Scrolling Strip (Plan v2)
 * Horizontal smooth-scrolling card strip with auto-advancement, pause guards,
 * progress bar indicator, counter, and touch/keyboard accessibility.
 */
(function() {
    'use strict';

    function initDepStrip() {
        var strip = document.querySelector('.dep-strip');
        if (!strip) return;

        var track = strip.querySelector('.dep-track');
        var cards = strip.querySelectorAll('.dep-card');
        var prevBtn = strip.querySelector('.dep-prev');
        var nextBtn = strip.querySelector('.dep-next');
        var pauseBtn = strip.querySelector('.dep-toggle-pause, .dep-pause');
        var counterEl = strip.querySelector('.dep-counter-curr');
        var progressContainer = strip.querySelector('.dep-progress');
        var progressBar = strip.querySelector('.dep-progress-bar');

        var totalCards = cards.length;
        if (totalCards === 0 || !track) return;

        var intervalMs = parseInt(strip.getAttribute('data-interval'), 10) || 5000;
        var timer = null;
        var idleTimer = null;
        var isPlaying = true;
        var isHovered = false;
        var isFocused = false;
        var isInteracting = false;
        var isTabHidden = false;
        var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        var isReducedMotion = motionQuery.matches;

        // If reduced motion is requested, turn auto-move off by default
        if (isReducedMotion) {
            isPlaying = false;
        }

        function getCardStep() {
            if (!cards.length) return 260;
            var firstCard = cards[0];
            var style = window.getComputedStyle(track);
            var gap = parseFloat(style.gap) || 16;
            return firstCard.offsetWidth + gap;
        }

        function isScrollable() {
            return track.scrollWidth > track.clientWidth + 4;
        }

        function updateCounter() {
            if (!counterEl) return;
            var step = getCardStep();
            if (step <= 0) return;
            var idx = Math.min(totalCards, Math.max(1, Math.round(track.scrollLeft / step) + 1));
            counterEl.textContent = idx;
        }

        function resetProgressAnimation() {
            if (!progressBar) return;
            progressBar.style.transition = 'none';
            progressBar.style.width = '0%';
            // Force reflow
            void progressBar.offsetWidth;
            if (shouldAutoAdvance()) {
                progressBar.style.transition = 'width ' + intervalMs + 'ms linear';
                progressBar.style.width = '100%';
                if (progressContainer) progressContainer.classList.remove('is-paused');
            } else {
                if (progressContainer) progressContainer.classList.add('is-paused');
            }
        }

        function shouldAutoAdvance() {
            return isPlaying && !isHovered && !isFocused && !isInteracting && !isTabHidden && !isReducedMotion && isScrollable();
        }

        function stop() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
            if (progressBar) {
                progressBar.style.transition = 'none';
                progressBar.style.width = '0%';
                if (progressContainer) progressContainer.classList.add('is-paused');
            }
        }

        function play() {
            stop();
            if (shouldAutoAdvance()) {
                resetProgressAnimation();
                timer = setInterval(function() {
                    step(1);
                }, intervalMs);
            }
        }

        function step(direction) {
            if (!isScrollable()) return;
            var cardWidth = getCardStep();
            var maxScroll = track.scrollWidth - track.clientWidth;
            var currentScroll = track.scrollLeft;

            if (direction > 0) {
                if (currentScroll >= maxScroll - 4) {
                    track.scrollTo({ left: 0, behavior: isReducedMotion ? 'auto' : 'smooth' });
                } else {
                    track.scrollTo({ left: Math.min(maxScroll, currentScroll + cardWidth), behavior: isReducedMotion ? 'auto' : 'smooth' });
                }
            } else {
                if (currentScroll <= 4) {
                    track.scrollTo({ left: maxScroll, behavior: isReducedMotion ? 'auto' : 'smooth' });
                } else {
                    track.scrollTo({ left: Math.max(0, currentScroll - cardWidth), behavior: isReducedMotion ? 'auto' : 'smooth' });
                }
            }
            setTimeout(function() {
                updateCounter();
                resetProgressAnimation();
            }, 300);
        }

        function updatePauseButton() {
            if (!pauseBtn) return;
            var iconSpan = pauseBtn.querySelector('.dep-pause-icon');
            if (isPlaying) {
                pauseBtn.setAttribute('aria-label', 'Pause automatic sliding');
                pauseBtn.setAttribute('title', 'Pause automatic sliding');
                if (iconSpan) iconSpan.textContent = '⏸';
                else pauseBtn.textContent = '⏸';
            } else {
                pauseBtn.setAttribute('aria-label', 'Resume automatic sliding');
                pauseBtn.setAttribute('title', 'Resume automatic sliding');
                if (iconSpan) iconSpan.textContent = '▶';
                else pauseBtn.textContent = '▶';
            }
        }

        function scrollToFirstActiveDeparture() {
            for (var i = 0; i < cards.length; i++) {
                if (!cards[i].classList.contains('is-departed')) {
                    if (i > 0) {
                        var stepWidth = getCardStep();
                        track.scrollTo({ left: i * stepWidth, behavior: 'auto' });
                    }
                    break;
                }
            }
            updateCounter();
        }

        function handleUserInteraction() {
            isInteracting = true;
            stop();
            if (idleTimer) clearTimeout(idleTimer);
            idleTimer = setTimeout(function() {
                isInteracting = false;
                play();
            }, 5000);
        }

        // Attach controls
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                handleUserInteraction();
                step(-1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                handleUserInteraction();
                step(1);
            });
        }

        if (pauseBtn) {
            pauseBtn.addEventListener('click', function() {
                isPlaying = !isPlaying;
                updatePauseButton();
                if (isPlaying) {
                    play();
                } else {
                    stop();
                }
            });
        }

        // Pause on mouse hover
        strip.addEventListener('mouseenter', function() {
            isHovered = true;
            stop();
        });
        strip.addEventListener('mouseleave', function() {
            isHovered = false;
            play();
        });

        // Pause on keyboard focus inside strip
        strip.addEventListener('focusin', function() {
            isFocused = true;
            stop();
        });
        strip.addEventListener('focusout', function(e) {
            if (!strip.contains(e.relatedTarget)) {
                isFocused = false;
                play();
            }
        });

        // Keyboard arrow navigation on focusable track
        track.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                handleUserInteraction();
                step(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                handleUserInteraction();
                step(1);
            }
        });

        // Touch & Wheel events
        track.addEventListener('touchstart', handleUserInteraction, { passive: true });
        track.addEventListener('wheel', handleUserInteraction, { passive: true });
        track.addEventListener('scroll', function() {
            updateCounter();
        }, { passive: true });

        // Tab hidden / visibilitychange
        document.addEventListener('visibilitychange', function() {
            isTabHidden = document.hidden;
            if (isTabHidden) {
                stop();
            } else {
                play();
            }
        });

        // Reduced motion changes
        if (motionQuery.addEventListener) {
            motionQuery.addEventListener('change', function(e) {
                isReducedMotion = e.matches;
                if (isReducedMotion) {
                    stop();
                } else {
                    play();
                }
            });
        }

        // Window resize
        window.addEventListener('resize', function() {
            updateCounter();
            if (!isScrollable()) {
                stop();
            } else if (shouldAutoAdvance() && !timer) {
                play();
            }
        });

        // Initialize state
        scrollToFirstActiveDeparture();
        updatePauseButton();
        if (shouldAutoAdvance()) {
            play();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDepStrip);
    } else {
        initDepStrip();
    }
})();
