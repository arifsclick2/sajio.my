# Sajio.my — Product & Development Plan

**Product:** Sajio.my  
**Tagline:** Simple POS. Simple Ordering. Simple Business.  
**Target:** Malaysian small/medium restaurants, cafes, warungs and dine-in businesses  
**Trial:** 14 days free  
**Version:** V1 MVP

---

## 1. Product Vision

Sajio is a simple, affordable restaurant SaaS platform for Malaysia.

Core V1:
- Restaurant management
- Menu management
- Table management
- Staff management
- POS
- Staff mobile/tablet ordering
- Customer QR ordering
- Table Card / Table Tag system
- Sales tracking
- Expense tracking
- Basic money tracking
- Reports
- Subscription management

V1 should remain simple and should NOT become a full ERP/accounting system.

## 2. Core Customer Flow

### Registration
1. Owner registers
2. Chooses a unique subdomain
3. Account + restaurant are created
4. 14-day trial starts
5. Owner completes onboarding

Example: `abc-restaurant.sajio.my`

### Onboarding
1. Profile
2. Branding
3. Tables
4. Menu
5. Staff
6. Ready to sell

### Daily Flow
Staff: `Login → Select/Scan Table → Add Food → Send Order`

Customer: `Scan QR → Menu → Cart → Place Order`

Restaurant: `Receive → Preparing → Ready → Served`

Cashier: `Open Bill → Payment → Receipt → Close Session`

## 3. Packages

| Feature | Basic | Premium | Pro |
|---|---:|---:|---:|
| Staff | 5 | 10 | 20 |
| POS Devices | 1 | 3 | 10 |
| Tables | 10 | 30 | Unlimited |
| Menu Items | 100 | 500 | Unlimited |
| POS | Yes | Yes | Yes |
| Staff Table Ordering | Yes | Yes | Yes |
| Customer QR Ordering | No | Yes | Yes |
| Table Management | Yes | Yes | Yes |
| Sales | Yes | Yes | Yes |
| Basic Money Tracking | Yes | Yes | Yes |
| Expense Tracking | Yes | Yes | Yes |
| Basic Reports | Yes | Yes | Yes |
| Advanced Reports | No | Yes | Yes |
| Restaurant Branding | Yes | Yes | Yes |
| Table Card / Tag System | No | No | Yes |
| Fast Table Scan at POS | No | No | Yes |
| NFC Tag Support | No | No | Yes |
| Table Card Printing | No | No | Yes |

**Pricing: TBD. Do not hard-code pricing until finalized.**

## 4. Trial & Subscription

Every new restaurant receives a **14-day free trial**.

After expiry:
- POS/order creation is blocked
- Customer ordering is blocked
- Existing data is retained
- Subscription area remains available
- Owner can subscribe and continue

Subscription states:
- TRIAL
- ACTIVE
- PAST_DUE
- EXPIRED
- CANCELLED
- SUSPENDED

## 5. User Roles

### Sajio Super Admin
Restaurants, users, packages, subscriptions, payments, system settings, audit logs and system statistics.

### Restaurant Owner
Profile, branding, menu, tables, table tags, staff, orders, POS, sales, expenses, reports and subscription.

### Restaurant Manager
Operational access to tables, menu, orders, POS, sales, reports and staff operations. Normally no subscription/SaaS-level settings.

### Restaurant Staff
Login, tables, orders, POS and permitted payment functions only.

## 6. Restaurant Profile & Branding

Fields:
- Restaurant name
- Logo
- Phone
- Email
- Address
- City
- State
- Postcode
- Country
- Opening hours
- Currency
- Timezone
- Primary brand colour
- Receipt header/footer

Malaysia defaults:
- MYR / RM
- Asia/Kuala_Lumpur

## 7. Subdomain

Example: `abc-restaurant.sajio.my`

Rules:
- Lowercase
- Letters/numbers/hyphens
- No spaces
- No leading/trailing hyphen
- Unique

