<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
header('Content-Type: application/json; charset=utf-8');

function redemptionJson(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    redemptionJson(405, ['ok' => false, 'message' => 'Use the rewards form to submit a redemption.']);
}
if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) redemptionJson(419, ['ok' => false, 'message' => 'This form expired. Reload the page and try again.']);

$user = currentUser();
$userId = (int) $user['id'];
$rewardId = filter_var($_POST['reward_id'] ?? null, FILTER_VALIDATE_INT);
$token = strtolower(trim((string) ($_POST['request_token'] ?? '')));
if (!$rewardId || !preg_match('/^[a-f0-9]{64}$/', $token)) redemptionJson(422, ['ok' => false, 'message' => 'Choose a valid reward and try again.']);

$connection = db();
try {
    $connection->begin_transaction();
    $userLock = $connection->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $userLock->bind_param('i', $userId);
    $userLock->execute();
    $userExists = $userLock->get_result()->fetch_assoc();
    $userLock->close();
    if (!$userExists) throw new RuntimeException('Customer account was not found.');

    $prior = $connection->prepare('SELECT id, points_used, status FROM reward_redemptions WHERE request_token = ? AND user_id = ? LIMIT 1');
    $prior->bind_param('si', $token, $userId);
    $prior->execute();
    $existing = $prior->get_result()->fetch_assoc();
    $prior->close();
    if ($existing) {
        $connection->commit();
        setFlash('success', 'Your reward redemption is already recorded with status ' . $existing['status'] . '.');
        redemptionJson(200, ['ok' => true, 'duplicate' => true, 'id' => (int) $existing['id'], 'points_used' => (int) $existing['points_used'], 'status' => $existing['status']]);
    }

    $rewardStmt = $connection->prepare('SELECT id, name, points_required, stock, is_active FROM rewards WHERE id = ? FOR UPDATE');
    $rewardStmt->bind_param('i', $rewardId);
    $rewardStmt->execute();
    $reward = $rewardStmt->get_result()->fetch_assoc();
    $rewardStmt->close();
    if (!$reward || !(int) $reward['is_active']) throw new RuntimeException('This reward is not currently available.');
    if ((int) $reward['stock'] < 1) throw new RuntimeException('This reward is out of stock.');

    $balance = getPointsBalance($userId);
    $cost = (int) $reward['points_required'];
    if ($balance < $cost) {
        $needed = $cost - $balance;
        $connection->rollback();
        redemptionJson(409, ['ok' => false, 'message' => 'You need ' . $needed . ' more point' . ($needed === 1 ? '' : 's') . ' to redeem this reward.', 'balance' => $balance, 'required' => $cost]);
    }

    $stock = $connection->prepare('UPDATE rewards SET stock = stock - 1 WHERE id = ? AND is_active = 1 AND stock > 0');
    $stock->bind_param('i', $rewardId);
    $stock->execute();
    if ($stock->affected_rows !== 1) throw new RuntimeException('This reward is no longer in stock.');
    $stock->close();

    $quantity = 1;
    $status = 'Pending';
    $redemption = $connection->prepare('INSERT INTO reward_redemptions (user_id, reward_id, quantity, points_used, status, request_token) VALUES (?, ?, ?, ?, ?, ?)');
    $redemption->bind_param('iiiiss', $userId, $rewardId, $quantity, $cost, $status, $token);
    $redemption->execute();
    $redemptionId = (int) $redemption->insert_id;
    $redemption->close();

    $points = -$cost;
    $type = 'redeemed';
    $note = 'Redeemed reward: ' . $reward['name'];
    $ledger = $connection->prepare('INSERT INTO points_transactions (user_id, points, transaction_type, related_redemption_id, notes) VALUES (?, ?, ?, ?, ?)');
    $ledger->bind_param('iisis', $userId, $points, $type, $redemptionId, $note);
    $ledger->execute();
    $ledger->close();
    createNotification($userId, 'Reward redemption submitted', 'Your redemption for ' . $reward['name'] . ' is Pending.', 'info', null, $redemptionId);
    $connection->commit();
    setFlash('success', 'Reward redemption submitted. Points were deducted and the request is Pending.');
    redemptionJson(201, ['ok' => true, 'id' => $redemptionId, 'points_used' => $cost, 'balance' => $balance - $cost, 'status' => $status]);
} catch (RuntimeException $exception) {
    $connection->rollback();
    redemptionJson(409, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (mysqli_sql_exception $exception) {
    $connection->rollback();
    error_log('EcoLoop reward redemption failed: ' . $exception->getMessage());
    if ((int) $exception->getCode() === 1062) redemptionJson(200, ['ok' => true, 'duplicate' => true, 'message' => 'This redemption was already submitted.']);
    redemptionJson(500, ['ok' => false, 'message' => 'We could not process this redemption. Please try again.']);
} catch (Throwable $exception) {
    $connection->rollback();
    error_log('EcoLoop reward redemption failed: ' . $exception->getMessage());
    redemptionJson(500, ['ok' => false, 'message' => 'We could not process this redemption. Please try again.']);
}
