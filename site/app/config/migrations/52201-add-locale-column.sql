-- This is another change that has nothing to do with this revision.  We need this
-- column for stat counting.  See bug 518707
alter table update_counts add column locale text after os;
