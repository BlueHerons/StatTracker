SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `Agent` (
  `email` varchar(255) NOT NULL,
  `agent` varchar(15) DEFAULT NULL,
  `faction` enum('E','R') DEFAULT 'R',
  `auth_code` varchar(6) NOT NULL DEFAULT '',
  `request_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `profile_id` varchar(21) NOT NULL,
  PRIMARY KEY (`email`),
  UNIQUE KEY `auth_code` (`auth_code`),
  KEY `faction` (`faction`),
  KEY `agent` (`agent`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

