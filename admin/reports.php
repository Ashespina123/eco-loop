<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pageTitle = 'Reports | Eco-Loop';
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$validDate = static function (string $value): bool { $date = DateTime::createFromFormat('Y-m-d', $value); return $date && $date->format('Y-m-d') === $value; };
if (($from !== '' && !$validDate($from)) || ($to !== '' && !$validDate($to)) || ($from !== '' && $to !== '' && $from > $to)) { $from = ''; $to = ''; setFlash('error', 'Use a valid date range.'); }
$start = $from === '' ? '2000-01-01 00:00:00' : $from . ' 00:00:00';
$endDate = $to === '' ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d', strtotime($to . ' +1 day'));
$end = $endDate . ' 00:00:00';
$connection = db();
$summaryStmt = $connection->prepare('SELECT COUNT(*) total, SUM(status="Completed") completed, SUM(status="Pending") pending FROM pickup_requests WHERE created_at>=? AND created_at<?');
$summaryStmt->bind_param('ss', $start, $end); $summaryStmt->execute(); $summary = $summaryStmt->get_result()->fetch_assoc(); $summaryStmt->close();
$pointsStmt = $connection->prepare('SELECT COALESCE(SUM(points),0) total FROM points_transactions WHERE transaction_type="earned" AND created_at>=? AND created_at<?');
$pointsStmt->bind_param('ss', $start, $end); $pointsStmt->execute(); $points = (int) $pointsStmt->get_result()->fetch_assoc()['total']; $pointsStmt->close();
$redemptionStmt = $connection->prepare('SELECT COUNT(*) total, COALESCE(SUM(points_used),0) points_used FROM reward_redemptions WHERE created_at>=? AND created_at<?');
$redemptionStmt->bind_param('ss', $start, $end); $redemptionStmt->execute(); $redemptions = $redemptionStmt->get_result()->fetch_assoc(); $redemptionStmt->close();
$categoryStmt = $connection->prepare('SELECT vi.item_category, SUM(vi.quantity) quantity, SUM(vi.subtotal_points) points FROM pickup_verified_items vi JOIN pickup_verifications v ON v.id=vi.verification_id JOIN pickup_requests p ON p.id=v.pickup_request_id WHERE v.created_at>=? AND v.created_at<? GROUP BY vi.item_category ORDER BY quantity DESC');
$categoryStmt->bind_param('ss', $start, $end); $categoryStmt->execute(); $categories = $categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC); $categoryStmt->close();
$trendStmt = $connection->prepare('SELECT DATE(created_at) day, COUNT(*) requests, SUM(status="Completed") completed FROM pickup_requests WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 90');
$trendStmt->bind_param('ss', $start, $end); $trendStmt->execute(); $trend = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC); $trendStmt->close();
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="ecoloop-report-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['Category', 'Verified quantity', 'Points awarded']);
    foreach ($categories as $row) fputcsv($out, [$row['item_category'], $row['quantity'], $row['points']]);
    fclose($out); exit;
}
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell"><?php require __DIR__ . '/../includes/navigation.php'; ?><main class="main-content">
<div class="page-header"><div><span class="eyebrow">Operations</span><h1>Reports</h1></div></div>
<section class="content-card"><form method="get" class="inline-form"><label>From <input type="date" name="from" value="<?php echo e($from); ?>"></label><label>To <input type="date" name="to" value="<?php echo e($to); ?>"></label><button class="btn btn-secondary">Apply dates</button><a class="btn btn-outline" href="<?php echo e(APP_ROOT . '/admin/reports.php?from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&export=csv'); ?>">Export verified categories CSV</a></form></section>
<div class="stats-grid"><div class="stat-card"><span>Total pickup requests</span><strong><?php echo (int) $summary['total']; ?></strong></div><div class="stat-card"><span>Completed collections</span><strong><?php echo (int) $summary['completed']; ?></strong></div><div class="stat-card"><span>Pending requests</span><strong><?php echo (int) $summary['pending']; ?></strong></div><div class="stat-card"><span>Reward points issued</span><strong><?php echo $points; ?></strong></div><div class="stat-card"><span>Redemptions</span><strong><?php echo (int) $redemptions['total']; ?></strong></div><div class="stat-card"><span>Points redeemed</span><strong><?php echo (int) $redemptions['points_used']; ?></strong></div></div>
<section class="content-card"><h2>Verified e-waste collected</h2><?php if ($categories): ?><div class="table-wrap"><table><thead><tr><th>Item category</th><th>Quantity received</th><th>Points awarded</th></tr></thead><tbody><?php foreach ($categories as $row): ?><tr><td><?php echo e($row['item_category']); ?></td><td><?php echo (int) $row['quantity']; ?></td><td><?php echo (int) $row['points']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p>No verified item records for this period.</p><?php endif; ?></section>
<section class="content-card"><h2>Pickup activity by day</h2><?php if ($trend): ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Requests</th><th>Completed</th></tr></thead><tbody><?php foreach ($trend as $row): ?><tr><td><?php echo e($row['day']); ?></td><td><?php echo (int) $row['requests']; ?></td><td><?php echo (int) $row['completed']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p>No pickup activity for this period.</p><?php endif; ?></section>
</main></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
