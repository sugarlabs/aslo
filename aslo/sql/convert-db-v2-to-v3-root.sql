CREATE TRIGGER collections_update_addon_count_insert
  AFTER INSERT ON addons_collections
  FOR EACH ROW
    UPDATE collections AS c
    SET c.addonCount = c.addonCount + 1
    WHERE c.id=NEW.collection_id;

CREATE TRIGGER collections_update_addon_count_delete
  AFTER DELETE ON addons_collections
  FOR EACH ROW
    UPDATE collections AS c
    SET c.addonCount = c.addonCount - 1
    WHERE c.id=OLD.collection_id;

CREATE TRIGGER collections_update_subscriber_count_insert
  AFTER INSERT ON collection_subscriptions
  FOR EACH ROW
    UPDATE collections AS c
    SET c.subscribers = c.subscribers + 1
    WHERE c.id=NEW.collection_id;

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

