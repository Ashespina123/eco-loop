<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$admin = currentUser();
$pageTitle = 'Customers | Eco-Loop';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'status');
    if ($action === 'adjust_points') {
        $customerId = filter_var($_POST['customer_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $points = filter_var($_POST['points'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $connection = db();
        try {
            if ($customerId < 1 || $points === false || $points === 0 || abs($points) > 1000000 || $reason === '' || mb_strlen($reason) > 1000) throw new RuntimeException('Enter a nonzero adjustment (within 1,000,000 points) and a reason of up to 1,000 characters.');
            $connection->begin_transaction();
            $lock = $connection->prepare('SELECT id FROM users WHERE id=? AND role="user" FOR UPDATE'); $lock->bind_param('i', $customerId); $lock->execute(); $exists = $lock->get_result()->fetch_assoc(); $lock->close();
            if (!$exists) throw new RuntimeException('Customer not found.');
            $balance = getPointsBalance($customerId);
            if ($balance + $points < 0) throw new RuntimeException('This adjustment would make the customer balance negative.');
            $type = 'adjustment'; $adminId = (int) $admin['id']; $note = 'Administrator adjustment: ' . $reason;
            $ledger = $connection->prepare('INSERT INTO points_transactions (user_id,points,transaction_type,notes,created_by) VALUES (?,?,?, ?, ?)'); $ledger->bind_param('iissi', $customerId, $points, $type, $note, $adminId); $ledger->execute(); $ledger->close();
            logAdminAction($adminId, 'Customer points adjusted', 'Customer #' . $customerId . ': ' . ($points > 0 ? '+' : '') . $points . ' points. Reason: ' . $reason);
            createNotification($customerId, 'Points balance adjusted', 'An administrator adjusted your points by ' . ($points > 0 ? '+' : '') . $points . '. Reason: ' . $reason, 'info');
            $connection->commit(); setFlash('success', 'Points adjustment recorded in the ledger.');
        } catch (RuntimeException $exception) { $connection->rollback(); setFlash('error', $exception->getMessage()); } catch (Throwable $exception) { $connection->rollback(); error_log('EcoLoop points adjustment failed: ' . $exception->getMessage()); setFlash('error', 'The points adjustment could not be saved.'); }
        redirect('admin/users.php?id=' . $customerId);
    }
    $customerId = filter_var($_POST['customer_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $status = (string) ($_POST['status'] ?? '');
    if ($customerId > 0 && in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $stmt = db()->prepare('UPDATE users SET status=? WHERE id=? AND role="user"');
        $stmt->bind_param('si', $status, $customerId);
        $stmt->execute();
        if ($stmt->affected_rows || $stmt->errno === 0) logAdminAction((int) $admin['id'], 'Customer account status changed', 'Customer #' . $customerId . ' status set to ' . $status);
        $stmt->close();
        setFlash('success', 'Customer account status updated.');
    } else setFlash('error', 'Choose a valid customer account status.');
    redirect('admin/users.php');
}
$search = trim((string) ($_GET['q'] ?? ''));
$searchLike = '%' . $search . '%';
$stmt = db()->prepare('SELECT u.id,u.full_name,u.email,u.mobile_number,u.address,u.latitude,u.longitude,u.status,u.created_at,
 (SELECT COUNT(*) FROM pickup_requests p WHERE p.user_id=u.id AND p.status="Completed") completed_count,
 (SELECT COALESCE(SUM(points),0) FROM points_transactions t WHERE t.user_id=u.id) balance,
 (SELECT COALESCE(SUM(points),0) FROM points_transactions t WHERE t.user_id=u.id AND t.transaction_type="earned") earned,
 (SELECT COALESCE(SUM(-points),0) FROM points_transactions t WHERE t.user_id=u.id AND t.transaction_type="redeemed") redeemed
 FROM users u WHERE u.role="user" AND (?="" OR CAST(u.id AS CHAR)=? OR u.full_name LIKE ? OR u.email LIKE ? OR u.mobile_number LIKE ?) ORDER BY u.created_at DESC LIMIT 200');
$stmt->bind_param('sssss', $search, $search, $searchLike, $searchLike, $searchLike);
$stmt->execute(); $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$selected = null; $history = [];
if ($selectedId) {
    $detail = db()->prepare('SELECT id,full_name,email,mobile_number,address,latitude,longitude,status,created_at FROM users WHERE id=? AND role="user"');
    $detail->bind_param('i', $selectedId); $detail->execute(); $selected = $detail->get_result()->fetch_assoc(); $detail->close();
    if ($selected) {
        $historyStmt = db()->prepare('SELECT id,reference_number,status,service_type,service_fee,created_at FROM pickup_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
        $historyStmt->bind_param('i', $selectedId); $historyStmt->execute(); $history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC); $historyStmt->close();
        $ledgerStmt = db()->prepare('SELECT points,transaction_type,notes,created_at FROM points_transactions WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 100'); $ledgerStmt->bind_param('i', $selectedId); $ledgerStmt->execute(); $ledger = $ledgerStmt->get_result()->fetch_all(MYSQLI_ASSOC); $ledgerStmt->close();
        $redemptionStmt = db()->prepare('SELECT rr.id,rr.points_used,rr.status,rr.created_at,r.name reward_name FROM reward_redemptions rr JOIN rewards r ON r.id=rr.reward_id WHERE rr.user_id=? ORDER BY rr.created_at DESC LIMIT 100'); $redemptionStmt->bind_param('i', $selectedId); $redemptionStmt->execute(); $customerRedemptions = $redemptionStmt->get_result()->fetch_all(MYSQLI_ASSOC); $redemptionStmt->close();
        $addressStmt = db()->prepare('SELECT address,latitude,longitude,is_primary FROM user_addresses WHERE user_id=? ORDER BY is_primary DESC,id');
        $addressStmt->bind_param('i', $selectedId); $addressStmt->execute(); $addresses = $addressStmt->get_result()->fetch_all(MYSQLI_ASSOC); $addressStmt->close();
        $selected['saved_addresses'] = $addresses;
    }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell"><?php require __DIR__ . '/../includes/navigation.php'; ?><main class="main-content">
<div class="page-header"><div><span class="eyebrow">Administration</span><h1>Customers</h1></div></div>
<?php if ($selected): ?><section class="content-card"><div class="section-heading"><h2><?php echo e($selected['full_name']); ?></h2><a class="btn btn-outline" href="<?php echo e(APP_ROOT . '/admin/users.php'); ?>">Back to customers</a></div>
<p><?php echo e($selected['email']); ?> · <?php echo e($selected['mobile_number']); ?> · Account <?php echo e($selected['status']); ?> · Joined <?php echo e(date('M j, Y', strtotime($selected['created_at']))); ?></p>
<h3>Saved addresses</h3><?php if ($selected['saved_addresses']): ?><ul><?php foreach ($selected['saved_addresses'] as $address): ?><li><?php echo e($address['address']); ?><?php if ($address['latitude'] !== null && $address['longitude'] !== null): ?> · <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?php echo rawurlencode($address['latitude'] . ',' . $address['longitude']); ?>">Map pin (<?php echo e($address['latitude']); ?>, <?php echo e($address['longitude']); ?>)</a><?php endif; ?></li><?php endforeach; ?></ul><?php else: ?><p><?php echo e($selected['address'] ?: 'No saved address.'); ?></p><?php endif; ?>
<h3>Pickup history</h3><?php if ($history): ?><div class="table-wrap"><table><thead><tr><th>Request</th><th>Service / Fee</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($history as $row): ?><tr><td><?php echo e($row['reference_number']); ?></td><td><?php echo e($row['service_type']); ?> · <?php echo e(formatCurrency((float) $row['service_fee'])); ?></td><td><?php echo e($row['status']); ?></td><td><?php echo e(date('M j, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p>No pickup history.</p><?php endif; ?>
<h3>Points ledger</h3><form method="post" class="request-grid"><?php echo csrf_field(); ?><input type="hidden" name="action" value="adjust_points"><input type="hidden" name="customer_id" value="<?php echo (int) $selectedId; ?>"><div class="field-group"><label>Point adjustment (+/-)</label><input type="number" name="points" min="-1000000" max="1000000" required></div><div class="field-group"><label>Reason (required)</label><input name="reason" maxlength="1000" required></div><div class="full-span"><button class="btn btn-secondary">Record adjustment</button></div></form><?php if ($ledger): ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Points</th><th>Details</th></tr></thead><tbody><?php foreach ($ledger as $tx): ?><tr><td><?php echo e(date('M j, Y g:i A', strtotime($tx['created_at']))); ?></td><td><?php echo e($tx['transaction_type']); ?></td><td><?php echo (int) $tx['points']; ?></td><td><?php echo e($tx['notes']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p>No points activity.</p><?php endif; ?>
<h3>Redemptions</h3><?php if ($customerRedemptions): ?><div class="table-wrap"><table><thead><tr><th>ID</th><th>Reward</th><th>Points</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($customerRedemptions as $redemption): ?><tr><td>#<?php echo (int) $redemption['id']; ?></td><td><?php echo e($redemption['reward_name']); ?></td><td><?php echo (int) $redemption['points_used']; ?></td><td><?php echo e($redemption['status']); ?></td><td><?php echo e(date('M j, Y', strtotime($redemption['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p>No reward redemptions.</p><?php endif; ?></section><?php endif; ?>
<section class="content-card"><form method="get" class="inline-form"><label for="customer-search">Search name, email, phone, or customer ID</label><input id="customer-search" name="q" value="<?php echo e($search); ?>"><button class="btn btn-secondary">Search</button></form>
<?php if ($customers): ?><div class="table-wrap"><table><thead><tr><th>Customer</th><th>Contact</th><th>Completed</th><th>Points balance</th><th>Earned / redeemed</th><th>Joined</th><th>Account</th></tr></thead><tbody><?php foreach ($customers as $customer): ?><tr><td><a href="<?php echo e(APP_ROOT . '/admin/users.php?id=' . (int) $customer['id']); ?>"><strong><?php echo e($customer['full_name']); ?></strong></a><br><small>#<?php echo (int) $customer['id']; ?> · <?php echo e($customer['email']); ?></small></td><td><?php echo e($customer['mobile_number']); ?></td><td><?php echo (int) $customer['completed_count']; ?></td><td><?php echo (int) $customer['balance']; ?></td><td><?php echo (int) $customer['earned']; ?> / <?php echo (int) $customer['redeemed']; ?></td><td><?php echo e(date('M j, Y', strtotime($customer['created_at']))); ?></td><td><form method="post" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="customer_id" value="<?php echo (int) $customer['id']; ?>"><select name="status" aria-label="Account status"><?php foreach (['active','inactive','suspended'] as $status): ?><option value="<?php echo e($status); ?>" <?php echo $customer['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option><?php endforeach; ?></select><button class="btn btn-secondary" type="submit">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><p>No customers match this search.</p></div><?php endif; ?></section>
</main></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
