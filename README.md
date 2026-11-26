# iboltnetworks E-Commerce Platform

A high-performance, database-driven enterprise computer hardware and laptop accessories e-commerce platform built with **PHP 8.2+**, **MySQL / MariaDB**, **Vanilla JavaScript**, **HTML5**, and **CSS3**, featuring **Razorpay payment integration** and an administrative control panel.

---

## 1. System Architecture & Features

- **Storefront & Catalog:**
  - Dynamic product catalog (`products.php`) supporting hierarchical categories, hardware brands, condition filters (`New` vs. `Certified Refurbished`), keyword search, price sorting, and pagination.
  - Interactive mega navigation menus for **SERVERS**, **NETWORKING**, **COMPONENTS**, and **OUR PRODUCTS** preserving existing brand design and responsive layouts.
  - Detailed product view (`product.php`) with multi-image gallery, condition badges, quantity selector, technical specifications table, related products, and WhatsApp inquiry buttons.
- **Cart & Transactional Checkout:**
  - Session-backed shopping cart (`cart.php`) with real-time inventory validation and server-side price recalculation (client prices are never trusted).
  - Streamlined checkout pipeline (`checkout.php`) collecting shipping addresses with atomic row locks (`SELECT ... FOR UPDATE`) to prevent overselling.
- **Razorpay Payment Integration:**
  - Fully integrated payment flow (`payment-gateway.php` & `payment-verify.php`) supporting UPI, Debit/Credit Cards, NetBanking, and EMI.
  - Cryptographic HMAC-SHA256 signature verification.
  - Automated inventory stock deduction and audit logging upon captured payment.
  - Built-in test simulator mode for local development without live API keys.
- **Customer Authentication & Account Portal:**
  - Customer registration (`register.php`) and secure login (`login.php`) with `password_hash()` (Bcrypt) and session fixation protection.
  - Customer portal (`account/index.php`) featuring order history tracking (`account/orders.php`) and printable invoice breakdowns (`account/order-detail.php`).
- **Administrative Control Panel (`/admin/`):**

### Homepage & Product Management Hub (`/admin/`)

The admin panel includes dedicated management screens and distinct icons for each storefront section:

- **🏠 Homepage Master Overview (`admin/homepage/index.php`):**
  - Master view of all products placed across homepage sections.
  - Inline quick price editing (Selling Price ₹ & MRP) with instant synchronization across storefront, catalog, and checkout.
  - Live display order sorting and promotional badge editing (e.g. `Save 15%`, `Hot Deal`).
  - Visibility toggles (Live vs. Hidden) and modal to assign existing catalog items to any homepage section.

- **▤ Servers Section Manager (`admin/sections/servers.php`):**
  - Dedicated controller for enterprise rack/tower servers (Dell PowerEdge, HPE ProLiant, Cisco UCS, Huawei).
  - Quick price adjustments, spotlight banners, and server section display ordering.

- **🖧 Networking Section Manager (`admin/sections/networking.php`):**
  - Dedicated controller for enterprise switches, routers, firewalls, and fiber NICs.

- **▦ Components Section Manager (`admin/sections/components.php`):**
  - Dedicated controller for Intel Xeon/AMD EPYC CPUs, ECC server RAM, NVMe/SAS storage, and redundant PSUs.

- **🛍️ Our Products Section Manager (`admin/sections/our-products.php`):**
  - Dedicated controller for monitors, keyboards, mice, docking stations, and PC peripherals.

- **🏷️ Featured & Promotions Manager (`admin/sections/promotions.php`):**
  - Spotlight promotional products with calculated discount percentages and special deal tags.

- **📁 Category Management (`admin/categories/index.php`):**
  - Manage categories, slugs, parent hierarchy, active toggles, and section mappings.

