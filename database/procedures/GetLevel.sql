DROP PROCEDURE IF EXISTS `GetLevel`;

CREATE PROCEDURE `GetLevel`(IN `agent_name` VARCHAR(15), IN `submission_date` DATE)
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS _Level;

SET @ap_obtained = 0;
SET @silver_obtained = 0;
SET @gold_obtained = 0;
SET @platinum_obtained = 0;
SET @onyx_obtained = 0;

SELECT count(*) > 0 INTO @has_submission
  FROM Data
 WHERE agent = agent_name AND
       date = submission_date;

IF (~@has_submission) THEN
  SELECT MAX(date) INTO submission_date
    FROM Data
   WHERE agent = agent_name;
END IF;

SELECT value INTO @ap_obtained
  FROM Data
 WHERE stat = 'ap' AND
       agent = agent_name AND
       date = submission_date
 LIMIT 1;

CALL GetBadgeCount(agent_name, submission_date);

SELECT `count` FROM BadgeCount WHERE level = 'silver' INTO @silver_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'gold' INTO @gold_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'platinum' INTO @platinum_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'onyx' INTO @onyx_obtained;

CREATE TEMPORARY TABLE _Level
  SELECT `level` 
    FROM Level 
   WHERE ap_required <= @ap_obtained AND 
         silver_required <= @silver_obtained AND 
         gold_required <= @gold_obtained AND 
         platinum_required <= @platinum_obtained AND 
         onyx_required <= @onyx_obtained 
ORDER BY level DESC
   LIMIT 1;

END;
