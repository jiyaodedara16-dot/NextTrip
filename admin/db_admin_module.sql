-- ==========================================================================
-- SMART TOURISM MANAGEMENT SYSTEM — Admin module migration
-- Run AFTER: smart_tourism.sql  and  agent_schema_migration.sql
-- Safe to re-run (uses IF NOT EXISTS / INSERT IGNORE).
-- ==========================================================================

-- 1) Verification numbers shown on the admin Agent details page.
--    The signup form already uploads the documents; these columns hold the numbers.
ALTER TABLE `agents`
  ADD COLUMN IF NOT EXISTS `aadhaar_number` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `pan_number` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `business_document_type` varchar(60) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `business_document_number` varchar(100) DEFAULT NULL;

-- 2) Role fix: the seed data gave tourists role_id 3 (Admin) and the admin account role_id 1 (User).
UPDATE `accounts` a JOIN `users` u ON u.account_id = a.account_id
   SET a.role_id = (SELECT role_id FROM roles WHERE role_name = 'User')
 WHERE a.role_id <> (SELECT role_id FROM roles WHERE role_name = 'User');

UPDATE `accounts`
   SET role_id = (SELECT role_id FROM roles WHERE role_name = 'Admin')
 WHERE email = 'admin@smarttourism.com';

-- 3) Demo pending agent applications so the Agent Requests queue is not empty on first run.
INSERT IGNORE INTO `accounts` (`account_id`, `role_id`, `email`, `password_hash`, `status`, `created_at`) VALUES
(200, 2, 'kiran.desai@demo.com', '123456', 'Active', NOW()),
(201, 2, 'meera.shah@demo.com',  '123456', 'Active', NOW()),
(202, 2, 'harsh.vora@demo.com',  '123456', 'Active', NOW());

INSERT IGNORE INTO `agents`
  (`agent_id`, `account_id`, `full_name`, `phone`, `address`, `city`, `aadhaar_number`, `pan_number`, `status`, `created_at`) VALUES
(200, 200, 'Kiran Desai', '9800012345', 'Ellis Bridge', 'Ahmedabad', 'XXXX-XXXX-4321', NULL, 'Pending', NOW()),
(201, 201, 'Meera Shah',  '9800012346', 'Bodakdev',     'Ahmedabad', NULL, 'ABCDE4321F', 'Pending', NOW()),
(202, 202, 'Harsh Vora',  '9800012347', 'Rander',       'Surat',     'XXXX-XXXX-9876', 'FGHIJ9876K', 'Pending', NOW());
