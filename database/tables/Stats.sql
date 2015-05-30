SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Stats`;
CREATE TABLE IF NOT EXISTS `Stats` (
  `order` tinyint(3) NOT NULL,
  `stat` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `unit` varchar(15) NOT NULL,
  `group` varchar(18) NOT NULL,
  `ocr` tinyint(1) NOT NULL DEFAULT '0',
  `graph` tinyint(1) NOT NULL DEFAULT '0',
  `leaderboard` tinyint(1) NOT NULL DEFAULT '7',
  `prediction` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`stat`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `Stats` (`order`, `stat`, `name`, `unit`, `group`, `ocr`, `graph`, `leaderboard`, `prediction`) VALUES
(0, 'ap', 'AP', 'AP', '', 1, 1, 7, 0),
(3, 'innovator', 'Innovator', '', '', 0, 0, 0, 0),
(6, 'unique_visits', 'Unique Portals Visited', 'visits', 'Discovery', 1, 1, 7, 1),
(9, 'portals_discovered', 'Portals Discovered', 'discoveries', 'Discovery', 1, 0, 7, 0),
(12, 'xm_collected', 'XM Collected', 'XM', 'Discovery', 1, 1, 7, 0),
(15, 'distance_walked', 'Distance Walked', 'km', 'Health', 1, 1, 7, 1),
(18, 'res_deployed', 'Resonators Deployed', 'deployments', 'Building', 1, 1, 7, 1),
(21, 'links_created', 'Links Created', 'links', 'Building', 1, 1, 7, 1),
(24, 'fields_created', 'Control Fields Created', 'fields', 'Building', 1, 1, 7, 1),
(27, 'mu_captured', 'Mind Units Captured', 'MUs', 'Building', 1, 1, 7, 1),
(30, 'longest_link', 'Longest Link Ever Created', 'km', 'Building', 1, 0, 1, 0),
(33, 'largest_field', 'Largest Control Field', 'MUs', 'Building', 1, 0, 1, 0),
(36, 'xm_recharged', 'XM Recharged', 'XM', 'Building', 1, 1, 7, 1),
(39, 'portals_captured', 'Portals Captured', 'captures', 'Building', 1, 1, 7, 1),
(42, 'unique_captures', 'Unique Portals Captured', 'unique captures', 'Building', 1, 1, 7, 1),
(45, 'mods_deployed', 'Mods Deployed', 'mods', 'Building', 1, 1, 7, 1),
(48, 'res_destroyed', 'Resonators Destroyed', 'resonators', 'Combat', 1, 1, 7, 1),
(51, 'portals_neutralized', 'Portals Neutralized', 'portals', 'Combat', 1, 1, 7, 0),
(54, 'links_destroyed', 'Enemy Links Destroyed', 'links', 'Combat', 1, 1, 7, 0),
(57, 'fields_destroyed', 'Enemy Control Fields Destroyed', 'fields', 'Combat', 1, 1, 7, 0),
(60, 'oldest_portal', 'Max Time Portal Held', 'days', 'Defense', 1, 0, 1, 0),
(63, 'oldest_link', 'Max Time Link Maintained', 'days', 'Defense', 1, 0, 1, 0),
(66, 'oldest_link_days', 'Max Link Length x Days', 'km-days', 'Defense', 1, 0, 1, 0),
(69, 'oldest_field', 'Max Time Field Held', 'days', 'Defense', 1, 0, 1, 0),
(72, 'largest_field_days', 'Largest Field MUs x Days', 'MU-days', 'Defense', 1, 0, 1, 0),
(75, 'unique_missions', 'Unique Missions Completed', 'missions', 'Missions', 1, 1, 7, 1),
(78, 'hacks', 'Hacks', 'hacks', 'Resource Gathering', 1, 1, 7, 1),
(81, 'glyphs', 'Glyph Hack Points', 'points', 'Resource Gathering', 1, 1, 7, 1),
(84, 'hacking_streak', 'Longest Hacking Streak', 'days', 'Resource Gathering', 1, 0, 1, 0),
(87, 'recruits', 'Agents Successfully Recruited', 'agents', 'Mentoring', 1, 0, 7, 0);
