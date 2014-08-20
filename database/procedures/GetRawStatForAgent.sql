DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetRawStatForAgent`(IN `agent` VARCHAR(15), IN `statName` VARCHAR(20))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS RawStatForAgent;
DROP TABLE IF EXISTS RawStatsForAgent;

CALL GetRawStatsForAgent(agent);

CREATE TEMPORARY TABLE RawStatForAgent
	SELECT * 
      FROM RawStatsForAgent
	 WHERE stat = statName;

END $$
DELIMITER ;
