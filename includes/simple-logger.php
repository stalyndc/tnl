<?php
/**
 * The News Log - Simple Logger Utility
 * A lightweight logging utility to handle errors and application events
 */

class SimpleLogger {
    // Log levels
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';
    
    // Configuration
    private $logFile = 'storage/logs/app.log';
    private $logLevel = self::ERROR; // Default to only log errors
    private $enabled = true;
    private $maxFileSize = 5242880; // 5MB
    
    /**
     * Constructor - creates log directory if it doesn't exist
     */
    public function __construct() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Set the minimum log level to record
     *
     * @param string $level One of ERROR, WARNING, INFO, DEBUG
     * @return SimpleLogger For method chaining
     */
    public function setLogLevel($level) {
        $this->logLevel = $level;
        return $this;
    }
    
    /**
     * Enable or disable logging
     *
     * @param bool $enabled Whether logging is enabled
     * @return SimpleLogger For method chaining
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
        return $this;
    }
    
    /**
     * Set the log file path
     *
     * @param string $filePath Path to log file
     * @return SimpleLogger For method chaining
     */
    public function setLogFile($filePath) {
        $this->logFile = $filePath;
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        return $this;
    }
    
    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function error($message, $context = []) {
        return $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function warning($message, $context = []) {
        return $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function info($message, $context = []) {
        return $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function debug($message, $context = []) {
        return $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Main logging method
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function log($level, $message, $context = []) {
        // Skip if logging is disabled or level is below minimum
        if (!$this->enabled || !$this->shouldLog($level)) {
            return false;
        }
        
        // Rotate log if needed
        $this->rotateLogIfNeeded();
        
        // Format the log entry
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        // Write to log file
        return error_log($logEntry, 3, $this->logFile);
    }
    
    /**
     * Determine if this log level should be recorded
     *
     * @param string $level Log level to check
     * @return bool Whether to log this message
     */
    private function shouldLog($level) {
        $levels = [
            self::DEBUG => 1,
            self::INFO => 2,
            self::WARNING => 3,
            self::ERROR => 4
        ];
        
        return isset($levels[$level]) && isset($levels[$this->logLevel]) && 
               $levels[$level] >= $levels[$this->logLevel];
    }
    
    /**
     * Rotate log file if it exceeds max size
     */
    private function rotateLogIfNeeded() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        if (filesize($this->logFile) > $this->maxFileSize) {
            $backupFile = $this->logFile . '.' . date('YmdHis');
            rename($this->logFile, $backupFile);
        }
    }
    
    /**
     * Log an exception
     *
     * @param Exception $exception The exception to log
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function logException($exception, $context = []) {
        $message = get_class($exception) . ': ' . $exception->getMessage() . 
                   ' in ' . $exception->getFile() . ' on line ' . $exception->getLine();
        
        // Add stack trace to context
        $context['trace'] = $exception->getTraceAsString();
        
        return $this->error($message, $context);
    }
}

/**
 * Create a singleton instance for easy access
 */
class Logger {
    private static $instance;
    
    /**
     * Get the logger instance
     *
     * @return SimpleLogger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new SimpleLogger();
        }
        return self::$instance;
    }
    
    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public static function error($message, $context = []) {
        return self::getInstance()->error($message, $context);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public static function warning($message, $context = []) {
        return self::getInstance()->warning($message, $context);
    }
    
    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public static function info($message, $context = []) {
        return self::getInstance()->info($message, $context);
    }
    
    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public static function debug($message, $context = []) {
        return self::getInstance()->debug($message, $context);
    }
    
    /**
     * Log an exception
     *
     * @param Exception $exception The exception to log
     * @param array $context Additional context data
     * @return bool Success status
     */
    public static function logException($exception, $context = []) {
        return self::getInstance()->logException($exception, $context);
    }
}
