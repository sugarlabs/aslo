ALTER TABLE collections
  ADD COLUMN `upvotes` int(11) unsigned NOT NULL DEFAULT '0',
  ADD COLUMN `downvotes` int(11) unsigned NOT NULL DEFAULT '0';

DROP TABLE IF EXISTS `collections_votes`;
CREATE TABLE `collections_votes` (
  `collection_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `vote` tinyint(2) NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`collection_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `collections_votes_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `collections_votes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
