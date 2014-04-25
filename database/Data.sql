SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `Data` (
  `agent` varchar(15) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `stat` varchar(20) NOT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`agent`,`timestamp`,`stat`),
  KEY `stat` (`stat`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE `Data`
  ADD CONSTRAINT `stat_fk` FOREIGN KEY (`stat`) REFERENCES `Stats` (`stat`);

