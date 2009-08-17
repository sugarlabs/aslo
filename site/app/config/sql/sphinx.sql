-- We should ensure a unique "name" (which is an FK to translations), since we'll use this as the
-- document id
-- This takes 8s using near-production data.
ALTER TABLE addons ADD CONSTRAINT UNIQUE(name);


-- We should also create a autoid field (autoincremented unique primary key)
-- for the translations table

ALTER TABLE translations 
DROP PRIMARY KEY, 
ADD COLUMN autoid INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY FIRST, 
ADD CONSTRAINT UNIQUE(`id`, `locale`);

-- Query OK, 481606 rows affected (22.80 sec)

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
    name.localized_string AS name,
    (SELECT localized_string FROM translations WHERE id = a.homepage AND locale = name.locale) AS homepage,
    (SELECT localized_string FROM translations WHERE id = a.description AND locale = name.locale) AS description,
    (SELECT localized_string FROM translations WHERE id = a.summary AND locale = name.locale) AS summary,
    (SELECT localized_string FROM translations WHERE id = a.developercomments AND locale = name.locale) AS developercomments,
    (SELECT max(version_int) FROM versions v, applications_versions av, appversions max WHERE v.addon_id = a.id AND av.version_id = v.id AND av.max = max.id) AS max_ver,
    (SELECT min(version_int) FROM versions v, applications_versions av, appversions min WHERE v.addon_id = a.id AND av.version_id = v.id AND av.min = min.id) AS min_ver,
    UNIX_TIMESTAMP(a.created) AS created,
    UNIX_TIMESTAMP(a.modified) AS modified
FROM 
    translations name, 
    addons a
WHERE a.name                = name.id

-- This view is used to extract some version-related data

versions (we use this for joins)

CREATE OR REPLACE VIEW versions_summary_view AS

SELECT DISTINCT 
    t.autoid AS translation_id, 
    v.addon_id, 
    v.id, 
    av.application_id, 
    v.created, 
    v.modified, 
    min.version_int AS min, 
    max.version_int AS max, 
    MAX(v.created)
FROM versions v, addons a, translations t, applications_versions av, appversions max, appversions min
WHERE 
    a.id = v.addon_id AND a.name = t.id AND av.version_id = v.id
    AND av.min = min.id AND av.max=max.id
GROUP BY 
    translation_id, 
    v.addon_id, 
    v.id, 
    av.application_id, 
    v.created, 
    v.modified, 
    min.version_int, 
    max.version_int;

