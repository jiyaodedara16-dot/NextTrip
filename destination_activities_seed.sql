-- ============================================================
-- destination_activities_seed.sql
-- ------------------------------------------------------------
-- `activities` already has 3 rows per destination (21-30), but the
-- `destination_activities` link table had no rows at all, so the
-- "Activities" section on every destination page rendered empty.
-- This links each activity back to its destination in the same
-- order it was authored (display_order 1-3).
-- ============================================================
INSERT INTO `destination_activities` (`destination_id`, `activity_id`, `display_order`) VALUES
  (21, 39, 1), (21, 40, 2), (21, 41, 3),
  (22, 42, 1), (22, 43, 2), (22, 44, 3),
  (23, 45, 1), (23, 46, 2), (23, 47, 3),
  (24, 48, 1), (24, 49, 2), (24, 50, 3),
  (25, 51, 1), (25, 52, 2), (25, 53, 3),
  (26, 54, 1), (26, 55, 2), (26, 56, 3),
  (27, 57, 1), (27, 58, 2), (27, 59, 3),
  (28, 60, 1), (28, 61, 2), (28, 62, 3),
  (29, 63, 1), (29, 64, 2), (29, 65, 3),
  (30, 66, 1), (30, 67, 2), (30, 68, 3);
