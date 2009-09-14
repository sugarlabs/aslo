DROP TABLE IF EXISTS `howto_votes`;
CREATE TABLE `howto_votes` (
  `howto_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `vote` tinyint(2) NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`howto_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `howto_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
