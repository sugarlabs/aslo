SET FOREIGN_KEY_CHECKS=0;

DELETE FROM `groups`;
INSERT INTO `groups` (`id`, `name`, `rules`, `created`, `modified`) VALUES 
(1, 'Admins', '*:*', '2007-02-10 13:29:03', '2007-02-10 16:57:10'),
(2, 'Editors', 'Editors:*', '2007-02-10 16:32:21', '2007-02-10 16:32:21');

DELETE FROM `groups_users`;
INSERT INTO `groups_users` (`group_id`, `user_id`) VALUES 
(1, 2),
(2, 2),
(2, 3);

-- Add root@sugarlabs.org user with password "test" 
DELETE from `users`;
INSERT INTO `users` (`id`, `email`, `password`, `firstname`, `lastname`, `nickname`, `emailhidden`, `sandboxshown`, `homepage`, `confirmationcode`, `created`, `modified`, `notes`) VALUES 
(1, 'nobody@addons.mozilla.org', '098f6bcd4621d373cade4e832627b4f6', 'nobody', 'nobody', 'nobody', 0, 1, 'http://wiki.sugarlabs.org', '', now(), now(), NULL),
(2, 'admin@sugarlabs.org', '098f6bcd4621d373cade4e832627b4f6', 'root', 'root', 'root', 0, 1, 'http://wiki.sugarlabs.org', '', now(), now(), NULL),
(3, 'editor@sugarlabs.org', '098f6bcd4621d373cade4e832627b4f6', 'editor', 'editor', 'editor', 0, 1, 'http://wiki.sugarlabs.org', '', now(), now(), NULL),
(4, 'developer@sugarlabs.org', '098f6bcd4621d373cade4e832627b4f6', 'developer', 'developer', 'developer', 0, 1, 'http://wiki.sugarlabs.org', '', now(), now(), NULL);
-- (1000, 'full', '098f6bcd4621d373cade4e832627b4f6', 'full', 'full', 'full', 0, 1, 'http://wiki.sugarlabs.org', '', now(), now(), NULL);
-- (1001, 'less', '098f6bcd4621d373cade4e832627b4f6', 'less', 'less', 'less', 0, 1, 'http://wiki.sugarlabs.org', '', now(), now(), NULL);

-- Create Sugar application, 1
DELETE from applications;
insert into applications values
(1, '{3ca105e0-2280-4897-99a0-c277d1b733d2}', 50000, NULL, '', 50001, 1, now(), now())
;

DELETE FROM `categories`;
INSERT INTO categories (id, name, description, addontype_id, application_id, created, modified, weight) VALUES
(100, 50002, 50003, 1, 1, now(), now(), 0),
(101, 50004, 50005, 1, 1, now(), now(), 0),
(102, 50006, 50007, 1, 1, now(), now(), 0),
(103, 50008, 50009, 1, 1, now(), now(), 0),
(104, 50010, 50011, 1, 1, now(), now(), 0)
;

DELETE FROM `appversions`;
INSERT INTO `appversions` (`id`, `application_id`, `version`, `created`, `modified`) VALUES
(100, 1, '0.82', now(), now()),
(101, 1, '0.84', now(), now())
;

DELETE FROM `platforms`;
INSERT INTO `platforms` (`id`, `name`, `shortname`, `icondata`, `icontype`, `created`, `modified`) VALUES 
(1, 114, 115, NULL, '', now(), now()),
(2, 116, 117, NULL, '', now(), now()),
(3, 118, 119, NULL, '', now(), now()),
(4, 120, 121, NULL, '', now(), now());

DELETE FROM `translations`;
INSERT INTO `translations` (`id`, `locale`, `localized_string`, `created`, `modified`) VALUES
(0, 'en-US', 'NONE', now(), now()),
(114, 'en-US', 'ALL', now(), now()),
(115, 'en-US', '', now(), now()),
(116, 'en-US', 'x86', now(), now()),
(117, 'en-US', 'x86', now(), now()),
(118, 'en-US', 'MIPS', now(), now()),
(119, 'en-US', 'mips', now(), now()),
(120, 'en-US', 'ARM', now(), now()),
(121, 'en-US', 'arm', now(), now()),
(50000, 'en-US', 'Sugar Platform', now(), now()),
(50001, 'en-US', 'sugar', now(), now()),
(50002, 'en-US', 'Games', now(), now()),
(50003, 'en-US', 'games', now(), now()),
(50004, 'en-US', 'Drawing', now(), now()),
(50005, 'en-US', 'drawing', now(), now()),
(50006, 'en-US', 'Maths & Science', now(), now()),
(50007, 'en-US', 'maths', now(), now()),
(50008, 'en-US', 'Communication', now(), now()),
(50009, 'en-US', 'communication', now(), now()),
(50010, 'en-US', 'Art', now(), now()),
(50011, 'en-US', 'art', now(), now())
;

DELETE FROM `config`;
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
('validation_disabled', '1'),
('cron_debug_enabled', '0'),
('paypal_disabled', 0);

DELETE FROM `addontypes`;
INSERT INTO `addontypes` (`id`, `created`, `modified`) VALUES
(1, '2006-08-21 23:53:19', '2006-08-21 23:53:19');

DELETE FROM `facebook_data`;
INSERT INTO `facebook_data` (trait) VALUES
('age_under12'), ('age_12to15'), ('age_16to19'), ('age_20to23'),
('age_24to27'), ('age_28to31'), ('age_32to35'), ('age_36to39'),
('age_40to49'), ('age_50to59'), ('age_above60'), ('sex_male'),
('sex_female');


DELETE FROM `translations_seq`;
INSERT INTO `translations_seq` (id) values (1000);

SET FOREIGN_KEY_CHECKS=1;
