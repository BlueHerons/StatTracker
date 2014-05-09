SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Stats`;

CREATE TABLE `Stats` (
  `order` tinyint(2) NOT NULL,
  `stat` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `unit` varchar(15) NOT NULL,
  `graph` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`stat`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `Stats` 
(`order`, `stat`,            `name`,                           `unit`,            `graph`) VALUES
(  0, 'ap',                  'AP',                             'AP',              1),
(  5, 'unique_visits',       'Unique Portals Visited',         'portals',         1),
( 10, 'portals_discovered',  'Portals Discovered',             'discoveries',     1),
( 15, 'xm_collected',        'XM Collected',                   'XM',              1),
( 20, 'hacks',               'Hacks',                          'hacks',           1),
( 25, 'res_deployed',        'Resonators Deployed',            'deployments',     1),
( 30, 'links_created',       'Links Created',                  'links',           1),
( 35, 'fields_created',      'Control Fields Created',         'fields',          1),
( 40, 'mu_captured',         'Mind Units Captured',            'MUs',             1),
( 45, 'longest_link',        'Longest Link Ever Created',      'km',              0),
( 50, 'largest_field',       'Largest Control Field',          'MUs',             0),
( 55, 'xm_recharged',        'XM Recharged',                   'XM',              1),
( 60, 'portals_captured',    'Portals Captured',               'captures',        1),
( 65, 'unique_captures',     'Unique Portals Captured',        'unique captures', 1),
( 70, 'res_destroyed',       'Resonators Destroyed',           'resonators',      1),
( 75, 'portals_neutralized', 'Portals Neutralized',            'portals',         1),
( 80, 'links_destroyed',     'Enemy Links Destroyed',          'links',           1),
( 85, 'fields_destroyed',    'Enemy Control Fields Destroyed', 'fields',          1),
( 90, 'distance_walked',     'Distance Walked',                'km',              1),
( 95, 'oldest_portal',       'Max Time Portal Held',           'days',            0),
(100, 'oldest_link',         'Max Time Link Maintained',       'days',            0),
(105, 'oldest_link_days',    'Max Link Length x Days',         'km-days',         0),
(110, 'oldest_field',        'Max Time Field Held',            'days',            0),
(115, 'largest_field_days',  'Largest Field MUs x Days',       'MU-days',         0);

