SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `AP`;

CREATE TABLE `AP` (
  `stat` varchar(20) NOT NULL,
  `grouping` tinyint(4) NOT NULL,
  `sequence` tinyint(4) NOT NULL,
  `ap_gain` int(4) NOT NULL,
  `factor` double(4,3) NOT NULL DEFAULT '1.000' COMMENT 'Factor to multiply value by before multiplying by ap_gain',
  PRIMARY KEY (`stat`,`ap_gain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `AP`
(`stat`,               `grouping`, `sequence`, `ap_gain`, `factor`) VALUES
('fields_destroyed',   1,          3,          750,       1.000),
('links_destroyed',    1,          2,          187,       1.000),
('res_destroyed',      1,          1,          75,        1.000),
('portals_discovered', 3,          0,          1000,      1.000),
('fields_created',     3,          1,          1250,      1.000),
('links_created',      3,          2,          313,       1.000),
('portals_captured',   3,          3,          500,       1.000),
('mods_deployed',      3,          4,          125,       1.000),
('res_deployed',       3,          5,          125,       0.000),
('xm_recharged',       3,          6,          10,        0.001);

ALTER TABLE `AP`
  ADD CONSTRAINT `AP_ibfk_1` FOREIGN KEY (`stat`) REFERENCES `Stats` (`stat`);

