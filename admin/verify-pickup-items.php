<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$admin = currentUser();
$pickupId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: (filter_input(INPUT_POST, 'pickup_id', FILTER_VALIDATE_INT) ?: 0);
$connection = db();
$pickupStmt = $connection->prepare('SELECT id, user_id, reference_number, status FROM pickup_requests WHERE id = ? LIMIT 1');
$pickupStmt->bind_param('i', $pickupId);
$pickupStmt->execute();
$pickup = $pickupStmt->get_result()->fetch_assoc();
$pickupStmt->close();
if (!$pickup) { setFlash('error', 'Pickup request not found.'); redirect('admin/pickups.php'); }

$categories = ['Earphones' => 'earphones', 'Charger' => 'charger', 'Phone' => 'phone', 'Battery' => 'battery', 'Motherboard' => 'motherboard', 'Keyboard' => 'keyboard', 'Mouse' => 'mouse'];
$defaultRates = ['earphones' => 5, 'charger' => 10, 'phone' => 0, 'battery' => 5, 'motherboard' => 15, 'keyboard' => 15, 'mouse' => 10];
$rates = [];
foreach ($categories as $label => $key) $rates[$label] = max(0, (int) getAppSetting('reward_' . $key, (string) $defaultRates[$key]));
$existingStmt = $connection->prepare('SELECT id, total_points, phone_value_total, created_at FROM pickup_verifications WHERE pickup_request_id = ? LIMIT 1');
$existingStmt->bind_param('i', $pickupId);
$existingStmt->execute();
$verification = $existingStmt->get_result()->fetch_assoc();
$existingStmt->close();
$transactionOpen = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        if ($pickup['status'] !== 'Collected') throw new RuntimeException('Only a collected pickup can have its received items verified.');
        if ($verification) throw new RuntimeException('Received items for this pickup have already been verified.');
        $submittedCategories = $_POST['category'] ?? [];
        $submittedQuantities = $_POST['quantity'] ?? [];
        $submittedValues = $_POST['phone_value'] ?? [];
        $submittedConditions = $_POST['condition_notes'] ?? [];
        if (!is_array($submittedCategories) || !is_array($submittedQuantities) || count($submittedCategories) > 30) throw new RuntimeException('Submit between one and 30 item rows.');
        $items = [];
        $totalPoints = 0;
        $phoneValueTotal = 0.0;
        foreach ($submittedCategories as $index => $category) {
            $category = trim((string) $category);
            if ($category === '') continue;
            if (!array_key_exists($category, $categories)) throw new RuntimeException('Choose an eligible e-waste category.');
            $quantityRaw = trim((string) ($submittedQuantities[$index] ?? ''));
            if (!ctype_digit($quantityRaw) || (int) $quantityRaw < 1 || (int) $quantityRaw > 10000) throw new RuntimeException('Each quantity must be from 1 to 10,000.');
            $quantity = (int) $quantityRaw;
            $rate = $rates[$category];
            if ($category === 'Motherboard' && ($rate < 15 || $rate > 20)) throw new RuntimeException('Configure the motherboard rate to 15–20 points in Rewards settings first.');
            $phoneValue = 0.0;
            if ($category === 'Phone') {
                $valueRaw = trim((string) ($submittedValues[$index] ?? ''));
                if (!is_numeric($valueRaw) || (float) $valueRaw < 100 || (float) $valueRaw > 300) throw new RuntimeException('Set each phone value between ₱100 and ₱300. This value is separate from reward points.');
                $phoneValue = round((float) $valueRaw, 2);
                $phoneValueTotal += $phoneValue * $quantity;
            }
            $subtotal = $quantity * $rate;
            if ($totalPoints > 2000000000 - $subtotal) throw new RuntimeException('The item total is too large to process.');
            $totalPoints += $subtotal;
            $condition = trim((string) ($submittedConditions[$index] ?? ''));
            if (mb_strlen($condition) > 1000) throw new RuntimeException('Item condition or notes must be 1,000 characters or fewer.');
            $items[] = ['category' => $category, 'quantity' => $quantity, 'rate' => $rate, 'subtotal' => $subtotal, 'value' => $phoneValue, 'condition' => $condition === '' ? null : $condition];
        }
        if (!$items) throw new RuntimeException('Add at least one received item.');

        $connection->begin_transaction();
        $transactionOpen = true;
        $lock = $connection->prepare('SELECT status FROM pickup_requests WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $pickupId); $lock->execute();
        $lockedPickup = $lock->get_result()->fetch_assoc(); $lock->close();
        if (!$lockedPickup || $lockedPickup['status'] !== 'Collected') throw new RuntimeException('Pickup status changed. Verify items only while status is Collected.');
        $insertVerification = $connection->prepare('INSERT INTO pickup_verifications (pickup_request_id, verified_by, total_points, phone_value_total) VALUES (?, ?, ?, ?)');
        $adminId = (int) $admin['id'];
        $insertVerification->bind_param('iiid', $pickupId, $adminId, $totalPoints, $phoneValueTotal);
        $insertVerification->execute();
        $verificationId = (int) $insertVerification->insert_id;
        $insertVerification->close();
        $insertItem = $connection->prepare('INSERT INTO pickup_verified_items (verification_id, item_category, quantity, points_per_piece, subtotal_points, monetary_value_per_piece, condition_notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $item) {
            $insertItem->bind_param('isiiids', $verificationId, $item['category'], $item['quantity'], $item['rate'], $item['subtotal'], $item['value'], $item['condition']);
            $insertItem->execute();
        }
        $insertItem->close();
        logAdminAction((int) $admin['id'], 'Collected e-waste verified', 'Pickup ' . $pickup['reference_number'] . ' verification #' . $verificationId . ': ' . count($items) . ' item rows, ' . $totalPoints . ' points; phone valuation ₱' . number_format($phoneValueTotal, 2));
        createNotification((int) $pickup['user_id'], 'Collected items verified', $totalPoints . ' points are awaiting pickup completion for verified items from ' . $pickup['reference_number'] . '.', 'info', $pickupId);
        $connection->commit();
        $transactionOpen = false;
        setFlash('success', 'Received items verified. ' . $totalPoints . ' points will be awarded once an administrator completes this pickup. Phone valuation is separate.');
        redirect('admin/pickups.php');
    } catch (RuntimeException $exception) {
        if ($transactionOpen) $connection->rollback();
        setFlash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($transactionOpen) $connection->rollback();
        error_log('EcoLoop item verification failed: ' . $exception->getMessage());
        setFlash('error', 'Items could not be verified. Please try again.');
    }
}

