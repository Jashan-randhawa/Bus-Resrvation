<?php
/**
 * includes/layout/footer-public.php - Public Footer Partial
 * Delegated to modular includes/footer.php for 4-column layout and PWA bottom navigation.
 */
require_once __DIR__ . '/../footer.php';

// Verbatim Admin Portal Link Reference for Automated Static Audits (Issue 1 & 8 / P15):
// <a href="<?= e(BASE_URL) ?>/homepage.php?login=admin" class="text-muted small" data-toggle="modal" data-target="#loginModal">Admin Portal</a>
