CREATE TABLE `stats_share_counts_totals` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `addon_id` int(10) unsigned NOT NULL default '0',
  `service` varchar(255) not null default '',
  `count` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `count` (`count`),
  CONSTRAINT `stats_share_counts_totals_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
