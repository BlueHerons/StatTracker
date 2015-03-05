SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Level`;

CREATE TABLE `Level` (
  `level` tinyint(4) NOT NULL,
  `ap_required` int(11) NOT NULL,
  `silver_required` tinyint(4) NOT NULL,
  `gold_required` tinyint(4) NOT NULL,
  `platinum_required` tinyint(4) NOT NULL,
  `onyx_required` tinyint(4) NOT NULL,
  PRIMARY KEY (`level`),
  UNIQUE KEY `ap_required` (`ap_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `Level` (`level`, `ap_required`, `silver_required`, `gold_required`, `platinum_required`, `onyx_required`) VALUES
( 1,        0, 0, 0, 0, 0),
( 2,    10000, 0, 0, 0, 0),
( 3,    30000, 0, 0, 0, 0),
( 4,    70000, 0, 0, 0, 0),
( 5,   150000, 0, 0, 0, 0),
( 6,   300000, 0, 0, 0, 0),
( 7,   600000, 0, 0, 0, 0),
( 8,  1200000, 0, 0, 0, 0),
( 9,  2400000, 4, 1, 0, 0),
(10,  4000000, 5, 2, 0, 0),
(11,  6000000, 6, 4, 0, 0),
(12,  8400000, 7, 6, 0, 0),
(13, 12000000, 0, 7, 1, 0),
(14, 17000000, 0, 0, 2, 0),
(15, 24000000, 0, 0, 3, 0),
(16, 40000000, 0, 0, 4, 2);

