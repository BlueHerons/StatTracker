DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetCurrentLevel`(IN `agent_name` VARCHAR(15))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS CurrentLevel;

SET @ap_obtained = 0;
SET @silver_obtained = 0;
SET @gold_obtained = 0;
SET @platinum_obtained = 0;
SET @onyx_obtained = 0;

SELECT MAX(timestamp) INTO @latest_submission
  FROM Data
 WHERE agent = agent_name
 LIMIT 1;

SELECT value INTO @ap_obtained
  FROM Data
 WHERE stat = 'ap' AND
       agent = agent_name AND
       timestamp = @latest_submission
 LIMIT 1;

CALL GetBadgeCount(agent_name);

SELECT `count` FROM BadgeCount WHERE level = 'silver' INTO @silver_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'gold' INTO @gold_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'platinum' INTO @platinum_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'onyx' INTO @onyx_obtained;

CREATE TEMPORARY TABLE CurrentLevel
  SELECT `level` 
    FROM Level 
   WHERE ap_required <= @ap_obtained AND 
         silver_required <= @silver_obtained AND 
         gold_required <= @gold_obtained AND 
         platinum_required <= @platinum_obtained AND 
         onyx_required <= @onyx_obtained 
ORDER BY level DESC 
   LIMIT 1;

END $$
DELIMITER ;
