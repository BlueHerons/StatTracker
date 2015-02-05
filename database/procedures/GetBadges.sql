DELIMITER $$

CREATE DEFINER=`StatTracker_ADM`@`localhost` PROCEDURE `GetBadges`(IN `agent_name` VARCHAR(15), IN `submission_date` DATE)
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS _Badges;

CREATE TEMPORARY TABLE _Badges
SELECT q2.stat,
       b.name `badge`,
	   b.level 
  FROM (SELECT q1.*,
               MAX(b.amount_required) `threshold` 
          FROM (SELECT stat, 
                       value
                  FROM Data 
                 WHERE date = submission_date AND
                       agent = agent_name) q1 
            INNER JOIN Badges b ON 
                       b.stat = q1.stat AND 
                       q1.value >= b.amount_required
              GROUP BY b.stat) q2
LEFT JOIN Badges b ON 
          q2.stat = b.stat AND 
          q2.threshold = b.amount_required
ORDER BY `badge`;

END $$

DELIMITER ;
