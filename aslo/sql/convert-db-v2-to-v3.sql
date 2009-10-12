SET FOREIGN_KEY_CHECKS = 0;

/*** remora.sql ***/

ALTER TABLE addons ADD COLUMN sharecount int(11) unsigned NOT NULL;
ALTER TABLE addons ADD COLUMN nominationdate datetime NOT NULL default '0000-00-00 00:00:00';
ALTER TABLE addons ADD COLUMN dev_agreement tinyint(1) unsigned NOT NULL default '0';
ALTER TABLE addons ADD KEY sharecount (sharecount);

ALTER TABLE users ADD COLUMN bio int(11) UNSIGNED default NULL;
ALTER TABLE users ADD COLUMN display_collections tinyint(1) unsigned NOT NULL default '0';
ALTER TABLE users ADD COLUMN display_collections_fav tinyint(1) unsigned NOT NULL default '0';
ALTER TABLE users ADD COLUMN resetcode varchar(255) NOT NULL default '';
ALTER TABLE users ADD COLUMN resetcode_expires datetime NOT NULL default '0000-00-00 00:00:00';
ALTER TABLE users ADD KEY bio (bio);
ALTER TABLE users ADD CONSTRAINT users_ibfk_1 FOREIGN KEY (bio) REFERENCES translations (id);

ALTER TABLE versions ADD COLUMN license_id int(11) unsigned default NULL;
ALTER TABLE versions ADD KEY license_id (license_id);
ALTER TABLE versions ADD CONSTRAINT versions_ibfk_3 FOREIGN KEY (license_id) REFERENCES licenses (id);

DROP TABLE IF EXISTS `categories`;
rename table tags to categories;
ALTER TABLE categories ADD COLUMN count int(11) NOT NULL DEFAULT '0';

DROP TABLE IF EXISTS `addons_categories`;
rename table addons_tags to addons_categories;
alter table addons_categories drop foreign key addons_categories_ibfk_4;
alter table addons_categories change tag_id category_id int(11) unsigned;
alter table addons_categories add constraint addons_categories_ibfk_4 foreign key (category_id) references `categories` (`id`);

DROP TABLE IF EXISTS `collections_categories`;
rename table collections_tags to collections_categories;
alter table collections_categories drop foreign key collections_categories_ibfk_2;
alter table collections_categories change tag_id category_id int(11) unsigned;
alter table collections_categories add constraint collections_categories_ibfk_2 foreign key (category_id) references `categories` (`id`);

DROP TABLE IF EXISTS `api_auth_tokens`;
CREATE TABLE `api_auth_tokens` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `user_id` int(11) unsigned NOT NULL default '0',
      `token` varchar(64) NOT NULL,
      `user_agent_hash` varchar(64) NOT NULL,
      `user_profile_hash` varchar(64) NOT NULL,
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      PRIMARY KEY  (`id`),
      KEY `user_id` (`user_id`),
      KEY `token` (`token`),
      CONSTRAINT `api_auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `global_stats`;
CREATE TABLE `global_stats` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `name` varchar(255) NOT NULL default '',
      `count` int(10) unsigned NOT NULL default '0',
      `date` date NOT NULL default '0000-00-00',
      UNIQUE KEY `namedate` (`name`, `date`),
      PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `licenses`;
CREATE TABLE `licenses` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `name` int(1) NOT NULL default '-1',
      `text` int(11) unsigned default NULL,
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      PRIMARY KEY (`id`),
      KEY `text` (`text`),
      CONSTRAINT `licenses_ibfk_1` FOREIGN KEY (`text`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `versioncomments`;
CREATE TABLE `versioncomments` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `version_id` int(11) unsigned NOT NULL default '0',
      `user_id` int(11) unsigned NOT NULL default '0',
      `reply_to` int(11) unsigned default NULL,
      `subject` varchar(1000) NOT NULL default '',
      `comment` text NOT NULL,
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      PRIMARY KEY  (`id`),
      KEY `version_id` (`version_id`),
      KEY `reply_to` (`reply_to`),
      KEY `created` (`created`),
      CONSTRAINT `versioncomments_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `versions` (`id`),
      CONSTRAINT `versioncomments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
      CONSTRAINT `versioncomments_ibfk_3` FOREIGN KEY (`reply_to`) REFERENCES `versioncomments` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor comments for version discussion threads';

DROP TABLE IF EXISTS `users_versioncomments`;
CREATE TABLE `users_versioncomments` (
      `user_id` int(11) unsigned NOT NULL,
      `comment_id` int(11) unsigned NOT NULL,
      `subscribed` tinyint(1) unsigned NOT NULL default '1',
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      PRIMARY KEY  (`user_id`,`comment_id`),
      KEY `user_id` (`user_id`),
      KEY `comment_id` (`comment_id`),
      CONSTRAINT `users_versioncomments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `users_versioncomments_ibfk_2` FOREIGN KEY (`comment_id`) REFERENCES `versioncomments` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor subscriptions to version discussion threads';

