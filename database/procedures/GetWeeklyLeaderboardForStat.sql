DROP PROCEDURE IF EXISTS `GetWeeklyLeaderboardForStat`;

CREATE PROCEDURE `GetWeeklyLeaderboardForStat`(IN `stat_name` VARCHAR(20), IN `start_date` DATE)
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN

DECLARE agent_name VARCHAR(20);
DECLARE done INT DEFAULT false;
DECLARE agent_cursor CURSOR FOR SELECT agent FROM weekly;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = true;

DROP TABLE IF EXISTS `LeaderboardForStat`;
DROP TABLE IF EXISTS `weekly`;

CREATE TEMPORARY TABLE weekly (
  `agent` VARCHAR(20) NOT NULL,
  `faction` VARCHAR(1) NOT NULL,
  `prior_submission_date` DATE NOT NULL,
  `prior_submission_timepoint` INT(4) NOT NULL,
  `prior_submission_value` INT(11) NOT NULL,
  `start_timepoint` INT(4) NOT NULL,
  `delta_timepoint` INT(4) NOT NULL,
  `rate` DOUBLE(10,2) NOT NULL,
  `actual_value` INT(11) NOT NULL,      
  `start_value` INT(11) NOT NULL,   
  `latest_date` DATE NOT NULL,
  `latest_timepoint` INT(4) NOT NULL,
  `latest_value` INT(11) NOT NULL,
  `delta_value` INT(11) NOT NULL,
  primary key(agent)
);

-- Insert all agents that have submitted since the start date
-- If no submission exists for the week start, flag by setting regression = 1
INSERT INTO weekly (agent, faction)
SELECT d.agent, a.faction
  FROM `Data` d LEFT JOIN `Agent` a ON d.agent = a.agent
 WHERE d.date >= start_date AND
       d.date <= DATE_ADD(start_date, INTERVAL 7 DAY) AND
       d.stat = stat_name
GROUP BY d.agent;

-- Find the latest submission from each agent this week
SET @has_actual_start_value = 0;
SET @actual_start_value = 0;
SET @start_value = 0;
SET @latest_date = 0;
SET @latest_value = 0;
SET @latest_timepoint = 0;
SET @prior_submission_date = 0;
SET @prior_submission_value = 0;
SET @prior_submission_timepoint = 0;
OPEN agent_cursor;

agent_loop: LOOP
    FETCH agent_cursor INTO agent_name;
    
    IF done THEN LEAVE agent_loop; END IF;
    
      SELECT date, value, timepoint INTO
             @prior_submission_date, @prior_submission_value, @prior_submission_timepoint
        FROM Data
       WHERE agent = agent_name AND
             stat = stat_name AND
             date <= start_date
    ORDER BY date DESC LIMIT 1;

	IF done THEN
		DELETE FROM weekly WHERE agent = agent_name;
		SET done = false;
		ITERATE agent_loop;
	END IF;

    UPDATE weekly
       SET prior_submission_date = @prior_submission_date,
           prior_submission_value = @prior_submission_value,
           prior_submission_timepoint = @prior_submission_timepoint
     WHERE agent = agent_name;

    UPDATE weekly 
       SET start_timepoint = GetTimepointForAgentAndDate(agent_name, DATE_SUB(start_date, INTERVAL 1 DAY)),
           rate = GetRateForAgentAndStat(agent_name, stat_name)
     WHERE agent = agent_name;

    -- Get latest submitted value
    SELECT value, date, timepoint INTO @latest_value, @latest_date, @latest_timepoint
      FROM (SELECT date,
                   value,
                   timepoint
              FROM `Data`
             WHERE date >= start_date AND
                   date <= DATE_ADD(start_date, INTERVAL 7 DAY) AND
                   agent = agent_name AND
                   stat = stat_name
             ORDER BY date DESC) q1 LIMIT 1;
    
    UPDATE weekly 
       SET latest_date = @latest_date,
           latest_timepoint = @latest_timepoint,
           latest_value = @latest_value
     WHERE agent = agent_name;
    
    -- start_value = value from start_date - 1, if exists (actual value)
    SELECT count(*)
      FROM `Data`
     WHERE date = DATE_SUB(start_date, INTERVAL 1 DAY) AND
           stat = stat_name AND
           agent = agent_name
     ORDER BY date DESC LIMIT 1 INTO @has_actual_start_value;
    
    IF @has_actual_start_value > 0 THEN
        SELECT value
          FROM `Data`
         WHERE date = DATE_SUB(start_date, INTERVAL 1 DAY) AND
               stat = stat_name AND
               agent = agent_name
         ORDER BY date DESC LIMIT 1 INTO @actual_start_value;
    
        UPDATE weekly 
           SET actual_value = @actual_start_value, 
               start_value = @actual_start_value 
         WHERE agent = agent_name;
    ELSE
        -- start value = last_submission + diff * rate
        UPDATE weekly 
           SET delta_timepoint = ABS(LEAST(prior_submission_timepoint, start_timepoint) - GREATEST(prior_submission_timepoint, start_timepoint)),
               start_value = prior_submission_value + (delta_timepoint * rate), 
               actual_value = -1
         WHERE agent = agent_name;

    END IF;

    IF @prior_submission_date = CAST('0000-00-00' AS Date) THEN
        UPDATE weekly 
           SET delta_timepoint = ABS(LEAST(latest_timepoint, start_timepoint) - GREATEST(latest_timepoint, start_timepoint)),
               start_value = latest_value - (delta_timepoint * rate)
          WHERE agent = agent_name;
    END IF;

    UPDATE weekly
       SET delta_value = latest_value - start_value
     WHERE agent = agent_name;

END LOOP;

# Delete any agent for which we cannot calculate a weekly value or regression
DELETE FROM weekly WHERE delta_value <= 0;

CREATE TEMPORARY TABLE LeaderboardForStat
  SELECT @rank := @rank + 1 AS rank, 
         agent,
         faction,
         delta_value `value`,
         DATEDIFF(CURDATE(), latest_date) `age` 
    FROM weekly,
         (SELECT @rank := 0) r 
ORDER BY delta_value DESC;

END;
