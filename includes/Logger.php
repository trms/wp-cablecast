<?php
// In your main plugin file or a included class file.

namespace Cablecast;

defined('ABSPATH') || exit;

class Logger {
    private static $dir;
    private static $file;

    // Log levels in order of severity (higher = more severe)
    const LEVEL_DEBUG   = 0;
    const LEVEL_INFO    = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR   = 3;

    private static $level_map = [
        'debug'   => self::LEVEL_DEBUG,
        'info'    => self::LEVEL_INFO,
        'warning' => self::LEVEL_WARNING,
        'error'   => self::LEVEL_ERROR,
    ];

    public static function init() {
        // Choose uploads so it's writable and not wiped by updates.
        $uploads = wp_upload_dir(null, false);
        self::$dir  = trailingslashit($uploads['basedir']) . 'cablecast/logs/';
        self::$file = self::$dir . 'cablecast.log';

        // Make folder on activation and protect it.
        add_action('admin_init', [__CLASS__, 'ensure_dir']);
    }

    public static function ensure_dir() {
        if ( ! file_exists(self::$dir) ) {
            wp_mkdir_p(self::$dir);
        }
        // Prevent direct browsing; downloads go through our authenticated handler.
        $htaccess = self::$dir . '.htaccess';
        if ( ! file_exists($htaccess) ) {
            file_put_contents($htaccess, "Require all denied\n");
        }
        $index = self::$dir . 'index.php';
        if ( ! file_exists($index) ) {
            file_put_contents($index, "<?php // Silence is golden.");
        }
    }

    /**
     * Get the minimum log level based on WP_DEBUG setting.
     * When WP_DEBUG is false, only warnings and errors are logged.
     * When WP_DEBUG is true, all messages including debug and info are logged.
     *
     * @return int Minimum log level to record
     */
    public static function get_min_level() {
        // Allow override via filter
        $min_level = apply_filters('cablecast_log_level', null);
        if ($min_level !== null && isset(self::$level_map[$min_level])) {
            return self::$level_map[$min_level];
        }

        // Default: debug/info only when WP_DEBUG is on
        return (defined('WP_DEBUG') && WP_DEBUG) ? self::LEVEL_DEBUG : self::LEVEL_WARNING;
    }

    public static function log($level, $message, array $context = []) {
        // Check if this level should be logged
        $level_value = isset(self::$level_map[$level]) ? self::$level_map[$level] : self::LEVEL_INFO;
        if ($level_value < self::get_min_level()) {
            return; // Skip logging messages below minimum level
        }

        self::ensure_dir();

        // Simple PSR-3-ish line format
        $line = sprintf(
            "[%s] %s: %s %s\n",
            wp_date('c'),
            strtoupper($level),
            is_string($message) ? $message : wp_json_encode($message),
            $context ? wp_json_encode($context) : ''
        );

        // Basic rotation: 5MB max -> rotate to .1
        if ( file_exists(self::$file) && filesize(self::$file) > 5 * 1024 * 1024 ) {
            @rename(self::$file, self::$file . '.1');
        }

        // Append atomically
        file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX);
        @chmod(self::$file, 0640);
    }

    public static function path()   { return self::$file; }
    public static function exists() { return file_exists(self::$file); }
}
Logger::init();

// Usage anywhere in your plugin:
// \MyPlugin\Logger::log('info', 'Started job', ['id' => 123]);
