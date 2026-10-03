# 🛡️ Security, Configuration & Troubleshooting

This document explains security best practices, connection troubleshooting, and common operational challenges.

---

## 1. Security Architecture & Hardening

### 1.1 Zero Hardcoded Secrets
All production credentials have been stripped from the source code and relocated to environment variables.
- Legacy configuration committed credentials in plain text (`db_con.php`).
- Modern configuration reads credentials securely via `getenv()`:

```php
$hostname = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$db_name  = getenv('DB_NAME') ?: 'majorproject';
$db_port  = getenv('DB_PORT') ?: 3306;
$use_ssl  = getenv('DB_SSL') === 'true';
```

### 1.2 Mandatory TLS/SSL Transport
When connecting to TiDB Cloud or cloud-hosted MySQL, database traffic traverses the public internet. Enforcing TLS ensures credentials, customer records, and ticket purchases remain encrypted in transit:

```php
$link = mysqli_init();

if ($use_ssl) {
    mysqli_ssl_set($link, NULL, NULL, NULL, NULL, NULL);
    mysqli_real_connect($link, $hostname, $username, $password, $db_name, (int)$db_port, NULL, MYSQLI_CLIENT_SSL);
} else {
    mysqli_real_connect($link, $hostname, $username, $password, $db_name, (int)$db_port);
}
```

### 1.3 Session Security & Output Buffering
- **Header Order:** `session_start()` and `ob_start()` are called at the top of scripts before any HTML output or included headers.
- **Output Buffering (`output_buffering = 4096`):** Configured in the Docker container to buffer server output, preventing `headers already sent` fatal errors during HTTP redirects.

---

## 2. Troubleshooting & Diagnostic Runbook

### Issue 1: "Connections using insecure transport are prohibited"
- **Cause:** TiDB Cloud Serverless mandates TLS/SSL connections.
- **Fix:** Ensure `DB_SSL=true` is set in your Render environment variables. The PHP connector will initialize `MYSQLI_CLIENT_SSL`.

### Issue 2: "Session cannot be started after headers have already been sent"
- **Cause:** Output (whitespace, HTML, or `echo`/`var_dump` calls) was sent to the client before `session_start()`.
- **Fix:** Ensure `session_start()` is called at the beginning of the script. Verify that the Docker container has `output_buffering` enabled.

### Issue 3: "Database connection failed: Connection refused"
- **Cause:** Incorrect `DB_PORT` or unreachable database host.
- **Fix:**
  - TiDB Cloud runs on port **`4000`** (not default MySQL 3306). Verify that `DB_PORT=4000` is set.
  - Ensure the database user is allowed to connect from any remote host (`%`).

### Issue 4: "Blocked aria-hidden on an element because its descendant retained focus"
- **Cause:** Bootstrap 4 modal close button briefly retains focus while the parent dialog is assigned `aria-hidden="true"`.
- **Impact:** Harmless client-side browser warning. Does not impact application logic or server processing.

### Issue 5: Render Web Service spins down after inactivity
- **Cause:** Render free tier web services enter sleep mode after 15 minutes without incoming HTTP traffic.
- **Behavior:** The first request after a sleep cycle takes ~30 to 50 seconds while the container spins back up. Subsequent requests respond instantly.
