# Safdar Mobile Store - Database Deployment Guide

This directory contains the production-ready MySQL database schemas and full data dumps for **Safdar Mobile Store (SMZ POS & E-Commerce Website)**.

---

## 📁 Database Files

1. **`schema.sql`** (Recommended)
   - Complete Database Schema + Full Production Seed Data (Users, Products, Sales, Expenses, Customers, Suppliers, Settings).
2. **`safdar_mobile_store.sql`**
   - Direct standalone database export for 1-click cPanel / phpMyAdmin import.

---

## 🚀 How to Import to Production (cPanel / phpMyAdmin / VPS)

### Method A: Using phpMyAdmin (Recommended for Shared Hosting / cPanel)
1. Log into your hosting **cPanel** and click **phpMyAdmin**.
2. Click **Import** in the top navigation menu.
3. Click **Choose File** and select `database/schema.sql` (or `database/safdar_mobile_store.sql`).
4. Click **Go** at the bottom.
5. All tables (`users`, `products`, `sales`, `expenses`, `customers`, `suppliers`, `settings`, `categories`, `brands`, `audit_logs`) will be created and populated instantly!

### Method B: Using MySQL Command Line (VPS / Dedicated Server)
```bash
mysql -u root -p < database/schema.sql
```

---

## 🔐 Default Admin Credentials

- **Super Admin Username**: `admin`
- **Super Admin Password**: `smz1234`
- **Salesman Account Username**: `salesman`
- **Salesman Account Password**: `sale1234`

---

## ⚙️ Configuration in `backend/config.php`

When deploying to live hosting (**safdarmobilestore.com** on Hostinger), database connection details in `backend/config.php` are configured as:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u423425124_SMS');
define('DB_USER', 'u423425124_SMS');
define('DB_PASS', 'SafdarAdmin#2026!');
```
