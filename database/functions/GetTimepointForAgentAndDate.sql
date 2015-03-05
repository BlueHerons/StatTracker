DELIMITER $$

DROP FUNCTION IF EXISTS `GetTimepointForAgentAndDate` $$

CREATE FUNCTION `GetTimepointForAgentAndDate`(`agent_name` VARCHAR(20), `start_date` DATE) RETURNS int(4)
    READS SQL DATA
BEGIN

SET @first_submission = CURDATE();
SET @timepoint = 0;


SELECT MIN(date) FROM Data WHERE agent = agent_name INTO @first_submission;
SELECT DATEDIFF(start_date, @first_submission) + 1 INTO @timepoint;

RETURN @timepoint;

END $$

DELIMITER ;
