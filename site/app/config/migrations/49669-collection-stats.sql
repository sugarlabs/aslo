--
-- Creating stats_collections table
--
DROP TABLE IF EXISTS `stats_collections`;
CREATE TABLE `stats_collections` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `collection_id` int(11) unsigned NOT NULL default '0',
  `name` varchar(255) NOT NULL default '',
  `count` int(10) unsigned NOT NULL default '0',
  `date` date NOT NULL default '0000-00-00',
  UNIQUE KEY `collectionnamedate` (`collection_id`, `name`, `date`),
  KEY `collection_id` (`collection_id`),
  CONSTRAINT `stats_collections_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Initializing collection stats with approximate collection subscription history
--
REPLACE INTO `stats_collections` (`name`, `collection_id`, `date`, `count`) 
    SELECT 'new_subscribers', `collection_id`, DATE(`created`) AS `the_date`, COUNT(*)
    FROM `collection_subscriptions`
    GROUP BY `collection_id`, `the_date`;
