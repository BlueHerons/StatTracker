SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `Agent` (
  `agent` varchar(20) NOT NULL,
  `faction` enum('E','R') NOT NULL DEFAULT 'R',
  `email` varchar(255) NOT NULL,
  `auth_code` varchar(6) NOT NULL,
  PRIMARY KEY (`email`),
  KEY `faction` (`faction`),
  KEY `agent` (`agent`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
