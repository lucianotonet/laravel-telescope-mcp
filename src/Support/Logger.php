<?php

namespace LucianoTonet\TelescopeMcp\Support;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;

class Logger
{
    protected static $instance = null;
    protected $logger;
    
    protected function __construct()
    {
        $config = config('telescope-mcp.logging');
        
        if (!$config['enabled']) {
            return;
        }
        
        // Create Monolog logger
        $this->logger = new Monolog('telescope-mcp');
        
        // Add handler for file without rotation by days
        $handler = new StreamHandler(
            $config['path'],
            $this->getMonologLevel($config['level'])
        );
        
        $this->logger->pushHandler($handler);
    }
    
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    protected function getMonologLevel($level)
    {
        $levels = [
            'debug' => Monolog::DEBUG,
            'info' => Monolog::INFO,
            'notice' => Monolog::NOTICE,
            'warning' => Monolog::WARNING,
            'error' => Monolog::ERROR,
            'critical' => Monolog::CRITICAL,
            'alert' => Monolog::ALERT,
            'emergency' => Monolog::EMERGENCY,
        ];
        
        return $levels[strtolower($level)] ?? Monolog::DEBUG;
    }
    
    public function log($level, $message, array $context = [])
    {
        if (!config('telescope-mcp.logging.enabled')) {
            return;
        }
        
        // Log to the package-specific file
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
        
        // Also log to the default Laravel channel
        Log::channel(config('telescope-mcp.logging.channel'))->log($level, $message, $context);
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