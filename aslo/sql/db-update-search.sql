SET autocommit=0;
BEGIN;

DELETE FROM `text_search_summary`;

INSERT INTO `text_search_summary`
SELECT  a.id AS id, 
    `tr_name`.locale AS locale,  
    a.addontype_id AS addontype, 
    a.status AS status, 
    a.inactive AS inactive, 
    a.averagerating AS averagerating, 
    a.weeklydownloads AS weeklydownloads,
    CONCAT(REPLACE(`tr_name`.localized_string, ' ', '_ '), '_') AS name, 
    CONCAT(REPLACE(`tr_summary`.localized_string, ' ', '_ '), '_') AS summary, 
    `tr_description`.localized_string AS description
FROM addons AS a 
LEFT JOIN translations AS `tr_name` ON (`tr_name`.id = a.`name`) 
LEFT JOIN translations AS `tr_summary` ON (`tr_summary`.id = a.`summary` AND  `tr_name`.locale = `tr_summary`.locale) 
LEFT JOIN translations AS `tr_description` 
        ON (`tr_description`.id = a.`description` AND  `tr_name`.locale = `tr_description`.locale)
WHERE `tr_name`.locale IS NOT NULL AND (
    `tr_name`.localized_string IS NOT NULL 
    OR `tr_summary`.localized_string IS NOT NULL 
    OR `tr_description`.localized_string IS NOT NULL
) 
ORDER BY a.id ASC, locale DESC;

-- the purpose of the temporary table is to get the most recently created version of an addon (avoiding sub-selects which are mysql 4 bad)

DROP TABLE IF EXISTS `most_recent_version`; -- I am being paranoid to make sure temp table does not exist (it shouldn't by below)

CREATE TEMPORARY TABLE `most_recent_version` (
    `addon_id` int(11) NOT NULL,
    `created` DATETIME NOT NULL   
) DEFAULT CHARSET=utf8;

DELETE FROM `versions_summary`;

INSERT INTO `most_recent_version`
    SELECT DISTINCT v.addon_id, MAX(v.created)
    FROM versions AS v
    INNER JOIN files AS f ON (f.version_id = v.id AND f.status IN (1, 2, 3, 4)) -- implode(',',$valid_status)
    GROUP BY v.addon_id;

INSERT INTO `versions_summary`
    SELECT DISTINCT v.addon_id, v.id, av.application_id, v.created, v.modified, av.min, av.max
    FROM (most_recent_version AS mrv NATURAL JOIN versions AS v) LEFT JOIN applications_versions AS av
    ON (av.version_id = v.id );

DROP TABLE  `most_recent_version`;

COMMIT;
