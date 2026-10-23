<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();
$pageTitle = 'Redemption History | Eco-Loop';
$stmt = db()->prepare('SELECT rr.id, rr.points_used, rr.quantity, rr.status, rr.notes, rr.created_at, r.name AS reward_name FROM reward_redemptions rr JOIN rewards r ON r.id = rr.reward_id WHERE rr.user_id = ? ORDER BY rr.created_at DESC, rr.id DESC');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header"><div><span class="eyebrow">Rewards</span><h1>Redemption History</h1></div><a href="<?php echo e(APP_ROOT . '/user/rewards.php'); ?>" class="btn btn-primary">Browse Rewards</a></div>
        <div class="content-card">
            <?php if ($rows): ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Redemption ID</th><th>Reward</th><th>Points deducted</th><th>Date</th><th>Status</th><th>Notes</th></tr></thead><tbody>
                <?php foreach ($rows as $row): ?><tr><td>#<?php echo e($row['id']); ?></td><td><?php echo e($row['reward_name']); ?><?php echo (int) $row['quantity'] > 1 ? ' × ' . e($row['quantity']) : ''; ?></td><td><?php echo e($row['points_used']); ?></td><td><?php echo e(date('M j, Y g:i A', strtotime($row['created_at']))); ?></td><td><span class="status-pill status-<?php echo e(strtolower(str_replace(' ', '-', $row['status']))); ?>"><?php echo e($row['status']); ?></span></td><td><?php echo e($row['notes'] ?: '—'); ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php else: ?><div class="empty-state"><h2>No redemptions yet</h2><p>Your reward redemptions will appear here.</p></div><?php endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
