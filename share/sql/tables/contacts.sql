DROP TABLE IF EXISTS `contacts`;

CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  `firstname` varchar(255) default NULL,
  `lastname` varchar(255) default NULL,
  `email` varchar(255) UNIQUE default NULL,
  `phone` varchar(100) default NULL,
  `favorite` INTEGER NOT NULL  DEFAULT 0
);
