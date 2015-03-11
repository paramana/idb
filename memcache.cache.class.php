<?php
/**
 * iDB Memcache Cache Class
 * 
 * Version: 1.0
 * Started: 19-03-2015
 * Updated: 19-03-2015
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
    private $host = null;
    private $port = null;
    private $compress = null;
    private $expiry   = null;

    /**
     * Behaves as a contructor
     * And overwrites the init of the Q_Core class
     */
    protected function init($params = array()){
        $this->host     = !empty($params[0]) ? $params[0] : 'localhost';
        $this->port     = !empty($params[1]) ? $params[1] : 11211;
        $this->compress = !empty($params[2]) ? $params[2] : 0;
        $this->expiry   = !empty($params[3]) ? $params[3] : 3600;
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
        if(self::$connected === true)
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
