<?php
// includes/layout/google-button.php -- "Continue with Google" button + divider.
// Renders nothing unless GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are configured.
require_once dirname(__DIR__) . '/google-auth.php';
if (google_enabled() && google_redirect_uri() !== ''):
    $google_btn_next = safe_next_url($_GET['next'] ?? $_POST['next'] ?? null);
    $google_btn_href = BASE_URL . '/google-login.php' . ($google_btn_next ? '?next=' . rawurlencode($google_btn_next) : '');
?>
<div class="d-flex align-items-center my-3" role="separator" aria-label="or">
  <hr class="flex-grow-1 m-0">
  <span class="px-2 small text-muted">or</span>
  <hr class="flex-grow-1 m-0">
</div>
<a href="<?= e($google_btn_href) ?>" class="btn btn-outline-secondary btn-block py-2 font-weight-bold d-flex align-items-center justify-content-center" style="min-height: 44px;">
  <svg width="18" height="18" viewBox="0 0 48 48" class="mr-2" aria-hidden="true" focusable="false">
    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
  </svg>
  Continue with Google
</a>
<?php endif; ?>
