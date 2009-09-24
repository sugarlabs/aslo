
-- This is the main view that will seed our Sphinx index.
CREATE OR REPLACE VIEW translated_addons
AS
SELECT
    name.autoid AS id,
    a.id AS addon_id,
    a.addontype_id AS addontype,
    a.status,
    name.locale,
    CRC32(name.locale) AS locale_ord,
    a.averagerating,
    a.weeklydownloads,
    a.totaldownloads,
    a.inactive,
    LTRIM(name.localized_string) AS name,
    (
        SELECT GROUP_CONCAT(nickname)
        FROM addons_users au, users u
        WHERE addon_id=a.id AND au.user_id = u.id AND listed = 1
    ) AS authors,
    (
         SELECT GROUP_CONCAT(tag_text) 
         FROM users_tags_addons, tags 
         WHERE tags.id = tag_id AND addon_id = a.id
    ) AS tags,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.homepage AND locale = name.locale
    ) AS homepage,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.description AND locale = name.locale
    ) AS description,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.summary AND locale = name.locale
    ) AS summary,
    (
        SELECT localized_string
        FROM translations
        WHERE id = a.developercomments AND locale = name.locale
    ) AS developercomments,
    (
        SELECT max(version_int)
        FROM versions v, files f, applications_versions av, appversions max
        WHERE f.version_id =v.id
            AND v.addon_id = a.id
            AND av.version_id = v.id AND av.max = max.id AND f.status = 4
    ) AS max_ver,
    (
        SELECT min(version_int)
        FROM versions v, files f, applications_versions av, appversions min
        WHERE f.version_id =v.id
            AND v.addon_id = a.id
            AND av.version_id = v.id
            AND av.min = min.id
            AND f.status = 4
    ) AS min_ver,
    UNIX_TIMESTAMP(a.created) AS created,
    (
        SELECT UNIX_TIMESTAMP(MAX(IFNULL(f.datestatuschanged, f.created))) 
        FROM versions AS v 
        INNER JOIN files AS f ON f.status = 4 AND f.version_id = v.id 
        WHERE v.addon_id=a.id
    ) AS modified
FROM
    translations name,
    addons a
WHERE a.name = name.id;