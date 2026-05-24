# Don Macchiatos Management System (Simplified)

A procedural (non-OOP) PHP + MySQL + Tailwind CSS web system based on your flowchart.

## Core Workflow (Based on flowchart.jpg)

1. Sales records customer orders and logs daily production.
2. Production receives Sales Order copies, prepares orders, records inventory movement, and sends purchase requests for low stock directly to Inventory.
3. Inventory receives purchase requests, confirms the generated purchase order, and sends it to Purchasing.
4. Purchasing processes confirmed purchase orders, then routes them to the General Manager for final approval.
5. Accounting records sales-fed financial activity and operating expenses such as utilities, electricity, and water bills.
6. CRM tracks customer preferences and purchase history.
7. Marketing analyzes trends and promotes low-sales coffee.

Manager review remains available as an oversight layer for pending non-POS records, but the operational flow is department-driven.

## Production Management (Revised Logic)

1. Core Idea
   - Shift from per-ingredient manual input -> per-flavor recipe system.
   - Each product (flavor) has a fixed recipe.
   - Sales will auto-deduct ingredients based on that recipe.
2. Ingredient Setup (Stock Level)
   - Example:
     - Milk -> 1 liter
     - Caramel Syrup -> 1 bottle
     - Coffee Beans -> 1 pack
     - Cups -> per piece
     - Straws -> per piece
3. Product Recipes (Per Flavor)
   - Caramel Macchiato
     - Milk -> 100 ml
     - Caramel Syrup -> 100 ml
     - Coffee Beans -> 1/8 portion
     - Cup -> 1
     - Straw -> 1
   - Spanish Latte
     - Milk -> 100 ml
     - Caramel Syrup -> 100 ml
     - Coffee Beans -> 1/8 portion
     - Cup -> 1
     - Straw -> 1
4. Sales Process (Simplified)
   - Staff selects:
     - One or more flavors (e.g., Caramel Macchiato and Strawberry)
     - Quantity and unit price per flavor
   - System will:
     - Save the selected flavors under one order code and receipt
     - Auto-compute required ingredients
     - Auto-deduct from inventory
   - Example:
     - Order: 10 Caramel Macchiato
     - Deduction:
       - Milk -> 100 ml x 10 = 1000 ml (1 liter)
       - Caramel Syrup -> 100 ml x 10
       - Coffee Beans -> 1/8 x 10
       - Cups -> 10
       - Straws -> 10
5. Inventory Integration
   - No manual deduction needed
   - Stock updates in real-time after every sale
   - Prevent sale if:
     - Ingredients are insufficient
6. Sales -> Accounting Automation
   - Every transaction:
     - Auto-record in Sales Table
     - Auto-send data to Accounting Module
   - Accounting will:
     - Add to Revenue
     - Update Income Statement automatically
7. Benefits (System Behavior)
   - Faster cashier workflow (select flavor only)
   - Accurate ingredient tracking
   - Real-time stock monitoring
   - Automatic accounting records

## Departments and Tasks

- Purchasing Department
  - Review purchase orders confirmed by Inventory
  - Process or reject purchase orders using unit cost
  - Processed purchase orders return to Inventory for received quantity verification
  - Verified purchase orders require General Manager final approval before restocking
- Inventory Department
  - Real-time stock monitoring (live auto-refresh)
  - Determine low and high stock levels
  - Confirm generated purchase orders for Purchasing processing
  - Verify actual received quantity before stock is added
- Production Department
  - Receive Sales Order copies
  - Prepare orders and review inventory movement
  - Send purchase requests to Inventory when stock is low
- Sales Department
  - Process customer orders directly in POS
  - Record customer TIN on receipts when the customer needs expense documentation
  - Add multiple products or flavors to one customer transaction
  - Log daily production from the Sales Department
  - Select beverage recipe (flavor) and quantity
  - Set per-cup and per-straw consumption values
  - Process POS payment method (cash/card/digital)
  - Generate digital order code and receipt number
  - Automate sales recording in real time
  - Production stock is checked when the order is processed
  - Print receipt from Sales actions using jsPDF
- Accounting Department
  - Record expense transactions for utilities and bills
  - Review sales-fed income records
  - Generate financial reports
- CRM Department
  - Track customer preferences
  - Track customer purchase history
- Marketing Department
  - Analyze daily trends
  - Plan and execute automated digital promotion

## Role Access

- General Manager
  - Can access all departments
  - Can approve/reject pending records
  - Can open summary reports
  - Can review full audit trail logs (with old/new/diff snapshots)
- Department Heads
  - Can access only assigned department
  - Can create, edit, view, and delete own pending/rejected records via modals

## Automation Rules

