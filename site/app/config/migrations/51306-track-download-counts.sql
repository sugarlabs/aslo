-- bug 507221
alter table download_counts add column `src` text default null after `count`;
