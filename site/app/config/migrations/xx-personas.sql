DROP TABLE IF EXISTS `personas`;
CREATE TABLE `personas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `addon_id` int(11) unsigned NOT NULL,
  `persona_id` int(11) unsigned NOT NULL,

  `name` int(11) unsigned default NULL,
  `description` int(11) unsigned default NULL,

  `header` varchar(64) default NULL,
  `footer` varchar(64) default NULL,

  `accentcolor` varchar(10) default NULL,
  `textcolor` varchar(10) default NULL,

  `author` varchar(32) default NULL,
  `display_username` varchar(32) default NULL,

  `submit` datetime NOT NULL default '0000-00-00 00:00:00',
  `approve` datetime NOT NULL default '0000-00-00 00:00:00',

  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  CONSTRAINT `personas_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personas_ibfk_2` FOREIGN KEY (`name`) REFERENCES `translations` (`id`),
  CONSTRAINT `personas_ibfk_3` FOREIGN KEY (`description`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE addons
  CHANGE COLUMN `sharecount` `sharecount` int(11) unsigned NOT NULL default '0';

-- From version 0.1 of this migration.
DROP TABLE IF EXISTS `personas_categories`;
