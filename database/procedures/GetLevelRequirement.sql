DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetLevelRequirement`()
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS LevelRequirement;

SET @current_level = 0;
SET @next_level = 0;

SET @ap_obtained = 0;
SET @silver_obtained = 0;
SET @gold_obtained = 0;
SET @platinum_obtained = 0;
SET @onyx_obtained = 0;

SET @ap_required = 0;
SET @silver_required = 0;
SET @gold_required = 0;
SET @platinum_required = 0;
SET @onyx_required = 0;

  SELECT value 
    FROM RawStatsForAgent
   WHERE stat = 'ap' 
ORDER BY timestamp DESC 
   LIMIT 1 INTO @ap_obtained;

CALL GetBadgeCount();

SELECT `count` FROM BadgeCount WHERE level = 'silver' INTO @silver_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'gold' INTO @gold_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'platinum' INTO @platinum_obtained;
SELECT `count` FROM BadgeCount WHERE level = 'onyx' INTO @onyx_obtained;

CALL GetCurrentLevel();

SELECT level FROM CurrentLevel INTO @current_level;
SELECT @current_level + 1 INTO @next_level;

CREATE TEMPORARY TABLE LevelRequirement
SELECT level, 
       IF (ap_required - @ap_obtained < 0, 0, ap_required - @ap_obtained) 'ap_remaining', 
       IF (silver_required - @silver_obtained < 0, 0, silver_required - @silver_obtained) 'silver_remaining', 
       IF (gold_required - @gold_obtained < 0, 0, gold_required - @gold_obtained) 'gold_remaining', 
       IF (platinum_required - @platinum_obtained < 0, 0, platinum_required - @platinum_obtained) 'platinum_remaining', 
       IF (onyx_required - @onyx_obtained < 0, 0, onyx_required - @onyx_obtained) 'onyx_remaining' 
  FROM Level 
 WHERE level = @next_level;

END $$
DELIMITER ;