- **🖼️ Flexible Multi-Image Gallery Manager (`admin/images/index.php`):**
  - Upload single or multiple images per product (totally defined by admin).
  - Client-side drag-and-drop / multi-file picker with instant image previews.
  - Set Primary Image (marked with ★ badge) for cards and listings.
  - Reorder gallery thumbnails, replace existing photos, and delete individual images without touching others.
  - Server-side MIME validation, 8MB file size limit, and automatic deletion of orphaned files from `uploads/products/`.

  - Protected admin dashboard (`admin/index.php`) with KPI analytics: Total Revenue, Orders Breakdown, Low Stock Alerts, and Customer Counts.
  - Complete Product CRUD (`admin/products/index.php` & `form.php`) allowing product creation, price management, stock adjustments, image assignment, and dynamic specification rows.
  - Order fulfillment management (`admin/orders/index.php` & `view.php`) supporting order status transitions (`pending` → `processing` → `shipped` → `delivered`), tracking carrier assignment (BlueDart, Delhivery, etc.), and tracking consignment numbers.

---

## 2. Directory Structure

```text
├── .env.example              # Template environment configuration
├── .env                      # Local environment settings (MAMP / Production)
├── .htaccess                 # Security rules (blocks .env & sensitive directories)
├── index.php                 # Homepage (modularized from index.html)
├── products.php              # Dynamic product catalog & filter engine
├── product.php               # Dynamic product detail & specs page
├── cart.php                  # Shopping cart controller & session management
├── checkout.php              # Shipping address & transactional order pipeline
├── payment-gateway.php       # Razorpay checkout & test simulator dispatcher
├── payment-verify.php        # HMAC signature verifier & stock decrementer
├── order-success.php         # Printable order receipt & confirmation view
├── login.php                 # Customer login controller
├── register.php              # Customer registration controller
├── logout.php                # Customer session destruction
├── style.css                 # Master site stylesheet
├── script.js                 # Primary navigation & interactive behaviors
│
├── account/                  # Customer Account Area
│   ├── index.php             # Account dashboard & recent orders
│   ├── orders.php            # Order history listing
│   └── order-detail.php      # Printable invoice & shipment tracking
│
├── admin/                    # Administrative Control Panel
│   ├── index.php             # Analytics dashboard & low stock warnings
│   ├── login.php             # Admin authentication controller
│   ├── logout.php            # Admin session destruction
│   ├── includes/             # Admin header & footer partials
│   ├── products/             # Product CRUD controllers (index, form, delete)
│   └── orders/               # Order inspection & fulfillment controllers (index, view)
│
├── app/                      # Application Core & Helpers
│   ├── config/
│   │   ├── config.php        # Zero-dependency .env parser & configuration loader
│   │   └── database.php      # Singleton PDO database connection manager
│   └── helpers/
│       ├── auth.php          # Session, role management, and access controls
│       ├── csrf.php          # CSRF token generation & validation
│       ├── sanitize.php      # XSS escaping (e()) & string sanitizers
│       └── currency.php      # Indian Rupee (INR) formatting & paise math
│
├── includes/                 # Shared Storefront Partials
│   ├── header.php            # Top header, dynamic cart badge, & search bar
│   ├── navigation.php        # Primary green navigation bar
│   ├── mega-menu.php         # 4 Mega menus (Servers, Networking, Components, Our Products)
│   └── footer.php            # Floating WhatsApp button & footer links
│
└── database/
    ├── schema.sql            # Normalized MySQL schema (13 tables)
    ├── seeders.sql           # Initial 121 products, 65 categories, 29 brands, & admin
    ├── build_seeders.py      # Automated seeder extraction script
    └── migrate.php           # Database migration & seeding runner
```

---

## 3. Monetary Representation Standard

To prevent floating-point rounding errors and comply with Razorpay's native API, **all monetary amounts in the database and session are stored as unsigned integers in PAISE** (1 INR = 100 paise):

- ₹1,499.00 = `149900` paise
- ₹95,000.00 = `9500000` paise
- ₹2,45,000.00 = `24500000` paise

Display formatting uses `format_inr(int $paise)` from `app/helpers/currency.php`, which formats numbers according to the standard Indian grouping format (e.g. `₹2,45,000`).

---

## 4. Local Development Setup (MAMP on macOS)

