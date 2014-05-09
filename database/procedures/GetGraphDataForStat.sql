CREATE DEFINER=`SRStats`@`localhost` PROCEDURE `GetGraphDataForStat`(IN `statName` VARCHAR(20))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS GraphDataForStat;

CALL GetBadgePrediction(statName);

SET @intercept = 0;
SET @slope = 0;

SELECT intercept FROM BadgePrediction INTO @intercept;
SELECT slope FROM BadgePrediction INTO @slope;

CREATE TEMPORARY TABLE GraphDataForStat
    SELECT * 
      FROM (SELECT date `Date`, 
                   value `Actual`,
                   CEIL(@intercept + (@slope * timepoint)) `Regression`
              FROM `RawStatsForAgent`
             WHERE stat = statName) t1;

END