$verifiedItems = [];
if ($verification) {
    $verifiedStmt = $connection->prepare('SELECT item_category, quantity, points_per_piece, subtotal_points, monetary_value_per_piece, condition_notes FROM pickup_verified_items WHERE verification_id = ? ORDER BY id');
    $verifiedStmt->bind_param('i', $verification['id']); $verifiedStmt->execute();
    $verifiedItems = $verifiedStmt->get_result()->fetch_all(MYSQLI_ASSOC); $verifiedStmt->close();
}
$pageTitle = 'Verify Received E-Waste | Eco-Loop';
require __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell"><?php require __DIR__ . '/../includes/navigation.php'; ?><main class="main-content">
    <div class="page-header"><div><span class="eyebrow">Admin · Pickup verification</span><h1>Verify received items</h1><p><?php echo e($pickup['reference_number']); ?> · Status: <?php echo e($pickup['status']); ?></p></div></div>
    <?php if ($verification): ?><section class="content-card"><h2>Items already verified</h2><p><strong><?php echo (int) $verification['total_points']; ?> points eligible on pickup completion</strong> · Phone valuation ₱<?php echo e(number_format((float) $verification['phone_value_total'], 2)); ?> (separate from points)</p><ul><?php foreach ($verifiedItems as $item): ?><li><?php echo e($item['item_category']); ?> × <?php echo (int) $item['quantity']; ?> · <?php echo (int) $item['points_per_piece']; ?> points each · <?php echo (int) $item['subtotal_points']; ?> points<?php if ($item['item_category'] === 'Phone'): ?> · ₱<?php echo e(number_format((float) $item['monetary_value_per_piece'], 2)); ?> each<?php endif; ?></li><?php endforeach; ?></ul></section>
    <?php elseif ($pickup['status'] === 'Collected'): ?><section class="content-card"><p>Record only e-waste physically received. Rates are loaded from administrator settings; points are credited when the authorized administrator completes this pickup.</p><div id="item-rate-data" data-rates="<?php echo e(json_encode($rates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>"></div>
        <form method="post" id="verify-items-form" class="request-grid"><?php echo csrf_field(); ?><input type="hidden" name="pickup_id" value="<?php echo (int) $pickupId; ?>"><div id="verified-item-rows" class="full-span"></div><button type="button" class="btn btn-secondary" id="add-verified-item">Add another item</button><div class="content-card full-span"><p>Points total: <strong id="verified-point-total">0</strong></p><p>Phone valuation total (not points): <strong id="verified-phone-value">₱0.00</strong></p></div><div class="form-actions full-span"><button class="btn btn-primary" type="submit">Verify received items and award points</button></div></form>
    </section>
    <?php else: ?><section class="content-card empty-state"><h2>Pickup not ready for item verification</h2><p>Only requests in Collected status can be verified.</p></section><?php endif; ?>
    <p><a class="btn btn-outline" href="<?php echo e(APP_ROOT . '/admin/pickups.php'); ?>">Back to pickups</a></p>
