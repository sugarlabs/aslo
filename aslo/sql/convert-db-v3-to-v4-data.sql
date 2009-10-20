UPDATE collections AS c SET c.addonCount = (select count(*) from addons_collections as NEW WHERE c.id=NEW.collection_id);
UPDATE collections AS c SET c.subscribers = (select count(*) from collection_subscriptions as NEW WHERE c.id=NEW.collection_id);

delete from tag_stat;
insert ignore INTO tag_stat(tag_id, num_addons, modified) 
select NEW.tag_id, 0, now() from users_tags_addons as NEW;
UPDATE tag_stat set num_addons = (select count(*) from users_tags_addons as NEW WHERE tag_stat.tag_id = NEW.tag_id);

UPDATE collections SET upvotes = (select count(*) from collections_votes as NEW WHERE collections.id=NEW.collection_id and NEW.vote = 1);
UPDATE collections SET downvotes = (select count(*) from collections_votes as NEW WHERE collections.id=NEW.collection_id and NEW.vote = -1);

INSERT INTO `config` ( `key` , `value` )
VALUES
('api_disabled', '0'),
('validation_disabled', '1'),
('cron_debug_enabled', '0'),
('paypal_disabled', 0);
