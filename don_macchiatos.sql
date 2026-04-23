-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2026 at 07:04 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `don_macchiatos`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounting_entries`
--

CREATE TABLE `accounting_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `entry_type` enum('income','expense') NOT NULL,
  `source` varchar(160) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounting_entries`
--

INSERT INTO `accounting_entries` (`id`, `entry_type`, `source`, `amount`, `description`, `status`, `submitted_by`, `approved_by`, `approval_note`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'income', 'Sales DM20260416-0006', 99.00, 'Auto-generated from sales approval flow.', 'approved', 4, 4, 'Auto-generated from approved sales order.', '2026-04-16 11:03:43', '2026-04-16 11:03:43', '2026-04-16 11:03:43'),
(2, 'income', 'Sales SMOKE-EDIT-20260416111904', 160.00, 'Auto-generated from sales approval flow.', 'approved', 4, 4, 'Auto-generated from approved sales order.', '2026-04-16 11:19:04', '2026-04-16 11:19:04', '2026-04-16 11:19:04'),
(3, 'income', 'Sales DM20260419-0013', 160.00, 'Auto-generated from sales approval flow.', 'approved', 4, 4, 'Auto-generated from approved sales order.', '2026-04-19 11:18:30', '2026-04-19 11:18:30', '2026-04-19 11:18:30'),
(4, 'income', 'Sales DM20260416-0005', 99.00, 'Auto-generated from processed sales order flow.', 'approved', 4, 1, 'Auto-generated from approved sales order.', '2026-04-23 10:40:23', '2026-04-23 10:40:23', '2026-04-23 10:40:23'),
(7, 'income', 'Sales 213131', 1599.00, 'Auto-generated from processed sales order flow.', 'approved', 1, 1, 'Auto-generated from approved sales order.', '2026-04-23 13:01:24', '2026-04-23 13:01:24', '2026-04-23 13:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `approval_logs`
--

CREATE TABLE `approval_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `module` enum('purchasing','inventory','production','sales','accounting','crm','marketing') NOT NULL,
  `record_id` int(10) UNSIGNED NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `note` text DEFAULT NULL,
  `action_by` int(10) UNSIGNED DEFAULT NULL,
  `action_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `approval_logs` (`id`, `module`, `record_id`, `action`, `note`, `action_by`, `action_at`) VALUES
(1, 'production', 1, 'approved', NULL, 1, '2026-04-14 09:52:01'),
(2, 'marketing', 1, 'approved', 'Auto-approved marketing campaign from sales automation flow.', 4, '2026-04-16 11:03:43'),
(3, 'sales', 2, 'approved', 'Auto-approved in real-time POS mode.', 4, '2026-04-16 11:03:43'),
(4, 'sales', 3, 'approved', 'Auto-approved in real-time POS mode.', 4, '2026-04-16 11:19:04'),
(5, 'purchasing', 4, 'approved', 'smoke gm approve', 1, '2026-04-19 11:18:14'),
(6, 'marketing', 2, 'approved', 'Auto-approved marketing campaign from sales automation flow.', 4, '2026-04-19 11:18:30'),
(7, 'sales', 4, 'approved', 'Auto-approved in real-time POS mode.', 4, '2026-04-19 11:18:30'),
(8, 'marketing', 3, 'approved', 'Auto-approved marketing campaign from sales automation flow.', 1, '2026-04-23 10:40:23'),
(9, 'sales', 1, 'approved', NULL, 1, '2026-04-23 10:40:23'),
(11, 'purchasing', 7, 'approved', 'Orderly flow auto-restock approval', 1, '2026-04-23 10:47:28'),
(13, 'purchasing', 8, 'approved', 'Orderly flow auto-restock approval', 1, '2026-04-23 10:49:56'),
(14, 'purchasing', 9, 'approved', 'Orderly flow manual restock approval', 1, '2026-04-23 10:49:56'),
(18, 'purchasing', 11, 'approved', NULL, 1, '2026-04-23 11:06:45'),
(19, 'purchasing', 12, 'approved', NULL, 1, '2026-04-23 11:07:40'),
(20, 'purchasing', 13, 'approved', NULL, 1, '2026-04-23 12:27:04'),
(21, 'purchasing', 14, 'approved', NULL, 1, '2026-04-23 12:27:06'),
(22, 'purchasing', 15, 'approved', NULL, 1, '2026-04-23 12:27:08'),
(23, 'purchasing', 16, 'approved', NULL, 1, '2026-04-23 12:27:09'),
(24, 'sales', 7, 'approved', 'Auto-approved in real-time POS mode.', 1, '2026-04-23 13:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `audit_trails`
--

CREATE TABLE `audit_trails` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module` enum('inventory','production','sales','accounting','crm','marketing','system') NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(10) UNSIGNED NOT NULL,
  `action_type` varchar(40) NOT NULL,
  `source` enum('user','system') NOT NULL DEFAULT 'user',
  `note` text DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `diff_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`diff_data`)),
  `performed_by` int(10) UNSIGNED DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_trails`
--

