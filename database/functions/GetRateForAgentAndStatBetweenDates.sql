DELIMITER $$

DROP FUNCTION IF EXISTS `GetRateForAgentAndStatBetweenDates` $$

CREATE DEFINER=`admin`@`localhost` FUNCTION `GetRateForAgentAndStatBetweenDates`(`agent_name` VARCHAR(15), `stat_key` VARCHAR(20), `start_date` DATE, `end_date` DATE) RETURNS int(10)
    READS SQL DATA
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

RETURN @slope;

END $$

DELIMITER ;
