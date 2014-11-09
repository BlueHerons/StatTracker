DELIMITER $$

CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetUpcomingBadges`(IN `agent_name` VARCHAR(15))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS UpcomingBadges;

CREATE TEMPORARY TABLE UpcomingBadges (
	stat VARCHAR(20),
    badge VARCHAR(20),
    next VARCHAR(10),
    progress DOUBLE(3,2),
    days_remaining DOUBLE(5,1)
);

SELECT MAX(date) INTO @latest_submission
  FROM Data 
 WHERE agent = agent_name;

INSERT INTO UpcomingBadges (stat, badge, next, progress, days_remaining)
  SELECT b.stat, 
         b.name,
         b.level,
         d.value / b.amount_required,
         (b.amount_required - d.value) / GetRateForAgentAndStat(agent_name, b.stat) `remaining`
    FROM Data d
    JOIN Badges b ON d.stat = b.stat 
   WHERE d.agent = agent_name AND 
         d.date = @latest_submission AND
         b.amount_required > d.value
GROUP BY b.stat
ORDER BY remaining ASC;

DELETE FROM UpcomingBadges WHERE stat IN 
       ('ap', 'portals_discovered', 'oldest_portal');

END $$
DELIMITER ;
