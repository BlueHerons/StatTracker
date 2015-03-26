<?php
namespace BlueHerons\StatTracker;

use Composer\Script\Event;
use Composer\IO\IOInterface;

require(__DIR__ . "/../../../config.php");

class InstallScript {

    public static function postInstall(Event $event) {
        self::createDirectories($event->getIO());
    }

    public static function postUpdate(Event $event) {
        self::createDirectories($event->getIO());
    }

    private static function createDirectories(IOInterface $io) {
        $io->write("Using config file: " . realpath(__DIR__ . "/../../../config.php"));
        if (!file_exists(LOG_DIR)) {
            mkdir(LOG_DIR, 0775);
            $io->write("Created [" . LOG_DIR . "]");
        }
        else {
            $io->write("[" . LOG_DIR . "] already exists");
        }

        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775);
            $io->write("Created [" . UPLOAD_DIR . "]");
        }
        else {
            $io->write("[" . UPLOAD_DIR . "] already exists");
        }

    }
}
?>
