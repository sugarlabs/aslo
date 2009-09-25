-- MySQL dump 10.9
--
-- Host: localhost    Database: c_remora
-- ------------------------------------------------------
-- Server version	4.1.20

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

SET FOREIGN_KEY_CHECKS = 0;

--
-- Table structure for table `addons`
--

DROP TABLE IF EXISTS `addons`;
CREATE TABLE `addons` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `guid` varchar(255) NOT NULL default '',
  `name` int(11) unsigned default NULL,
  `defaultlocale` varchar(10) NOT NULL default 'en-US',
  `addontype_id` int(11) unsigned NOT NULL default '0',
  `status` tinyint(2) unsigned NOT NULL default '0',
  `higheststatus` tinyint(2) unsigned NOT NULL default '0',
  `icondata` blob,
  `icontype` varchar(25) NOT NULL default '',
  `homepage` int(11) unsigned default NULL,
  `description` int(11) unsigned default NULL,
  `summary` int(11) unsigned default NULL,
  `supportemail` int(11) unsigned DEFAULT NULL,
  `supporturl` int(11) unsigned DEFAULT NULL,
  `averagerating` varchar(255) default NULL,
  `bayesianrating` float NOT NULL default '0',
  `totalreviews` int(11) unsigned NOT NULL default '0',
  `weeklydownloads` int(11) unsigned NOT NULL default '0',
  `totaldownloads` int(11) unsigned NOT NULL default '0',
  `average_daily_downloads` int(11) unsigned NOT NULL default '0',
  `average_daily_users` int(11) unsigned NOT NULL default '0',
  `sharecount` int(11) unsigned NOT NULL,
  `developercomments` int(11) unsigned default NULL,
  `inactive` tinyint(1) unsigned NOT NULL default '0',
  `trusted` tinyint(1) unsigned NOT NULL default '0',
  `viewsource` tinyint(1) unsigned NOT NULL default '0',
  `publicstats` tinyint(1) unsigned NOT NULL default '0',
  `prerelease` tinyint(1) unsigned NOT NULL default '0',
  `adminreview` tinyint(1) unsigned NOT NULL default '0',
  `sitespecific` tinyint(1) unsigned NOT NULL default '0',
  `externalsoftware` tinyint(1) unsigned NOT NULL default '0',
  `binary` tinyint(1) unsigned NOT NULL default '0',
  `eula` int(11) unsigned default NULL,
  `privacypolicy` int(11) unsigned default NULL,
  `nominationmessage` text,
  `target_locale` varchar(25) default NULL,
  `locale_disambiguation` varchar(255) default NULL,
  `nominationdate` datetime NOT NULL default '0000-00-00 00:00:00',
  `dev_agreement` tinyint(1) unsigned NOT NULL default '0',
  `the_reason` int(11) unsigned default NULL,
  `the_future` int(11) unsigned default NULL,
  `paypal_id` varchar(255) NOT NULL default '',
  `suggested_amount` varchar(255) default NULL,
  `annoying` int(11) default '0',
  `wants_contributions` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `addontype_id` (`addontype_id`),
  KEY `addons_ibfk_2` (`name`),
  KEY `addons_ibfk_3` (`homepage`),
  KEY `addons_ibfk_4` (`supportemail`),
  KEY `addons_ibfk_5` (`supporturl`),
  KEY `addons_ibfk_6` (`description`),
  KEY `addons_ibfk_7` (`summary`),
  KEY `addons_ibfk_8` (`developercomments`),
  KEY `addons_ibfk_9` (`eula`),
  KEY `addons_ibfk_10` (`privacypolicy`),
  KEY `addons_ibfk_11` (`the_reason`),
  KEY `addons_ibfk_12` (`the_future`),
  KEY `status` (`status`),
  KEY `guid` (`guid`),
  KEY `inactive` (`inactive`),
  KEY `target_locale` (`target_locale`),
  KEY `bayesianrating` (`bayesianrating`),
  KEY `sharecount` (`sharecount`),
  CONSTRAINT `addons_ibfk_1` FOREIGN KEY (`addontype_id`) REFERENCES `addontypes` (`id`),
  CONSTRAINT `addons_ibfk_2` FOREIGN KEY (`name`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_3` FOREIGN KEY (`homepage`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_4` FOREIGN KEY (`supportemail`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_5` FOREIGN KEY (`supporturl`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_6` FOREIGN KEY (`description`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_7` FOREIGN KEY (`summary`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_8` FOREIGN KEY (`developercomments`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_9` FOREIGN KEY (`eula`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_10` FOREIGN KEY (`privacypolicy`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_11` FOREIGN KEY (`the_reason`) REFERENCES `translations` (`id`),
  CONSTRAINT `addons_ibfk_12` FOREIGN KEY (`the_future`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `addons_categories`
--

DROP TABLE IF EXISTS `addons_categories`;
CREATE TABLE `addons_categories` (
  `addon_id` int(11) unsigned NOT NULL default '0',
  `category_id` int(11) unsigned NOT NULL default '0',
  `feature` int(1) unsigned NOT NULL default '0',
  `feature_locales` varchar(255) default NULL,
  PRIMARY KEY  (`addon_id`,`category_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `addons_categories_ibfk_3` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `addons_categories_ibfk_4` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `addons_users`
--

DROP TABLE IF EXISTS `addons_users`;
CREATE TABLE `addons_users` (
  `addon_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `role` tinyint(2) unsigned NOT NULL default '5',
  `listed` tinyint(1) unsigned NOT NULL default '1',
  `position` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`addon_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `addons_users_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `addons_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `addon_recommendations`
--

DROP TABLE IF EXISTS `addon_recommendations`;
CREATE TABLE `addon_recommendations` (
  `addon_id` int(11) unsigned NOT NULL default '0',
  `other_addon_id` int(11) unsigned NOT NULL default '0',
  `score` float default NULL,
  KEY `addon_id` (`addon_id`),
  KEY `addon_recommendations_ibfk_2` (`other_addon_id`),
  CONSTRAINT `addon_recommendations_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `addon_recommendations_ibfk_2` FOREIGN KEY (`other_addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `addontypes`
--

DROP TABLE IF EXISTS `addontypes`;
CREATE TABLE `addontypes` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `guid` varchar(255) NOT NULL default '',
  `name` int(11) unsigned NOT NULL default '0',
  `icondata` blob,
  `icontype` varchar(25) NOT NULL default '',
  `shortname` int(11) unsigned NOT NULL default '0',
  `supported` tinyint(1) NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `applications_ibfk_1` (`name`),
  KEY `applications_ibfk_2` (`shortname`),
  KEY `guid` (`guid`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`name`) REFERENCES `translations` (`id`),
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`shortname`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `applications_versions`
--

DROP TABLE IF EXISTS `applications_versions`;
CREATE TABLE `applications_versions` (
  `application_id` int(11) unsigned NOT NULL default '0',
  `version_id` int(11) unsigned NOT NULL default '0',
  `min` int(11) unsigned NOT NULL default '0',
  `max` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`application_id`,`version_id`),
  KEY `version_id` (`version_id`),
  KEY `min` (`min`),
  KEY `max` (`max`),
  CONSTRAINT `applications_versions_ibfk_3` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  CONSTRAINT `applications_versions_ibfk_4` FOREIGN KEY (`version_id`) REFERENCES `versions` (`id`),
  CONSTRAINT `applications_versions_ibfk_5` FOREIGN KEY (`min`) REFERENCES `appversions` (`id`),
  CONSTRAINT `applications_versions_ibfk_6` FOREIGN KEY (`max`) REFERENCES `appversions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `approvals`
--

DROP TABLE IF EXISTS `approvals`;
CREATE TABLE `approvals` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `reply_to` int(11) unsigned default NULL,
  `user_id` int(11) unsigned NOT NULL default '0',
  `reviewtype` varchar(10) NOT NULL default 'pending',
  `action` int(11) NOT NULL default '0',
  `addon_id` int(11) unsigned NOT NULL default '0',
  `file_id` int(11) unsigned NOT NULL default '0',
  `os` varchar(255) NOT NULL default '',
  `applications` varchar(255) NOT NULL default '',
  `comments` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `file_id` (`file_id`),
  KEY `addon_id` (`addon_id`),
  KEY `reply_to` (`reply_to`),
  CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `approvals_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`),
  CONSTRAINT `approvals_ibfk_3` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `approvals_ibfk_4` FOREIGN KEY (`reply_to`) REFERENCES `approvals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `appversions`
--

DROP TABLE IF EXISTS `appversions`;
CREATE TABLE `appversions` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `application_id` int(11) unsigned NOT NULL default '0',
  `version` varchar(255) NOT NULL default '',
  `version_int` bigint(20) unsigned DEFAULT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `application_id` (`application_id`),
  KEY `version` (`version`),
  CONSTRAINT `appversions_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `api_auth_tokens`
--

DROP TABLE IF EXISTS `api_auth_tokens`;
CREATE TABLE `api_auth_tokens` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `user_id` int(11) unsigned NOT NULL default '0',
  `token` varchar(64) NOT NULL,
  `user_agent_hash` varchar(64) NOT NULL,
  `user_profile_hash` varchar(64) NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  CONSTRAINT `api_auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `blacklisted_guids`
--

DROP TABLE IF EXISTS `blacklisted_guids`;
CREATE TABLE `blacklisted_guids` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `guid` varchar(255) NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `guid` (`guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `blapps`
--

DROP TABLE IF EXISTS `blapps`;
CREATE TABLE `blapps` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `blitem_id` int(11) unsigned NOT NULL default '0',
  `guid` varchar(255) default NULL,
  `min` varchar(255) default NULL,
  `max` varchar(255) default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `blitem_id` (`blitem_id`),
  KEY `guid` (`guid`),
  CONSTRAINT `blapps_ibfk_1` FOREIGN KEY (`blitem_id`) REFERENCES `blitems` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `blitems`
--

DROP TABLE IF EXISTS `blitems`;
CREATE TABLE `blitems` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `guid` varchar(255) NOT NULL default '',
  `min` varchar(255) default NULL,
  `max` varchar(255) default NULL,
  `os` varchar(255) default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `blplugins`
--

DROP TABLE IF EXISTS `blplugins`;
CREATE TABLE `blplugins` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `guid` varchar(255) default NULL,
  `min` varchar(255) default NULL,
  `max` varchar(255) NOT NULL default '',
  `os` varchar(255) default NULL,
  `xpcomabi` varchar(255) default NULL,
  `description` varchar(255) default NULL,
  `filename` varchar(255) default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `guid` (`guid`(128),`min`(128))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `blog`
--

DROP TABLE IF EXISTS `blog`;
CREATE TABLE `blog` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `user_id` int(11) unsigned NOT NULL default '0',
  `title` int(11) unsigned NOT NULL default '0',
  `body` int(11) unsigned NOT NULL default '0',
  `hidden` tinyint(1) NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `blog_ibfk_2` (`title`),
  KEY `blog_ibfk_3` (`body`),
  CONSTRAINT `blog_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `blog_ibfk_2` FOREIGN KEY (`title`) REFERENCES `translations` (`id`),
  CONSTRAINT `blog_ibfk_3` FOREIGN KEY (`body`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `id` varchar(32) NOT NULL default '',
  `data` mediumblob NOT NULL,
  `expire` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Cake Cache table for query caches';

--
-- Table structure for table `cake_sessions`
--

DROP TABLE IF EXISTS `cake_sessions`;
CREATE TABLE `cake_sessions` (
  `id` varchar(255) NOT NULL default '',
  `data` text,
  `expires` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `cannedresponses`
--

DROP TABLE IF EXISTS `cannedresponses`;
CREATE TABLE `cannedresponses` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` int(11) unsigned NOT NULL default '0',
  `response` int(11) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `key` varchar(255) NOT NULL default '',
  `value` text NOT NULL,
  PRIMARY KEY  (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `config` ( `key` , `value` )
VALUES ('site_notice', ''),
('submissions_disabled', '0'),
('queues_disabled', '0'),
('search_disabled', '0'),
('api_disabled', '0'),
('stats_updating', '0'),
('firefox_notice_version', ''),
('firefox_notice_url', ''),
('stats_disabled', '0'),
('validation_disabled', '0'),
('cron_debug_enabled', '0');

-- --------------------------------------------------------

--
-- Table structure for table `download_counts`
--

DROP TABLE IF EXISTS `download_counts`;
CREATE TABLE `download_counts` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `addon_id` int(10) unsigned NOT NULL default '0',
  `src` text default null,
  `count` int(10) unsigned NOT NULL default '0',
  `date` date NOT NULL default '0000-00-00',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `count` (`count`),
  KEY `date` (`date`),
  CONSTRAINT `download_counts_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `downloads`
--

DROP TABLE IF EXISTS `downloads`;
CREATE TABLE `downloads` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `file_id` int(11) unsigned default NULL,
  `addon_id` int(11) unsigned default NULL,
  `userip` varchar(255) NOT NULL default '',
  `useragent` varchar(255) NOT NULL default '',
  `counted` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `file_id` (`file_id`),
  KEY `addon_id` (`addon_id`),
  KEY `date_created` (`created`,`addon_id`),
  KEY `date_counted` (`counted`,`addon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `downloads_tmp`
--

DROP TABLE IF EXISTS `downloads_tmp`;
CREATE TABLE `downloads_tmp` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `file_id` int(11) unsigned default NULL,
  `addon_id` int(11) unsigned default NULL,
  `userip` varchar(255) NOT NULL default '',
  `useragent` varchar(255) NOT NULL default '',
  `counted` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `file_id` (`file_id`),
  KEY `addon_id` (`addon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `editor_subscriptions`
--

DROP TABLE IF EXISTS `editor_subscriptions`;
CREATE TABLE `editor_subscriptions` (
  `user_id` int(11) unsigned NOT NULL,
  `addon_id` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`user_id`,`addon_id`),
  KEY `user_id` (`user_id`),
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `editor_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `editor_subscriptions_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor subscriptions for add-on updates';

--
-- Table structure for table `eventlog`
--

DROP TABLE IF EXISTS `eventlog`;
CREATE TABLE `eventlog` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `type` varchar(20) NOT NULL default '',
  `action` varchar(40) NOT NULL default '',
  `field` varchar(20) default NULL,
  `user_id` int(11) unsigned NOT NULL default '0',
  `changed_id` int(11) unsigned NOT NULL default '0',
  `added` varchar(255) default NULL,
  `removed` varchar(255) default NULL,
  `notes` text,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `addonlogs`
--

DROP TABLE IF EXISTS `addonlogs`;
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

--
-- Table structure for table `facebook_data`
--

DROP TABLE IF EXISTS `facebook_data`;
CREATE TABLE `facebook_data` (
  `trait` varchar(255) NOT NULL default '',
  `count_current` int(11) unsigned NOT NULL default '0',
  `count_ever` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`trait`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `facebook_detected`
--

DROP TABLE IF EXISTS `facebook_detected`;
CREATE TABLE `facebook_detected` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `fb_user` int(11) unsigned NOT NULL default '0',
  `addon_guid` varchar(255) NOT NULL default '',
  `disabled` tinyint(1) unsigned NOT NULL default '0',
  `sequence` int(11) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `fb_user` (`fb_user`),
  KEY `addon_guid` (`addon_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `facebook_favorites`
--

DROP TABLE IF EXISTS `facebook_favorites`;
CREATE TABLE `facebook_favorites` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `fb_user` int(11) unsigned NOT NULL default '0',
  `addon_id` int(11) unsigned NOT NULL default '0',
  `imported` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `fb_user` (`fb_user`),
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `facebook_favorites_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `facebook_sessions`
--

DROP TABLE IF EXISTS `facebook_sessions`;
CREATE TABLE `facebook_sessions` (
  `session_key` varchar(255) NOT NULL default '',
  `fb_user` int(11) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`session_key`),
  KEY `fb_user` (`fb_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `facebook_users`
--

DROP TABLE IF EXISTS `facebook_users`;
CREATE TABLE `facebook_users` (
  `fb_user` int(11) unsigned NOT NULL default '0',
  `added` datetime NOT NULL default '0000-00-00 00:00:00',
  `removed` datetime NOT NULL default '0000-00-00 00:00:00',
  `lastactivity` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`fb_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `user_id` int(11) unsigned NOT NULL default '0',
  `addon_id` int(11) unsigned NOT NULL default '0',
  `favorite` tinyint(1) unsigned NOT NULL default '0',
  `reviewfavorite` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `features`
--

DROP TABLE IF EXISTS `features`;
CREATE TABLE `features` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned NOT NULL default '0',
  `start` datetime NOT NULL default '0000-00-00 00:00:00',
  `end` datetime NOT NULL default '0000-00-00 00:00:00',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  `locale` varchar(10) default NULL,
  `application_id` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `features_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `features_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `version_id` int(11) unsigned NOT NULL default '0',
  `platform_id` int(11) unsigned NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `size` int(11) unsigned NOT NULL default '0',
  `hash` varchar(255) default NULL,
  `codereview` tinyint(1) unsigned NOT NULL default '0',
  `status` tinyint(2) unsigned NOT NULL default '0',
  `datestatuschanged` datetime NOT NULL default '0000-00-00 00:00:00',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `version_id` (`version_id`),
  KEY `platform_id` (`platform_id`),
  KEY `status` (`status`),
  CONSTRAINT `files_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `versions` (`id`),
  CONSTRAINT `files_ibfk_2` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `global_stats`
--

DROP TABLE IF EXISTS `global_stats`;
CREATE TABLE `global_stats` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `count` int(10) unsigned NOT NULL default '0',
  `date` date NOT NULL default '0000-00-00',
  UNIQUE KEY `namedate` (`name`, `date`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

DROP TABLE IF EXISTS `stats_addons_collections_counts`;
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

DROP TABLE IF EXISTS `stats_contributions`;
CREATE TABLE `stats_contributions` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned NOT NULL default '0',
  `amount` varchar(255) default NULL,
  `source` varchar(255) default '',
  `annoying` int(11) unsigned NOT NULL default '0',
  `uuid` varchar(255) default NULL,
  `is_suggested` tinyint(1) unsigned NOT NULL default '0',
  `suggested_amount` varchar(255) default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `transaction_id` varchar(255) default NULL,
  `post_data` text default NULL,
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `stats_contributions_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `rules` text,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `groups_users`
--

DROP TABLE IF EXISTS `groups_users`;
CREATE TABLE `groups_users` (
  `group_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`group_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `groups_users_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `groups_users_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `licenses`
--

DROP TABLE IF EXISTS `licenses`;
CREATE TABLE `licenses` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` int(1) NOT NULL default '-1',
  `text` int(11) unsigned default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `text` (`text`),
  CONSTRAINT `licenses_ibfk_1` FOREIGN KEY (`text`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `logs_parsed`
--

DROP TABLE IF EXISTS `logs_parsed`;
CREATE TABLE `logs_parsed` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `geo` varchar(10) NOT NULL default '',
  `downloads_done` tinyint(1) unsigned NOT NULL default '0',
  `updatepings_done` tinyint(1) unsigned NOT NULL default '0',
  `collections_done` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`, `geo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `platforms`
--

DROP TABLE IF EXISTS `platforms`;
CREATE TABLE `platforms` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` int(11) unsigned NOT NULL default '0',
  `shortname` int(11) unsigned NOT NULL default '0',
  `icondata` blob,
  `icontype` varchar(25) NOT NULL default '',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `platforms_ibfk_1` (`name`),
  KEY `platforms_ibfk_2` (`shortname`),
  CONSTRAINT `platforms_ibfk_1` FOREIGN KEY (`name`) REFERENCES `translations` (`id`),
  CONSTRAINT `platforms_ibfk_2` FOREIGN KEY (`shortname`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `previews`
--

DROP TABLE IF EXISTS `previews`;
CREATE TABLE `previews` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned NOT NULL default '0',
  `filedata` mediumblob,
  `filetype` varchar(25) NOT NULL default '',
  `thumbdata` blob,
  `thumbtype` varchar(25) NOT NULL default '',
  `caption` int(11) unsigned default NULL,
  `highlight` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `previews_ibfk_2` (`caption`),
  CONSTRAINT `previews_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `previews_ibfk_2` FOREIGN KEY (`caption`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `reviewratings`
--

DROP TABLE IF EXISTS `reviewratings`;
CREATE TABLE `reviewratings` (
  `review_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `helpful` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`review_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `reviewratings_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`),
  CONSTRAINT `reviewratings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `version_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `reply_to` int(11) unsigned default NULL,
  `rating` tinyint(3) unsigned default NULL,
  `title` int(11) unsigned NOT NULL default '0',
  `body` int(11) unsigned NOT NULL default '0',
  `editorreview` tinyint(1) unsigned NOT NULL default '0',
  `flag` tinyint(1) unsigned NOT NULL default '0',
  `sandbox` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `reply_to` (`reply_to`),
  UNIQUE KEY `one_review_per_user` (`version_id`,`user_id`,`reply_to`),
  KEY `version_id` (`version_id`),
  KEY `reviews_ibfk_2` (`user_id`),
  KEY `reviews_ibfk_3` (`title`),
  KEY `reviews_ibfk_4` (`body`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `versions` (`id`),
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`title`) REFERENCES `translations` (`id`),
  CONSTRAINT `reviews_ibfk_4` FOREIGN KEY (`body`) REFERENCES `translations` (`id`),
  CONSTRAINT `reviews_reply` FOREIGN KEY (`reply_to`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `reviews_moderation_flags`
--
DROP TABLE IF EXISTS `reviews_moderation_flags`;
CREATE TABLE `reviews_moderation_flags` (
  `id` int(11) NOT NULL auto_increment,
  `review_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `flag_name` varchar(64) NOT NULL default 'review_flag_reason_other',
  `flag_notes` varchar(100) NOT NULL default '',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `index_review_user` (`review_id`,`user_id`),
  KEY `index_user` (`user_id`),
  KEY `index_review` (`review_id`),
  KEY `index_modified` (`modified`),
  CONSTRAINT `reviews_moderation_flags_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`),
  CONSTRAINT `reviews_moderation_flags_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Table structure for table `versioncomments`
--
DROP TABLE IF EXISTS `versioncomments`;
CREATE TABLE `versioncomments` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `version_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `reply_to` int(11) unsigned default NULL,
  `subject` varchar(1000) NOT NULL default '',
  `comment` text NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `version_id` (`version_id`),
  KEY `reply_to` (`reply_to`),
  KEY `created` (`created`),
  CONSTRAINT `versioncomments_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `versions` (`id`),
  CONSTRAINT `versioncomments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `versioncomments_ibfk_3` FOREIGN KEY (`reply_to`) REFERENCES `versioncomments` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor comments for version discussion threads';

--
-- Table structure for table `users_versioncomments`
--
DROP TABLE IF EXISTS `users_versioncomments`;
CREATE TABLE `users_versioncomments` (
  `user_id` int(11) unsigned NOT NULL,
  `comment_id` int(11) unsigned NOT NULL,
  `subscribed` tinyint(1) unsigned NOT NULL default '1',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`user_id`,`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `comment_id` (`comment_id`),
  CONSTRAINT `users_versioncomments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_versioncomments_ibfk_2` FOREIGN KEY (`comment_id`) REFERENCES `versioncomments` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor subscriptions to version discussion threads';


--
-- Table structure for table `stats_share_counts`
--

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

--
-- Table structure for table `stats_share_counts_totals`
--
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

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` int(11) unsigned default NULL,
  `description` int(11) unsigned default NULL,
  `addontype_id` int(11) unsigned NOT NULL default '0',
  `application_id` int(11) unsigned default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  `count` int(11) NOT NULL DEFAULT '0',
  `weight` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (`id`),
  KEY `addontype_id` (`addontype_id`),
  KEY `application_id` (`application_id`),
  KEY `categories_ibfk_3` (`name`),
  KEY `categories_ibfk_4` (`description`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`addontype_id`) REFERENCES `addontypes` (`id`),
  CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  CONSTRAINT `categories_ibfk_3` FOREIGN KEY (`name`) REFERENCES `translations` (`id`),
  CONSTRAINT `categories_ibfk_4` FOREIGN KEY (`description`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `translations`
--

DROP TABLE IF EXISTS `translations`;
CREATE TABLE `translations` (
  `autoid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id` int(11) unsigned NOT NULL default '0',
  `locale` varchar(10) NOT NULL default '',
  `localized_string` text,
  `created` datetime default NULL,
  `modified` datetime default NULL,
  PRIMARY KEY (`autoid`),
  UNIQUE KEY `id` (`id`,`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



--
-- Table structure for table `translations_seq`
--

DROP TABLE IF EXISTS `translations_seq`;
CREATE TABLE `translations_seq` (
  `id` int(11) NOT NULL default '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `update_counts`
--

DROP TABLE IF EXISTS `update_counts`;
CREATE TABLE `update_counts` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned NOT NULL default '0',
  `count` int(11) unsigned NOT NULL default '0',
  `version` text,
  `status` text,
  `application` text,
  `os` text,
  `update_counts` text,
  `date` date NOT NULL default '0000-00-00',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `count` (`count`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `email` varchar(255) NULL default '',
  `password` varchar(255) NOT NULL default '',
  `firstname` varchar(255) NOT NULL default '',
  `lastname` varchar(255) NOT NULL default '',
  `nickname` varchar(255) default NULL,
  `bio` int(11) UNSIGNED default NULL,
  `location` varchar(255) NOT NULL default '',
  `occupation` varchar(255) NOT NULL default '',
  `picture_data` mediumblob default null,
  `picture_type` varchar(25) NOT NULL default '',
  `emailhidden` tinyint(1) unsigned NOT NULL default '0',
  `sandboxshown` tinyint(1) unsigned NOT NULL default '0',
  `homepage` varchar(255) default NULL,
  `display_collections` tinyint(1) unsigned NOT NULL default '0',
  `display_collections_fav` tinyint(1) unsigned NOT NULL default '0',
  `confirmationcode` varchar(255) NOT NULL default '',
  `resetcode` varchar(255) NOT NULL default '',
  `resetcode_expires` datetime NOT NULL default '0000-00-00 00:00:00',
  `notifycompat` tinyint(1) unsigned NOT NULL default '1',
  `notifyevents` tinyint(1) unsigned NOT NULL default '1',
  `deleted` tinyint(1) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  `notes` text,
  `averagerating` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `nickname` (`nickname`),
  KEY `unconfirmed` (`created`,`confirmationcode`),
  KEY `notifycompat` (`notifycompat`),
  KEY `notifyevents` (`notifyevents`),
  KEY `bio` (`bio`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`bio`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `versions`
--

DROP TABLE IF EXISTS `versions`;
CREATE TABLE `versions` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `addon_id` int(11) unsigned NOT NULL default '0',
  `license_id` int(11) unsigned default NULL,
  `version` varchar(255) NOT NULL default '',
  `approvalnotes` text,
  `releasenotes` int(11) unsigned default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `addon_id` (`addon_id`),
  KEY `versions_ibfk_2` (`releasenotes`),
  KEY `license_id` (`license_id`),
  CONSTRAINT `versions_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `versions_ibfk_2` FOREIGN KEY (`releasenotes`) REFERENCES `translations` (`id`),
  CONSTRAINT `versions_ibfk_3` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `text_search_summary`
-- This materialized view maintains a indexed summary of the text data in an addon to make search faster
--

DROP TABLE IF EXISTS `text_search_summary`;
CREATE TABLE `text_search_summary` (
  `id` int(11) NOT NULL,
  `locale` varchar(10) NOT NULL,
  `addontype` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `inactive` int(11) NOT NULL,
  `averagerating` varchar(255),
  `weeklydownloads` int(11) UNSIGNED ,
  `name` text,
  `summary` text,
  `description` text,
  `tags` text,
  FULLTEXT KEY `name` (`name`,`summary`,`description`),
  FULLTEXT KEY `na_su_de_ta` (`name`,`summary`,`description`,`tags`),
  FULLTEXT KEY `tags` (`tags`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `version_summary`
-- This materialized view maintains a summary of information about the most recently created version of an addon
--

DROP TABLE IF EXISTS `versions_summary`;
CREATE TABLE `versions_summary` (
  `addon_id` int(11) NOT NULL,
  `version_id` int(11) NOT NULL,
  `application_id` int(11),
  `created` DATETIME NOT NULL,
  `modified`DATETIME NOT NULL,
  `min` int(11) unsigned,
  `max` int(11) unsigned,
  INDEX (addon_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `collections_search_summary`
-- This materialized view maintains a indexed summary of the text data in a collection to make search faster
--

DROP TABLE IF EXISTS `collections_search_summary`;
CREATE TABLE `collections_search_summary` (
  `id` int(11) NOT NULL,
  `locale` varchar(10) NOT NULL,
  `name` text,
  `description` text,
  FULLTEXT KEY `name` (`name`,`description`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `tshirt_requests`
-- Used to store addresses of developers who claimed a T-shirt in
-- T-Shirt promotion.
--

DROP TABLE IF EXISTS `tshirt_requests`;
CREATE TABLE `tshirt_requests` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `user_id` int(11) unsigned NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `address2` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `region_province` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `zip_postal_code` varchar(255) NOT NULL,
  `telephone` varchar(255) NOT NULL,
  `tshirt_size` varchar(10) NOT NULL,
  `comment` varchar(1000) NOT NULL,
  KEY `user_id` (`user_id`),
  PRIMARY KEY `id` (`id`),
  CONSTRAINT `tshirt_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;


DROP TABLE IF EXISTS `collections`;
CREATE TABLE `collections` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `uuid` char(36) NOT NULL default '',
  `name` int(11) unsigned NOT NULL,
  `defaultlocale` varchar(10) NOT NULL default 'en-US',
  `collection_type` int(11) unsigned NOT NULL DEFAULT '0',
  `icondata` blob,
  `icontype` varchar(25) NOT NULL default '',
  `nickname` varchar(30) NULL,
  `description` int(11) unsigned NOT NULL,
  `access` tinyint(1) NOT NULL DEFAULT '0',
  `listed` tinyint(1) NOT NULL DEFAULT '1',
  `password` varchar(255) NOT NULL,
  `subscribers` int(11) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  `downloads` int(11) unsigned NOT NULL DEFAULT '0',
  `application_id` int(10) unsigned default NULL,
  `weekly_subscribers` int(11) unsigned NOT NULL default '0',
  `monthly_subscribers` int(11) unsigned NOT NULL default '0',
  `addonCount` int(11) unsigned NOT NULL default '0',
  `upvotes` int(11) unsigned NOT NULL DEFAULT '0',
  `downvotes` int(11) unsigned NOT NULL DEFAULT '0',
  `rating` float NOT NULL default '0',
  PRIMARY KEY `id` (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `nickname` (`nickname`),
  KEY (`listed`),
  KEY `application_id` (`application_id`),
  KEY `name` (`name`),
  KEY `description` (`description`),
  CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  CONSTRAINT `collections_ibfk_2` FOREIGN KEY (`name`) REFERENCES `translations` (`id`),
  CONSTRAINT `collections_ibfk_3` FOREIGN KEY (`description`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `addons_collections`;
CREATE TABLE `addons_collections` (
  `addon_id` int(11) unsigned NOT NULL ,
  `collection_id` int(11) unsigned NOT NULL ,
  `user_id` int(11) unsigned default NULL,
  `added` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  `category` tinyint(4) unsigned default NULL COMMENT 'for interactive collections template',
  `comments` int(11) unsigned default NULL,
  `downloads` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY ( `addon_id` , `collection_id` ),
  KEY `addon_id` (`addon_id`),
  KEY `user_id` (`user_id`),
  KEY `collection_id` (`collection_id`),
  KEY `comments` (`comments`),
  CONSTRAINT `addons_collections_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `addons_collections_ibfk_2` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `addons_collections_ibfk_3` FOREIGN KEY (`comments`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `collection_features`;
CREATE TABLE `collection_features` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `title` int(11) unsigned NOT NULL default '0',
  `tagline` int(11) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `collection_features_ibfk_1` (`title`),
  KEY `collection_features_ibfk_2` (`tagline`),
  CONSTRAINT `collection_features_ibfk_1` FOREIGN KEY (`title`) REFERENCES `translations` (`id`),
  CONSTRAINT `collection_features_ibfk_2` FOREIGN KEY (`tagline`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `collection_promos`
-- Named that way because it's not actually a join.
--

DROP TABLE IF EXISTS `collection_promos`;
CREATE TABLE `collection_promos` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `collection_id` int(11) unsigned NOT NULL default '0',
  `locale` varchar(10) default NULL,
  `collection_feature_id` int(11) unsigned NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `one_collection_per_tagline_per_locale` (`collection_id`,`locale`,`collection_feature_id`),
  KEY `collection_id` (`collection_id`),
  KEY `collection_promos_ibfk_2` (`collection_feature_id`),
  CONSTRAINT `collection_promos_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `collection_promos_ibfk_2` FOREIGN KEY (`collection_feature_id`) REFERENCES `collection_features` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `collections_categories`;
CREATE TABLE `collections_categories` (
  `collection_id` int(11) unsigned NOT NULL ,
  `category_id` int(11) unsigned NOT NULL ,
  PRIMARY KEY ( `collection_id` , `category_id` ),
  KEY `collection_id` (`collection_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `collections_categories_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `addons` (`id`),
  CONSTRAINT `collections_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `collection_subscriptions`;
CREATE TABLE `collection_subscriptions` (
  `user_id` int(11) unsigned NOT NULL,
  `collection_id` int(11) unsigned NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`user_id`, `collection_id`),
  CONSTRAINT `collections_subscriptions_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `collections_subscriptions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `collections_users`;
CREATE TABLE `collections_users` (
  `collection_id` int(11) unsigned NOT NULL default '0',
  `user_id` int(11) unsigned NOT NULL default '0',
  `role` tinyint(2) unsigned NOT NULL default '5',
  PRIMARY KEY  (`collection_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `collections_users_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `collections_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

DROP TABLE IF EXISTS `mimes`;
CREATE TABLE `mimes` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `mime` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `suffixes` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`,`mime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `plugins`;
CREATE TABLE `plugins` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `version` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `vendor` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon_url` varchar(255) NOT NULL,
  `latest_version` varchar(255) NOT NULL,
  `installer_location` varchar(255) NOT NULL,
  `installer_hash` varchar(255) NOT NULL,
  `installer_shows_ui` tinyint(1) NOT NULL,
  `license_url` varchar(255) NOT NULL,
  `needs_restart` tinyint(1) NOT NULL,
  `description` text NOT NULL,
  `min` varchar(255) default NULL,
  `max` varchar(255) default NULL,
  `os` varchar(255) default NULL,
  `xpcomabi` varchar(255) default NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY  (`id`,`name`,`filename`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `plugins_mimes`;
CREATE TABLE `plugins_mimes` (
  `mime_id` int(11) unsigned NOT NULL,
  `plugin_id` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`mime_id`,`plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `schema_version`;
CREATE TABLE `schema_version` (
  `version` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` int(11) unsigned NOT NULL auto_increment,
    `tag_text` varchar(128) NOT NULL,
    `blacklisted` tinyint(1) NOT NULL default 0,
    `created` datetime NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY (`id`),
    UNIQUE KEY `tag_text` (`tag_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tag_stat`;
CREATE TABLE `tag_stat` (
    `tag_id` int(11) unsigned NOT NULL,
    `num_addons` int(11) unsigned NOT NULL default '0',
    `modified` datetime NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY  (`tag_id`),
    CONSTRAINT `tag_stat_ibfk_1` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tag_strength`;
CREATE TABLE `tag_strength` (
    `tag1_id` int(11) unsigned NOT NULL,
    `tag2_id` int(11) unsigned NOT NULL,
    `strength` int(11) unsigned NOT NULL default '0',
    PRIMARY KEY  (`tag1_id`, `tag2_id`),
    CONSTRAINT `tag_strength_ibfk_1` FOREIGN KEY (`tag1_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
    CONSTRAINT `tag_strength_ibfk_2` FOREIGN KEY (`tag2_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users_tags_addons`;
CREATE TABLE `users_tags_addons` (
    `user_id` int(11) unsigned NOT NULL,
    `tag_id` int(11) unsigned NOT NULL,
    `addon_id` int(11) unsigned NOT NULL,
    `created` datetime NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY  (`user_id`,`tag_id`,`addon_id`),
      INDEX (`tag_id`),
    INDEX (`addon_id`),
    CONSTRAINT `users_tags_addons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `users_tags_addons_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
    CONSTRAINT `users_tags_addons_ibfk_3` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Developers Hub tables
--

DROP TABLE IF EXISTS `hubpromos`;
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

DROP TABLE IF EXISTS `hubevents`;
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

DROP TABLE IF EXISTS `hubrsskeys`;
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

-- Cache for blog posts
DROP TABLE IF EXISTS `blogposts`;
CREATE TABLE `blogposts` (
    `title` varchar(255) NOT NULL default '',
    `date_posted` datetime NOT NULL default '0000-00-00 00:00:00',
    `permalink` text NOT NULL default ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



--
-- Validation tables
--

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

DROP TABLE IF EXISTS `test_results_cache`;
CREATE TABLE `test_results_cache` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `date` datetime NOT NULL,
  `key` varchar(255) NOT NULL,
  `test_case_id` int(11) NOT NULL,
  `value` mediumtext,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `fizzypop`;
CREATE TABLE `fizzypop` (
    `hash` varchar(255) NOT NULL,
    `serialized` text NOT NULL,
    PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Default data that doesn't change over time (or isn't supposed to anyway).
--

--
-- Dumping data for table `addontypes`
--
INSERT INTO `addontypes` (`id`, `created`, `modified`) VALUES
(1, '2006-08-21 23:53:19', '2006-08-21 23:53:19'),
(2, '2006-08-21 23:53:24', '2006-08-21 23:53:24'),
(3, '2006-08-21 23:53:30', '2006-08-21 23:53:30'),
(4, '2006-08-21 23:53:36', '2006-08-21 23:53:36'),
(5, '2006-08-21 23:53:58', '2006-08-21 23:53:58'),
(6, '2006-08-21 23:54:09', '2006-08-21 23:54:09'),
(7, '2007-01-19 14:00:00', '2007-01-19 14:00:00');

--
-- Dumping data for table `facebook_data`
--
INSERT INTO `facebook_data` (trait) VALUES
('age_under12'), ('age_12to15'), ('age_16to19'), ('age_20to23'),
('age_24to27'), ('age_28to31'), ('age_32to35'), ('age_36to39'),
('age_40to49'), ('age_50to59'), ('age_above60'), ('sex_male'),
('sex_female');

--
-- Dumping data for table `test_groups`
--

INSERT INTO `test_groups` (`id`, `category`, `tier`, `critical`, `types`) VALUES
(1,'general',1,1,127),(2,'security',2,0,127),(3,'l10n',2,0,3),
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
(13,1,NULL,'all_general_verifyFileTypes'),(14,1,NULL,'all_general_checkJSPollution'),
(21,2,NULL,'all_security_filterUnsafeJS'),(22,2,NULL,'all_security_filterUnsafeSettings'),
(23,2,NULL,'all_security_filterRemoteJS'),(24,2,NULL,'all_security_libraryChecksum'),
(121,12,NULL,'extension_security_checkGeolocation'),(122,12,NULL,'extension_security_checkConduit'),
(211,21,NULL,'dictionary_general_verifyFileLayout'),(212,21,NULL,'dictionary_general_checkExtraFiles'),
(213,21,NULL,'dictionary_general_checkSeaMonkeyFiles'),
(221,22,NULL,'dictionary_security_checkInstallJS'),
(311,31,NULL,'langpack_general_verifyFileLayout'),(312,31,NULL,'langpack_general_checkExtraFiles'),
(321,32,NULL,'langpack_security_filterUnsafeHTML'),(322,32,NULL,'langpack_security_checkRemoteLoading'),
(323,32,NULL,'langpack_security_checkChromeManifest'),
(411,41,NULL,'theme_general_verifyFileLayout'),
(421,42,NULL,'theme_security_checkChromeManifest'),
(511,51,NULL,'search_general_checkFormat'),
(521,52,NULL,'search_security_checkUpdateURL');

--
-- Initialize `translations_seq` to be the number of the last entry
--   in the `translations` table
--

-- INSERT INTO `translations_seq` (id) SELECT MAX(id) from `translations`;
INSERT INTO `translations_seq` (id) values (1000);

-- Old tables.  Remove these lines sometime.
DROP TABLE IF EXISTS `acos`;
DROP TABLE IF EXISTS `activeusers`;
DROP TABLE IF EXISTS `addonevents`;
DROP TABLE IF EXISTS `aros`;
DROP TABLE IF EXISTS `aros_acos`;
DROP TABLE IF EXISTS `downloads-tmp`;
DROP TABLE IF EXISTS `features_tags`;
DROP TABLE IF EXISTS `foo`;
DROP TABLE IF EXISTS `userevents`;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


/* These are my TRIGGERS. */

CREATE TRIGGER collections_update_addon_count_insert
  AFTER INSERT ON addons_collections
  FOR EACH ROW
    UPDATE collections AS c
    SET c.addonCount = c.addonCount + 1
    WHERE c.id=NEW.collection_id;

CREATE TRIGGER collections_update_addon_count_delete
  AFTER DELETE ON addons_collections
  FOR EACH ROW
    UPDATE collections AS c
    SET c.addonCount = c.addonCount - 1
    WHERE c.id=OLD.collection_id;

CREATE TRIGGER collections_update_subscriber_count_insert
  AFTER INSERT ON collection_subscriptions
  FOR EACH ROW
    UPDATE collections AS c
    SET c.subscribers = c.subscribers + 1
    WHERE c.id=NEW.collection_id;

CREATE TRIGGER collections_update_subscriber_count_delete
  AFTER DELETE ON collection_subscriptions
  FOR EACH ROW
    UPDATE collections AS c
    SET c.subscribers = c.subscribers - 1
    WHERE c.id=OLD.collection_id;

DELIMITER |

drop trigger if exists trg_tag_stat_inc |

CREATE TRIGGER trg_tag_stat_inc AFTER INSERT ON `users_tags_addons`
   FOR EACH ROW
   BEGIN
    insert ignore INTO tag_stat(tag_id, num_addons, modified) values(NEW.tag_id, 0, now());
    UPDATE `tag_stat` set num_addons = (num_addons+1) WHERE tag_id = NEW.tag_id;
  END;
|

drop trigger if exists trg_tag_stat_dec |

CREATE TRIGGER trg_tag_stat_dec AFTER DELETE ON `users_tags_addons`
   FOR EACH ROW
   BEGIN
    UPDATE `tag_stat` set num_addons = (num_addons-1) WHERE tag_id = OLD.tag_id;
  END;

|
DELIMITER ;


-- Collection voting triggers
DELIMITER |
DROP TRIGGER IF EXISTS collection_vote_insert|
CREATE TRIGGER collection_vote_insert
  AFTER INSERT ON collections_votes
  FOR EACH ROW
  CASE NEW.vote
  WHEN 1 THEN
    UPDATE collections SET upvotes=(upvotes + 1) WHERE id=NEW.collection_id;
  WHEN -1 THEN
    UPDATE collections SET downvotes=(downvotes + 1) WHERE id=NEW.collection_id;
  END CASE;
|

DROP TRIGGER IF EXISTS collection_vote_delete|
CREATE TRIGGER collection_vote_delete
  AFTER DELETE ON collections_votes
  FOR EACH ROW
  CASE OLD.vote
  WHEN 1 THEN
    UPDATE collections SET upvotes=(upvotes - 1) WHERE id=OLD.collection_id;
  WHEN -1 THEN
    UPDATE collections SET downvotes=(downvotes - 1) WHERE id=OLD.collection_id;
  END CASE;
|
DELIMITER ;

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
        SELECT max(version_int)
        FROM versions v, files f, applications_versions av, appversions max
        WHERE f.version_id =v.id
            AND v.addon_id = a.id
            AND av.version_id = v.id AND av.max = max.id AND f.status = 4
    ) AS max_ver,
    (
        SELECT min(version_int)
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
-- This view is used to extract some version-related data

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

