CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetRawStatsForAgent`(IN `agentName` VARCHAR(15))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS RawStatsForAgent;

SET @minDate = CURDATE();

CREATE TEMPORARY TABLE TRawStatsForAgent
  SELECT agent, 
         DATE(usable_timestamp) `date`, 
         timestamp, 
         stat, 
         value
    FROM Data
   INNER JOIN (  SELECT MAX(timestamp)`usable_timestamp`
                   FROM Data 
	              WHERE agent = agentName 
               GROUP BY DATE(timestamp)) q0
         ON q0.`usable_timestamp` = Data.timestamp;

SELECT MIN(date) FROM TRawStatsForAgent INTO @minDate;

CREATE TEMPORARY TABLE RawStatsForAgent
	SELECT agent,
	       (DATEDIFF(date, @minDate) + 1) timepoint,
	       date,
           timestamp,
	       stat,
	       MAX(value) value
	  FROM TRawStatsForAgent 
	 WHERE agent = agentName 
  GROUP BY date, stat 
  ORDER BY stat, date; 

DROP TABLE TRawStatsForAgent;
END
