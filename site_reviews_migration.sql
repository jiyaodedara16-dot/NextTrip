-- ============================================================
-- site_reviews_migration.sql
-- ------------------------------------------------------------
-- Stores the traveller reviews shown on the Contact page's 3D
-- "reviews board", and receives every "Share Feedback" form
-- submission (rating + text) from that page.
--
-- Why a new table: `enquiries` has no rating column and no way to
-- mark a row as safe to show publicly; `destination_reviews` and
-- `package_reviews` are tied to one destination / package each.
-- This one is for reviews of NextTrip itself.
--
-- Contact-form queries and the other 3 feedback forms (feature,
-- destination, problem) keep going into the existing `enquiries`
-- table — no change there.
--
-- The 6 rows at the bottom are SAMPLE reviews so the board isn't
-- empty on first run. Delete them once real reviews come in:
--   DELETE FROM site_reviews WHERE user_id IS NULL AND email IS NULL;
-- ============================================================

CREATE TABLE IF NOT EXISTS `site_reviews` (
  `review_id`     int(11) NOT NULL AUTO_INCREMENT,
  `user_id`       int(11) DEFAULT NULL,
  `reviewer_name` varchar(100) NOT NULL,
  `email`         varchar(150) DEFAULT NULL,
  `rating`        tinyint(1) NOT NULL,
  `review_text`   varchar(500) NOT NULL,
  `status`        enum('Approved','Pending','Hidden') NOT NULL DEFAULT 'Approved',
  `created_at`    datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `idx_site_reviews_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_site_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `site_reviews` (`user_id`, `reviewer_name`, `email`, `rating`, `review_text`, `status`, `created_at`) VALUES
(NULL, 'Aarav Mehta',  NULL, 5, 'The crowd level on each destination page saved our weekend. We picked a quieter hour for the Rann and it was magical.', 'Approved', '2026-09-21 10:12:00'),
(NULL, 'Priya Nair',   NULL, 5, 'Finding hotels and restaurants near the place I was visiting, all in one page, made planning so much easier.', 'Approved', '2026-09-14 18:40:00'),
(NULL, 'Kabir Shah',   NULL, 4, 'Booking a package was smooth and the itinerary was clear. Would love a way to compare two packages side by side.', 'Approved', '2026-09-08 09:05:00'),
(NULL, 'Ananya Iyer',  NULL, 5, 'Clean, fast and genuinely useful. The hourly crowd forecast is something I did not know I needed.', 'Approved', '2026-08-30 16:22:00'),
(NULL, 'Rohan Desai',  NULL, 4, 'Great collection of Gujarat destinations with honest reviews from real visitors. Easy to trust.', 'Approved', '2026-08-22 12:48:00'),
(NULL, 'Meera Joshi',  NULL, 5, 'Sent a question through the contact page and got a helpful reply the next day. Lovely experience overall.', 'Approved', '2026-08-15 20:31:00');
