CREATE TABLE `test_results_cache` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `date` datetime NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text default NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
