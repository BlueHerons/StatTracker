CREATE DEFINER=`jluetke`@`localhost` PROCEDURE `GetLeaderboardForStat`(IN `stat_name` VARCHAR(20))
    READS SQL DATA
BEGIN
CREATE TEMPORARY TABLE LeaderboardForStat
  SELECT `agent`,
         `value`,
          MIN(`age`) `age`
    FROM (SELECT d.`agent`,
                 d.`value`,
                 DATEDIFF(CURDATE(), CAST(d.`timestamp` AS DATE)) `age`
            FROM Data d 
                 INNER JOIN (SELECT `agent`,
                                    MAX(`value`) `value`
                               FROM `Data`
                              WHERE `stat` = stat_name AND
                                    `value` > 0
                           GROUP BY `agent`
                           ORDER BY `value` DESC) q1
                 ON d.`agent` = q1.`agent` AND
                    d.`value` = q1.`value`) q2
GROUP BY `agent`
ORDER BY `value` DESC;

END
