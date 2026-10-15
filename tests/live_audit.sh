#!/usr/bin/env sh
# tests/live_audit.sh
# Phase 5: Automated Live HTTPS Verification Script
export PATH="/usr/bin:$PATH"

APP_URL="${1:-https://your-live-bus-domain.com}"
APP_URL="${APP_URL%/}"

echo "=== PHASE 5 LIVE HTTPS & PWA VERIFICATION ==="
echo "Testing Target: $APP_URL"
echo ""

for endpoint in "index.php" "manifest.json" "sw.js" "offline.html" "assets/img/app-icon.svg" "admin/dashboard.php"; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" -L "$APP_URL/$endpoint")
  if [ "$STATUS" = "200" ]; then
    echo "[PASS] HTTP 200: $APP_URL/$endpoint is live and accessible."
  else
    echo "[FAIL] HTTP $STATUS: $APP_URL/$endpoint failed to load!"
  fi
done

# Check that index.php outputs the manifest link over HTTPS
if curl -s -L "$APP_URL/index.php" | grep -q "manifest.json"; then
  echo "[PASS] Live HTML Head: _pwa-head.php is actively rendering on production."
else
  echo "[FAIL] Live HTML Head: manifest.json link not found in live index.php!"
fi
echo "============================================="