### Prerequisites
- PHP 8.2 or 8.3 CLI
- MAMP with MySQL running (Default port: `8889`, user: `root`, password: `root`)

### Installation Steps

1. **Configure Environment:**
   Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
   Ensure `.env` contains your local MAMP settings:
   ```ini
   APP_ENV=development
   APP_DEBUG=true
   APP_URL=http://localhost:8888

   DB_HOST=127.0.0.1
   DB_PORT=8889
   DB_NAME=iboltnetworks_db
   DB_USER=root
   DB_PASS=root
   DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock

   RAZORPAY_KEY_ID=rzp_test_placeholder
   RAZORPAY_KEY_SECRET=placeholder_secret_key
   RAZORPAY_CURRENCY=INR
   ```

2. **Run Database Migration & Seeding:**
   Execute the migration script:
   ```bash
   php database/migrate.php
   ```
   This creates `iboltnetworks_db` and seeds 121 enterprise hardware products, 65 categories, 29 brands, and the default super administrator account.

3. **Start Local Web Server:**
   You can run via MAMP Apache, or use the PHP built-in server:
   ```bash
   php -S localhost:8181
   ```
   Open `http://localhost:8181/index.php` in your browser.

---

## 5. Hostinger Shared Hosting Deployment Guide

### Step 1: Create Database in Hostinger hPanel
1. Log in to your Hostinger hPanel dashboard.
2. Navigate to **Databases** → **Management**.
3. Create a new MySQL database:
   - **Database Name:** e.g., `u123456789_ibolt`
   - **Username:** e.g., `u123456789_admin`
   - **Password:** Generate a strong password.

### Step 2: Import Database Tables & Seed Data
1. Open **phpMyAdmin** for your newly created database in hPanel.
2. Click **Import** tab.
3. Import `database/production_master_schema.sql` (Consolidated schema + 121-product catalog).
   Alternatively, you can import `database/schema.sql` followed by `database/seeders.sql`.
   Both methods are 100% portable with zero `CREATE DATABASE` or `USE` statements.

### Step 3: Upload Code to `public_html`
1. Upload and extract the verified release archive `iboltnetworks_hostinger_production_v1.zip` directly into `public_html/`.
2. See `HOSTINGER_DEPLOYMENT_GUIDE.md` for full step-by-step instructions.

### Step 4: Configure Production `.env`
Copy `.env.production.example` to `.env` in `public_html/` and supply your database credentials:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iboltnetworks.com

DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_ibolt
DB_USER=u123456789_admin
DB_PASS=YourStrongHostingerPasswordHere
DB_CHARSET=utf8mb4
DB_SOCKET=""

CONTACT_WHATSAPP="919911003865"
STORE_PHONE="+91 99110 03865"
STORE_EMAIL=sales@iboltnetworks.com
STORE_ADDRESS="Vishal Bhawan, Nehru Place, New Delhi, Delhi 110019"
```

### Step 5: Initialize Administrator Account
Because default compromised credentials have been removed, initialize your unique administrator account using:
- **Web Setup Wizard:** Navigate to `https://iboltnetworks.com/admin/setup-admin.php`
- **Or CLI Command:** `php database/create_admin.php "admin@iboltnetworks.com" "admin" "YourStrongPassword!" "System Administrator"`

---

## 6. Administrative Access

- **Admin Login URL:** `https://iboltnetworks.com/admin/login.php`
- Create your administrator credentials via the setup wizard (`admin/setup-admin.php`) during deployment. No hardcoded or publicly known passwords exist in production.

---

## 7. Razorpay Payment Gateway Testing

- **Development / Sandbox Mode:**
  When `RAZORPAY_KEY_ID` or `RAZORPAY_KEY_SECRET` contain `placeholder` in `.env`, the system runs in safe development simulation mode. You can test complete checkouts, inventory stock deductions, and invoice receipts without making real financial transactions.
- **Production Mode:**
  Provide your active Razorpay Key ID (`rzp_live_...` or `rzp_test_...`) and Key Secret in `.env`. The checkout page will automatically invoke the official Razorpay Checkout SDK popup.