Reserved:
`www`, `admin`, `api`, `app`, `mail`, `support`, `billing`, `status`

Use wildcard DNS: `*.sajio.my`.

Tenant resolution must be server-side. Never trust a restaurant_id supplied by the browser.

## 8. Menu

### Categories
- Name
- Description
- Sort order
- Active/inactive

### Products
- Name
- Category
- Description
- Price
- Image
- Optional SKU/reference
- Active/unavailable
- Sort order

Simple modifiers/add-ons may be supported. Avoid complex recipe/inventory logic in V1.

## 9. Table Management

Each restaurant can create tables.

Fields:
- Table number/name
- Capacity
- Active status
- QR code
- Public token

Show:
- Available
- Occupied
- Active order
- Current session
- Current bill

---

# 10. Table Card / Table Tag System — PRO

This is a key Malaysian dine-in feature.

A physical card/tag contains a table number. Staff can use it during ordering and the cashier can scan it when the customer wants to pay.

Recommended terminology:

**Table Tag / Table Token System**

### Physical Tag
May contain:
- Sajio branding
- Table number
- QR code
- NFC tag
- Unique tag identifier

Example:

```text
SAJIO
TABLE #25
QR CODE
NFC
```

### V1 Recommendation

Implement **QR-based Table Tags fully** and make the backend NFC-ready.

NFC integration can be added later.

## 11. Table Tag Architecture

**Physical Tag != Table**

Tags must be assignable/reassignable.

Example:

```text
Table:
ID: 25
Number: 25

Tag:
ID: 1008
Token: 8F4K2
Type: QR
Status: ACTIVE
Assigned Table: 25
```

Suggested `table_tags` fields:
- id
- restaurant_id
- table_id
- tag_code
- public_token
- tag_type
- status
- created_at
- updated_at

Types:
- QR
- NFC
- QR_NFC

## 12. Table Session

Use a **Table Session** concept to manage the current bill.

Example:

```text
Session #10052
Table: 25
Started: 7:15 PM
Orders: #5001, #5002, #5003
Total: RM87
Status: OPEN
```

After payment: `OPEN → CLOSED`

Suggested fields:
- id
- restaurant_id
- table_id
- opened_by
- closed_by
- status
- opened_at
- closed_at
- total_amount

## 13. Table Tag Workflow

### Staff
`Login → Scan Tag → Identify Table → Open Session → Add Food → Send Order`

If a session already exists:
`Scan Tag → Existing Session → Add Order`

### Cashier
`Scan Tag → Table → Active Session → Current Bill → Payment → Receipt → Close Session`

Example:

```text
TABLE 25

Chicken Rice          RM24
Teh Tarik             RM 6
Fried Egg             RM 3
---------------------------
TOTAL                 RM33

[ CASH ] [ CARD ] [ QR ]
```

After payment: `Session CLOSED → Table AVAILABLE`

## 14. Table Tag Management

Pro users can:
- Create tags
- Assign/unassign tags
- Reassign tags
- Replace damaged tags
- Disable tags
- Regenerate QR
- Print table cards
- View tag status

Future:
- NFC management
- NFC tap at POS
- Branded Sajio Table Tag hardware packs

## 15. Customer QR Ordering

Available: Premium, Pro  
Not available: Basic

Customer needs no app/account.

Flow: `Scan QR → Mobile Menu → Cart → Confirm → Order Created`

Optional:
- Customer name
- Customer phone

Use public tokens, not database IDs.

Example: `/order/table/{public_token}`

Conceptual distinction:
- Customer QR = ordering
- Table Tag = table/session identification

A single physical card may contain both.

## 16. Staff Mobile / Tablet Ordering

Use responsive web/PWA in V1. No native apps.

Support:
- Android phone/tablet
- iPhone/iPad
- Desktop
- POS touchscreen

Flow: `Staff Login → Tables → Select/Scan Table → Products → Cart → Confirm → Send`

UI:
- Large touch targets
- Fast category switching
- Minimal clicks
- Fast table selection
- Clear confirmation

