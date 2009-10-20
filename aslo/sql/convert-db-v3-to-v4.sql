
-- ** 28954-contributions.sql

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


-- ** 28989-paypal-stats.sql

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


-- ** 29118-additional-author-fields.sql

alter table users 
  add column `location` varchar(255) default '' not null, 
  add column `occupation` varchar(255) default '' not null, 
  add column `picture_data` mediumblob default null,
  add column `picture_type` varchar(25) default '' not null;


-- ** 29150-paypal-killswitch.sql

-- Apparently production doesn't have api_disabled in the table.

INSERT IGNORE INTO config VALUES
  ('paypal_disabled', 0),
  ('api_disabled', 0);


-- ** 29960-validation-framework.sql

DROP TABLE IF EXISTS `test_groups`;
CREATE TABLE `test_groups` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `category` varchar(255) NOT NULL,
  `tier` tinyint(4) NOT NULL default '2',
  `critical` tinyint(1) NOT NULL default '0',
  `types` tinyint(7) NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `test_cases`;
CREATE TABLE `test_cases` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `test_group_id` int(11) unsigned NOT NULL default '0',
  `help_link` varchar(255) default NULL,
  `function` varchar(255) NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `test_group_id` (`test_group_id`),
  CONSTRAINT `test_cases_ibfk_1` FOREIGN KEY (`test_group_id`) REFERENCES `test_groups` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `test_results`;
CREATE TABLE `test_results` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `file_id` int(11) unsigned NOT NULL default '0',
  `test_case_id` int(11) unsigned NOT NULL default '0',
  `result` tinyint(2) NOT NULL default '0',
  `line` int(11) NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `message` text default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',  
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`), 
  KEY `file_id` (`file_id`),
  KEY `test_case_id` (`test_case_id`),
  CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`),
  CONSTRAINT `test_results_ibfk_2` FOREIGN KEY (`test_case_id`) REFERENCES `test_cases` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `test_groups`
--

INSERT INTO `test_groups` (`id`, `category`, `tier`, `critical`, `types`) VALUES 
(1,'general',1,1,127),(2,'security',2,0,127),
(11,'general',2,0,1),(12,'security',3,0,1),
(21,'general',2,0,4),(22,'security',3,0,4),
(31,'general',2,0,16),(32,'security',3,0,16),
(41,'general',2,0,2),(42,'security',3,0,2),
(51,'general',2,0,8),(52,'security',3,0,8);

--
-- Dumping data for table `test_cases`
--

INSERT INTO `test_cases` (`id`, `test_group_id`, `help_link`, `function`) VALUES 
(11,1,NULL,'all_general_verifyExtension'),(12,1,NULL,'all_general_verifyInstallRDF'),
(13,1,NULL,'all_general_verifyFileTypes'),
(21,2,NULL,'all_security_filterUnsafeJS'),(22,2,NULL,'all_security_filterUnsafeSettings'),
(23,2,NULL,'all_security_filterRemoteJS'),
(121,12,NULL,'extension_security_checkGeolocation'),(122,12,NULL,'extension_security_checkConduit'),
(211,21,NULL,'dictionary_general_verifyFileLayout'),(212,21,NULL,'dictionary_general_checkExtraFiles'),
(213,21,NULL,'dictionary_general_checkSeaMonkeyFiles'),
(221,22,NULL,'dictionary_security_checkInstallJS'),
(311,31,NULL,'langpack_general_verifyFileLayout'),(312,31,NULL,'langpack_general_checkExtraFiles'),
(321,32,NULL,'langpack_security_filterUnsafeHTML'),(322,32,NULL,'langpack_security_checkRemoteLoading'),
(323,32,NULL,'langpack_security_checkChromeManifest'),
(411,41,NULL,'theme_general_verifyFileLayout'),
(421,42,NULL,'theme_security_checkChromeManifest');


-- ** 30099-validation-kill-switch.sql

INSERT INTO `config` (`key`, `value`) VALUES ('validation_disabled', '0');

