-- Account portal tables that sit next to the stock Yukon schema.
--
-- 1. Create the Yukon database first with yukon.sql from
--    https://github.com/wizguin/yukon-server
-- 2. Then run this file against the same database.
--
-- Nothing here changes Yukon's own tables; it only adds new ones.

SET NAMES utf8mb4;

-- Parent Center: parent logins (separate from penguin accounts).
CREATE TABLE IF NOT EXISTS `parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(254) NOT NULL,
  `password` char(60) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parent Center: which penguins a parent account manages.
-- role = 'parent' marks the parent's own penguin, 'child' a managed child account.
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

-- Manage My Penguin: saved outfits (same definition the outfits API creates lazily).
CREATE TABLE IF NOT EXISTS `user_outfits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `shareCode` varchar(16) NOT NULL,
  `sourceShareCode` varchar(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `isPublic` tinyint(1) NOT NULL DEFAULT 0,
  `likes` int(11) NOT NULL DEFAULT 0,
  `color` int(11) NOT NULL DEFAULT 1,
  `head` int(11) NOT NULL DEFAULT 0,
  `face` int(11) NOT NULL DEFAULT 0,
  `neck` int(11) NOT NULL DEFAULT 0,
  `body` int(11) NOT NULL DEFAULT 0,
  `hand` int(11) NOT NULL DEFAULT 0,
  `feet` int(11) NOT NULL DEFAULT 0,
  `photo` int(11) NOT NULL DEFAULT 0,
  `flag` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shareCode` (`shareCode`),
  KEY `idx_userId` (`userId`),
  CONSTRAINT `fk_user_outfits_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Manage My Penguin: commission payouts shown on the Overview and Commissions tabs.
-- The portal only reads this table; nothing in this repo writes to it yet
-- (see "Known limitations" in the README).
CREATE TABLE IF NOT EXISTS `outfit_commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `outfitShareCode` varchar(16) NOT NULL,
  `authorUserId` int(11) NOT NULL,
  `buyerUserId` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_authorUserId` (`authorUserId`),
  KEY `idx_outfitShareCode` (`outfitShareCode`),
  CONSTRAINT `fk_outfit_commissions_author` FOREIGN KEY (`authorUserId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