## 17. POS

Supports:
- Table selection
- Table Tag scanning
- Dine-in
- Takeaway
- Products/quantity
- Remove item
- Notes
- Discount
- Subtotal
- Tax
- Total
- Payment
- Receipt
- Sales history

Initial payment methods:
- Cash
- Card
- QR
- Other

Sajio records payment methods; it is not initially a payment processor.

## 18. Order Types & Lifecycle

Order types:
- DINE_IN
- TAKEAWAY

Lifecycle: `NEW → PREPARING → READY → SERVED → COMPLETED`

Additional:
- CANCELLED
- VOIDED

Status changes must be logged.

## 19. Basic Money / Accounting

V1 is **not full accounting**.

Track:
- Gross sales
- Discounts
- Tax
- Net sales
- Order count
- Payment totals
- Cash/card/QR/other breakdown

Expenses:
- Category
- Description
- Amount
- Date
- Payment method
- Note
- Created by

Simple summary: `Sales - Expenses = Net Position`

Call this a business money summary, not statutory/full accounting.

## 20. Dashboard

- Today's sales
- Today's orders
- Active tables
- Pending orders
- Payment breakdown
- Recent orders
- Sales trend
- Quick actions

## 21. Reports

Basic:
- Daily sales
- Weekly sales
- Monthly sales
- Product sales
- Category sales
- Payment summary
- Order summary
- Expense summary

Premium/Pro:
- Staff sales
- Hourly sales
- Discount summary
- Refund/void summary
- Profit/expense summary

All reports must be tenant-isolated.

## 22. Receipt

V1 browser-based printing.

Contains:
- Restaurant name/logo
- Address/phone
- Order number
- Table number
- Date/time
- Items
- Quantity/price
- Discount
- Tax
- Total
- Payment method
- Footer

Future: local print bridge + thermal printers.

## 23. POS Hardware Strategy

Sajio should be hardware-agnostic.

Potential hardware:
- Android tablet
- Android POS terminal
- Windows PC
- Touchscreen
- Thermal printer
- Cash drawer
- QR/table tags

V1: browser/PWA POS.

Future:
`Sajio Cloud → Local Print Bridge → Thermal Printer`

Do not lock Sajio to one hardware vendor.

## 24. Super Admin

Dashboard:
- Total restaurants
- Active/trial/expired
- Package distribution
- Subscription statistics
- MRR
- Payments

Management:
- Restaurants
- Restaurant details
- Packages
- Package limits
- Subscriptions
- Payments
- Users
- System settings
- Audit logs

## 25. Database Architecture

Recommended: **PostgreSQL**

Every restaurant-owned entity should contain `restaurant_id`.

Tenant resolution:
- Authenticated user
- Restaurant membership
- Subdomain
- Secure public token where appropriate

Never trust client-provided tenant IDs.

### Main Entities

SaaS:
- users
- restaurants
- packages
- package_limits
- subscriptions
- subscription_events
- payments
- trial_records

Restaurant:
- restaurant_settings
- restaurant_branding
- staff
- roles
- permissions
- role_permissions

Menu:
- categories
- products
- product_modifiers
- product_modifier_options

Tables:
- restaurant_tables
- table_tags
- table_qr_tokens
- table_sessions

Orders:
- orders
- order_items
- order_item_modifiers
- order_status_history

Sales:
- sales
- sale_items

Expenses:
- expense_categories
- expenses

System:
- audit_logs
- notifications
- system_settings

## 26. Financial Integrity

Use `NUMERIC(12,2)` for money. Never use floating point.

Preserve:
- Amount
- Currency
- Timestamp
- Restaurant
- Source
- Status

Never permanently delete completed financial transactions.

Use cancel/void/refund records.

## 27. Technology Stack

Frontend: **Next.js + TypeScript**  
Backend: **Laravel + PHP**  
Database: **PostgreSQL**  
Cache/Queue: **Redis**  
Web Server: **Nginx**  
DNS/CDN/SSL: **Cloudflare**  
Hosting: **DigitalOcean initially**  
Storage: **S3-compatible object storage**  
Auth: **Laravel Sanctum / secure sessions**  
API: **REST**

