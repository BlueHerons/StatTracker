DELIMITER $$

DROP PROCEDURE IF EXISTS `GetAPBreakdown` $$

CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetAPBreakdown`(IN `agent_name` VARCHAR(15))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS APBreakdown;

SELECT MAX(date) INTO @latest_submission
  FROM Data
 WHERE agent = agent_name
 LIMIT 1;

CREATE TEMPORARY TABLE APBreakdown
SELECT ap.grouping,
       ap.sequence,
       ap.stat,
       stat.name,
       (FLOOR(q1.value * ap.factor) * ap.ap_gain) ap_gained
  FROM (  SELECT stat,
                 value
            FROM Data
           WHERE agent = agent_name AND
                 date = @latest_submission) q1
        INNER JOIN AP ap ON
              ap.stat = q1.stat
        INNER JOIN Stats stat ON
              ap.stat = stat.stat
  WHERE ap_gain > 0
  ORDER BY ap.grouping DESC, ap.sequence ASC;

-- Meta Stats
SELECT ap_gain INTO @deploy_ap FROM AP WHERE stat = 'res_deployed';
SELECT value INTO @portals_captured FROM Data WHERE agent = agent_name AND date = @latest_submission AND stat = 'portals_captured';
UPDATE APBreakdown SET ap_gained = ap_gained + (@portals_captured * @deploy_ap) WHERE stat = 'res_deployed';

SELECT 65 INTO @upgrade_ap;
SELECT value - @portals_captured INTO @res_deployed FROM Data WHERE agent = agent_name AND date = @latest_submission AND stat = 'res_deployed';
UPDATE APBreakdown SET ap_gained = ap_gained + (@res_deployed * @upgrade_ap) WHERE stat = 'res_deployed';

SET @totalAP = 0;
SET @remainder = 0;
SELECT value INTO @totalAP FROM Data WHERE stat = 'ap' AND agent = agent_name and date = @latest_submission;
SELECT @totalAP - SUM(ap_gained) FROM APBreakdown INTO @remainder;

INSERT INTO APBreakdown VALUES(2, 1, '', 'Uncalculated', ABS(@remainder));

END $$
DELIMITER ;

