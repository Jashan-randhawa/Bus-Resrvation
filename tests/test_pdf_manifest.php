<?php
/**
 * Test Suite: Passenger Manifest PDF Generation & Mobile Card Implementation
 */

require_once __DIR__ . '/../includes/helpers.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}" . ($detail ? " - {$detail}" : "") . "\n";
    }
}

echo "========================================================\n";
echo "   Manifest PDF & Mobile View Verification Suite        \n";
echo "========================================================\n\n";

// 1. PDF Generation Engine Tests
echo "[*] Suite 1: Pure PHP PDF Document Generation\n";
assert_test("generate_manifest_pdf_doc is defined in helpers.php", function_exists('generate_manifest_pdf_doc'));
assert_test("escape_pdf_txt is defined in helpers.php", function_exists('escape_pdf_txt'));

$dummy_passengers = [
    [
        'seat' => '1',
        'name' => 'Alice Johnson',
        'contact' => '******7890',
        'city1' => 'City A',
        'city2' => 'City B',
        'time' => '10:00 AM',
        'pnr' => 'PNR1001',
        'price' => 450.00
    ],
    [
        'seat' => '2',
        'name' => 'Bob Smith',
        'contact' => '******1234',
        'city1' => 'City A',
        'city2' => 'City B',
        'time' => '10:00 AM',
        'pnr' => 'PNR1002',
        'price' => 450.00
    ]
];

$pdf_output = generate_manifest_pdf_doc('PASSENGER TRIP MANIFEST', 'BUS-TEST-101', '2026-10-11', $dummy_passengers, 900.00);

assert_test("PDF output starts with PDF-1.4 header", str_starts_with($pdf_output, "%PDF-1.4"));
assert_test("PDF output ends with %%EOF", str_contains($pdf_output, "%%EOF"));
assert_test("PDF contains Catalog dictionary", str_contains($pdf_output, "/Type /Catalog"));
assert_test("PDF contains Pages dictionary", str_contains($pdf_output, "/Type /Pages"));
assert_test("PDF contains Bus Number in text stream", str_contains($pdf_output, "BUS-TEST-101"));
assert_test("PDF contains Passenger Name in text stream", str_contains($pdf_output, "Alice Johnson"));
assert_test("PDF contains PNR in text stream", str_contains($pdf_output, "PNR1001"));

// Test multi-page handling
$many_passengers = [];
for ($i = 1; $i <= 35; $i++) {
    $many_passengers[] = [
        'seat' => (string)$i,
        'name' => 'Passenger ' . $i,
        'contact' => '98765432' . sprintf("%02d", $i),
        'city1' => 'City A',
        'city2' => 'City B',
        'time' => '08:00',
        'pnr' => 'PNR' . (100 + $i),
        'price' => 500.00
    ];
}
$multipage_pdf = generate_manifest_pdf_doc('PASSENGER TRIP MANIFEST', 'BUS-MULTI', '2026-10-11', $many_passengers, 17500.00);
assert_test("Multipage PDF contains /Count 2 for 35 rows", str_contains($multipage_pdf, "/Count 2"));

// 2. Admin Manifest Mobile View & Endpoints
echo "\n[*] Suite 2: Manifest Mobile UI & Export Endpoints\n";
$manifest_code = file_get_contents(__DIR__ . '/../admin/manifest.php');

assert_test("manifest.php handles ?export=pdf", str_contains($manifest_code, "\$_GET['export'] === 'pdf'"));
assert_test("manifest.php handles ?export=csv for desktop", str_contains($manifest_code, "\$_GET['export'] === 'csv'"));
assert_test("manifest.php contains dedicated mobile card (.manifest-mobile-card)", str_contains($manifest_code, "manifest-mobile-card"));
assert_test("manifest-mobile-card has .d-md-none to render only on mobile", str_contains($manifest_code, "manifest-mobile-card d-md-none"));
assert_test("manifest-mobile-card form targets ?export=pdf", str_contains($manifest_code, '<input type="hidden" name="export" value="pdf">'));
assert_test("manifest-mobile-card allows selecting bus by bus number", str_contains($manifest_code, 'name="bus"') && str_contains($manifest_code, 'id="mobile_manifest_bus"'));
assert_test("manifest-mobile-card has Download Manifest (PDF) button", str_contains($manifest_code, 'Download Manifest (PDF)'));
assert_test("Header CSV export button is hidden on mobile (.d-none .d-md-inline-flex)", (str_contains($manifest_code, "'export' => 'csv'") || str_contains($manifest_code, "export=csv")) && str_contains($manifest_code, "d-none d-md-inline-flex"));
assert_test("Header Print button is hidden on mobile (.d-none .d-md-inline-flex)", str_contains($manifest_code, "window.print()") && str_contains($manifest_code, "d-none d-md-inline-flex"));
assert_test("Complex desktop trip sheet table is hidden on mobile (.manifest-sheet-card .d-none .d-md-block)", str_contains($manifest_code, "manifest-sheet-card border-0 shadow-sm mb-4 d-none d-md-block"));
assert_test("Filter card is hidden on mobile (.manifest-filter-card .d-none .d-md-block)", str_contains($manifest_code, "manifest-filter-card no-print filter-card d-none d-md-block"));

// 3. Admin CSS Mobile Manifest Styling
echo "\n[*] Suite 3: Admin Stylesheet Verification\n";
$css_code = file_get_contents(__DIR__ . '/../assets/css/admin.css');
assert_test("admin.css defines .manifest-mobile-card", str_contains($css_code, ".manifest-mobile-card"));
assert_test("admin.css defines .manifest-mobile-icon-wrap", str_contains($css_code, ".manifest-mobile-icon-wrap"));
assert_test("admin.css supports dark theme for manifest-mobile-card", str_contains($css_code, 'html[data-theme="dark"] .manifest-mobile-card'));

echo "\n========================================================\n";
echo "   Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
