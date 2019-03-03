CREATE TABLE `sessions` (
  `id` BINARY(32) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `data` longtext CHARSET 'utf8mb4' NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
