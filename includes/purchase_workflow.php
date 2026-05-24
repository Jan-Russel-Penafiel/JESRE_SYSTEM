<?php
declare(strict_types=1);

function purchase_workflow_stage(array $record): string
{
    $status = (string) ($record['status'] ?? 'pending');
    if ($status === 'approved') {
        return 'finalized';
    }

    if ($status === 'rejected') {
        return 'rejected';
    }

    if (empty($record['inventory_confirmed_at'])) {
        return 'inventory_review';
    }

    if (empty($record['purchasing_processed_at'])) {
        return 'purchasing_processing';
    }

    if (empty($record['received_verified_at'])) {
        return 'inventory_receiving';
    }

    return 'gm_review';
}

function purchase_workflow_stage_label(array $record): string
{
    $stage = purchase_workflow_stage($record);

    $labels = [
        'inventory_review' => 'Inventory Review',
        'purchasing_processing' => 'Purchasing Make Order',
        'inventory_receiving' => 'Inventory Receive Check',
        'gm_review' => 'GM Final Approval',
        'finalized' => 'Finalized',
        'rejected' => 'Rejected',
    ];

    return $labels[$stage] ?? 'Pending';
}

function can_inventory_confirm_purchase_order(array $record): bool
{
    return purchase_workflow_stage($record) === 'inventory_review';
}

function can_purchasing_process_purchase_order(array $record): bool
{
    return purchase_workflow_stage($record) === 'purchasing_processing';
}

function can_inventory_verify_received_purchase_order(array $record): bool
{
    return purchase_workflow_stage($record) === 'inventory_receiving';
}

function can_general_manager_finalize_purchase_order(array $record): bool
{
    return purchase_workflow_stage($record) === 'gm_review';
}

function inventory_purchase_order_action_label(array $record): ?string
{
    if (can_inventory_confirm_purchase_order($record)) {
        return 'Confirm';
    }

    if (can_inventory_verify_received_purchase_order($record)) {
        return 'Verify Received';
    }

    return null;
}

function purchasing_purchase_order_action_label(array $record): ?string
{
    return can_purchasing_process_purchase_order($record) ? 'Make Order' : null;
}

function resolve_purchase_request_source_department(array $user, string $redirectDepartment): string
{
    $userDepartment = (string) ($user['department'] ?? '');
    if (in_array($userDepartment, ['production', 'inventory'], true)) {
        return $userDepartment;
    }

    if (($user['role'] ?? null) === 'general_manager' && in_array($redirectDepartment, ['production', 'inventory'], true)) {
        return $redirectDepartment;
    }

    return '';
}
