CREATE TABLE `stats_contributions` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned NOT NULL default '0',
  `amount` varchar(10) default '0.00',
  `source` varchar(255) default '',
  `annoying` int(11) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `stats_contributions_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
