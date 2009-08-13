-- This has nothing to do with this revision, but apparently this table wasn't added
-- to the maintenance scripts 5 months ago and adding it at the proper revision
-- (22770) now will make it out of order.  So...

CREATE TABLE `stats_addons_collections_counts` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `addon_id` int(10) unsigned NOT NULL default '0',
      `collection_id` int(10) unsigned NOT NULL default '0',
      `count` int(10) unsigned NOT NULL default '0',
      `date` date NOT NULL default '0000-00-00',
      PRIMARY KEY  (`id`),
      KEY `addon_id` (`addon_id`),
      KEY `collection_id` (`collection_id`),
      KEY `count` (`count`),
      KEY `date` (`date`),
      CONSTRAINT `stats_addons_collections_counts_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
      CONSTRAINT `stats_addons_collections_counts_ibfk_2` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
