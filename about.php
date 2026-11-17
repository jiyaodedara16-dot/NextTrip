<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/about.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>NextTrip</title>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <?php
        $journey = [
        [
            'year'  => '2026',
            'title' => 'The Idea',
            'desc'  => 'The idea of NextTrip was developed to solve common problems faced by travellers while planning and organizing trips.',
            'icon'  => 'idea',
        ],
        [
            'year'  => '2026',
            'title' => 'Planning & Design',
            'desc'  => 'The system architecture, database, user roles, and website interface were designed.',
            'icon'  => 'design',
        ],
        [
            'year'  => '2026',
            'title' => 'Development',
            'desc'  => 'The core modules for tourists, administrators, and travel agents were developed using PHP and MySQL.',
            'icon'  => 'code',
        ],
        [
            'year'  => '2026',
            'title' => 'Smart Features',
            'desc'  => 'Smart destination recommendations, eco-friendly suggestions, and crowd prediction features were incorporated.',
            'icon'  => 'spark',
        ],
        [
            'year'  => '2026',
            'title' => 'NextTrip',
            'desc'  => 'NextTrip brings these features together into a single smart tourism platform.',
            'icon'  => 'flag',
        ],
        ];

        $team = [
        [
            'name'  => 'Bharti Odedara',
            'role'  => 'Backend Architecture & Database Developer',
            'blurb' => 'Interested in backend development, database design, and building the core structure behind web applications.',
            'photo' => 'assets/images/team/bharti.jpg',
        ],
        [
            'name'  => 'Diya Odedara',
            'role'  => 'UI & Front-End Designer',
            'blurb' => 'Passionate about creating clean, attractive, and user-friendly interfaces that make websites easy to explore.',
            'photo' => 'assets/images/team/diya.jpg',
        ],
        [
            'name'  => 'Jiya Odedara',
            'role'  => 'Front-End & Backend Developer',
            'blurb' => 'Interested in both front-end and backend development, with a focus on connecting design with functionality.',
            'photo' => 'assets/images/team/jiya.jpg',
        ],
        [
            'name'  => 'Bhima Odedara',
            'role'  => 'Research, Testing & Documentation',
            'blurb' => 'Interested in research, testing, and organizing project information to make the overall system more reliable and understandable.',
            'photo' => 'assets/images/team/bhima.jpg',
        ],
        ];

        function nt_icon($name) {
        $icons = [
            'compass' => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-2.5 5.5L9 12l3-3z" stroke-linejoin="round"/>',
            'route'   => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8 18h4a4 4 0 0 0 4-4V9a2 2 0 0 1 2-2"/>',
            'idea'    => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.5.4.8 1 .8 1.6v.5h5.4v-.5c0-.6.3-1.2.8-1.6A6 6 0 0 0 12 3z"/>',
            'design'  => '<path d="M4 20l4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20z"/><path d="M13.5 6.5l3 3"/>',
            'code'    => '<path d="M8 9l-4 3 4 3M16 9l4 3-4 3M13 6l-3 12"/>',
            'spark'   => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/>',
            'flag'    => '<path d="M5 21V4"/><path d="M5 5h11l-2.5 3.5L16 12H5"/>',
            'leaf'    => '<path d="M4 15c0-6 5-11 16-11-1 10-6 16-16 16 0-3 1-5 3-7 3-3 6-4 9-4"/>',
            'user'    => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20c1.2-3.6 4-5.4 7-5.4S17.8 16.4 19 20"/>',
        ];
        return isset($icons[$name]) ? $icons[$name] : '';
        }
    ?>

    <main class="about">

        <!-- ==================================================================
            HERO
            ================================================================== -->
        <section class="about-hero">
            <div class="container">
                <div class="about-hero__grid">
                    <span class="eyebrow about-hero__eyebrow">About NextTrip</span>
                    <h1 class="about-hero__title">Journey So <em>Far</em></h1>
                    <p class="about-hero__subtitle">
                        NextTrip is a Smart Tourism Management System designed to make
                        travel planning easier, smarter, and more convenient. Our
                        platform helps tourists discover destinations, plan trips,
                        find suitable travel options, and make informed decisions
                        using technology and intelligent recommendations.
                    </p>
                </div>
            </div>

            <div class="about-hero__curve">
            <svg viewBox="0 0 1440 70" preserveAspectRatio="none">
                <path d="M0,70 C360,0 1080,0 1440,70 L1440,70 L0,70 Z" fill="var(--background-color)"></path>
            </svg>
            </div>
        </section>

        <!-- ==================================================================
            VISION & MISSION
            ================================================================== -->
        <section class="section vm">
            <div class="container">
            <div class="vm__head reveal">
                <span class="eyebrow">Why We Exist</span>
                <h2 class="section-title mt-3">Our Vision &amp; Mission</h2>
            </div>

            <div class="vm__grid">
                <div class="vm-card" data-tilt data-tilt-max="7">
                <div class="vm-card__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><?= nt_icon('compass') ?></svg>
                </div>
                <div class="vm-card__label">Vision</div>
                <h3 class="vm-card__quote">&ldquo;Making every journey smarter and more meaningful.&rdquo;</h3>
                <p class="vm-card__text">
                    We envision a future where technology makes tourism easier,
                    more personalized, and more responsible while helping
                    travellers discover destinations with confidence.
                </p>
                </div>

                <div class="vm-card" data-tilt data-tilt-max="7">
                <div class="vm-card__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><?= nt_icon('route') ?></svg>
                </div>
                <div class="vm-card__label">Mission</div>
                <h3 class="vm-card__quote">&ldquo;Connecting travellers with smarter tourism solutions.&rdquo;</h3>
                <p class="vm-card__text">
                    Our mission is to provide an integrated platform that combines
                    destination information, smart recommendations, travel
                    planning, and tourism services to create a smooth and
                    convenient travel experience.
                </p>
                </div>
            </div>
            </div>
        </section>

        <!-- ==================================================================
            OUR JOURNEY (timeline)
            ================================================================== -->
        <section class="section section-alt journey">
            <div class="container">
                <div class="journey__head reveal">
                    <span class="eyebrow">Our Story</span>
                    <h2 class="section-title mt-3">Our Journey</h2>
                    <p class="section-subtitle mb-0">How NextTrip went from an idea to a working platform.</p>
                </div>

                <div class="journey__track">
                    <div class="journey__line" aria-hidden="true"></div>

                    <?php foreach ($journey as $i => $step): ?>
                    <?php $side = $i % 2 === 0 ? 'left' : 'right'; ?>
                    <div class="journey-item journey-item--<?= $side ?>">
                        <?php if ($side === 'right'): ?><div class="journey-item__spacer"></div><?php endif; ?>

                        <div class="journey-item__card">
                            <div class="journey-item__year"><?= htmlspecialchars($step['year']) ?></div>
                            <h3 class="journey-item__title"><?= htmlspecialchars($step['title']) ?></h3>
                            <p class="journey-item__desc"><?= htmlspecialchars($step['desc']) ?></p>
                        </div>

                        <div class="journey-item__marker">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= nt_icon($step['icon']) ?></svg>
                        </div>

                        <?php if ($side === 'left'): ?><div class="journey-item__spacer"></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ==================================================================
            MEET THE TEAM
            Hover cards: at rest each card shows only the member's tile/photo;
            on hover (or keyboard focus) a themed overlay slides up with the
            member's name, role, and the work they did on the project.
            ================================================================== -->
        <section class="section team" id="team">
            <div class="container">
                <div class="team__head reveal">
                    <span class="eyebrow">The People</span>
                    <h2 class="section-title mt-3">Meet the Team</h2>
                    <p class="section-subtitle mb-0">Four students, one idea — building a smarter and more responsible way to explore India.</p>
                </div>

                <div class="team__grid">
                    <?php foreach ($team as $i => $member): ?>
                    <div class="member-card reveal"
                        style="--card-accent: var(--about-icon-<?= ($i % 4) + 1 ?>);"
                        tabindex="0"
                        role="group"
                        aria-label="<?= htmlspecialchars($member['name']) ?>, <?= htmlspecialchars($member['role']) ?>">

                        <div class="member-card__media">
                        <img src="<?= htmlspecialchars($member['photo']) ?>"
                            alt="Photo of <?= htmlspecialchars($member['name']) ?>"
                            loading="lazy"
                            onerror="this.remove()">
                        <!-- <svg class="member-card__media-fallback" viewBox="0 0 24 24" fill="none" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></svg>-->
                        </div>

                        <div class="member-card__tag">
                        <span class="member-card__tag-name"><?= htmlspecialchars($member['name']) ?></span>
                        </div>

                        <div class="member-card__overlay">
                        <span class="member-card__role"><?= htmlspecialchars($member['role']) ?></span>
                        <h3 class="member-card__name"><?= htmlspecialchars($member['name']) ?></h3>
                        <p class="member-card__desc"><?= htmlspecialchars($member['blurb']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

    </main>

    <?php include 'includes/footer.php'; ?>

<script src="assets/js/navbar.js"></script>
<script src="assets/js/about.js"></script>
<script src="about.js" defer></script>
</body>
</html>