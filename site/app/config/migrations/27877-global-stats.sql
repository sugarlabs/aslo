DROP TABLE IF EXISTS `global_stats`;
CREATE TABLE `global_stats` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `count` int(10) unsigned NOT NULL default '0',
  `date` date NOT NULL default '0000-00-00',
  UNIQUE KEY `namedate` (`name`, `date`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
