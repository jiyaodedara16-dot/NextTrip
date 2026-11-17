-- ============================================================
-- agent_schema_migration.sql
-- ------------------------------------------------------------
-- Fixes the agents / agent_applications / agencies confusion:
--
-- BEFORE: an applicant filled `agent_applications` (no login).
-- Admin approval then INSERTed a brand-new row into `agents`
-- (with its own duplicate document_type/number/file columns) and
-- only THEN created their `accounts` login. Two tables tracked
-- overlapping data, plus a near-unused `agencies` table that
-- `agents.agency_id` pointed at.
--
-- AFTER: `agents` IS the application. An agent signs up like
-- anyone else (auth/login.php), account + agents row are created
-- together right after they verify their email OTP, with
-- `agents.status` = 'Pending'. Admin's Agent Requests page just
-- flips that same row's status to Approved/Rejected — no second
-- table, no account created "on approve".
--
-- Run this once against the existing smart_tourism database.
-- Existing agents rows are kept (their data isn't touched beyond
-- the column changes below) and backfilled to status='Approved',
-- since they were already live, working agents under the old
-- (admin-approval-creates-the-row) flow.
-- ============================================================

-- 1) Drop the FKs that touch the columns/tables being removed.
ALTER TABLE `agents`
  DROP FOREIGN KEY `fk_agent_agency`,
  DROP FOREIGN KEY `fk_agents_application`;

ALTER TABLE `agents`
  DROP KEY `fk_agent_agency`,
  DROP KEY `application_id`;

-- 2) Drop the columns that belonged to the old two-table split.
ALTER TABLE `agents`
  DROP COLUMN `application_id`,
  DROP COLUMN `agency_id`,
  DROP COLUMN `document_type`,
  DROP COLUMN `document_number`,
  DROP COLUMN `document_file`;

-- 3) Add the real application fields directly on `agents`, plus the
--    Pending/Approved/Rejected workflow columns.
ALTER TABLE `agents`
  ADD COLUMN `aadhaar_file` varchar(255) DEFAULT NULL AFTER `profile_image`,
  ADD COLUMN `pan_file` varchar(255) DEFAULT NULL AFTER `aadhaar_file`,
  ADD COLUMN `business_document_file` varchar(255) DEFAULT NULL AFTER `pan_file`,
  ADD COLUMN `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER `business_document_file`,
  ADD COLUMN `rejection_reason` varchar(255) DEFAULT NULL AFTER `status`,
  ADD COLUMN `reviewed_at` datetime DEFAULT NULL AFTER `rejection_reason`;

-- Existing rows predate this workflow and were already treated as
-- live agents, so mark them reviewed/approved rather than leaving
-- them stuck in "Pending" with no application files on file.
UPDATE `agents` SET `status` = 'Approved', `reviewed_at` = `created_at` WHERE `status` = 'Pending';

-- 4) The two tables this replaces.
DROP TABLE IF EXISTS `agent_applications`;
DROP TABLE IF EXISTS `agencies`;
