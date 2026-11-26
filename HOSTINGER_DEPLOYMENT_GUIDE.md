# Hostinger Shared Hosting Deployment Guide — IBOLTNETWORKS

This guide details the exact step-by-step procedure to deploy the **IBOLTNETWORKS** e-commerce platform onto **Hostinger Shared Hosting** (hPanel) without requiring code modifications.

---

## 1. Hosting Prerequisites & Environment

Before deploying, verify these settings in your **Hostinger hPanel**:

| Setting | Required Value | Where to Verify in hPanel |
| :--- | :--- | :--- |
| **PHP Version** | **PHP 8.2** or **PHP 8.3** (Recommended) | **hPanel → Advanced → PHP Configuration** |
| **PHP Extensions** | `pdo_mysql`, `mbstring`, `gd`, `curl`, `openssl`, `json`, `fileinfo` | **hPanel → PHP Extensions** |
| **Web Server** | Apache / LiteSpeed (Default) | Built into Hostinger Linux plans |
| **Document Root** | `public_html/` | **hPanel → Websites → Manage → File Manager** |
| **SSL / HTTPS** | Active (Let's Encrypt / Hostinger SSL) | **hPanel → Security → SSL** |

---

## 2. Step 1: Upload and Extract Deployment ZIP

1. Log into your **Hostinger hPanel**.
2. Navigate to **Websites → Manage → File Manager** (or connect via SFTP/FileZilla).
3. Open the **`public_html/`** directory.
4. Upload **`iboltnetworks_hostinger_deployment.zip`** into `public_html/`.
5. Right-click the uploaded ZIP file and select **Extract**.
6. Set the extraction destination to `. ` (the current `public_html/` directory).
7. Confirm that the application entry files are positioned directly in `public_html/`:
   ```text
   public_html/
   ├── .htaccess
   ├── .env.example
   ├── index.php
   ├── products.php
   ├── product.php
   ├── cart.php
   ├── checkout.php
   ├── account/
   ├── admin/
   ├── app/
   ├── assets/
   ├── database/
   ├── images/
   ├── includes/
   └── uploads/
   ```
8. Delete the `iboltnetworks_hostinger_deployment.zip` file from `public_html/` after successful extraction to reclaim storage.

---

## 3. Step 2: Create MySQL Database in hPanel

1. In hPanel, go to **Databases → MySQL Databases**.
2. Create a new database:
   - **Database Name:** e.g., `u123456789_ibolt`
   - **Username:** e.g., `u123456789_admin`
   - **Password:** Generate a strong, secure password.
3. Click **Create**.
4. Note down the **full database name**, **username**, and **password**. Hostinger prefixes usernames and database names with your account identifier (e.g., `u123456789_`).

---

## 4. Step 3: Import Master Database Schema

1. In hPanel, go to **Databases → phpMyAdmin** and click **Enter phpMyAdmin** next to your newly created database.
2. Select your database from the left-hand navigation pane.
3. Click the **Import** tab at the top.
4. Click **Choose File** and select:
   ```text
   database/production_master_schema.sql
   ```
   *(Located inside your extracted `public_html/database/` directory)*.
5. Keep character set as `utf-8` and format as `SQL`.
6. Click **Import** (or **Go**) at the bottom.
7. Verification: phpMyAdmin will confirm that all **18 tables** were created with initial reference categories, brands, homepage section assignments, and catalogue products.

---

## 5. Step 4: Configure Production `.env` File

1. In **File Manager**, navigate to `public_html/`.
2. Locate `.env.example`, make a copy, and rename the copy to **`.env`** (ensure the leading dot is present).
3. Open `.env` in the File Manager Code Editor and configure your credentials:

```dotenv
# ==============================================================================
# IBOLTNETWORKS - Hostinger Production Configuration
# ==============================================================================

APP_ENV=production
APP_DEBUG=false
APP_URL=https://iboltnetworks.com
APP_NAME="iboltnetworks"

# Hostinger MySQL Database Settings
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_ibolt
DB_USER=u123456789_admin
DB_PASS=YourGeneratedDatabasePassword
DB_CHARSET=utf8mb4
DB_SOCKET=

# Razorpay Credentials (Live or Test)
RAZORPAY_KEY_ID=rzp_live_your_actual_key_id
RAZORPAY_KEY_SECRET=your_actual_razorpay_secret
RAZORPAY_CURRENCY=INR

# Session Security
SESSION_LIFETIME=7200
SESSION_SECURE_COOKIE=true

# Store & WhatsApp Contact Details
STORE_EMAIL=sales@iboltnetworks.com
STORE_PHONE="+91 99110 03865"
CONTACT_WHATSAPP="919911003865"
STORE_ADDRESS="Vishal Bhawan, Nehru Place, New Delhi, Delhi 110019"

# Email / SMTP Configuration (Hostinger Webmail)
MAIL_DRIVER=smtp
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_ENCRYPTION=ssl
SMTP_USER=sales@iboltnetworks.com
SMTP_PASS=YourHostingerEmailPassword
MAIL_FROM_ADDRESS=sales@iboltnetworks.com
MAIL_FROM_NAME="iboltnetworks Support"
```

4. Save the file.

---

## 6. Step 5: Initialize the Administrator Account

For security, the database seed file intentionally excludes default admin passwords. Use the one-time secure setup wizard to create your admin account:

1. Open your browser and navigate to:
   ```text
   https://iboltnetworks.com/admin/setup-admin.php
   ```
2. Fill in your administrative details:
   - **Full Name:** e.g., System Administrator
   - **Username:** Your desired admin handle (e.g., `admin`)
   - **Email:** Your staff email address
   - **Password:** Minimum 10 characters with mixed case and digits
3. Click **Create Administrator Account & Lock Setup**.
4. The setup wizard permanently locks itself as soon as the first active administrator account is created.
5. You will be redirected to **`https://iboltnetworks.com/admin/login.php`** to sign in.

---

## 7. Step 6: Verify File Permissions

Ensure the writable upload directories have write access for the web server process:

- `public_html/uploads/` — Permissions `0755`
- `public_html/uploads/products/` — Permissions `0755`
- `public_html/uploads/products/thumbs/` — Permissions `0755`
- `public_html/app/storage/` — Permissions `0755`

*(Hostinger's File Manager allows you to right-click folders to check or change permissions).*

---

## 8. Post-Deployment Verification Checklist

Perform these tests on the live production domain:

- [ ] **Homepage (`/index.php`):** Renders header, navigation, mega menus, hero slider, and all 5 homepage sections (Servers, Networking, Components, Our Products, Featured).
- [ ] **Catalog (`/products.php`):** Categories, brand filters, condition filters, and search function properly.
- [ ] **Product Details (`/product.php?slug=...`):** High-resolution images render, specifications table populates, price displays correctly.
- [ ] **Cart & Checkout (`/cart.php` & `/checkout.php`):** Add to cart, quantity update, and shipping address collection function without errors.
- [ ] **WhatsApp Communication:** Clicking WhatsApp inquiry triggers prefilled chat to `+91 99110 03865`.
- [ ] **Admin Control Panel (`/admin/`):** Dashboard metrics, product editor, stock adjuster, categories, orders, activity logs, and mobile drawer menu all work smoothly.
- [ ] **HTTPS / Security:** Domain redirects HTTP to HTTPS automatically; direct access to `.env`, `app/`, and `database/` returns `403 Forbidden`.
