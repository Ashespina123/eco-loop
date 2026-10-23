<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();
$userId = (int) $user['id'];
$pageTitle = 'Rewards | Eco-Loop';
$balance = getPointsBalance($userId);
$rewards = db()->query('SELECT id, name, description, points_required, stock, image_path FROM rewards WHERE is_active = 1 ORDER BY points_required, name')->fetch_all(MYSQLI_ASSOC);
$historyStmt = db()->prepare('SELECT rr.id, rr.points_used, rr.status, rr.quantity, rr.created_at, r.name AS reward_name FROM reward_redemptions rr JOIN rewards r ON r.id = rr.reward_id WHERE rr.user_id = ? ORDER BY rr.created_at DESC, rr.id DESC LIMIT 20');
$historyStmt->bind_param('i', $userId);
$historyStmt->execute();
$redemptions = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historyStmt->close();
$earnedStmt = db()->prepare('SELECT v.id, v.total_points, v.phone_value_total, v.created_at, p.reference_number, (SELECT GROUP_CONCAT(CONCAT(i.item_category, " × ", i.quantity, " · ", i.subtotal_points, " pts") ORDER BY i.id SEPARATOR "; ") FROM pickup_verified_items i WHERE i.verification_id = v.id) AS items FROM pickup_verifications v JOIN pickup_requests p ON p.id = v.pickup_request_id WHERE p.user_id = ? ORDER BY v.created_at DESC, v.id DESC LIMIT 30');
$earnedStmt->bind_param('i', $userId);
$earnedStmt->execute();
$verifiedEwaste = $earnedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$earnedStmt->close();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div><span class="eyebrow">Rewards</span><h1>Rewards Catalog</h1></div>
            <div class="stat-card"><span>Available points</span><strong><?php echo e($balance); ?></strong></div>
        </div>
        <section class="content-card">
            <div class="section-heading"><div><h2>Available rewards</h2><p>Point costs and stock are set by EcoLoop administrators.</p></div><a href="#redemption-history" class="btn btn-secondary">Redemption History</a></div>
            <?php if ($rewards): ?>
                <div class="reward-grid">
                    <?php foreach ($rewards as $reward): ?>
                        <article class="reward-card">
                            <?php if ($reward['image_path']): ?><img src="<?php echo e(APP_ROOT . '/' . ltrim($reward['image_path'], '/')); ?>" alt="<?php echo e($reward['name']); ?>" loading="lazy"><?php endif; ?>
                            <h3><?php echo e($reward['name']); ?></h3>
                            <p><?php echo e($reward['description'] ?: ''); ?></p>
                            <div class="reward-cost"><?php echo e(number_format((int) $reward['points_required'])); ?> points</div>
                            <p><?php echo (int) $reward['stock'] > 0 ? e($reward['stock'] . ' available') : 'Out of stock'; ?></p>
                            <button type="button" class="btn btn-primary" data-open-redemption data-reward-id="<?php echo (int) $reward['id']; ?>" data-reward-name="<?php echo e($reward['name']); ?>" data-reward-cost="<?php echo (int) $reward['points_required']; ?>" data-points-balance="<?php echo (int) $balance; ?>" data-stock="<?php echo (int) $reward['stock']; ?>" <?php echo (int) $reward['stock'] < 1 ? 'disabled' : ''; ?>>Redeem</button>
                            <?php if ($balance < (int) $reward['points_required'] && (int) $reward['stock'] > 0): ?><small>You need <?php echo e((int) $reward['points_required'] - $balance); ?> more points.</small><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><h3>No rewards configured</h3><p>Available rewards will appear here after an administrator adds them.</p></div>
            <?php endif; ?>
        </section>

        <section class="content-card" style="margin-top:20px"><div class="section-heading"><div><span class="eyebrow">Verified e-waste</span><h2>Points earned from collected items</h2></div></div>
            <?php if ($verifiedEwaste): ?><div class="workflow-card-grid"><?php foreach ($verifiedEwaste as $entry): ?><article class="pickup-card"><h3><?php echo e($entry['reference_number']); ?></h3><p><?php echo e($entry['items'] ?: 'Verified item receipt'); ?></p><p><strong><?php echo (int) $entry['total_points']; ?> points</strong></p><?php if ((float) $entry['phone_value_total'] > 0): ?><p>Phone valuation (separate): ₱<?php echo e(number_format((float) $entry['phone_value_total'], 2)); ?></p><?php endif; ?><small><?php echo e(date('M j, Y g:i A', strtotime($entry['created_at']))); ?></small></article><?php endforeach; ?></div><?php else: ?><p class="muted-text">Points are added here after an administrator verifies e-waste received from a pickup.</p><?php endif; ?>
        </section>

        <section class="content-card" id="redemption-history" style="margin-top:20px">
            <div class="section-heading"><div><span class="eyebrow">Your requests</span><h2>Redemption History</h2></div><a href="<?php echo e(APP_ROOT . '/user/redemptions.php'); ?>">Open full history</a></div>
            <?php if ($redemptions): ?>
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Redemption ID</th><th>Reward</th><th>Points used</th><th>Date</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($redemptions as $redemption): ?>
                        <tr><td>#<?php echo e($redemption['id']); ?></td><td><?php echo e($redemption['reward_name']); ?></td><td><?php echo e($redemption['points_used']); ?></td><td><?php echo e(date('M j, Y g:i A', strtotime($redemption['created_at']))); ?></td><td><span class="status-pill status-<?php echo e(strtolower(str_replace(' ', '-', $redemption['status']))); ?>"><?php echo e($redemption['status']); ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php else: ?><p class="muted-text">You have not redeemed a reward yet.</p><?php endif; ?>
        </section>
    </main>
</div>

<dialog id="redemption-modal" class="workflow-modal">
    <form id="redemption-form" class="modal-card" method="post" action="<?php echo e(APP_ROOT . '/user/redeem-reward.php'); ?>">
        <div class="modal-heading"><div><span class="eyebrow">Confirm redemption</span><h2>Redeem Reward</h2></div><button type="button" class="modal-close" data-close-redemption aria-label="Close">×</button></div>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="reward_id"><input type="hidden" name="request_token">
        <div id="redemption-form-message" class="alert alert-error" role="alert" hidden></div>
        <div class="content-card"><p><strong>Reward:</strong> <span id="redemption-reward-name"></span></p><p><strong>Points required:</strong> <span id="redemption-cost"></span></p><p><strong>Current balance:</strong> <span id="redemption-balance"></span></p><p><strong>Balance after redemption:</strong> <span id="redemption-remaining"></span></p></div>
        <div class="modal-actions"><button type="button" class="btn btn-outline" data-close-redemption>Cancel</button><button type="submit" class="btn btn-primary" id="submit-redemption">Confirm redemption</button></div>
    </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php'; ?>
