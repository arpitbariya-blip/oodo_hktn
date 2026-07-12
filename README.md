# AssetFlow — Enterprise Asset & Resource Management System

AssetFlow helps organizations track, allocate, book, and maintain physical assets and shared
resources (equipment, furniture, vehicles, rooms) instead of using spreadsheets or paper logs.

## Tech Stack
- **Frontend:** HTML, Tailwind CSS, JavaScript
- **Backend:** PHP (custom router, no framework)
- **Database:** MySQL / MariaDB

## User Roles
- **Admin** – manages departments, categories, audit cycles, and promotes employees to Department Head / Asset Manager
- **Asset Manager** – registers/allocates assets, approves transfers, maintenance, and audits
- **Department Head** – manages assets/bookings for their department
- **Employee** – views their assets, books resources, raises maintenance requests

New signups are always created as **Employee**. Only Admin can promote roles.

## Core Features
- **Auth:** login, signup, forgot password, session check
- **Dashboard:** KPI cards, overdue returns, alerts
- **Org Setup:** departments, categories, employee directory & role promotion
- **Assets:** register, search/filter, lifecycle status (Available, Allocated, Reserved, Under Maintenance, Lost, Retired, Disposed)
- **Allocation & Transfer:** assign assets, block double-allocation, transfer requests, returns
- **Bookings:** calendar view, overlap validation, cancel
- **Maintenance:** raise → approve/reject → in progress → resolved
- **Audits:** create cycle, verify/mark items, close cycle (auto-updates asset status)
- **Reports:** KPI summary, department allocation, maintenance frequency, CSV export
- **Logs & Notifications:** activity feed, read/unread notifications

## Non-Functional Requirements
- Role-based access control enforced on the backend
- Passwords hashed (bcrypt), SQL via prepared statements
- Transactions used for multi-step operations (allocate, transfer, audit close)
- Responsive UI, consistent JSON API responses
- Modular code structure (routes → middleware → controllers)

## Known Limitations
- Department & Category creation/editing is not yet built (view-only)
- No asset edit or photo/document upload
- Overdue status isn't auto-flagged (no scheduled job)
- Only one auditor supported per audit cycle
- Some dashboard/report metrics from the spec aren't implemented yet


{ "success": true, "data": { } }
{ "success": false, "error": "message" }
```
