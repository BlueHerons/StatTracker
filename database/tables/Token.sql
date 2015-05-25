SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `Tokens` (
  `agent` varchar(15) NOT NULL,
  `name` varchar(25) NOT NULL,
  `token` varchar(64) NOT NULL,
  `last_used` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`agent`,`name`),
  UNIQUE KEY `token` (`token`),
  KEY `agent` (`agent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `Tokens`
  ADD CONSTRAINT `FK_Token_Agent_agent` FOREIGN KEY (`agent`) REFERENCES `Agent` (`agent`) ON UPDATE CASCADE;
