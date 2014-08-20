DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetAPBreakdown`(IN `agent_name` VARCHAR(15))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS APBreakdown;

SELECT MAX(timestamp) INTO @latest_submission
  FROM Data
 WHERE agent = agent_name
 LIMIT 1;

CREATE TEMPORARY TABLE APBreakdown
SELECT ap.stat,
       stat.name,
       (FLOOR(q1.value * ap.factor) * ap.ap_gain) ap_gained
  FROM (  SELECT stat,
                 value
            FROM Data
           WHERE agent = agent_name AND
                 timestamp = @latest_submission) q1
        INNER JOIN AP ap ON
              ap.stat = q1.stat
        INNER JOIN Stats stat ON
              ap.stat = stat.stat
  WHERE ap_gain > 0;

SET @totalAP = 0;
SET @remainder = 0;
SELECT value INTO @totalAP FROM Data WHERE stat = 'ap' AND agent = agent_name and timestamp = @latest_submission;
SELECT @totalAP - SUM(ap_gained) FROM APBreakdown INTO @remainder;

INSERT INTO APBreakdown VALUES('', 'Uncalculated', ABS(@remainder));

END $$
DELIMITER ;