DROP TABLE IF EXISTS `collections_search_summary`;
CREATE TABLE `collections_search_summary` (
      `id` int(11) NOT NULL,
      `locale` varchar(10) NOT NULL,
      `name` text,
      `description` text,
      FULLTEXT KEY `name` (`name`,`description`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `collections`;
CREATE TABLE `collections` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `uuid` char(36) NOT NULL default '',
      `name` int(11) unsigned NOT NULL,
      `defaultlocale` varchar(10) NOT NULL default 'en-US',
      `collection_type` int(11) unsigned NOT NULL DEFAULT '0',
      `icondata` blob,
      `icontype` varchar(25) NOT NULL default '',
      `nickname` varchar(30) NULL,
      `description` int(11) unsigned NOT NULL,
      `access` tinyint(1) NOT NULL DEFAULT '0',
      `listed` tinyint(1) NOT NULL DEFAULT '1',
      `password` varchar(255) NOT NULL,
      `subscribers` int(11) unsigned NOT NULL DEFAULT '0',
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      `downloads` int(11) unsigned NOT NULL DEFAULT '0',
      `application_id` int(10) unsigned default NULL,
      `weekly_subscribers` int(11) unsigned NOT NULL default '0',
      `monthly_subscribers` int(11) unsigned NOT NULL default '0',
      `addonCount` int(11) unsigned NOT NULL default '0',
      PRIMARY KEY `id` (`id`),
      UNIQUE KEY `uuid` (`uuid`),
      UNIQUE KEY `nickname` (`nickname`),
      KEY (`listed`),
      KEY `application_id` (`application_id`),
      KEY `name` (`name`),
      KEY `description` (`description`),
      CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
      CONSTRAINT `collections_ibfk_2` FOREIGN KEY (`name`) REFERENCES `translations` (`id`),
      CONSTRAINT `collections_ibfk_3` FOREIGN KEY (`description`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `addons_collections`;
CREATE TABLE `addons_collections` (
      `addon_id` int(11) unsigned NOT NULL ,
      `collection_id` int(11) unsigned NOT NULL ,
      `user_id` int(11) unsigned default NULL,
      `added` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      `category` tinyint(4) unsigned default NULL COMMENT 'for interactive collections template',
      `comments` int(11) unsigned default NULL,
      `downloads` int(11) unsigned NOT NULL DEFAULT '0',
      PRIMARY KEY ( `addon_id` , `collection_id` ),
      KEY `addon_id` (`addon_id`),
      KEY `user_id` (`user_id`),
      KEY `collection_id` (`collection_id`),
      KEY `comments` (`comments`),
      CONSTRAINT `addons_collections_ibfk_1` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`),
      CONSTRAINT `addons_collections_ibfk_2` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
      CONSTRAINT `addons_collections_ibfk_3` FOREIGN KEY (`comments`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `collection_features`;
CREATE TABLE `collection_features` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `title` int(11) unsigned NOT NULL default '0',
      `tagline` int(11) unsigned NOT NULL default '0',
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      PRIMARY KEY  (`id`),
      KEY `collection_features_ibfk_1` (`title`),
      KEY `collection_features_ibfk_2` (`tagline`),
      CONSTRAINT `collection_features_ibfk_1` FOREIGN KEY (`title`) REFERENCES `translations` (`id`),
      CONSTRAINT `collection_features_ibfk_2` FOREIGN KEY (`tagline`) REFERENCES `translations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `collection_promos`;
CREATE TABLE `collection_promos` (
      `id` int(11) unsigned NOT NULL auto_increment,
      `collection_id` int(11) unsigned NOT NULL default '0',
      `locale` varchar(10) default NULL,
      `title_tagline` int(11) unsigned NOT NULL default '0',
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      PRIMARY KEY  (`id`),
      KEY `collection_id` (`collection_id`),
      UNIQUE KEY `one_collection_per_tagline_per_locale` (`collection_id`,`locale`,`title_tagline`),
      CONSTRAINT `collection_promos_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `collections_categories`;
CREATE TABLE `collections_categories` (
      `collection_id` int(11) unsigned NOT NULL ,
      `category_id` int(11) unsigned NOT NULL ,
      PRIMARY KEY ( `collection_id` , `category_id` ),
      KEY `collection_id` (`collection_id`),
      KEY `category_id` (`category_id`),
      CONSTRAINT `collections_categories_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `addons` (`id`),
      CONSTRAINT `collections_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

DROP TABLE IF EXISTS `collection_subscriptions`;
CREATE TABLE `collection_subscriptions` (
      `user_id` int(11) unsigned NOT NULL,
      `collection_id` int(11) unsigned NOT NULL,
      `created` datetime NOT NULL default '0000-00-00 00:00:00',
      `modified` datetime NOT NULL default '0000-00-00 00:00:00',
      PRIMARY KEY (`user_id`, `collection_id`),
      CONSTRAINT `collections_subscriptions_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
      CONSTRAINT `collections_subscriptions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `collections_users`;
CREATE TABLE `collections_users` (
      `collection_id` int(11) unsigned NOT NULL default '0',
      `user_id` int(11) unsigned NOT NULL default '0',
      `role` tinyint(2) unsigned NOT NULL default '5',
      PRIMARY KEY  (`collection_id`,`user_id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `collections_users_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
      CONSTRAINT `collections_users_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `schema_version`;
CREATE TABLE `schema_version` (
  `version` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` int(11) unsigned NOT NULL auto_increment,
    `tag_text` varchar(128) NOT NULL,
    `blacklisted` tinyint(1) NOT NULL default 0,
    `created` datetime NOT NULL default '0000-00-00 00:00:00',    
    PRIMARY KEY (`id`),
    UNIQUE KEY `tag_text` (`tag_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tag_stat`;
CREATE TABLE `tag_stat` (
    `tag_id` int(11) unsigned NOT NULL,
    `num_addons` int(11) unsigned NOT NULL default '0',
    `modified` datetime NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY  (`tag_id`),
    CONSTRAINT `tag_stat_ibfk_1` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tag_strength`;
CREATE TABLE `tag_strength` (
    `tag1_id` int(11) unsigned NOT NULL,
    `tag2_id` int(11) unsigned NOT NULL,
    `strength` int(11) unsigned NOT NULL default '0',
    PRIMARY KEY  (`tag1_id`, `tag2_id`),
    CONSTRAINT `tag_strength_ibfk_1` FOREIGN KEY (`tag1_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
    CONSTRAINT `tag_strength_ibfk_2` FOREIGN KEY (`tag2_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users_tags_addons`;
CREATE TABLE `users_tags_addons` (
    `user_id` int(11) unsigned NOT NULL,
    `tag_id` int(11) unsigned NOT NULL,
    `addon_id` int(11) unsigned NOT NULL,
    `created` datetime NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY  (`user_id`,`tag_id`,`addon_id`),
      INDEX (`tag_id`),
    INDEX (`addon_id`),
    CONSTRAINT `users_tags_addons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `users_tags_addons_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE, 
    CONSTRAINT `users_tags_addons_ibfk_3` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*** values ***/

UPDATE addons set sharecount=0;

UPDATE applications set id=1;
UPDATE features set application_id=1;
UPDATE categories set application_id=1;
UPDATE versions_summary set application_id=1;
UPDATE applications_versions set application_id=1;
UPDATE appversions set application_id=1;

INSERT INTO translations VALUES (0, 'en-US', "NULL", NOW(), null);

-- ALTER TABLE versions ADD COLUMN license_id int(11) unsigned default NULL;

/*** 28519-migrate-promobox-to-database.sql ***/

-- Import L10n from .po files
INSERT INTO translations VALUES (172,'he', "מארגן משפחה", NOW(), null);
INSERT INTO translations VALUES (176,'he', "מעגל חברתי", NOW(), null);
INSERT INTO translations VALUES (173,'fa', "مراقب فرزندان و برنامهٔ خود باشید", NOW(), null);
INSERT INTO translations VALUES (172,'fa', "خانواده", NOW(), null);
INSERT INTO translations VALUES (175,'fa', "در وب دربارهٔ همه‌چیز تحقیق کنید", NOW(), null);
INSERT INTO translations VALUES (174,'fa', "مراجع", NOW(), null);
INSERT INTO translations VALUES (177,'fa', "مدیریت شبکه‌های اجتماعی", NOW(), null);
INSERT INTO translations VALUES (176,'fa', "اجتماعی", NOW(), null);
INSERT INTO translations VALUES (179,'fa', "برنامه‌ریزی برای سفرهای کاری و تعطیلات به یاد ماندنی", NOW(), null);
INSERT INTO translations VALUES (178,'fa', "مسافرت", NOW(), null);
INSERT INTO translations VALUES (171,'fa', "بهترین وب‌گاه را بسازید", NOW(), null);
INSERT INTO translations VALUES (170,'fa', "توسعهٔ وب", NOW(), null);
INSERT INTO translations VALUES (173,'it', "Tieni d'occhio i tuoi bambini e la tua agenda.", NOW(), null);
INSERT INTO translations VALUES (172,'it', "Famiglia", NOW(), null);
INSERT INTO translations VALUES (175,'it', "Cerca informazioni online.", NOW(), null);
INSERT INTO translations VALUES (174,'it', "Informazioni", NOW(), null);
INSERT INTO translations VALUES (177,'it', "Gestisci i tuoi social network.", NOW(), null);
INSERT INTO translations VALUES (176,'it', "Social", NOW(), null);
INSERT INTO translations VALUES (179,'it', "Progetta viaggi di lavoro e vacanze indimenticabili.", NOW(), null);
INSERT INTO translations VALUES (178,'it', "Viaggi", NOW(), null);
INSERT INTO translations VALUES (171,'it', "Realizza siti web perfetti.", NOW(), null);
INSERT INTO translations VALUES (170,'it', "Sviluppo web", NOW(), null);
INSERT INTO translations VALUES (173,'pt-PT', "Os extras podem vigiar os seus filhos e o seu calendário.", NOW(), null);
INSERT INTO translations VALUES (172,'pt-PT', "Organizador Familiar", NOW(), null);
INSERT INTO translations VALUES (175,'pt-PT', "Os extras podem fazer uma melhor pesquisa quando ligados.", NOW(), null);
INSERT INTO translations VALUES (174,'pt-PT', "Balcão de referência", NOW(), null);
INSERT INTO translations VALUES (177,'pt-PT', "Os extras podem gerir a sua rede social.", NOW(), null);
INSERT INTO translations VALUES (176,'pt-PT', "Circuito social", NOW(), null);
INSERT INTO translations VALUES (179,'pt-PT', "Os extras podem torná-lo num agente de viagens.", NOW(), null);
INSERT INTO translations VALUES (178,'pt-PT', "O pacote do viajante", NOW(), null);
INSERT INTO translations VALUES (171,'pt-PT', "Os extras tornam mais fácil a construção do sítio web perfeito.", NOW(), null);
INSERT INTO translations VALUES (170,'pt-PT', "Caixa de ferramenta do desenvolvedor web", NOW(), null);
INSERT INTO translations VALUES (173,'de', "Achten Sie auf Ihre Kinder und auf Ihren Kalender", NOW(), null);
INSERT INTO translations VALUES (172,'de', "Familie", NOW(), null);
INSERT INTO translations VALUES (175,'de', "Recherchieren Sie online, was auch immer Sie möchten", NOW(), null);
INSERT INTO translations VALUES (174,'de', "Recherchieren", NOW(), null);
INSERT INTO translations VALUES (177,'de', "Verwalten Sie Ihr Soziales Netzwerk", NOW(), null);
INSERT INTO translations VALUES (176,'de', "Gesellschaft", NOW(), null);
INSERT INTO translations VALUES (179,'de', "Planen Sie Geschäftsreisen und unvergessliche Urlaube", NOW(), null);
INSERT INTO translations VALUES (178,'de', "Reisen", NOW(), null);
INSERT INTO translations VALUES (171,'de', "Bauen Sie eine perfekte Webseite", NOW(), null);
INSERT INTO translations VALUES (170,'de', "Web-Entwicklung", NOW(), null);
INSERT INTO translations VALUES (173,'sv-SE', "Tillägg kan hålla ett öga på dina barn och din kalender.", NOW(), null);
INSERT INTO translations VALUES (172,'sv-SE', "Familjeplanering", NOW(), null);
INSERT INTO translations VALUES (175,'sv-SE', "Tillägg kan hjälpa dig göra bättre nätsökningar.", NOW(), null);
INSERT INTO translations VALUES (174,'sv-SE', "Referensdisken", NOW(), null);
INSERT INTO translations VALUES (177,'sv-SE', "Tillägg som hjälper dig hantera sociala nätverk.", NOW(), null);
INSERT INTO translations VALUES (176,'sv-SE', "Sällskapslivet", NOW(), null);
INSERT INTO translations VALUES (179,'sv-SE', "Tilläggen som kan förvandla dig till en reseagent.", NOW(), null);
INSERT INTO translations VALUES (178,'sv-SE', "Resepaketet", NOW(), null);
INSERT INTO translations VALUES (171,'sv-SE', "Tillägg gör det enklare att bygga den perfekta webbsidan.", NOW(), null);
INSERT INTO translations VALUES (170,'sv-SE', "Webbutvecklarens verktygslåda", NOW(), null);
INSERT INTO translations VALUES (173,'en-US', "Keep an eye on your kids and your calendar", NOW(), null);
INSERT INTO translations VALUES (172,'en-US', "Family", NOW(), null);
INSERT INTO translations VALUES (175,'en-US', "Research anything online", NOW(), null);
INSERT INTO translations VALUES (174,'en-US', "Reference", NOW(), null);
INSERT INTO translations VALUES (177,'en-US', "Manage your social network", NOW(), null);
INSERT INTO translations VALUES (176,'en-US', "Social", NOW(), null);
INSERT INTO translations VALUES (179,'en-US', "Plan business trips and unforgettable vacations", NOW(), null);
INSERT INTO translations VALUES (178,'en-US', "Travel", NOW(), null);
INSERT INTO translations VALUES (171,'en-US', "Build the perfect website", NOW(), null);
INSERT INTO translations VALUES (170,'en-US', "Web Development", NOW(), null);
INSERT INTO translations VALUES (173,'es-ES', "Los complementos pueden tener un ojo en tus hijos y tu agenda.", NOW(), null);
INSERT INTO translations VALUES (172,'es-ES', "Organizador familiar", NOW(), null);
INSERT INTO translations VALUES (175,'es-ES', "Los complementos pueden hacer la mejor búsqueda en la web.", NOW(), null);
INSERT INTO translations VALUES (174,'es-ES', "Referencia de escritorio", NOW(), null);
INSERT INTO translations VALUES (177,'es-ES', "Los complementos pueden administrar tus redes sociales.", NOW(), null);
INSERT INTO translations VALUES (176,'es-ES', "Conexiones sociales", NOW(), null);
INSERT INTO translations VALUES (179,'es-ES', "Los complementos pueden convertirte en un agente de viajes.", NOW(), null);
INSERT INTO translations VALUES (178,'es-ES', "El pack de los viajeros", NOW(), null);
INSERT INTO translations VALUES (171,'es-ES', "Los complementos te hacen más fácil construir la web perfecta.", NOW(), null);
INSERT INTO translations VALUES (170,'es-ES', "Conjunto de herramientas de desarrolladores web", NOW(), null);
INSERT INTO translations VALUES (173,'pl', "Miej na oku swoje dzieci i kalendarz", NOW(), null);
INSERT INTO translations VALUES (172,'pl', "Rodzina", NOW(), null);
INSERT INTO translations VALUES (175,'pl', "Eksploruj Internet", NOW(), null);
INSERT INTO translations VALUES (174,'pl', "Podręczne", NOW(), null);
INSERT INTO translations VALUES (177,'pl', "Zarządzaj swoimi sieciami społecznościowymi", NOW(), null);
INSERT INTO translations VALUES (176,'pl', "Społeczności", NOW(), null);
INSERT INTO translations VALUES (179,'pl', "Zaplanuj podróż służbową i niezapomniane wakacje", NOW(), null);
INSERT INTO translations VALUES (178,'pl', "Podróże", NOW(), null);
INSERT INTO translations VALUES (171,'pl', "Zbuduj doskonałą stronę internetową", NOW(), null);
INSERT INTO translations VALUES (170,'pl', "Webmaster", NOW(), null);
INSERT INTO translations VALUES (173,'el', "Πρόσθετα που μπορούν να προσέχουν τις δραστηριότητες των παιδιών σας και το ημερολόγιο σας.", NOW(), null);
INSERT INTO translations VALUES (172,'el', "Οικογενειακά", NOW(), null);
INSERT INTO translations VALUES (175,'el', "Πρόσθετα που μπορούν να βελτιστοποιήσουν τις αναζητήσεις στο διαδίκτυο.", NOW(), null);
INSERT INTO translations VALUES (174,'el', "Πληροφορίες", NOW(), null);
INSERT INTO translations VALUES (177,'el', "Πρόσθετα που μπορούν να διαχειριστούν τα κοινωνικά σας δίκτυα.", NOW(), null);
INSERT INTO translations VALUES (176,'el', "Κοινωνικά", NOW(), null);
INSERT INTO translations VALUES (179,'el', "Πρόσθετα που μπορούν να σας μετατρέψουν σε ταξιδιωτικό πράκτορα.", NOW(), null);
INSERT INTO translations VALUES (178,'el', "Ταξιδιωτικά", NOW(), null);
INSERT INTO translations VALUES (171,'el', "Πρόσθετα που θα σας διευκολύνουν να φτιάξετε την τέλεια ιστοσελίδα.", NOW(), null);
INSERT INTO translations VALUES (170,'el', "Δημιουργικά", NOW(), null);
INSERT INTO translations VALUES (173,'sk', "Dozerajte na vaše deti a kalendár.", NOW(), null);
INSERT INTO translations VALUES (172,'sk', "Rodina", NOW(), null);
INSERT INTO translations VALUES (175,'sk', "Skúmajte hocičo online.", NOW(), null);
INSERT INTO translations VALUES (174,'sk', "Referencie", NOW(), null);
INSERT INTO translations VALUES (177,'sk', "Spravujte vašu sociálnu sieť.", NOW(), null);
INSERT INTO translations VALUES (176,'sk', "Sociálne", NOW(), null);
INSERT INTO translations VALUES (179,'sk', "Naplánujte si obchodné cesty a nezabudnuteľné dovolenky.", NOW(), null);
INSERT INTO translations VALUES (178,'sk', "Cestovanie", NOW(), null);
INSERT INTO translations VALUES (171,'sk', "Vytvorte perfektnú stránku.", NOW(), null);
INSERT INTO translations VALUES (170,'sk', "Vývoj webu", NOW(), null);
INSERT INTO translations VALUES (173,'zh-TW', "關照您的小孩和您的行事曆", NOW(), null);
INSERT INTO translations VALUES (172,'zh-TW', "家庭", NOW(), null);
INSERT INTO translations VALUES (175,'zh-TW', "在線上研究任何東西", NOW(), null);
INSERT INTO translations VALUES (174,'zh-TW', "參考資源", NOW(), null);
INSERT INTO translations VALUES (177,'zh-TW', "管理您的社交網路", NOW(), null);
INSERT INTO translations VALUES (176,'zh-TW', "社交", NOW(), null);
INSERT INTO translations VALUES (179,'zh-TW', "計劃商業旅行和難忘假期", NOW(), null);
INSERT INTO translations VALUES (178,'zh-TW', "旅遊", NOW(), null);
INSERT INTO translations VALUES (171,'zh-TW', "建立完美的網站", NOW(), null);
INSERT INTO translations VALUES (170,'zh-TW', "網頁開發", NOW(), null);
INSERT INTO translations VALUES (173,'ru', "Дополнения, которые могут присматривать за вашими детьми и вашим расписанием.", NOW(), null);
INSERT INTO translations VALUES (172,'ru', "Семейная жизнь", NOW(), null);
INSERT INTO translations VALUES (175,'ru', "Дополнения, которые помогут вам в онлайновых исследованиях.", NOW(), null);
INSERT INTO translations VALUES (174,'ru', "Справочная", NOW(), null);
INSERT INTO translations VALUES (177,'ru', "Дополнения для управления вашими социальными сетями.", NOW(), null);
INSERT INTO translations VALUES (176,'ru', "Социальный круг", NOW(), null);
INSERT INTO translations VALUES (179,'ru', "Дополнения, которые помогут вам в ваших путешествиях.", NOW(), null);
INSERT INTO translations VALUES (178,'ru', "Набор путешественника", NOW(), null);
INSERT INTO translations VALUES (171,'ru', "Дополнения, которые облегчат вам создание идеальных веб-сайтов.", NOW(), null);
INSERT INTO translations VALUES (170,'ru', "Набор инструментов веб-разработчика", NOW(), null);
INSERT INTO translations VALUES (173,'cs', "Doplňky mohou dávat pozor na vaše děti i kalendář.", NOW(), null);
INSERT INTO translations VALUES (172,'cs', "Rodina", NOW(), null);
INSERT INTO translations VALUES (175,'cs', "Doplňky mohou usnadnit vaše vyhledávání.", NOW(), null);
INSERT INTO translations VALUES (174,'cs', "Reference", NOW(), null);
INSERT INTO translations VALUES (177,'cs', "Doplňky mohou spravovat vaši sociální síť.", NOW(), null);
INSERT INTO translations VALUES (176,'cs', "Sociální sítě", NOW(), null);
INSERT INTO translations VALUES (179,'cs', "Doplňky se mohou stát vašim cestovním agentem.", NOW(), null);
INSERT INTO translations VALUES (178,'cs', "Cestování", NOW(), null);
INSERT INTO translations VALUES (171,'cs', "Doplňky usnadňují vytváření perfektní webové stránky.", NOW(), null);
INSERT INTO translations VALUES (170,'cs', "Vývoj pro web", NOW(), null);
INSERT INTO translations VALUES (173,'sq', "Mbani nën kontroll fëmijët dhe kalendarin tuaj", NOW(), null);
INSERT INTO translations VALUES (172,'sq', "Familje", NOW(), null);
INSERT INTO translations VALUES (175,'sq', "Kërkoni \"online\" për gjithçka", NOW(), null);
INSERT INTO translations VALUES (174,'sq', "Referencë", NOW(), null);
INSERT INTO translations VALUES (177,'sq', "Administroni rrjetin tuaj shoqëror", NOW(), null);
INSERT INTO translations VALUES (176,'sq', "Shoqërore", NOW(), null);
INSERT INTO translations VALUES (179,'sq', "Planifikoni udhëtime pune dhe pushime të paharrueshme", NOW(), null);
INSERT INTO translations VALUES (178,'sq', "Udhëtime", NOW(), null);
INSERT INTO translations VALUES (171,'sq', "Krijoni \"site\"-in e përsosur web", NOW(), null);
INSERT INTO translations VALUES (170,'sq', "Zhvillim Web", NOW(), null);
INSERT INTO translations VALUES (172,'ro', "Familie", NOW(), null);
INSERT INTO translations VALUES (174,'ro', "Referință", NOW(), null);
INSERT INTO translations VALUES (178,'ro', "Călătorie", NOW(), null);
INSERT INTO translations VALUES (170,'ro', "Dezvoltare web", NOW(), null);
INSERT INTO translations VALUES (173,'vi', "Trông chừng con cái và bộ lịch của bạn", NOW(), null);
INSERT INTO translations VALUES (172,'vi', "Gia đình", NOW(), null);
INSERT INTO translations VALUES (175,'vi', "Thực hiện nghiên cứu trực tuyến", NOW(), null);
INSERT INTO translations VALUES (174,'vi', "Tham khảo", NOW(), null);
INSERT INTO translations VALUES (177,'vi', "Quản lí mạng xã hội của bạn", NOW(), null);
INSERT INTO translations VALUES (176,'vi', "Mạng xã hội", NOW(), null);
INSERT INTO translations VALUES (179,'vi', "Lập kế hoạch cho những chuyến du ngoạn và kì nghỉ đáng nhớ", NOW(), null);
INSERT INTO translations VALUES (178,'vi', "Du lịch", NOW(), null);
INSERT INTO translations VALUES (171,'vi', "Xây dựng trang web hoàn hảo", NOW(), null);
INSERT INTO translations VALUES (170,'vi', "Phát triển Web", NOW(), null);
INSERT INTO translations VALUES (173,'ja', "あなたの子どもやカレンダーを見守るアドオン", NOW(), null);
INSERT INTO translations VALUES (172,'ja', "家族のスケジュール管理", NOW(), null);
INSERT INTO translations VALUES (175,'ja', "オンラインでの調査を支援するアドオン", NOW(), null);
INSERT INTO translations VALUES (174,'ja', "リファレンス・デスク", NOW(), null);
INSERT INTO translations VALUES (177,'ja', "自分のソーシャルネットワークを管理するアドオン", NOW(), null);
INSERT INTO translations VALUES (176,'ja', "ソーシャル・サーキット", NOW(), null);
INSERT INTO translations VALUES (179,'ja', "あなたを旅行代理店に変身させるアドオン", NOW(), null);
INSERT INTO translations VALUES (178,'ja', "旅行者向けパック", NOW(), null);
INSERT INTO translations VALUES (171,'ja', "完璧な Web サイトを簡単に作るためのアドオン", NOW(), null);
INSERT INTO translations VALUES (170,'ja', "Web 開発者向けツール", NOW(), null);
INSERT INTO translations VALUES (173,'ca', "Complements que poden vigilar els vostres fills i controlar el vostre calendari.", NOW(), null);
INSERT INTO translations VALUES (172,'ca', "Organitzador familiar", NOW(), null);
INSERT INTO translations VALUES (175,'ca', "Complements que poden millorar la cerca en línia.", NOW(), null);
INSERT INTO translations VALUES (174,'ca', "Taula de referències", NOW(), null);
INSERT INTO translations VALUES (177,'ca', "Complements per a gestionar les vostres xarxes socials.", NOW(), null);
INSERT INTO translations VALUES (176,'ca', "Circuït social", NOW(), null);
INSERT INTO translations VALUES (179,'ca', "Complements que us poden convertir en una agència de viatges.", NOW(), null);
INSERT INTO translations VALUES (178,'ca', "Agència de viatges", NOW(), null);
INSERT INTO translations VALUES (171,'ca', "Complements per a crear fàcilment el perfecte lloc web.", NOW(), null);
INSERT INTO translations VALUES (170,'ca', "Barra d'eines del desenvolupador web", NOW(), null);
INSERT INTO translations VALUES (173,'nl', "Houd uw kinderen en uw agenda in de gaten.", NOW(), null);
INSERT INTO translations VALUES (172,'nl', "Familie", NOW(), null);
INSERT INTO translations VALUES (175,'nl', "Onderzoek alles online.", NOW(), null);
INSERT INTO translations VALUES (174,'nl', "Referentie", NOW(), null);
INSERT INTO translations VALUES (177,'nl', "Beheer uw sociale netwerk.", NOW(), null);
INSERT INTO translations VALUES (176,'nl', "Sociaal", NOW(), null);
INSERT INTO translations VALUES (179,'nl', "Plan zakenreizen en onvergetelijke vakanties.", NOW(), null);
INSERT INTO translations VALUES (178,'nl', "Reizen", NOW(), null);
INSERT INTO translations VALUES (171,'nl', "Bouw de perfecte website.", NOW(), null);
INSERT INTO translations VALUES (170,'nl', "Webontwikkeling", NOW(), null);

-- Make our features
INSERT INTO collection_features VALUES
(1, 170, 171, NOW(), '0000-00-00 00:00:00'),
(2, 172, 173, NOW(), '0000-00-00 00:00:00'),
(3, 174, 175, NOW(), '0000-00-00 00:00:00'),
(4, 176, 177, NOW(), '0000-00-00 00:00:00'),
(5, 178, 179, NOW(), '0000-00-00 00:00:00');

-- Reorganize tag collections because they were zero indexed and now they aren't. g d
update collection_promos set title_tagline=50 where title_tagline=4;
update collection_promos set title_tagline=4 where title_tagline=0;
update collection_promos set title_tagline=5 where title_tagline=2;
update collection_promos set title_tagline=2 where title_tagline=1;
update collection_promos set title_tagline=1 where title_tagline=50;

-- This is a foreign key now
ALTER TABLE collection_promos change column title_tagline collection_feature_id
int(11) unsigned not null;
ALTER TABLE collection_promos ADD CONSTRAINT `collection_promos_ibfk_2` FOREIGN KEY
(`collection_feature_id`) REFERENCES `collection_features` (`id`);

/*** 28602-webmocha-merge.sql ***/

-- You're going to need to have SUPER privileges to add the triggers!

-- Support for tag searching
-- add new column 'tags' to text_search_summary
alter table text_search_summary add column `tags` text NULL;
-- add new index which contains tags
alter table text_search_summary add FULLTEXT KEY `na_su_de_ta` (`name`,`summary`,`description`,`tags`);
-- add new index for searching on tags only
alter table text_search_summary add FULLTEXT KEY `tags`  (`tags`);

--
-- new tables to support tagging feature
--
--
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` int(11) unsigned NOT NULL auto_increment,
    `tag_text` varchar(128) NOT NULL,
    `blacklisted` tinyint(1) NOT NULL default 0,
    `created` datetime NOT NULL default '0000-00-00 00:00:00',    
    PRIMARY KEY (`id`),
    UNIQUE KEY `tag_text` (`tag_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users_tags_addons`;
CREATE TABLE `users_tags_addons` (
    `user_id` int(11) unsigned NOT NULL,
    `tag_id` int(11) unsigned NOT NULL,
    `addon_id` int(11) unsigned NOT NULL,
    `created` datetime NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY  (`user_id`,`tag_id`,`addon_id`),
      INDEX (`tag_id`),
    INDEX (`addon_id`),
    CONSTRAINT `users_tags_addons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `users_tags_addons_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE, 
    CONSTRAINT `users_tags_addons_ibfk_3` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tag_stat`;
CREATE TABLE `tag_stat` (
    `tag_id` int(11) unsigned NOT NULL,
    `num_addons` int(11) unsigned NOT NULL default '0',
    `modified` datetime NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY  (`tag_id`),
    CONSTRAINT `tag_stat_ibfk_1` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tag_strength`;
CREATE TABLE `tag_strength` (
    `tag1_id` int(11) unsigned NOT NULL,
    `tag2_id` int(11) unsigned NOT NULL,
    `strength` int(11) unsigned NOT NULL default '0',
    PRIMARY KEY  (`tag1_id`, `tag2_id`),
    CONSTRAINT `tag_strength_ibfk_1` FOREIGN KEY (`tag1_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
    CONSTRAINT `tag_strength_ibfk_2` FOREIGN KEY (`tag2_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


