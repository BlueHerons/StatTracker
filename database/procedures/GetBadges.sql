DELIMITER $$

CREATE PROCEDURE `GetBadges`(IN `agent_name` VARCHAR(15), IN `submission_date` DATE)
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN

DROP TABLE IF EXISTS _Badges;

SELECT count(*) > 0 INTO @has_submission
  FROM Data
 WHERE agent = agent_name AND
       date = submission_date;

IF (~@has_submission) THEN
  SELECT MAX(date) INTO submission_date
    FROM Data
   WHERE agent = agent_name;
END IF;

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
