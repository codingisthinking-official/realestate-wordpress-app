<?php
/**
 * User: simon
 * Date: 11.06.2019
 */

class ShortPixelAILogger {
    private static $instance;
    private $logPath;
    /**
     * Make sure only one instance is running.
     */
    public static function instance()
    {
        if (!isset (self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->logPath = $upload_dir['basedir'] . '/' . ShortPixelAI::LOG_NAME;
        if (SHORTPIXEL_AI_DEBUG === 'delete') {
            @unlink($this->logPath);
            $this->log("START FRESH", SHORTPIXEL_AI_DEBUG);
        }
    }

    public function log($msg, $extra = false) {
        if (SHORTPIXEL_AI_DEBUG) {
            file_put_contents($this->logPath, '[' . date('Y-m-d H:i:s') . "] $msg" . ($extra ? json_encode($extra, JSON_PRETTY_PRINT) : '') . "\n", FILE_APPEND);
        }
    }
}