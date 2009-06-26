SET FOREIGN_KEY_CHECKS = 0;
rename table tags to categories;
rename table addons_tags to addons_categories;
alter table addons_categories change tag_id category_id int(11) unsigned;
rename table collections_tags to collections_categories;
alter table collections_categories change tag_id category_id int(11) unsigned;
SET FOREIGN_KEY_CHECKS = 1; -- new tables to support tagging feature
--
--
-- Support for tag searching
-- add new column 'tags' to text_search_summary
alter table text_search_summary add column `tags` text NULL ;
-- add new index which contains tags
alter table text_search_summary add FULLTEXT KEY `na_su_de_ta`  (`name`,`summary`,`description`,`tags`)
-- add new index for searching on tags only
alter table text_search_summary add FULLTEXT KEY `tags`  (`tags`)

