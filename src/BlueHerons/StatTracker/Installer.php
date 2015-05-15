<?php
namespace BlueHerons\StatTracker;

use Exception;
use Composer\Script\Event;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

class Installer {

    public static function postUpdate(Event $event) {
        self::install($event);
    }

    public static function postInstall(Event $event) {
        self::install($event);
    }

    public static function install(Event $event) {
        self::init();
        self::createDirectories($event);
        self::createDatabaseTables($event);
    }

    private static function init() {
        if (!file_exists("config.php")) {
            throw new Exception("config.php does not exist. Please follow the instructions in config.php.sample");
        }

        require("config.php");

        self::assertDefined("LOG_DIR");
        self::assertDefined("UPLOAD_DIR");

        self::assertDefined("DATABASE_HOST");
        self::assertDefined("DATABASE_NAME");
        self::assertDefined("DATABASE_CHARSET");
        self::assertDefined("DATABASE_USER");
        self::assertDefined("DATABASE_PASS");
    }

    private static function createDirectories(Event $event) {
        $io = $event->getIO();

        self::createDirectory(LOG_DIR, $io);
        self::createDirectory(UPLOAD_DIR, $io);
    }

    private static function createDatabaseTables(Event $event) {
        // If any order needs to be enforced, specify it here
        $tables = glob("./database/tables/*.sql");
        $table_order = array("Stats", "Agent", "Dates", "Data");

        foreach($table_order as $table) {
            $file = sprintf("./database/tables/%s.sql", $table);
            $tables = array_diff($tables, array($file));
            self::executeSQL($file, $event->getIO());
        }

        foreach($tables as $file) {
            self::executeSQL($file, $event->getIO());
        }
    }

    private static function assertDefined($const) {
        if (!defined("LOG_DIR"))
            throw new Exception("$const is not defined. Please edit config.php");
    }

    private static function createDirectory($path, IOInterface $io) {
        $io->write("Creating $path...");        

        if (file_exists($path)) {
            return true;
        }
        else {
            $r = mkdir($path, 01775, true);
            $r = $r == 1 ? chmod($path, 01775) : $r;
            return $r;
        }
    }

    private static function executeSQL($file, $io) {
        $io->write("Executing $file...");
        $db = StatTracker::db();
        $sql = file_get_contents($file);
        $r = $db->exec($sql);
        $io->write("$r rows affected");
        return $r;
    }   
}
?>
