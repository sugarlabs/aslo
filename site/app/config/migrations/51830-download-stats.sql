-- Recalculate missing download stats for 2009-08-31 and 2009-09-01
--
-- This migration has nothing to do revision 51830, but will fix
-- bug 517952 once pushed and run on production.

REPLACE INTO global_stats (name, count, date) VALUES ('addon_downloads_new',
(SELECT IFNULL(SUM(count), 0) FROM download_counts WHERE date = '2009-08-31'),
'2009-08-31');

REPLACE INTO global_stats (name, count, date) VALUES ('addon_downloads_new',
(SELECT IFNULL(SUM(count), 0) FROM download_counts WHERE date = '2009-09-01'),
'2009-09-01');
