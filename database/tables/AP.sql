SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `AP`;
CREATE TABLE IF NOT EXISTS `AP` (
  `stat` varchar(20) NOT NULL,
  `ap_gain` int(4) NOT NULL,
  `factor` double(3,2) NOT NULL DEFAULT '1.00' COMMENT 'Factor to multiply value by before multiplying by ap_gain',
  `group` enum('Building','Destroying','Misc','Other') NOT NULL DEFAULT 'Other',
  PRIMARY KEY (`stat`,`ap_gain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `AP` (`stat`, `ap_gain`, `factor`, `group`) VALUES
('fields_created', 1250, 1.00, 'Building'),
('fields_destroyed', 750, 1.00, 'Destroying'),
('links_created', 313, 1.00, 'Building'),
('links_destroyed', 187, 1.00, 'Destroying'),
('portals_captured', 500, 1.00, 'Building'),
('portals_discovered', 1000, 1.00, 'Misc'),
('portals_neutralized', 0, 1.00, 'Destroying'),
('res_destroyed', 75, 1.00, 'Destroying'),
('xm_recharged', 10, 0.01, 'Misc');


ALTER TABLE `AP`
  ADD CONSTRAINT `AP_ibfk_1` FOREIGN KEY (`stat`) REFERENCES `Stats` (`stat`);
