ALTER TABLE stats_contributions
  ADD COLUMN `is_suggested` tinyint(1) unsigned NOT NULL default '0',
  ADD COLUMN `suggested_amount` varchar(255) default NULL;
