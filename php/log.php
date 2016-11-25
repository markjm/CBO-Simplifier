<?php
require 'php/util.php';

const LOG_DEBUG = 0;
const LOG_WARNING = 1;
const LOG_ERROR = 2;

/*
 * Gets a Logger that logs via the 'error_log' function
 */
function get_error_logger($name) {
    return new Logger($name, error_log);
}

class Logger {
    private $log_level;
    private $log_format;
    private $log_method;

    public function __construct($log_name, $log_method) {
        $this->log_level = LOG_WARNING;
        $this->log_format = '{time} [{level}] {name}: {message}';
        $this->log_method = $log_method;
        $this->log_name = $log_name;
    }

    /*
     * Sets the minimum logging level - logging messages with a severity less
     * than this will be ignored.
     */
    public function set_level($log_level) {
        $this->log_level = $log_level;
    }

    /*
     * Sets the logging format, which is a string accepted by 'fmt_string'
     * with the following placeholders: {time}, {level}, {name} and {message}
     */
    public function set_format($log_format) {
        $this->log_format = $log_format;
    }

    /*
     * Formats the information from a logging statement into a complete logging
     * message.
     */
    private function format_log($level, $messgae) {
        $levels = array('DEBUG', 'WARNING', 'ERROR');
        $params = array(
            'time' => date('c'),
            'level' => $levels[$level],
            'message' => $message,
            'name' => $this->log_name
        );

        return fmt_string($this->log_format, $params);
    }

    /*
     * Posts a log message. Note that the format and parameters must be in the
     * form accepted by 'fmt_log'
     */
    public function log($level, $format, $params=null) {
        if (is_null($params)) {
            $params = array();
        }

        if ($level < $this->log_level) return;

        $full_message = $this->format_log($level, fmt_string($format, $params));
        $this->log_method($full_message);
    }

    // Convenience logging functions that imply a specific log level

    public function debug($format, $params=null) {
        $this->log(LOG_DEBUG, $format, $params);
    }

    public function warning($format, $params=null) {
        $this->log(LOG_WARNING, $format, $params);
    }

    public function error($format, $params=null) {
        $this->log(LOG_ERROR, $format, $params);
    }
}