CREATE DEFINER=`SRStats`@`localhost` PROCEDURE `GetBadgePrediction`(IN `stat_key` VARCHAR(20))
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

SET @silver_remaining = 0;
SET @gold_remaining = 0;
SET @platinum_remaining = 0;
SET @onyx_remaining = 0;

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

SELECT MAX(value) FROM RawStatsForAgent WHERE stat = stat_key INTO @max;

SELECT name FROM Stats WHERE stat = stat_key INTO @stat;
SELECT unit FROM Stats WHERE stat = stat_key INTO @unit;
SELECT name FROM Badges WHERE stat = stat_key LIMIT 1 INTO @badge;
SELECT level FROM Badges WHERE stat = stat_key AND amount_required <= @max ORDER BY amount_required DESC LIMIT 1 INTO @current;
SELECT level FROM Badges WHERE stat = stat_key AND amount_required > @max ORDER BY amount_required ASC LIMIT 1 INTO @next;
SELECT amount_required - @max FROM Badges WHERE stat = stat_key AND level = @next INTO @remaining;

SELECT amount_required FROM Badges WHERE stat = stat_key AND level = @next INTO @y;
SELECT @remaining / @slope INTO @days;

IF stat_key = 'ap' THEN
	SET @ap_required = 0;
	SET @silver_obtained = 0;
	SET @gold_obtained = 0;
	SET @platinum_obtained = 0;
	SET @onyx_obtained = 0;

	CALL GetBadgeCount();

	SELECT `count` FROM BadgeCount WHERE level = 'silver' INTO @silver_obtained;
	SELECT `count` FROM BadgeCount WHERE level = 'gold' INTO @gold_obtained;
	SELECT `count` FROM BadgeCount WHERE level = 'platinum' INTO @platinum_obtained;
	SELECT `count` FROM BadgeCount WHERE level = 'onyx' INTO @onyx_obtained;

	  SELECT `level` 
        FROM Level 
       WHERE ap_required <= @max AND 
             silver_required <= @silver_obtained AND 
             gold_required <= @gold_obtained AND 
             platinum_required <= @platinum_obtained AND 
             onyx_required <= @onyx_obtained 
    ORDER BY level DESC 
       LIMIT 1 INTO @current;

	SELECT `level` 
      FROM Level 
     WHERE level = @current + 1 INTO @next;

	SELECT `ap_required` 
      FROM Level 
     WHERE level = @current INTO @ap_required;

	SELECT silver_required - @silver_obtained FROM Level WHERE level = @next INTO @silver_remaining;
	SELECT gold_required - @gold_obtained FROM Level WHERE level = @next INTO @gold_remaining;
	SELECT platinum_required - @platinum_obtained FROM Level WHERE level = @next INTO @platinum_remaining;
	SELECT onyx_required - @onyx_obtained FROM Level WHERE level = @next INTO @onyx_remaining;

	SELECT IF(@silver_remaining < 0, 0, @silver_remaining) INTO @silver_remaining;
	SELECT IF(@gold_remaining < 0, 0, @gold_remaining) INTO @gold_remaining;
	SELECT IF(@platinum_remaining < 0, 0, @platinum_remaining) INTO @platinum_remaining;
	SELECT IF(@onyx_remaining < 0, 0, @onyx_remaining) INTO @onyx_remaining;
	
	IF @ap_required <= @max THEN
		SET @remaining = 0;
		SET @y = @max;
		SET @days = 0;
	END IF;

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
	       @remaining `Remaining`,
	       @silver_remaining `silver_remaining`,
	       @gold_remaining `gold_remaining`,
	       @platinum_remaining `platinum_remaining`,
	       @onyx_remaining `onyx_remaining`,
	       ROUND(@days, 1) `Days`,
	       ROUND(@rate, 1) `Rate`,
	       ROUND(@intercept) `intercept`,
	       ROUND(@slope, 2) `slope`;

END
