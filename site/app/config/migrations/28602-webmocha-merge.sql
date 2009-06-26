-- You're going to need to have SUPER privileges to add the triggers!

SET FOREIGN_KEY_CHECKS = 0;
rename table tags to categories;

rename table addons_tags to addons_categories;
alter table addons_categories drop foreign key addons_categories_ibfk_4;
alter table addons_categories change tag_id category_id int(11) unsigned;
alter table addons_categories add constraint addons_categories_ibfk_4 foreign key (category_id) references `categories` (`id`);

rename table collections_tags to collections_categories;
alter table collections_categories drop foreign key collections_categories_ibfk_2;
alter table collections_categories change tag_id category_id int(11) unsigned;
alter table collections_categories add constraint collections_categories_ibfk_2 foreign key (category_id) references `categories` (`id`);

SET FOREIGN_KEY_CHECKS = 1;

-- Support for tag searching
-- add new column 'tags' to text_search_summary
alter table text_search_summary add column `tags` text NULL;
-- add new index which contains tags
alter table text_search_summary add FULLTEXT KEY `na_su_de_ta` (`name`,`summary`,`description`,`tags`);
-- add new index for searching on tags only
alter table text_search_summary add FULLTEXT KEY `tags`  (`tags`);

--
-- new tables to support tagging feature
--
--
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` int(11) unsigned NOT NULL auto_increment,
    `tag_text` varchar(128) NOT NULL,
    `blacklisted` tinyint(1) NOT NULL default 0,
    `created` datetime NOT NULL default '0000-00-00 00:00:00',    
    PRIMARY KEY (`id`),
    UNIQUE KEY `tag_text` (`tag_text`)
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


-- triggers
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

