SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `addontypes`;
CREATE TABLE `addontypes` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `modified` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `addontypes` (`id`, `created`, `modified`) VALUES 
(1, now(), now()),
(2, now(), now());
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE addons ADD (
    bayesianrating float NOT NULL default '0',
    KEY bayesianrating (bayesianrating)
);

ALTER TABLE users ADD (
    notifybroadcast tinyint(1) unsigned NOT NULL default '0',
    KEY notifybroadcast (notifybroadcast)
);

ALTER TABLE approvals ADD (
    `reply_to` int(11) unsigned default NULL,
    KEY `reply_to` (`reply_to`),
    CONSTRAINT `approvals_ibfk_4` FOREIGN KEY (`reply_to`) REFERENCES `approvals` (`id`) ON DELETE CASCADE
);

UPDATE addons set addontype_id=1;
UPDATE tags set addontype_id=1;
UPDATE collections set addontype_id=1;

UPDATE features set application_id=19;
UPDATE tags set application_id=19;
UPDATE versions_summary set application_id=19;
UPDATE applications_versions set application_id=19;
UPDATE appversions set application_id=19;

UPDATE versions_summary set min=101 WHERE min is NULL;
UPDATE versions_summary set max=101 WHERE max is NULL;

DELETE FROM blapps;
DELETE FROM blitems;
DELETE FROM blplugins;

DROP TABLE IF EXISTS `editor_subscriptions`;
CREATE TABLE `editor_subscriptions` (
  `user_id` int(11) unsigned NOT NULL,
  `addon_id` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`user_id`,`addon_id`),
  KEY `user_id` (`user_id`),
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `editor_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `editor_subscriptions_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Editor subscriptions for add-on updates';

UPDATE addons SET guid='org.laptop.WebActivity'                     WHERE id=4024;
UPDATE addons SET guid='org.laptop.community.TypingTurtle'          WHERE id=4026;
UPDATE addons SET guid='org.laptop.TurtleArtActivity'               WHERE id=4027;
UPDATE addons SET guid='org.laptop.sugar.ReadActivity'              WHERE id=4028;
UPDATE addons SET guid='org.sugarlabs.IRC'                          WHERE id=4029;
UPDATE addons SET guid='org.vpri.EtoysActivity'                     WHERE id=4030;
UPDATE addons SET guid='com.martindengler.WirelessGraph'            WHERE id=4031;
UPDATE addons SET guid='org.laptop.ImageViewerActivity'             WHERE id=4032;
UPDATE addons SET guid='vu.lux.olpc.HablarConSara'                  WHERE id=4033;
UPDATE addons SET guid='com.garycmartin.Moon'                       WHERE id=4034;
UPDATE addons SET guid='org.laptop.sugar.ReadEtextsActivity'        WHERE id=4035;
UPDATE addons SET guid='com.ywwg.CartoonBuilderActivity'            WHERE id=4037;
UPDATE addons SET guid='vu.lux.olpc.Speak'                          WHERE id=4038;
UPDATE addons SET guid='org.laptop.ViewSlidesActivity'              WHERE id=4039;
UPDATE addons SET guid='org.laptop.community.Finance'               WHERE id=4040;
UPDATE addons SET guid='org.laptop.Pippy'                           WHERE id=4041;
UPDATE addons SET guid='org.sugarlabs.InfoSlicer'                   WHERE id=4042;
UPDATE addons SET guid='org.laptop.Terminal'                        WHERE id=4043;
UPDATE addons SET guid='org.worldwideworkshop.olpc.FlipSticks'      WHERE id=4044;
UPDATE addons SET guid='org.laptop.sugar.Jukebox'                   WHERE id=4045;
UPDATE addons SET guid='org.worldwideworkshop.olpc.JigsawPuzzle'    WHERE id=4046;
UPDATE addons SET guid='org.worldwideworkshop.olpc.SliderPuzzle'    WHERE id=4047;
UPDATE addons SET guid='org.sugarlabs.surf'                         WHERE id=4048; -- tmp
UPDATE addons SET guid='org.laptop.community.Colors'                WHERE id=4050;
UPDATE addons SET guid='org.laptop.HelpActivity'                    WHERE id=4051;
UPDATE addons SET guid='org.squeak.FreeCell'                        WHERE id=4054;
UPDATE addons SET guid='bounce'                                     WHERE id=4055;
UPDATE addons SET guid='org.laptop.Log'                             WHERE id=4056;
UPDATE addons SET guid='org.sugarlabs.ajedrez'                      WHERE id=4057; -- tmp
UPDATE addons SET guid='org.laptop.Develop'                         WHERE id=4058;
UPDATE addons SET guid='org.laptop.TamTamEdit'                      WHERE id=4059;
UPDATE addons SET guid='org.laptop.TamTamJam'                       WHERE id=4060;
UPDATE addons SET guid='org.laptop.TamTamMini'                      WHERE id=4061;
UPDATE addons SET guid='org.laptop.TamTamSynthLab'                  WHERE id=4062;
UPDATE addons SET guid='org.laptop.Memorize'                        WHERE id=4063;
UPDATE addons SET guid='org.worldwideworkshop.JokeMachineActivity'  WHERE id=4064;
UPDATE addons SET guid='org.laptop.Chat'                            WHERE id=4069;
UPDATE addons SET guid='vu.lux.olpc.Maze'                           WHERE id=4071;
UPDATE addons SET guid='vu.lux.olpc.Frotz'                          WHERE id=4072;
UPDATE addons SET guid='org.worldwideworkshop.olpc.storybuilder'    WHERE id=4073;
UPDATE addons SET guid='org.worldwideworkshop.PollBuilder'          WHERE id=4074;

