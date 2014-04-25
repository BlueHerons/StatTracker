DROP PROCEDURE `GetRawStatsForAgent`;
CREATE DEFINER=`jluetke`@`localhost` PROCEDURE `GetRawStatsForAgent`(IN `agentName` VARCHAR(15)) NOT DETERMINISTIC READS SQL DATA SQL SECURITY DEFINER BEGIN

DROP TABLE IF EXISTS RawStatsForAgent;

SET @minDate = CURDATE();

CREATE TEMPORARY TABLE TRawStatsForAgent
	SELECT agent,
	       CAST(timestamp as DATE) date,
	       stat,
	       MAX(value) value
	  FROM Data 
	 WHERE agent = agentName 
  GROUP BY date, stat 
  ORDER BY stat, date; 

SELECT MIN(date) FROM TRawStatsForAgent INTO @minDate;

CREATE TEMPORARY TABLE RawStatsForAgent
	SELECT agent,
	       (DATEDIFF(date, @minDate) + 1) timepoint,
	       date,
	       stat,
	       MAX(value) value
	  FROM TRawStatsForAgent 
	 WHERE agent = agentName 
  GROUP BY date, stat 
  ORDER BY stat, date; 

DROP TABLE TRawStatsForAgent;
END
