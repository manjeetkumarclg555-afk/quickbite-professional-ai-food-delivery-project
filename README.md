# QuickBite Professional Food Delivery Platform

QuickBite is a professional PHP and MySQL food delivery project with customer ordering, delivery operations, historical order tracking, and AI-ready preprocessing for order and delivery modeling.

## What is included

- customer registration and login
- menu browsing with category filters
- cart management and checkout
- delivery zone selection with ETA estimation
- customer order history page
- admin dispatch and fulfillment dashboard
- AI modeling center with preprocessed training features
- personalized dish recommendations
- route optimization insights for active dispatch
- in-app notifications for order lifecycle updates
- browser CSV export and CLI dataset export script
- Python training and inference scripts for recommendations and route ETA

## Main pages

- `index.php` professional landing page
- `menu.php` menu ordering experience
- `cart.php` cart management
- `order.php` checkout with delivery profile generation
- `history.php` customer order history
- `admin/dashboard.php` delivery operations dashboard
- `admin/ai-modeling.php` AI preprocessing preview and workflow
- `admin/export-preprocessed-data.php` CSV dataset download
- `scripts/preprocess_delivery_ai_dataset.php` CLI export script
- `scripts/export_recommendation_ai_dataset.php` recommendation dataset export script
- `scripts/train_ai_models.py` Python model trainer
- `scripts/run_ai_inference.py` Python inference runner
- `scripts/train_ai_models.php` PHP orchestrator for training
- `scripts/run_ai_validation.php` AI validation and performance checks
- `docs/ARCHITECTURE.md` full-stack architecture
- `AI_WORKFLOW.md` AI workflow
- `DEPLOYMENT_GUIDE.md` deployment guide

## Database setup

1. Import `database.sql` into MySQL or MariaDB.
2. The script creates the `food_delivery` database and all seed data.
3. Seed data includes menu items, demo customers, historical orders, delivery signals, and delivered orders for AI preprocessing.

## Runtime setup

1. Serve the project through XAMPP, WAMP, Laragon, or the PHP built-in server.
2. Default database values are read from `includes/bootstrap.php`:
   - `DB_HOST=localhost`
   - `DB_USER=root`
   - `DB_PASS=`
   - `DB_NAME=food_delivery`
3. Change environment variables or edit `includes/bootstrap.php` if your credentials differ.

## Demo accounts

- Admin: `admin@quickbite.test` / `admin123`
- Customer demo: `aarav@quickbite.test` / `demo123`
- Additional seeded customers use the same demo password: `demo123`

## AI preprocessing and model workflow

The preprocessing pipeline combines order, customer, basket, and delivery fulfillment signals into a model-ready dataset. Features include:

- order hour and day-of-week
- weekend flag
- customer lifetime orders before current order
- customer average ticket before current order
- days since last order
- basket size and unique item count
- subtotal, fee, tax, and total basket value
- payment method and encoded payment method code
- delivery zone and encoded zone code
- distance, prep time, ETA, actual delivery time, and delay label
- traffic level and traffic score
- weather condition and weather score
- customer rating and final delivery status

## Export options

### Browser export

1. Log in as admin.
2. Open `admin/ai-modeling.php`.
3. Click `Download Preprocessed CSV`.

### CLI export

Run this from the project root:

```powershell
php scripts/preprocess_delivery_ai_dataset.php
```

This writes `exports/ai_training_dataset.csv`.

To export the recommendation interaction dataset:

```powershell
C:\xampp\php\php.exe scripts\export_recommendation_ai_dataset.php
```

This writes `exports/recommendation_training_dataset.csv`.

## Python AI models

QuickBite now includes two Python-backed AI models:

- a hybrid dish recommender trained from delivered order interactions
- a route ETA regression model trained from the delivery history dataset

To train both models end to end:

```powershell
C:\xampp\php\php.exe scripts\train_ai_models.php
```

This pipeline:

- exports the delivery dataset
- exports the recommendation dataset
- trains the Python models
- writes model artifacts into `exports/models`

If Python is not installed or `shell_exec` is disabled, the app falls back to the existing PHP heuristics so the site still works.

## Suggested modeling tasks

- predict actual delivery time
- classify on-time vs delayed orders
- identify risky delivery zones or hours
- model customer satisfaction from delivery signals
- estimate repeat-order likelihood from service experience

## Validation

Run this from the project root after importing the seeded database:

```powershell
php scripts/run_ai_validation.php
```

This checks preprocessing coverage, route-model accuracy, recommendation availability, notification persistence, and basic latency expectations.

## Notes

- New registered users are stored with hashed passwords.
- Seeded demo users rely on the lightweight fallback in `login.php` for easier local demo access.
- PHP CLI was not available in this environment while editing, so browser-based runtime validation should be done on your local PHP stack.
