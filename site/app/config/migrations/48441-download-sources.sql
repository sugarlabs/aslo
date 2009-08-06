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

