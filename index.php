<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSS FILES LINK -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/hero-modern.css">
       <link rel="stylesheet" href="assets/css/home-motion.css">
     <!-- BOOTSTRAP ICONS LINK -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <title>Home</title>
</head>
<body>
    <!-- navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- main section -->
    <main data-scroll-container>

    <!-------------------- herosection section ----------------->
        <section class="hero" id="heroSection" aria-label="Explore India smartly">

            <!-- Two stacked background layers so we can crossfade opacity  -->
            <div class="hero-bg-layer is-active" id="heroBgA"
                style="background-image: url('../assets/images/hero/herosection_img_1.png');"></div>
            <div class="hero-bg-layer" id="heroBgB"></div>

            <div class="hero-overlay" aria-hidden="true"></div>

            <div class="hero-container">

            <div class="hero-content">
                <p class="hero-badge">
                <span class="hero-badge-icon" aria-hidden="true">&#9737;</span>
                Smart Tourism Platform &middot; India
                </p>

                <h1 class="hero-heading">
                Explore<br>
                <span class="hero-heading-accent">India</span><br>
                Smartly
                </h1>

                <p class="hero-description">
                Discover hidden gems, avoid crowded destinations, and plan your perfect
                journey with trusted travel agents &mdash; all in one place.
                </p>

                <div class="hero-cta-group">
                <a href="destinations.php" data-login-required data-login-target="destinations.php" class="hero-btn hero-btn-primary">Explore Destinations</a>
                <a href="packages.php" data-login-required data-login-target="packages.php" class="hero-btn hero-btn-secondary">Plan a Package</a>
                </div>

                <p class="hero-location">
                <span class="hero-location-rule" aria-hidden="true"></span>
                <span id="heroLocationText">Gir Forest &middot; Sasan, Gujarat</span>
                </p>
            </div>

            </div>

             <!-- Passive progress indicator only — not interactive, not a slider control  -->
            <div class="hero-dots" id="heroDots" aria-hidden="true"></div>



        </section>
        
        <!------------------- Numeric section ------------------------>
        <?php
            $destinationsCount = 0;
            $travellersCount   = 0;
            $agentsCount       = 0;
            $avgRating         = 0.0;
            
            $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM destinations WHERE status = 'Active'");
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $destinationsCount = (int) $row['total'];
            }
            
            $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $travellersCount = (int) $row['total'];
            }
            
            $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM agents");
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $agentsCount = (int) $row['total'];
            }
            
            $result = mysqli_query($conn, "SELECT AVG(rating) AS avg_rating FROM destination_reviews WHERE status = 'Approved'");
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $avgRating = $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 1) : 0.0;
            }
        ?>
        
        <section class="stats" id="statsSection" aria-label="Platform statistics">
            <div class="stats-container">
            
                <div class="stats-item" data-target="<?= $destinationsCount ?>" data-suffix="+">
                <p class="stats-number">
                    <span class="stats-count">0</span><span class="stats-suffix">+</span>
                </p>
                <p class="stats-label">Destinations</p>
                </div>
            
                <div class="stats-item" data-target="<?= $travellersCount ?>" data-suffix="+">
                <p class="stats-number">
                    <span class="stats-count">0</span><span class="stats-suffix">+</span>
                </p>
                <p class="stats-label">Happy Travellers</p>
                </div>
            
                <div class="stats-item" data-target="<?= $agentsCount ?>" data-suffix="+">
                <p class="stats-number">
                    <span class="stats-count">0</span><span class="stats-suffix">+</span>
                </p>
                <p class="stats-label">Travel Agents</p>
                </div>
            
                <div class="stats-item" data-target="<?= $avgRating ?>" data-suffix="&#9733;" data-decimals="1">
                <p class="stats-number">
                    <span class="stats-count">0</span><span class="stats-suffix">&#9733;</span>
                </p>
                <p class="stats-label">Avg Rating</p>
                </div>
            
            </div>
        </section>

        <!------------------------------------ About section ----------------------------->
        <section class="why-section">
        <div class="container why-grid">
            <div class="why-image-wrapper">
            <img src="assets/images/about_sec_homepage.avif" alt="Taj Mahal, India">
            <div class="why-image-badge">
                <div class="why-image-badge-icon">🌿</div>
                <div>
                <div class="why-image-badge-title">Beyond the Ordinary</div>
                <div class="why-image-badge-subtext">Discover what's waiting</div>
                </div>
            </div>
            </div>

            <div class="why-content">
            <span class="badge">Why We Started This</span>
            <h2 class="why-heading">
                Travel Like Someone<br>
                <span class="highlight">Who Already Knows the Way</span>
            </h2>
            <p>The best trips aren't planned by algorithms alone — they're shaped by people who've actually been there, who know which trail catches the best light. That's what we set out to build: a companion that feels less like an app, more like a friend who's one step ahead.</p>
            <p>From misty hill stations to sunbaked deserts, we help you find the India that doesn't show up in the first ten search results — and help keep it that way for the traveller who comes after you.</p>
            <a href="about.php" class="why-cta">Start Exploring →</a>
            </div>
        </div>
        </section>

        <!------------------------------------ Category Section ----------------------------->
        <?php
            $query = "
                SELECT c.category_id, c.category_name, c.description,
                    COUNT(dc.destination_id) AS destination_count
                FROM categories c
                LEFT JOIN destination_categories dc ON dc.category_id = c.category_id
                WHERE c.status = 'Active'
                GROUP BY c.category_id
                ORDER BY c.category_id ASC
            ";
            $result = mysqli_query($conn, $query);
            if (!$result) {
                die("Query failed: " . mysqli_error($conn));
            }

            // Icon + color mapping per category name
            $category_styles = [
                'Beaches'   => ['icon' => '🏖️', 'color' => 'blue'],
                'Mountains' => ['icon' => '⛰️', 'color' => 'yellow'],
                'Heritage'  => ['icon' => '🏛️', 'color' => 'gray'],
                'Nature'    => ['icon' => '🌳', 'color' => 'green'],
                'Religious' => ['icon' => '🛕', 'color' => 'orange'],
                'Adventure' => ['icon' => '🎡', 'color' => 'red'],
            ];
        ?>

        <section class="categories-section">
            <div class="container">
                <div class="section-header">
                    <span class="badge">Browse by Category</span>
                    <h2 class="section-title">Destination Categories</h2>
                </div>

                <div class="category-grid">
                    <?php while ($row = mysqli_fetch_assoc($result)):
                        $name = $row['category_name'];
                        $style = $category_styles[$name] ?? ['icon' => '📍', 'color' => 'gray'];
                    ?>
                        <div class="category-card category-<?php echo $style['color']; ?>">
                            <div class="category-icon"><?php echo $style['icon']; ?></div>
                            <h3 class="category-name"><?php echo htmlspecialchars($name); ?></h3>
                            <p class="category-count"><?php echo (int)$row['destination_count']; ?> destinations</p>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <!------------------------------------ gallery section -------------------------------->
        <?php
            $query = "
                SELECT
                    di.image_id,
                    di.image_path,
                    di.image_title,
                    di.alt_text,
                    d.destination_name
                FROM destination_images di
                INNER JOIN destinations d ON d.destination_id = di.destination_id
                WHERE di.status = 'Active'
                AND d.status = 'Active'
                ORDER BY di.is_primary DESC, d.destination_id ASC, di.display_order ASC
                LIMIT 24
            ";

            $result = mysqli_query($conn, $query);
            $galleryImages = mysqli_fetch_all($result, MYSQLI_ASSOC);
        ?>

        <section class="gallery-marquee" data-speed="0.55" data-hover-speed="0.12" aria-label="Photo gallery">
            <div class="gallery-marquee__head">
                <span class="gallery-marquee__eyebrow">Gallery</span>
                <h2 class="gallery-marquee__title">Explore Popular Destinations</h2>
                <p class="gallery-marquee__subtitle">
                Discover beatiful places, hidden gems, and unforgettable experiences from across India through our curated photo gallery.
                </p>
            </div>

            <div class="gallery-marquee__viewport">
                <div class="gallery-marquee__track" id="galleryTrack">
                <?php if (empty($galleryImages)): ?>
                    <!-- No active images yet — nothing renders; the section head still shows. -->
                <?php else: ?>
                    <?php foreach ($galleryImages as $img): ?>
                    <?php
                        $alt = htmlspecialchars($img['alt_text'] ?: $img['image_title'] ?: $img['destination_name'], ENT_QUOTES, 'UTF-8');
                        $caption = htmlspecialchars($img['image_title'] ?: $img['destination_name'], ENT_QUOTES, 'UTF-8');
                        $src = htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="gallery-marquee__item">
                        <img src="<?= $src ?>" alt="<?= $alt ?>" loading="lazy" decoding="async">
                        <span class="gallery-marquee__caption"><?= $caption ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </section>

            
        <!----------------------------------- Wy Choose US ------------------------------------->
        <section class="features-section">
        <div class="container">
            <div class="section-header">
            <span class="badge">Our Advantages</span>
            <h2 class="section-title">Why Choose Us?</h2>
            <p class="section-subtitle">
                Everything you need for a smarter, greener, more memorable trip to India.
            </p>
            </div>

            <div class="features-grid">

            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h3 class="feature-title">Verified Travel Agents</h3>
                <p class="feature-desc">All agents are background-checked and reviewed by thousands of happy travellers.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🏨</div>
                <h3 class="feature-title">Nearby Hotels</h3>
                <p class="feature-desc">Curated accommodations ranked by distance, price, and guest ratings.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🍽️</div>
                <h3 class="feature-title">Nearby Restaurants</h3>
                <p class="feature-desc">Discover authentic local cuisine near every destination with hygiene ratings.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🗺️</div>
                <h3 class="feature-title">Interactive Maps</h3>
                <p class="feature-desc">Live navigation, offline maps, and point-of-interest overlays for every route.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🎒</div>
                <h3 class="feature-title">Curated Travel Packages</h3>
                <p class="feature-desc">Handpicked itineraries bundling stays, transport, and experiences at the best value.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📍</div>
                <h3 class="feature-title">All Destination Information</h3>
                <p class="feature-desc">In-depth guides covering history, best seasons, local tips, and travel essentials.</p>
            </div>

            </div>
        </div>
        </section> 


        <!----------------------------------- Smart Fetures ------------------------------------->
        <section class="smart-features-section">
        <div class="container">
            <div class="section-header">
            <span class="badge">Powered by Technology</span>
            <h2 class="section-title">Smart Features</h2>
            </div>

            <div class="smart-features-grid">
            <div class="smart-feature-card">
                <div class="smart-feature-icon">📊</div>
                <h3 class="smart-feature-title">Crowd Level Estimation</h3>
                <p class="smart-feature-desc">Review-based crowd insights to help travellers identify less crowded visiting times.</p>
            </div>

            <div class="smart-feature-card">
                <div class="smart-feature-icon">🗺️</div>
                <h3 class="smart-feature-title">Smart Destination Discovery</h3>
                <p class="smart-feature-desc">Explore destinations with categories, search, filters, and detailed information.</p>
            </div>

            <div class="smart-feature-card">
                <div class="smart-feature-icon">⭐</div>
                <h3 class="smart-feature-title">Visitor Reviews & Ratings</h3>
                <p class="smart-feature-desc">Discover traveller experiences, ratings, and destination photographs.</p>
            </div>

            <div class="smart-feature-card">
                <div class="smart-feature-icon">🎒</div>
                <h3 class="smart-feature-title">Discover Tour Packages</h3>
                <p class="smart-feature-desc">Explore detailed packages with itineraries, inclusions, exclusions, and pricing.</p>
            </div>

            <div class="smart-feature-card">
                <div class="smart-feature-icon">📝</div>
                <h3 class="smart-feature-title">Simplified Booking Requests</h3>
                <p class="smart-feature-desc">Request package bookings with travel dates, traveller details, and coupon discounts.</p>
            </div>

            <div class="smart-feature-card">
                <div class="smart-feature-icon">🔒</div>
                <h3 class="smart-feature-title">Secure Online Payments</h3>
                <p class="smart-feature-desc">Enjoy safe and secure payments powered by Razorpay.</p>
            </div>
            </div>

            <div class="cta-block">
            <h2 class="cta-heading">
                Ready for Your<br>
                <span class="cta-highlight">Next Journey?</span>
            </h2>
            <p class="cta-subtext">Your next india trip starts here.</p>

            <div class="cta-buttons">
                <a href="destinations.php" data-login-required data-login-target="destinations.php" class="btn-primary-yellow">Explore Destinations</a>
                <a href="packages.php" data-login-required data-login-target="packages.php" class="btn-outline-white">Plan a Package</a>
            </div>
            </div>
        </div>
        </section>


    </main>
    <?php include 'includes/footer.php'; ?>

<script src="assets/js/navbar.js"></script>
<script src="assets/js/hero.js"></script>
<script src="assets/js/stats.js"></script>
<script src="assets/js/gallery.js"></script>
   <script src="assets/js/home-motion.js"></script>
</body>
</html>