<?php
use BlueHerons\StatTracker\StatTracker;
use BlueHerons\StatTracker\OCR;

class OCRTest extends PHPUnit_Framework_TestCase {

    public function testConvertToPBM() {
        foreach (glob(FIXTURE_DIR . "/profile_screenshot_*.png") as $screenshot) {
            $file = __DIR__ . "/test_" . basename($screenshot);
            copy($screenshot, $file);

            $this->assertFileExists($file, sprintf("%s does not exist!", $file));

            $newFile = OCR::convertToPBM($file);

            $this->assertStringEndsWith("pbm", $newFile, sprintf("[%s] %s is not a PBM file", basename($screenshot), $newFile));
            $this->assertFileEquals(FIXTURE_DIR . "/" . pathinfo($screenshot, PATHINFO_FILENAME) . ".pbm",
                                    $newFile, 
                                    sprintf("[%s] %s does not have the expected contents", basename($screenshot), basename($newFile)));
            unlink($newFile);
        }
    }

    /**
     * @depends testConvertToPBM
     */
    public function testExecuteOCR() {
        foreach (glob(FIXTURE_DIR . "/profile_screenshot_*.pbm") as $screenshot) {
            $file = __DIR__ . "/test_" . basename($screenshot);
            copy($screenshot, $file);

            $this->assertFileExists($file, sprintf("%s does not exist!", $file));

            $lines = OCR::executeOCR($file);
            
            $this->assertArrayHasKey(28, $lines);
            //file_put_contents(FIXTURE_DIR . "/" . pathinfo($screenshot, PATHINFO_FILENAME) . ".txt", implode(PHP_EOL, $lines));
        }
    }

    /**
     * @depends testExecuteOCR
     */
    public function testProcessAgentData() {
        foreach (glob(FIXTURE_DIR . "/profile_screenshot_*.txt") as $file) {
            $lines = file($file);
            
            $stats = unserialize(file_get_contents(FIXTURE_DIR . "/stats.serialized"));
            $data = json_encode(OCR::processAgentData($lines, $stats));
            //file_put_contents(FIXTURE_DIR . "/" . pathinfo($file, PATHINFO_FILENAME) . ".json", json_encode($data));
            $fixture = FIXTURE_DIR . "/" . pathinfo($file, PATHINFO_FILENAME) . ".json";
            $this->assertJsonStringEqualsJsonFile($fixture, $data, $fixture);
        }
    }
}
?>
