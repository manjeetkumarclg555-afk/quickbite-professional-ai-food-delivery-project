# Deployment Guide

## 1. Prerequisites

- PHP 8.x with `mysqli`
- MySQL or MariaDB
- XAMPP, WAMP, Laragon, or another local PHP stack

## 2. Database setup

1. Create or reset the target database environment.
2. Import `database.sql`.
3. Confirm the `food_delivery` schema contains `users`, `food_items`, `cart`, `orders`, `order_items`, and `notifications`.

## 3. Application configuration

Default connection values live in `includes/bootstrap.php`:

- `DB_HOST=localhost`
- `DB_USER=root`
- `DB_PASS=`
- `DB_NAME=food_delivery`

Override them with environment variables if your local stack differs.

## 4. Serving the app

Place the project under your web root, for example:

- `C:\xampp\htdocs\quickbite-professional-ai-food-delivery-project`

Then start Apache and MySQL from XAMPP and open:

- `http://localhost/quickbite-professional-ai-food-delivery-project/`

## 5. Admin verification flow

1. Log in with `admin@quickbite.test` / `admin123`.
2. Open `admin/dashboard.php` and verify route planning, zone performance, and live status controls.
3. Open `admin/ai-modeling.php` and verify dataset metrics and CSV export.

## 6. AI validation

Run:

```powershell
php scripts/run_ai_validation.php
```

Expected outcome:

- preprocessing returns historical rows
- route-model MAE stays within seeded tolerance
- recommendations render for seeded customers
- notifications persist correctly

## 7. Production hardening checklist

- move database credentials out of source and into environment variables
- replace seeded/demo credentials
- add CSRF protection for state-changing forms
- move AI inference into dedicated services if the dataset grows materially
- integrate SMS, email, or push gateways behind the notification service if real delivery messaging is required
