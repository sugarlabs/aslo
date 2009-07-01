alter table users 
  add column `location` varchar(255) default '' not null, 
  add column `occupation` varchar(255) default '' not null, 
  add column `picture_data` mediumblob default null,
  add column `picture_type` varchar(25) default '' not null;