## 28. Multi-Tenant Architecture

```text
Cloudflare
     |
     v
Nginx
     |
     +---- Next.js
     |
     +---- Laravel API
              |
              +---- PostgreSQL
              |
              +---- Redis
```

Tenant flow:

```text
Request
 ↓
Subdomain
 ↓
Restaurant Resolution
 ↓
Authenticated User / Public Token
 ↓
Tenant Context
 ↓
Authorization
 ↓
Restaurant Data
```

One platform serves many restaurants. Do not create a separate server per restaurant.

## 29. Security

Required:
- HTTPS
- Password hashing
- Server-side authorization
- RBAC
- Tenant isolation
- CSRF protection
- API authentication
- Rate limiting
- Validation
- Output escaping
- Secure cookies
- Secure file upload
- Environment-based secrets
- Audit logs
- Monitoring
- Error handling

Never store credentials/secrets in source code.

Critical test:

**Restaurant A must never access Restaurant B data.**

## 30. Backup Strategy

- Automated daily PostgreSQL backup
- Minimum 30-day retention
- Off-server/off-site storage
- Encryption
- Monitoring
- Regular restore testing

Future:
- Point-in-time recovery
- More frequent backups
- Cross-region backup

A backup is only useful if restoration is tested.

## 31. Malaysia Readiness

Support from the beginning:
- MYR / RM
- Asia/Kuala_Lumpur
- Malaysian phone numbers
- Malaysian address structure
- SST configuration
- Data-driven tax configuration
- Future e-Invoice readiness
- Malaysian payment methods

Do not hard-code tax rates.

### e-Invoice
Do not make full e-Invoice integration a V1 requirement unless separately approved. Design the data model so it can be added later.

## 32. V1 Included

- Public landing page
- Registration
- 14-day trial
- Packages/subscriptions
- Restaurant subdomain
- Profile/branding
- Menu/category/product management
- Table management
- Table QR
- Pro Table Card / Table Tag system
- QR-based tag scanning
- Table sessions
- Staff management
- Roles
- Dashboard
- POS
- Dine-in
- Takeaway
- Staff mobile/tablet ordering
- Customer QR ordering
- Order management/status
- Payment recording
- Sales
- Basic money tracking
- Expenses
- Basic reports
- Receipt
- Super Admin
- Audit logging
- Automated backups
- Basic security/monitoring
- Malaysian readiness

## 33. Explicitly NOT V1

Do not build unless separately approved:
- Full accounting/double-entry
- Payroll
- Advanced inventory
- Supplier/purchasing
- Loyalty
- Delivery
- Reservations
- Multi-branch
- Marketplace
- Native Android/iOS apps
- Complex offline sync
- AI
- Advanced marketing automation
- Public API platform
- Large integration ecosystem
- Advanced analytics
- Full e-Invoice integration
- Full offline POS

## 34. Development Phases

### Phase 0 — Product & Architecture
Requirements, packages, roles, workflows, ERD, multi-tenancy, subscriptions, security, UI, Table Tag architecture.

Deliverables:
- Architecture document
- ERD
- Feature matrix
- Route map
- Permission matrix

### Phase 1 — Foundation
Git, Next.js, Laravel, PostgreSQL, Redis, environment variables, local development, CI/CD, base UI/API/auth.

### Phase 2 — SaaS Core
Registration, login, email verification, restaurant creation, subdomain, trial, packages, subscription states, Super Admin.

### Phase 3 — Restaurant Setup
Profile, branding, categories, products, tables, QR, Table Tags, Table Sessions, staff, roles, permissions.

### Phase 4 — POS & Orders
POS, table selection, Table Tag scanning, dine-in, takeaway, staff ordering, order lifecycle, payments, receipts, sales.

