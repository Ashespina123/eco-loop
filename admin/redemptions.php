<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$admin = currentUser();
$pageTitle = 'Reward Redemption Management | Eco-Loop';
$transitions = [
    'Pending' => ['Approved', 'Rejected'],
    'Approved' => ['Ready for Collection', 'Rejected'],
    'Ready for Collection' => ['Completed', 'Rejected'],
    'Completed' => [], 'Rejected' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $redemptionId = filter_input(INPUT_POST, 'redemption_id', FILTER_VALIDATE_INT) ?: 0;
    $newStatus = trim((string) ($_POST['status'] ?? ''));
    $connection = db();
    try {
        $connection->begin_transaction();
        $lock = $connection->prepare('SELECT rr.id, rr.user_id, rr.reward_id, rr.quantity, rr.points_used, rr.status, r.name AS reward_name FROM reward_redemptions rr JOIN rewards r ON r.id = rr.reward_id WHERE rr.id = ? FOR UPDATE');
        $lock->bind_param('i', $redemptionId);
        $lock->execute();
        $redemption = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$redemption || !in_array($newStatus, $transitions[$redemption['status']] ?? [], true)) throw new RuntimeException('That redemption status change is not allowed.');

        $update = $connection->prepare('UPDATE reward_redemptions SET status = ? WHERE id = ?');
        $update->bind_param('si', $newStatus, $redemptionId);
        $update->execute();
        $update->close();

        if ($newStatus === 'Rejected') {
            $debit = $connection->prepare('SELECT id FROM points_transactions WHERE related_redemption_id = ? AND transaction_type = "redeemed" LIMIT 1');
            $debit->bind_param('i', $redemptionId);
            $debit->execute();
            $hasDebit = $debit->get_result()->fetch_assoc();
            $debit->close();
            if ($hasDebit) {
                $refundExistsStmt = $connection->prepare('SELECT id FROM points_transactions WHERE related_redemption_id = ? AND transaction_type = "adjustment" LIMIT 1');
                $refundExistsStmt->bind_param('i', $redemptionId);
                $refundExistsStmt->execute();
                $refundExists = $refundExistsStmt->get_result()->fetch_assoc();
                $refundExistsStmt->close();
                if (!$refundExists && (int) $redemption['points_used'] > 0) {
                    $points = (int) $redemption['points_used'];
                    $type = 'adjustment';
                    $note = 'Points refunded after rejected redemption #' . $redemptionId;
                    $customerId = (int) $redemption['user_id'];
                    $adminId = (int) $admin['id'];
                    $refund = $connection->prepare('INSERT INTO points_transactions (user_id, points, transaction_type, related_redemption_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $refund->bind_param('iisisi', $customerId, $points, $type, $redemptionId, $note, $adminId);
                    $refund->execute();
                    $refund->close();
                }
            }
            $stock = $connection->prepare('UPDATE rewards SET stock = stock + ? WHERE id = ?');
            $stock->bind_param('ii', $redemption['quantity'], $redemption['reward_id']);
            $stock->execute();
            $stock->close();
        }
        createNotification((int) $redemption['user_id'], 'Reward redemption updated', 'Your request for ' . $redemption['reward_name'] . ' is now ' . $newStatus . ($newStatus === 'Rejected' ? '; eligible points have been refunded.' : '.'), $newStatus === 'Rejected' ? 'warning' : 'info', null, $redemptionId);
        logAdminAction((int) $admin['id'], 'Reward redemption status changed', 'Redemption #' . $redemptionId . ' for customer #' . $redemption['user_id'] . ': ' . $redemption['status'] . ' → ' . $newStatus . ' (' . $redemption['reward_name'] . ', ' . $redemption['points_used'] . ' points)');
        $connection->commit();
        setFlash('success', $newStatus === 'Rejected' ? 'Redemption rejected, eligible points refunded, and stock restored.' : 'Redemption status updated.');
    } catch (RuntimeException $exception) {
        $connection->rollback();
        setFlash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        $connection->rollback();
        error_log('EcoLoop admin redemption update failed: ' . $exception->getMessage());
        setFlash('error', 'The redemption could not be updated. Please try again.');
    }
    redirect('admin/redemptions.php');
}

$rows = db()->query('SELECT rr.id, rr.user_id, rr.reward_id, rr.quantity, rr.points_used, rr.status, rr.created_at, u.full_name, u.email, r.name AS reward_name FROM reward_redemptions rr JOIN users u ON u.id = rr.user_id JOIN rewards r ON r.id = rr.reward_id ORDER BY rr.created_at DESC, rr.id DESC')->fetch_all(MYSQLI_ASSOC);
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell">
    <?php require __DIR__ . '/../includes/navigation.php'; ?>
    <main class="main-content">
        <div class="page-header"><div><span class="eyebrow">Admin</span><h1>Reward Redemption Management</h1></div></div>
        <?php if ($rows): ?><div class="workflow-card-grid">
            <?php foreach ($rows as $row): ?>
                <article class="pickup-card">
                    <div class="pickup-card-header"><div><h3>Redemption #<?php echo e($row['id']); ?> · <?php echo e($row['reward_name']); ?></h3><p><?php echo e($row['full_name']); ?> · <?php echo e($row['email']); ?></p></div><span class="status-pill status-<?php echo e(strtolower(str_replace(' ', '-', $row['status']))); ?>"><?php echo e($row['status']); ?></span></div>
                    <p><?php echo e($row['points_used']); ?> points · quantity <?php echo e($row['quantity']); ?> · <?php echo e(date('M j, Y g:i A', strtotime($row['created_at']))); ?></p>
                    <?php $options = $transitions[$row['status']] ?? []; ?>
                    <?php if ($options): ?><form method="post" class="pickup-card-actions"><?php echo csrf_field(); ?><input type="hidden" name="redemption_id" value="<?php echo (int) $row['id']; ?>"><select name="status" aria-label="Update redemption status"><?php foreach ($options as $option): ?><option value="<?php echo e($option); ?>"><?php echo e($option); ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Update</button></form><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div><?php else: ?><div class="content-card empty-state"><h2>No redemption requests</h2><p>Customer redemption requests will appear here.</p></div><?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
