#!/usr/bin/env sh
export PATH="/usr/bin:$PATH"
echo "=== PHASE 4 VERIFICATION CHECKLIST ==="

# 1. Verify Root Customer Views (index.php, pnr.php)
for f in index.php pnr.php; do
  if grep -q "assets/partials/_pwa-head.php" "$f" && php -l "$f" > /dev/null 2>&1; then
    echo "[PASS] Root View: $f includes _pwa-head.php and has valid PHP syntax."
  else
    echo "[FAIL] Root View: $f is missing _pwa-head.php or has a syntax error!"
  fi
done

# 2. Verify All 7 Admin Views
for f in admin/dashboard.php admin/booking.php admin/bus.php admin/route.php admin/seat.php admin/customer.php admin/signup.php; do
  if grep -q "\.\./assets/partials/_pwa-head.php" "$f" && php -l "$f" > /dev/null 2>&1; then
    echo "[PASS] Admin View: $f includes ../assets/partials/_pwa-head.php and has valid syntax."
  else
    echo "[FAIL] Admin View: $f is missing ../assets/partials/_pwa-head.php or has a syntax error!"
  fi
done

# 3. Verify Backend JSON & Auth Handlers Were NOT Modified
if ! grep -q "_pwa-head.php" assets/partials/_getJSON.php assets/partials/_handleLogin.php 2>/dev/null; then
  echo "[PASS] Backend Safety: _getJSON.php and _handleLogin.php remain pure backend scripts."
else
  echo "[FAIL] Backend Safety: Remove _pwa-head.php from _getJSON.php / _handleLogin.php!"
fi

echo "======================================"
