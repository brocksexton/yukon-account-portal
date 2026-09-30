-- Demo data for local screenshots and the static GitHub Pages demo.
-- Every name, email and value below is made up. Do NOT load this into a real server.
--
-- Load order: yukon.sql (from wizguin/yukon-server), then sql/portal_schema.sql, then this file,
-- into an EMPTY database (the ids below are fixed).
--
-- Demo logins (local testing only):
--   Parent Center:      parent@example.com / 0kivoDQRX7aE62dR
--   Every demo penguin: <username> / Bzl2nJXWCoivI7d9   (for example DemoParent)
--   ModMarigold has rank 3, so it can open /administration/.
--
-- Child states shown in the Parent Center:
--   SnowySprout   active, clean record (also owns the PSA and Tour Guide items)
--   IcebergIvy    temporarily banned (an active ban with an expiry date)
--   PebbleWaddle  permanently banned (users.permaBan = 1)
--   FrostyFinn    recently banned (the ban expired within the last 30 days)
--   CocoaPuff     brand-new account with no items, puffles or friends

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `users` (`id`,`username`,`email`,`password`,`rank`,`permaBan`,`joinTime`,`coins`,`head`,`face`,`neck`,`body`,`hand`,`feet`,`color`,`photo`,`flag`,`ninjaRank`,`ninjaProgress`) VALUES
  (1,'DemoParent','demoparent@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 180 DAY,12450,413,125,179,243,0,365,5,932,565,3,40),
  (2,'SnowySprout','snowysprout@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 150 DAY,3820,407,0,171,211,234,369,12,909,0,1,75),
  (3,'IcebergIvy','icebergivy@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 120 DAY,1575,484,0,175,222,0,386,3,901,0,0,0),
  (4,'PebbleWaddle','pebblewaddle@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,1,NOW() - INTERVAL 200 DAY,640,490,104,0,845,0,374,4,0,565,0,0),
  (5,'FrostyFinn','frostyfinn@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 90 DAY,2210,461,0,0,278,220,352,13,919,0,2,10),
  (6,'CocoaPuff','cocoapuff@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 2 DAY,500,0,0,0,0,0,0,9,0,0,0,0),
  (7,'PolarPal','polarpal@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 300 DAY,900,469,101,0,221,0,357,7,0,0,0,0),
  (8,'TuxedoTom','tuxedotom@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 260 DAY,5400,1800,0,214,294,0,0,16,903,0,4,0),
  (9,'AuroraBea','aurorabea@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 240 DAY,2600,1284,117,0,4128,0,380,8,904,0,0,0),
  (10,'SlushySam','slushysam@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',1,0,NOW() - INTERVAL 60 DAY,1200,464,131,0,4395,5021,6058,15,0,0,0,0),
  (11,'ModMarigold','modmarigold@example.com','$2y$10$07HGnFNceENCr6MW42AAEuHH7dGzWxBPxb1z2QMYTdAbBdbSrRU0e',3,0,NOW() - INTERVAL 365 DAY,999,403,0,0,0,0,372,2,0,0,0,0);

-- Yukon's insert trigger already adds each penguin's colour to inventories, hence IGNORE.
INSERT IGNORE INTO `inventories` (`userId`,`itemId`) VALUES
  (1,413), (1,125), (1,179), (1,243), (1,365), (1,932), (1,565), (1,452),
  (1,455), (1,294), (1,4065), (1,214), (1,166), (1,433), (1,404), (1,217),
  (1,368), (1,221), (1,223), (1,352), (1,357), (1,481), (1,1164), (1,483),
  (1,234), (1,233), (1,220), (1,901), (1,902), (1,903), (1,904), (1,428),
  (2,407), (2,431), (2,171), (2,193), (2,211), (2,234), (2,369), (2,909),
  (2,800), (2,428), (2,205), (2,498), (2,1120), (2,1126), (2,413), (2,444),
  (2,430), (2,221), (2,222), (2,357), (2,387), (2,366), (2,5083), (2,902),
  (2,925), (2,632), (3,484), (3,175), (3,222), (3,386), (3,901), (3,418),
  (3,421), (3,427), (3,496), (3,380), (3,359), (3,211), (3,4117), (3,5491),
  (4,490), (4,104), (4,845), (4,374), (4,231), (4,258), (4,565), (4,1056),
  (4,2003), (5,461), (5,278), (5,220), (5,352), (5,919), (5,277), (5,453),
  (5,463), (5,720), (5,726), (5,6036), (7,469), (7,101), (7,221), (7,357),
  (8,1800), (8,214), (8,294), (8,903), (9,1284), (9,117), (9,4128), (9,380),
  (9,904), (10,464), (10,131), (10,4395), (10,5021), (10,6058), (11,403), (11,372);

-- Puffles (typeId: 0 blue, 1 pink, 2 black, 3 green, 4 purple, 5 red, 6 yellow, 7 white, 8 orange)
INSERT INTO `pets` (`userId`,`typeId`,`name`,`adoptionDate`,`energy`,`health`,`rest`) VALUES
  (1,7,'Snowball',NOW() - INTERVAL 170 DAY,100,95,90),
  (2,0,'Bubbles',NOW() - INTERVAL 140 DAY,96,100,88),
  (2,1,'Sprinkles',NOW() - INTERVAL 100 DAY,82,91,76),
  (2,2,'Shadow',NOW() - INTERVAL 45 DAY,64,70,58),
  (3,4,'Violet',NOW() - INTERVAL 110 DAY,50,64,40),
  (4,5,'Ember',NOW() - INTERVAL 190 DAY,20,35,15),
  (5,3,'Pickles',NOW() - INTERVAL 80 DAY,88,93,97),
  (5,6,'Sunny',NOW() - INTERVAL 30 DAY,100,100,100);

-- Friends (a buddy row in both directions means the friendship is mutual)
INSERT INTO `buddies` (`userId`,`buddyId`) VALUES (2,7), (7,2), (2,8), (8,2), (2,9), (9,2), (2,10), (3,7), (7,3), (3,9), (9,3), (5,10), (10,5), (5,8), (1,8), (8,1), (1,9), (9,1), (4,10);

-- Ban history. ModMarigold (id 11) is the moderator on every record.
INSERT INTO `bans` (`userId`,`issued`,`expires`,`moderatorId`,`message`) VALUES
  (3,NOW() - INTERVAL 1 DAY,NOW() + INTERVAL 2 DAY,11,'Inappropriate chat in the Town'),
  (3,NOW() - INTERVAL 75 DAY,NOW() - INTERVAL 74 DAY,11,'Name-calling during a party'),
  (4,NOW() - INTERVAL 9 DAY,NOW() - INTERVAL 6 DAY,11,'Repeated rule breaking after warnings'),
  (4,NOW() - INTERVAL 50 DAY,NOW() - INTERVAL 49 DAY,11,'Spamming the Coffee Shop'),
  (5,NOW() - INTERVAL 12 DAY,NOW() - INTERVAL 11 DAY,11,'Spamming emotes');

-- Parent Center: one parent account managing its own penguin plus five children.
INSERT INTO `parents` (`id`,`email`,`password`,`createdAt`) VALUES (1,'parent@example.com','$2y$10$Qv3V/7cRzU9/B41cM89V5O7/YVJpybMclkWhfOIdaGisaA4Nfq6.S',NOW() - INTERVAL 170 DAY);
INSERT INTO `parent_children` (`parentId`,`userId`,`alias`,`role`,`claimedAt`) VALUES
  (1,1,NULL,'parent',NOW() - INTERVAL 170 DAY),
  (1,2,'Oldest','child',NOW() - INTERVAL 160 DAY),
  (1,3,'Twin A','child',NOW() - INTERVAL 118 DAY),
  (1,4,'Twin B','child',NOW() - INTERVAL 117 DAY),
  (1,5,'Cousin (weekends)','child',NOW() - INTERVAL 85 DAY),
  (1,6,'Youngest','child',NOW() - INTERVAL 2 DAY);

-- Saved and public outfits
INSERT INTO `user_outfits` (`userId`,`shareCode`,`sourceShareCode`,`name`,`description`,`isPublic`,`likes`,`color`,`head`,`face`,`neck`,`body`,`hand`,`feet`,`photo`,`flag`,`created_at`) VALUES
  (1,'a1b2c3d4',NULL,'Snow Day Classic','Party hat, parka and winter boots.',1,14,5,413,125,179,243,0,365,932,565,NOW() - INTERVAL 40 DAY),
  (1,'b2c3d4e5',NULL,'Viking Night Out','Helmet plus tuxedo. Why not.',1,6,5,452,0,214,294,0,0,903,0,NOW() - INTERVAL 30 DAY),
  (1,'c3d4e5f6',NULL,'Weekend Hoodie','',0,0,5,481,0,0,221,234,352,0,0,NOW() - INTERVAL 20 DAY),
  (8,'d4e5f6a7',NULL,'Dapper Tux','Bowler hat and bowtie.',1,22,16,1800,0,214,294,0,0,903,0,NOW() - INTERVAL 60 DAY),
  (9,'e5f6a7b8',NULL,'Blizzard Wizard','Robes for the Ski Lodge.',1,9,8,1284,117,0,4128,0,380,904,0,NOW() - INTERVAL 25 DAY),
  (10,'f6a7b8c9',NULL,'Toboggan Racer','Helmet, snorkel and sled.',1,4,15,464,131,0,4395,5021,6058,0,0,NOW() - INTERVAL 15 DAY),
  (7,'a7b8c9d0',NULL,'Rainy Day','Umbrella hat and hoodie.',1,3,7,469,101,0,221,0,357,0,0,NOW() - INTERVAL 8 DAY);

-- Commission payouts for DemoParent's public outfits
INSERT INTO `outfit_commissions` (`outfitShareCode`,`authorUserId`,`buyerUserId`,`amount`,`created_at`) VALUES
  ('a1b2c3d4',1,2,45,NOW() - INTERVAL 3 DAY),
  ('a1b2c3d4',1,7,30,NOW() - INTERVAL 9 DAY),
  ('b2c3d4e5',1,10,120,NOW() - INTERVAL 14 DAY),
  ('a1b2c3d4',1,9,25,NOW() - INTERVAL 21 DAY),
  ('b2c3d4e5',1,3,60,NOW() - INTERVAL 33 DAY);

SET FOREIGN_KEY_CHECKS = 1;
