CREATE DEFINER=`admin`@`localhost` FUNCTION `GetRateForAgentAndStat`(`agent_name` VARCHAR(20), `stat_key` VARCHAR(20)) RETURNS double(10,2)
    READS SQL DATA
BEGIN

SET @minDate = CURDATE();

SET @n = 0;
SET @sumX = 0;
SET @sumY = 0;
SET @sumX2 = 0;
SET @sumXY = 0;

SET @y = 0;
SET @slope = 0;
SET @intercept = 0;

SET @rate = 0;

DROP TEMPORARY TABLE IF EXISTS RateDataPoints;

SELECT MIN(CAST(timestamp AS Date)) FROM Data WHERE agent = agent INTO @minDate;

CREATE TEMPORARY TABLE RateDataPoints
SELECT date, (DATEDIFF(date, @minDate) + 1)  `timepoint`, stat_key `stat`, value FROM
(SELECT DATE(q2.timestamp) `date`, stat_key `stat`, value
   FROM (  SELECT MAX(timestamp) `timestamp` 
             FROM (SELECT DATE(timestamp) `date`, 
                          timestamp 
                     FROM Data 
                    WHERE agent = agent_name) q1 
         GROUP BY `date`) q1 
   JOIN (SELECT timestamp, 
                value
           FROM Data 
          WHERE stat = stat_key AND 
                agent = agent_name) q2 
     ON q1.timestamp = q2.timestamp) q3;

SELECT count(*) FROM RateDataPoints WHERE value > 0 INTO @n;
SELECT SUM(timepoint) FROM RateDataPoints WHERE value > 0 INTO @sumX;
SELECT SUM(value) FROM RateDataPoints WHERE value > 0 INTO @sumY;
SELECT SUM(timepoint * timepoint) FROM RateDataPoints WHERE value > 0 INTO @sumX2;
SELECT SUM(value * timepoint) FROM RateDataPoints WHERE value > 0 INTO @sumXY;

SELECT (((@n * @sumXY) - (@sumX * @sumY)) / ((@n * @sumX2) - (@sumX * @sumX))) INTO @slope;
SELECT ((@sumY - (@slope * @sumX)) / @n) INTO @intercept;

RETURN @slope;

END;
