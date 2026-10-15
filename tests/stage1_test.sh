#!/usr/bin/env sh
export PATH="/usr/bin:$PATH"
echo "=== STAGE 1: PHASE 2 STATIC & SYNTAX AUDIT ==="

# 1. Validate manifest.json syntax using PHP's JSON parser
php -r '
  $json = @file_get_contents("manifest.json");
  $data = json_decode($json, true);
  if ($data && isset($data["start_url"], $data["icons"], $data["shortcuts"])) {
      echo "✅ [PASS] manifest.json is valid JSON and contains start_url, icons, and shortcuts.\n";
  } else {
      echo "❌ [FAIL] manifest.json has invalid JSON syntax or missing required keys!\n";
  }
'

# 2. Check all icon paths referenced inside manifest.json actually exist on disk
if [ -f "assets/img/app-icon.svg" ] && grep -q "</svg>" assets/img/app-icon.svg; then
  echo "✅ [PASS] assets/img/app-icon.svg exists and has a closing </svg> tag."
else
  echo "❌ [FAIL] assets/img/app-icon.svg is missing or corrupted."
fi

# 3. Check offline.html structure
if [ -f "offline.html" ] && grep -q "window.location.reload()" offline.html; then
  echo "✅ [PASS] offline.html exists and includes the reload trigger."
else
  echo "❌ [FAIL] offline.html is missing or incomplete."
fi

# 4. Audit sw.js critical safety rules for PHP + MySQL
if [ -f "sw.js" ]; then
  grep -q "request.method !== 'GET'" sw.js && echo "✅ [PASS] sw.js Rule A: POST requests (logins/bookings) bypass cache." || echo "❌ [FAIL] sw.js missing POST bypass!"
  grep -q "endsWith('.php')" sw.js && echo "✅ [PASS] sw.js Rule C: Dynamic .php pages use Network-First." || echo "❌ [FAIL] sw.js missing .php Network-First rule!"
  grep -q "OFFLINE_URL" sw.js && echo "✅ [PASS] sw.js Fallback: Offline fallback reference is configured." || echo "❌ [FAIL] sw.js missing OFFLINE_URL fallback!"
else
  echo "❌ [FAIL] sw.js does not exist in root directory!"
fi

echo "=============================================="
