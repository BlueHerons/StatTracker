DROP PROCEDURE `GetBadgePrediction`;
CREATE DEFINER=`jluetke`@`localhost` PROCEDURE `GetBadgePrediction`(IN `stat_key` VARCHAR(20)) NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER BEGIN

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

SELECT MAX(value) FROM RawStatsForAgent WHERE stat = stat_key INTO @max;

SELECT name FROM Stats WHERE stat = stat_key INTO @stat;
SELECT name FROM Badges WHERE stat = stat_key LIMIT 1 INTO @badge;
SELECT level FROM Badges WHERE stat = stat_key AND amount_required <= @max ORDER BY amount_required DESC LIMIT 1 INTO @current;
SELECT level FROM Badges WHERE stat = stat_key AND amount_required > @max ORDER BY amount_required ASC LIMIT 1 INTO @next;
SELECT amount_required - @max FROM Badges WHERE stat = stat_key AND level = @next INTO @remaining;

SELECT amount_required FROM Badges WHERE stat = stat_key AND level = @next INTO @y;
SELECT ((@y - @intercept) / @slope) INTO @days;
SELECT (@remaining / @days) INTO @rate;


CREATE TEMPORARY TABLE BadgePrediction
	SELECT stat_key `Stat`, 
	       @stat `Name`,
	       @badge `Badge`,
	       @current `Current`,
	       @next `Next`,
	       @remaining `Remaining`,
	       ROUND(@days, 1) `Days`,
	       ROUND(@rate, 1) `Rate`;

END
