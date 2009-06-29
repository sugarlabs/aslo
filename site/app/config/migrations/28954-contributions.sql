ALTER TABLE addons
  ADD COLUMN `the_reason` int(11) unsigned default NULL,
  ADD COLUMN `the_future` int(11) unsigned default NULL,
  ADD COLUMN `paypal_id` varchar(255) NOT NULL default '',
  ADD COLUMN `suggested_amount` varchar(255) default NULL,
  ADD COLUMN `annoying` int(11) default '0',
  ADD COLUMN `wants_contributions` tinyint(1) unsigned NOT NULL default '0',
  ADD KEY `addons_ibfk_11` (`the_reason`),
  ADD KEY `addons_ibfk_12` (`the_future`),
  ADD CONSTRAINT `addons_ibfk_11` FOREIGN KEY (`the_reason`) REFERENCES `translations` (`id`),
  ADD CONSTRAINT `addons_ibfk_12` FOREIGN KEY (`the_future`) REFERENCES `translations` (`id`);
