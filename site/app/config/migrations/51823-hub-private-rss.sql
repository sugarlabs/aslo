--
-- a top secret uuid is required for add-on news feed RSS
--
CREATE TABLE `hubrsskeys` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `rsskey` char(36) NOT NULL default '',    -- simply a uuid
  `addon_id` int(11) unsigned default NULL, -- for a private add-on rss feed
  `user_id` int(11) unsigned default NULL,  -- OR for a private feed of all the user's add-ons
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rsskey` (`rsskey`),
  UNIQUE KEY `addon_id` (`addon_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `hubrsskeys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hubrsskeys_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
