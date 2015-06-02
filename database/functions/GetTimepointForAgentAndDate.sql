DELIMITER $$

DROP FUNCTION IF EXISTS `GetTimepointForAgentAndDate` $$

CREATE FUNCTION `GetTimepointForAgentAndDate`(`agent_name` VARCHAR(15), `start_date` DATE) RETURNS int(4)
    READS SQL DATA
BEGIN

SELECT COALESCE(
    (SELECT timepoint FROM Data WHERE agent = agent_name and date = start_date LIMIT 1),
    (SELECT DATEDIFF(start_date, MIN(date)) + 1 FROM Data WHERE agent = agent_name)
) INTO @timepoint;

RETURN @timepoint;

END $$

DELIMITER ;
