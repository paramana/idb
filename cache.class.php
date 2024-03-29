<?php
/**
 * iDB Disk Cache Class
 *
 * Version: 1.12
 * Started: 05-01-2015
 * Updated: 29-11-2023
 *
 */

/**
 * Database Access Abstraction Object
 *
 */
abstract class idb_Cache_Core {
    /**
     * The DB class
     *
     * @access public
     */
    var $idb;

    /**
     * Cache expiry
     * Note: this is in seconds
     *
     * @since 1.1
     * @access public
     * @var int
     */
    var $cache_timeout = 24 * 60 * 60;

    /**
     * If true it caches the inserts also
     *
     * @since 1.1
     * @access public
     * @var boolean
     */
    var $cache_inserts = false;

    /**
     * Whether to show SQL/DB errors
     *
     * @since 0.71
     * @access private
     * @var bool
     */
    var $show_errors = false;

    /**
     * This is used to prefix cache names
     *
     * @since 1.11
     * @access private
     * @var string
     */
    var $cache_prefix = "";

    /**
     *
     * @param string $cache_type The type of cache to use defaults to disk, can be apc, memcache
     *
     */
	function __construct($idb) {
		register_shutdown_function( array( $this, '__destruct' ) );

        if (DEBUG)
            $this->show_errors = true;

        if (defined('DB_CACHE_TIMEOUT'))
            $this->cache_timeout = DB_CACHE_TIMEOUT;

        if (defined('DB_CACHE_INSERTS') && DB_CACHE_INSERTS)
            $this->cache_inserts = DB_CACHE_INSERTS;

        if (defined('DB_CACHE_PREFIX') && DB_CACHE_PREFIX)
            $this->cache_prefix = DB_CACHE_PREFIX;

        if (empty($idb))
            return $this->show_errors ? trigger_error("idb class not found", E_USER_WARNING) : null;

        $this->idb = $idb;

        // init method via magic static keyword ($this injected)
        static::init();
    }

    /**
     * PHP5 style destructor and will run when database object is destroyed.
     *
     * @see idb::__construct()
     * @since 2.0.8
     * @return bool true
     */
    function __destruct() {
        return true;
    }

    /**
     * The init function
     * Can be overidden from the child class
     *
     */
    protected function init() {

    }

    /**
     * Clears cache
     * Can be overidden from the child class
     * 
     * @since 1.12
     */
    function flush() {

    }

    /**
     * Stores a query in cache
     *
     * @param string $query the string result of a query to store
     * @param boolean $is_insert if is an insert or not
     */
    function store_cache($query, $is_insert, $ttl=NULL) {
        if (!$this->cache_inserts && $is_insert)
            return false;

        $cache_name = md5($this->cache_prefix . $query);
        $cache_ttl = (float)(!$ttl ? $this->cache_timeout : $ttl);

        // Cache all result values
        $result_cache = array(
            'col_info' => $this->idb->col_info,
            'last_result' => $this->idb->last_result,
            'num_rows' => $this->idb->num_rows,
            'return_value' => $this->idb->num_rows,
            'expire_at' => time() + $cache_ttl
        );

        $this->set($cache_name, $result_cache, $cache_ttl);

        return true;
    }

    /**
     * Gets the cached results
     *
     * @param string $query SQL query.
     *
     * @return mixed Database query results
     */
    function get_cache($query) {
        $cache_name = md5($this->cache_prefix . $query);

        $result_cache = $this->get($cache_name);

        if (empty($result_cache))
            return false;

        $this->idb->col_info    = $result_cache['col_info'];
        $this->idb->last_result = $result_cache['last_result'];
        $this->idb->num_rows    = $result_cache['num_rows'];

        return $result_cache['return_value'];
    }
}
