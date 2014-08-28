SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `Data` (
  `agent` varchar(15) NOT NULL COMMENT 'Agent Name',
  `date` date NOT NULL COMMENT 'Date of Entry',
  `timepoint` int(3) NOT NULL COMMENT 'Days since first submission',
  `stat` varchar(20) NOT NULL,
  `value` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '[DEPRECATED]',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated',
  PRIMARY KEY (`agent`,`date`,`timestamp`,`stat`),
  KEY `stat` (`stat`),
  KEY `agent_timestamp` (`agent`,`timestamp`),
  KEY `agent_date` (`agent`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE `Data`
  ADD CONSTRAINT `Data_ibfk_1` FOREIGN KEY (`agent`) REFERENCES `Agent` (`agent`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `Data_ibfk_2` FOREIGN KEY (`stat`) REFERENCES `Stats` (`stat`) ON DELETE NO ACTION ON UPDATE NO ACTION;
