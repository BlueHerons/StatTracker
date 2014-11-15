DELIMITER $$

DROP FUNCTION IF EXISTS `GetInterceptForAgentAndStatBetweenDates` $$

CREATE DEFINER=`admin`@`localhost` FUNCTION `GetInterceptForAgentAndStatBetweenDates`(`agent_name` VARCHAR(15), `stat_key` VARCHAR(20), `start_date` DATE, `end_date` DATE) RETURNS int(10)
    NO SQL
BEGIN

SELECT count(*),
       SUM(timepoint),
       SUM(value),
       SUM(timepoint * timepoint),
       SUM(value * timepoint)
  INTO @n,
       @sumX,
       @sumY,
       @sumX2,
       @sumXY
  FROM Data 
 WHERE agent = agent_name AND
       stat = stat_key AND
       value > 0 AND
       date >= start_date AND
       date <= end_date;

SELECT (((@n * @sumXY) - (@sumX * @sumY)) / ((@n * @sumX2) - (@sumX * @sumX))) INTO @slope;
SELECT ((@sumY - (@slope * @sumX)) / @n) INTO @intercept;

RETURN @intercept;

END $$

DELIMITER ;
