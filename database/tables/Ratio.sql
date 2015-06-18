SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Ratio`;

CREATE TABLE IF NOT EXISTS `Ratio` (
  `stat_1` varchar(20) NOT NULL,
  `stat_2` varchar(20) NOT NULL,
  `factor` decimal(4,1) NOT NULL DEFAULT '100.0',
  PRIMARY KEY (`stat_1`,`stat_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `Ratio` VALUES
('ap', 'hacks', 100.0),
('fields_created', 'links_created', 0.1),
('mu_captured', 'fields_created', 100.0),
('res_deployed', 'hacks', 0.1),
('res_deployed', 'portals_captured', 1.0),
('res_destroyed', 'res_deployed', 0.1),
('unique_visits', 'unique_captures', 1.0);

ALTER TABLE `Ratio`
  ADD CONSTRAINT `Ratio_Stats1_FK` FOREIGN KEY (`stat_1`) REFERENCES `Stats` (`stat`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `Ratio_Stats2_FK` FOREIGN KEY (`stat_2`) REFERENCES `Stats` (`stat`) ON DELETE NO ACTION ON UPDATE NO ACTION;
