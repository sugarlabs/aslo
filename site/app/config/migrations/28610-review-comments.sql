DROP TABLE IF EXISTS `versioncomments`;
CREATE TABLE `versioncomments` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `version_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `reply_to` int(11) unsigned default NULL,
  `subject` varchar(1000) NOT NULL default '',
  `comment` text NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `version_id` (`version_id`),
  KEY `reply_to` (`reply_to`),
  KEY `created` (`created`),
  CONSTRAINT `versioncomments_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `versions` (`id`),
  CONSTRAINT `versioncomments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `versioncomments_ibfk_3` FOREIGN KEY (`reply_to`) REFERENCES `versioncomments` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor comments for version discussion threads';

DROP TABLE IF EXISTS `users_versioncomments`;
CREATE TABLE `users_versioncomments` (
  `user_id` int(11) unsigned NOT NULL,
  `comment_id` int(11) unsigned NOT NULL,
  `subscribed` tinyint(1) unsigned NOT NULL default '1',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`user_id`,`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `comment_id` (`comment_id`),
  CONSTRAINT `users_versioncomments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_versioncomments_ibfk_2` FOREIGN KEY (`comment_id`) REFERENCES `versioncomments` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor subscriptions to version discussion threads';
