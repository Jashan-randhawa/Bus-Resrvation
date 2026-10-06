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
    ?>
    <fieldset class="bus-map-card" style="--left: <?= $left ?>; --right: <?= $right ?>;">
        <legend class="seat-legend-title sr-only">Select your seat</legend>

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
                    // Left seat group
                    for ($i = 0; $i < $left; $i++):
                        $seat = $row[$seat_idx++] ?? null;
                        if ($seat === null): ?>
                            <span class="seat-box seat-empty" aria-hidden="true"></span>
                        <?php else:
                            $s_no = (int)$seat['no'];
                            $s_type = (string)$seat['type'];
                            $is_bk = isset($booked[$s_no]);
                            $is_selected = ($s_no === $preselected && !$is_bk);
                            $s_tag = ($s_type === 'Window') ? 'W' : 'A';
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
                        <?php endif;
                    endfor;
                    ?>

                    <span class="bus-aisle-space" aria-hidden="true">
                        <span class="aisle-runner"></span>
                    </span>

                    <?php
                    // Right seat group
                    for ($i = 0; $i < $right; $i++):
                        $seat = $row[$seat_idx++] ?? null;
                        if ($seat === null): ?>
                            <span class="seat-box seat-empty" aria-hidden="true"></span>
                        <?php else:
                            $s_no = (int)$seat['no'];
                            $s_type = (string)$seat['type'];
                            $is_bk = isset($booked[$s_no]);
                            $is_selected = ($s_no === $preselected && !$is_bk);
                            $s_tag = ($s_type === 'Window') ? 'W' : 'A';
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
                        <?php endif;
                    endfor;
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
    </fieldset>

    <p id="seat-live" class="sr-only" role="status" aria-live="polite"></p>

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
    <?php
}
