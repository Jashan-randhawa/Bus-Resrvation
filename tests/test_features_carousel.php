<?php
/**
 * tests/test_features_carousel.php
 *
 * Automated verification suite for the Mobile Features Carousel:
 * 1. Semantic Carousel Markup & Slide Roles in homepage.php
 * 2. Mobile-Only Navigation Controls (Arrows & Pagination Dots)
 * 3. Mobile CSS Scroll-Snap, Touch & Card Dimensions in home.css
 * 4. Desktop Grid Layout Preservation (hidden controls on >=768px)
 * 5. JavaScript Controller (Touch sync, Dot clicks, Keyboard navigation)
 * 6. Light and Dark Theme Styling Support
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function assert_carousel(string $desc, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ [PASS] {$desc}\n";
        $passed++;
    } else {
        echo "❌ [FAIL] {$desc}" . ($detail ? " ({$detail})" : '') . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "   BusBook Mobile Features Carousel Test Suite          \n";
echo "========================================================\n\n";

$homepage_path = $root . '/homepage.php';
$css_path = $root . '/assets/css/home.css';

assert_carousel("homepage.php exists", file_exists($homepage_path));
assert_carousel("assets/css/home.css exists", file_exists($css_path));

$home_content = file_exists($homepage_path) ? file_get_contents($homepage_path) : '';
$css_content = file_exists($css_path) ? file_get_contents($css_path) : '';

// Suite 1: Markup & Semantic Roles
echo "\n[*] Suite 1: Semantic Markup & Carousel Attributes in homepage.php\n";
assert_carousel("homepage.php wraps features in .features-carousel-wrap", strpos($home_content, 'features-carousel-wrap') !== false);
assert_carousel("features container defines id='featuresContainer' with carousel roles", 
    strpos($home_content, 'id="featuresContainer"') !== false &&
    strpos($home_content, 'role="region"') !== false &&
    strpos($home_content, 'aria-roledescription="carousel"') !== false);

assert_carousel("feature cards declare slide roles (role='group', aria-roledescription='slide')", 
    strpos($home_content, "role=\"group\" aria-roledescription=\"slide\"") !== false);

assert_carousel("mobile navigation controls have d-md-none scoping", 
    strpos($home_content, 'features-carousel-controls d-md-none') !== false);

assert_carousel("navigation arrows exist (#featuresPrevBtn & #featuresNextBtn)", 
    strpos($home_content, 'id="featuresPrevBtn"') !== false &&
    strpos($home_content, 'id="featuresNextBtn"') !== false);

assert_carousel("pagination dots exist with tablist and data-index", 
    strpos($home_content, 'features-carousel-dots') !== false &&
    strpos($home_content, 'data-index="0"') !== false &&
    strpos($home_content, 'data-index="1"') !== false &&
    strpos($home_content, 'data-index="2"') !== false);

// Suite 2: Stylesheet Architecture in home.css
echo "\n[*] Suite 2: Stylesheet Architecture & Responsive Rules in home.css\n";
assert_carousel("home.css defines desktop features grid layout", 
    strpos($css_content, '.features-container') !== false &&
    strpos($css_content, 'grid-template-columns: repeat(auto-fit') !== false);

assert_carousel("home.css defines mobile carousel with scroll-snap-type: x mandatory", 
    strpos($css_content, 'scroll-snap-type: x mandatory') !== false);

assert_carousel("home.css enables smooth scroll behavior and touch scrolling", 
    strpos($css_content, 'scroll-behavior: smooth') !== false &&
    strpos($css_content, '-webkit-overflow-scrolling: touch') !== false);

assert_carousel("home.css hides mobile scrollbars for clean UI", 
    strpos($css_content, 'scrollbar-width: none') !== false &&
    strpos($css_content, 'features-container::-webkit-scrollbar') !== false);

assert_carousel("home.css sizes mobile cards with scroll-snap-align: center", 
    strpos($css_content, 'scroll-snap-align: center') !== false &&
    strpos($css_content, 'scroll-snap-stop: always') !== false);

assert_carousel("home.css enforces 44px min-touch target for arrow controls", 
    strpos($css_content, 'min-width: 44px') !== false &&
    strpos($css_content, 'min-height: 44px') !== false);

assert_carousel("home.css styles active dot pill transition (width: 28px)", 
    strpos($css_content, '.features-dot.active') !== false &&
    strpos($css_content, 'width: 28px') !== false);

assert_carousel("home.css hides carousel controls on desktop (min-width: 768px)", 
    strpos($css_content, '@media (min-width: 768px)') !== false &&
    strpos($css_content, '.features-carousel-controls') !== false &&
    strpos($css_content, 'display: none !important') !== false);

assert_carousel("home.css includes dark mode support for mobile carousel controls", 
    strpos($css_content, 'html[data-theme="dark"] .features-carousel-arrow') !== false &&
    strpos($css_content, 'html[data-theme="dark"] .features-dot') !== false);

// Suite 3: JavaScript Controller
echo "\n[*] Suite 3: Controller Interactions in homepage.php\n";
assert_carousel("homepage.php defines initFeaturesCarousel controller", 
    strpos($home_content, 'initFeaturesCarousel') !== false);

assert_carousel("controller binds prev and next arrow click listeners", 
    strpos($home_content, 'prevBtn.addEventListener(\'click\'') !== false &&
    strpos($home_content, 'nextBtn.addEventListener(\'click\'') !== false);

assert_carousel("controller binds indicator dot click listeners", 
    strpos($home_content, 'dot.addEventListener(\'click\'') !== false &&
    strpos($home_content, 'scrollToCard(idx)') !== false);

assert_carousel("controller synchronizes active dot on scroll/swipe events", 
    strpos($home_content, 'container.addEventListener(\'scroll\'') !== false &&
    strpos($home_content, 'updateControls(closestIndex)') !== false);

assert_carousel("controller provides keyboard arrow navigation (ArrowLeft & ArrowRight)", 
    strpos($home_content, "e.key === 'ArrowLeft'") !== false &&
    strpos($home_content, "e.key === 'ArrowRight'") !== false);

assert_carousel("controller honors prefers-reduced-motion media query", 
    strpos($home_content, "window.matchMedia('(prefers-reduced-motion: reduce)')") !== false);

assert_carousel("features carousel implements 5-second auto-scroll timer", 
    strpos($home_content, 'autoScrollInterval = 5000') !== false &&
    strpos($home_content, 'startAutoScroll()') !== false);

assert_carousel("features carousel pauses on hover and touch interaction", 
    strpos($home_content, "addEventListener('mouseenter'") !== false &&
    strpos($home_content, "addEventListener('touchstart'") !== false);

assert_carousel("popular routes carousel defines 5-second interval", 
    strpos($home_content, 'interval: autoplay ? 5000 : false') !== false);

// Summary
echo "\n========================================================\n";
echo "   Features Carousel Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
