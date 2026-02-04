<?php

namespace LucianoTonet\TelescopeMcp\Support;

use Illuminate\Support\Facades\Log;

class Logger
{
    protected static $instance = null;

    protected function __construct()
    {
        // Empty constructor
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($level, $message, array $context = [])
    {
        if (!config('telescope-mcp.logging.enabled', true)) {
            return;
        }

        // Log using Laravel's default channel
        Log::log($level, $message, $context);
    }

    public static function debug($message, array $context = [])
    {
        self::getInstance()->log('debug', $message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::getInstance()->log('info', $message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::getInstance()->log('warning', $message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::getInstance()->log('error', $message, $context);
    }
}
