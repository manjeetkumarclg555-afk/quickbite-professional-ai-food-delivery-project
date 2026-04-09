# AI/ML Conversion Guide

## Current state

This project currently implements AI logic in PHP:

- `includes/intelligence.php`
- `includes/ai_pipeline.php`

It uses:

- weighted recommendation scoring
- weighted route ETA prediction
- MySQL data from `orders`, `order_items`, `users`, `food_items`

This is good for a demo, but if you want real ML, the recommended design is:

1. Keep PHP for frontend and order workflow.
2. Move AI training and inference to separate ML services.
3. Expose prediction APIs from Python, Java, or Node.
4. Call those APIs from PHP.

---

## Best architecture

### Recommended production split

- PHP: UI, cart, checkout, admin dashboard
- MySQL: source data
- Python: model training and prediction
- REST API: communication between PHP and ML service

Why:

- Python has the strongest ML ecosystem
- TensorFlow and scikit-learn fit recommendation and ETA prediction well
- PHP should not be the training runtime

---

## What to convert first

### 1. Dish recommendation model

Current PHP logic uses:

- reorder history
- category affinity
- popularity
- similar-customer demand
- price-fit

Convert this into:

- collaborative filtering
- content-based recommendation
- learning-to-rank
- neural recommendation model

### 2. Route optimization model

Current PHP logic uses:

- distance
- prep time
- traffic
- weather
- order status
- zone load

Convert this into:

- regression model for ETA prediction
- classification model for delay risk
- route ranking service

---

## Python implementation

### Best use

Use Python as the main ML backend.

### Libraries

- `pandas`
- `numpy`
- `scikit-learn`
- `tensorflow`
- `fastapi` or `flask`
- `joblib`

### Recommendation model in Python

Use:

- `scikit-learn` for baseline recommendation scoring
- `TensorFlow` for deep recommendation later

Possible approaches:

- cosine similarity on user-item matrix
- matrix factorization
- neural embeddings

### Route model in Python

Use:

- `scikit-learn` regression models
- `xgboost` optional later
- `TensorFlow` only if dataset becomes large

Good first model:

- `RandomForestRegressor`
- `GradientBoostingRegressor`
- `XGBoostRegressor` if you add external dependency

### Python service flow

1. Export training data from MySQL.
2. Train recommendation and ETA models.
3. Save models as `.pkl` or TensorFlow SavedModel.
4. Expose `/recommend` and `/predict-eta` API endpoints.
5. PHP calls those endpoints.

### Example service layout

```text
ml-python/
  app.py
  train_recommendation.py
  train_eta.py
  models/
  data/
  requirements.txt
```

---

## scikit-learn implementation

### Best use

Use scikit-learn for:

- ETA prediction
- delay classification
- baseline recommendation ranking features

### Good models

For ETA:

- `LinearRegression`
- `RandomForestRegressor`
- `GradientBoostingRegressor`

For delay risk:

- `LogisticRegression`
- `RandomForestClassifier`
- `GradientBoostingClassifier`

### Input features for ETA

- `distance_km`
- `prep_time_minutes`
- `estimated_delivery_minutes`
- `traffic_score`
- `weather_score`
- `total_items`
- `delivery_zone_code`
- `order_hour`
- `zone_load`

### Model output

- predicted ETA minutes
- delay probability

---

## TensorFlow implementation

### Best use

Use TensorFlow when:

- recommendation data grows
- you want deep learning ranking
- you want embeddings or sequential recommendation

### Recommendation options

- embedding model for users and dishes
- two-tower recommendation model
- ranking network using user and item features

### ETA options

- dense regression network
- multi-output model for ETA and delay risk

### When not to use TensorFlow first

Do not start with TensorFlow if:

- dataset is still small
- you need fast implementation
- interpretability matters more than complexity

For this project, `scikit-learn first, TensorFlow later` is the best path.

---

## Java implementation

### Best use

Use Java if:

- you want enterprise microservices
- your backend team prefers JVM
- you need strong typed service architecture

### Libraries

- Spring Boot
- Smile
- Tribuo
- DeepLearning4J

### Java role in this project

Java is a good option for:

- serving prediction APIs
- business-rule orchestration
- integration with enterprise systems

It is not the easiest first choice for training compared to Python.

### Recommended Java structure

```text
ml-java/
  src/main/java/.../RecommendationController.java
  src/main/java/.../EtaController.java
  src/main/java/.../ModelService.java
```

Use Spring Boot REST endpoints:

- `POST /recommend`
- `POST /predict-eta`

---

## Node AI libraries implementation

### Best use

Use Node if:

- you want one JS-based service layer
- you need lightweight inference APIs
- frontend/backend team is already JavaScript-heavy

### Libraries

- `@tensorflow/tfjs`
- `brain.js`
- `ml-regression`
- `express`

### Good use cases

- serving trained models
- lightweight recommendation logic
- real-time API wrappers

### Limitation

Node is fine for inference and moderate ML, but Python is stronger for training pipelines.

### Recommended Node structure

```text
ml-node/
  server.js
  services/recommendation.js
  services/eta.js
  models/
  package.json
```

---

## How PHP should connect to ML service

### Keep PHP app unchanged for business flow

Your PHP pages should continue to handle:

- login
- menu
- cart
- order placement
- admin dashboard

### Replace direct AI functions

Replace:

- `fetch_dish_recommendations(...)`
- `predict_route_eta(...)`

With:

- PHP API client calls to ML service

### Example flow

1. `menu.php` sends user id and features to Python API.
2. Python returns recommended dishes.
3. `admin/dashboard.php` sends delivery features to ETA API.
4. API returns predicted ETA and delay risk.

---

## Recommended migration order

### Phase 1

- Keep current PHP AI as fallback
- Export training dataset
- Build Python `scikit-learn` ETA model
- Build Python recommendation baseline

### Phase 2

- Create FastAPI endpoints
- Replace PHP heuristic inference with API calls
- Log prediction requests and responses

### Phase 3

- Add TensorFlow recommendation model
- Add retraining pipeline
- Add model versioning

### Phase 4

- Optional Java or Node inference wrappers
- Optional microservice split

---

## Best choice for your project

If you want all technologies available, use them like this:

- Python: main ML training pipeline
- scikit-learn: first ETA and delay models
- TensorFlow: advanced recommendation model
- Java: enterprise serving layer if needed
- Node AI libraries: lightweight alternate inference gateway or frontend-friendly service

### Practical recommendation

For this project, the best real implementation path is:

1. `Python + scikit-learn` first
2. `TensorFlow` second
3. `Node` or `Java` only if you specifically need them

---

## What “all implement” should mean realistically

Do not train the same model separately in all five stacks for no reason.

Better:

- Train in Python
- Serve in Python first
- Optionally expose or mirror inference in Java or Node
- Keep PHP as the application layer

That is the technically correct architecture.

---

## If you want me to implement this next

I recommend this exact next step:

1. Create `ml-python/` service
2. Add `scikit-learn` ETA training script
3. Add recommendation API endpoint
4. Connect PHP `menu.php` and `admin/dashboard.php` to that API
5. Keep current PHP AI as fallback

That is the fastest correct conversion path.
