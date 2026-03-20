-- ============================================================
--  admin_login database patch
--  Run this in phpMyAdmin on your admin_login database
--  Adds the `name` column used by the dashboard header
-- ============================================================

USE admin_login;

-- Add name column if it doesn't exist
ALTER TABLE `admin_details`
  ADD COLUMN IF NOT EXISTS `name` VARCHAR(100) NOT NULL DEFAULT 'Admin'
  AFTER `email`;

-- Verify your table has these columns:
-- id | email | password | token | name | last_logged
DESCRIBE `admin_details`;