On Sales processing (real-time POS mode is now enabled by default):
- Sales checks today's logged production stock before accepting a customer order.
- Inventory deduction happens when the daily production log is saved from Sales.
- Utility stock (Cup/Straw) is deducted as part of production recipe ingredients.
- Accounting income record is auto-created.
- CRM profile is auto-created/updated.
- CRM purchase history is auto-recorded.
- Marketing campaign is auto-generated/updated (`AUTO-DIGITAL-YYYYMMDD`) using sales trend + inventory health.
- Low stock auto-generates/updates a purchasing document for the Inventory -> Purchasing flow.

On Sales create/edit (POS validation):
- Production stock availability is checked before a Sales Order is processed.
- If production stock is insufficient, submission is blocked until daily production is logged.
- Payment is marked paid and receipt number is auto-issued.

## Security Hardening

- All POST forms now include CSRF tokens and POST handlers validate tokens server-side.
- Session ID is regenerated on successful login to reduce session fixation risk.
- Select-type form fields are validated server-side against configured options.
- Daily order/request/receipt numbering now uses an atomic sequence table to avoid code collisions under concurrent requests.

On Purchase workflow:
- Production purchase requests are created as pending Inventory review and do not require General Manager approval.
- Inventory confirmation marks the request as a purchase order and sends it to Purchasing.
- Purchasing processing sends the order back to Inventory for actual received quantity verification.
- Inventory records the actual received quantity, so an order for 20 can be verified as 20 received or noted as a variance.
- General Manager final approval increases the linked inventory stock by verified `received_qty`.
- Accounting receives an approved purchase expense entry only after General Manager final approval.

On Production approval:
- Inventory is automatically deducted based on recipe ingredient quantities.

Simple operational flow:
- Sales -> Production -> Inventory -> Purchasing -> Inventory receiving verification -> General Manager final purchase approval
- Sales -> Accounting for expenses and financial records

Inventory monitoring:
- `Central Dashboard` Live Stock Monitor refreshes every 15 seconds via `inventory_live.php` for users with inventory access.

## Audit Trail (Old/New Diff)

- Every major action is logged in `audit_trails`:
  - Create
  - Edit
  - Delete
  - Approve / Reject
  - System automations (inventory deduction, accounting auto-entry, CRM auto-updates)
- Each log stores:
  - Old data snapshot
  - New data snapshot
  - Field-level diff
  - Action source (`user` or `system`)
  - Actor and timestamp

## Search, Filters, Pagination

- All department tables now support:
  - Keyword search
  - Status filter
  - Date range filter
  - Adjustable rows per page
  - Previous/Next pagination

## PDF Export (jsPDF)

- `Summary Reports` includes an `Export Summary PDF (jsPDF)` button.
- Export contains:
  - Financial summary
  - Unified output summary
  - Inventory report
  - CRM purchase history
  - Daily trends
  - Marketing campaign activity

## File Structure

- `schema.sql` - Database schema + seed users + sample inventory
- `login.php` - Authentication screen
- `dashboard.php` - Central dashboard
- `department.php` - Department CRUD with modals
- `approvals.php` - Manager review queue
- `audit_logs.php` - General Manager audit trail browser
- `reports.php` - Final consolidated reports
- `handlers.php` - POST actions and approval automation
- `scripts/2026_05_18_sales_order_items.sql` - Upgrade migration for multi-item sales receipts
- `scripts/2026_05_24_purchase_receiving_customer_tin.sql` - Upgrade migration for purchase receiving verification and customer TIN
- `includes/` - Reusable helpers, auth, DB, layout

## Setup Instructions (XAMPP)

1. Place the project in `C:\xampp\htdocs\re`.
2. Start Apache and MySQL in XAMPP.
3. Import `schema.sql` into MySQL (via phpMyAdmin or MySQL CLI).
4. If needed, edit DB credentials in `config.php`.
5. Sales POS runs in real time by default. If needed, override with environment variable `REALTIME_SALES_MODE=0`.
6. Open: `http://localhost/re`.

Upgrade note:
- Existing databases must run `scripts/2026_05_10_purchase_workflow.sql` once to add the Inventory confirmation and Purchasing processing columns used by the updated purchase workflow.
- Existing databases must run `scripts/2026_05_18_sales_order_items.sql` once to add sales order line items and backfill existing one-flavor sales.
- Existing databases must run `scripts/2026_05_24_purchase_receiving_customer_tin.sql` once to add purchase receiving verification fields and customer TIN fields.

If you are upgrading from a much earlier version, back up data first, then either run the listed upgrade scripts in order or recreate the database from `schema.sql`.

## Default Accounts

All default passwords are: `password123`

- General Manager: `gm`
- Purchasing Head: `purch_head`
- Inventory Head: `inv_head`
- Production Head: `prod_head`
- Sales Head: `sales_head`
- Accounting Head: `acct_head`
- CRM Head: `crm_head`
- Marketing Head: `mkt_head`

## Notes

- This is intentionally simplified and procedural (non-OOP).
- UI is sidebar-based and all CRUD actions use modal dialogs.
- Approved records are locked from edit/delete for workflow integrity.
- Database schema includes `code_sequences` for collision-safe daily document numbering.