DELETE FROM addons_tags where addon_id=4025;
DELETE FROM addons_users where addon_id=4025;
DELETE FROM previews where addon_id=4025;
DELETE FROM download_counts where addon_id=4025;
DELETE FROM addons where id=4025;

DELETE FROM addons_tags where addon_id=4049;
DELETE FROM addons_users where addon_id=4049;
DELETE FROM previews where addon_id=4049;
DELETE FROM download_counts where addon_id=4049;
DELETE FROM approvals where addon_id=4049;
DELETE FROM files where version_id in (select id from versions where addon_id=4049);
DELETE FROM applications_versions where version_id in (select id from versions where addon_id=4049);
DELETE FROM versions where addon_id=4049;
DELETE FROM addons where id=4049;

DELETE FROM addons_tags where addon_id=4052;
DELETE FROM addons_users where addon_id=4052;
DELETE FROM previews where addon_id=4052;
DELETE FROM addons where id=4052;

DELETE FROM addons_tags where addon_id=4053;
DELETE FROM addons_users where addon_id=4053;
DELETE FROM previews where addon_id=4053;
DELETE FROM files  where version_id in (select id from versions where addon_id=4053);
DELETE FROM versions where addon_id=4053;
DELETE FROM addons where id=4053;

DELETE FROM addons_tags where addon_id=4065;
DELETE FROM addons_users where addon_id=4065;
DELETE FROM previews where addon_id=4065;
DELETE FROM addons where id=4065;

DELETE FROM addons_tags where addon_id=4066;
DELETE FROM addons_users where addon_id=4066;
DELETE FROM previews where addon_id=4066;
DELETE FROM addons where id=4066;

DELETE FROM addons_tags where addon_id=4067;
DELETE FROM addons_users where addon_id=4067;
DELETE FROM previews where addon_id=4067;
DELETE FROM addons where id=4067;

DELETE FROM addons_tags where addon_id=4068;
DELETE FROM addons_users where addon_id=4068;
DELETE FROM previews where addon_id=4068;
DELETE FROM addons where id=4068;

DELETE FROM addons_tags where addon_id=4070;
DELETE FROM addons_users where addon_id=4070;
DELETE FROM previews where addon_id=4070;
DELETE FROM addons where id=4070;
