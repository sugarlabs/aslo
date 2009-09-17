CREATE TABLE `addonlogs` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned default NULL,      -- applies to all add-ons if null
  `user_id` int(11) unsigned NOT NULL default 0, -- user that triggers the log action
  `type` tinyint(2) unsigned NOT NULL default 0, -- determines how the rest of the fields are used
  `object1_id` int(11) unsigned default NULL,    -- foreign key or other numeric field
  `object2_id` int(11) unsigned default NULL,    -- foreign key or other numeric field
  `name1` varchar(255) default NULL,             -- name of non-persistent object1 
  `name2` varchar(255) default NULL,             -- name of non-persistent object2
  `notes` text default NULL,                     -- notes for system-wide logging
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
