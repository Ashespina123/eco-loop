<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$user = currentUser();
$pageTitle = 'Rewards Management | Eco-Loop';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_settings') {
            $saverFee = filter_var($_POST['pickup_saver_fee'] ?? null, FILTER_VALIDATE_INT);
            $priorityFee = filter_var($_POST['pickup_priority_fee'] ?? null, FILTER_VALIDATE_INT);
            $priceFields = ['saver_rate_3_8', 'saver_rate_over_8', 'priority_rate_3_8', 'priority_rate_over_8'];
            $priceRates = [];
            foreach ($priceFields as $field) {
                $value = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT);
                if ($value === false || $value < 0 || $value > 100000) throw new RuntimeException('Enter whole number distance rates from 0 to 100,000.');
                $priceRates[$field] = $value;
            }
            if ($saverFee === false || $saverFee < 0 || $priorityFee === false || $priorityFee < 0 || $saverFee > 100000 || $priorityFee > 100000) throw new RuntimeException('Enter whole number service fees from 0 to 100,000.');
            $rewardRates = ['earphones' => [0, 100000], 'charger' => [0, 100000], 'phone' => [0, 100000], 'battery' => [0, 100000], 'motherboard' => [15, 20], 'keyboard' => [0, 100000], 'mouse' => [0, 100000]];
            $settingsToSave = ['pickup_saver_fee' => $saverFee, 'pickup_priority_fee' => $priorityFee] + $priceRates;
            foreach ($rewardRates as $key => [$minimum, $maximum]) {
                $rate = filter_var($_POST['reward_' . $key] ?? null, FILTER_VALIDATE_INT);
                if ($rate === false || $rate < $minimum || $rate > $maximum) throw new RuntimeException('Enter valid per-piece reward rates. Motherboard rewards must be 15 to 20 points.');
                $settingsToSave['reward_' . $key] = $rate;
            }
            $connection = db(); $connection->begin_transaction();
            try {
                $changes = [];
                foreach ($settingsToSave as $key => $value) {
                    $previous = getAppSetting($key, '');
                    setAppSetting($key, (string) $value, (int) $user['id']);
                    if ($previous !== (string) $value) $changes[] = $key . ': ' . ($previous === '' ? '(unset)' : $previous) . ' → ' . $value;
                }
                if ($changes) logAdminAction((int) $user['id'], 'Pricing and reward rules changed', implode('; ', $changes));
                $connection->commit();
            } catch (Throwable $exception) { $connection->rollback(); throw $exception; }
            setFlash('success', 'Service pricing and item reward rules saved.');
        } elseif ($action === 'save_reward') {
            $rewardId = filter_var($_POST['reward_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $cost = filter_var($_POST['points_required'] ?? null, FILTER_VALIDATE_INT);
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($name === '' || mb_strlen($name) > 150 || $cost === false || $cost < 1 || $stock === false || $stock < 0) throw new RuntimeException('Enter a reward name, a positive point cost, and nonnegative stock.');
            if ($rewardId > 0) {
                $stmt = db()->prepare('UPDATE rewards SET name = ?, description = ?, points_required = ?, stock = ?, is_active = ? WHERE id = ?');
                $stmt->bind_param('ssiiii', $name, $description, $cost, $stock, $active, $rewardId);
            } else {
                $stmt = db()->prepare('INSERT INTO rewards (name, description, points_required, stock, is_active) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('ssiii', $name, $description, $cost, $stock, $active);
            }
            $stmt->execute();
            $savedId = $rewardId > 0 ? $rewardId : (int) db()->insert_id;
            $stmt->close();
            logAdminAction((int) $user['id'], $rewardId > 0 ? 'Reward catalog item updated' : 'Reward catalog item added', 'Reward #' . $savedId . ' ' . $name . ', points=' . $cost . ', stock=' . $stock . ', active=' . $active);
            setFlash('success', 'Reward catalog item saved.');
        } else {
            throw new RuntimeException('Choose a valid action.');
        }
    } catch (RuntimeException $exception) {
        setFlash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('EcoLoop admin reward update failed: ' . $exception->getMessage());
        setFlash('error', 'The change could not be saved. Please try again.');
    }
    redirect('admin/rewards.php');
}

$settings = [
    'saver' => getAppSetting('pickup_saver_fee', '40'),
    'priority' => getAppSetting('pickup_priority_fee', '90'),
    'saver_rate_3_8' => getAppSetting('saver_rate_3_8', '12'), 'saver_rate_over_8' => getAppSetting('saver_rate_over_8', '20'),
    'priority_rate_3_8' => getAppSetting('priority_rate_3_8', '25'), 'priority_rate_over_8' => getAppSetting('priority_rate_over_8', '40'),
    'earphones' => getAppSetting('reward_earphones', '5'), 'charger' => getAppSetting('reward_charger', '10'),
    'phone' => getAppSetting('reward_phone', '0'), 'battery' => getAppSetting('reward_battery', '5'),
    'motherboard' => getAppSetting('reward_motherboard', '15'), 'keyboard' => getAppSetting('reward_keyboard', '15'),
    'mouse' => getAppSetting('reward_mouse', '10'),
];
$rewards = db()->query('SELECT * FROM rewards ORDER BY is_active DESC, points_required, name')->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header"><div><span class="eyebrow">Admin</span><h1>Rewards and Pickup Rules</h1></div></div>
        <section class="content-card">
            <h2>Service fees and earning rule</h2>
            <p>These server-side settings determine pickup fees and verified e-waste item rewards. Item points are only recorded by an administrator after collection.</p>
            <form method="post" class="request-grid">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_settings">
                <div class="field-group"><label for="pickup_saver_fee">Convenience (Saver) fee (₱)</label><input type="number" id="pickup_saver_fee" name="pickup_saver_fee" min="0" max="100000" value="<?php echo e($settings['saver']); ?>" required></div>
                <div class="field-group"><label for="pickup_priority_fee">Priority Collection fee (₱)</label><input type="number" id="pickup_priority_fee" name="pickup_priority_fee" min="0" max="100000" value="<?php echo e($settings['priority']); ?>" required></div>
                <div class="field-group"><label for="saver_rate_3_8">Saver per km, 3–8 km</label><input type="number" id="saver_rate_3_8" name="saver_rate_3_8" min="0" max="100000" value="<?php echo e($settings['saver_rate_3_8']); ?>" required></div>
                <div class="field-group"><label for="saver_rate_over_8">Saver per km, beyond 8 km</label><input type="number" id="saver_rate_over_8" name="saver_rate_over_8" min="0" max="100000" value="<?php echo e($settings['saver_rate_over_8']); ?>" required></div>
                <div class="field-group"><label for="priority_rate_3_8">Priority per km, 3–8 km</label><input type="number" id="priority_rate_3_8" name="priority_rate_3_8" min="0" max="100000" value="<?php echo e($settings['priority_rate_3_8']); ?>" required></div>
                <div class="field-group"><label for="priority_rate_over_8">Priority per km, beyond 8 km</label><input type="number" id="priority_rate_over_8" name="priority_rate_over_8" min="0" max="100000" value="<?php echo e($settings['priority_rate_over_8']); ?>" required></div>
                <div class="field-group"><label for="reward_earphones">Earphones (points per piece)</label><input type="number" id="reward_earphones" name="reward_earphones" min="0" max="100000" value="<?php echo e($settings['earphones']); ?>" required></div>
                <div class="field-group"><label for="reward_charger">Charger (points per piece)</label><input type="number" id="reward_charger" name="reward_charger" min="0" max="100000" value="<?php echo e($settings['charger']); ?>" required></div>
                <div class="field-group"><label for="reward_phone">Phone (approved points per piece; 0 means not configured)</label><input type="number" id="reward_phone" name="reward_phone" min="0" max="100000" value="<?php echo e($settings['phone']); ?>" required></div>
                <div class="field-group"><label for="reward_battery">Battery (points per piece)</label><input type="number" id="reward_battery" name="reward_battery" min="0" max="100000" value="<?php echo e($settings['battery']); ?>" required></div>
                <div class="field-group"><label for="reward_motherboard">Motherboard (15–20 points per piece)</label><input type="number" id="reward_motherboard" name="reward_motherboard" min="15" max="20" value="<?php echo e($settings['motherboard']); ?>" required></div>
                <div class="field-group"><label for="reward_keyboard">Keyboard (points per piece)</label><input type="number" id="reward_keyboard" name="reward_keyboard" min="0" max="100000" value="<?php echo e($settings['keyboard']); ?>" required></div>
                <div class="field-group"><label for="reward_mouse">Mouse (points per piece)</label><input type="number" id="reward_mouse" name="reward_mouse" min="0" max="100000" value="<?php echo e($settings['mouse']); ?>" required></div>
                <p class="muted-text full-span">The legacy per-completed-pickup points value remains stored for existing records but is no longer awarded. Phone ₱100–₱300 valuation is recorded separately during verification.</p>
                <div class="form-actions full-span"><button class="btn btn-primary" type="submit">Save rules</button></div>
            </form>
        </section>

        <section class="content-card" style="margin-top:20px">
            <h2>Add reward</h2>
            <form method="post" class="request-grid">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_reward"><input type="hidden" name="reward_id" value="0">
                <div class="field-group"><label>Reward name</label><input name="name" maxlength="150" required></div>
                <div class="field-group"><label>Points required</label><input type="number" name="points_required" min="1" required></div>
                <div class="field-group"><label>Stock</label><input type="number" name="stock" min="0" required></div>
                <div class="field-group"><label>Description</label><input name="description"></div>
                <label class="check-item"><input type="checkbox" name="is_active" checked> Available in customer catalog</label>
                <div class="form-actions full-span"><button class="btn btn-primary" type="submit">Add reward</button></div>
            </form>
        </section>

        <section class="content-card" style="margin-top:20px">
            <h2>Catalog and inventory</h2>
            <?php if ($rewards): ?><div class="workflow-card-grid">
                <?php foreach ($rewards as $reward): ?>
                    <form method="post" class="pickup-card request-grid">
                        <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_reward"><input type="hidden" name="reward_id" value="<?php echo (int) $reward['id']; ?>">
                        <div class="field-group"><label>Name</label><input name="name" value="<?php echo e($reward['name']); ?>" maxlength="150" required></div>
                        <div class="field-group"><label>Points</label><input type="number" name="points_required" min="1" value="<?php echo (int) $reward['points_required']; ?>" required></div>
                        <div class="field-group"><label>Stock</label><input type="number" name="stock" min="0" value="<?php echo (int) $reward['stock']; ?>" required></div>
                        <div class="field-group"><label>Description</label><input name="description" value="<?php echo e($reward['description']); ?>"></div>
                        <label class="check-item"><input type="checkbox" name="is_active" <?php echo (int) $reward['is_active'] ? 'checked' : ''; ?>> Active</label>
                        <div class="form-actions full-span"><button class="btn btn-secondary" type="submit">Save catalog item</button></div>
                    </form>
                <?php endforeach; ?>
            </div><?php else: ?><p>No reward items are configured.</p><?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
