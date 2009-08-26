ALTER TABLE `stats_contributions`
    ADD COLUMN `transaction_id` varchar(255) default NULL,  
    ADD COLUMN `final_amount` varchar(10) default '0.00',
    ADD COLUMN `post_data` text default NULL;