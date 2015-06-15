SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Ratio`;

CREATE TABLE `Ratio` (
  `stat_1` varchar(20) NOT NULL,
  `stat_2` varchar(20) NOT NULL,
  PRIMARY KEY (`stat_1`,`stat_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `Ratio` VALUES
('ap', 'hacks'),
('fields_created', 'links_created'),
('mu_captured', 'fields_created'),
('res_deployed', 'hacks'),
('res_deployed', 'portals_captured'),
('res_destroyed', 'res_deployed'),
('unique_visits', 'unique_captures');

ALTER TABLE `Ratio`
  ADD CONSTRAINT `Ratio_Stats1_FK` FOREIGN KEY (`stat_1`) REFERENCES `Stats` (`stat`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `Ratio_Stats2_FK` FOREIGN KEY (`stat_2`) REFERENCES `Stats` (`stat`) ON DELETE NO ACTION ON UPDATE NO ACTION;
