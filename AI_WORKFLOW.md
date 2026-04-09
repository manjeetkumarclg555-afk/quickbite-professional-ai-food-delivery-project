# QuickBite AI Workflow

## Data capture

- `order.php` stores basket totals, zone, ETA, distance, prep time, traffic, weather, and payment method.
- `admin/dashboard.php` updates delivery status and finalizes actual delivery minutes and ratings for delivered orders.
- `confirm_upi.php` marks successful UPI payments and emits a customer notification.

## Preprocessing pipeline

`includes/ai_pipeline.php` converts historical orders into model-ready rows with:

- temporal features
- customer repeat-behavior features
- basket composition features
- delivery timing and zone features
- traffic and weather features
- delay and on-time labels

The browser export endpoint is `admin/export-preprocessed-data.php`.

The delivery export path is:

```powershell
C:\xampp\php\php.exe scripts\preprocess_delivery_ai_dataset.php
```

The recommendation interaction export path is:

```powershell
C:\xampp\php\php.exe scripts\export_recommendation_ai_dataset.php
```

## Recommendation model

`scripts/train_ai_models.py` trains a Python hybrid recommendation model using:

- dish co-occurrence across delivered baskets
- category affinity from prior orders
- reorder strength for dishes already liked by the customer
- delivered-order popularity
- price-fit against the customer history

`includes/intelligence.php` passes the live customer history and dish catalog into `scripts/run_ai_inference.py`, then renders the ranked output in `menu.php`. If the Python artifact is missing, the PHP fallback ranker is used automatically.

## Route optimization model

`scripts/train_ai_models.py` also trains a Python ETA regression model using:

- estimated delivery minutes
- distance in km
- prep time
- order timing features
- customer history features
- basket value and composition
- traffic level
- weather condition

`includes/intelligence.php` sends active dispatch rows into `scripts/run_ai_inference.py`, receives ETA and priority predictions, and uses them in `admin/dashboard.php`. If the Python artifact is missing, the PHP fallback route scoring is used automatically.

## Training command

Run the full AI pipeline from the project root:

```powershell
C:\xampp\php\php.exe scripts\train_ai_models.php
```

This exports both datasets and writes trained artifacts into `exports/models`.

## Monitoring and validation

- `admin/ai-modeling.php` surfaces AI row counts, model readiness, coverage, and route-model metrics.
- `scripts/run_ai_validation.php` checks seeded-data accuracy, notification persistence, and basic latency.