INSERT INTO `audit_trails` (`id`, `module`, `table_name`, `record_id`, `action_type`, `source`, `note`, `old_data`, `new_data`, `diff_data`, `performed_by`, `performed_at`) VALUES
(1, 'sales', 'sales_orders', 1, 'create', 'user', 'Record created and submitted for approval.', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"beverage_name\":\"SmokeTest Latte\",\"created_at\":\"2026-04-16 11:02:33\",\"customer_name\":\"SMOKE_RT_20260416110233\",\"id\":1,\"inventory_item_id\":1,\"notes\":\"Realtime smoke test\",\"order_code\":\"DM20260416-0005\",\"paid_at\":\"2026-04-16 11:02:33\",\"payment_method\":\"cash\",\"payment_reference\":null,\"payment_status\":\"paid\",\"quantity\":1,\"receipt_no\":\"RCPT-20260416-0005\",\"status\":\"pending\",\"stock_deduct_qty\":\"0.10\",\"submitted_by\":4,\"total_amount\":\"99.00\",\"unit_price\":\"99.00\",\"updated_at\":\"2026-04-16 11:02:33\"}', '{\"beverage_name\":{\"old\":null,\"new\":\"SmokeTest Latte\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-16 11:02:33\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_RT_20260416110233\"},\"id\":{\"old\":null,\"new\":1},\"inventory_item_id\":{\"old\":null,\"new\":1},\"notes\":{\"old\":null,\"new\":\"Realtime smoke test\"},\"order_code\":{\"old\":null,\"new\":\"DM20260416-0005\"},\"paid_at\":{\"old\":null,\"new\":\"2026-04-16 11:02:33\"},\"payment_method\":{\"old\":null,\"new\":\"cash\"},\"payment_status\":{\"old\":null,\"new\":\"paid\"},\"quantity\":{\"old\":null,\"new\":1},\"receipt_no\":{\"old\":null,\"new\":\"RCPT-20260416-0005\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"stock_deduct_qty\":{\"old\":null,\"new\":\"0.10\"},\"submitted_by\":{\"old\":null,\"new\":4},\"total_amount\":{\"old\":null,\"new\":\"99.00\"},\"unit_price\":{\"old\":null,\"new\":\"99.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:02:33\"}}', 4, '2026-04-16 11:02:33'),
(2, 'sales', 'sales_orders', 2, 'create', 'user', 'Record created and processed in real-time POS mode.', NULL, '{\"approval_note\":\"Auto-approved in real-time POS mode.\",\"approved_at\":\"2026-04-16 11:03:43\",\"approved_by\":4,\"beverage_name\":\"SmokeTest Latte\",\"created_at\":\"2026-04-16 11:03:43\",\"customer_name\":\"SMOKE_RT_20260416110343\",\"id\":2,\"inventory_item_id\":1,\"notes\":\"Realtime smoke test\",\"order_code\":\"DM20260416-0006\",\"paid_at\":\"2026-04-16 11:03:43\",\"payment_method\":\"cash\",\"payment_reference\":null,\"payment_status\":\"paid\",\"quantity\":1,\"receipt_no\":\"RCPT-20260416-0006\",\"status\":\"approved\",\"stock_deduct_qty\":\"0.10\",\"submitted_by\":4,\"total_amount\":\"99.00\",\"unit_price\":\"99.00\",\"updated_at\":\"2026-04-16 11:03:43\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-approved in real-time POS mode.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"approved_by\":{\"old\":null,\"new\":4},\"beverage_name\":{\"old\":null,\"new\":\"SmokeTest Latte\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_RT_20260416110343\"},\"id\":{\"old\":null,\"new\":2},\"inventory_item_id\":{\"old\":null,\"new\":1},\"notes\":{\"old\":null,\"new\":\"Realtime smoke test\"},\"order_code\":{\"old\":null,\"new\":\"DM20260416-0006\"},\"paid_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"payment_method\":{\"old\":null,\"new\":\"cash\"},\"payment_status\":{\"old\":null,\"new\":\"paid\"},\"quantity\":{\"old\":null,\"new\":1},\"receipt_no\":{\"old\":null,\"new\":\"RCPT-20260416-0006\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"stock_deduct_qty\":{\"old\":null,\"new\":\"0.10\"},\"submitted_by\":{\"old\":null,\"new\":4},\"total_amount\":{\"old\":null,\"new\":\"99.00\"},\"unit_price\":{\"old\":null,\"new\":\"99.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"}}', 4, '2026-04-16 11:03:43'),
(3, 'inventory', 'inventory_items', 1, 'system_update', 'system', 'Auto-deducted stock from approved sales order #2', '{\"id\":1,\"item_name\":\"Coffee Beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"55.00\",\"unit\":\"kg\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"54.90\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-16 11:03:43\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"notes\":{\"old\":null,\"new\":\"Primary espresso beans\"},\"stock_qty\":{\"old\":\"55.00\",\"new\":\"54.90\"},\"submitted_by\":{\"old\":null,\"new\":2},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"}}', 4, '2026-04-16 11:03:43'),
(4, 'accounting', 'accounting_entries', 1, 'system_create', 'system', 'Auto-created accounting entry from approved sales order DM20260416-0006.', NULL, '{\"amount\":\"99.00\",\"approval_note\":\"Auto-generated from approved sales order.\",\"approved_at\":\"2026-04-16 11:03:43\",\"approved_by\":4,\"created_at\":\"2026-04-16 11:03:43\",\"description\":\"Auto-generated from sales approval flow.\",\"entry_type\":\"income\",\"id\":1,\"source\":\"Sales DM20260416-0006\",\"status\":\"approved\",\"submitted_by\":4,\"updated_at\":\"2026-04-16 11:03:43\"}', '{\"amount\":{\"old\":null,\"new\":\"99.00\"},\"approval_note\":{\"old\":null,\"new\":\"Auto-generated from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"approved_by\":{\"old\":null,\"new\":4},\"created_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"description\":{\"old\":null,\"new\":\"Auto-generated from sales approval flow.\"},\"entry_type\":{\"old\":null,\"new\":\"income\"},\"id\":{\"old\":null,\"new\":1},\"source\":{\"old\":null,\"new\":\"Sales DM20260416-0006\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"}}', 4, '2026-04-16 11:03:43'),
(5, 'crm', 'crm_profiles', 1, 'system_create', 'system', 'Auto-created CRM profile from approved sales order DM20260416-0006.', NULL, '{\"approval_note\":\"Auto-created from approved sales order.\",\"approved_at\":\"2026-04-16 11:03:43\",\"approved_by\":4,\"contact_no\":null,\"created_at\":\"2026-04-16 11:03:43\",\"customer_name\":\"SMOKE_RT_20260416110343\",\"id\":1,\"last_purchase_at\":\"2026-04-16 11:03:43\",\"preferences\":null,\"purchase_count\":1,\"status\":\"approved\",\"submitted_by\":4,\"total_spent\":\"99.00\",\"updated_at\":\"2026-04-16 11:03:43\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-created from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"approved_by\":{\"old\":null,\"new\":4},\"created_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_RT_20260416110343\"},\"id\":{\"old\":null,\"new\":1},\"last_purchase_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"purchase_count\":{\"old\":null,\"new\":1},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"total_spent\":{\"old\":null,\"new\":\"99.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"}}', 4, '2026-04-16 11:03:43'),
(6, 'crm', 'crm_purchase_history', 1, 'system_create', 'system', 'Auto-created CRM purchase history from approved sales order DM20260416-0006.', NULL, '{\"amount\":\"99.00\",\"id\":1,\"profile_id\":1,\"purchased_at\":\"2026-04-16 11:03:43\",\"sales_order_id\":2}', '{\"amount\":{\"old\":null,\"new\":\"99.00\"},\"id\":{\"old\":null,\"new\":1},\"profile_id\":{\"old\":null,\"new\":1},\"purchased_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"sales_order_id\":{\"old\":null,\"new\":2}}', 4, '2026-04-16 11:03:43'),
(7, 'marketing', 'marketing_campaigns', 1, 'system_create', 'system', 'Auto-created digital promotion campaign from approved sales order #2.', NULL, '{\"approval_note\":\"Auto-generated by approved sales order #2\",\"approved_at\":\"2026-04-16 11:03:43\",\"approved_by\":4,\"campaign_name\":\"AUTO-DIGITAL-20260416\",\"created_at\":\"2026-04-16 11:03:43\",\"end_date\":\"2026-04-19\",\"id\":1,\"promotion_plan\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeTest Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"start_date\":\"2026-04-16\",\"status\":\"approved\",\"submitted_by\":4,\"trend_notes\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 99.00 vs average 14.14/day, high-sales beverage SmokeTest Latte (1 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"updated_at\":\"2026-04-16 11:03:43\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-generated by approved sales order #2\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"approved_by\":{\"old\":null,\"new\":4},\"campaign_name\":{\"old\":null,\"new\":\"AUTO-DIGITAL-20260416\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"},\"end_date\":{\"old\":null,\"new\":\"2026-04-19\"},\"id\":{\"old\":null,\"new\":1},\"promotion_plan\":{\"old\":null,\"new\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeTest Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\"},\"start_date\":{\"old\":null,\"new\":\"2026-04-16\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"trend_notes\":{\"old\":null,\"new\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 99.00 vs average 14.14/day, high-sales beverage SmokeTest Latte (1 qty), low-sales beverage SmokeTest Latte (1 qty).\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:03:43\"}}', 4, '2026-04-16 11:03:43'),
(8, 'sales', 'sales_orders', 3, 'edit', 'user', 'Record edited and re-processed in real-time POS mode.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"beverage_name\":\"SmokeEdit Latte\",\"created_at\":\"2026-04-16 11:19:04\",\"customer_name\":\"SMOKE_EDIT_20260416111904\",\"id\":3,\"inventory_item_id\":1,\"notes\":\"seed pending for edit smoke test\",\"order_code\":\"SMOKE-EDIT-20260416111904\",\"paid_at\":\"2026-04-16 11:19:04\",\"payment_method\":\"cash\",\"payment_reference\":null,\"payment_status\":\"paid\",\"quantity\":1,\"receipt_no\":null,\"status\":\"pending\",\"stock_deduct_qty\":\"0.10\",\"submitted_by\":4,\"total_amount\":\"80.00\",\"unit_price\":\"80.00\",\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"approval_note\":\"Auto-approved in real-time POS mode.\",\"approved_at\":\"2026-04-16 11:19:04\",\"approved_by\":4,\"beverage_name\":\"SmokeEdit Latte\",\"created_at\":\"2026-04-16 11:19:04\",\"customer_name\":\"SMOKE_EDIT_20260416111904\",\"id\":3,\"inventory_item_id\":1,\"notes\":\"edited in realtime smoke test\",\"order_code\":\"SMOKE-EDIT-20260416111904\",\"paid_at\":\"2026-04-16 11:19:04\",\"payment_method\":\"cash\",\"payment_reference\":null,\"payment_status\":\"paid\",\"quantity\":2,\"receipt_no\":\"RCPT-20260416-0007\",\"status\":\"approved\",\"stock_deduct_qty\":\"0.10\",\"submitted_by\":4,\"total_amount\":\"160.00\",\"unit_price\":\"80.00\",\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-approved in real-time POS mode.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"},\"approved_by\":{\"old\":null,\"new\":4},\"notes\":{\"old\":\"seed pending for edit smoke test\",\"new\":\"edited in realtime smoke test\"},\"quantity\":{\"old\":1,\"new\":2},\"receipt_no\":{\"old\":null,\"new\":\"RCPT-20260416-0007\"},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"total_amount\":{\"old\":\"80.00\",\"new\":\"160.00\"}}', 4, '2026-04-16 11:19:04'),
(9, 'inventory', 'inventory_items', 1, 'system_update', 'system', 'Auto-deducted stock from approved sales order #3', '{\"id\":1,\"item_name\":\"Coffee Beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"54.90\",\"unit\":\"kg\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"54.70\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"notes\":{\"old\":null,\"new\":\"Primary espresso beans\"},\"stock_qty\":{\"old\":\"54.90\",\"new\":\"54.70\"},\"submitted_by\":{\"old\":null,\"new\":2},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"}}', 4, '2026-04-16 11:19:04'),
(10, 'accounting', 'accounting_entries', 2, 'system_create', 'system', 'Auto-created accounting entry from approved sales order SMOKE-EDIT-20260416111904.', NULL, '{\"amount\":\"160.00\",\"approval_note\":\"Auto-generated from approved sales order.\",\"approved_at\":\"2026-04-16 11:19:04\",\"approved_by\":4,\"created_at\":\"2026-04-16 11:19:04\",\"description\":\"Auto-generated from sales approval flow.\",\"entry_type\":\"income\",\"id\":2,\"source\":\"Sales SMOKE-EDIT-20260416111904\",\"status\":\"approved\",\"submitted_by\":4,\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"amount\":{\"old\":null,\"new\":\"160.00\"},\"approval_note\":{\"old\":null,\"new\":\"Auto-generated from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"},\"approved_by\":{\"old\":null,\"new\":4},\"created_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"},\"description\":{\"old\":null,\"new\":\"Auto-generated from sales approval flow.\"},\"entry_type\":{\"old\":null,\"new\":\"income\"},\"id\":{\"old\":null,\"new\":2},\"source\":{\"old\":null,\"new\":\"Sales SMOKE-EDIT-20260416111904\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"}}', 4, '2026-04-16 11:19:04'),
(11, 'crm', 'crm_profiles', 2, 'system_create', 'system', 'Auto-created CRM profile from approved sales order SMOKE-EDIT-20260416111904.', NULL, '{\"approval_note\":\"Auto-created from approved sales order.\",\"approved_at\":\"2026-04-16 11:19:04\",\"approved_by\":4,\"contact_no\":null,\"created_at\":\"2026-04-16 11:19:04\",\"customer_name\":\"SMOKE_EDIT_20260416111904\",\"id\":2,\"last_purchase_at\":\"2026-04-16 11:19:04\",\"preferences\":null,\"purchase_count\":1,\"status\":\"approved\",\"submitted_by\":4,\"total_spent\":\"160.00\",\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-created from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"},\"approved_by\":{\"old\":null,\"new\":4},\"created_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_EDIT_20260416111904\"},\"id\":{\"old\":null,\"new\":2},\"last_purchase_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"},\"purchase_count\":{\"old\":null,\"new\":1},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"total_spent\":{\"old\":null,\"new\":\"160.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"}}', 4, '2026-04-16 11:19:04'),
(12, 'crm', 'crm_purchase_history', 2, 'system_create', 'system', 'Auto-created CRM purchase history from approved sales order SMOKE-EDIT-20260416111904.', NULL, '{\"amount\":\"160.00\",\"id\":2,\"profile_id\":2,\"purchased_at\":\"2026-04-16 11:19:04\",\"sales_order_id\":3}', '{\"amount\":{\"old\":null,\"new\":\"160.00\"},\"id\":{\"old\":null,\"new\":2},\"profile_id\":{\"old\":null,\"new\":2},\"purchased_at\":{\"old\":null,\"new\":\"2026-04-16 11:19:04\"},\"sales_order_id\":{\"old\":null,\"new\":3}}', 4, '2026-04-16 11:19:04'),
(13, 'marketing', 'marketing_campaigns', 1, 'system_update', 'system', 'Auto-updated digital promotion campaign from approved sales order #3.', '{\"approval_note\":\"Auto-generated by approved sales order #2\",\"approved_at\":\"2026-04-16 11:03:43\",\"approved_by\":4,\"campaign_name\":\"AUTO-DIGITAL-20260416\",\"created_at\":\"2026-04-16 11:03:43\",\"end_date\":\"2026-04-19\",\"id\":1,\"promotion_plan\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeTest Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"start_date\":\"2026-04-16\",\"status\":\"approved\",\"submitted_by\":4,\"trend_notes\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 99.00 vs average 14.14/day, high-sales beverage SmokeTest Latte (1 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"updated_at\":\"2026-04-16 11:03:43\"}', '{\"approval_note\":\"Auto-updated by approved sales order #3\",\"approved_at\":\"2026-04-16 11:19:04\",\"approved_by\":4,\"campaign_name\":\"AUTO-DIGITAL-20260416\",\"created_at\":\"2026-04-16 11:03:43\",\"end_date\":\"2026-04-19\",\"id\":1,\"promotion_plan\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"start_date\":\"2026-04-16\",\"status\":\"approved\",\"submitted_by\":4,\"trend_notes\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 259.00 vs average 37.00/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"approval_note\":{\"old\":\"Auto-generated by approved sales order #2\",\"new\":\"Auto-updated by approved sales order #3\"},\"approved_at\":{\"old\":\"2026-04-16 11:03:43\",\"new\":\"2026-04-16 11:19:04\"},\"promotion_plan\":{\"old\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeTest Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"new\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\"},\"trend_notes\":{\"old\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 99.00 vs average 14.14/day, high-sales beverage SmokeTest Latte (1 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"new\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 259.00 vs average 37.00/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\"},\"updated_at\":{\"old\":\"2026-04-16 11:03:43\",\"new\":\"2026-04-16 11:19:04\"}}', 4, '2026-04-16 11:19:04'),
(14, 'inventory', 'inventory_items', 1, 'system_update', 'system', 'Auto-restocked inventory from approved purchase request #4.', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"54.70\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"57.70\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-19 11:18:14\"}', '{\"stock_qty\":{\"old\":\"54.70\",\"new\":\"57.70\"},\"updated_at\":{\"old\":\"2026-04-16 11:19:04\",\"new\":\"2026-04-19 11:18:14\"}}', 1, '2026-04-19 11:18:14'),
(15, '', 'purchase_requests', 4, 'approved', 'user', 'smoke gm approve', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-19 11:18:14\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-19\",\"id\":4,\"inventory_item_id\":1,\"notes\":\"smoke approve purchase\",\"quoted_unit_cost\":null,\"request_code\":\"PR-SMOKE-20260419111813\",\"requested_qty\":\"3.00\",\"status\":\"pending\",\"submitted_by\":4,\"supplier_name\":null,\"updated_at\":\"2026-04-19 11:18:14\"}', '{\"approval_note\":\"smoke gm approve\",\"approved_at\":\"2026-04-19 11:18:14\",\"approved_by\":1,\"created_at\":\"2026-04-19 11:18:14\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-19\",\"id\":4,\"inventory_item_id\":1,\"notes\":\"smoke approve purchase\",\"quoted_unit_cost\":null,\"request_code\":\"PR-SMOKE-20260419111813\",\"requested_qty\":\"3.00\",\"status\":\"approved\",\"submitted_by\":4,\"supplier_name\":null,\"updated_at\":\"2026-04-19 11:18:14\"}', '{\"approval_note\":{\"old\":null,\"new\":\"smoke gm approve\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:14\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"}}', 1, '2026-04-19 11:18:14'),
(16, 'sales', 'sales_orders', 4, 'create', 'user', 'Record created and processed in real-time POS mode.', NULL, '{\"approval_note\":\"Auto-approved in real-time POS mode.\",\"approved_at\":\"2026-04-19 11:18:30\",\"approved_by\":4,\"beverage_name\":\"Realtime Latte\",\"created_at\":\"2026-04-19 11:18:30\",\"customer_name\":\"SMOKE_RT_20260419111829\",\"id\":4,\"inventory_item_id\":1,\"notes\":\"smoke realtime create\",\"order_code\":\"DM20260419-0013\",\"paid_at\":\"2026-04-19 11:18:30\",\"payment_method\":\"cash\",\"payment_reference\":null,\"payment_status\":\"paid\",\"quantity\":2,\"receipt_no\":\"RCPT-20260419-0013\",\"status\":\"approved\",\"stock_deduct_qty\":\"0.10\",\"submitted_by\":4,\"total_amount\":\"160.00\",\"unit_price\":\"80.00\",\"updated_at\":\"2026-04-19 11:18:30\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-approved in real-time POS mode.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"approved_by\":{\"old\":null,\"new\":4},\"beverage_name\":{\"old\":null,\"new\":\"Realtime Latte\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_RT_20260419111829\"},\"id\":{\"old\":null,\"new\":4},\"inventory_item_id\":{\"old\":null,\"new\":1},\"notes\":{\"old\":null,\"new\":\"smoke realtime create\"},\"order_code\":{\"old\":null,\"new\":\"DM20260419-0013\"},\"paid_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"payment_method\":{\"old\":null,\"new\":\"cash\"},\"payment_status\":{\"old\":null,\"new\":\"paid\"},\"quantity\":{\"old\":null,\"new\":2},\"receipt_no\":{\"old\":null,\"new\":\"RCPT-20260419-0013\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"stock_deduct_qty\":{\"old\":null,\"new\":\"0.10\"},\"submitted_by\":{\"old\":null,\"new\":4},\"total_amount\":{\"old\":null,\"new\":\"160.00\"},\"unit_price\":{\"old\":null,\"new\":\"80.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"}}', 4, '2026-04-19 11:18:30'),
(17, 'inventory', 'inventory_items', 1, 'system_update', 'system', 'Auto-deducted stock from approved sales order #4', '{\"id\":1,\"item_name\":\"Coffee Beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"57.70\",\"unit\":\"kg\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"57.50\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-19 11:18:30\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"notes\":{\"old\":null,\"new\":\"Primary espresso beans\"},\"stock_qty\":{\"old\":\"57.70\",\"new\":\"57.50\"},\"submitted_by\":{\"old\":null,\"new\":2},\"updated_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"}}', 4, '2026-04-19 11:18:30'),
(18, 'accounting', 'accounting_entries', 3, 'system_create', 'system', 'Auto-created accounting entry from approved sales order DM20260419-0013.', NULL, '{\"amount\":\"160.00\",\"approval_note\":\"Auto-generated from approved sales order.\",\"approved_at\":\"2026-04-19 11:18:30\",\"approved_by\":4,\"created_at\":\"2026-04-19 11:18:30\",\"description\":\"Auto-generated from sales approval flow.\",\"entry_type\":\"income\",\"id\":3,\"source\":\"Sales DM20260419-0013\",\"status\":\"approved\",\"submitted_by\":4,\"updated_at\":\"2026-04-19 11:18:30\"}', '{\"amount\":{\"old\":null,\"new\":\"160.00\"},\"approval_note\":{\"old\":null,\"new\":\"Auto-generated from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"approved_by\":{\"old\":null,\"new\":4},\"created_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"description\":{\"old\":null,\"new\":\"Auto-generated from sales approval flow.\"},\"entry_type\":{\"old\":null,\"new\":\"income\"},\"id\":{\"old\":null,\"new\":3},\"source\":{\"old\":null,\"new\":\"Sales DM20260419-0013\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"updated_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"}}', 4, '2026-04-19 11:18:30'),
(19, 'crm', 'crm_profiles', 3, 'system_create', 'system', 'Auto-created CRM profile from approved sales order DM20260419-0013.', NULL, '{\"approval_note\":\"Auto-created from approved sales order.\",\"approved_at\":\"2026-04-19 11:18:30\",\"approved_by\":4,\"contact_no\":null,\"created_at\":\"2026-04-19 11:18:30\",\"customer_name\":\"SMOKE_RT_20260419111829\",\"id\":3,\"last_purchase_at\":\"2026-04-19 11:18:30\",\"preferences\":null,\"purchase_count\":1,\"status\":\"approved\",\"submitted_by\":4,\"total_spent\":\"160.00\",\"updated_at\":\"2026-04-19 11:18:30\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-created from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"approved_by\":{\"old\":null,\"new\":4},\"created_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_RT_20260419111829\"},\"id\":{\"old\":null,\"new\":3},\"last_purchase_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"purchase_count\":{\"old\":null,\"new\":1},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"total_spent\":{\"old\":null,\"new\":\"160.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"}}', 4, '2026-04-19 11:18:30'),
(20, 'crm', 'crm_purchase_history', 3, 'system_create', 'system', 'Auto-created CRM purchase history from approved sales order DM20260419-0013.', NULL, '{\"amount\":\"160.00\",\"id\":3,\"profile_id\":3,\"purchased_at\":\"2026-04-19 11:18:30\",\"sales_order_id\":4}', '{\"amount\":{\"old\":null,\"new\":\"160.00\"},\"id\":{\"old\":null,\"new\":3},\"profile_id\":{\"old\":null,\"new\":3},\"purchased_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"sales_order_id\":{\"old\":null,\"new\":4}}', 4, '2026-04-19 11:18:30'),
(21, 'marketing', 'marketing_campaigns', 2, 'system_create', 'system', 'Auto-created digital promotion campaign from approved sales order #4.', NULL, '{\"approval_note\":\"Auto-generated by approved sales order #4\",\"approved_at\":\"2026-04-19 11:18:30\",\"approved_by\":4,\"campaign_name\":\"AUTO-DIGITAL-20260419\",\"created_at\":\"2026-04-19 11:18:30\",\"end_date\":\"2026-04-22\",\"id\":2,\"promotion_plan\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"start_date\":\"2026-04-19\",\"status\":\"approved\",\"submitted_by\":4,\"trend_notes\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 160.00 vs average 59.86/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"updated_at\":\"2026-04-19 11:18:30\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-generated by approved sales order #4\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"approved_by\":{\"old\":null,\"new\":4},\"campaign_name\":{\"old\":null,\"new\":\"AUTO-DIGITAL-20260419\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"},\"end_date\":{\"old\":null,\"new\":\"2026-04-22\"},\"id\":{\"old\":null,\"new\":2},\"promotion_plan\":{\"old\":null,\"new\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\"},\"start_date\":{\"old\":null,\"new\":\"2026-04-19\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"trend_notes\":{\"old\":null,\"new\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 160.00 vs average 59.86/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-19 11:18:30\"}}', 4, '2026-04-19 11:18:30'),
(22, '', 'purchase_requests', 5, 'system_create', 'system', 'Auto-created purchase request from low stock alert (Coffee Beans).', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-19 11:33:31\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-22\",\"id\":5,\"inventory_item_id\":1,\"notes\":\"[SYSTEM] Flavor unavailable during POS entry. Required 9,999.00 but only 57.50 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260419-0002\",\"requested_qty\":\"9941.50\",\"status\":\"pending\",\"submitted_by\":4,\"supplier_name\":null,\"updated_at\":\"2026-04-19 11:33:31\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-19 11:33:31\"},\"estimated_total\":{\"old\":null,\"new\":\"0.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-22\"},\"id\":{\"old\":null,\"new\":5},\"inventory_item_id\":{\"old\":null,\"new\":1},\"notes\":{\"old\":null,\"new\":\"[SYSTEM] Flavor unavailable during POS entry. Required 9,999.00 but only 57.50 available.\"},\"request_code\":{\"old\":null,\"new\":\"PR20260419-0002\"},\"requested_qty\":{\"old\":null,\"new\":\"9941.50\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":4},\"updated_at\":{\"old\":null,\"new\":\"2026-04-19 11:33:31\"}}', 4, '2026-04-19 11:33:31'),
(23, '', 'purchase_requests', 6, 'system_create', 'system', 'Auto-created purchase request from low stock alert (Coffee Beans).', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-19 11:33:58\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-22\",\"id\":6,\"inventory_item_id\":1,\"notes\":\"[SYSTEM] Flavor unavailable during POS entry. Required 9,999.00 but only 57.50 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260419-0003\",\"requested_qty\":\"9941.50\",\"status\":\"pending\",\"submitted_by\":4,\"supplier_name\":null,\"updated_at\":\"2026-04-19 11:33:58\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-19 11:33:58\"},\"estimated_total\":{\"old\":null,\"new\":\"0.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-22\"},\"id\":{\"old\":null,\"new\":6},\"inventory_item_id\":{\"old\":null,\"new\":1},\"notes\":{\"old\":null,\"new\":\"[SYSTEM] Flavor unavailable during POS entry. Required 9,999.00 but only 57.50 available.\"},\"request_code\":{\"old\":null,\"new\":\"PR20260419-0003\"},\"requested_qty\":{\"old\":null,\"new\":\"9941.50\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":4},\"updated_at\":{\"old\":null,\"new\":\"2026-04-19 11:33:58\"}}', 4, '2026-04-19 11:33:58'),
(24, 'inventory', 'inventory_items', 1, 'system_update', 'system', 'Auto-deducted stock from approved sales order #1', '{\"id\":1,\"item_name\":\"Coffee Beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"57.50\",\"unit\":\"kg\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"57.40\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-23 10:40:23\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"notes\":{\"old\":null,\"new\":\"Primary espresso beans\"},\"stock_qty\":{\"old\":\"57.50\",\"new\":\"57.40\"},\"submitted_by\":{\"old\":null,\"new\":2},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"}}', 1, '2026-04-23 10:40:23'),
(25, 'accounting', 'accounting_entries', 4, 'system_create', 'system', 'Auto-created accounting entry from approved sales order DM20260416-0005.', NULL, '{\"amount\":\"99.00\",\"approval_note\":\"Auto-generated from approved sales order.\",\"approved_at\":\"2026-04-23 10:40:23\",\"approved_by\":1,\"created_at\":\"2026-04-23 10:40:23\",\"description\":\"Auto-generated from processed sales order flow.\",\"entry_type\":\"income\",\"id\":4,\"source\":\"Sales DM20260416-0005\",\"status\":\"approved\",\"submitted_by\":4,\"updated_at\":\"2026-04-23 10:40:23\"}', '{\"amount\":{\"old\":null,\"new\":\"99.00\"},\"approval_note\":{\"old\":null,\"new\":\"Auto-generated from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"description\":{\"old\":null,\"new\":\"Auto-generated from processed sales order flow.\"},\"entry_type\":{\"old\":null,\"new\":\"income\"},\"id\":{\"old\":null,\"new\":4},\"source\":{\"old\":null,\"new\":\"Sales DM20260416-0005\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"}}', 1, '2026-04-23 10:40:23'),
(26, 'crm', 'crm_profiles', 4, 'system_create', 'system', 'Auto-created CRM profile from approved sales order DM20260416-0005.', NULL, '{\"approval_note\":\"Auto-created from approved sales order.\",\"approved_at\":\"2026-04-23 10:40:23\",\"approved_by\":1,\"contact_no\":null,\"created_at\":\"2026-04-23 10:40:23\",\"customer_name\":\"SMOKE_RT_20260416110233\",\"id\":4,\"last_purchase_at\":\"2026-04-23 10:40:23\",\"preferences\":null,\"purchase_count\":1,\"status\":\"approved\",\"submitted_by\":4,\"total_spent\":\"99.00\",\"updated_at\":\"2026-04-23 10:40:23\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-created from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_RT_20260416110233\"},\"id\":{\"old\":null,\"new\":4},\"last_purchase_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"purchase_count\":{\"old\":null,\"new\":1},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"total_spent\":{\"old\":null,\"new\":\"99.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"}}', 1, '2026-04-23 10:40:23'),
(27, 'crm', 'crm_purchase_history', 4, 'system_create', 'system', 'Auto-created CRM purchase history from approved sales order DM20260416-0005.', NULL, '{\"amount\":\"99.00\",\"id\":4,\"profile_id\":4,\"purchased_at\":\"2026-04-23 10:40:23\",\"sales_order_id\":1}', '{\"amount\":{\"old\":null,\"new\":\"99.00\"},\"id\":{\"old\":null,\"new\":4},\"profile_id\":{\"old\":null,\"new\":4},\"purchased_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"sales_order_id\":{\"old\":null,\"new\":1}}', 1, '2026-04-23 10:40:23'),
(28, 'marketing', 'marketing_campaigns', 3, 'system_create', 'system', 'Auto-created digital promotion campaign from approved sales order #1.', NULL, '{\"approval_note\":\"Auto-generated by approved sales order #1\",\"approved_at\":\"2026-04-23 10:40:23\",\"approved_by\":1,\"campaign_name\":\"AUTO-DIGITAL-20260423\",\"created_at\":\"2026-04-23 10:40:23\",\"end_date\":\"2026-04-26\",\"id\":3,\"promotion_plan\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"start_date\":\"2026-04-23\",\"status\":\"approved\",\"submitted_by\":4,\"trend_notes\":\"Auto-analysis from approved sales flow: 7-day revenue downward, today revenue 0.00 vs average 22.86/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"updated_at\":\"2026-04-23 10:40:23\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-generated by approved sales order #1\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"approved_by\":{\"old\":null,\"new\":1},\"campaign_name\":{\"old\":null,\"new\":\"AUTO-DIGITAL-20260423\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"end_date\":{\"old\":null,\"new\":\"2026-04-26\"},\"id\":{\"old\":null,\"new\":3},\"promotion_plan\":{\"old\":null,\"new\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\"},\"start_date\":{\"old\":null,\"new\":\"2026-04-23\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":4},\"trend_notes\":{\"old\":null,\"new\":\"Auto-analysis from approved sales flow: 7-day revenue downward, today revenue 0.00 vs average 22.86/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"}}', 1, '2026-04-23 10:40:23'),
(29, 'sales', 'sales_orders', 1, 'approved', 'user', 'Record approved by General Manager.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"beverage_name\":\"SmokeTest Latte\",\"created_at\":\"2026-04-16 11:02:33\",\"customer_name\":\"SMOKE_RT_20260416110233\",\"id\":1,\"inventory_item_id\":1,\"notes\":\"Realtime smoke test\",\"order_code\":\"DM20260416-0005\",\"paid_at\":\"2026-04-16 11:02:33\",\"payment_method\":\"cash\",\"payment_reference\":null,\"payment_status\":\"paid\",\"quantity\":1,\"receipt_no\":\"RCPT-20260416-0005\",\"status\":\"pending\",\"stock_deduct_qty\":\"0.10\",\"submitted_by\":4,\"total_amount\":\"99.00\",\"unit_price\":\"99.00\",\"updated_at\":\"2026-04-16 11:02:33\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 10:40:23\",\"approved_by\":1,\"beverage_name\":\"SmokeTest Latte\",\"created_at\":\"2026-04-16 11:02:33\",\"customer_name\":\"SMOKE_RT_20260416110233\",\"id\":1,\"inventory_item_id\":1,\"notes\":\"Realtime smoke test\",\"order_code\":\"DM20260416-0005\",\"paid_at\":\"2026-04-16 11:02:33\",\"payment_method\":\"cash\",\"payment_reference\":null,\"payment_status\":\"paid\",\"quantity\":1,\"receipt_no\":\"RCPT-20260416-0005\",\"status\":\"approved\",\"stock_deduct_qty\":\"0.10\",\"submitted_by\":4,\"total_amount\":\"99.00\",\"unit_price\":\"99.00\",\"updated_at\":\"2026-04-23 10:40:23\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 10:40:23\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"updated_at\":{\"old\":\"2026-04-16 11:02:33\",\"new\":\"2026-04-23 10:40:23\"}}', 1, '2026-04-23 10:40:23'),
(59, '', 'purchase_requests', 11, 'create', 'user', 'Record created and queued for manager review.', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 11:06:33\",\"estimated_total\":\"1585572.00\",\"expected_delivery_date\":\"2026-04-24\",\"id\":11,\"inventory_item_id\":3,\"notes\":\"asdsad\",\"quoted_unit_cost\":\"132131.00\",\"request_code\":\"213131\",\"requested_qty\":\"12.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":\"asdada\",\"updated_at\":\"2026-04-23 11:06:33\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-23 11:06:33\"},\"estimated_total\":{\"old\":null,\"new\":\"1585572.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-24\"},\"id\":{\"old\":null,\"new\":11},\"inventory_item_id\":{\"old\":null,\"new\":3},\"notes\":{\"old\":null,\"new\":\"asdsad\"},\"quoted_unit_cost\":{\"old\":null,\"new\":\"132131.00\"},\"request_code\":{\"old\":null,\"new\":\"213131\"},\"requested_qty\":{\"old\":null,\"new\":\"12.00\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":1},\"supplier_name\":{\"old\":null,\"new\":\"asdada\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 11:06:33\"}}', 1, '2026-04-23 11:06:33'),
(60, 'inventory', 'inventory_items', 3, 'system_update', 'system', 'Auto-restocked inventory from approved purchase request #11.', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"18.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-14 09:52:01\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"30.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 11:06:45\"}', '{\"stock_qty\":{\"old\":\"18.00\",\"new\":\"30.00\"},\"updated_at\":{\"old\":\"2026-04-14 09:52:01\",\"new\":\"2026-04-23 11:06:45\"}}', 1, '2026-04-23 11:06:45'),
(61, '', 'purchase_requests', 11, 'approved', 'user', 'Record approved by General Manager.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 11:06:33\",\"estimated_total\":\"1585572.00\",\"expected_delivery_date\":\"2026-04-24\",\"id\":11,\"inventory_item_id\":3,\"notes\":\"asdsad\",\"quoted_unit_cost\":\"132131.00\",\"request_code\":\"213131\",\"requested_qty\":\"12.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":\"asdada\",\"updated_at\":\"2026-04-23 11:06:33\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 11:06:45\",\"approved_by\":1,\"created_at\":\"2026-04-23 11:06:33\",\"estimated_total\":\"1585572.00\",\"expected_delivery_date\":\"2026-04-24\",\"id\":11,\"inventory_item_id\":3,\"notes\":\"asdsad\",\"quoted_unit_cost\":\"132131.00\",\"request_code\":\"213131\",\"requested_qty\":\"12.00\",\"status\":\"approved\",\"submitted_by\":1,\"supplier_name\":\"asdada\",\"updated_at\":\"2026-04-23 11:06:45\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 11:06:45\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"updated_at\":{\"old\":\"2026-04-23 11:06:33\",\"new\":\"2026-04-23 11:06:45\"}}', 1, '2026-04-23 11:06:45'),
(62, '', 'purchase_requests', 12, 'create', 'user', 'Record created and queued for manager review.', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 11:07:32\",\"estimated_total\":\"3693690.00\",\"expected_delivery_date\":\"2026-04-24\",\"id\":12,\"inventory_item_id\":3,\"notes\":\"asdad\",\"quoted_unit_cost\":\"123123.00\",\"request_code\":\"qweqweq\",\"requested_qty\":\"30.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":\"asdada\",\"updated_at\":\"2026-04-23 11:07:32\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-23 11:07:32\"},\"estimated_total\":{\"old\":null,\"new\":\"3693690.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-24\"},\"id\":{\"old\":null,\"new\":12},\"inventory_item_id\":{\"old\":null,\"new\":3},\"notes\":{\"old\":null,\"new\":\"asdad\"},\"quoted_unit_cost\":{\"old\":null,\"new\":\"123123.00\"},\"request_code\":{\"old\":null,\"new\":\"qweqweq\"},\"requested_qty\":{\"old\":null,\"new\":\"30.00\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":1},\"supplier_name\":{\"old\":null,\"new\":\"asdada\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 11:07:32\"}}', 1, '2026-04-23 11:07:32'),
(63, 'inventory', 'inventory_items', 3, 'system_update', 'system', 'Auto-restocked inventory from approved purchase request #12.', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"30.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 11:06:45\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"60.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 11:07:40\"}', '{\"stock_qty\":{\"old\":\"30.00\",\"new\":\"60.00\"},\"updated_at\":{\"old\":\"2026-04-23 11:06:45\",\"new\":\"2026-04-23 11:07:40\"}}', 1, '2026-04-23 11:07:40'),
(64, '', 'purchase_requests', 12, 'approved', 'user', 'Record approved by General Manager.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 11:07:32\",\"estimated_total\":\"3693690.00\",\"expected_delivery_date\":\"2026-04-24\",\"id\":12,\"inventory_item_id\":3,\"notes\":\"asdad\",\"quoted_unit_cost\":\"123123.00\",\"request_code\":\"qweqweq\",\"requested_qty\":\"30.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":\"asdada\",\"updated_at\":\"2026-04-23 11:07:32\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 11:07:40\",\"approved_by\":1,\"created_at\":\"2026-04-23 11:07:32\",\"estimated_total\":\"3693690.00\",\"expected_delivery_date\":\"2026-04-24\",\"id\":12,\"inventory_item_id\":3,\"notes\":\"asdad\",\"quoted_unit_cost\":\"123123.00\",\"request_code\":\"qweqweq\",\"requested_qty\":\"30.00\",\"status\":\"approved\",\"submitted_by\":1,\"supplier_name\":\"asdada\",\"updated_at\":\"2026-04-23 11:07:40\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 11:07:40\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"updated_at\":{\"old\":\"2026-04-23 11:07:32\",\"new\":\"2026-04-23 11:07:40\"}}', 1, '2026-04-23 11:07:40'),
(65, '', 'purchase_requests', 13, 'system_create', 'system', 'Auto-created purchase request after Inventory forwarded a low-stock alert (Caramel Syrup).', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":13,\"inventory_item_id\":3,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 60.00 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0005\",\"requested_qty\":\"66001.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:25:09\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-23 12:25:09\"},\"estimated_total\":{\"old\":null,\"new\":\"0.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-26\"},\"id\":{\"old\":null,\"new\":13},\"inventory_item_id\":{\"old\":null,\"new\":3},\"notes\":{\"old\":null,\"new\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 60.00 available.\"},\"request_code\":{\"old\":null,\"new\":\"PR20260423-0005\"},\"requested_qty\":{\"old\":null,\"new\":\"66001.00\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":1},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 12:25:09\"}}', 1, '2026-04-23 12:25:09');
INSERT INTO `audit_trails` (`id`, `module`, `table_name`, `record_id`, `action_type`, `source`, `note`, `old_data`, `new_data`, `diff_data`, `performed_by`, `performed_at`) VALUES
(66, '', 'purchase_requests', 14, 'system_create', 'system', 'Auto-created purchase request after Inventory forwarded a low-stock alert (Coffee Beans).', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":14,\"inventory_item_id\":1,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 57.40 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0006\",\"requested_qty\":\"66003.60\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:25:09\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-23 12:25:09\"},\"estimated_total\":{\"old\":null,\"new\":\"0.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-26\"},\"id\":{\"old\":null,\"new\":14},\"inventory_item_id\":{\"old\":null,\"new\":1},\"notes\":{\"old\":null,\"new\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 57.40 available.\"},\"request_code\":{\"old\":null,\"new\":\"PR20260423-0006\"},\"requested_qty\":{\"old\":null,\"new\":\"66003.60\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":1},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 12:25:09\"}}', 1, '2026-04-23 12:25:09'),
(67, '', 'purchase_requests', 15, 'system_create', 'system', 'Auto-created purchase request after Inventory forwarded a low-stock alert (Milk).', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":15,\"inventory_item_id\":2,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 90.00 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0007\",\"requested_qty\":\"65971.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:25:09\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-23 12:25:09\"},\"estimated_total\":{\"old\":null,\"new\":\"0.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-26\"},\"id\":{\"old\":null,\"new\":15},\"inventory_item_id\":{\"old\":null,\"new\":2},\"notes\":{\"old\":null,\"new\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 90.00 available.\"},\"request_code\":{\"old\":null,\"new\":\"PR20260423-0007\"},\"requested_qty\":{\"old\":null,\"new\":\"65971.00\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":1},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 12:25:09\"}}', 1, '2026-04-23 12:25:09'),
(68, '', 'purchase_requests', 16, 'create', 'user', 'Record created and queued for manager review.', NULL, '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:26:57\",\"estimated_total\":\"284414361.00\",\"expected_delivery_date\":\"2026-04-25\",\"id\":16,\"inventory_item_id\":3,\"notes\":\"asda\",\"quoted_unit_cost\":\"231.00\",\"request_code\":\"123131\",\"requested_qty\":\"1231231.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":\"sadada\",\"updated_at\":\"2026-04-23 12:26:57\"}', '{\"created_at\":{\"old\":null,\"new\":\"2026-04-23 12:26:57\"},\"estimated_total\":{\"old\":null,\"new\":\"284414361.00\"},\"expected_delivery_date\":{\"old\":null,\"new\":\"2026-04-25\"},\"id\":{\"old\":null,\"new\":16},\"inventory_item_id\":{\"old\":null,\"new\":3},\"notes\":{\"old\":null,\"new\":\"asda\"},\"quoted_unit_cost\":{\"old\":null,\"new\":\"231.00\"},\"request_code\":{\"old\":null,\"new\":\"123131\"},\"requested_qty\":{\"old\":null,\"new\":\"1231231.00\"},\"status\":{\"old\":null,\"new\":\"pending\"},\"submitted_by\":{\"old\":null,\"new\":1},\"supplier_name\":{\"old\":null,\"new\":\"sadada\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 12:26:57\"}}', 1, '2026-04-23 12:26:57'),
(69, 'inventory', 'inventory_items', 3, 'system_update', 'system', 'Auto-restocked inventory from approved purchase request #13.', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"60.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 11:07:40\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"66061.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 12:27:04\"}', '{\"stock_qty\":{\"old\":\"60.00\",\"new\":\"66061.00\"},\"updated_at\":{\"old\":\"2026-04-23 11:07:40\",\"new\":\"2026-04-23 12:27:04\"}}', 1, '2026-04-23 12:27:04'),
(70, '', 'purchase_requests', 13, 'approved', 'user', 'Record approved by General Manager.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":13,\"inventory_item_id\":3,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 60.00 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0005\",\"requested_qty\":\"66001.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:25:09\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 12:27:04\",\"approved_by\":1,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":13,\"inventory_item_id\":3,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 60.00 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0005\",\"requested_qty\":\"66001.00\",\"status\":\"approved\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:27:04\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 12:27:04\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"updated_at\":{\"old\":\"2026-04-23 12:25:09\",\"new\":\"2026-04-23 12:27:04\"}}', 1, '2026-04-23 12:27:04'),
(71, 'inventory', 'inventory_items', 1, 'system_update', 'system', 'Auto-restocked inventory from approved purchase request #14.', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"57.40\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-23 10:40:23\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"66061.00\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-23 12:27:06\"}', '{\"stock_qty\":{\"old\":\"57.40\",\"new\":\"66061.00\"},\"updated_at\":{\"old\":\"2026-04-23 10:40:23\",\"new\":\"2026-04-23 12:27:06\"}}', 1, '2026-04-23 12:27:06'),
(72, '', 'purchase_requests', 14, 'approved', 'user', 'Record approved by General Manager.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":14,\"inventory_item_id\":1,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 57.40 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0006\",\"requested_qty\":\"66003.60\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:25:09\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 12:27:06\",\"approved_by\":1,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":14,\"inventory_item_id\":1,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 57.40 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0006\",\"requested_qty\":\"66003.60\",\"status\":\"approved\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:27:06\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 12:27:06\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"updated_at\":{\"old\":\"2026-04-23 12:25:09\",\"new\":\"2026-04-23 12:27:06\"}}', 1, '2026-04-23 12:27:06'),
(73, 'inventory', 'inventory_items', 2, 'system_update', 'system', 'Auto-restocked inventory from approved purchase request #15.', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":2,\"item_name\":\"Milk\",\"notes\":\"Fresh milk stock\",\"reorder_level\":\"30.00\",\"status\":\"approved\",\"stock_qty\":\"90.00\",\"submitted_by\":2,\"unit\":\"liter\",\"updated_at\":\"2026-04-14 09:46:28\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":2,\"item_name\":\"Milk\",\"notes\":\"Fresh milk stock\",\"reorder_level\":\"30.00\",\"status\":\"approved\",\"stock_qty\":\"66061.00\",\"submitted_by\":2,\"unit\":\"liter\",\"updated_at\":\"2026-04-23 12:27:08\"}', '{\"stock_qty\":{\"old\":\"90.00\",\"new\":\"66061.00\"},\"updated_at\":{\"old\":\"2026-04-14 09:46:28\",\"new\":\"2026-04-23 12:27:08\"}}', 1, '2026-04-23 12:27:08'),
(74, '', 'purchase_requests', 15, 'approved', 'user', 'Record approved by General Manager.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":15,\"inventory_item_id\":2,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 90.00 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0007\",\"requested_qty\":\"65971.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:25:09\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 12:27:08\",\"approved_by\":1,\"created_at\":\"2026-04-23 12:25:09\",\"estimated_total\":\"0.00\",\"expected_delivery_date\":\"2026-04-26\",\"id\":15,\"inventory_item_id\":2,\"notes\":\"[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 90.00 available.\",\"quoted_unit_cost\":null,\"request_code\":\"PR20260423-0007\",\"requested_qty\":\"65971.00\",\"status\":\"approved\",\"submitted_by\":1,\"supplier_name\":null,\"updated_at\":\"2026-04-23 12:27:08\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 12:27:08\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"updated_at\":{\"old\":\"2026-04-23 12:25:09\",\"new\":\"2026-04-23 12:27:08\"}}', 1, '2026-04-23 12:27:08'),
(75, 'inventory', 'inventory_items', 3, 'system_update', 'system', 'Auto-restocked inventory from approved purchase request #16.', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"66061.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 12:27:04\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"1297292.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 12:27:09\"}', '{\"stock_qty\":{\"old\":\"66061.00\",\"new\":\"1297292.00\"},\"updated_at\":{\"old\":\"2026-04-23 12:27:04\",\"new\":\"2026-04-23 12:27:09\"}}', 1, '2026-04-23 12:27:09'),
(76, '', 'purchase_requests', 16, 'approved', 'user', 'Record approved by General Manager.', '{\"approval_note\":null,\"approved_at\":null,\"approved_by\":null,\"created_at\":\"2026-04-23 12:26:57\",\"estimated_total\":\"284414361.00\",\"expected_delivery_date\":\"2026-04-25\",\"id\":16,\"inventory_item_id\":3,\"notes\":\"asda\",\"quoted_unit_cost\":\"231.00\",\"request_code\":\"123131\",\"requested_qty\":\"1231231.00\",\"status\":\"pending\",\"submitted_by\":1,\"supplier_name\":\"sadada\",\"updated_at\":\"2026-04-23 12:26:57\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 12:27:09\",\"approved_by\":1,\"created_at\":\"2026-04-23 12:26:57\",\"estimated_total\":\"284414361.00\",\"expected_delivery_date\":\"2026-04-25\",\"id\":16,\"inventory_item_id\":3,\"notes\":\"asda\",\"quoted_unit_cost\":\"231.00\",\"request_code\":\"123131\",\"requested_qty\":\"1231231.00\",\"status\":\"approved\",\"submitted_by\":1,\"supplier_name\":\"sadada\",\"updated_at\":\"2026-04-23 12:27:09\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 12:27:09\"},\"approved_by\":{\"old\":null,\"new\":1},\"status\":{\"old\":\"pending\",\"new\":\"approved\"},\"updated_at\":{\"old\":\"2026-04-23 12:26:57\",\"new\":\"2026-04-23 12:27:09\"}}', 1, '2026-04-23 12:27:09'),
(77, 'sales', 'sales_orders', 7, 'create', 'user', 'Record created and processed in real-time POS mode.', NULL, '{\"approval_note\":\"Auto-approved in real-time POS mode.\",\"approved_at\":\"2026-04-23 13:01:23\",\"approved_by\":1,\"beverage_name\":\"213\",\"created_at\":\"2026-04-23 13:01:24\",\"customer_name\":\"SMOKE_EDIT_20260416111904\",\"id\":7,\"ingredient_item_ids\":\"[3,1,6,2,7]\",\"inventory_item_id\":3,\"notes\":null,\"order_code\":\"213131\",\"paid_at\":\"2026-04-23 13:01:23\",\"payment_method\":\"cash\",\"payment_reference\":\"asda\",\"payment_status\":\"paid\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"quantity\":13,\"receipt_no\":\"RCPT-20260423-0013\",\"status\":\"approved\",\"stock_deduct_qty\":\"12.00\",\"submitted_by\":1,\"total_amount\":\"1599.00\",\"unit_price\":\"123.00\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approval_note\":{\"old\":null,\"new\":\"Auto-approved in real-time POS mode.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:23\"},\"approved_by\":{\"old\":null,\"new\":1},\"beverage_name\":{\"old\":null,\"new\":\"213\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"},\"customer_name\":{\"old\":null,\"new\":\"SMOKE_EDIT_20260416111904\"},\"id\":{\"old\":null,\"new\":7},\"ingredient_item_ids\":{\"old\":null,\"new\":\"[3,1,6,2,7]\"},\"inventory_item_id\":{\"old\":null,\"new\":3},\"order_code\":{\"old\":null,\"new\":\"213131\"},\"paid_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:23\"},\"payment_method\":{\"old\":null,\"new\":\"cash\"},\"payment_reference\":{\"old\":null,\"new\":\"asda\"},\"payment_status\":{\"old\":null,\"new\":\"paid\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"quantity\":{\"old\":null,\"new\":13},\"receipt_no\":{\"old\":null,\"new\":\"RCPT-20260423-0013\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"stock_deduct_qty\":{\"old\":null,\"new\":\"12.00\"},\"submitted_by\":{\"old\":null,\"new\":1},\"total_amount\":{\"old\":null,\"new\":\"1599.00\"},\"unit_price\":{\"old\":null,\"new\":\"123.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(78, 'inventory', 'inventory_items', 3, 'system_update', 'system', 'Auto-deducted stock from approved sales order #7', '{\"id\":3,\"item_name\":\"Caramel Syrup\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"1297292.00\",\"unit\":\"bottle\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":3,\"item_name\":\"Caramel Syrup\",\"notes\":\"Flavoring stock\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"reorder_level\":\"10.00\",\"status\":\"approved\",\"stock_qty\":\"1297136.00\",\"submitted_by\":2,\"unit\":\"bottle\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"notes\":{\"old\":null,\"new\":\"Flavoring stock\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"stock_qty\":{\"old\":\"1297292.00\",\"new\":\"1297136.00\"},\"submitted_by\":{\"old\":null,\"new\":2},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(79, 'inventory', 'inventory_items', 1, 'system_update', 'system', 'Auto-deducted stock from approved sales order #7', '{\"id\":1,\"item_name\":\"Coffee Beans\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"66061.00\",\"unit\":\"kg\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":1,\"item_name\":\"Coffee Beans\",\"notes\":\"Primary espresso beans\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"reorder_level\":\"20.00\",\"status\":\"approved\",\"stock_qty\":\"65905.00\",\"submitted_by\":2,\"unit\":\"kg\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"notes\":{\"old\":null,\"new\":\"Primary espresso beans\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"stock_qty\":{\"old\":\"66061.00\",\"new\":\"65905.00\"},\"submitted_by\":{\"old\":null,\"new\":2},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(80, 'inventory', 'inventory_items', 6, 'system_update', 'system', 'Auto-deducted stock from approved sales order #7', '{\"id\":6,\"item_name\":\"Cup\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"1000.00\",\"unit\":\"pcs\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 13:00:38\",\"approved_by\":null,\"created_at\":\"2026-04-23 13:00:38\",\"id\":6,\"item_name\":\"Cup\",\"notes\":\"Utility cup stock for Sales POS deductions\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"844.00\",\"submitted_by\":null,\"unit\":\"pcs\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"notes\":{\"old\":null,\"new\":\"Utility cup stock for Sales POS deductions\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"stock_qty\":{\"old\":\"1000.00\",\"new\":\"844.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(81, 'inventory', 'inventory_items', 2, 'system_update', 'system', 'Auto-deducted stock from approved sales order #7', '{\"id\":2,\"item_name\":\"Milk\",\"reorder_level\":\"30.00\",\"status\":\"approved\",\"stock_qty\":\"66061.00\",\"unit\":\"liter\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-14 09:46:28\",\"approved_by\":1,\"created_at\":\"2026-04-14 09:46:28\",\"id\":2,\"item_name\":\"Milk\",\"notes\":\"Fresh milk stock\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"reorder_level\":\"30.00\",\"status\":\"approved\",\"stock_qty\":\"65905.00\",\"submitted_by\":2,\"unit\":\"liter\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-14 09:46:28\"},\"notes\":{\"old\":null,\"new\":\"Fresh milk stock\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"stock_qty\":{\"old\":\"66061.00\",\"new\":\"65905.00\"},\"submitted_by\":{\"old\":null,\"new\":2},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(82, 'inventory', 'inventory_items', 7, 'system_update', 'system', 'Auto-deducted stock from approved sales order #7', '{\"id\":7,\"item_name\":\"Straw\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"1000.00\",\"unit\":\"pcs\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 13:00:38\",\"approved_by\":null,\"created_at\":\"2026-04-23 13:00:38\",\"id\":7,\"item_name\":\"Straw\",\"notes\":\"Utility straw stock for Sales POS deductions\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"844.00\",\"submitted_by\":null,\"unit\":\"pcs\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"notes\":{\"old\":null,\"new\":\"Utility straw stock for Sales POS deductions\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"stock_qty\":{\"old\":\"1000.00\",\"new\":\"844.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(83, 'inventory', 'inventory_items', 6, 'system_update', 'system', 'Auto-deducted cup usage from approved sales order #7', '{\"id\":6,\"item_name\":\"Cup\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"844.00\",\"unit\":\"pcs\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 13:00:38\",\"approved_by\":null,\"created_at\":\"2026-04-23 13:00:38\",\"id\":6,\"item_name\":\"Cup\",\"notes\":\"Utility cup stock for Sales POS deductions\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"831.00\",\"submitted_by\":null,\"unit\":\"pcs\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"notes\":{\"old\":null,\"new\":\"Utility cup stock for Sales POS deductions\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"stock_qty\":{\"old\":\"844.00\",\"new\":\"831.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(84, 'inventory', 'inventory_items', 7, 'system_update', 'system', 'Auto-deducted straw usage from approved sales order #7', '{\"id\":7,\"item_name\":\"Straw\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"844.00\",\"unit\":\"pcs\"}', '{\"approval_note\":null,\"approved_at\":\"2026-04-23 13:00:38\",\"approved_by\":null,\"created_at\":\"2026-04-23 13:00:38\",\"id\":7,\"item_name\":\"Straw\",\"notes\":\"Utility straw stock for Sales POS deductions\",\"per_cup_qty\":\"1.00\",\"per_straw_qty\":\"1.00\",\"reorder_level\":\"200.00\",\"status\":\"approved\",\"stock_qty\":\"831.00\",\"submitted_by\":null,\"unit\":\"pcs\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 13:00:38\"},\"notes\":{\"old\":null,\"new\":\"Utility straw stock for Sales POS deductions\"},\"per_cup_qty\":{\"old\":null,\"new\":\"1.00\"},\"per_straw_qty\":{\"old\":null,\"new\":\"1.00\"},\"stock_qty\":{\"old\":\"844.00\",\"new\":\"831.00\"},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(85, 'accounting', 'accounting_entries', 7, 'system_create', 'system', 'Auto-created accounting entry from approved sales order 213131.', NULL, '{\"amount\":\"1599.00\",\"approval_note\":\"Auto-generated from approved sales order.\",\"approved_at\":\"2026-04-23 13:01:24\",\"approved_by\":1,\"created_at\":\"2026-04-23 13:01:24\",\"description\":\"Auto-generated from processed sales order flow.\",\"entry_type\":\"income\",\"id\":7,\"source\":\"Sales 213131\",\"status\":\"approved\",\"submitted_by\":1,\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"amount\":{\"old\":null,\"new\":\"1599.00\"},\"approval_note\":{\"old\":null,\"new\":\"Auto-generated from approved sales order.\"},\"approved_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"},\"approved_by\":{\"old\":null,\"new\":1},\"created_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"},\"description\":{\"old\":null,\"new\":\"Auto-generated from processed sales order flow.\"},\"entry_type\":{\"old\":null,\"new\":\"income\"},\"id\":{\"old\":null,\"new\":7},\"source\":{\"old\":null,\"new\":\"Sales 213131\"},\"status\":{\"old\":null,\"new\":\"approved\"},\"submitted_by\":{\"old\":null,\"new\":1},\"updated_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(86, 'crm', 'crm_profiles', 2, 'system_update', 'system', 'Auto-updated CRM profile from approved sales order 213131.', '{\"approval_note\":\"Auto-created from approved sales order.\",\"approved_at\":\"2026-04-16 11:19:04\",\"approved_by\":4,\"contact_no\":null,\"created_at\":\"2026-04-16 11:19:04\",\"customer_name\":\"SMOKE_EDIT_20260416111904\",\"id\":2,\"last_purchase_at\":\"2026-04-16 11:19:04\",\"preferences\":null,\"purchase_count\":1,\"status\":\"approved\",\"submitted_by\":4,\"total_spent\":\"160.00\",\"updated_at\":\"2026-04-16 11:19:04\"}', '{\"approval_note\":\"Auto-updated from approved sales order.\",\"approved_at\":\"2026-04-23 13:01:24\",\"approved_by\":1,\"contact_no\":null,\"created_at\":\"2026-04-16 11:19:04\",\"customer_name\":\"SMOKE_EDIT_20260416111904\",\"id\":2,\"last_purchase_at\":\"2026-04-23 13:01:24\",\"preferences\":null,\"purchase_count\":2,\"status\":\"approved\",\"submitted_by\":4,\"total_spent\":\"1759.00\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approval_note\":{\"old\":\"Auto-created from approved sales order.\",\"new\":\"Auto-updated from approved sales order.\"},\"approved_at\":{\"old\":\"2026-04-16 11:19:04\",\"new\":\"2026-04-23 13:01:24\"},\"approved_by\":{\"old\":4,\"new\":1},\"last_purchase_at\":{\"old\":\"2026-04-16 11:19:04\",\"new\":\"2026-04-23 13:01:24\"},\"purchase_count\":{\"old\":1,\"new\":2},\"total_spent\":{\"old\":\"160.00\",\"new\":\"1759.00\"},\"updated_at\":{\"old\":\"2026-04-16 11:19:04\",\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24'),
(87, 'crm', 'crm_purchase_history', 7, 'system_create', 'system', 'Auto-created CRM purchase history from approved sales order 213131.', NULL, '{\"amount\":\"1599.00\",\"id\":7,\"profile_id\":2,\"purchased_at\":\"2026-04-23 13:01:24\",\"sales_order_id\":7}', '{\"amount\":{\"old\":null,\"new\":\"1599.00\"},\"id\":{\"old\":null,\"new\":7},\"profile_id\":{\"old\":null,\"new\":2},\"purchased_at\":{\"old\":null,\"new\":\"2026-04-23 13:01:24\"},\"sales_order_id\":{\"old\":null,\"new\":7}}', 1, '2026-04-23 13:01:24'),
(88, 'marketing', 'marketing_campaigns', 3, 'system_update', 'system', 'Auto-updated digital promotion campaign from approved sales order #7.', '{\"approval_note\":\"Auto-generated by approved sales order #1\",\"approved_at\":\"2026-04-23 10:40:23\",\"approved_by\":1,\"campaign_name\":\"AUTO-DIGITAL-20260423\",\"created_at\":\"2026-04-23 10:40:23\",\"end_date\":\"2026-04-26\",\"id\":3,\"promotion_plan\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"start_date\":\"2026-04-23\",\"status\":\"approved\",\"submitted_by\":4,\"trend_notes\":\"Auto-analysis from approved sales flow: 7-day revenue downward, today revenue 0.00 vs average 22.86/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"updated_at\":\"2026-04-23 10:40:23\"}', '{\"approval_note\":\"Auto-updated by approved sales order #7\",\"approved_at\":\"2026-04-23 13:01:24\",\"approved_by\":1,\"campaign_name\":\"AUTO-DIGITAL-20260423\",\"created_at\":\"2026-04-23 10:40:23\",\"end_date\":\"2026-04-26\",\"id\":3,\"promotion_plan\":\"Auto digital promo priority: promote low-sales coffee SmokeEdit Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee 213 to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"start_date\":\"2026-04-23\",\"status\":\"approved\",\"submitted_by\":4,\"trend_notes\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 1,599.00 vs average 251.29/day, high-sales beverage 213 (13 qty), low-sales beverage SmokeEdit Latte (2 qty).\",\"updated_at\":\"2026-04-23 13:01:24\"}', '{\"approval_note\":{\"old\":\"Auto-generated by approved sales order #1\",\"new\":\"Auto-updated by approved sales order #7\"},\"approved_at\":{\"old\":\"2026-04-23 10:40:23\",\"new\":\"2026-04-23 13:01:24\"},\"promotion_plan\":{\"old\":\"Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\",\"new\":\"Auto digital promo priority: promote low-sales coffee SmokeEdit Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee 213 to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.\"},\"trend_notes\":{\"old\":\"Auto-analysis from approved sales flow: 7-day revenue downward, today revenue 0.00 vs average 22.86/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).\",\"new\":\"Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 1,599.00 vs average 251.29/day, high-sales beverage 213 (13 qty), low-sales beverage SmokeEdit Latte (2 qty).\"},\"updated_at\":{\"old\":\"2026-04-23 10:40:23\",\"new\":\"2026-04-23 13:01:24\"}}', 1, '2026-04-23 13:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `code_sequences`
--

CREATE TABLE `code_sequences` (
  `sequence_key` varchar(64) NOT NULL,
  `sequence_date` date NOT NULL,
  `last_value` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `code_sequences`
--

INSERT INTO `code_sequences` (`sequence_key`, `sequence_date`, `last_value`, `updated_at`) VALUES
('purchase_request_code', '2026-04-23', 7, '2026-04-23 12:25:09'),
('sales_order_code', '2026-04-23', 3, '2026-04-23 10:49:57'),
('sales_receipt_code', '2026-04-23', 13, '2026-04-23 13:01:23');

-- --------------------------------------------------------

--
-- Table structure for table `crm_profiles`
--

CREATE TABLE `crm_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `contact_no` varchar(60) DEFAULT NULL,
  `preferences` text DEFAULT NULL,
  `last_purchase_at` datetime DEFAULT NULL,
  `purchase_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_spent` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `crm_profiles`
--

INSERT INTO `crm_profiles` (`id`, `customer_name`, `contact_no`, `preferences`, `last_purchase_at`, `purchase_count`, `total_spent`, `status`, `submitted_by`, `approved_by`, `approval_note`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'SMOKE_RT_20260416110343', NULL, NULL, '2026-04-16 11:03:43', 1, 99.00, 'approved', 4, 4, 'Auto-created from approved sales order.', '2026-04-16 11:03:43', '2026-04-16 11:03:43', '2026-04-16 11:03:43'),
(2, 'SMOKE_EDIT_20260416111904', NULL, NULL, '2026-04-23 13:01:24', 2, 1759.00, 'approved', 4, 1, 'Auto-updated from approved sales order.', '2026-04-23 13:01:24', '2026-04-16 11:19:04', '2026-04-23 13:01:24'),
(3, 'SMOKE_RT_20260419111829', NULL, NULL, '2026-04-19 11:18:30', 1, 160.00, 'approved', 4, 4, 'Auto-created from approved sales order.', '2026-04-19 11:18:30', '2026-04-19 11:18:30', '2026-04-19 11:18:30'),
(4, 'SMOKE_RT_20260416110233', NULL, NULL, '2026-04-23 10:40:23', 1, 99.00, 'approved', 4, 1, 'Auto-created from approved sales order.', '2026-04-23 10:40:23', '2026-04-23 10:40:23', '2026-04-23 10:40:23');

-- --------------------------------------------------------

--
-- Table structure for table `crm_purchase_history`
--

CREATE TABLE `crm_purchase_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `profile_id` int(10) UNSIGNED NOT NULL,
  `sales_order_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `purchased_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `crm_purchase_history`
--

INSERT INTO `crm_purchase_history` (`id`, `profile_id`, `sales_order_id`, `amount`, `purchased_at`) VALUES
(1, 1, 2, 99.00, '2026-04-16 11:03:43'),
(2, 2, 3, 160.00, '2026-04-16 11:19:04'),
(3, 3, 4, 160.00, '2026-04-19 11:18:30'),
(4, 4, 1, 99.00, '2026-04-23 10:40:23'),
(7, 2, 7, 1599.00, '2026-04-23 13:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(120) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `stock_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `per_cup_qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `per_straw_qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_name`, `unit`, `stock_qty`, `reorder_level`, `per_cup_qty`, `per_straw_qty`, `notes`, `status`, `submitted_by`, `approved_by`, `approval_note`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'Coffee Beans', 'kg', 65905.00, 20.00, 1.00, 1.00, 'Primary espresso beans', 'approved', 2, 1, NULL, '2026-04-14 09:46:28', '2026-04-14 09:46:28', '2026-04-23 13:01:24'),
(2, 'Milk', 'liter', 65905.00, 30.00, 1.00, 1.00, 'Fresh milk stock', 'approved', 2, 1, NULL, '2026-04-14 09:46:28', '2026-04-14 09:46:28', '2026-04-23 13:01:24'),
(3, 'Caramel Syrup', 'bottle', 1297136.00, 10.00, 1.00, 1.00, 'Flavoring stock', 'approved', 2, 1, NULL, '2026-04-14 09:46:28', '2026-04-14 09:46:28', '2026-04-23 13:01:24'),
(6, 'Cup', 'pcs', 831.00, 200.00, 1.00, 1.00, 'Utility cup stock for Sales POS deductions', 'approved', NULL, NULL, NULL, '2026-04-23 13:00:38', '2026-04-23 13:00:38', '2026-04-23 13:01:24'),
(7, 'Straw', 'pcs', 831.00, 200.00, 1.00, 1.00, 'Utility straw stock for Sales POS deductions', 'approved', NULL, NULL, NULL, '2026-04-23 13:00:38', '2026-04-23 13:00:38', '2026-04-23 13:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_campaigns`
--

CREATE TABLE `marketing_campaigns` (
  `id` int(10) UNSIGNED NOT NULL,
  `campaign_name` varchar(160) NOT NULL,
  `trend_notes` text NOT NULL,
  `promotion_plan` text NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marketing_campaigns`
--

INSERT INTO `marketing_campaigns` (`id`, `campaign_name`, `trend_notes`, `promotion_plan`, `start_date`, `end_date`, `status`, `submitted_by`, `approved_by`, `approval_note`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'AUTO-DIGITAL-20260416', 'Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 259.00 vs average 37.00/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).', 'Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.', '2026-04-16', '2026-04-19', 'approved', 4, 4, 'Auto-updated by approved sales order #3', '2026-04-16 11:19:04', '2026-04-16 11:03:43', '2026-04-16 11:19:04'),
(2, 'AUTO-DIGITAL-20260419', 'Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 160.00 vs average 59.86/day, high-sales beverage SmokeEdit Latte (2 qty), low-sales beverage SmokeTest Latte (1 qty).', 'Auto digital promo priority: promote low-sales coffee SmokeTest Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee SmokeEdit Latte to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.', '2026-04-19', '2026-04-22', 'approved', 4, 4, 'Auto-generated by approved sales order #4', '2026-04-19 11:18:30', '2026-04-19 11:18:30', '2026-04-19 11:18:30'),
(3, 'AUTO-DIGITAL-20260423', 'Auto-analysis from approved sales flow: 7-day revenue upward, today revenue 1,599.00 vs average 251.29/day, high-sales beverage 213 (13 qty), low-sales beverage SmokeEdit Latte (2 qty).', 'Auto digital promo priority: promote low-sales coffee SmokeEdit Latte via social feed, SMS, and checkout banner. Bundle with high-sales coffee 213 to improve conversion and repeat buying. Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.', '2026-04-23', '2026-04-26', 'approved', 4, 1, 'Auto-updated by approved sales order #7', '2026-04-23 13:01:24', '2026-04-23 10:40:23', '2026-04-23 13:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `production_logs`
--

CREATE TABLE `production_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `beverage_name` varchar(120) NOT NULL,
  `quantity_prepared` int(10) UNSIGNED NOT NULL,
  `inventory_item_id` int(10) UNSIGNED DEFAULT NULL,
  `ingredient_item_ids` text DEFAULT NULL,
  `ingredient_used_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `production_logs`
--

INSERT INTO `production_logs` (`id`, `beverage_name`, `quantity_prepared`, `inventory_item_id`, `ingredient_item_ids`, `ingredient_used_qty`, `notes`, `status`, `submitted_by`, `approved_by`, `approval_note`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'asdada', 121, 3, NULL, 12.00, 'asda', 'approved', 1, 1, NULL, '2026-04-14 09:52:01', '2026-04-14 09:50:17', '2026-04-14 09:52:01');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_code` varchar(40) NOT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `requested_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier_name` varchar(160) DEFAULT NULL,
  `quoted_unit_cost` decimal(12,2) DEFAULT NULL,
  `estimated_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expected_delivery_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `request_code`, `inventory_item_id`, `requested_qty`, `supplier_name`, `quoted_unit_cost`, `estimated_total`, `expected_delivery_date`, `notes`, `status`, `submitted_by`, `approved_by`, `approval_note`, `approved_at`, `created_at`, `updated_at`) VALUES
(4, 'PR-SMOKE-20260419111813', 1, 3.00, NULL, NULL, 0.00, '2026-04-19', 'smoke approve purchase', 'approved', 4, 1, 'smoke gm approve', '2026-04-19 11:18:14', '2026-04-19 11:18:14', '2026-04-19 11:18:14'),
(11, '213131', 3, 12.00, 'asdada', 132131.00, 1585572.00, '2026-04-24', 'asdsad', 'approved', 1, 1, NULL, '2026-04-23 11:06:45', '2026-04-23 11:06:33', '2026-04-23 11:06:45'),
(12, 'qweqweq', 3, 30.00, 'asdada', 123123.00, 3693690.00, '2026-04-24', 'asdad', 'approved', 1, 1, NULL, '2026-04-23 11:07:40', '2026-04-23 11:07:32', '2026-04-23 11:07:40'),
(13, 'PR20260423-0005', 3, 66001.00, NULL, NULL, 0.00, '2026-04-26', '[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 60.00 available.', 'approved', 1, 1, NULL, '2026-04-23 12:27:04', '2026-04-23 12:25:09', '2026-04-23 12:27:04'),
(14, 'PR20260423-0006', 1, 66003.60, NULL, NULL, 0.00, '2026-04-26', '[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 57.40 available.', 'approved', 1, 1, NULL, '2026-04-23 12:27:06', '2026-04-23 12:25:09', '2026-04-23 12:27:06'),
(15, 'PR20260423-0007', 2, 65971.00, NULL, NULL, 0.00, '2026-04-26', '[SYSTEM] Inventory Department received a shortage alert from Sales POS. Required 66,061.00 but only 90.00 available.', 'approved', 1, 1, NULL, '2026-04-23 12:27:08', '2026-04-23 12:25:09', '2026-04-23 12:27:08'),
(16, '123131', 3, 1231231.00, 'sadada', 231.00, 284414361.00, '2026-04-25', 'asda', 'approved', 1, 1, NULL, '2026-04-23 12:27:09', '2026-04-23 12:26:57', '2026-04-23 12:27:09');

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_code` varchar(40) NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `beverage_name` varchar(120) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','card','digital') NOT NULL DEFAULT 'cash',
  `payment_reference` varchar(120) DEFAULT NULL,
  `payment_status` enum('paid','pending','failed') NOT NULL DEFAULT 'paid',
  `receipt_no` varchar(60) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `inventory_item_id` int(10) UNSIGNED DEFAULT NULL,
  `ingredient_item_ids` text DEFAULT NULL,
  `stock_deduct_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `per_cup_qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `per_straw_qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_note` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `order_code`, `customer_name`, `beverage_name`, `quantity`, `unit_price`, `payment_method`, `payment_reference`, `payment_status`, `receipt_no`, `paid_at`, `inventory_item_id`, `ingredient_item_ids`, `stock_deduct_qty`, `per_cup_qty`, `per_straw_qty`, `total_amount`, `notes`, `status`, `submitted_by`, `approved_by`, `approval_note`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'DM20260416-0005', 'SMOKE_RT_20260416110233', 'SmokeTest Latte', 1, 99.00, 'cash', NULL, 'paid', 'RCPT-20260416-0005', '2026-04-16 11:02:33', 1, NULL, 0.10, 1.00, 1.00, 99.00, 'Realtime smoke test', 'approved', 4, 1, NULL, '2026-04-23 10:40:23', '2026-04-16 11:02:33', '2026-04-23 10:40:23'),
(2, 'DM20260416-0006', 'SMOKE_RT_20260416110343', 'SmokeTest Latte', 1, 99.00, 'cash', NULL, 'paid', 'RCPT-20260416-0006', '2026-04-16 11:03:43', 1, NULL, 0.10, 1.00, 1.00, 99.00, 'Realtime smoke test', 'approved', 4, 4, 'Auto-approved in real-time POS mode.', '2026-04-16 11:03:43', '2026-04-16 11:03:43', '2026-04-16 11:03:43'),
(3, 'SMOKE-EDIT-20260416111904', 'SMOKE_EDIT_20260416111904', 'SmokeEdit Latte', 2, 80.00, 'cash', NULL, 'paid', 'RCPT-20260416-0007', '2026-04-16 11:19:04', 1, NULL, 0.10, 1.00, 1.00, 160.00, 'edited in realtime smoke test', 'approved', 4, 4, 'Auto-approved in real-time POS mode.', '2026-04-16 11:19:04', '2026-04-16 11:19:04', '2026-04-16 11:19:04'),
(4, 'DM20260419-0013', 'SMOKE_RT_20260419111829', 'Realtime Latte', 2, 80.00, 'cash', NULL, 'paid', 'RCPT-20260419-0013', '2026-04-19 11:18:30', 1, NULL, 0.10, 1.00, 1.00, 160.00, 'smoke realtime create', 'approved', 4, 4, 'Auto-approved in real-time POS mode.', '2026-04-19 11:18:30', '2026-04-19 11:18:30', '2026-04-19 11:18:30'),
(7, '213131', 'SMOKE_EDIT_20260416111904', '213', 13, 123.00, 'cash', 'asda', 'paid', 'RCPT-20260423-0013', '2026-04-23 13:01:23', 3, '[3,1,6,2,7]', 12.00, 1.00, 1.00, 1599.00, NULL, 'approved', 1, 1, 'Auto-approved in real-time POS mode.', '2026-04-23 13:01:23', '2026-04-23 13:01:24', '2026-04-23 13:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('general_manager','department_head') NOT NULL,
  `department` enum('purchasing','inventory','production','sales','accounting','crm','marketing') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `department`, `created_at`, `updated_at`) VALUES
(1, 'General Manager', 'gm', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'general_manager', NULL, '2026-04-14 09:46:28', '2026-04-14 09:46:28'),
(2, 'Inventory Head', 'inv_head', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'department_head', 'inventory', '2026-04-14 09:46:28', '2026-04-14 09:46:28'),
(3, 'Production Head', 'prod_head', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'department_head', 'production', '2026-04-14 09:46:28', '2026-04-14 09:46:28'),
(4, 'Sales Head', 'sales_head', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'department_head', 'sales', '2026-04-14 09:46:28', '2026-04-14 09:46:28'),
(5, 'Accounting Head', 'acct_head', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'department_head', 'accounting', '2026-04-14 09:46:28', '2026-04-14 09:46:28'),
(6, 'CRM Head', 'crm_head', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'department_head', 'crm', '2026-04-14 09:46:28', '2026-04-14 09:46:28'),
(7, 'Marketing Head', 'mkt_head', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'department_head', 'marketing', '2026-04-14 09:46:28', '2026-04-14 09:46:28'),
(9, 'Purchasing Head', 'purch_head', '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy', 'department_head', 'purchasing', '2026-04-16 11:26:11', '2026-04-23 10:49:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounting_entries`
--
ALTER TABLE `accounting_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_accounting_status` (`status`),
  ADD KEY `idx_accounting_type` (`entry_type`),
  ADD KEY `fk_accounting_submitted_by` (`submitted_by`),
  ADD KEY `fk_accounting_approved_by` (`approved_by`);

--
-- Indexes for table `approval_logs`
--
ALTER TABLE `approval_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_approval_logs_module` (`module`,`record_id`),
  ADD KEY `idx_approval_logs_date` (`action_at`),
  ADD KEY `fk_approval_action_by` (`action_by`);

--
-- Indexes for table `audit_trails`
--
ALTER TABLE `audit_trails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_module_record` (`module`,`record_id`),
  ADD KEY `idx_audit_action` (`action_type`),
  ADD KEY `idx_audit_performed_at` (`performed_at`),
  ADD KEY `fk_audit_performed_by` (`performed_by`);

--
-- Indexes for table `code_sequences`
--
ALTER TABLE `code_sequences`
  ADD PRIMARY KEY (`sequence_key`);

--
-- Indexes for table `crm_profiles`
--
ALTER TABLE `crm_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_crm_customer_name` (`customer_name`),
  ADD KEY `idx_crm_status` (`status`),
  ADD KEY `fk_crm_submitted_by` (`submitted_by`),
  ADD KEY `fk_crm_approved_by` (`approved_by`);

--
-- Indexes for table `crm_purchase_history`
--
ALTER TABLE `crm_purchase_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crm_history_profile` (`profile_id`),
  ADD KEY `idx_crm_history_date` (`purchased_at`),
  ADD KEY `fk_crm_history_sales` (`sales_order_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_status` (`status`),
  ADD KEY `idx_inventory_stock` (`stock_qty`,`reorder_level`),
  ADD KEY `fk_inventory_submitted_by` (`submitted_by`),
  ADD KEY `fk_inventory_approved_by` (`approved_by`);

--
-- Indexes for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_status` (`status`),
  ADD KEY `fk_marketing_submitted_by` (`submitted_by`),
  ADD KEY `fk_marketing_approved_by` (`approved_by`);

--
-- Indexes for table `production_logs`
--
ALTER TABLE `production_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_production_status` (`status`),
  ADD KEY `fk_production_inventory_item` (`inventory_item_id`),
  ADD KEY `fk_production_submitted_by` (`submitted_by`),
  ADD KEY `fk_production_approved_by` (`approved_by`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `idx_purchase_status` (`status`),
  ADD KEY `idx_purchase_inventory` (`inventory_item_id`),
  ADD KEY `fk_purchase_submitted_by` (`submitted_by`),
  ADD KEY `fk_purchase_approved_by` (`approved_by`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD UNIQUE KEY `uk_sales_orders_receipt_no` (`receipt_no`),
  ADD KEY `idx_sales_status` (`status`),
  ADD KEY `idx_sales_date` (`created_at`),
  ADD KEY `fk_sales_inventory_item` (`inventory_item_id`),
  ADD KEY `fk_sales_submitted_by` (`submitted_by`),
  ADD KEY `fk_sales_approved_by` (`approved_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounting_entries`
--
ALTER TABLE `accounting_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `approval_logs`
--
ALTER TABLE `approval_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `audit_trails`
--
ALTER TABLE `audit_trails`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `crm_profiles`
--
ALTER TABLE `crm_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `crm_purchase_history`
--
ALTER TABLE `crm_purchase_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `production_logs`
--
ALTER TABLE `production_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounting_entries`
--
ALTER TABLE `accounting_entries`
  ADD CONSTRAINT `fk_accounting_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_accounting_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `approval_logs`
--
ALTER TABLE `approval_logs`
  ADD CONSTRAINT `fk_approval_action_by` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_trails`
--
ALTER TABLE `audit_trails`
  ADD CONSTRAINT `fk_audit_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `crm_profiles`
--
ALTER TABLE `crm_profiles`
  ADD CONSTRAINT `fk_crm_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_crm_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `crm_purchase_history`
--
ALTER TABLE `crm_purchase_history`
  ADD CONSTRAINT `fk_crm_history_profile` FOREIGN KEY (`profile_id`) REFERENCES `crm_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_crm_history_sales` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inventory_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD CONSTRAINT `fk_marketing_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_marketing_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `production_logs`
--
ALTER TABLE `production_logs`
  ADD CONSTRAINT `fk_production_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_production_inventory_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_production_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `fk_purchase_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase_inventory_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_purchase_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `fk_sales_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sales_inventory_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sales_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
