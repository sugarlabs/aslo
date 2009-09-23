-- This has nothing to do with this revision, but we had to get it in somewhere.
-- Bug 518158 explains.
ALTER TABLE groups_users DROP FOREIGN KEY groups_users_ibfk_4;

ALTER TABLE groups_users ADD FOREIGN KEY groups_users_ibfk_4 (user_id) REFERENCES `users` (id) ON DELETE CASCADE ON UPDATE CASCADE;

UPDATE users SET id=((SELECT blah FROM (SELECT max(id) as blah from users) as blah2)+1) WHERE id=1;

INSERT INTO users (id,email,nickname,deleted,notes) VALUES (1,'nobody@mozilla.org','nobody',1,'Just a placeholder.  See bug 518158');
