DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetGraphForStat`(IN `agent_name` VARCHAR(15), IN `stat_key` VARCHAR(20))
    READS SQL DATA
BEGIN

SELECT value INTO @rate
  FROM Cache 
 WHERE agent = agent_name AND 
       stat = stat_key AND
       `key` = 'rate';

SELECT value INTO @intercept
  FROM Cache 
 WHERE agent = agent_name AND 
       stat = stat_key AND
       `key` = 'intercept';

SELECT NOW() INTO @maxDate;
SELECT DATE_SUB(@maxDate, INTERVAL 30 DAY) INTO @minDate;

CREATE TEMPORARY TABLE GraphDataForStat
    SELECT dl.date `Date`, 
           q.value `Actual`,
           ((GetTimepointForAgentAndDate(agent_name, dl.date) * @rate) + @intercept) `Regression`
      FROM Dates dl
 LEFT JOIN (SELECT date, value 
              FROM Data 
             WHERE stat = stat_key AND
                   agent = agent_name AND
                   date >= @minDate AND 
                   date <= @maxDate) q
        ON q.date = dl.date
     WHERE dl.date >= @minDate AND 
           dl.date <= @maxDate;

END $$
DELIMITER ;
