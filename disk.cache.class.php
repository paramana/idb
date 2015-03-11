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
     * The directory for the cache
     * Specify a cache dir.
     *
     * @since 1.1
     * @access public
     * @var boolean
     */
    var $cache_dir = __DIR__;

    /**
     * Behaves as a contructor
     * And overwrites the init of the Q_Core class
     */
    protected function init(){
        if (defined('DB_CACHE_DIR'))
            $this->cache_dir = DB_CACHE_DIR;
    }

    /**
     * Sets the cache
     *
     * @param string $query SQL query.
     *
     * @return mixed Database query results
     */
    function set($key, $value, $ttl=NULL) {
        if (empty($key) || empty($value))
            return false;

        // The would be cache file for this query
        $cache_file = $this->cache_dir . '/' . $key;

        // disk caching of queries
        if (!is_dir($this->cache_dir)) {
            $this->show_errors ? trigger_error("Could not open cache dir: $this->cache_dir", E_USER_WARNING) : null;
            return false;
        }
        
        try {
            $value = serialize($value);
        } 
        catch (Exception $e) {
            $this->show_errors ? trigger_error("Failed to serialize cache value: $e" , E_USER_WARNING) : null;
            return false;
        }

        error_log($value, 3, $cache_file);
        return true;
    }

    /**
     * Gets the cached results
     *
     * @param string $query SQL query.
     *
     * @return mixed Database query results
     */
    function get($key) {
        if (empty($key))
            return null;

        // The would be cache file for this query
        $cache_file = $this->cache_dir . '/' . $key;

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
