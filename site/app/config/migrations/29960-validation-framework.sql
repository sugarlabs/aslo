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
