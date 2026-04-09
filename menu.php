<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$selectedCity = current_delivery_city();
$cityOptions = delivery_city_options();
$recommendations = [];
if (is_logged_in()) {
    $recommendations = fetch_dish_recommendations($conn, (int) current_user()['id'], 4);
}

if ($recommendations !== []) {
    $allowedRecommendationIds = [];
    $allowedRecommendationResult = $conn->query(
        'SELECT f.id
         FROM food_items f
         INNER JOIN restaurants r ON r.id = f.restaurant_id
         WHERE r.city = "' . $conn->real_escape_string($selectedCity) . '"'
    );

    while ($allowedRow = $allowedRecommendationResult->fetch_assoc()) {
        $allowedRecommendationIds[(int) $allowedRow['id']] = true;
    }

    $recommendations = array_values(array_filter(
        $recommendations,
        static fn (array $dish): bool => isset($allowedRecommendationIds[(int) $dish['id']])
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_id'])) {
    require_login();

    $foodId = (int) ($_POST['food_id'] ?? 0);
    $qty = max(1, min(10, (int) ($_POST['qty'] ?? 1)));
    $userId = (int) current_user()['id'];

    $checkStmt = $conn->prepare('SELECT id, quantity FROM cart WHERE user_id = ? AND food_id = ? LIMIT 1');
    $checkStmt->bind_param('ii', $userId, $foodId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        $newQty = (int) $existing['quantity'] + $qty;
        $updateStmt = $conn->prepare('UPDATE cart SET quantity = ? WHERE id = ?');
        $updateStmt->bind_param('ii', $newQty, $existing['id']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        $insertStmt = $conn->prepare('INSERT INTO cart(user_id, food_id, quantity) VALUES(?, ?, ?)');
        $insertStmt->bind_param('iii', $userId, $foodId, $qty);
        $insertStmt->execute();
        $insertStmt->close();
    }

    flash('success', 'Item added to your cart.');
    redirect('menu.php');
}

$categoryFilter = trim($_GET['category'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');
$categories = [];
$selectedCityEscaped = $conn->real_escape_string($selectedCity);
$categoryResult = $conn->query(
    'SELECT DISTINCT f.category
     FROM food_items f
     INNER JOIN restaurants r ON r.id = f.restaurant_id
     WHERE r.city = "' . $selectedCityEscaped . '"
     ORDER BY f.category'
);
while ($category = $categoryResult->fetch_assoc()) {
    $categories[] = $category['category'];
}

$searchSuggestions = [];
$suggestionResult = $conn->query(
    'SELECT f.name, f.category, COALESCE(r.name, "QuickBite Kitchen") AS restaurant_name
     FROM food_items f
     INNER JOIN restaurants r ON r.id = f.restaurant_id
     WHERE r.city = "' . $selectedCityEscaped . '"
     ORDER BY f.name'
);
while ($suggestion = $suggestionResult->fetch_assoc()) {
    $searchSuggestions[] = [
        'label' => $suggestion['name'],
        'meta' => $suggestion['category'] . ' · ' . $suggestion['restaurant_name'],
    ];
}

if ($searchQuery !== '' || $categoryFilter !== '') {
    if ($searchQuery !== '' && $categoryFilter !== '') {
        $searchTerm = '%' . $searchQuery . '%';
        $stmt = $conn->prepare(
            'SELECT f.*, COALESCE(r.name, "QuickBite Kitchen") AS restaurant_name, COALESCE(r.location, "") AS restaurant_location, COALESCE(r.city, "") AS restaurant_city
             FROM food_items f
             INNER JOIN restaurants r ON r.id = f.restaurant_id
             WHERE r.city = ? AND (f.name LIKE ? OR f.description LIKE ?) AND f.category = ?
             ORDER BY f.id'
        );
        $stmt->bind_param('ssss', $selectedCity, $searchTerm, $searchTerm, $categoryFilter);
    } elseif ($searchQuery !== '') {
        $searchTerm = '%' . $searchQuery . '%';
        $stmt = $conn->prepare(
            'SELECT f.*, COALESCE(r.name, "QuickBite Kitchen") AS restaurant_name, COALESCE(r.location, "") AS restaurant_location, COALESCE(r.city, "") AS restaurant_city
             FROM food_items f
             INNER JOIN restaurants r ON r.id = f.restaurant_id
             WHERE r.city = ? AND (f.name LIKE ? OR f.description LIKE ?)
             ORDER BY f.id'
        );
        $stmt->bind_param('sss', $selectedCity, $searchTerm, $searchTerm);
    } else { // only category
        $stmt = $conn->prepare(
            'SELECT f.*, COALESCE(r.name, "QuickBite Kitchen") AS restaurant_name, COALESCE(r.location, "") AS restaurant_location, COALESCE(r.city, "") AS restaurant_city
             FROM food_items f
             INNER JOIN restaurants r ON r.id = f.restaurant_id
             WHERE r.city = ? AND f.category = ?
             ORDER BY f.id'
        );
        $stmt->bind_param('ss', $selectedCity, $categoryFilter);
    }
    $stmt->execute();
    $foods = $stmt->get_result();
} else {
    $stmt = null;
    $foods = $conn->query(
        'SELECT f.*, COALESCE(r.name, "QuickBite Kitchen") AS restaurant_name, COALESCE(r.location, "") AS restaurant_location, COALESCE(r.city, "") AS restaurant_city
         FROM food_items f
         INNER JOIN restaurants r ON r.id = f.restaurant_id
         WHERE r.city = "' . $selectedCityEscaped . '"
         ORDER BY f.id'
    );
}

render_header('Menu');
?>

<section class="section-heading">
    <div>
        <p class="eyebrow">Order now</p>
        <h1>Curated menu for lunch, dinner, and cravings in between</h1>
        <form class="delivery-location-form delivery-location-form-inline" method="post" action="<?php echo h(app_path('menu.php')); ?>">
            <label class="delivery-location-label" for="menu-delivery-city">Delivery location</label>
            <div class="location-select-shell">
                <span class="location-select-icon" aria-hidden="true"></span>
                <select name="delivery_city" id="menu-delivery-city" class="location-select-input" onchange="this.form.submit()">
                    <?php foreach ($cityOptions as $city): ?>
                        <option value="<?php echo h($city); ?>" <?php echo $selectedCity === $city ? 'selected' : ''; ?>>
                            <?php echo h($city); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <form class="search-container search-container-wide" method="get">
            <?php if ($categoryFilter !== ''): ?>
                <input type="hidden" name="category" value="<?php echo h($categoryFilter); ?>">
            <?php endif; ?>
            <div class="search-box">
                <input
                    class="search-input"
                    id="menu-search"
                    type="search"
                    name="search"
                    value="<?php echo h($searchQuery); ?>"
                    placeholder="Search menu items..."
                    autocomplete="off"
                >
                <div class="search-suggestions" id="menu-search-suggestions" hidden>
                    <?php foreach ($searchSuggestions as $suggestion): ?>
                        <button
                            class="search-suggestion"
                            type="button"
                            data-value="<?php echo h($suggestion['label']); ?>"
                            data-meta="<?php echo h($suggestion['meta']); ?>"
                        >
                            <span><?php echo h($suggestion['label']); ?></span>
                            <span class="search-suggestion-meta"><?php echo h($suggestion['meta']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($searchQuery !== ''): ?>
                <a class="filter-pill" href="<?php echo $categoryFilter ? 'menu.php?category=' . urlencode($categoryFilter) : 'menu.php'; ?>">Clear</a>
            <?php else: ?>
                <button class="button primary" type="submit">Search</button>
            <?php endif; ?>
        </form>
    </div>
    <div class="filter-row">
        <a class="filter-pill <?php echo $categoryFilter === '' && $searchQuery === '' ? 'active' : ''; ?>" href="menu.php">All</a>
        <?php foreach ($categories as $category): ?>
            <a class="filter-pill <?php echo $categoryFilter === $category && $searchQuery === '' ? 'active' : ''; ?>" href="menu.php?category=<?php echo urlencode($category); ?>">
                <?php echo h($category); ?>
            </a>
        <?php endforeach; ?>
        
        <?php if ($searchQuery !== ''): ?>
            <span class="filter-pill active">Searching "<?php echo h($searchQuery); ?>"</span>
        <?php endif; ?>
    </div>
</section>



<?php if ($recommendations !== []): ?>
    <section class="page-banner" id="menu-ai-panel" hidden>
        <div class="section-heading-tight">
            <div>
                <p class="eyebrow">Personalized Picks</p>
                <h2>Dish recommendations tuned from your order history</h2>
                <p class="hero-text">QuickBite ranks dishes using category affinity, reorder signals, similar-customer demand, and price-fit scoring.</p>
            </div>
        </div>
        <div class="recommendation-grid">
            <?php foreach ($recommendations as $dish): ?>
                <article class="recommendation-card">
                    <img src="<?php echo h(resolve_food_image((string) $dish['image'], (string) $dish['name'], (string) $dish['category'])); ?>" alt="<?php echo h($dish['name']); ?>" loading="lazy">
                    <div>
                        <div class="menu-meta">
                            <span class="tag"><?php echo h($dish['category']); ?></span>
                            <span class="price"><?php echo h(format_price((float) $dish['price'])); ?></span>
                        </div>
                        <h3><?php echo h($dish['name']); ?></h3>
                        <p><?php echo h($dish['description']); ?></p>
                        <p class="muted"><?php echo h($dish['reason']); ?> · Score <?php echo h(number_format((float) $dish['score'] * 100, 1)); ?></p>
                        <form class="menu-form" method="post">
                            <input type="hidden" name="food_id" value="<?php echo (int) $dish['id']; ?>">
                            <input type="hidden" name="qty" value="1">
                            <button class="button secondary" type="submit">Add Recommended Dish</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="menu-grid">
<?php if ($foods->num_rows === 0): ?>
    <div class="empty-state" style="grid-column: 1 / -1;">
        <p>No menu items match your search or filters.</p>
        <a class="button primary" href="menu.php">Browse full menu</a>
        <?php if ($searchQuery !== ''): ?>
            <a class="button ghost" href="<?php echo $categoryFilter ? 'menu.php?category=' . urlencode($categoryFilter) : 'menu.php'; ?>">Clear search</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php while ($row = $foods->fetch_assoc()): ?>
        <article class="menu-card">
         
         <div class="menu-image">
    <img src="<?php echo h(resolve_food_image((string) $row['image'], (string) $row['name'], (string) $row['category'])); ?>" alt="<?php echo h($row['name']); ?>" width="200" height="150">
</div>
            <div class="menu-body">
                <div class="menu-meta">
                    <span class="tag"><?php echo h($row['category']); ?></span>
                    <span class="price"><?php echo h(format_price((float) $row['price'])); ?></span>
                </div>
                <h2><?php echo h($row['name']); ?></h2>
                <p class="menu-restaurant"><?php echo h($row['restaurant_name']); ?><?php echo $row['restaurant_location'] !== '' ? ' · ' . h($row['restaurant_location']) : ''; ?></p>
                <div class="menu-description-block">
                    <p class="menu-description" data-description><?php echo h($row['description']); ?></p>
                    <button class="menu-description-toggle" type="button" data-description-toggle hidden>Read more</button>
                </div>
                <form class="menu-form" method="post">
                    <input type="hidden" name="food_id" value="<?php echo (int) $row['id']; ?>">
                    <input type="number" name="qty" min="1" max="10" value="1">
                    <button class="button primary" type="submit">Add to Cart</button>
                </form>
            </div>
        </article>
    <?php endwhile; ?>
<?php endif; ?>
</section>
<?php
if ($stmt instanceof mysqli_stmt) {
    $stmt->close();
}
?>
<script>
(() => {
    const input = document.getElementById('menu-search');
    const aiPanel = document.getElementById('menu-ai-panel');
    const dropdown = document.getElementById('menu-search-suggestions');

    if (!input) {
        return;
    }

    const suggestions = dropdown ? Array.from(dropdown.querySelectorAll('.search-suggestion')) : [];
    let dropdownOpen = false;
    const maxSuggestions = 6;

    const escapeForRegex = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    const buildMatcher = (query) => {
        const normalized = query.trim().toLowerCase();
        if (normalized === '') {
            return null;
        }

        const lettersOnly = normalized.replace(/\s+/g, '');
        const fuzzyPattern = lettersOnly.split('').map(escapeForRegex).join('.*');
        return new RegExp(fuzzyPattern, 'i');
    };

    const getSuggestionScore = (query, label, meta) => {
        const normalizedQuery = query.trim().toLowerCase();
        if (normalizedQuery === '') {
            return -1;
        }

        const normalizedLabel = label.toLowerCase();
        const normalizedMeta = meta.toLowerCase();
        const matcher = buildMatcher(normalizedQuery);
        const words = normalizedLabel.split(/[^a-z0-9]+/i).filter(Boolean);

        if (normalizedLabel === normalizedQuery) {
            return 2000;
        }

        if (normalizedLabel.startsWith(normalizedQuery)) {
            return 1500 - normalizedLabel.length;
        }

        if (words.some((word) => word.startsWith(normalizedQuery))) {
            return 1200 - normalizedLabel.length;
        }

        if (normalizedLabel.includes(normalizedQuery)) {
            return 900 - normalizedLabel.indexOf(normalizedQuery);
        }

        if (normalizedMeta.startsWith(normalizedQuery)) {
            return 700 - normalizedMeta.length;
        }

        if (matcher && matcher.test(normalizedLabel)) {
            return 500 - normalizedLabel.length;
        }

        if (matcher && matcher.test(`${normalizedLabel} ${normalizedMeta}`)) {
            return 300 - normalizedLabel.length;
        }

        return -1;
    };

    const updateSuggestions = () => {
        if (!dropdown) {
            return;
        }

        const rankedSuggestions = suggestions.map((item) => {
            const label = item.dataset.value || '';
            const meta = item.dataset.meta || '';
            return {
                item,
                score: getSuggestionScore(input.value, label, meta),
                label,
            };
        }).sort((left, right) => {
            if (right.score !== left.score) {
                return right.score - left.score;
            }
            return left.label.localeCompare(right.label);
        });

        let visibleCount = 0;
        const fragment = document.createDocumentFragment();

        rankedSuggestions.forEach((entry) => {
            const visible = entry.score >= 0 && visibleCount < maxSuggestions;
            entry.item.hidden = !visible;
            if (visible) {
                visibleCount++;
            }
            fragment.appendChild(entry.item);
        });

        dropdown.replaceChildren(fragment);
        dropdown.hidden = !dropdownOpen || visibleCount === 0;
    };

    const openDropdown = () => {
        if (aiPanel) {
            aiPanel.hidden = false;
        }
        if (dropdown) {
            dropdownOpen = true;
            updateSuggestions();
        }
    };

    const closeDropdown = () => {
        if (aiPanel) {
            aiPanel.hidden = true;
        }
        if (dropdown) {
            dropdownOpen = false;
            dropdown.hidden = true;
        }
    };

    closeDropdown();

    input.addEventListener('focus', openDropdown);
    input.addEventListener('click', openDropdown);
    input.addEventListener('input', () => {
        dropdownOpen = true;
        if (aiPanel) {
            aiPanel.hidden = false;
        }
        updateSuggestions();
    });

    suggestions.forEach((item) => {
        item.addEventListener('click', () => {
            input.value = item.dataset.value || '';
            if (dropdown) {
                dropdown.hidden = true;
            }
            input.form.submit();
        });
    });

    document.addEventListener('click', (event) => {
        if (event.target !== input && (!dropdown || !dropdown.contains(event.target))) {
            closeDropdown();
        }
    });
})();
</script>
<script>
(() => {
    const descriptionBlocks = Array.from(document.querySelectorAll('.menu-description-block'));

    descriptionBlocks.forEach((block) => {
        const description = block.querySelector('[data-description]');
        const toggle = block.querySelector('[data-description-toggle]');

        if (!(description instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement)) {
            return;
        }

        const setExpanded = (expanded) => {
            block.classList.toggle('is-expanded', expanded);
            toggle.textContent = expanded ? 'Show less' : 'Read more';
        };

        const shouldShowToggle = description.scrollHeight > description.clientHeight + 4;
        if (!shouldShowToggle) {
            return;
        }

        toggle.hidden = false;
        setExpanded(false);

        toggle.addEventListener('click', () => {
            setExpanded(!block.classList.contains('is-expanded'));
        });
    });
})();
</script>
<?php render_footer(); ?>
