SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Stats`;
CREATE TABLE IF NOT EXISTS `Stats` (
  `order` tinyint(2) NOT NULL,
  `stat` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`stat`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `Stats` (`order`, `stat`, `name`) VALUES
(0, 'ap', 'AP'),
(5, 'unique_visits', 'Unique Portals Visited'),
(10, 'portals_discovered', 'Portals Discovered'),
(15, 'xm_collected', 'XM Collected'),
(20, 'hacks', 'Hacks'),
(25, 'res_deployed', 'Resonators Deployed'),
(30, 'links_created', 'Links Created'),
(35, 'fields_created', 'Control Fields Created'),
(40, 'mu_captured', 'Mind Units Captured'),
(45, 'longest_link', 'Longest Link Ever Created'),
(50, 'largest_field', 'Largest Control Field'),
(55, 'xm_recharged', 'XM Recharged'),
(60, 'portals_captured', 'Portals Captured'),
(65, 'unique_captures', 'Unique Portals Captured'),
(70, 'res_destroyed', 'Resonators Destroyed'),
(75, 'portals_neutralized', 'Portals Neutralized'),
(80, 'links_destroyed', 'Enemy Links Destroyed'),
(85, 'fields_destroyed', 'Enemy Control Fields Destroyed'),
(90, 'distance_walked', 'Distance Walked'),
(95, 'oldest_portal', 'Max Time Portal Held'),
(100, 'oldest_link', 'Max Time Link Maintained'),
(105, 'oldest_link_days', 'Max Link Length x Days'),
(110, 'oldest_field', 'Max Time Field Held'),
(115, 'largest_field_days', 'Largest Field MUs x Days');
