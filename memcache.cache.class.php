<?php
/**
 * iDB Memcache Cache Class
 *
 * Version: 1.0
 * Started: 19-03-2015
 * Updated: 30-01-2018
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

    private static $connected = false;
    private $memcache = null;
    private $host = 'localhost';
    private $port = 11211;
    private $compress = 0;
    private $expiry   = 3600;

    /**
     * Behaves as a contructor
     * And overwrites the init of the idb_Cache_Core class
     */
    protected function init(){
        if (defined('DB_MEMCACHED_HOST'))
            $this->host = DB_MEMCACHED_HOST;
        if (defined('DB_MEMCACHED_PORT'))
            $this->port = DB_MEMCACHED_PORT;
        if (defined('DB_MEMCACHED_COMPRESS'))
            $this->compress = DB_MEMCACHED_COMPRESS;
        if (defined('DB_MEMCACHED_EXPIRY'))
            $this->expiry = DB_MEMCACHED_EXPIRY;
    }

    public function delete($key, $timeout = 0) {
        if(!$this->connect() || empty($key))
          return false;

        return $this->memcache->delete($key, $timeout);
    }

    /**
     *
     * @return mixed Database query results
     */
    public function set($key, $value, $ttl=NULL) {
        if(!$this->connect())
            return false;

        $expiry = !$ttl ? $this->expiry : $ttl;
        $this->memcache->set($key, $value, MEMCACHE_COMPRESSED, $expiry);
        return true;
    }

    /**
     * Gets the cached results
     *
     * @param string $query SQL query.
     *
     * @return mixed Database query results
     */
    public function get($key) {
        if(!$this->connect() || empty($key))
            return null;

        return $this->memcache->get($key);
    }

    private function connect() {
        if(self::$connected === true && !empty($this->memcache))
            return true;

        if(class_exists('Memcache')) {
            $this->memcache = new Memcache;

            if($this->memcache->addServer($this->host, $this->port))
                return self::$connected = true;

            $this->show_errors ? trigger_error("Could not connect to memcache server", E_USER_WARNING) : null;
            return false;
        }

        $this->show_errors ? trigger_error("No memcache client exists", E_USER_WARNING) : null;
        return false;
    }
}