### Phase 5 — Customer QR
Public menu, table QR, mobile ordering, cart, order creation, status, restaurant notifications.

### Phase 6 — Money & Reports
Sales, payment breakdown, expenses, dashboard, daily/monthly/product/category reports, money summary.

### Phase 7 — Security & Reliability
Tenant isolation, authorization, auth security, rate limits, audit logs, backups, restore testing, monitoring, error handling.

### Phase 8 — Beta
Test with **3–10 real restaurants** and validate POS speed, table workflow, Table Tags, staff ordering, customer QR, receipts, sales, expenses, reports and subscriptions.

## 35. Antigravity IDE AI Development Rules

Do not build the whole SaaS in one giant prompt.

Workflow:
`Requirement → Plan → DB/API Design → Implementation → Tests → Security Check → UI Check → Run → Review → Commit`

Before architectural changes:
1. Read `PLAN.md`
2. Understand current architecture
3. Explain proposed change
4. Identify affected modules
5. Implement
6. Test
7. Review security
8. Update documentation

Rules:
- Do not silently expand V1
- No major dependency without explanation
- DB changes through migrations
- No manual production schema changes
- No secrets in Git
- Tests for important business logic
- Preserve tenant isolation
- Server-side permissions
- Validate all input
- Decimal money
- No V2/V3 without approval

After major work report:
- Files changed
- Database changes
- API changes
- Tests
- Security considerations
- Remaining issues

## 36. Recommended Build Order

1. Architecture
2. Database
3. Authentication
4. Multi-tenancy
5. Restaurant registration
6. Subdomain
7. Trial
8. Packages/subscription
9. Restaurant setup
10. Menu
11. Tables
12. Table Sessions
13. Staff
14. Table Tags
15. POS
16. Staff ordering
17. Customer QR ordering
18. Payments
19. Sales
20. Expenses
21. Reports
22. Super Admin
23. Security
24. Backup
25. Beta testing

## 37. Definition of Done

A restaurant can:

```text
Register
 ↓
14-day trial
 ↓
Subdomain
 ↓
Restaurant setup
 ↓
Tables
 ↓
Menu
 ↓
Staff
 ↓
Staff login
 ↓
Select/scan table
 ↓
Open table session
 ↓
Create order
 ↓
Customer scans QR and orders
 ↓
Restaurant receives order
 ↓
Preparing
 ↓
Ready
 ↓
Served
 ↓
Cashier scans Table Tag
 ↓
Current bill appears
 ↓
Customer pays
 ↓
Receipt generated
 ↓
Session closes
 ↓
Table becomes available
 ↓
Sales recorded
 ↓
Expense recorded
 ↓
Reports updated
 ↓
Owner sees sales/expense/money summary
 ↓
Trial expires
 ↓
Owner subscribes
 ↓
Restaurant continues
```

## 38. Product Philosophy

### Simple
Staff should learn Sajio quickly.

### Fast
POS and ordering should require minimal clicks.

### Affordable
Pricing should suit Malaysian small businesses.

### Reliable
Restaurant operations cannot depend on a fragile system.

### Scalable
Architecture should support thousands of restaurants without rebuilding the platform.

## 39. Product Positioning

**Sajio.my**

> Simple POS. Simple Ordering. Simple Business.

Core positioning:

**One simple system for Malaysian restaurants to manage tables, orders, payments, sales and everyday money.**

Strong Pro differentiator:

**Table Tag + Fast POS Scan + QR/NFC-ready Table System**

## 40. Future Roadmap — After V1

Only consider after real customer validation:
- NFC table tags
- Thermal printer bridge
- Advanced inventory
- Supplier/purchasing
- Multi-branch
- Delivery
- Reservations
- Loyalty
- Customer CRM
- Marketing automation
- Malaysian e-Invoice integration
- Advanced analytics
- Native mobile apps
- Offline POS
- Payment gateway integration
- Accounting integrations
- API/integrations
- Hardware bundles
- AI features

**Do not build these before validating V1 with real restaurants.**
