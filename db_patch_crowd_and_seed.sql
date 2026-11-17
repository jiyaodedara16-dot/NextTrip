-- ==========================================================================
-- db_patch_crowd_and_seed.sql
-- Run this AFTER importing smart_tourism.sql.
--
-- Fixes the bug that made the Destinations page show "0 destinations":
-- the page's query joins a `destination_crowd` table that doesn't exist
-- in the current dump, so every search silently failed.
--
-- Also fills in a few tables the dump left empty, so the page actually
-- has ratings, seasons and photos to show instead of "0.0" / "—" /
-- broken images for half the destinations.
-- ==========================================================================

-- 1. destination_crowd — powers the Weekday/Weekend + Low/Medium/High
--    crowd badge on every card and the "Crowd Level" filter.
CREATE TABLE IF NOT EXISTS `destination_crowd` (
  `destination_id` int(11) NOT NULL,
  `day_type` enum('Weekday','Weekend') NOT NULL,
  `crowd_level` enum('Low','Medium','High') NOT NULL DEFAULT 'Low',
  PRIMARY KEY (`destination_id`, `day_type`),
  CONSTRAINT `fk_crowd_destination` FOREIGN KEY (`destination_id`)
    REFERENCES `destinations` (`destination_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `destination_crowd` (`destination_id`, `day_type`, `crowd_level`) VALUES
(21, 'Weekday', 'Medium'), (21, 'Weekend', 'High'),   -- Taj Mahal
(22, 'Weekday', 'Medium'), (22, 'Weekend', 'High'),   -- Goa Beaches
(23, 'Weekday', 'Low'),    (23, 'Weekend', 'Medium'), -- Kerala Backwaters
(24, 'Weekday', 'Medium'), (24, 'Weekend', 'High'),   -- Golden Temple
(25, 'Weekday', 'Medium'), (25, 'Weekend', 'High'),   -- Jaipur - Hawa Mahal & Amber Fort
(26, 'Weekday', 'Medium'), (26, 'Weekend', 'High'),   -- Varanasi Ghats
(27, 'Weekday', 'Low'),    (27, 'Weekend', 'Medium'), -- Red Fort
(28, 'Weekday', 'Low'),    (28, 'Weekend', 'Medium'), -- Qutub Minar
(29, 'Weekday', 'Medium'), (29, 'Weekend', 'High'),   -- Gateway of India
(30, 'Weekday', 'Low'),    (30, 'Weekend', 'Low')     -- Rann of Kutch
ON DUPLICATE KEY UPDATE crowd_level = VALUES(crowd_level);

-- 2. seasons — the dump defines the table but never seeds it.
INSERT INTO `seasons` (`season_id`, `season_name`, `start_month`, `end_month`) VALUES
(1, 'Oct - Mar', 10, 3),
(2, 'Nov - Feb', 11, 2),
(3, 'Oct - Feb', 10, 2),
(4, 'Sep - Mar', 9, 3),
(5, 'Nov - Mar', 11, 3)
ON DUPLICATE KEY UPDATE season_name = VALUES(season_name);

-- 3. destination_seasons — best-time-to-visit link per destination.
INSERT INTO `destination_seasons` (`destination_id`, `season_id`, `is_best_time`, `description`) VALUES
(21, 1, 1, 'Cool, clear mornings make sunrise visits comfortable.'),
(22, 3, 1, 'Dry season with calm seas and sunny beach days.'),
(23, 4, 1, 'Backwaters are lush right after the monsoon.'),
(24, 2, 1, 'Pleasant weather for the long queue and courtyard.'),
(25, 1, 1, 'Mild days are best for walking the fort ramparts.'),
(26, 5, 1, 'Cooler evenings make the ghats and boat rides comfortable.'),
(27, 1, 1, 'Comfortable temperatures for the open fort grounds.'),
(28, 1, 1, 'Good light and mild heat for the complex and gardens.'),
(29, 3, 1, 'Pleasant harbour breeze and clear skies.'),
(30, 2, 1, 'Cool nights and clear skies for the White Rann.');

-- 4. destination_images — the dump only has photos for destinations
--    22-25; these six were missing even though the asset files exist
--    in assets/images/destinations/.
INSERT INTO `destination_images`
  (`destination_id`, `image_path`, `image_title`, `alt_text`, `is_primary`, `display_order`, `status`) VALUES
(21, 'assets/images/destinations/tajmahal_main.avif', 'Taj Mahal', 'Taj Mahal at sunrise', 1, 1, 'Active'),
(21, 'assets/images/destinations/tajmahal_2.avif',    'Taj Mahal', 'Taj Mahal reflecting pool', 0, 2, 'Active'),
(21, 'assets/images/destinations/tagmahal_3.avif',    'Taj Mahal', 'Taj Mahal close-up', 0, 3, 'Active'),

(26, 'assets/images/destinations/varnsi_main.avif', 'Varanasi Ghats', 'Ganges ghats at dusk', 1, 1, 'Active'),
(26, 'assets/images/destinations/varnsi_2.avif',    'Varanasi Ghats', 'Boats along the ghats', 0, 2, 'Active'),
(26, 'assets/images/destinations/varnsi_3.avif',    'Varanasi Ghats', 'Evening aarti ceremony', 0, 3, 'Active'),

(27, 'assets/images/destinations/redfort_main.avif', 'Red Fort', 'Red Fort main gate', 1, 1, 'Active'),
(27, 'assets/images/destinations/redfort_2.avif',    'Red Fort', 'Red Fort ramparts', 0, 2, 'Active'),
(27, 'assets/images/destinations/refort_3.avif',     'Red Fort', 'Red Fort courtyard', 0, 3, 'Active'),

(28, 'assets/images/destinations/qutub_minar_main.avif', 'Qutub Minar', 'Qutub Minar tower', 1, 1, 'Active'),
(28, 'assets/images/destinations/qutub_minar_2.avif',    'Qutub Minar', 'Qutub complex ruins', 0, 2, 'Active'),
(28, 'assets/images/destinations/qutub_minar_3.avif',    'Qutub Minar', 'Qutub Minar carvings', 0, 3, 'Active'),

(29, 'assets/images/destinations/gatway_india_main.avif', 'Gateway of India', 'Gateway of India waterfront', 1, 1, 'Active'),
(29, 'assets/images/destinations/gateway_india_2.avif',   'Gateway of India', 'Gateway of India at dusk', 0, 2, 'Active'),
(29, 'assets/images/destinations/gateway_india_3.webp',   'Gateway of India', 'Boats near the Gateway', 0, 3, 'Active'),

(30, 'assets/images/destinations/run_kutch_main.avif', 'Rann of Kutch', 'White salt desert', 1, 1, 'Active'),
(30, 'assets/images/destinations/run_kutch_2.avif',    'Rann of Kutch', 'Rann at sunset', 0, 2, 'Active'),
(30, 'assets/images/destinations/run_kutch_3.avif',    'Rann of Kutch', 'Full moon desert festival', 0, 3, 'Active');

-- 5. destination_reviews — approved reviews so the star rating on each
--    card is a real average instead of 0.0. user_id 1-20 already exist.
INSERT INTO `destination_reviews`
  (`destination_id`, `user_id`, `rating`, `review_title`, `review_text`, `status`) VALUES
(21, 1, 5, 'Breathtaking', 'Even with the crowds, seeing it at sunrise was unforgettable.', 'Approved'),
(21, 2, 5, 'Bucket list done', 'Marble detailing up close is stunning. Go early.', 'Approved'),
(21, 3, 4, 'Worth the queue', 'Long line but moves fast. Hire a guide, helps a lot.', 'Approved'),

(22, 4, 4, 'Great beach week', 'Calangute was lively, Palolem was calm. Loved both.', 'Approved'),
(22, 5, 5, 'Perfect getaway', 'Shacks, sunsets, water sports — Goa delivers every time.', 'Approved'),
(22, 6, 4, 'Would return', 'A bit crowded on weekends but still a lot of fun.', 'Approved'),

(23, 7, 5, 'Peaceful houseboat stay', 'Waking up on the backwaters was pure calm.', 'Approved'),
(23, 8, 5, 'Stunning scenery', 'Green paddy fields and coconut groves everywhere.', 'Approved'),
(23, 9, 4, 'Relaxing trip', 'Food on the houseboat was a highlight too.', 'Approved'),

(24, 10, 5, 'Deeply moving', 'The langar hall and the golden dome at night are special.', 'Approved'),
(24, 11, 5, 'Must visit', 'Peaceful even with thousands of visitors around.', 'Approved'),
(24, 12, 4, 'Beautifully maintained', 'Go at night when it is lit up, it is magical.', 'Approved'),

(25, 13, 5, 'Fort views for days', 'Amber Fort at sunset is unbeatable.', 'Approved'),
(25, 14, 4, 'Hawa Mahal is iconic', 'Smaller than expected but the facade is gorgeous.', 'Approved'),
(25, 15, 5, 'Pink City charm', 'Loved the forts, the markets, all of it.', 'Approved'),

(26, 16, 5, 'Spiritual and raw', 'The evening Ganga aarti is something else entirely.', 'Approved'),
(26, 17, 4, 'Intense but rewarding', 'Chaotic streets, but the ghats at dawn make up for it.', 'Approved'),
(26, 18, 5, 'Unforgettable boat ride', 'Sunrise boat ride along the ghats was the highlight.', 'Approved'),

(27, 19, 4, 'Solid history stop', 'Sound and light show in the evening is worth staying for.', 'Approved'),
(27, 20, 4, 'Impressive walls', 'Big complex, good for a slow morning walk.', 'Approved'),
(27, 1, 5, 'Loved the architecture', 'The red sandstone against a blue sky is a great photo op.', 'Approved'),

(28, 2, 4, 'Tall and elegant', 'The carvings on the tower are incredibly detailed.', 'Approved'),
(28, 3, 5, 'Quiet heritage park', 'Less crowded than other Delhi monuments, nice to walk around.', 'Approved'),
(28, 4, 4, 'Good half-day trip', 'Combine with nearby Mehrauli Archaeological Park.', 'Approved'),

(29, 5, 5, 'Mumbai icon', 'Best seen from a ferry looking back at the arch.', 'Approved'),
(29, 6, 4, 'Busy but fun', 'Street food and boat rides right next to it.', 'Approved'),
(29, 7, 5, 'Great evening spot', 'Lit up beautifully after sunset.', 'Approved'),

(30, 8, 5, 'Surreal white desert', 'Full moon night at the Rann is unlike anywhere else.', 'Approved'),
(30, 9, 4, 'Plan around Rann Utsav', 'Tent city gets busy in season, book ahead.', 'Approved'),
(30, 10, 5, 'Endless horizon', 'The salt flats stretching to the horizon are surreal.', 'Approved');
