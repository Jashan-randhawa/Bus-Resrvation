<?php
// includes/seat-map.php -- Realistic Bus Seat Map Renderer (U-15)
// Renders authentic coach interior with real-life passenger seats, driver cabin, and accessible controls.

/**
 * Render accessible, realistic bus seat map fieldset with radio inputs and layout model.
 *
 * @param array $layout Output from build_seat_layout()
 * @param array $booked Associative array of booked seat numbers [seat_no => true]
 * @param array $meta Metadata containing 'bus', 'date', 'time' for grid attributes
 */
function render_seat_map(array $layout, array $booked, array $meta = []): void {
    $left = (int)($layout['left'] ?? 2);
    $right = (int)($layout['right'] ?? 2);
    $rows = (array)($layout['rows'] ?? []);

    $bus_attr = htmlspecialchars($meta['bus'] ?? '', ENT_QUOTES, 'UTF-8');
    $date_attr = htmlspecialchars($meta['date'] ?? '', ENT_QUOTES, 'UTF-8');
    $time_attr = htmlspecialchars($meta['time'] ?? '', ENT_QUOTES, 'UTF-8');
    $preselected = (int)($meta['selected'] ?? 0);
    $admin_mode = !empty($meta['admin_mode']);
    $status_map = (array)($meta['status_map'] ?? []);

    $render_seat_box = function(?array $seat) use ($admin_mode, $status_map, $booked, $preselected): void {
        if ($seat === null) {
            echo '<span class="seat-box seat-empty" aria-hidden="true"></span>';
            return;
        }
        $s_no = (int)$seat['no'];
        $s_type = (string)$seat['type'];
        $s_tag = ($s_type === 'Window') ? 'W' : 'A';

        if ($admin_mode) {
            $seat_status = 'Available';
            if (isset($status_map[$s_no])) {
                $seat_status = ($status_map[$s_no] === 'Pending') ? 'Pending' : 'Confirmed';
            } elseif (isset($booked[$s_no]) || in_array($s_no, $booked, true)) {
                $seat_status = 'Confirmed';
            }
            $status_class = match($seat_status) {
                'Confirmed' => 'seat-status-confirmed',
                'Pending'   => 'seat-status-pending',
                default     => 'seat-status-available',
            };
            $status_label = match($seat_status) {
                'Confirmed' => 'Confirmed Booking',
                'Pending'   => 'Pending Hold',
                default     => 'Available',
            };
            ?>
            <div class="seat-box <?= $status_class ?>" data-seat="<?= $s_no ?>" data-status="<?= $seat_status ?>">
                <div class="seat-label" title="Seat <?= $s_no ?> (<?= e($s_type) ?> Seat - <?= $status_label ?>)"
                     role="img" aria-label="Seat <?= $s_no ?>, <?= e($s_type) ?>, <?= $status_label ?>" tabindex="0">
                    <!-- Real Headrest Pillow -->
                    <span class="seat-headrest" aria-hidden="true"></span>

                    <!-- Realistic Chair Body with Armrests -->
                    <span class="seat-body-wrap">
                        <span class="seat-armrest seat-armrest-left" aria-hidden="true"></span>
                        <span class="seat-cushion">
                            <span class="seat-corner-icon" aria-hidden="true"></span>
                            <span class="seat-num"><?= $s_no ?></span>
                            <span class="seat-type-tag" aria-hidden="true"><?= $s_tag ?></span>
                        </span>
                        <span class="seat-armrest seat-armrest-right" aria-hidden="true"></span>
                    </span>
                </div>
            </div>
            <?php
        } else {
            $is_bk = isset($booked[$s_no]);
            $is_selected = ($s_no === $preselected && !$is_bk);
            $aria_state = $is_bk ? 'booked' : 'available';
            ?>
            <div class="seat-box">
                <input type="radio" name="seat" id="seat-<?= $s_no ?>" value="<?= $s_no ?>" class="seat-radio"
                    data-seat-type="<?= e($s_type) ?>"
                    aria-label="Seat <?= $s_no ?>, <?= e($s_type) ?>, <?= $aria_state ?>"
                    <?= $is_bk ? 'disabled' : 'required' ?>
                    <?= $is_selected ? 'checked' : '' ?>>
                <label for="seat-<?= $s_no ?>" class="seat-label" title="Seat <?= $s_no ?> (<?= e($s_type) ?> Seat - <?= ucfirst($aria_state) ?>)">
                    <!-- Real Headrest Pillow -->
                    <span class="seat-headrest" aria-hidden="true"></span>

                    <!-- Realistic Chair Body with Armrests -->
                    <span class="seat-body-wrap">
                        <span class="seat-armrest seat-armrest-left" aria-hidden="true"></span>
                        <span class="seat-cushion">
                            <span class="seat-corner-icon" aria-hidden="true"></span>
                            <span class="seat-num"><?= $s_no ?></span>
                            <span class="seat-type-tag" aria-hidden="true"><?= $s_tag ?></span>
                        </span>
                        <span class="seat-armrest seat-armrest-right" aria-hidden="true"></span>
                    </span>
                </label>
            </div>
            <?php
        }
    };
    ?>
    <?php if ($admin_mode): ?>
    <div class="bus-map-card" role="region" aria-label="Bus Seat Occupancy Map" style="--left: <?= $left ?>; --right: <?= $right ?>;">
    <?php else: ?>
    <fieldset class="bus-map-card" style="--left: <?= $left ?>; --right: <?= $right ?>;">
        <legend class="seat-legend-title sr-only">Select your seat</legend>
    <?php endif; ?>

        <!-- Exterior Coach Side Mirrors -->
        <span class="bus-side-mirror bus-mirror-left" aria-hidden="true"></span>
        <span class="bus-side-mirror bus-mirror-right" aria-hidden="true"></span>

        <!-- Coach Windshield & Front Bumper -->
        <div class="bus-windshield" aria-hidden="true">
            <div class="bus-windshield-glare"></div>
            <div class="bus-windshield-wipers">
                <span class="wiper wiper-left"></span>
                <span class="wiper wiper-right"></span>
            </div>
            <div class="bus-front-cap">
                <span class="headlight headlight-left"></span>
                <span class="bus-front-badge">FRONT &bull; WINDSHIELD</span>
                <span class="headlight headlight-right"></span>
            </div>
        </div>

        <!-- Driver Cabin & Passenger Door Header -->
        <div class="bus-map-header">
            <div class="bus-driver-station" title="Driver Cabin">
                <svg class="bus-steering-wheel" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"></circle>
                    <circle cx="12" cy="12" r="3.2"></circle>
                    <line x1="12" y1="2" x2="12" y2="8.8"></line>
                    <line x1="4.5" y1="16.5" x2="9.5" y2="13.5"></line>
                    <line x1="19.5" y1="16.5" x2="14.5" y2="13.5"></line>
                </svg>
                <span class="bus-driver-label">Driver</span>
            </div>
            <div class="bus-door-station" title="Passenger Entry Door">
                <span class="bus-door-label">Door &rarr;</span>
                <svg class="bus-door-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="3" width="16" height="18" rx="2" ry="2"></rect>
                    <line x1="10" y1="3" x2="10" y2="21"></line>
                    <circle cx="7" cy="12" r="1" fill="currentColor"></circle>
                </svg>
            </div>
        </div>

        <div class="bus-dash-divider" aria-hidden="true"></div>

        <!-- Passenger Cabin & Realistic Seats Grid -->
        <div class="bus-seats-grid" id="seat-grid"
             data-bus="<?= $bus_attr ?>"
             data-date="<?= $date_attr ?>"
             data-time="<?= $time_attr ?>">
            <?php foreach ($rows as $row_idx => $row): ?>
                <div class="bus-row" data-row="<?= $row_idx + 1 ?>">
                    <?php
                    $seat_idx = 0;
                    for ($i = 0; $i < $left; $i++) {
                        $render_seat_box($row[$seat_idx++] ?? null);
                    }
                    ?>

                    <span class="bus-aisle-space" aria-hidden="true">
                        <span class="aisle-runner"></span>
                    </span>

                    <?php
                    for ($i = 0; $i < $right; $i++) {
                        $render_seat_box($row[$seat_idx++] ?? null);
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bus-dash-divider footer-divider" aria-hidden="true"></div>

        <!-- Bus Rear Lounge / Bumper -->
        <div class="bus-map-footer">
            <div class="bus-rear-indicator">
                <span class="taillight taillight-left" aria-hidden="true"></span>
                <span class="bus-rear-label">Rear</span>
                <span class="taillight taillight-right" aria-hidden="true"></span>
            </div>
        </div>
    <?php if ($admin_mode): ?>
    </div>
    <?php else: ?>
    </fieldset>
    <p id="seat-live" class="sr-only" role="status" aria-live="polite"></p>
    <?php endif; ?>

    <?php if ($admin_mode): ?>
        <?php
        $confirmed_cnt = (int)($meta['confirmed_count'] ?? 0);
        $pending_cnt = (int)($meta['pending_count'] ?? 0);
        $available_cnt = (int)($meta['available_count'] ?? 0);
        if ($confirmed_cnt === 0 && $pending_cnt === 0 && $available_cnt === 0) {
            foreach ($rows as $r_row) {
                foreach ($r_row as $st) {
                    if ($st === null) continue;
                    $s_num = (int)$st['no'];
                    $st_stat = $status_map[$s_num] ?? ((isset($booked[$s_num]) || in_array($s_num, $booked, true)) ? 'Confirmed' : 'Available');
                    if ($st_stat === 'Confirmed') $confirmed_cnt++;
                    elseif ($st_stat === 'Pending') $pending_cnt++;
                    else $available_cnt++;
                }
            }
        }
        ?>
        <!-- Legend with Realistic Seat Swatches (Admin Occupancy Visualizer) -->
        <div class="bus-legend" aria-hidden="true">
            <div class="bus-legend-item">
                <span class="bus-legend-seat-icon legend-confirmed">
                    <span class="mini-headrest"></span>
                    <span class="mini-cushion">&#10005;</span>
                </span>
                <span>Confirmed (<?= $confirmed_cnt ?>)</span>
            </div>
            <div class="bus-legend-item">
                <span class="bus-legend-seat-icon legend-pending">
                    <span class="mini-headrest"></span>
                    <span class="mini-cushion">&#23F3;</span>
                </span>
                <span>Pending Hold (<?= $pending_cnt ?>)</span>
            </div>
            <div class="bus-legend-item">
                <span class="bus-legend-seat-icon legend-available">
                    <span class="mini-headrest"></span>
                    <span class="mini-cushion"></span>
                </span>
                <span>Available (<?= $available_cnt ?>)</span>
            </div>
            <div class="bus-legend-item bus-legend-types">
                <span class="bus-legend-type-pill"><strong>W</strong> Window</span>
                <span class="bus-legend-type-pill"><strong>A</strong> Aisle</span>
            </div>
        </div>
    <?php else: ?>
        <!-- Legend with Realistic Seat Swatches -->
        <div class="bus-legend" aria-hidden="true">
            <div class="bus-legend-item">
                <span class="bus-legend-seat-icon legend-available">
                    <span class="mini-headrest"></span>
                    <span class="mini-cushion"></span>
                </span>
                <span>Available</span>
            </div>
            <div class="bus-legend-item">
                <span class="bus-legend-seat-icon legend-selected">
                    <span class="mini-headrest"></span>
                    <span class="mini-cushion">&#10003;</span>
                </span>
                <span>Selected</span>
            </div>
            <div class="bus-legend-item">
                <span class="bus-legend-seat-icon legend-booked">
                    <span class="mini-headrest"></span>
                    <span class="mini-cushion">&#10005;</span>
                </span>
                <span>Booked</span>
            </div>
            <div class="bus-legend-item bus-legend-types">
                <span class="bus-legend-type-pill"><strong>W</strong> Window</span>
                <span class="bus-legend-type-pill"><strong>A</strong> Aisle</span>
            </div>
        </div>
    <?php endif; ?>
    <?php
}
