DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetBadgePrediction`(IN `stat_key` VARCHAR(20))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS BadgePrediction;

SET @n = 0;
SET @sumX = 0;
SET @sumY = 0;
SET @sumX2 = 0;
SET @sumXY = 0;

SET @y = 0;
SET @slope = 0;
SET @intercept = 0;

SET @max = 0;
SET @stat = '';
SET @unit = '';
SET @badge = '';
SET @current = '';
SET @next = '';
SET @remaining = 0;
SET @days = 0;
SET @rate = 0;

SELECT count(*) FROM RawStatsForAgent WHERE stat = stat_key AND value > 0 INTO @n;
SELECT SUM(timepoint) FROM RawStatsForAgent WHERE stat = stat_key AND value > 0 INTO @sumX;
SELECT SUM(value) FROM RawStatsForAgent WHERE stat = stat_key AND value > 0 INTO @sumY;

SELECT SUM(timepoint * timepoint) FROM RawStatsForAgent WHERE stat = stat_key INTO @sumX2;
SELECT SUM(value * timepoint) FROM RawStatsForAgent WHERE stat = stat_key AND value > 0 INTO @sumXY;
SELECT (((@n * @sumXY) - (@sumX * @sumY)) / ((@n * @sumX2) - (@sumX * @sumX))) INTO @slope;
SELECT ((@sumY - (@slope * @sumX)) / @n) INTO @intercept;
SELECT @slope INTO @rate;

SELECT value FROM RawStatsForAgent WHERE stat = stat_key ORDER BY timestamp DESC LIMIT 1 INTO @max;

SELECT name FROM Stats WHERE stat = stat_key INTO @stat;
SELECT unit FROM Stats WHERE stat = stat_key INTO @unit;
SELECT name FROM Badges WHERE stat = stat_key LIMIT 1 INTO @badge;
SELECT level FROM Badges WHERE stat = stat_key AND amount_required <= @max ORDER BY amount_required DESC LIMIT 1 INTO @current;
SELECT level FROM Badges WHERE stat = stat_key AND amount_required > @max ORDER BY amount_required ASC LIMIT 1 INTO @next;
SELECT amount_required - @max FROM Badges WHERE stat = stat_key AND level = @next INTO @remaining;

SELECT amount_required FROM Badges WHERE stat = stat_key AND level = @next INTO @y;
SELECT @remaining / @slope INTO @days;

IF stat_key = 'oldest_portal' THEN
	SELECT @remaining INTO @days;
END IF;

IF @days < 0 THEN
	SELECT 0 INTO @days;
END IF;

CREATE TEMPORARY TABLE BadgePrediction
	SELECT stat_key `Stat`, 
	       @stat `Name`,
           @unit `unit`,
	       @badge `Badge`,
	       @current `Current`,
	       @next `Next`,
	       ROUND((@max / @y), 2) `progress`,
	       @max `obtained`,
	       @remaining `Remaining`,
	       ROUND(@days, 1) `Days`,
	       ROUND(@rate, 0) `Rate`,
	       ROUND(@intercept) `intercept`,
	       ROUND(@slope, 2) `slope`;

END $$
DELIMITER ;
