CREATE DEFINER=`jluetke`@`localhost` PROCEDURE `DropLeaderboard`()
    READS SQL DATA
BEGIN
    DROP TABLE IF EXISTS `LeaderboardForStat`;
END
