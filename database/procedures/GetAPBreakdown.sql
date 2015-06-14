DROP PROCEDURE IF EXISTS `GetAPBreakdown`;

CREATE PROCEDURE `GetAPBreakdown`(IN `agent_name` VARCHAR(15), IN `days_back` INT(2))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS APBreakdown;

SET @recent = GetClosestSubmissionDate(agent_name, now());
SET @target = IF(days_back = 0, 
              (SELECT DATE_SUB(MIN(date), INTERVAL 1 DAY) FROM Data WHERE agent = agent_name), 
              GetClosestSubmissionDate(agent_name, DATE_SUB(@recent, INTERVAL days_back DAY)));

CREATE TEMPORARY TABLE APBreakdown
SELECT @recent `start`,
       @target `end`,
       ap.grouping,
       ap.sequence,
       ap.stat,
       stat.name,
       COALESCE((FLOOR(q1.value * ap.factor) * ap.ap_gain), 0) ap_gained
  FROM (  SELECT stat, GetStatDiffForAgentBetweenDates(agent_name, stat, @recent, @target) `value` FROM Stats) q1
        INNER JOIN AP ap ON
              ap.stat = q1.stat
        INNER JOIN Stats stat ON
              ap.stat = stat.stat
  WHERE ap_gain > 0
ORDER BY ap.grouping DESC, ap.sequence ASC;

-- Meta Stats
SELECT ap_gain INTO @deploy_ap FROM AP WHERE stat = 'res_deployed';
SELECT GetStatDiffForAgentBetweenDates(agent_name, 'portals_captured', @recent, @target) INTO @portals_captured;
UPDATE APBreakdown SET ap_gained = COALESCE(ap_gained, 0) + (@portals_captured * @deploy_ap) WHERE stat = 'res_deployed';

SELECT 65 INTO @upgrade_ap;
SELECT GetStatDiffForAgentBetweenDates(agent_name, 'res_deployed', @recent, @target) - @portals_captured INTO @res_deployed;
UPDATE APBreakdown SET ap_gained = COALESCE(ap_gained, 0) + ((@res_deployed - @portals_captured) * @upgrade_ap) WHERE stat = 'res_deployed';

SET @totalAP = 0;
SET @remainder = 0;
SELECT GetStatDiffForAgentBetweenDates(agent_name, 'ap', @recent, @target) INTO @totalAP;

IF (@totalAP != 0) THEN
    SELECT @totalAP - SUM(ap_gained) FROM APBreakdown INTO @remainder;
    INSERT INTO APBreakdown VALUES(@recent, @target, 2, 1, '', 'Uncalculated', ABS(@remainder));
END IF;

END;
