SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE IF NOT EXISTS `Stats` (
  `order` tinyint(2) NOT NULL,
  `stat` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `unit` varchar(10) NOT NULL,
  PRIMARY KEY (`stat`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `Stats` (`order`, `stat`, `name`, `unit`) VALUES
(0, 'ap', 'AP', 'AP'),
(5, 'unique_visits', 'Unique Portals Visited', 'portals'),
(10, 'portals_discovered', 'Portals Discovered', 'portals'),
(15, 'xm_collected', 'XM Collected', 'XM'),
(20, 'hacks', 'Hacks', 'hacks'),
(25, 'res_deployed', 'Resonators Deployed', 'resonators'),
(30, 'links_created', 'Links Created', 'links'),
(35, 'fields_created', 'Control Fields Created', 'fields'),
(40, 'mu_captured', 'Mind Units Captured', 'MUs'),
(45, 'longest_link', 'Longest Link Ever Created', 'km'),
(50, 'largest_field', 'Largest Control Field', 'MUs'),
(55, 'xm_recharged', 'XM Recharged', 'XM'),
(60, 'portals_captured', 'Portals Captured', 'portals'),
(65, 'unique_captures', 'Unique Portals Captured', 'portals'),
(70, 'res_destroyed', 'Resonators Destroyed', 'resonators'),
(75, 'portals_neutralized', 'Portals Neutralized', 'portals'),
(80, 'links_destroyed', 'Enemy Links Destroyed', 'links'),
(85, 'fields_destroyed', 'Enemy Control Fields Destroyed', 'fields'),
(90, 'distance_walked', 'Distance Walked', 'km'),
(95, 'oldest_portal', 'Max Time Portal Held', 'days'),
(100, 'oldest_link', 'Max Time Link Maintained', 'days'),
(105, 'oldest_link_days', 'Max Link Length x Days', 'km-days'),
(110, 'oldest_field', 'Max Time Field Held', 'days'),
(115, 'largest_field_days', 'Largest Field MUs x Days', 'MU-days');
