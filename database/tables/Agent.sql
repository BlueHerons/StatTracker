SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `Agent` (
  `email` varchar(255) NOT NULL,
  `agent` varchar(15) DEFAULT NULL,
  `faction` enum('E','R') DEFAULT 'R',
  `auth_code` varchar(6) NOT NULL DEFAULT '',
  `meta` TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (`email`),
  KEY `faction` (`faction`),
  KEY `agent` (`agent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
