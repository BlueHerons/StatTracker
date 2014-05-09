CREATE DEFINER=`SRStats`@`localhost` PROCEDURE `GetCurrentBadges`()
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS CurrentBadges;

CREATE TEMPORARY TABLE CurrentBadges
SELECT q2.stat,
       b.name `badge`,
	   b.level 
  FROM (SELECT q1.*,
               MAX(b.amount_required) `threshold` 
          FROM (SELECT stat, 
                       MAX(value) `value` 
                  FROM RawStatsForAgent 
              GROUP BY stat) q1 
            INNER JOIN Badges b ON 
                       b.stat = q1.stat AND 
                       q1.value > b.amount_required
              GROUP BY b.stat) q2
LEFT JOIN Badges b ON 
          q2.stat = b.stat AND 
          q2.threshold = b.amount_required;

END
