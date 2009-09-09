-- 
-- Content blocks! All webapps sooner or later turn into a CMS.
-- 

CREATE TABLE `hubpromos` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `heading` int(11) unsigned NOT NULL default '0',
  `body` int(11) unsigned NOT NULL default '0',
  `visibility` tinyint(2) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `hubpromos_ibfk_1` (`heading`),
  KEY `hubpromos_ibfk_2` (`body`),
  CONSTRAINT `hubpromos_ibfk_1` FOREIGN KEY (`heading`) REFERENCES `translations` (`id`),
  CONSTRAINT `hubpromos_ibfk_2` FOREIGN KEY (`body`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

