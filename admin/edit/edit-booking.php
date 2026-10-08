<?php
// admin/edit/edit-booking.php -- Edit Booking Details (Issues 8, 15)
require_once __DIR__ . '/../../includes/auth/admin-session.php';
require_once __DIR__ . '/../../includes/db_con.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_role('super_admin', 'operator');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

$row = db_one($link, "SELECT * FROM booking WHERE sno = ?", 'i', [$id]);
if (!$row) {
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

$status = (string)($row['status'] ?? 'Confirmed');
$is_editable = !in_array($status, ['Cancelled', 'Expired'], true);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    csrf_verify();

    if (!$is_editable) {
        $error = "This reservation is {$status} and cannot be modified. A new booking must be created.";
    } else {
        $name = trim((string)($_POST['unm'] ?? ''));
        $num  = trim((string)($_POST['num'] ?? ''));
        $edit_reason = trim((string)($_POST['edit_reason'] ?? ''));
        $phone_digits = preg_replace('/\D+/', '', $num);

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Passenger name must be between 2 and 100 characters.';
        } elseif (strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
            $error = 'Please enter a valid contact phone number (10 to 15 digits).';
        } elseif (strlen($edit_reason) < 10) {
            $error = 'Please provide an edit reason of at least 10 characters.';
        } else {
            $new_price = (float)$row['price'];
            $price_override_reason = '';

            // Fare override: super_admin only with required >= 10 char reason (Issue 8)
            if (is_super_admin() && isset($_POST['amount'])) {
                $posted_amount = (float)$_POST['amount'];
                if (abs($posted_amount - (float)$row['price']) > 0.001) {
                    $price_reason = trim((string)($_POST['price_override_reason'] ?? ''));
                    if (strlen($price_reason) < 10) {
                        $error = 'Fare override requires a detailed justification of at least 10 characters.';
                    } else {
                        $new_price = $posted_amount;
                        $price_override_reason = $price_reason;
                    }
                }
            }

            if (!$error) {
                db_exec($link,
                    "UPDATE booking SET name = ?, contact = ?, price = ? WHERE sno = ?",
                    'ssdi',
                    [$name, $phone_digits, $new_price, $id]
                );

                $old_audit = ['name' => $row['name'], 'contact' => $row['contact'], 'price' => (float)$row['price']];
                $new_audit = [
                    'name'        => $name,
                    'contact'     => $phone_digits,
                    'price'       => $new_price,
                    'reason'      => $edit_reason,
                    'price_reason'=> $price_override_reason
                ];

                try {
                    audit($link, 'UPDATE', 'booking', $id, $old_audit, $new_audit);
                } catch (Throwable $e) {}

                flash_set('success', 'Booking details updated successfully.');
                header('Location: ' . BASE_URL . '/admin/bookings.php');
                exit;
            }
        }
    }
}

$title = 'Edit Booking';
require_once __DIR__ . '/../../includes/layout/header-admin.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/bookings.php">Bookings</a></li>
        <li class="breadcrumb-item active" aria-current="page">PNR <?= e($row['pnr'] ?? (string)$row['sno']) ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 font-weight-bold">Modify Booking Details</h5>
                <code>PNR: <?= e($row['pnr'] ?? (string)$row['sno']) ?></code>
            </div>
            <div class="card-body p-4">
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row text-center">
                        <div class="col-3">
                            <small class="text-muted text-uppercase">Bus</small>
                            <div class="font-weight-bold text-dark"><?= e($row['bus'] ?? '') ?></div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted text-uppercase">Seat</small>
                            <div class="font-weight-bold text-primary">#<?= e((string)$row['seat']) ?></div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted text-uppercase">Date</small>
                            <div class="font-weight-bold text-dark"><?= e($row['date'] ?? '') ?></div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted text-uppercase">Status</small>
                            <div>
                                <span class="badge badge-<?= $status === 'Confirmed' ? 'success' : ($status === 'Cancelled' ? 'danger' : 'warning') ?>">
                                    <?= e($status) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="border-top mt-2 pt-2 text-center text-muted small">
                        Route: <strong><?= e($row['city1'] ?? '') ?> &rarr; <?= e($row['city2'] ?? '') ?></strong> &bull; Departs: <strong><?= e($row['time'] ?? '') ?></strong>
                    </div>
                </div>

                <?php if (!$is_editable): ?>
                    <div class="alert alert-danger font-weight-bold mb-4">
                        ⚠️ This booking is <?= e($status) ?> and cannot be modified. Any changes to route, bus, date, or seat require cancellation and rebooking.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form action="" method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="unm" class="font-weight-bold small text-muted">Passenger Full Name</label>
                        <input type="text" id="unm" name="unm" class="form-control" value="<?= e($row['name'] ?? '') ?>" <?= !$is_editable ? 'disabled' : '' ?> required />
                    </div>
                    <div class="form-group">
                        <label for="num" class="font-weight-bold small text-muted">Contact Phone Number</label>
                        <input type="tel" id="num" name="num" class="form-control" value="<?= e($row['contact'] ?? '') ?>" <?= !$is_editable ? 'disabled' : '' ?> required />
                    </div>

                    <!-- Read-only Route Info (Issue 15: Route changes require rebooking) -->
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label class="font-weight-bold small text-muted">Origin (Trip locked)</label>
                            <input type="text" value="<?= e($row['city1'] ?? '') ?>" class="form-control bg-light" disabled />
                        </div>
                        <div class="col-6 form-group">
                            <label class="font-weight-bold small text-muted">Destination (Trip locked)</label>
                            <input type="text" value="<?= e($row['city2'] ?? '') ?>" class="form-control bg-light" disabled />
                        </div>
                    </div>

                    <!-- Tariff Field (Issue 8: super_admin only override) -->
                    <div class="form-group mb-3">
                        <label for="amount" class="font-weight-bold small text-muted">Tariff (<?= CURRENCY ?>)</label>
                        <?php if (is_super_admin() && $is_editable): ?>
                            <input type="number" step="0.01" min="1" name="amount" value="<?= e($row['price'] ?? '') ?>" class="form-control" id="amount">
                            <small class="form-text text-muted">Super Admin override enabled.</small>
                            <div class="mt-2">
                                <label for="price_override_reason" class="small font-weight-bold text-muted">Fare Override Reason (Min 10 chars, required if changed)</label>
                                <input type="text" id="price_override_reason" name="price_override_reason" class="form-control" placeholder="E.g., Senior citizen concession / promo adjustment">
                            </div>
                        <?php else: ?>
                            <input type="text" value="<?= e(number_format((float)$row['price'], 2)) ?>" class="form-control bg-light" disabled>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-4">
                        <label for="edit_reason" class="font-weight-bold small text-muted">Modification Reason (Min 5 chars)</label>
                        <input type="text" id="edit_reason" name="edit_reason" class="form-control" placeholder="E.g., Corrected passenger name spelling" <?= !$is_editable ? 'disabled' : '' ?> required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-outline-secondary">Back to Bookings</a>
                        <?php if ($is_editable): ?>
                            <button type="submit" class="btn btn-primary px-4 font-weight-bold" name="edit">Save Modifications</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout/footer-admin.php'; ?>