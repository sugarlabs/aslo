-- You need SUPER privileges for these triggers!  Check it.

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
