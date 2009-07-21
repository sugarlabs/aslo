ALTER TABLE addons 
ADD COLUMN `average_daily_downloads` int(11) unsigned not null default '0' AFTER `totaldownloads`,
ADD COLUMN `average_daily_users` int(11) unsigned not null default '0' AFTER `average_daily_downloads`;


