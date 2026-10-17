<?php
/**
 * tests/test_pwabuilder_manifest.php
 *
 * Automated verification suite for PWABuilder Manifest Criteria:
 * - Required members (start_url, short_name, icons, name)
 * - Recommended members (id, orientation, display, theme_color, background_color, description, screenshots)
 * - Optional members (scope, display_override, edge_side_panel, share_target, protocol_handlers,
 *                     launch_handler, file_handlers, categories, shortcuts, prefer_related_applications,
 *                     related_applications, iarc_rating_id, widgets, note_taking, lang, dir, scope_extensions)
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function check_item($category, $member, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        echo "✅ [PASS] [$category] $member\n";
        $passed++;
    } else {
        echo "❌ [FAIL] [$category] $member" . ($details ? " ($details)" : "") . "\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "   PWABuilder Web App Manifest Verification Suite       \n";
echo "========================================================\n\n";

$manifest_path = $root . '/manifest.json';
if (!file_exists($manifest_path)) {
    die("manifest.json not found!\n");
}

$manifest = json_decode(file_get_contents($manifest_path), true);
if (!is_array($manifest)) {
    die("manifest.json is not valid JSON!\n");
}

// 1. Required
echo "[1] Required Criteria:\n";
check_item('Required', 'start_url', !empty($manifest['start_url']));
check_item('Required', 'short_name', !empty($manifest['short_name']));
check_item('Required', 'icons', is_array($manifest['icons']) && count($manifest['icons']) > 0);
$has_192_png_any = false;
foreach ($manifest['icons'] as $icon) {
    if (($icon['sizes'] ?? '') === '192x192' && ($icon['type'] ?? '') === 'image/png' && strpos(($icon['purpose'] ?? 'any'), 'any') !== false && file_exists($root . '/' . $icon['src'])) {
        $has_192_png_any = true;
        break;
    }
}
check_item('Required', 'icons (192x192 PNG with purpose any)', $has_192_png_any);
check_item('Required', 'name', !empty($manifest['name']));

// 2. Recommended
echo "\n[2] Recommended Criteria:\n";
check_item('Recommended', 'id', !empty($manifest['id']));
check_item('Recommended', 'orientation', !empty($manifest['orientation']));
check_item('Recommended', 'display', !empty($manifest['display']));
check_item('Recommended', 'theme_color', !empty($manifest['theme_color']));
check_item('Recommended', 'background_color', !empty($manifest['background_color']));
check_item('Recommended', 'description', !empty($manifest['description']));
$has_512_png_any = false;
foreach ($manifest['icons'] as $icon) {
    if (($icon['sizes'] ?? '') === '512x512' && ($icon['type'] ?? '') === 'image/png' && ($icon['purpose'] ?? '') === 'any' && file_exists($root . '/' . $icon['src'])) {
        $has_512_png_any = true;
        break;
    }
}
check_item('Recommended', 'icons (512x512 PNG with purpose any)', $has_512_png_any);
check_item('Recommended', 'screenshots', is_array($manifest['screenshots']) && count($manifest['screenshots']) >= 2 && file_exists($root . '/' . $manifest['screenshots'][0]['src']));

// 3. Optional
echo "\n[3] Optional Criteria:\n";
check_item('Optional', 'scope', !empty($manifest['scope']));
check_item('Optional', 'display_override', is_array($manifest['display_override']) && count($manifest['display_override']) > 0);
check_item('Optional', 'edge_side_panel', is_array($manifest['edge_side_panel']) && isset($manifest['edge_side_panel']['preferred_width']));
check_item('Optional', 'share_target', is_array($manifest['share_target']) && !empty($manifest['share_target']['action']));
$has_valid_enctype = !empty($manifest['share_target']['enctype']) && in_array($manifest['share_target']['enctype'], ['application/x-www-form-urlencoded', 'multipart/form-data']);
check_item('Optional', 'share_target (explicit valid enctype)', $has_valid_enctype);
check_item('Optional', 'protocol_handlers', is_array($manifest['protocol_handlers']) && count($manifest['protocol_handlers']) > 0);
check_item('Optional', 'launch_handler', is_array($manifest['launch_handler']) && !empty($manifest['launch_handler']['client_mode']));
check_item('Optional', 'file_handlers', is_array($manifest['file_handlers']) && count($manifest['file_handlers']) > 0);
check_item('Optional', 'categories', is_array($manifest['categories']) && count($manifest['categories']) > 0);
check_item('Optional', 'shortcuts', is_array($manifest['shortcuts']) && count($manifest['shortcuts']) >= 2);
check_item('Optional', 'prefer_related_applications', isset($manifest['prefer_related_applications']) && is_bool($manifest['prefer_related_applications']));
check_item('Optional', 'related_applications', is_array($manifest['related_applications']) && count($manifest['related_applications']) > 0);
check_item('Optional', 'iarc_rating_id', !empty($manifest['iarc_rating_id']));
check_item('Optional', 'widgets', is_array($manifest['widgets']) && !empty($manifest['widgets'][0]['ms_ac_template']));

$widget_screenshots_valid = true;
if (!empty($manifest['widgets'][0]['screenshots'])) {
    foreach ($manifest['widgets'][0]['screenshots'] as $ws) {
        $src = $root . '/' . ($ws['src'] ?? '');
        if (!file_exists($src)) {
            $widget_screenshots_valid = false;
            break;
        }
        $sizes = explode('x', $ws['sizes'] ?? '');
        if (count($sizes) === 2) {
            $w = (int)$sizes[0];
            $h = (int)$sizes[1];
            if ($w > 1024 || $h > 1024 || $w <= 0 || $h <= 0) {
                $widget_screenshots_valid = false;
                break;
            }
        } else {
            $widget_screenshots_valid = false;
            break;
        }
    }
}
check_item('Optional', 'widgets (screenshots <= 1024x1024)', $widget_screenshots_valid);

check_item('Optional', 'note_taking', is_array($manifest['note_taking']) && !empty($manifest['note_taking']['new_note_url']));
check_item('Optional', 'lang', !empty($manifest['lang']));
check_item('Optional', 'dir', !empty($manifest['dir']));
check_item('Optional', 'scope_extensions', is_array($manifest['scope_extensions']) && count($manifest['scope_extensions']) > 0);

$scope_extensions_valid = true;
foreach ($manifest['scope_extensions'] as $se) {
    if (empty($se['type']) || empty($se['origin'])) {
        $scope_extensions_valid = false;
        break;
    }
}
check_item('Optional', 'scope_extensions (entries define both type and origin)', $scope_extensions_valid);

echo "\n========================================================\n";
echo "   Results: $passed Passed, $failed Failed\n";
echo "========================================================\n";

if ($failed > 0) exit(1);
