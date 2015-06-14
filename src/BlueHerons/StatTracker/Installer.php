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
        self::createDatabaseRoutines($event);
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

        self::assertDefined("AUTH_PROVIDER");
        if (constant("AUTH_PROVIDER") == "GooglePlusProvider") {
            self::assertDefined("GOOGLE_CLIENT_ID");
            self::assertDefined("GOOGLE_CLIENT_SECRET");
        }
        else if (constant("AUTH_PROVIDER") == "WordpressProvider") {
            self::assertDefined("WORDPRESS_ROOT_PATH");
        }
    }

    private static function createDirectories(Event $event) {
        self::createDirectory(LOG_DIR, $event->getIO());
        self::createDirectory(UPLOAD_DIR, $event->getIO());
    }

    private static function createDatabaseTables(Event $event) {
        // If any order needs to be enforced, specify it here
        $tables = glob("./database/tables/*.sql");
        $table_order = array("Stats", "Agent", "Dates", "Data");

        foreach($table_order as $table) {
            $file = sprintf("./database/tables/%s.sql", $table);
            $tables = array_diff($tables, array($file));
            $event->getIO()->write(sprintf("Creating table %s...", pathinfo($file, PATHINFO_FILENAME)));
            self::executeSQL($file);
        }

        foreach($tables as $file) {
            $event->getIO()->write(sprintf("Creating table %s...", pathinfo($file, PATHINFO_FILENAME)));
            self::executeSQL($file);
        }
    }

    private static function createDatabaseRoutines(Event $event) {
        $functions = glob("./database/functions/*.sql");
        $procedures = glob("./database/procedures/*.sql");
        $routines = array_merge($functions, $procedures);

        foreach ($routines as $file) {
            $event->getIO()->write(sprintf("Creating function %s...", pathinfo($file, PATHINFO_FILENAME)));
            self::executeSQL($file);
        }
    }

    private static function assertDefined($const) {
        if (!defined($const) || empty(constant($const)))
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

    private static function executeSQL($file) {
        $db = StatTracker::db();
        $sql = file_get_contents($file);
        $r = $db->exec($sql);
        return $r;
    }   
}
?>
