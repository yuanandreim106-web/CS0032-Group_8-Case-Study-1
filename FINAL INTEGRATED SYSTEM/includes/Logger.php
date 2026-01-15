<?php

/**
 * Centralized Logging Utility
 * Provides structured logging with timestamps and severity levels
 * Logs are stored in the logs/ directory (not web-accessible)
 */

class Logger
{
    private static $logFile = null;
    private static $logDir = null;

    /**
     * Initialize logger with custom log directory
     * @param string $logDir Directory path for logs (optional, defaults to logs/)
     */
    public static function init($logDir = null)
    {
        if ($logDir === null) {
            // Default to logs/ directory relative to Case Study 1 root
            $logDir = __DIR__ . '/../logs';
        }

        self::$logDir = $logDir;
        self::$logFile = self::$logDir . '/app.log';

        // Ensure log directory exists
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    /**
     * Ensure logger is initialized
     */
    private static function ensureInit()
    {
        if (self::$logFile === null) {
            self::init();
        }
    }

    /**
     * Log a message with severity level
     * @param string $message The log message
     * @param string $level Severity level: INFO, ERROR, DEBUG, WARNING
     * @param array $context Additional context data (optional)
     */
    public static function log($message, $level = 'INFO', $context = [])
    {
        self::ensureInit();

        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' | ' . json_encode($context) : '';
        $formattedMessage = "[$timestamp] [$level] $message$contextString" . PHP_EOL;

        file_put_contents(self::$logFile, $formattedMessage, FILE_APPEND);
    }

    /**
     * Log an error
     * @param string $message Error message
     * @param array $context Additional context
     */
    public static function error($message, $context = [])
    {
        self::log($message, 'ERROR', $context);
    }

    /**
     * Log an info message
     * @param string $message Info message
     * @param array $context Additional context
     */
    public static function info($message, $context = [])
    {
        self::log($message, 'INFO', $context);
    }

    /**
     * Log a debug message
     * @param string $message Debug message
     * @param array $context Additional context
     */
    public static function debug($message, $context = [])
    {
        self::log($message, 'DEBUG', $context);
    }

    /**
     * Log a warning
     * @param string $message Warning message
     * @param array $context Additional context
     */
    public static function warning($message, $context = [])
    {
        self::log($message, 'WARNING', $context);
    }

    /**
     * Get the log file path (for monitoring/admin purposes)
     */
    public static function getLogFile()
    {
        self::ensureInit();
        return self::$logFile;
    }
}

// Auto-initialize logger on include
Logger::init();
