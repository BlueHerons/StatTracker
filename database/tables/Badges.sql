SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `Badges`;
CREATE TABLE IF NOT EXISTS `Badges` (
  `name` varchar(20) NOT NULL,
  `level` enum('None','Bronze','Silver','Gold','Platinum','Onyx','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16') NOT NULL,
  `stat` varchar(20) NOT NULL,
  `amount_required` int(11) NOT NULL,
  PRIMARY KEY (`name`,`level`),
  KEY `stat` (`stat`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `Badges` (`name`, `level`, `stat`, `amount_required`) VALUES
('Builder', 'None', 'res_deployed', 0),
('Builder', 'Bronze', 'res_deployed', 2000),
('Builder', 'Silver', 'res_deployed', 10000),
('Builder', 'Gold', 'res_deployed', 30000),
('Builder', 'Platinum', 'res_deployed', 100000),
('Builder', 'Onyx', 'res_deployed', 200000),
('Connector', 'None', 'links_created', 0),
('Connector', 'Bronze', 'links_created', 50),
('Connector', 'Silver', 'links_created', 1000),
('Connector', 'Gold', 'links_created', 5000),
('Connector', 'Platinum', 'links_created', 25000),
('Connector', 'Onyx', 'links_created', 100000),
('Explorer', 'None', 'unique_visits', 0),
('Explorer', 'Bronze', 'unique_visits', 100),
('Explorer', 'Silver', 'unique_visits', 1000),
('Explorer', 'Gold', 'unique_visits', 2000),
('Explorer', 'Platinum', 'unique_visits', 10000),
('Explorer', 'Onyx', 'unique_visits', 30000),
('Guardian', 'None', 'oldest_portal', 0),
('Guardian', 'Bronze', 'oldest_portal', 3),
('Guardian', 'Silver', 'oldest_portal', 10),
('Guardian', 'Gold', 'oldest_portal', 20),
('Guardian', 'Platinum', 'oldest_portal', 90),
('Guardian', 'Onyx', 'oldest_portal', 150),
('Hacker', 'None', 'hacks', 0),
('Hacker', 'Bronze', 'hacks', 2000),
('Hacker', 'Silver', 'hacks', 10000),
('Hacker', 'Gold', 'hacks', 30000),
('Hacker', 'Platinum', 'hacks', 100000),
('Hacker', 'Onyx', 'hacks', 200000),
('Level', '1', 'ap', 0),
('Level', '2', 'ap', 10000),
('Level', '3', 'ap', 30000),
('Level', '4', 'ap', 70000),
('Level', '5', 'ap', 150000),
('Level', '6', 'ap', 300000),
('Level', '7', 'ap', 600000),
('Level', '8', 'ap', 1200000),
('Level', '9', 'ap', 2400000),
('Level', '10', 'ap', 4000000),
('Level', '11', 'ap', 6000000),
('Level', '12', 'ap', 8400000),
('Level', '13', 'ap', 12000000),
('Level', '14', 'ap', 17000000),
('Level', '15', 'ap', 24000000),
('Level', '16', 'ap', 40000000),
('Liberator', 'None', 'portals_captured', 0),
('Liberator', 'Bronze', 'portals_captured', 100),
('Liberator', 'Silver', 'portals_captured', 1000),
('Liberator', 'Gold', 'portals_captured', 5000),
('Liberator', 'Platinum', 'portals_captured', 15000),
('Liberator', 'Onyx', 'portals_captured', 40000),
('Mind Controller', 'None', 'fields_created', 0),
('Mind Controller', 'Bronze', 'fields_created', 100),
('Mind Controller', 'Silver', 'fields_created', 500),
('Mind Controller', 'Gold', 'fields_created', 2000),
('Mind Controller', 'Platinum', 'fields_created', 10000),
('Mind Controller', 'Onyx', 'fields_created', 40000),
('Pioneer', 'None', 'unique_captures', 0),
('Pioneer', 'Bronze', 'unique_captures', 20),
('Pioneer', 'Silver', 'unique_captures', 200),
('Pioneer', 'Gold', 'unique_captures', 1000),
('Pioneer', 'Platinum', 'unique_captures', 5000),
('Pioneer', 'Onyx', 'unique_captures', 20000),
('Purifier', 'None', 'res_destroyed', 0),
('Purifier', 'Bronze', 'res_destroyed', 2000),
('Purifier', 'Silver', 'res_destroyed', 10000),
('Purifier', 'Gold', 'res_destroyed', 30000),
('Purifier', 'Platinum', 'res_destroyed', 100000),
('Purifier', 'Onyx', 'res_destroyed', 300000),
('Recharger', 'None', 'xm_recharged', 0),
('Recharger', 'Bronze', 'xm_recharged', 100000),
('Recharger', 'Silver', 'xm_recharged', 1000000),
('Recharger', 'Gold', 'xm_recharged', 3000000),
('Recharger', 'Platinum', 'xm_recharged', 10000000),
('Recharger', 'Onyx', 'xm_recharged', 25000000),
('Seer', 'None', 'portals_discovered', 0),
('Seer', 'Bronze', 'portals_discovered', 10),
('Seer', 'Silver', 'portals_discovered', 50),
('Seer', 'Gold', 'portals_discovered', 200),
('Seer', 'Platinum', 'portals_discovered', 500),
('Seer', 'Onyx', 'portals_discovered', 5000);


ALTER TABLE `Badges`
  ADD CONSTRAINT `stat_fk` FOREIGN KEY (`stat`) REFERENCES `Stats` (`stat`);

