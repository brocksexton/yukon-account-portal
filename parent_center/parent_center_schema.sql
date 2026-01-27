-- Parent Center schema additions for Yukon
-- Run this once against your yukon database.

CREATE TABLE IF NOT EXISTS `parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(254) NOT NULL,
  `password` char(60) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If you created the table before role support, run this once:
-- ALTER TABLE `parent_children` ADD COLUMN `role` enum('child','parent') NOT NULL DEFAULT 'child' AFTER `alias`;

CREATE TABLE IF NOT EXISTS `parent_children` (
  `parentId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `alias` varchar(32) DEFAULT NULL,
  `role` enum('child','parent') NOT NULL DEFAULT 'child',
  `claimedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`parentId`,`userId`),
  KEY `idx_userId` (`userId`),
  CONSTRAINT `fk_parent_children_parent` FOREIGN KEY (`parentId`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_parent_children_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If you already created `parent_children` before this update, add the role column:
ALTER TABLE `parent_children`
  ADD COLUMN IF NOT EXISTS `role` enum('child','parent') NOT NULL DEFAULT 'child' AFTER `alias`;
