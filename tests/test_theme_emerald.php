<?php
// tests/test_theme_emerald.php
// Validates Theme Emerald Deprecation and Enforced Clean Light Mode

$total = 0;
$passed = 0;

function assert_emerald(string $description, bool $condition): void {
    global $total, $passed;
    $total++;
    if ($condition) {
        $passed++;
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
    }
}

echo "========================================================\n";
echo "   Theme Emerald Deprecation & Light Mode Test Suite    \n";
echo "========================================================\n\n";

$root = dirname(__DIR__);
$theme_path = $root . '/assets/css/theme-emerald.css';
assert_emerald("theme-emerald.css exists in assets/css/", file_exists($theme_path));

$theme_css = file_exists($theme_path) ? file_get_contents($theme_path) : '';
$manifest_path = $root . '/manifest.json';
$manifest = file_exists($manifest_path) ? json_decode(file_get_contents($manifest_path), true) : [];

// Suite 1: Theme Emerald Deprecation
echo "[*] Suite 1: Theme Emerald Deprecation\n";
assert_emerald("theme-emerald.css is deprecated with deprecation notice", 
    str_contains($theme_css, 'Deprecated')
);
assert_emerald("theme-emerald.css contains NO color-scheme: dark", 
    !str_contains($theme_css, 'color-scheme: dark;')
);
assert_emerald("theme-emerald.css contains NO dark canvas tokens", 
    !str_contains($theme_css, '--bg-canvas:') && !str_contains($theme_css, '--bg-surface:      #081216')
);

// Suite 2: Layout Template Unlinking
echo "\n[*] Suite 2: Layout Template Isolation (theme-emerald.css unlinked)\n";
$header_public = file_get_contents($root . '/includes/layout/header-public.php');
$header_user = file_get_contents($root . '/includes/layout/header-user.php');
$header_admin = file_get_contents($root . '/includes/layout/header-admin.php');
$header_login = file_get_contents($root . '/includes/layout/header-login.php');

assert_emerald("header-public.php does NOT link theme-emerald.css", !str_contains($header_public, 'theme-emerald.css'));
assert_emerald("header-user.php does NOT link theme-emerald.css", !str_contains($header_user, 'theme-emerald.css'));
assert_emerald("header-admin.php does NOT link theme-emerald.css", !str_contains($header_admin, 'theme-emerald.css'));
assert_emerald("header-login.php does NOT link theme-emerald.css", !str_contains($header_login, 'theme-emerald.css'));

// Suite 3: PWA Manifest Clean Light Background
echo "\n[*] Suite 3: PWA Manifest Clean Light Configuration\n";
assert_emerald("manifest.json defines background_color: #ffffff", ($manifest['background_color'] ?? '') === '#ffffff');
assert_emerald("manifest.json defines theme_color: #ffffff", ($manifest['theme_color'] ?? '') === '#ffffff');

// Suite 4: Semantic View Hooks Intact in Light Mode
echo "\n[*] Suite 4: Semantic View Component Hooks Intact in Light Mode\n";
$homepage = file_get_contents($root . '/homepage.php');
$user_index = file_get_contents($root . '/user/index.php');
$user_ticket = file_get_contents($root . '/user/ticket.php');

assert_emerald("homepage.php retains journey-card and search-panel semantic classes",
    str_contains($homepage, 'journey-card') && str_contains($homepage, 'search-panel')
);
assert_emerald("homepage.php retains btn-swap-cities button classes",
    str_contains($homepage, 'btn-swap-cities')
);
assert_emerald("user/index.php retains journey-card and search-panel classes",
    str_contains($user_index, 'journey-card') && str_contains($user_index, 'search-panel')
);
assert_emerald("user/ticket.php retains ticket structure classes",
    str_contains($user_ticket, 'ticket-container')
);

echo "\n========================================================\n";
echo "   Results: {$passed} Passed, " . ($total - $passed) . " Failed\n";
echo "========================================================\n";

if ($passed !== $total) {
    exit(1);
}
