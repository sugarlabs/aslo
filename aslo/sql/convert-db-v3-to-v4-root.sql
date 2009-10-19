drop TRIGGER if exists collections_update_addon_count_insert;
CREATE TRIGGER collections_update_addon_count_insert
  AFTER INSERT ON addons_collections
  FOR EACH ROW
    UPDATE collections AS c
    SET c.addonCount = c.addonCount + 1
    WHERE c.id=NEW.collection_id;

drop TRIGGER if exists collections_update_addon_count_delete;
CREATE TRIGGER collections_update_addon_count_delete
  AFTER DELETE ON addons_collections
  FOR EACH ROW
    UPDATE collections AS c
    SET c.addonCount = c.addonCount - 1
    WHERE c.id=OLD.collection_id;

drop TRIGGER if exists collections_update_subscriber_count_insert;
CREATE TRIGGER collections_update_subscriber_count_insert
  AFTER INSERT ON collection_subscriptions
  FOR EACH ROW
    UPDATE collections AS c
    SET c.subscribers = c.subscribers + 1
    WHERE c.id=NEW.collection_id;

drop TRIGGER if exists collections_update_subscriber_count_delete;
CREATE TRIGGER collections_update_subscriber_count_delete
  AFTER DELETE ON collection_subscriptions
  FOR EACH ROW
    UPDATE collections AS c
    SET c.subscribers = c.subscribers - 1
    WHERE c.id=OLD.collection_id;

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


-- Collection voting triggers
DELIMITER |
DROP TRIGGER IF EXISTS collection_vote_insert|
CREATE TRIGGER collection_vote_insert
  AFTER INSERT ON collections_votes
  FOR EACH ROW
  CASE NEW.vote
  WHEN 1 THEN
    UPDATE collections SET upvotes=(upvotes + 1) WHERE id=NEW.collection_id;
  WHEN -1 THEN
    UPDATE collections SET downvotes=(downvotes + 1) WHERE id=NEW.collection_id;
  END CASE;
|

DROP TRIGGER IF EXISTS collection_vote_delete|
CREATE TRIGGER collection_vote_delete
  AFTER DELETE ON collections_votes
  FOR EACH ROW
  CASE OLD.vote
  WHEN 1 THEN
    UPDATE collections SET upvotes=(upvotes - 1) WHERE id=OLD.collection_id;
  WHEN -1 THEN
    UPDATE collections SET downvotes=(downvotes - 1) WHERE id=OLD.collection_id;
  END CASE;
|
DELIMITER ;
