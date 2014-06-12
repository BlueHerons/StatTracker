CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetWeeklyLeaderboardForStat`(IN `stat_name` VARCHAR(20), IN `start_date` TIMESTAMP)
    READS SQL DATA
BEGIN

DECLARE agent_name VARCHAR(20);
DECLARE done INT DEFAULT false;
DECLARE agent_cursor CURSOR FOR SELECT agent FROM weekly;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = true;

DROP TABLE IF EXISTS `LeaderboardForStat`;
DROP TABLE IF EXISTS `weekly`;

CREATE TEMPORARY TABLE weekly (
  `agent` VARCHAR(20) NOT NULL,
  `actual_value` INT(11) NOT NULL,
  `regression` BIT(1) NOT NULL,
  `rate` DOUBLE(10,2) NOT NULL,
  `start_timepoint` INT(4) NOT NULL,
  `latest_timepoint` INT(4) NOT NULL,
  `latest_date` DATE NOT NULL,
  `delta_timepoint` INT(4) NOT NULL,
  `start_value` INT(11) NOT NULL,
  `latest_value` INT(11) NOT NULL,
  `delta_value` INT(11) NOT NULL
);

# Insert all agents that have submitted this week
# If no submission exists for the week start, flag by setting regression = 1
INSERT INTO weekly (agent, actual_value, regression)
SELECT agent,
       IF(CAST(MIN(timestamp) AS Date) != start_date, null, value) `actual_value`,
       IF(CAST(MIN(timestamp) AS Date) != start_date, 1, 0) `regression`
  FROM `Data`
 WHERE timestamp >= start_date AND
       timestamp <= DATE_ADD(start_date, INTERVAL 7 DAY) AND
       stat = stat_name
GROUP BY agent;

# For all rows where regression = 1, we will caluclate a value based on the agent's regression slope
UPDATE weekly SET start_timepoint = GetTimepointForAgentAndDate(agent, start_date) WHERE regression = 1;
UPDATE weekly SET rate = GetRateForAgentAndStat(agent, stat_name) WHERE regression = 1;
UPDATE weekly SET start_value = actual_value WHERE regression = 0;

# Delete any agent for which we cannot calculate a weekly value or regression
#DELETE FROM weekly WHERE start_value = 0;

# Find the latest submission from each agent this week
SET @latest_value = 0;
SET @latest_timestamp = 0;
OPEN agent_cursor;

agent_loop: LOOP
    FETCH agent_cursor INTO agent_name;
    IF done THEN LEAVE agent_loop; END IF;

    SELECT value, timestamp
      FROM (SELECT timestamp,
				   value
			  FROM `Data`
			 WHERE timestamp >= start_date AND
				   timestamp <= DATE_ADD(start_date, INTERVAL 7 DAY) AND
				   agent = agent_name AND
				   stat = stat_name
		  ORDER BY timestamp DESC) q1 LIMIT 1 INTO @latest_value, @latest_timestamp;

    UPDATE weekly SET latest_date = DATE(@latest_timestamp) WHERE agent = agent_name;
    UPDATE weekly SET latest_value = @latest_value, latest_timepoint = GetTimepointForAgentAndDate(agent_name, DATE(@latest_timestamp)) WHERE agent = agent_name;
    UPDATE weekly SET delta_timepoint = latest_timepoint - start_timepoint WHERE agent = agent_name AND start_value = 0;
    UPDATE weekly SET start_value = @latest_value - (delta_timepoint * rate) WHERE agent = agent_name AND start_value = 0;
    UPDATE weekly SET delta_value = (@latest_value - start_value) WHERE agent = agent_name;
END LOOP;

# Delete any agent for which we cannot calculate a weekly value or regression
DELETE FROM weekly WHERE delta_value = 0;

CREATE TEMPORARY TABLE LeaderboardForStat
  SELECT @rank := @rank + 1 AS rank, 
         agent, 
         delta_value `value`, 
         DATEDIFF(CURDATE(), latest_date) `age` 
    FROM weekly,
         (SELECT @rank := 0) r 
ORDER BY delta_value DESC;

END;
