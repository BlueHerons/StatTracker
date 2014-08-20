DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetGraphDataForStat`(IN `statName` VARCHAR(20))
    READS SQL DATA
BEGIN

SET @minDate = CURDATE();
SET @intercept = 0;
SET @slope = 0;
SET @agent = '';

SELECT DISTINCT agent FROM RawStatsForAgent LIMIT 1 INTO @agent;

CALL GetRawStatForAgent(@agent, statName);
CALL GetBadgePrediction(statName);

SELECT MIN(`date`) FROM RawStatForAgent INTO @minDate;
SELECT MAX(`date`) FROM RawStatForAgent INTO @maxDate;

SELECT slope FROM BadgePrediction INTO @slope;
SELECT intercept FROM BadgePrediction INTO @intercept;

CREATE TEMPORARY TABLE GraphDataForStat
SELECT * FROM
   (SELECT dl.date `Date`,
	       r.value `Actual`,
           CEIL(@intercept + (@slope * (DATEDIFF(dl.date, @minDate) + 1))) `Regression`
      FROM RawStatForAgent r 
RIGHT JOIN Dates dl 
           ON dl.date = r.date
WHERE dl.date >= @minDate AND
      dl.date <= @maxDate 
ORDER BY Date DESC LIMIT 30) q
ORDER BY Date ASC;

END $$
DELIMITER ;
