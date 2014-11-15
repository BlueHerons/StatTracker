SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Ratio`;

CREATE TABLE `Ratio` (
  `stat_1` varchar(20) CHARACTER SET latin1 NOT NULL,
  `stat_2` varchar(20) CHARACTER SET latin1 NOT NULL,
  `message` text COLLATE latin1_general_cs NOT NULL,
  PRIMARY KEY (`stat_1`,`stat_2`),
  KEY `stat_2` (`stat_2`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;

INSERT INTO `Ratio` VALUES
('links_created', 'fields_created', ''),
('mu_captured', 'fields_created', ''),
('portals_captured', 'portals_neutralized', ''),
('portals_captured', 'res_deployed', ''),
('res_destroyed', 'res_deployed', ''),
('xm_collected', 'xm_recharged', '');


ALTER TABLE `Ratio`
  ADD CONSTRAINT `Ratio_ibfk_1` FOREIGN KEY (`stat_1`) REFERENCES `Stats` (`stat`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `Ratio_ibfk_2` FOREIGN KEY (`stat_2`) REFERENCES `Stats` (`stat`) ON DELETE NO ACTION ON UPDATE NO ACTION;
