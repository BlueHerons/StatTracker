SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `AgentStats` (
  `agent` varchar(15) NOT NULL DEFAULT '' COMMENT 'Agent Name',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ap` int(11) NOT NULL COMMENT 'AP',
  `unique_visits` int(11) NOT NULL COMMENT 'Unique Portals Visited',
  `portals_discovered` int(11) NOT NULL COMMENT 'Portals Discovered',
  `xm_collected` int(11) NOT NULL COMMENT 'XM Collected',
  `hacks` int(11) NOT NULL COMMENT 'Hacks',
  `res_deployed` int(11) NOT NULL COMMENT 'Resonators Deployed',
  `links_created` int(11) NOT NULL COMMENT 'Links Created',
  `fields_created` int(11) NOT NULL COMMENT 'Control Fields Created',
  `mu_captured` int(11) NOT NULL COMMENT 'Mind Units Captured',
  `longest_link` int(11) NOT NULL COMMENT 'Longest Link Ever Created',
  `largest_field` int(11) NOT NULL COMMENT 'Largest Control Field',
  `xm_recharged` int(11) NOT NULL COMMENT 'XM Recharged',
  `portals_captured` int(11) NOT NULL COMMENT 'Portals Captured',
  `unique_captures` int(11) NOT NULL COMMENT 'Unique Portals Captured',
  `res_destroyed` int(11) NOT NULL COMMENT 'Resonators Destroyed',
  `portals_neutralized` int(11) NOT NULL COMMENT 'Portals Neutralized',
  `links_destroyed` int(11) NOT NULL COMMENT 'Enemy Links Destroyed',
  `fields_destroyed` int(11) NOT NULL COMMENT 'Enemy Control Fields Destroyed',
  `distance_walked` int(11) NOT NULL COMMENT 'Distance Walked',
  `oldest_portal` int(11) NOT NULL COMMENT 'Max Time Portal Held',
  `oldest_link` int(11) NOT NULL COMMENT 'Max Time Link Maintained',
  `oldest_link_days` int(11) NOT NULL COMMENT 'Max Link Length x Days',
  `oldest_field` int(11) NOT NULL COMMENT 'Max Time Field Held',
  `largest_field_days` int(11) NOT NULL COMMENT 'Largest Field MUs x Days',
  PRIMARY KEY (`agent`,`timestamp`),
  UNIQUE KEY `UNIQUE` (`agent`,`ap`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

