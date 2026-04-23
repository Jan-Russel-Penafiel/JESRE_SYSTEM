# Don Macchiatos Management System (Simplified)

A procedural (non-OOP) PHP + MySQL + Tailwind CSS web system based on your flowchart.

## Core Workflow (Based on flowchart.jpg)

1. Purchasing buys ingredients before inventory runs out.
2. Inventory receives ingredients, stores them in the database, auto-deducts stock on sales, and raises low-stock updates.
3. Production requests ingredients from Inventory and logs beverage preparation.
4. Sales confirms customer orders in POS, checks flavor availability, processes payment, issues the receipt, and updates sales logs in real time.
5. Accounting records financial transactions and stores digital logs.
6. CRM tracks customer preferences and purchase history.
7. Marketing analyzes trends and promotes low-sales coffee.

Manager review remains available as an oversight layer for pending non-POS records, but the operational flow is department-driven.

## Departments and Tasks

- Purchasing Department
  - Review low-stock updates from Inventory/Sales automation
  - Create purchase requests for ingredients and supplies
  - On approval, auto-restock linked inventory items
- Inventory Department
  - Real-time stock monitoring (live auto-refresh)
  - Automatic updates
- Production Department
  - Prepare beverages
  - Input usage with multi-ingredient selection (with select-all support)
- Sales Department
  - Process customer orders directly in POS
  - Select multiple ingredient items for each order
  - Set per-cup and per-straw consumption values
  - Process POS payment method (cash/card/digital)
  - Generate digital order code and receipt number
  - Automate sales recording in real time
  - Inventory auto-deducted when the order is processed
  - Print receipt from Sales actions using jsPDF
- Accounting Department
  - Record financial transactions
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
- Inventory is automatically deducted for all selected ingredient items (`quantity * stock_deduct_qty`).
- Additional utility stock deduction is applied for Cup and Straw using `quantity * per_cup_qty` and `quantity * per_straw_qty`.
- Accounting income record is auto-created.
- CRM profile is auto-created/updated.
- CRM purchase history is auto-recorded.
- Marketing campaign is auto-generated/updated (`AUTO-DIGITAL-YYYYMMDD`) using sales trend + inventory health.
- Low stock auto-generates/updates a `Purchasing Department` request.

On Sales create/edit (POS validation):
- Flavor availability is checked against linked inventory stock.
- If insufficient stock, submission is blocked, Inventory is alerted, and a low-stock purchasing request is auto-created.
- Payment is marked paid and receipt number is auto-issued.

## Security Hardening

- All POST forms now include CSRF tokens and POST handlers validate tokens server-side.
- Session ID is regenerated on successful login to reduce session fixation risk.
- Select-type form fields are validated server-side against configured options.
- Daily order/request/receipt numbering now uses an atomic sequence table to avoid code collisions under concurrent requests.

On Purchasing approval:
- Linked inventory item stock is automatically increased by `requested_qty`.

On Production approval:
- Inventory is automatically deducted (`ingredient_used_qty`).

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
- `includes/` - Reusable helpers, auth, DB, layout

## Setup Instructions (XAMPP)

1. Place the project in `C:\xampp\htdocs\re`.
2. Start Apache and MySQL in XAMPP.
3. Import `schema.sql` into MySQL (via phpMyAdmin or MySQL CLI).
4. If needed, edit DB credentials in `config.php`.
5. Sales POS runs in real time by default. If needed, override with environment variable `REALTIME_SALES_MODE=0`.
6. Open: `http://localhost/re`.

If you are upgrading from an earlier version of this project, back up data first, then recreate the database from `schema.sql` so Purchasing and POS schema updates are fully applied.

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
