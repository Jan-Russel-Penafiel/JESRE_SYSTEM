# Don Macchiatos Management System (Simplified)

A procedural (non-OOP) PHP + MySQL + Tailwind CSS web system based on your flowchart.

## Core Workflow (Based on flowchart.png)

1. Department Head creates/updates a department record.
2. Record becomes `pending`.
3. General Manager reviews the record in the Approval Queue.
4. If approved, record moves forward and automation executes.
5. If rejected, record stays in the same department for correction and resubmission.
6. Final consolidated outputs are shown in the Summary Reports module:
   - Financial Reports
   - CRM Insights
   - Inventory Status

## Departments and Tasks

- Inventory Department
  - Real-time stock monitoring
  - Automatic updates
- Production Department
  - Prepare beverages
  - Input usage
- Sales Department
  - Process customer orders
  - Automate sales recording
  - Inventory auto-deducted after approval
- Accounting Department
  - Record financial transactions
  - Generate financial reports
- CRM Department
  - Track customer preferences
  - Track customer purchase history
- Marketing Department
  - Analyze daily trends
  - Plan and implement automated digital promotion

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

On Sales approval:
- Inventory is automatically deducted (`quantity * stock_deduct_qty`).
- Accounting income record is auto-created.
- CRM profile is auto-created/updated.
- CRM purchase history is auto-recorded.

On Production approval:
- Inventory is automatically deducted (`ingredient_used_qty`).

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
- `approvals.php` - General Manager approval queue
- `audit_logs.php` - General Manager audit trail browser
- `reports.php` - Final consolidated reports
- `handlers.php` - POST actions and approval automation
- `includes/` - Reusable helpers, auth, DB, layout

## Setup Instructions (XAMPP)

1. Place the project in `C:\xampp\htdocs\re`.
2. Start Apache and MySQL in XAMPP.
3. Import `schema.sql` into MySQL (via phpMyAdmin or MySQL CLI).
4. If needed, edit DB credentials in `config.php`.
5. Open: `http://localhost/re`.

If you are upgrading from an earlier version of this project, re-run `schema.sql` so `audit_trails` is created.

## Default Accounts

All default passwords are: `password123`

- General Manager: `gm`
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
