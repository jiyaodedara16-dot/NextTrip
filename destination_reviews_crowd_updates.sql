-- ==========================================================================
-- destination_reviews_crowd_updates.sql
-- Run this AFTER smart_tourism.sql (and after db_patch_crowd_and_seed.sql,
-- if you're using that too).
--
-- WHY THIS FILE EXISTS INSTEAD OF A NEW "package_reviews" TABLE:
-- The project already has a `destination_reviews` table (destination_id,
-- user_id, rating, review_title, review_text, status) that place-details.php
-- reads from. Rather than duplicate it, this file just ADDS the two new
-- columns the crowd-reporting feature needs: `visit_time` and
-- `crowd_level`. Existing rows/features are untouched.
-- ==========================================================================

-- 0. Pre-existing schema bug (unrelated to this feature, found while
--    testing Favorite): `favorites.favorite_id` is never given
--    AUTO_INCREMENT anywhere in smart_tourism.sql (only the separate
--    `package_favorites` table gets that fix). Without this, every
--    INSERT into `favorites` fails with "Field 'favorite_id' doesn't
--    have a default value".
ALTER TABLE `favorites` MODIFY `favorite_id` bigint(20) NOT NULL AUTO_INCREMENT;

-- 1. New columns on the existing review table.
ALTER TABLE `destination_reviews`
  ADD COLUMN `visit_time` TIME NULL DEFAULT NULL AFTER `review_text`,
  ADD COLUMN `crowd_level` ENUM('Very Low','Low','Medium','High','Very High') NULL DEFAULT NULL AFTER `visit_time`;

-- Speeds up the "crowd by hour" aggregate query on the package/place page.
ALTER TABLE `destination_reviews`
  ADD INDEX `idx_dest_crowd` (`destination_id`, `visit_time`, `crowd_level`);

-- 2. Sample crowd data for Taj Mahal (destination_id = 21) so the chart has
--    something to render immediately. Spread across the day, weighted the
--    way a real monument's foot traffic behaves (quiet morning, midday
--    peak, tapering off toward evening). Reuses existing seeded users
--    1-12 (all present in the `users` table) — no new users created.
INSERT INTO `destination_reviews`
  (`destination_id`, `user_id`, `rating`, `review_title`, `review_text`, `visit_time`, `crowd_level`, `status`) VALUES
(21, 1,  5, 'Early bird view',     'Went right at sunrise, almost empty and beautiful light.', '06:30:00', 'Very Low',  'Approved'),
(21, 2,  4, 'Still quiet at 8',    'Nice and calm, easy to get good photos without crowds.',   '08:00:00', 'Low',       'Approved'),
(21, 3,  4, 'Getting busier',      'Tour buses were starting to arrive by mid-morning.',        '10:00:00', 'Medium',    'Approved'),
(21, 4,  3, 'Peak crowd',          'Extremely packed around noon, hard to move in places.',     '12:00:00', 'Very High', 'Approved'),
(21, 5,  3, 'Still very busy',     'Long queues for the main dome entrance in the afternoon.',  '13:00:00', 'High',      'Approved'),
(21, 6,  4, 'Afternoon lull',      'A little less crowded after lunch hours.',                  '15:00:00', 'Medium',    'Approved'),
(21, 7,  5, 'Golden hour',         'Lovely light and thinner crowds closer to closing time.',   '17:00:00', 'Low',       'Approved'),
(21, 8,  5, 'Great evening visit', 'Much more relaxed pace in the last hour before close.',     '18:00:00', 'Low',       'Approved'),
(21, 9,  4, 'Midday rush again',   'Second wave of tour groups right before lunch.',            '11:00:00', 'High',      'Approved'),
(21, 10, 3, 'Very crowded',        'Definitely the busiest I have seen it, avoid this slot.',   '12:30:00', 'Very High', 'Approved');
