<?php
/**
 * iDB Disk Cache Class
 * 
 * Version: 1.0
 * Started: 05-01-2015
 * Updated: 06-01-2015
 * 
 */

spl_autoload_register(function($class){
    require_once __DIR__ . "/cache.class.php";
});

/**
 * Database disk cache
 *
 */
class iDB_Cache extends idb_Cache_Core {
    /**
     * Set to true to enable disk cache
     *
     * @since 1.1
     * @access public
     * @var boolean
     */
    var $use_disk_cache = false;

    /**
     * The directory for the cache
     * Specify a cache dir.
     *
     * @since 1.1
     * @access public
     * @var boolean
     */
    var $cache_dir = false;

    /**
     * Behaves as a contructor
     * And overwrites the init of the Q_Core class
     */
    protected function init(){
        if (defined('DB_CACHE_DIR'))
            $this->cache_dir = DB_CACHE_DIR;
    }

    /**
     * Starts the timer, for debugging purposes.
     *
     * @since 1.5.0
     *
     * @return mixed Database query results
     */
    function set($cache_name, $cache_value) {

        // The would be cache file for this query
        $cache_file = $this->cache_dir . '/' . $cache_name;

        // disk caching of queries
        if (!is_dir($this->cache_dir))
            $this->show_errors ? trigger_error("Could not open cache dir: $this->cache_dir", E_USER_WARNING) : null;
        else
            error_log($cache_value, 3, $cache_file);
    }

    /**
     * Gets the cached results
     *
     * @param string $query SQL query.
     *
     * @return mixed Database query results
     */
    function get($cache_name) {

        // The would be cache file for this query
        $cache_file = $this->cache_dir . '/' . $cache_name;

        // Try to get previously cached version
        if (file_exists($cache_file)) {
            // Only use this cache file if less than 'cache_timeout' (hours)
            if ((time() - filemtime($cache_file)) > ($this->cache_timeout * 3600))
                unlink($cache_file);
            else
                return unserialize(file_get_contents($cache_file));
        }
    }
}
