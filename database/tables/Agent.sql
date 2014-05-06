SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `Agent` (
  `agent` varchar(20) NOT NULL,
  `faction` enum('E','R') NOT NULL DEFAULT 'R',
  `email` varchar(255) NOT NULL,
  `auth_code` varchar(6) NOT NULL,
  PRIMARY KEY (`agent`),
  UNIQUE KEY `email` (`email`),
  KEY `faction` (`faction`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
