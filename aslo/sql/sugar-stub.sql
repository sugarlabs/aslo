SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE versions
  ADD COLUMN `in_reply_to` varchar(255) NOT NULL default '',
  ADD COLUMN `uploader` int(11) unsigned NOT NULL default '0',
  ADD CONSTRAINT `versions_ibfk_uploader` FOREIGN KEY (`uploader`) REFERENCES `users` (`id`);

SET FOREIGN_KEY_CHECKS=1;
