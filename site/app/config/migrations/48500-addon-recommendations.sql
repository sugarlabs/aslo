CREATE TABLE `addon_recommendations` (
  `addon_id` int(11) unsigned NOT NULL default '0',
  `other_addon_id` int(11) unsigned NOT NULL default '0',
  `score` float default NULL,
  KEY `addon_id` (`addon_id`),
  KEY `addon_recommendations_ibfk_2` (`other_addon_id`),
  CONSTRAINT `addon_recommendations_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `addon_recommendations_ibfk_2` FOREIGN KEY (`other_addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
