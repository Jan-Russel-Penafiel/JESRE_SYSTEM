<?php
declare(strict_types=1);

$workflowFile = __DIR__ . '/../includes/purchase_workflow.php';
if (is_file($workflowFile)) {
    require_once $workflowFile;
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

assert_true(function_exists('purchase_workflow_stage'), 'purchase_workflow_stage() must be defined.');
assert_true(function_exists('can_inventory_confirm_purchase_order'), 'can_inventory_confirm_purchase_order() must be defined.');
assert_true(function_exists('can_purchasing_process_purchase_order'), 'can_purchasing_process_purchase_order() must be defined.');
assert_true(function_exists('can_general_manager_finalize_purchase_order'), 'can_general_manager_finalize_purchase_order() must be defined.');
assert_true(function_exists('inventory_purchase_order_action_label'), 'inventory_purchase_order_action_label() must be defined.');
assert_true(function_exists('purchasing_purchase_order_action_label'), 'purchasing_purchase_order_action_label() must be defined.');

$productionRequest = [
    'status' => 'pending',
    'inventory_confirmed_at' => null,
    'purchasing_processed_at' => null,
];
assert_same('inventory_review', purchase_workflow_stage($productionRequest), 'Production purchase requests should wait for Inventory review.');
assert_true(can_inventory_confirm_purchase_order($productionRequest), 'Inventory should be able to confirm unconfirmed pending purchase requests.');
assert_true(!can_purchasing_process_purchase_order($productionRequest), 'Purchasing must not process requests before Inventory confirmation.');
assert_true(!can_general_manager_finalize_purchase_order($productionRequest), 'GM must not finalize requests before Inventory and Purchasing stages.');
assert_same('Confirm', inventory_purchase_order_action_label($productionRequest), 'Inventory should see a one-time Confirm action before forwarding.');
assert_same(null, purchasing_purchase_order_action_label($productionRequest), 'Purchasing must not see an action before Inventory confirms.');

$inventoryConfirmed = [
    'status' => 'pending',
    'inventory_confirmed_at' => '2026-05-10 09:00:00',
    'purchasing_processed_at' => null,
];
assert_same('purchasing_processing', purchase_workflow_stage($inventoryConfirmed), 'Inventory-confirmed purchase orders should wait for Purchasing processing.');
assert_true(!can_inventory_confirm_purchase_order($inventoryConfirmed), 'Inventory should not confirm the same purchase order twice.');
assert_true(can_purchasing_process_purchase_order($inventoryConfirmed), 'Purchasing should process Inventory-confirmed purchase orders.');
assert_true(!can_general_manager_finalize_purchase_order($inventoryConfirmed), 'GM must not finalize before Purchasing processes the order.');
assert_same(null, inventory_purchase_order_action_label($inventoryConfirmed), 'Inventory should not see Confirm after forwarding.');
assert_same('Make Order', purchasing_purchase_order_action_label($inventoryConfirmed), 'Purchasing should see Make Order after Inventory confirmation.');

$purchasingProcessed = [
    'status' => 'pending',
    'inventory_confirmed_at' => '2026-05-10 09:00:00',
    'purchasing_processed_at' => '2026-05-10 10:00:00',
];
assert_same('gm_review', purchase_workflow_stage($purchasingProcessed), 'Purchasing-processed purchase orders should wait for GM final approval.');
assert_true(!can_inventory_confirm_purchase_order($purchasingProcessed), 'Inventory should not confirm Purchasing-processed purchase orders.');
assert_true(!can_purchasing_process_purchase_order($purchasingProcessed), 'Purchasing should not process the same purchase order twice.');
assert_true(can_general_manager_finalize_purchase_order($purchasingProcessed), 'GM should finalize purchase orders after Inventory confirmation and Purchasing processing.');
assert_same(null, purchasing_purchase_order_action_label($purchasingProcessed), 'Purchasing should not see Make Order after processing.');

$finalized = [
    'status' => 'approved',
    'inventory_confirmed_at' => '2026-05-10 09:00:00',
    'purchasing_processed_at' => '2026-05-10 10:00:00',
];
assert_same('finalized', purchase_workflow_stage($finalized), 'GM-approved purchase orders should be finalized.');

$rejected = [
    'status' => 'rejected',
    'inventory_confirmed_at' => '2026-05-10 09:00:00',
    'purchasing_processed_at' => null,
];
assert_same('rejected', purchase_workflow_stage($rejected), 'Rejected purchase orders should remain rejected.');

echo "Purchase workflow tests passed.\n";
