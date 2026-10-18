#!/usr/bin/env bash
# scripts/sync-assets.sh - package source is the single source of truth
set -euo pipefail
cp packages/ui/src/theme-toggle.js assets/js/theme-toggle.js
cp packages/seat-picker/dist/seat-picker.js assets/js/vendor/seat-picker.js
echo "Vendored assets synced."
