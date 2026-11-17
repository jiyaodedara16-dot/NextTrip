<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
require_once 'contact-db.php';

$currentUserId  = (int) ($_SESSION['user_id'] ?? 0);
$contactProfile = $currentUserId > 0 ? get_contact_profile($currentUserId) : null;
$isLoggedIn     = $contactProfile !== null;

$siteReviews     = get_site_reviews(12);
$siteReviewStats = get_site_review_stats();

/** Five small stars, filled up to $n. */
function contact_stars(int $n): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="bi ' . ($i <= $n ? 'bi-star-fill' : 'bi-star') . '"></i>';
    }
    return $out;
}

// The 4 quick-option cards in the hero — clicking one pre-selects this
// value in the form's Enquiry Type dropdown and scrolls down to the form.
$quickOptions = [
    ['type' => 'plan_trip',    'emoji' => '🗺️', 'title' => 'Plan my trip',    'desc' => 'Need help choosing destinations?',     'accent' => '1'],
    ['type' => 'booking_help', 'emoji' => '🏨', 'title' => 'Booking help',    'desc' => 'Questions about hotels/packages?',     'accent' => '2'],
    ['type' => 'general',      'emoji' => '💬', 'title' => 'General enquiry', 'desc' => 'Something you want to ask?',           'accent' => '3'],
    ['type' => 'report_issue', 'emoji' => '🐛', 'title' => 'Report an issue', 'desc' => "Found something that isn't working?", 'accent' => '4'],
];

// "Quick Help" — self-serve answers before someone files an enquiry.
// Items with a 'type' scroll down to the form with that type pre-selected.
$quickHelp = [
    ['icon' => 'bi-compass',   'q' => 'How can I explore destinations?', 'label' => 'Explore Destinations', 'href' => 'destinations.php'],
    ['icon' => 'bi-box-seam',  'q' => 'How can I book a package?',       'label' => 'View Packages',        'href' => 'packages.php'],
    ['icon' => 'bi-chat-dots', 'q' => 'Have a question for us?',         'label' => 'Ask Us',               'href' => '#contactForm', 'type' => 'general'],
    ['icon' => 'bi-headset',   'q' => 'Need help with your booking?',    'label' => 'Get Support',          'href' => '#contactForm', 'type' => 'booking_help'],
];