-- ** 30162-additional-validation-tests.sql

 INSERT INTO `test_groups` (`id`, `category`, `tier`, `critical`, `types`) VALUES (3,'l10n',2,0,3);
 
 INSERT INTO `test_cases` (`id`, `test_group_id`, `help_link`, `function`) VALUES (14,1,NULL,'all_general_checkJSPollution'),(24,2,NULL,'all_security_libraryChecksum');

-- ** 30180-fix-slow-queries.sql

ALTER TABLE addons 
ADD COLUMN `average_daily_downloads` int(11) unsigned not null default '0' AFTER `totaldownloads`,
ADD COLUMN `average_daily_users` int(11) unsigned not null default '0' AFTER `average_daily_downloads`;




-- ** 30276-contrib-stats-uuid.sql

ALTER TABLE stats_contributions
    ADD COLUMN uuid VARCHAR(255) default NULL;


-- ** 30310-guid-blacklist.sql

CREATE TABLE `blacklisted_guids` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `guid` varchar(255) NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `guid` (`guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 30365-user-average-rating.sql

ALTER TABLE users
  ADD COLUMN `averagerating` varchar(255) default NULL;


-- ** 48133-suggested-contrib-stats.sql

ALTER TABLE stats_contributions
  ADD COLUMN `is_suggested` tinyint(1) unsigned NOT NULL default '0',
  ADD COLUMN `suggested_amount` varchar(255) default NULL;


-- ** 48440-search-engine-tests.sql

INSERT INTO `test_cases` (`id`, `test_group_id`, `help_link`, `function`) VALUES 
(511,51,NULL,'search_general_checkFormat'),
(521,52,NULL,'search_security_checkUpdateURL');

-- ** 48441-download-sources.sql

-- This has nothing to do with r48441, but I needed to add it to the migrations.  Bug 507221 holds details.
DROP TABLE IF EXISTS `download_sources`;
CREATE TABLE `download_sources` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Source list for add-on downloads. Bug 507221.';

INSERT INTO `download_sources` VALUES 
(1, 'category', 'full', NOW()),
(2, 'search', 'full', NOW()),
(3, 'collection', 'full', NOW()),
(4, 'recommended', 'full', NOW()),
(5, 'homepagebrowse', 'full', NOW()),
(6, 'homepagepromo', 'full', NOW()),
(7, 'api', 'full', NOW()),
(8, 'sharingapi', 'full', NOW()),
(9, 'addondetail', 'full', NOW()),
(10, 'external-', 'prefix', NOW());



-- ** 49080-collections-downloads.sql

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


-- ** 49280-share-count-totals.sql

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


-- ** 49385-addon-recommendations.sql

CREATE TABLE `addon_recommendations` (
  `addon_id` int(11) unsigned NOT NULL default '0',
  `other_addon_id` int(11) unsigned NOT NULL default '0',
  `score` float default NULL,
  KEY `addon_id` (`addon_id`),
  KEY `addon_recommendations_ibfk_2` (`other_addon_id`),
  CONSTRAINT `addon_recommendations_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `addon_recommendations_ibfk_2` FOREIGN KEY (`other_addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 49660-collection-ratings.sql

ALTER TABLE collections
  ADD COLUMN `upvotes` int(11) unsigned NOT NULL DEFAULT '0',
  ADD COLUMN `downvotes` int(11) unsigned NOT NULL DEFAULT '0';

DROP TABLE IF EXISTS `collections_votes`;
CREATE TABLE `collections_votes` (
  `collection_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `vote` tinyint(2) NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`collection_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `collections_votes_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `collections_votes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 49665-verification-on-upload.sql

CREATE TABLE `test_results_cache` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `date` datetime NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text default NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 49669-collection-stats.sql

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


-- ** 49732-cron-debug.sql

INSERT INTO config VALUES('cron_debug_enabled',0);


-- ** 49780-collection-rating-column.sql

ALTER TABLE collections
  ADD COLUMN `rating` float NOT NULL DEFAULT '0';


-- ** 50088-sphinx.sql

-- we need a sortable column
ALTER TABLE appversions ADD COLUMN version_int BIGINT UNSIGNED AFTER version;


-- We should ensure a unique "name" (which is an FK to translations), since we'll use this as the
-- document id
-- This takes 8s using near-production data.
ALTER TABLE addons ADD CONSTRAINT UNIQUE(name);


-- We should also create a autoid field (autoincremented unique primary key)
-- for the translations table

ALTER TABLE translations 
DROP PRIMARY KEY, 
ADD COLUMN autoid INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY FIRST, 
ADD CONSTRAINT UNIQUE(`id`, `locale`);

-- Query OK, 481606 rows affected (22.80 sec)


-- This is the main view that will seed our Sphinx index.
CREATE OR REPLACE VIEW translated_addons 
AS
SELECT 
    name.autoid AS id,
    a.id AS addon_id, 
    a.addontype_id AS addontype, 
    a.status, 
    name.locale, 
    CRC32(name.locale) AS locale_ord,
    a.averagerating,
    a.weeklydownloads,
    a.totaldownloads,
    a.inactive,
    LTRIM(name.localized_string) AS name,
    (SELECT localized_string FROM translations WHERE id = a.homepage AND locale = name.locale) AS homepage,
    (SELECT localized_string FROM translations WHERE id = a.description AND locale = name.locale) AS description,
    (SELECT localized_string FROM translations WHERE id = a.summary AND locale = name.locale) AS summary,
    (SELECT localized_string FROM translations WHERE id = a.developercomments AND locale = name.locale) AS developercomments,
    (SELECT max(version_int) FROM versions v, applications_versions av, appversions max WHERE v.addon_id = a.id AND av.version_id = v.id AND av.max = max.id) AS max_ver,
    (SELECT min(version_int) FROM versions v, applications_versions av, appversions min WHERE v.addon_id = a.id AND av.version_id = v.id AND av.min = min.id) AS min_ver,
    UNIX_TIMESTAMP(a.created) AS created,
    (SELECT UNIX_TIMESTAMP(MAX(IFNULL(f.datestatuschanged, f.created))) FROM versions AS v INNER JOIN files AS f ON f.status = 4 AND f.version_id = v.id WHERE v.addon_id=a.id) AS modified
FROM 
    translations name, 
    addons a
WHERE a.name = name.id;

-- This view is used to extract some version-related data

-- versions (we use this for joins)

CREATE OR REPLACE VIEW versions_summary_view AS

SELECT DISTINCT 
    t.autoid AS translation_id, 
    v.addon_id, 
    v.id, 
    av.application_id, 
    v.created, 
    v.modified, 
    min.version_int AS min, 
    max.version_int AS max, 
    MAX(v.created)
FROM versions v, addons a, translations t, applications_versions av, appversions max, appversions min
WHERE 
    a.id = v.addon_id AND a.name = t.id AND av.version_id = v.id
    AND av.min = min.id AND av.max=max.id
GROUP BY 
    translation_id, 
    v.addon_id, 
    v.id, 
    av.application_id, 
    v.created, 
    v.modified, 
    min.version_int, 
    max.version_int;



-- ** 50112-stats-notify-url.sql

ALTER TABLE `stats_contributions`
    ADD COLUMN `transaction_id` varchar(255) default NULL,  
    ADD COLUMN `final_amount` varchar(10) default '0.00',
    ADD COLUMN `post_data` text default NULL;

-- ** 50118-remove-final-amount.sql

ALTER TABLE `stats_contributions` 
    DROP COLUMN `final_amount`;

-- ** 50372-fixing-testresults-cache.sql

ALTER TABLE `test_results_cache` 
    ADD COLUMN `test_case_id` int(11) default 0;

-- ** 50817-stats-contributions-table-fix.sql

DROP TABLE IF EXISTS `stats_share_counts_totals`;
CREATE TABLE `stats_share_counts_totals` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `addon_id` int(10) unsigned NOT NULL default '0',
  `service` varchar(255) not null default '',
  `count` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `count` (`count`),
  UNIQUE KEY (`addon_id`,`service`),
  CONSTRAINT `stats_share_counts_totals_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 51131-hub-promoboxes.sql

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



-- ** 51306-track-download-counts.sql

-- bug 507221
alter table download_counts add column `src` text default null after `count`;


-- ** 51335-blogposts.sql

-- Cache for blog posts
CREATE TABLE `blogposts` (
    `title` varchar(255) NOT NULL default '',
    `date_posted` datetime NOT NULL default '0000-00-00 00:00:00',
    `permalink` text NOT NULL default ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 51434-howto-votes.sql

DROP TABLE IF EXISTS `howto_votes`;
CREATE TABLE `howto_votes` (
  `howto_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `vote` tinyint(2) NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`howto_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `howto_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 51448-hubevents.sql

-- 
-- Simple upcoming events for developers hub
-- 

CREATE TABLE `hubevents` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `url` varchar(255) NOT NULL default '',
  `location` varchar(255) NOT NULL default '',
  `date` date NOT NULL default '0000-00-00',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



-- ** 51698-addonlogs.sql

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


-- ** 51804-unique-users.sql

-- Add a flag for users that are "deleted" (anonymized)
ALTER TABLE users ADD COLUMN deleted tinyint(1) DEFAULT 0 AFTER notifyevents;

-- Use the flag for all currently deleted users
UPDATE users SET nickname='', deleted=1 where nickname='Deleted User';

-- Allow null nicknames
ALTER TABLE users MODIFY `nickname` varchar(255) default null;

-- Currently empty nicknames should be null
UPDATE users SET nickname=null WHERE nickname="";

-- Enforce unique nicknames
-- ALTER TABLE users DROP KEY nickname, ADD UNIQUE (nickname);


-- ** 51823-hub-private-rss.sql

--
-- a top secret uuid is required for add-on news feed RSS
--
CREATE TABLE `hubrsskeys` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `rsskey` char(36) NOT NULL default '',    -- simply a uuid
  `addon_id` int(11) unsigned default NULL, -- for a private add-on rss feed
  `user_id` int(11) unsigned default NULL,  -- OR for a private feed of all the user's add-ons
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rsskey` (`rsskey`),
  UNIQUE KEY `addon_id` (`addon_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `hubrsskeys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hubrsskeys_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ** 51830-download-stats.sql

-- Recalculate missing download stats for 2009-08-31 and 2009-09-01
--
-- This migration has nothing to do revision 51830, but will fix
-- bug 517952 once pushed and run on production.

REPLACE INTO global_stats (name, count, date) VALUES ('addon_downloads_new',
(SELECT IFNULL(SUM(count), 0) FROM download_counts WHERE date = '2009-08-31'),
'2009-08-31');

REPLACE INTO global_stats (name, count, date) VALUES ('addon_downloads_new',
(SELECT IFNULL(SUM(count), 0) FROM download_counts WHERE date = '2009-09-01'),
'2009-09-01');


-- ** 51983-fizzypop.sql

DROP TABLE IF EXISTS `fizzypop`;
CREATE TABLE `fizzypop` (
  `hash` varchar(255) NOT NULL,
  `serialized` text NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Fix empty application version numbers, they F with sorting.
UPDATE appversions SET version='0.0' WHERE version='';


-- ** 52200-bump-wolfs-account.sql

-- This has nothing to do with this revision, but we had to get it in somewhere.
-- Bug 518158 explains.
ALTER TABLE groups_users DROP FOREIGN KEY groups_users_ibfk_4;

ALTER TABLE groups_users ADD FOREIGN KEY groups_users_ibfk_4 (user_id) REFERENCES `users` (id) ON DELETE CASCADE ON UPDATE CASCADE;

UPDATE users SET id=((SELECT blah FROM (SELECT max(id) as blah from users) as blah2)+1) WHERE id=1;

INSERT INTO users (id,email,nickname,deleted,notes) VALUES (1,'nobody@mozilla.org','nobody',1,'Just a placeholder.  See bug 518158');

-- Fix the now broken AUTO_INCREMENT var
ALTER TABLE users AUTO_INCREMENT=1;


-- ** 52201-add-locale-column.sql

-- This is another change that has nothing to do with this revision.  We need this
-- column for stat counting.  See bug 518707
alter table update_counts add column locale text after os;


-- ** 52381-sphinx-updates.sql

-- This is the main view that will seed our Sphinx index.
CREATE OR REPLACE VIEW translated_addons
AS
SELECT
    name.autoid AS id,
    a.id AS addon_id,
    a.addontype_id AS addontype,
    a.status,
    name.locale,
    CRC32(name.locale) AS locale_ord,
    a.averagerating,
    a.weeklydownloads,
    a.totaldownloads,
    a.inactive,
    LTRIM(name.localized_string) AS name,
    (
        SELECT GROUP_CONCAT(version)
        FROM versions WHERE addon_id = a.id
    ) AS addon_versions,
    (
        SELECT GROUP_CONCAT(nickname)
        FROM addons_users au, users u
        WHERE addon_id=a.id AND au.user_id = u.id AND listed = 1
    ) AS authors,
    (
         SELECT GROUP_CONCAT(tag_text) 
         FROM users_tags_addons, tags 
         WHERE tags.id = tag_id AND addon_id = a.id
    ) AS tags,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.homepage AND locale = name.locale
    ) AS homepage,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.description AND locale = name.locale
    ) AS description,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.summary AND locale = name.locale
    ) AS summary,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.developercomments AND locale = name.locale
    ) AS developercomments,
    (
        SELECT IF(addontype=4,9999999999999, max(version_int))
        FROM versions v, files f, applications_versions av, appversions max
        WHERE f.version_id =v.id
            AND v.addon_id = a.id
            AND av.version_id = v.id AND av.max = max.id AND f.status = 4
    ) AS max_ver,
    (
        SELECT IF(addontype=4,0, max(version_int))
        FROM versions v, files f, applications_versions av, appversions min
        WHERE f.version_id =v.id
            AND v.addon_id = a.id
            AND av.version_id = v.id
            AND av.min = min.id
            AND f.status = 4
    ) AS min_ver,
    UNIX_TIMESTAMP(a.created) AS created,
    (
        SELECT UNIX_TIMESTAMP(MAX(IFNULL(f.datestatuschanged, f.created))) 
        FROM versions AS v 
        INNER JOIN files AS f ON f.status = 4 AND f.version_id = v.id 
        WHERE v.addon_id=a.id
    ) AS modified
FROM
    translations name,
    addons a
WHERE a.name = name.id;

-- ** 52782-cleanup.sql

-- ALTER TABLE addons DROP KEY `name_2`, DROP KEY `name_3`;

DROP TABLE  IF EXISTS blog, download_counts_old, downloads, downloads_tmp, favorites, logs_parsed, reviewratings, tag_strength, tshirt_requests;


-- ** 52798-sphinx-cleanup.sql

DROP VIEW translated_addons;
DROP VIEW versions_summary_view;

-- ** 52880-addonsusers-index.sql

alter table addons_users add key(listed);

-- ****************************************************************************

UPDATE `config` SET value = 1 WHERE `key` = 'validation_disabled';

DROP TABLE IF EXISTS `stats_share_counts_totals`;
CREATE TABLE `stats_share_counts_totals` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `addon_id` int(10) unsigned NOT NULL default '0',
  `service` varchar(255) not null default '',
  `count` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `count` (`count`),
  UNIQUE KEY (`addon_id`,`service`),
  CONSTRAINT `stats_share_counts_totals_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `stats_share_counts`;
CREATE TABLE `stats_share_counts` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned NOT NULL default '0',
  `count` int(11) unsigned NOT NULL default '0',
  `service` varchar(128),
  `date` date NOT NULL default '0000-00-00',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `count` (`count`),
  KEY `date` (`date`),
  UNIQUE KEY `one_count_per_addon_service_and_date` (`addon_id`,`service`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
