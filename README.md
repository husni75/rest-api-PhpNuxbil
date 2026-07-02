# PHPNuxBill REST API

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-blue.svg)](https://php.net)

A modern, fast, and structured REST API module for **PHPNuxBill** (Mikrotik & Radius Billing System). This API provides complete management capabilities for users, packages, customers, vouchers, and transactions, making it ideal for mobile apps (e.g., Android/iOS) and third-party integrations.

---

## 🚀 Features

- **Authentication System**: Secure JWT/Token-based authentication for both Admin users and Customers.
- **Dashboard & Statistics**: Retrieve real-time summaries and charts of billing activity.
- **Customer CRUD**: Manage subscriber profiles, active packages, and service statuses.
- **Service & Plan Management**: Configure billing packages (PPPoE/Hotspot), speed profiles (bandwidths), and IP pools.
- **Radius Integration**: Manage Network Access Servers (NAS).
- **Voucher Operations**: Generate and activate prepaid internet vouchers.
- **Transactions & Reports**: Access transaction records, daily reports, and monthly summaries.
- **Customer Portal support**: Dedicated endpoints for customer profile updates, balance check, transfer, and package activation.

---

## 📁 Directory Structure

```text
├── controllers/              # API Route Controllers (Admin, Auth, Customers, Plans, Vouchers, etc.)
├── .htaccess                 # URL rewrite rules for clean endpoint routing
├── ApiAuth.php               # Middleware for Admin/Customer authentication and session bootstrap
├── ApiLog.php                # API activities logging utility
├── ApiResponse.php           # Unified JSON response templates (200 OK, 400 Bad Request, etc.)
├── ApiRouter.php             # Custom lightweight regex-based API router
└── index.php                 # API main entrypoint & route registration
```

---

## 🛠️ Installation & Setup

1. **Upload Files**: Copy the contents of this repository into the `system/api/` folder of your PHPNuxBill installation:
   ```text
   your-phpnuxbill-installation/
   └── system/
       └── api/
           ├── controllers/
           ├── .htaccess
           ├── ApiAuth.php
           ├── ...
           └── index.php
   ```

2. **Configure Web Server**:
   - **Apache**: The included `.htaccess` file handles URL rewriting. Ensure `mod_rewrite` is enabled.
   - **Nginx**: Add the following location block to your server configuration to route all API calls to `index.php`:
     ```nginx
     location /system/api/ {
         try_files $uri $uri/ /system/api/index.php?_route=$uri&$args;
     }
     ```

3. **Verify API Access**:
   You can verify if the API is working by hitting the authentication check endpoint:
   `GET /system/api/auth/me` with your authorization headers.

---

## 🔒 Security & Authentication

Endpoints are protected by authorization middleware. Pass your API Token in the HTTP headers:

```http
Authorization: Bearer <your_api_token>
```

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](LICENSE).
