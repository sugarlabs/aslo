-- Cache for blog posts
CREATE TABLE `blogposts` (
    `title` varchar(255) NOT NULL default '',
    `date_posted` datetime NOT NULL default '0000-00-00 00:00:00',
    `permalink` text NOT NULL default ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
