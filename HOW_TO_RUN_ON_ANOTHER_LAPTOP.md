# 🚀 How to Setup & Run Safdar Mobile Store (SMS) on Another Laptop

This guide explains how to quickly transfer and run the **Safdar Mobile Store (SMS)** storefront and POS system on any other Windows laptop or PC using **XAMPP**.

---

## 📋 Step 1: Install XAMPP on the New Laptop

1. If XAMPP is not already installed, download **XAMPP for Windows (PHP 8.1 or 8.2+)** from:
   👉 [https://www.apachefriends.org/download.html](https://www.apachefriends.org/download.html)
2. Install XAMPP into the standard directory: `C:\xampp`

---

## 📁 Step 2: Copy this Project Folder into `htdocs`

1. Copy this entire project folder (named `sms`).
2. Paste it directly into your XAMPP web root:
   📂 `C:\xampp\htdocs\sms\`

> [!TIP]
> Ensure the directory path looks like:
> `C:\xampp\htdocs\sms\index.php`
> `C:\xampp\htdocs\sms\secure-portal.php`
> `C:\xampp\htdocs\sms\admin\`
> `C:\xampp\htdocs\sms\backend\`

---

## ⚡ Step 3: One-Click Database Setup

Inside `C:\xampp\htdocs\sms\`:
1. Double-click **`1_SETUP_DATABASE.bat`**
2. The script will automatically:
   - Start Apache and MySQL services in the background.
   - Create the MySQL database `u423425124_SMS`.
   - Import all 33+ live products, 15+ sales invoices, users, settings, and logs.
   - Synchronize the JSON fallback engine for offline dual-parity.

---

## 🌐 Step 4: Open Website & Admin POS

Double-click **`2_LAUNCH_WEBSITE_AND_POS.bat`** (or open your browser manually):

| Portal | URL Link | Description |
|---|---|---|
| **🛒 Customer Storefront** | [http://localhost/sms/](http://localhost/sms/) | Public store, phone catalog, CCTV solutions & services |
| **🛡️ Secure Admin POS Portal** | [http://localhost/sms/secure-portal.php](http://localhost/sms/secure-portal.php) | Point of Sale, Inventory, Sales Invoices, Reports |
| **🗄️ phpMyAdmin (Database)** | [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/) | MySQL Database Management |

---

## 🔑 Login Credentials

### 1. Super Admin Account
- **Username / Email**: `admin` or `admin@admin.com`
- **Password**: `smz1234` or `SafdarAdmin@2026!`
- **Access**: Full unrestricted access to POS, Inventory, CCTV, Financial records, User management, Settings, and Audit logs.

### 2. Salesman / POS Operator Account
- **Username / Email**: `salesman` or `salesman@salesman.com`
- **Password**: `sale1234` or `SalesmanPos#2026!`
- **Access**: POS Terminal, invoice creation, and order checkout.

---

## 🛠️ Handy Shortcut Batch Files

We've provided 1-click batch files inside the folder:
- **`1_SETUP_DATABASE.bat`** : Initializes the database and starts services.
- **`2_LAUNCH_WEBSITE_AND_POS.bat`** : Starts services and opens both portals in your browser.
- **`OPEN_STOREFRONT.bat`** : Direct shortcut to the Customer Storefront.
- **`OPEN_ADMIN_POS.bat`** : Direct shortcut to the Admin POS Management Portal.

---

## ❓ Troubleshooting & FAQs

### Q1: Apache or MySQL does not start?
1. Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`) as Administrator.
2. Click **Start** next to **Apache** and **MySQL**.
3. If Port 80 or Port 3306 is occupied by Skype/IIS, you can change the Apache port in XAMPP config or stop conflicting software.

### Q2: What if MySQL is offline?
- The system has a built-in **Dual-Engine JSON Fallback**. Even if MySQL is stopped, the storefront and POS will automatically read and save data to `backend/data/*.json` without crashing!

### Q3: How to update data from live server in the future?
- Simply export your phpMyAdmin database from Hostinger as `u423425124_SMS.sql`, put it in `database/u423425124_SMS.sql`, and run `1_SETUP_DATABASE.bat` again.

---

&copy; Safdar Mobile Store (SMS). All Rights Reserved.
