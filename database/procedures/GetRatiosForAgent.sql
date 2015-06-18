DROP PROCEDURE IF EXISTS `GetRatiosForAgent`;

CREATE PROCEDURE `GetRatiosForAgent`(IN `agent_name` VARCHAR(15))
    READS SQL DATA
BEGIN

BEGIN

DECLARE stat_1 VARCHAR(20);
DECLARE stat_2 VARCHAR(20);
DECLARE factor DECIMAL(4,1);

DECLARE done INT DEFAULT false;
DECLARE row_cursor CURSOR FOR SELECT * FROM Ratio;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = true;

CREATE TEMPORARY TABLE RatiosForAgent (
  `stat_1` VARCHAR(20),
  `stat_2` VARCHAR(20),
  `ratio` DECIMAL(10,2),
  `factor` DECIMAL(4,1),
  `stat_1_name` VARCHAR(50),
  `stat_1_nickname` VARCHAR(50),
  `stat_1_unit` VARCHAR(20),
  `stat_2_name` VARCHAR(50),
  `stat_2_nickname` VARCHAR(50),
  `stat_2_unit` VARCHAR(20),
  `badge_1` varchar(25),
  `badge_1_level` varchar(25),
  `badge_2` varchar(25),
  `badge_2_level` varchar(25)
);

SELECT MAX(date) INTO @latest_submission
  FROM Data
 WHERE agent = agent_name;

OPEN row_cursor;

ratio_loop: LOOP
    FETCH FROM row_cursor INTO stat_1, stat_2, factor;

    IF done THEN LEAVE ratio_loop; END IF;

SELECT value INTO @stat1
      FROM Data
     WHERE agent = agent_name AND
           date = @latest_submission AND
           stat = stat_1;

    SELECT value INTO @stat2
      FROM Data
     WHERE agent = agent_name AND
           date = @latest_submission AND
           stat = stat_2;

    INSERT INTO RatiosForAgent VALUES(
                stat_1, 
                stat_2, 
                @stat1 / @stat2,
                factor,
                (SELECT name FROM Stats WHERE stat = stat_1),
                (SELECT nickname FROM Stats WHERE stat = stat_1),
                (SELECT unit FROM Stats WHERE stat = stat_1),
                (SELECT name FROM Stats WHERE stat = stat_2),
                (SELECT nickname FROM Stats WHERE stat = stat_2),
                (SELECT unit FROM Stats WHERE stat = stat_2),
                (SELECT name FROM Badges WHERE stat = stat_1 LIMIT 1),
                (SELECT level FROM Badges WHERE stat = stat_1 and @stat1 >= amount_required ORDER BY amount_required DESC LIMIT 1),
                (SELECT name FROM Badges WHERE stat = stat_2 LIMIT 1),
                (SELECT level FROM Badges WHERE stat = stat_2 and @stat2 >= amount_required ORDER BY amount_required DESC LIMIT 1));
END LOOP;

END;
