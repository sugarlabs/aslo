DROP TABLE IF EXISTS `fizzypop`;
CREATE TABLE `fizzypop` (
  `hash` varchar(255) NOT NULL,
  `serialized` text NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Fix empty application version numbers, they F with sorting.
UPDATE appversions SET version='0.0' WHERE version='';
