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





