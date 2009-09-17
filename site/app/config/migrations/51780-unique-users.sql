-- Add a flag for users that are "deleted" (anonymized)
ALTER TABLE users ADD COLUMN deleted tinyint(1) DEFAULT 0 AFTER notifyevents;

-- Use the flag for all currently deleted users
UPDATE users SET nickname='', deleted=1 where nickname='Deleted User';

-- Allow null nicknames
ALTER TABLE users MODIFY `nickname` varchar(255) default null;

-- Currently empty nicknames should be null
UPDATE users SET nickname=null WHERE nickname="";

-- Enforce unique nicknames
ALTER TABLE users DROP KEY nickname, ADD UNIQUE (nickname);
