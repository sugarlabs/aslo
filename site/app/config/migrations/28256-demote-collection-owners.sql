-- demote all owners to managers (bug 496419)
UPDATE `collections_users` SET role = 1 WHERE role = 2;
