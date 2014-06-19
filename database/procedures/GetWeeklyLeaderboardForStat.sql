CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetWeeklyLeaderboardForStat`(IN `stat_name` VARCHAR(20), IN `start_date` TIMESTAMP)
    READS SQL DATA
BEGIN

DECLARE agent_name VARCHAR(20);
DECLARE done INT DEFAULT false;
DECLARE agent_cursor CURSOR FOR SELECT agent FROM weekly;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = true;

SET @debug_enabled = TRUE;

DROP TABLE IF EXISTS `LeaderboardForStat`;
DROP TABLE IF EXISTS `weekly`;

CREATE TEMPORARY TABLE weekly (
  `agent` VARCHAR(20) NOT NULL,
  `latest_value` INT(11) NOT NULL,
  `latest_date` DATE NOT NULL,
  `latest_timepoint` INT(4) NOT NULL,
  `actual_value` INT(11) NOT NULL,
  `start_timepoint` INT(4) NOT NULL,
  `delta_timepoint` INT(4) NOT NULL,
  `rate` DOUBLE(10,2) NOT NULL,
  `start_value` INT(11) NOT NULL,  
  `delta_value` INT(11) NOT NULL,
  primary key(agent)
);

# Insert all agents that have submitted since the start date
# If no submission exists for the week start, flag by setting regression = 1
INSERT INTO weekly (agent)
SELECT agent
  FROM `Data`
 WHERE timestamp >= start_date AND
       timestamp <= DATE_ADD(start_date, INTERVAL 7 DAY) AND
       stat = stat_name
GROUP BY agent;

# For all rows where regression = 1, we will calculate a value based on the agent's regression slope
#UPDATE weekly SET start_timepoint = GetTimepointForAgentAndDate(agent, DATE_SUB(start_date, INTERVAL 1 DAY));
#UPDATE weekly SET rate = GetRateForAgentAndStat(agent, stat_name);

# Find the latest submission from each agent this week
SET @actual_value = 0;
SET @start_value = 0;
SET @latest_value = 0;
SET @latest_timestamp = 0;
OPEN agent_cursor;

agent_loop: LOOP
    FETCH agent_cursor INTO agent_name;

	#CALL debug(@debug_enabled, (SELECT agent_name));
	#CALL debug(@debug_enabled, (SELECT done));

    IF done THEN LEAVE agent_loop; END IF;

	UPDATE weekly SET start_timepoint = GetTimepointForAgentAndDate(agent_name, DATE_SUB(start_date, INTERVAL 1 DAY)) WHERE agent = agent_name;
	UPDATE weekly SET rate = GetRateForAgentAndStat(agent_name, stat_name) WHERE agent = agent_name;

	# Get latest submitted value
    SELECT value, timestamp
      FROM (SELECT timestamp,
				   value
			  FROM `Data`
			 WHERE timestamp >= start_date AND
				   timestamp <= DATE_ADD(start_date, INTERVAL 7 DAY) AND
				   agent = agent_name AND
				   stat = stat_name
		  ORDER BY timestamp DESC) q1 LIMIT 1 INTO @latest_value, @latest_timestamp;

	UPDATE weekly 
	   SET latest_date = DATE(@latest_timestamp),
	       latest_timepoint = GetTimepointForAgentAndDate(agent_name, DATE(@latest_timestamp)),
	       latest_value = @latest_value
	 WHERE agent = agent_name;

	# start_value = value from start_date - 1, if exists (actual value)
	  SELECT count(*)
	    FROM `Data`
	   WHERE DATE(timestamp) = DATE_SUB(start_date, INTERVAL 1 DAY) AND
	         stat = stat_name AND
	         agent = agent_name
	ORDER BY timestamp DESC LIMIT 1 INTO @actual_value;

	IF @actual_value > 0 THEN
		  SELECT value
			FROM `Data`
		   WHERE DATE(timestamp) = DATE_SUB(start_date, INTERVAL 1 DAY) AND
				 stat = stat_name AND
				 agent = agent_name
		ORDER BY timestamp DESC LIMIT 1 INTO @actual_value;

		UPDATE weekly 
		   SET actual_value = @actual_value, 
		       start_value = @actual_value 
		 WHERE agent = agent_name;
	ELSE
		# start value = regression on start_date - 1, if above false
		UPDATE weekly 
		   SET delta_timepoint = latest_timepoint - start_timepoint,
		       start_value = latest_value - (delta_timepoint * rate), 
		       actual_value = -1
		 WHERE agent = agent_name;
	END IF;

	UPDATE weekly
	   SET delta_value = latest_value - start_value
	 WHERE agent = agent_name;

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

END
