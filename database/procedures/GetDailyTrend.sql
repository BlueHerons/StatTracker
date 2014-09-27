DELIMITER $$
CREATE DEFINER=`jluetke`@`localhost` PROCEDURE `GetDailyTrend`(IN `agent_name` VARCHAR(15), IN `stat_key` VARCHAR(20), IN `start_date` DATE, IN `end_date` DATE)
    NO SQL
BEGIN

SELECT GetRateForAgentAndStatBetweenDates(agent_name, stat_key, start_date, end_date) INTO @rate;
SELECT GetInterceptForAgentAndStatBetweenDates(agent_name, stat_key, start_date, end_date) INTO @intercept;
SELECT GetValueForAgentAndStatOnDate(agent_name, stat_key, DATE_SUB(start_date, INTERVAL 1 DAY)) INTO @start;

CREATE TEMPORARY TABLE DailyTrend AS
SELECT date,
       IF (date > NOW(), NULL, COALESCE(value - @lastValue, 0)) `value`,
       @rate `target`,
       @lastValue := value
  FROM (SELECT @lastValue := @start) vars,
    (SELECT dl.date `date`, 
           @start `start`,
           COALESCE(q.value, ((GetTimepointForAgentAndDate(agent_name, dl.date) * @rate) + @intercept)) `value`
      FROM Dates dl
 LEFT JOIN (SELECT date, value
              FROM Data 
             WHERE stat = stat_key AND
                   agent = agent_name AND
                   date >= start_date AND 
                   date <= end_date) q
        ON q.date = dl.date
     WHERE dl.date >= start_date AND 
           dl.date <= end_date) q1;

END $$
DELIMITER ;
