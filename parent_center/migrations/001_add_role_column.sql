-- Parent Center migration: add `role` column to `parent_children` (MariaDB/MySQL compatible)
SET @has_role := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'parent_children'
    AND COLUMN_NAME = 'role'
);

SET @stmt := IF(@has_role = 0,
  "ALTER TABLE `parent_children` ADD COLUMN `role` varchar(16) NOT NULL DEFAULT 'child' AFTER `alias`",
  "SELECT 1"
);

PREPARE s FROM @stmt;
EXECUTE s;
DEALLOCATE PREPARE s;