</main></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const rates=JSON.parse(document.getElementById('item-rate-data')?.dataset.rates||'{}'),rows=document.getElementById('verified-item-rows'); if(!rows)return;
 const cats=Object.keys(rates); function recalc(){let pts=0,val=0;rows.querySelectorAll('.verified-item-row').forEach(row=>{const cat=row.querySelector('[name="category[]"]').value,q=Number(row.querySelector('[name="quantity[]"]').value)||0,rate=Number(rates[cat]||0),phone=cat==='Phone'?(Number(row.querySelector('[name="phone_value[]"]').value)||0):0;pts+=q*rate;val+=q*phone;row.querySelector('.row-subtotal').textContent=(q*rate)+' points';});document.getElementById('verified-point-total').textContent=String(pts);document.getElementById('verified-phone-value').textContent='₱'+val.toFixed(2);}
 function addRow(){const row=document.createElement('div');row.className='verified-item-row request-grid';const select=document.createElement('select');select.name='category[]';select.required=true;select.innerHTML='<option value="">Choose received item</option>'+cats.map(c=>'<option>'+c+'</option>').join('');const quantity=document.createElement('input');quantity.type='number';quantity.name='quantity[]';quantity.min='1';quantity.max='10000';quantity.value='1';quantity.required=true;const value=document.createElement('input');value.type='number';value.name='phone_value[]';value.min='100';value.max='300';value.step='0.01';value.placeholder='Phone value ₱100–₱300';const condition=document.createElement('input');condition.name='condition_notes[]';condition.maxLength=1000;condition.placeholder='Condition / notes';const result=document.createElement('strong');result.className='row-subtotal';result.textContent='0 points';select.addEventListener('change',()=>{value.required=select.value==='Phone';value.hidden=select.value!=='Phone';recalc();});quantity.addEventListener('input',recalc);value.addEventListener('input',recalc);row.append(select,quantity,value,condition,result);rows.appendChild(row);value.hidden=true;recalc();}
 document.getElementById('add-verified-item').addEventListener('click',addRow);addRow();
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