$fbOptions = [
    ['type' => 'feature',     'emoji' => '💡', 'label' => 'Suggest a Feature'],
    ['type' => 'destination', 'emoji' => '📍', 'label' => 'Suggest a Destination'],
    ['type' => 'feedback',    'emoji' => '⭐', 'label' => 'Share Feedback'],
    ['type' => 'problem',     'emoji' => '🐛', 'label' => 'Report a Problem'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/contact.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Contact Us — NextTrip</title>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="contact">

        <!-- ==================================================================
            HERO — "Let's Plan Your Next Journey" + 4 quick-option cards
            ================================================================== -->
        <section class="contact-hero">
            <div class="container">
                <div class="contact-hero__grid">
                    <span class="eyebrow">Get In Touch</span>
                    <h1 class="contact-hero__title">Let&rsquo;s Plan Your Next <em>Journey</em></h1>
                    <p class="contact-hero__subtitle">
                        Have a question, need help with a trip, or found something we should improve? We&rsquo;re here to help.
                    </p>
                </div>

                <div class="contact-quick-grid">
                    <?php foreach ($quickOptions as $opt): ?>
                    <button type="button" class="contact-quick-card" data-enquiry-type="<?= htmlspecialchars($opt['type']) ?>">
                        <span class="contact-quick-card__icon" data-accent="<?= $opt['accent'] ?>"><?= $opt['emoji'] ?></span>
                        <span class="contact-quick-card__title"><?= htmlspecialchars($opt['title']) ?></span>
                        <span class="contact-quick-card__desc"><?= htmlspecialchars($opt['desc']) ?></span>
                        <i class="bi bi-arrow-down-short contact-quick-card__arrow" aria-hidden="true"></i>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="contact-hero__curve" aria-hidden="true">
                <svg viewBox="0 0 1440 70" preserveAspectRatio="none">
                    <path d="M0,70 C360,0 1080,0 1440,70 L1440,70 L0,70 Z" fill="var(--background-color)"></path>
                </svg>
            </div>
        </section>

        <!-- ==================================================================
            MAIN CONTACT FORM + GET IN TOUCH INFO
            ================================================================== -->
        <section class="section contact-form-section" id="contactForm">
            <div class="container">
                <div class="contact-grid">

                    <div class="contact-card contact-card--form" id="contactFormCard">
                        <div id="contactFormPanel">
                            <h2 class="contact-card__title">Send Us a Message</h2>
                            <p class="contact-card__subtitle">Pick a topic above or just write in — either way we&rsquo;ll get back to you.</p>

                            <form id="contactMainForm" novalidate>
                                <?php if ($isLoggedIn): ?>
                                <p class="contact-form__signed-in">
                                    <i class="bi bi-person-check-fill"></i>
                                    <span>Sending as <strong><?= htmlspecialchars($contactProfile['name']) ?></strong>
                                    (<?= htmlspecialchars($contactProfile['email']) ?>)</span>
                                </p>
                                <?php else: ?>
                                <div class="contact-form__row">
                                    <label class="contact-field">
                                        <span>Your Name</span>
                                        <input type="text" name="name" required maxlength="100" placeholder="e.g. Jiya Odedara" autocomplete="name">
                                    </label>
                                    <label class="contact-field">
                                        <span>Email Address</span>
                                        <input type="email" name="email" required maxlength="150" placeholder="you@example.com" autocomplete="email">
                                    </label>
                                </div>
                                <label class="contact-field">
                                    <span>Phone <em>(optional)</em></span>
                                    <input type="tel" name="phone" maxlength="20" placeholder="+91 90000 00000" autocomplete="tel">
                                </label>
                                <?php endif; ?>

                                <label class="contact-field">
                                    <span>Enquiry Type</span>
                                    <select name="enquiry_type" id="contactEnquiryType">
                                        <option value="plan_trip">🗺️ Plan my trip</option>
                                        <option value="booking_help">🏨 Booking help</option>
                                        <option value="general" selected>💬 General enquiry</option>
                                        <option value="report_issue">🐛 Report an issue</option>
                                    </select>
                                </label>

                                <label class="contact-field">
                                    <span>Message</span>
                                    <textarea name="message" rows="5" required maxlength="2000" placeholder="Tell us a bit more..."></textarea>
                                </label>

                                <button type="submit" class="contact-btn contact-btn--primary">
                                    <span class="contact-btn__label">Send Message</span>
                                    <i class="bi bi-send contact-btn__icon" aria-hidden="true"></i>
                                    <span class="contact-btn__spinner" aria-hidden="true"></span>
                                </button>
                            </form>
                        </div>

                        <div class="contact-success" id="contactSuccessPanel" hidden>
                            <div class="contact-success__icon">🌿</div>
                            <h3>Thanks for reaching out!</h3>
                            <p>Your enquiry has been received. Our team will get back to you soon.</p>
                            <button type="button" class="contact-btn contact-btn--ghost" id="contactSendAnother">Send another message</button>
                        </div>
                    </div>

                    <div class="contact-info">
                        <div class="contact-card">
                            <h3 class="contact-card__title">Get in Touch</h3>
                            <ul class="contact-info-list">
                                <li>
                                    <span class="contact-info-list__icon"><i class="bi bi-envelope-fill"></i></span>
                                    <div>
                                        <strong>Email</strong>
                                        <a href="mailto:nexttrip@gmail.com">nexttrip@gmail.com</a>
                                    </div>
                                </li>
                                <li>
                                    <span class="contact-info-list__icon"><i class="bi bi-telephone-fill"></i></span>
                                    <div>
                                        <strong>Phone</strong>
                                        <a href="tel:+917923289000">+91 79 2328 9000</a>
                                    </div>
                                </li>
                                <li>
                                    <span class="contact-info-list__icon"><i class="bi bi-geo-alt-fill"></i></span>
                                    <div>
                                        <strong>Office</strong>
                                        <span>Tourism Corporation of Gujarat Ltd., Ahmedabad</span>
                                    </div>
                                </li>
                                <li>
                                    <span class="contact-info-list__icon"><i class="bi bi-clock-fill"></i></span>
                                    <div>
                                        <strong>Hours</strong>
                                        <span>Mon–Sat, 9:00 AM – 6:30 PM IST</span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="contact-reply-card">
                            <div class="contact-reply-card__badge"><i class="bi bi-lightning-charge-fill"></i></div>
                            <div class="contact-reply-card__body">
                                <h4>We reply quickly</h4>
                                <p>Most enquiries get an answer within <strong>24&ndash;48 hours</strong>. For anything urgent about a live booking, a call is fastest.</p>
                                <a class="contact-reply-card__link" href="tel:+917923289000"><i class="bi bi-telephone-outbound"></i> Call us now</a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==================================================================
            QUICK HELP — self-serve answers before filing an enquiry
            ================================================================== -->
        <section class="section section-alt">
            <div class="container">
                <div class="contact-section-head">
                    <span class="eyebrow">Before You Contact Us</span>
                    <h2 class="section-title mt-3">Quick Help</h2>
                    <p class="section-subtitle mb-0">A few things you can do right now, no waiting required.</p>
                </div>

                <div class="contact-help-grid">
                    <?php foreach ($quickHelp as $item): ?>
                    <a class="contact-help-card"
                       href="<?= htmlspecialchars($item['href']) ?>"
                       <?php if (!empty($item['type'])): ?>data-enquiry-type="<?= htmlspecialchars($item['type']) ?>"<?php endif; ?>>
                        <span class="contact-help-card__icon"><i class="bi <?= htmlspecialchars($item['icon']) ?>"></i></span>
                        <span class="contact-help-card__text">
                            <span class="contact-help-card__q"><?= htmlspecialchars($item['q']) ?></span>
                            <span class="contact-help-card__link"><?= htmlspecialchars($item['label']) ?> <i class="bi bi-arrow-right"></i></span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ==================================================================
            HELP US MAKE TRAVEL SMARTER — feedback mini-forms
            ================================================================== -->
        <section class="section" id="feedbackSection">
            <div class="container">
                <div class="contact-section-head">
                    <span class="eyebrow">We&rsquo;d Love Your Input</span>
                    <h2 class="section-title mt-3">Help Us Make Travel Smarter</h2>
                    <p class="section-subtitle mb-0">Your suggestions help us improve destinations, packages and the travel experience.</p>
                </div>

                <div class="contact-fb-buttons" id="contactFbButtons">
                    <?php foreach ($fbOptions as $i => $fb): ?>
                    <button type="button" class="contact-fb-btn<?= $i === 0 ? ' is-active' : '' ?>" data-fb-type="<?= htmlspecialchars($fb['type']) ?>">
                        <span><?= $fb['emoji'] ?></span> <?= htmlspecialchars($fb['label']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="contact-fb-panel" id="contactFbPanel">

                    <!-- 💡 Suggest a Feature -->
                    <div class="contact-fb-form-wrap" data-fb-panel="feature">
                        <form class="contact-fb-form" data-fb-type="feature" novalidate>
                            <?php if (!$isLoggedIn): ?><?php include 'includes/contact-fb-guest-fields.php'; ?><?php endif; ?>
                            <label class="contact-field">
                                <span>What feature would make NextTrip better?</span>
                                <textarea name="message" rows="4" required maxlength="2000" placeholder="e.g. Let me save a shortlist of destinations to compare later"></textarea>
                            </label>
                            <button type="submit" class="contact-btn contact-btn--primary">
                                <span class="contact-btn__label">Send Suggestion</span>
                                <i class="bi bi-send contact-btn__icon" aria-hidden="true"></i>
                                <span class="contact-btn__spinner" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>

                    <!-- 📍 Suggest a Destination -->
                    <div class="contact-fb-form-wrap" data-fb-panel="destination" hidden>
                        <form class="contact-fb-form" data-fb-type="destination" novalidate>
                            <?php if (!$isLoggedIn): ?><?php include 'includes/contact-fb-guest-fields.php'; ?><?php endif; ?>
                            <div class="contact-form__row">
                                <label class="contact-field">
                                    <span>Destination Name</span>
                                    <input type="text" name="destination_name" required maxlength="150" placeholder="e.g. Champaner-Pavagadh">
                                </label>
                                <label class="contact-field">
                                    <span>State</span>
                                    <input type="text" name="destination_state" required maxlength="100" placeholder="e.g. Gujarat">
                                </label>
                            </div>
                            <label class="contact-field">
                                <span>Why should travellers visit? What makes it special?</span>
                                <textarea name="message" rows="4" required maxlength="2000" placeholder="Tell us what makes this place worth the trip..."></textarea>
                            </label>
                            <button type="submit" class="contact-btn contact-btn--primary">
                                <span class="contact-btn__label">Suggest Destination</span>
                                <i class="bi bi-send contact-btn__icon" aria-hidden="true"></i>
                                <span class="contact-btn__spinner" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>

                    <!-- ⭐ Share Feedback (saved to site_reviews and shown on the board below) -->
                    <div class="contact-fb-form-wrap" data-fb-panel="feedback" hidden>
                        <form class="contact-fb-form" data-fb-type="feedback" novalidate>
                            <?php if (!$isLoggedIn): ?><?php include 'includes/contact-fb-guest-fields.php'; ?><?php endif; ?>
                            <div class="contact-field contact-field--stars" data-field="rating">
                                <span>How&rsquo;s your experience with NextTrip so far?</span>
                                <div class="contact-fb-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button" class="contact-fb-star" data-value="<?= $i ?>" aria-label="<?= $i ?> star"><i class="bi bi-star-fill"></i></button>
                                    <?php endfor; ?>
                                    <input type="hidden" name="rating" value="0">
                                </div>
                            </div>
                            <label class="contact-field">
                                <span>Tell us more <em>(shown on the reviews board below)</em></span>
                                <textarea name="message" rows="3" required maxlength="500" placeholder="What's working well, what could be better..."></textarea>
                            </label>
                            <button type="submit" class="contact-btn contact-btn--primary">
                                <span class="contact-btn__label">Share Feedback</span>
                                <i class="bi bi-send contact-btn__icon" aria-hidden="true"></i>
                                <span class="contact-btn__spinner" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>

                    <!-- 🐛 Report a Problem -->
                    <div class="contact-fb-form-wrap" data-fb-panel="problem" hidden>
                        <form class="contact-fb-form" data-fb-type="problem" novalidate>
                            <?php if (!$isLoggedIn): ?><?php include 'includes/contact-fb-guest-fields.php'; ?><?php endif; ?>
                            <label class="contact-field">
                                <span>What went wrong?</span>
                                <textarea name="message" rows="4" required maxlength="2000" placeholder="What happened, and where on the site?"></textarea>
                            </label>
                            <button type="submit" class="contact-btn contact-btn--primary">
                                <span class="contact-btn__label">Report Problem</span>
                                <i class="bi bi-send contact-btn__icon" aria-hidden="true"></i>
                                <span class="contact-btn__spinner" aria-hidden="true"></span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==================================================================
            TRAVELLER REVIEWS — 3D board, reads from `site_reviews`
            ================================================================== -->
        <section class="section section-alt contact-reviews" id="reviewsSection">
            <div class="container">
                <div class="contact-section-head">
                    <span class="eyebrow">Traveller Voices</span>
                    <h2 class="section-title mt-3">Loved by Travellers</h2>
                    <p class="section-subtitle mb-0">Real feedback from people using NextTrip to plan their journeys.</p>
                </div>

                <div class="contact-reviews__summary" id="reviewsSummary"<?= $siteReviewStats['count'] === 0 ? ' hidden' : '' ?>>
                    <span class="contact-reviews__avg" id="reviewsAvg"><?= number_format($siteReviewStats['avg'], 1) ?></span>
                    <span class="contact-reviews__avg-stars" id="reviewsAvgStars"><?= contact_stars((int) round($siteReviewStats['avg'])) ?></span>
                    <span class="contact-reviews__count">from <strong id="reviewsCount"><?= (int) $siteReviewStats['count'] ?></strong> traveller reviews</span>
                </div>

                <div class="contact-board" id="contactBoard"<?= empty($siteReviews) ? ' hidden' : '' ?>>
                    <div class="contact-board__surface" aria-hidden="true"></div>
                    <button type="button" class="contact-board__nav contact-board__nav--prev" id="boardPrev" aria-label="Previous review"><i class="bi bi-chevron-left"></i></button>

                    <div class="contact-board__stage" id="boardStage" tabindex="0" aria-roledescription="carousel" aria-label="Traveller reviews">
                        <?php foreach ($siteReviews as $rv): ?>
                        <article class="contact-review-card">
                            <i class="bi bi-quote contact-review-card__quote" aria-hidden="true"></i>
                            <div class="contact-review-card__stars"><?= contact_stars((int) $rv['rating']) ?></div>
                            <p class="contact-review-card__text"><?= htmlspecialchars($rv['review_text']) ?></p>
                            <footer class="contact-review-card__who">
                                <span class="contact-review-card__avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($rv['display_name'], 0, 1))) ?></span>
                                <span>
                                    <strong><?= htmlspecialchars($rv['display_name']) ?></strong>
                                    <small><?= htmlspecialchars($rv['date_label']) ?></small>
                                </span>
                            </footer>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="contact-board__nav contact-board__nav--next" id="boardNext" aria-label="Next review"><i class="bi bi-chevron-right"></i></button>
                </div>

                <div class="contact-board__dots" id="boardDots"<?= empty($siteReviews) ? ' hidden' : '' ?>></div>

                <div class="contact-reviews__empty" id="reviewsEmpty"<?= !empty($siteReviews) ? ' hidden' : '' ?>>
                    <div class="contact-reviews__empty-icon">⭐</div>
                    <p>No reviews yet — be the first to share your experience.</p>
                </div>

                <div class="contact-reviews__cta">
                    <button type="button" class="contact-btn contact-btn--ghost" id="reviewsWriteBtn">
                        <i class="bi bi-pencil-square"></i> Write a review
                    </button>
                </div>
            </div>
        </section>

    </main>

    <div class="contact-toast-stack" id="contactToasts" aria-live="polite" aria-atomic="false"></div>

    <?php include 'includes/footer.php'; ?>

<script src="assets/js/navbar.js"></script>
<script src="assets/js/contact.js"></script>
</body>
</html>
