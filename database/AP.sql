SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE `AP` (
  `stat` varchar(20) NOT NULL,
  `ap_gain` int(4) NOT NULL,
  PRIMARY KEY (`stat`,`ap_gain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `AP` (`stat`, `ap_gain`) VALUES
('fields_destroyed', 750),
('links_destroyed', 187),
('res_destroyed', 75);


ALTER TABLE `AP`
  ADD CONSTRAINT `stat_fk` FOREIGN KEY (`stat`) REFERENCES `Stats` (`stat`);

