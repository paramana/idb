<?php

/**
 * iDB Class
 * 
 * Version: 1.1
 * Started: 02-02-2010
 * Updated: 12-04-2016
 *
 * Original code from {@link http://php.justinvincent.com Justin Vincent (justin@visunet.ie)}
 * and from wordpress {@link http://wordpress.org/}
 * 
 * This is a clone of the wp db class
 * https://github.com/WordPress/WordPress/blob/master/wp-includes/wp-db.php
 * 
 * with some added file caching functionality
 * 
 * from wp commit 980668299c4756c0f7019d2265cc679d895ebabc on 10/02/2015
 * 
 * @source https://github.com/giannis/idb
 * 
 */

/**
 * @since 0.71
 */
define( 'EZSQL_VERSION', 'WP1.25' );

/**
 * @since 0.71
 */
define( 'OBJECT', 'OBJECT', true );

/**
 * @since 2.5.0
 */
define( 'OBJECT_K', 'OBJECT_K' );

/**
 * @since 0.71
 */
define( 'ARRAY_A', 'ARRAY_A' );

/**
 * @since 0.71
 */
define( 'ARRAY_N', 'ARRAY_N' );

/**
 * Database Access Abstraction Object
 *
 */
class idb {

    /**
     * Whether to show SQL/DB errors.
     *
     * Default behavior is to show errors if both DEBUG
     * evaluated to true.
    *
     * @since 0.71
     * @access private
     * @var bool
     */
    var $show_errors = false;

    /**
     * Whether to suppress errors during the DB bootstrapping.
     *
     * @access private
	 * @since 2.5.0
     * @var bool
     */
    var $suppress_errors = false;

    /**
     * The last error during query.
     *
	 * @since 2.5.0
     * @var string
     */
    public $last_error = '';

    /**
     * Amount of queries made
     *
     * @since 1.2.0
     * @access private
     * @var int
     */
    var $num_queries = 0;

    /**
     * Count of rows returned by previous query
     *
	 * @since 0.71
     * @access private
     * @var int
     */
    var $num_rows = 0;

    /**
     * Count of affected rows by previous query
     *
     * @since 0.71
     * @access private
     * @var int
     */
    var $rows_affected = 0;

    /**
     * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
     *
     * @since 0.71
     * @access public
     * @var int
     */
    var $insert_id = 0;

    /**
	 * Last query made
     *
	 * @since 0.71
     * @access private
     * @var array
     */
    var $last_query;

    /**
     * Results of the last query made
     *
	 * @since 0.71
     * @access private
     * @var array|null
     */
    var $last_result;

    /**
	 * MySQL result, which is either a resource or boolean.
	 *
	 * @since 0.71
	 * @access protected
	 * @var mixed
	 */
	protected $result;
    
    /**
	 * Cached column info, for sanity checking data before inserting
	 *
	 * @since 4.2.0
	 * @access protected
	 * @var array
	 */
	protected $col_meta = array();

	/**
	 * Calculated character sets on tables
	 *
	 * @since 4.2.0
	 * @access protected
	 * @var array
	 */
	protected $table_charset = array();

	/**
	 * Whether text fields in the current query need to be sanity checked.
	 *
	 * @since 4.2.0
	 * @access protected
	 * @var bool
	 */
	protected $check_current_query = true;
    
	/**
     * Saved info on the table column
     *
	 * @since 0.71
	 * @access protected
     * @var array
     */
	protected $col_info;

    /**
     * Saved queries that were executed
     *
     * @since 1.5.0
     * @access private
     * @var array
     */
    var $queries;
    
    /**
	 * The number of times to retry reconnecting before dying.
	 *
	 * @since 3.9.0
	 * @access protected
	 * @see idb::check_connection()
	 * @var int
	 */
	protected $reconnect_retries = 5;

	/**
	 * Whether the database queries are ready to start executing.
	 *
	 * @since 2.3.2
	 * @access private
     * @var bool
     */
    var $ready = false;

    /**
     * Format specifiers for DB columns. Columns not listed here default to %s. Initialized during load.
     *
     * Keys are column names, values are format types: 'ID' => '%d'
     *
     * @since 2.8.0
     * @see idb:prepare()
     * @see idb:insert()
     * @see idb:update()
     * @access public
     * @var array
     */
    public $field_types = array();

    /**
     * Database table columns charset
     *
     * @since 2.2.0
     * @access public
     * @var string
     */
    public $charset;

    /**
     * Database table columns collate
     *
     * @since 2.2.0
     * @access public
     * @var string
     */
    var $collate;

    /**
     * Database Username
     *
     * @since 2.9.0
     * @access private
     * @var string
     */
    var $dbuser;

    /**
     * A textual description of the last query/get_row/get_var call
     *
     * @since unknown
     * @access public
     * @var string
     */
    public $func_call;

    /**
     * Set to true to enable cache
     *
     * @since 1.1
     * @access public
     * @var boolean
     */
    var $use_cache = false;

    /**
     * Set to disk
     *
     * @access public
     * @var string
     */
    var $cache_type = "disk";
    
	/**
	 * Whether to use mysqli over mysql.
	 *
	 * @since 3.9.0
	 * @access private
	 * @var bool
	 */
	private $use_mysqli = false;

	/**
	 * Whether we've managed to successfully connect at some point
	 *
	 * @since 3.9.0
	 * @access private
	 * @var bool
	 */
	private $has_connected = false;
    
    /**
     * Connects to the database server and selects a database
     *
     * PHP5 style constructor for compatibility with PHP5. Does
     * the actual setting up of the class properties and connection
     * to the database.
     *
     * @since 2.0.8
     *
     * @param string $dbuser MySQL database user
     * @param string $dbpassword MySQL database password
     * @param string $dbname MySQL database name
     * @param string $dbhost MySQL database host
     */
	public function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		register_shutdown_function( array( $this, '__destruct' ) );

        if (DEBUG)
            $this->show_errors();
        
        /* Use ext/mysqli if it exists and:
		 *  - We are running PHP 5.5 or greater, or
		 */
		if ( function_exists( 'mysqli_connect' ) ) {
			if ( version_compare( phpversion(), '5.5', '>=' ) || ! function_exists( 'mysql_connect' ) ) {
				$this->use_mysqli = true;
			}
		}
        
        $this->init_cache();

        $this->dbuser = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname = $dbname;
        $this->dbhost = $dbhost;

        $this->db_connect();
    }

    /**
     * PHP5 style destructor and will run when database object is destroyed.
     *
     * @see idb::__construct()
     * @since 2.0.8
     * @return bool true
     */
    public function __destruct() {
        return true;
    }
    
    /**
	 * PHP5 style magic getter, used to lazy-load expensive data.
	 *
	 * @since 3.5.0
	 *
	 * @param string $name The private member to get, and optionally process
	 * @return mixed The private member
	 */
	public function __get( $name ) {
		if ( 'col_info' === $name )
			$this->load_col_info();

		return $this->$name;
	}

	/**
	 * Magic function, for backwards compatibility
	 *
	 * @since 3.5.0
	 *
	 * @param string $name  The private member to set
	 * @param mixed  $value The value to set
	 */
	public function __set( $name, $value ) {
		$this->$name = $value;
	}

	/**
	 * Magic function, for backwards compatibility
	 *
	 * @since 3.5.0
	 *
	 * @param string $name  The private member to check
	 *
	 * @return bool If the member is set or not
	 */
	public function __isset( $name ) {
		return isset( $this->$name );
	}

	/**
	 * Magic function, for backwards compatibility
	 *
	 * @since 3.5.0
	 *
	 * @param string $name  The private member to unset
	 */
	public function __unset( $name ) {
		unset( $this->$name );
	}

    /**
     * Sets the cache
     *
     * @since 3.1.0
     */
    public function init_cache() {
        if (defined('DB_CACHE') && DB_CACHE)
            $this->use_cache = DB_CACHE;
        else
            $this->use_cache = false;

        if (defined('DB_CACHE_TYPE')) {
            $this->cache_type = DB_CACHE_TYPE;

            require_once __DIR__ . "/" . $this->cache_type . ".cache.class.php";

            $this->cache = new iDB_Cache($this);
        }
    }

    /**
     * Set $this->charset and $this->collate
     *
     * @since 3.1.0
     */
    public function init_charset() {
        if (defined('DB_COLLATE')) {
            $this->collate = DB_COLLATE;
        }
        else {
            $this->collate = 'utf8_general_ci';
        }
        
		if ( defined( 'DB_CHARSET' ) )
            $this->charset = DB_CHARSET;
        
        if ( ( $this->use_mysqli && ! ( $this->dbh instanceof mysqli ) )
		  || ( empty( $this->dbh ) || ! ( $this->dbh instanceof mysqli ) ) ) {
			return;
		}

		if ( 'utf8' === $this->charset && $this->has_cap( 'utf8mb4' ) ) {
			$this->charset = 'utf8mb4';
		}

		if ( 'utf8mb4' === $this->charset && ( ! $this->collate || stripos( $this->collate, 'utf8_' ) === 0 ) ) {
			$this->collate = 'utf8mb4_unicode_ci';
		}
    }

    /**
     * Sets the connection's character set.
     *
     * @since 3.1.0
     *
     * @param resource $dbh     The resource given by mysql_connect
     * @param string   $charset Optional. The character set. Default null.
     * @param string   $collate Optional. The collation. Default null.
     */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
		if ( ! isset( $charset ) )
            $charset = $this->charset;
		if ( ! isset( $collate ) )
            $collate = $this->collate;
		if ( $this->has_cap( 'collation' ) && ! empty( $charset ) ) {
            if ( $this->use_mysqli ) {
				if ( function_exists( 'mysqli_set_charset' ) && $this->has_cap( 'set_charset' ) ) {
					mysqli_set_charset( $dbh, $charset );
				} else {
					$query = $this->prepare( 'SET NAMES %s', $charset );
					if ( ! empty( $collate ) )
						$query .= $this->prepare( ' COLLATE %s', $collate );
					mysqli_query( $dbh, $query );
				}
			} else {
                if ( function_exists( 'mysql_set_charset' ) && $this->has_cap( 'set_charset' ) ) {
                    mysql_set_charset( $charset, $dbh );
                } else {
                    $query = $this->prepare( 'SET NAMES %s', $charset );
                    if ( ! empty( $collate ) )
                        $query .= $this->prepare( ' COLLATE %s', $collate );
                    mysql_query( $query, $dbh );
                }
            }
        }
    }

    /**
     * Selects a database using the current database connection.
     *
     * The database name will be changed based on the current database
     * connection. On failure, the execution will bail and display an DB error.
     *
     * @since 0.71
     *
     * @param string $db MySQL database name
     * @param resource $dbh Optional link identifier.
     * @return null Always null.
     */
	public function select( $db, $dbh = null ) {
		if ( is_null($dbh) )
            $dbh = $this->dbh;
        
        if ( $this->use_mysqli ) {
			$success = @mysqli_select_db( $dbh, $db );
		} else {
			$success = @mysql_select_db( $db, $dbh );
		}
        
		if ( ! $success ) {
            $this->ready = false;
            $this->bail(sprintf('
<h1>Can&#8217;t select database</h1>
<p>We were able to connect to the database server (which means your username and password is okay) but not able to select the <code>%1$s</code> database.</p>
<ul>
<li>Are you sure it exists?</li>
<li>Does the user <code>%2$s</code> have permission to use the <code>%1$s</code> database?</li>
<li>On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?</li>
</ul>
<p>If you don\'t know how to set up a database you should <strong>contact your host</strong>.</p>', $db, $this->dbuser), 'db_select_fail');
            return;
        }
    }

    /**
     * Real escape, using mysqli_real_escape_string() or mysql_real_escape_string()
     *
     * @see mysql_real_escape_string()
     * @see addslashes()
     * @since 2.8
     * @access private
     *
     * @param  string $string to escape
     * @return string escaped
     */
    function _real_escape($string) {
        if ( $this->dbh ) {
			if ( $this->use_mysqli ) {
				return mysqli_real_escape_string( $this->dbh, $string );
			} else {
				return mysql_real_escape_string( $string, $this->dbh );
			}
		}
        
        return addslashes($string);
    }

    /**
     * Escape data. Works on arrays.
     *
	 * @uses idb::_real_escape()
	 * @since  2.8.0
     * @access private
     *
     * @param  string|array $data
     * @return string|array escaped
     */
	function _escape( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				if ( is_array($v) )
					$data[$k] = $this->_escape( $v );
                else
					$data[$k] = $this->_real_escape( $v );
            }
        } else {
			$data = $this->_real_escape( $data );
        }

        return $data;
    }

    /**
     * Escapes content by reference for insertion into the database, for security
     *
     * @uses idb::_real_escape()
     * @since 2.3.0
     * @param string $string to escape
     * @return void
     */
	public function escape_by_ref( &$string ) {
		if ( ! is_float( $string ) )
			$string = $this->_real_escape( $string );
    }

    /**
     * Prepares a SQL query for safe execution. Uses sprintf()-like syntax.
     *
     * The following directives can be used in the query format string:
	 *   %d (integer)
	 *   %f (float)
     *   %s (string)
     *   %% (literal percentage sign - no argument needed)
     *
	 * All of %d, %f, and %s are to be left unquoted in the query string and they need an argument passed for them.
     * Literals (%) as parts of the query must be properly written as %%.
     *
	 * This function only supports a small subset of the sprintf syntax; it only supports %d (integer), %f (float), and %s (string).
     * Does not support sign, padding, alignment, width or precision specifiers.
     * Does not support argument numbering/swapping.
     *
     * May be called like {@link http://php.net/sprintf sprintf()} or like {@link http://php.net/vsprintf vsprintf()}.
     *
     * Both %d and %s should be left unquoted in the query string.
     *
     * <code>
	 * idb::prepare( "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d", 'foo', 1337 )
	 * idb::prepare( "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s", 'foo' );
     * </code>
     *
     * @link http://php.net/sprintf Description of syntax.
     * @since 2.3.0
     *
     * @param string $query Query statement with sprintf()-like placeholders
     * @param array|mixed $args The array of variables to substitute into the query's placeholders if being called like
	 * 	{@link http://php.net/vsprintf vsprintf()}, or the first variable to substitute into the query's placeholders if
	 * 	being called like {@link http://php.net/sprintf sprintf()}.
     * @param mixed $args,... further variables to substitute into the query's placeholders if being called like
	 * 	{@link http://php.net/sprintf sprintf()}.
     * @return null|false|string Sanitized query string, null if there is no query, false if there is an error and string
	 * 	if there was something to prepare
     */
	public function prepare( $query, $args ) {
		if ( is_null( $query ) )
            return;

        $args = func_get_args();
		array_shift( $args );
        // If args were passed as an array (as in vsprintf), move them up
		if ( isset( $args[0] ) && is_array($args[0]) )
            $args = $args[0];
		$query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it
		$query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting
		$query = preg_replace( '|(?<!%)%f|' , '%F', $query ); // Force floats to be locale unaware
		$query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
		array_walk( $args, array( $this, 'escape_by_ref' ) );
		return @vsprintf( $query, $args );
    }
    
    /**
	 * First half of escaping for LIKE special characters % and _ before preparing for MySQL.
	 *
	 * Use this only before idb::prepare() or esc_sql().  Reversing the order is very bad for security.
	 *
	 * Example Prepared Statement:
	 *  $wild = '%';
	 *  $find = 'only 43% of planets';
	 *  $like = $wild . $idb->esc_like( $find ) . $wild;
	 *  $sql  = $idb->prepare( "SELECT * FROM $idb->posts WHERE post_content LIKE %s", $like );
	 *
	 * Example Escape Chain:
	 *  $sql  = esc_sql( $idb->esc_like( $input ) );
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $text The raw text to be escaped. The input typed by the user should have no
	 *                     extra or deleted slashes.
	 * @return string Text in the form of a LIKE phrase. The output is not SQL safe. Call $idb::prepare()
	 *                or real_escape next.
	 */
	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
    
    /**
     * Print SQL/DB error.
     *
     * @since 0.71
     * @global array $EZSQL_ERROR Stores error information of query and error string
     *
     * @param string $str The error to display
     * @return false|null False if the showing of errors is disabled.
     */
	public function print_error( $str = '' ) {
        global $EZSQL_ERROR;
        
        if ( !$str ) {
			if ( $this->use_mysqli ) {
				$str = mysqli_error( $this->dbh );
			} else {
				$str = mysql_error( $this->dbh );
			}
		}
        
		$EZSQL_ERROR[] = array( 'query' => $this->last_query, 'error_str' => $str );

		if ( $this->suppress_errors )
            return false;

        if ($caller = $this->get_caller())
            $error_str = sprintf('Database error %1$s for query %2$s made by %3$s', $str, $this->last_query, $caller);
        else
            $error_str = sprintf('Database error %1$s for query %2$s', $str, $this->last_query);

        $log_error = true;
        if (!function_exists('error_log'))
            $log_error = false;

        $log_file = @ini_get('error_log');
        if (!empty($log_file) && ('syslog' != $log_file) && !@is_writable($log_file))
            $log_error = false;

        if ($log_error)
            @error_log($error_str, 0);

        // Is error output turned on or not..
        if (!$this->show_errors)
            return false;

        $str = htmlspecialchars($str, ENT_QUOTES);
        $query = htmlspecialchars($this->last_query, ENT_QUOTES);

        // If there is an error then take note of it
        print "<div id='error'>
        <p class='idberror'><strong>Database error:</strong> [$str]<br />
        <code>$query</code></p>
        </div>";
    }

    /**
     * Enables showing of database errors.
     *
     * This function should be used only to enable showing of errors.
     * idb::hide_errors() should be used instead for hiding of errors. However,
     * this function can be used to enable and disable showing of database
     * errors.
     *
     * @since 0.71
     * @see idb::hide_errors()
     *
     * @param bool $show Whether to show or hide errors
     * @return bool Old value for showing errors.
     */
	public function show_errors( $show = true ) {
        $errors = $this->show_errors;
        $this->show_errors = $show;
        return $errors;
    }

    /**
     * Disables showing of database errors.
     *
     * By default database errors are not shown.
     *
     * @since 0.71
     * @see idb::show_errors()
     *
     * @return bool Whether showing of errors was active
     */
    public function hide_errors() {
        $show = $this->show_errors;
        $this->show_errors = false;
        return $show;
    }

    /**
     * Whether to suppress database errors.
     *
     * By default database errors are suppressed, with a simple
     * call to this function they can be enabled.
     *
     * @since 2.5
     * @see idb::hide_errors()
     * @param bool $suppress Optional. New value. Defaults to true.
     * @return bool Old value
     */
	public function suppress_errors( $suppress = true ) {
        $errors = $this->suppress_errors;
        $this->suppress_errors = (bool) $suppress;
        return $errors;
    }

    /**
     * Kill cached query results.
     *
     * @since 0.71
     * @return void
     */
    public function flush() {
        $this->last_result = array();
		$this->col_info    = null;
		$this->last_query  = null;
		$this->rows_affected = $this->num_rows = 0;
		$this->last_error  = '';
        
        if ( $this->use_mysqli && $this->result instanceof mysqli_result ) {
			mysqli_free_result( $this->result );
			$this->result = null;

			// Sanity check before using the handle
			if ( empty( $this->dbh ) || !( $this->dbh instanceof mysqli ) ) {
				return;
			}

			// Clear out any results from a multi-query
			while ( mysqli_more_results( $this->dbh ) ) {
				mysqli_next_result( $this->dbh );
			}
		} elseif ( is_resource( $this->result ) ) {
			mysql_free_result( $this->result );
		}
    }

    /**
	 * Connect to and select database.
	 *
	 * If $allow_bail is false, the lack of database connection will need
	 * to be handled manually.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 $allow_bail parameter added.
	 *
	 * @param bool $allow_bail Optional. Allows the function to bail. Default true.
	 * @return null|bool True with a successful connection, false on failure.
	 */
    public function db_connect( $allow_bail = true ) {
        $this->is_mysql = true;
        
        /*
		 * Deprecated in 3.9+ when using MySQLi. No equivalent
		 * $new_link parameter exists for mysqli_* functions.
		 */
        $new_link = defined( 'MYSQL_NEW_LINK' ) ? MYSQL_NEW_LINK : true;
		$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;
        
        if ( $this->use_mysqli ) {
			$this->dbh = mysqli_init();

			// mysqli_real_connect doesn't support the host param including a port or socket
			// like mysql_connect does. This duplicates how mysql_connect detects a port and/or socket file.
			$port = null;
			$socket = null;
			$host = $this->dbhost;
			$port_or_socket = strstr( $host, ':' );
			if ( ! empty( $port_or_socket ) ) {
				$host = substr( $host, 0, strpos( $host, ':' ) );
				$port_or_socket = substr( $port_or_socket, 1 );
				if ( 0 !== strpos( $port_or_socket, '/' ) ) {
					$port = intval( $port_or_socket );
					$maybe_socket = strstr( $port_or_socket, ':' );
					if ( ! empty( $maybe_socket ) ) {
						$socket = substr( $maybe_socket, 1 );
					}
				} else {
					$socket = $port_or_socket;
				}
			}

			if ( DEBUG ) {
				mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags );
			} else {
				@mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags );
			}

			if ( $this->dbh->connect_errno ) {
				$this->dbh = null;

				/* It's possible ext/mysqli is misconfigured. Fall back to ext/mysql if:
		 		 *  - We haven't previously connected, and
		 		 *  - ext/mysql is loaded.
		 		 */
				$attempt_fallback = true;

				if ( $this->has_connected ) {
					$attempt_fallback = false;
				} elseif ( ! function_exists( 'mysql_connect' ) ) {
					$attempt_fallback = false;
				}

				if ( $attempt_fallback ) {
					$this->use_mysqli = false;
					$this->db_connect();
				}
			}
		} else {
            if ( DEBUG ) {
                $this->dbh = mysql_connect($this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags );
            } else {
                $this->dbh = @mysql_connect( $this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags );
            }
        }

        if (!$this->dbh && $allow_bail) {
            $this->bail(sprintf("
<h1>Error establishing a database connection</h1>
<p>This either means that the username and password information in your <code>config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>.
This could mean your host's database server is down.</p>
<ul>
    <li>Are you sure you have the correct username and password?</li>
    <li>Are you sure that you have typed the correct hostname?</li>
    <li>Are you sure that the database server is running?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host.</p>
", htmlspecialchars($this->dbhost, ENT_QUOTES)), 'db_connect_fail');

            return false;
		} elseif ( $this->dbh ) {
			if ( ! $this->has_connected ) {
				$this->init_charset();
			}

			$this->has_connected = true;

			$this->set_charset( $this->dbh );

			$this->ready = true;
			$this->select( $this->dbname, $this->dbh );

			return true;
		}

		return false;
    }
    
    /**
	 * Check that the connection to the database is still up. If not, try to reconnect.
	 *
	 * If this function is unable to reconnect, it will forcibly die, or if after the
	 * the template_redirect hook has been fired, return false instead.
	 *
	 * If $allow_bail is false, the lack of database connection will need
	 * to be handled manually.
	 *
	 * @since 3.9.0
	 *
	 * @param bool $allow_bail Optional. Allows the function to bail. Default true.
	 * @return bool|null True if the connection is up.
	 */
	public function check_connection( $allow_bail = true ) {
		if ( $this->use_mysqli ) {
			if ( @mysqli_ping( $this->dbh ) ) {
				return true;
			}
		} else {
			if ( @mysql_ping( $this->dbh ) ) {
				return true;
			}
		}

		$error_reporting = false;

		// Disable warnings, as we don't want to see a multitude of "unable to connect" messages
		if ( DEBUG ) {
			$error_reporting = error_reporting();
			error_reporting( $error_reporting & ~E_WARNING );
		}

		for ( $tries = 1; $tries <= $this->reconnect_retries; $tries++ ) {
			// On the last try, re-enable warnings. We want to see a single instance of the
			// "unable to connect" message on the bail() screen, if it appears.
			if ( $this->reconnect_retries === $tries && DEBUG ) {
				error_reporting( $error_reporting );
			}

			if ( $this->db_connect( false ) ) {
				if ( $error_reporting ) {
					error_reporting( $error_reporting );
				}

				return true;
			}

			sleep( 1 );
		}

		if ( ! $allow_bail ) {
			return false;
		}

		// We weren't able to reconnect, so we better bail.
		$this->bail( sprintf( ( "
<h1>Error reconnecting to the database</h1>
<p>This means that we lost contact with the database server at <code>%s</code>. This could mean your host's database server is down.</p>
<ul>
	<li>Are you sure that the database server is running?</li>
	<li>Are you sure that the database server is not under particularly heavy load?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='https://wordpress.org/support/'>WordPress Support Forums</a>.</p>
" ), htmlspecialchars( $this->dbhost, ENT_QUOTES ) ), 'db_connect_fail' );
	}

    /**
     * Perform a MySQL database query, using current database connection.
     *
     * More information can be found on the codex page.
     *
     * @since 0.71
     *
     * @param string $query Database query
     * @return int|false Number of rows affected/selected or false on error
     */
	public function query( $query ) {
		if ( ! $this->ready ) {
			$this->check_current_query = true;
			return false;
		}

        $this->flush();

        // For reg expressions
        $query = trim($query);

        // Log how the function was called
        $this->func_call = "\$db->query(\"$query\")";
        
        // If we're writing to the database, make sure the query will write safely.
		if ( $this->check_current_query && ! $this->check_ascii( $query ) ) {
			$stripped_query = $this->strip_invalid_text_from_query( $query );
			// strip_invalid_text_from_query() can perform queries, so we need
			// to flush again, just to make sure everything is clear.
			$this->flush();
			if ( $stripped_query !== $query ) {
				$this->insert_id = 0;
				return false;
			}
		}

		$this->check_current_query = true;

        
        // Keep track of the last query for debug..
        $this->last_query = $query;

        // Use core file cache function
        if ($this->use_cache && !empty($this->cache) && ($cache = $this->cache->get_cache($query))) {
            // Count how many queries there have been
            $this->num_queries++;
            return $cache;
        }

		$this->_do_query( $query );

		// MySQL server has gone away, try to reconnect
		$mysql_errno = 0;
		if ( ! empty( $this->dbh ) ) {
			if ( $this->use_mysqli ) {
				$mysql_errno = mysqli_errno( $this->dbh );
			} else {
				$mysql_errno = mysql_errno( $this->dbh );
			}
		}

		if ( empty( $this->dbh ) || 2006 == $mysql_errno ) {
			if ( $this->check_connection() ) {
				$this->_do_query( $query );
			} else {
				$this->insert_id = 0;
				return false;
			}
		}

		// If there is an error then take note of it..
		if ( $this->use_mysqli ) {
			$this->last_error = mysqli_error( $this->dbh );
		} else {
			$this->last_error = mysql_error( $this->dbh );
		}

		if ( $this->last_error ) {
			// Clear insert_id on a subsequent failed insert.
			if ( $this->insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) )
				$this->insert_id = 0;

            $this->print_error();
            return false;
        }
        
        $is_insert = false;
		if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $query ) ) {
            $return_val = $this->result;
		} elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
			if ( $this->use_mysqli ) {
				$this->rows_affected = mysqli_affected_rows( $this->dbh );
			} else {
				$this->rows_affected = mysql_affected_rows( $this->dbh );
			}
            // Take note of the insert_id
			if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
				if ( $this->use_mysqli ) {
					$this->insert_id = mysqli_insert_id( $this->dbh );
				} else {
					$this->insert_id = mysql_insert_id( $this->dbh );
				}
                $is_insert = true;
            }
            // Return number of rows affected
            $return_val = $this->rows_affected;
        } else {
            $num_rows = 0;
			if ( $this->use_mysqli && $this->result instanceof mysqli_result ) {
				while ( $row = @mysqli_fetch_object( $this->result ) ) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}
			} elseif ( is_resource( $this->result ) ) {
				while ( $row = @mysql_fetch_object( $this->result ) ) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}
			}

            // Log number of rows the query returned
            // and return number of rows selected
            $this->num_rows = $num_rows;
			$return_val     = $num_rows;
        }

        // disk caching of queries
        if ($this->use_cache && !empty($this->cache))
            $this->cache->store_cache($query, $is_insert);

        return $return_val;
    }
    
	/**
	 * Internal function to perform the mysql_query() call.
	 *
	 * @since 3.9.0
	 *
	 * @access private
	 * @see idb::query()
	 *
	 * @param string $query The query to run.
	 */
	private function _do_query( $query ) {
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$this->timer_start();
		}

		if ( $this->use_mysqli ) {
			$this->result = @mysqli_query( $this->dbh, $query );
		} else {
			$this->result = @mysql_query( $query, $this->dbh );
		}
		$this->num_queries++;

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			$this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );
		}
	}

    /**
     * Insert a row into a table.
     *
     * <code>
     * idb::insert( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
     * idb::insert( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
     * </code>
     *
     * @since 2.5.0
     * @see idb::prepare()
     * @see idb::$field_types
     *
     * @param string $table table name
     * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
     * Sending a null value will cause the column to be set to NULL - the corresponding format is ignored in this case.
     * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $data will be treated as strings unless otherwise specified in idb::$field_types.
     * @return int|false The number of rows inserted, or false on error.
     */
	public function insert( $table, $data, $format = null ) {
		return $this->_insert_replace_helper( $table, $data, $format, 'INSERT' );
    }

    /**
     * Replace a row into a table.
     *
     * <code>
     * idb::replace( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
     * idb::replace( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
     * </code>
     *
     * @since 3.0.0
     * @see idb::prepare()
     * @see idb::$field_types
     *
     * @param string $table table name
     * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
     * Sending a null value will cause the column to be set to NULL - the corresponding format is ignored in this case.
     * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $data will be treated as strings unless otherwise specified in idb::$field_types.
     * @return int|false The number of rows affected, or false on error.
     */
	public function replace( $table, $data, $format = null ) {
		return $this->_insert_replace_helper( $table, $data, $format, 'REPLACE' );
    }

    /**
     * Helper function for insert and replace.
     *
     * Runs an insert or replace query based on $type argument.
     *
     * @access private
     * @since 3.0.0
     * @see idb::prepare()
     * @see idb::$field_types
     *
     * @param string $table table name
     * @param array $data Data to insert (in column => value pairs).  Both $data columns and $data values should be "raw" (neither should be SQL escaped).
     * Sending a null value will cause the column to be set to NULL - the corresponding format is ignored in this case.
     * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
     *     A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in idb::$field_types.
     * @return int|false The number of rows affected, or false on error.
     */
	function _insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' ) {
		$this->insert_id = 0;

        if ( ! in_array( strtoupper( $type ), array( 'REPLACE', 'INSERT' ) ) ) {
            return false;
        }
        
		$data = $this->process_fields( $table, $data, $format );
		if ( false === $data ) {
			return false;
		}

		$formats = $values = array();
		foreach ( $data as $value ) {
            if ( is_null( $value['value'] ) ) {
                $formats[] = 'NULL';
                continue;
            }

			$formats[] = $value['format'];
			$values[]  = $value['value'];
		}

		$fields  = '`' . implode( '`, `', array_keys( $data ) ) . '`';
		$formats = implode( ', ', $formats );

		$sql = "$type INTO `$table` ($fields) VALUES ($formats)";

		
		$this->check_current_query = false;
		return $this->query( $this->prepare( $sql, $values ) );
    }

    /**
     * Update a row in the table
     *
     * <code>
     * idb::update( 'table', array( 'column' => 'foo', 'field' => 'bar' ), array( 'ID' => 1 ) )
     * idb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
     * </code>
     *
     * @since 2.5.0
     * @see idb::prepare()
     * @see idb::$field_types
     *
     * @param string $table table name
     * @param array $data Data to update (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
     * Sending a null value will cause the column to be set to NULL - the corresponding format is ignored in this case.
     * @param array $where A named array of WHERE clauses (in column => value pairs). Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw".
     * @param array|string $format Optional. An array of formats to be mapped to each of the values in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $data will be treated as strings unless otherwise specified in idb::$field_types.
	 * @param array|string $where_format Optional. An array of formats to be mapped to each of the values in $where. If string, that format will be used for all of the items in $where. A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $where will be treated as strings.
     * @return int|false The number of rows updated, or false on error.
     */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( ! is_array( $data ) || ! is_array( $where ) ) {
            return false;
        }

        $data = $this->process_fields( $table, $data, $format );
		if ( false === $data ) {
			return false;
		}
		$where = $this->process_fields( $table, $where, $where_format );
		if ( false === $where ) {
			return false;
		}

		$fields = $conditions = $values = array();
		foreach ( $data as $field => $value ) {
            if ( is_null( $value['value'] ) ) {
                $fields[] = "`$field` = NULL";
                continue;
            }

			$fields[] = "`$field` = " . $value['format'];
			$values[] = $value['value'];
		}
		foreach ( $where as $field => $value ) {
            if ( is_null( $value['value'] ) ) {
                $conditions[] = "`$field` IS NULL";
                continue;
            }

			$conditions[] = "`$field` = " . $value['format'];
			$values[] = $value['value'];
		}

		$fields = implode( ', ', $fields );
		$conditions = implode( ' AND ', $conditions );

		$sql = "UPDATE `$table` SET $fields WHERE $conditions";

		$this->check_current_query = false;
		return $this->query( $this->prepare( $sql, $values ) );
    }

    /**
     * Delete a row in the table
     *
     * <code>
     * idb::delete( 'table', array( 'ID' => 1 ) )
     * idb::delete( 'table', array( 'ID' => 1 ), array( '%d' ) )
     * </code>
     *
     * @since 3.4.0
     * @see idb::prepare()
     * @see idb::$field_types
     *
     * @param string $table table name
     * @param array $where A named array of WHERE clauses (in column => value pairs). Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw".
     * Sending a null value will create an IS NULL comparison - the corresponding format will be ignored in this case.
     * @param array|string $where_format Optional. An array of formats to be mapped to each of the values in $where. If string, that format will be used for all of the items in $where. A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $where will be treated as strings unless otherwise specified in idb::$field_types.
     * @return int|false The number of rows updated, or false on error.
     */
	public function delete( $table, $where, $where_format = null ) {
		if ( ! is_array( $where ) ) {
			return false;
		}

		$where = $this->process_fields( $table, $where, $where_format );
		if ( false === $where ) {
			return false;
		}

		$conditions = $values = array();
		foreach ( $where as $field => $value ) {
            if ( is_null( $value['value'] ) ) {
                $conditions[] = "`$field` IS NULL";
                continue;
            }

			$conditions[] = "`$field` = " . $value['format'];
			$values[] = $value['value'];
		}

		$conditions = implode( ' AND ', $conditions );

		$sql = "DELETE FROM `$table` WHERE $conditions";

		$this->check_current_query = false;
		return $this->query( $this->prepare( $sql, $values ) );
    }
    
	/**
	 * Processes arrays of field/value pairs and field formats.
	 *
	 * This is a helper method for idb's CRUD methods, which take field/value
	 * pairs for inserts, updates, and where clauses. This method first pairs
	 * each value with a format. Then it determines the charset of that field,
	 * using that to determine if any invalid text would be stripped. If text is
	 * stripped, then field processing is rejected and the query fails.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param string $table  Table name.
	 * @param array  $data   Field/value pair.
	 * @param mixed  $format Format for each field.
	 * @return array|bool Returns an array of fields that contain paired values
	 *                    and formats. Returns false for invalid values.
	 */
	protected function process_fields( $table, $data, $format ) {
		$data = $this->process_field_formats( $data, $format );
		$data = $this->process_field_charsets( $data, $table );
		if ( false === $data ) {
			return false;
		}

		$converted_data = $this->strip_invalid_text( $data );

		if ( $data !== $converted_data ) {
			return false;
		}

		return $data;
	}

	/**
	 * Prepares arrays of value/format pairs as passed to idb CRUD methods.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param array $data   Array of fields to values.
	 * @param mixed $format Formats to be mapped to the values in $data.
	 * @return array Array, keyed by field names with values being an array
	 *               of 'value' and 'format' keys.
	 */
	protected function process_field_formats( $data, $format ) {
		$formats = $original_formats = (array) $format;

		foreach ( $data as $field => $value ) {
			$value = array(
				'value'  => $value,
				'format' => '%s',
			);

			if ( ! empty( $format ) ) {
				$value['format'] = array_shift( $formats );
				if ( ! $value['format'] ) {
					$value['format'] = reset( $original_formats );
				}
			} elseif ( isset( $this->field_types[ $field ] ) ) {
				$value['format'] = $this->field_types[ $field ];
			}

			$data[ $field ] = $value;
		}

		return $data;
	}

	/**
	 * Adds field charsets to field/value/format arrays generated by
	 * the {@see idb::process_field_formats()} method.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param array  $data  As it comes from the {@see idb::process_field_formats()} method.
	 * @param string $table Table name.
	 * @return The same array as $data with additional 'charset' keys.
	 */
	protected function process_field_charsets( $data, $table ) {
		foreach ( $data as $field => $value ) {
			if ( '%d' === $value['format'] || '%f' === $value['format'] ) {
				// We can skip this field if we know it isn't a string.
				// This checks %d/%f versus ! %s because it's sprintf() could take more.
				$value['charset'] = false;
			} elseif ( $this->check_ascii( $value['value'] ) ) {
				// If it's ASCII, then we don't need the charset. We can skip this field.
				$value['charset'] = false;
			} else {
				$value['charset'] = $this->get_col_charset( $table, $field );
				
				// This isn't ASCII. Don't have strip_invalid_text() re-check.
				$value['ascii'] = false;
			}

			$data[ $field ] = $value;
		}

		return $data;
	}   

    /**
     * Retrieve one variable from the database.
     *
     * Executes a SQL query and returns the value from the SQL result.
     * If the SQL result contains more than one column and/or more than one row, this function returns the value in the column and row specified.
     * If $query is null, this function returns the value in the specified column and row from the previous SQL result.
     *
     * @since 0.71
     *
     * @param string|null $query Optional. SQL query. Defaults to null, use the result from the previous query.
	 * @param int $x Optional. Column of value to return. Indexed from 0.
	 * @param int $y Optional. Row of value to return. Indexed from 0.
     * @return string|null Database query result (as string), or null on failure
     */
	public function get_var( $query = null, $x = 0, $y = 0 ) {
        $this->func_call = "\$db->get_var(\"$query\", $x, $y)";
		
        if ( $query ) {
			$this->query( $query );
        }
        
        // Extract var out of cached results based x,y vals
		if ( !empty( $this->last_result[$y] ) ) {
			$values = array_values( get_object_vars( $this->last_result[$y] ) );
        }

        // If there is a value return it else return null
		return ( isset( $values[$x] ) && $values[$x] !== '' ) ? $values[$x] : null;
    }

    /**
     * Retrieve one row from the database.
     *
     * Executes a SQL query and returns the row from the SQL result.
     *
     * @since 0.71
     *
     * @param string|null $query SQL query.
     * @param string $output Optional. one of ARRAY_A | ARRAY_N | OBJECT constants. Return an associative array (column => value, ...),
	 * 	a numerically indexed array (0 => value, ...) or an object ( ->column = value ), respectively.
     * @param int $y Optional. Row to return. Indexed from 0.
	 * @return mixed Database query result in format specified by $output or null on failure
     */
	public function get_row( $query = null, $output = OBJECT, $y = 0 ) {
        $this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ( $query ) {
			$this->query( $query );
        } else {
            return null;
        }
        
		if ( !isset( $this->last_result[$y] ) )
            return null;

		if ( $output == OBJECT ) {
            return $this->last_result[$y] ? $this->last_result[$y] : null;
		} elseif ( $output == ARRAY_A ) {
			return $this->last_result[$y] ? get_object_vars( $this->last_result[$y] ) : null;
		} elseif ( $output == ARRAY_N ) {
			return $this->last_result[$y] ? array_values( get_object_vars( $this->last_result[$y] ) ) : null;
        } elseif ( strtoupper( $output ) === OBJECT ) {
			// Back compat for OBJECT being previously case insensitive.
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} else {
			$this->print_error( " \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N" );
        }
    }

    /**
     * Retrieve one column from the database.
     *
     * Executes a SQL query and returns the column from the SQL result.
     * If the SQL result contains more than one column, this function returns the column specified.
     * If $query is null, this function returns the specified column from the previous SQL result.
     *
     * @since 0.71
     *
     * @param string|null $query Optional. SQL query. Defaults to previous query.
     * @param int $x Optional. Column to return. Indexed from 0.
     * @return array Database query result. Array indexed from 0 by SQL result row number.
     */
	public function get_col( $query = null , $x = 0 ) {
		if ( $query ) {
			$this->query( $query );
        }
        
        $new_array = array();
        // Extract the column values
		for ( $i = 0, $j = count( $this->last_result ); $i < $j; $i++ ) {
			$new_array[$i] = $this->get_var( null, $x, $i );
        }
        return $new_array;
    }

    /**
     * Retrieve an entire SQL result set from the database (i.e., many rows)
     *
     * Executes a SQL query and returns the entire SQL result.
     *
     * @since 0.71
     *
     * @param string $query SQL query.
     * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants. With one of the first three, return an array of rows indexed from 0 by SQL result row number.
	 * 	Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
	 * 	With OBJECT_K, return an associative array of row objects keyed by the value of each row's first column's value. Duplicate keys are discarded.
     * @return mixed Database query results
     */
	public function get_results( $query = null, $output = OBJECT ) {
        $this->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query ) {
			$this->query( $query );
        } else {
            return null;
        }
        
        $new_array = array();
		if ( $output == OBJECT ) {
            // Return an integer-keyed array of row objects
            return $this->last_result;
		} elseif ( $output == OBJECT_K ) {
            // Return an array of row objects with keys from column 1
            // (Duplicates are discarded)
			foreach ( $this->last_result as $row ) {
				$var_by_ref = get_object_vars( $row );
				$key = array_shift( $var_by_ref );
				if ( ! isset( $new_array[ $key ] ) )
					$new_array[ $key ] = $row;
            }
            return $new_array;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
            // Return an integer-keyed array of...
			if ( $this->last_result ) {
				foreach( (array) $this->last_result as $row ) {
					if ( $output == ARRAY_N ) {
                        // ...integer-keyed row arrays
						$new_array[] = array_values( get_object_vars( $row ) );
                    } else {
                        // ...column name-keyed row arrays
						$new_array[] = get_object_vars( $row );
                    }
                }
            }
            return $new_array;
        } elseif ( strtoupper( $output ) === OBJECT ) {
			// Back compat for OBJECT being previously case insensitive.
			return $this->last_result;
		}
        return null;
    }

	/**
	 * Retrieves the character set for the given table.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param string $table Table name.
	 * @return string|FALSE Table character set,
	 */
	protected function get_table_charset( $table ) {
		$tablekey = strtolower( $table );

		if ( isset( $this->table_charset[ $tablekey ] ) ) {
			return $this->table_charset[ $tablekey ];
		}

		$charsets = $columns = array();
		$results = $this->get_results( "SHOW FULL COLUMNS FROM `$table`" );
		if ( ! $results ) {
			return false;
		}

		foreach ( $results as $column ) {
			$columns[ strtolower( $column->Field ) ] = $column;
		}

		$this->col_meta[ $tablekey ] = $columns;

		foreach ( $columns as $column ) {
			if ( ! empty( $column->Collation ) ) {
				list( $charset ) = explode( '_', $column->Collation );
				$charsets[ strtolower( $charset ) ] = true;
			}

			list( $type ) = explode( '(', $column->Type );

			// A binary/blob means the whole query gets treated like this.
			if ( in_array( strtoupper( $type ), array( 'BINARY', 'VARBINARY', 'TINYBLOB', 'MEDIUMBLOB', 'BLOB', 'LONGBLOB' ) ) ) {
				$this->table_charset[ $tablekey ] = 'binary';
				return 'binary';
			}
		}

		// utf8mb3 is an alias for utf8.
		if ( isset( $charsets['utf8mb3'] ) ) {
			$charsets['utf8'] = true;
			unset( $charsets['utf8mb3'] );
		}

		// Check if we have more than one charset in play.
		$count = count( $charsets );
		if ( 1 === $count ) {
			$charset = key( $charsets );
		} elseif ( 0 === $count ) {
			// No charsets, assume this table can store whatever.
			$charset = false;
		} else {
			// More than one charset. Remove latin1 if present and recalculate.
			unset( $charsets['latin1'] );
			$count = count( $charsets );
			if ( 1 === $count ) {
				// Only one charset (besides latin1).
				$charset = key( $charsets );
			} elseif ( 2 === $count && isset( $charsets['utf8'], $charsets['utf8mb4'] ) ) {
				// Two charsets, but they're utf8 and utf8mb4, use utf8.
				$charset = 'utf8';
			} else {
				// Two mixed character sets. ascii.
				$charset = 'ascii';
			}
		}

		$this->table_charset[ $tablekey ] = $charset;
		return $charset;
	}

	/**
	 * Retrieves the character set for the given column.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @return mixed Column character set as a string. False if the column has no
	 *               character set
	 */
	public function get_col_charset( $table, $column ) {
		$tablekey = strtolower( $table );
		$columnkey = strtolower( $column );

		// Skip this entirely if this isn't a MySQL database.
		if ( false === $this->is_mysql ) {
			return false;
		}

		if ( empty( $this->table_charset[ $tablekey ] ) ) {
			// This primes column information for us.
			$table_charset = $this->get_table_charset( $table );
			if ( !$table_charset ) {
				return $table_charset;
			}
		}

		// If still no column information, return the table charset.
		if ( empty( $this->col_meta[ $tablekey ] ) ) {
			return $this->table_charset[ $tablekey ];
		}

		// If this column doesn't exist, return the table charset.
		if ( empty( $this->col_meta[ $tablekey ][ $columnkey ] ) ) {
			return $this->table_charset[ $tablekey ];
		}

		// Return false when it's not a string column.
		if ( empty( $this->col_meta[ $tablekey ][ $columnkey ]->Collation ) ) {
			return false;
		}

		list( $charset ) = explode( '_', $this->col_meta[ $tablekey ][ $columnkey ]->Collation );
		return $charset;
	}

	/**
	 * Check if a string is ASCII.
	 *
	 * The negative regex is faster for non-ASCII strings, as it allows
	 * the search to finish as soon as it encounters a non-ASCII character.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param string $string String to check.
	 * @return bool True if ASCII, false if not.
	 */
	protected function check_ascii( $string ) {
		if ( function_exists( 'mb_check_encoding' ) ) {
			if ( mb_check_encoding( $string, 'ASCII' ) ) {
				return true;
			}
		} elseif ( ! preg_match( '/[^\x00-\x7F]/', $string ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Strips any invalid characters based on value/charset pairs.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param array $data Array of value arrays. Each value array has the keys
	 *                    'value' and 'charset'. An optional 'ascii' key can be
	 *                    set to false to avoid redundant ASCII checks.
	 * @return array|FALSE The $data parameter, with invalid characters removed from
	 *                        each value. This works as a passthrough: any additional keys
	 *                        such as 'field' are retained in each value array. If we cannot
	 *                        remove invalid characters
	 */
	protected function strip_invalid_text( $data ) {
		// Some multibyte character sets that we can check in PHP.
		$mb_charsets = array(
			'ascii'   => 'ASCII',
			'big5'    => 'BIG-5',
			'eucjpms' => 'eucJP-win',
			'gb2312'  => 'EUC-CN',
			'ujis'    => 'EUC-JP',
			'utf32'   => 'UTF-32',
		);

		$supported_charsets = array();
		if ( function_exists( 'mb_list_encodings' ) ) {
			$supported_charsets = mb_list_encodings();
		}

		$db_check_string = false;

		foreach ( $data as &$value ) {
			$charset = $value['charset'];

			// Column isn't a string, or is latin1, which will will happily store anything.
			if ( false === $charset || 'latin1' === $charset ) {
				continue;
			}

			if ( ! is_string( $value['value'] ) ) {
				continue;
			}

			// ASCII is always OK.
			if ( ! isset( $value['ascii'] ) && $this->check_ascii( $value['value'] ) ) {
				continue;
			}

			// Convert the text locally.
			if ( $supported_charsets ) {
				if ( isset( $mb_charsets[ $charset ] ) && in_array( $mb_charsets[ $charset ], $supported_charsets ) ) {
					$value['value'] = mb_convert_encoding( $value['value'], $mb_charsets[ $charset ], $mb_charsets[ $charset ] );
					continue;
				}
			}

			// utf8 can be handled by regex, which is a bunch faster than a DB lookup.
			if ( 'utf8' === $charset || 'utf8mb3' === $charset || 'utf8mb4' === $charset ) {
				$regex = '/
					(
						(?: [\x00-\x7F]                  # single-byte sequences   0xxxxxxx
						|   [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
						|   \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
						|   [\xE1-\xEC][\x80-\xBF]{2}
						|   \xED[\x80-\x9F][\x80-\xBF]
						|   [\xEE-\xEF][\x80-\xBF]{2}';

				if ( 'utf8mb4' === $charset) {
					$regex .= '
						|    \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
						|    [\xF1-\xF3][\x80-\xBF]{3}
						|    \xF4[\x80-\x8F][\x80-\xBF]{2}
					';
				}

				$regex .= '){1,50}                          # ...one or more times
					)
					| .                                  # anything else
					/x';
				$value['value'] = preg_replace( $regex, '$1', $value['value'] );
				continue;
			}

			// We couldn't use any local conversions, send it to the DB.
			$value['db'] = $db_check_string = true;
		}
		unset( $value ); // Remove by reference.

		if ( $db_check_string ) {
			$queries = array();
			foreach ( $data as $col => $value ) {
				if ( ! empty( $value['db'] ) ) {
					if ( ! isset( $queries[ $value['charset'] ] ) ) {
						$queries[ $value['charset'] ] = array();
					}

					// Split the CONVERT() calls by charset, so we can make sure the connection is right
					$queries[ $value['charset'] ][ $col ] = $this->prepare( "CONVERT( %s USING {$value['charset']} )", $value['value'] );
				}
			}

			$connection_charset = $this->charset;
			foreach ( $queries as $charset => $query ) {
				if ( ! $query ) {
					continue;
				}

				// Change the charset to match the string(s) we're converting
				if ( $charset !== $connection_charset ) {
					$connection_charset = $charset;
					$this->set_charset( $this->dbh, $charset );
				}

				$this->check_current_query = false;

				$row = $this->get_row( "SELECT " . implode( ', ', $query ), ARRAY_N );
				if ( ! $row ) {
					$this->set_charset( $this->dbh, $connection_charset );
					return false;
				}

				$cols = array_keys( $query );
				$col_count = count( $cols );
				for ( $ii = 0; $ii < $col_count; $ii++ ) {
					$data[ $cols[ $ii ] ]['value'] = $row[ $ii ];
				}
			}

			// Don't forget to change the charset back!
			if ( $connection_charset !== $this->charset ) {
				$this->set_charset( $this->dbh );
			}
		}

		return $data;
	}

	/**
	 * Strips any invalid characters from the query.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param string $query Query to convert.
	 * @return string|FALSE The converted query.
	 */
	protected function strip_invalid_text_from_query( $query ) {
		$table = $this->get_table_from_query( $query );
		if ( $table ) {
			$charset = $this->get_table_charset( $table );
			if ( !$charset ) {
				return $charset;
			}

			// We can't reliably strip text from tables containing binary/blob columns
			if ( 'binary' === $charset ) {
				return $query;
			}
		} else {
			$charset = $this->charset;
		}

		$data = array(
			'value'   => $query,
			'charset' => $charset,
			'ascii'   => false,
		);

		$data = $this->strip_invalid_text( array( $data ) );
		if ( !$data ) {
			return $data;
		}

		return $data[0]['value'];
	}

	/**
	 * Strips any invalid characters from the string for a given table and column.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name.
	 * @param string $value  The text to check.
	 * @return string|FALSE The converted string.
	 */
	public function strip_invalid_text_for_column( $table, $column, $value ) {
		if ( ! is_string( $value ) || $this->check_ascii( $value ) ) {
			return $value;
		}

		$charset = $this->get_col_charset( $table, $column );
		if ( ! $charset ) {
			// Not a string column.
			return $value;
		} elseif ( !$charset ) {
			// Bail on real errors.
			return $charset;
		}

		$data = array(
			$column => array(
				'value'   => $value,
				'charset' => $charset,
				'ascii'   => false,
			)
		);

		$data = $this->strip_invalid_text( $data );
		if ( !$data ) {
			return $data;
		}

		return $data[ $column ]['value'];
	}

	/**
	 * Find the first table name referenced in a query.
	 *
	 * @since 4.2.0
	 * @access protected
	 *
	 * @param string $query The query to search.
	 * @return string|false $table The table name found, or false if a table couldn't be found.
	 */
	protected function get_table_from_query( $query ) {
		// Remove characters that can legally trail the table name.
		$query = rtrim( $query, ';/-#' );

		// Allow (select...) union [...] style queries. Use the first query's table name.
		$query = ltrim( $query, "\r\n\t (" );

		/*
		 * Strip everything between parentheses except nested selects and use only 1,000
		 * chars of the query.
		 */
		$query = preg_replace( '/\((?!\s*select)[^(]*?\)/is', '()', substr( $query, 0, 1000 ) );

		// Quickly match most common queries.
		if ( preg_match( '/^\s*(?:'
				. 'SELECT.*?\s+FROM'
				. '|INSERT(?:\s+LOW_PRIORITY|\s+DELAYED|\s+HIGH_PRIORITY)?(?:\s+IGNORE)?(?:\s+INTO)?'
				. '|REPLACE(?:\s+LOW_PRIORITY|\s+DELAYED)?(?:\s+INTO)?'
				. '|UPDATE(?:\s+LOW_PRIORITY)?(?:\s+IGNORE)?'
				. '|DELETE(?:\s+LOW_PRIORITY|\s+QUICK|\s+IGNORE)*(?:\s+FROM)?'
				. ')\s+`?([\w-]+)`?/is', $query, $maybe ) ) {
			return $maybe[1];
		}

		// SHOW TABLE STATUS and SHOW TABLES
		if ( preg_match( '/^\s*(?:'
				. 'SHOW\s+TABLE\s+STATUS.+(?:LIKE\s+|WHERE\s+Name\s*=\s*)'
				. '|SHOW\s+(?:FULL\s+)?TABLES.+(?:LIKE\s+|WHERE\s+Name\s*=\s*)'
				. ')\W([\w-]+)\W/is', $query, $maybe ) ) {
			return $maybe[1];
		}

		// Big pattern for the rest of the table-related queries.
		if ( preg_match( '/^\s*(?:'
				. '(?:EXPLAIN\s+(?:EXTENDED\s+)?)?SELECT.*?\s+FROM'
				. '|DESCRIBE|DESC|EXPLAIN|HANDLER'
				. '|(?:LOCK|UNLOCK)\s+TABLE(?:S)?'
				. '|(?:RENAME|OPTIMIZE|BACKUP|RESTORE|CHECK|CHECKSUM|ANALYZE|REPAIR).*\s+TABLE'
				. '|TRUNCATE(?:\s+TABLE)?'
				. '|CREATE(?:\s+TEMPORARY)?\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?'
				. '|ALTER(?:\s+IGNORE)?\s+TABLE'
				. '|DROP\s+TABLE(?:\s+IF\s+EXISTS)?'
				. '|CREATE(?:\s+\w+)?\s+INDEX.*\s+ON'
				. '|DROP\s+INDEX.*\s+ON'
				. '|LOAD\s+DATA.*INFILE.*INTO\s+TABLE'
				. '|(?:GRANT|REVOKE).*ON\s+TABLE'
				. '|SHOW\s+(?:.*FROM|.*TABLE)'
				. ')\s+\(*\s*`?([\w-]+)`?\s*\)*/is', $query, $maybe ) ) {
			return $maybe[1];
		}

		return false;
	}    
    
    /**
	 * Load the column metadata from the last query.
	 *
	 * @since 3.5.0
	 *
	 * @access protected
	 */
	protected function load_col_info() {
		if ( $this->col_info )
			return;

		if ( $this->use_mysqli ) {
			for ( $i = 0; $i < @mysqli_num_fields( $this->result ); $i++ ) {
				$this->col_info[ $i ] = @mysqli_fetch_field( $this->result );
			}
		} else {
			for ( $i = 0; $i < @mysql_num_fields( $this->result ); $i++ ) {
				$this->col_info[ $i ] = @mysql_fetch_field( $this->result, $i );
			}
		}
	}

	/**
     * Retrieve column metadata from the last query.
     *
     * @since 0.71
     *
     * @param string $info_type Optional. Type one of name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
     * @param int $col_offset Optional. 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type
     * @return mixed Column Results
     */
	public function get_col_info( $info_type = 'name', $col_offset = -1 ) {
		$this->load_col_info();

		if ( $this->col_info ) {
			if ( $col_offset == -1 ) {
                $i = 0;
                $new_array = array();
				foreach( (array) $this->col_info as $col ) {
                    $new_array[$i] = $col->{$info_type};
                    $i++;
                }
                return $new_array;
            } else {
                return $this->col_info[$col_offset]->{$info_type};
            }
        }
    }

    /**
     * Starts the timer, for debugging purposes.
     *
     * @return bool
     */
    public function timer_start() {
		$this->time_start = microtime( true );
        return true;
    }

    /**
     * Stops the debugging timer.
     *
     * @since 1.5.0
     *
	 * @return float Total time spent on the query, in seconds
     */
    public function timer_stop() {
		return ( microtime( true ) - $this->time_start );
    }

    /**
     * Wraps errors in a nice header and footer and dies.
     *
	 * Will not die if idb::$show_errors is false.
     *
     * @since 1.5.0
     *
     * @param string $message The Error message
     * @param string $error_code Optional. A Computer readable string to identify the error.
     * @return false|void
     */
    public function bail($message, $error_code = '500') {
        if (!$this->show_errors) {
            $this->error = $message;
            return false;
        }
        exit($message);
    }

    /**
     * Whether MySQL database is at least the required minimum version.
     *
     * @since 2.5.0
     *
     * @return error
     */
    public function check_database_version() {
		global $required_mysql_version;
		
		if (empty($required_mysql_version))
			return true;

		// Make sure the server has the required MySQL version
		if (version_compare($this->db_version(), $required_mysql_version, '<') )
            return trigger_error("database_version: <strong>ERROR</strong>: App %s requires MySQL 4.1.2 or higher ", E_USER_ERROR);
    }
    
    /**
     * The database character collate.
     *
     * @since 3.5.0
     *
     * @return string The database character collate.
     */
    public function get_charset_collate() {
        $charset_collate = '';

        if ( ! empty( $this->charset ) )
                $charset_collate = "DEFAULT CHARACTER SET $this->charset";
        if ( ! empty( $this->collate ) )
                $charset_collate .= " COLLATE $this->collate";

        return $charset_collate;
    }

    /**
     * Determine if a database supports a particular feature.
     *
     * @since 2.7.0
     * @since 4.1.0 Support was added for the 'utf8mb4' feature.
     * 
     * @see idb::db_version()
     *
     * @param string $db_cap The feature to check for. Accepts 'collation',
     *                       'group_concat', 'subqueries', 'set_charset',
     *                       or 'utf8mb4'.
	 * @return int|false Whether the database feature is supported, false otherwise.
     */
    public function has_cap( $db_cap ) {
        $version = $this->db_version();

	switch ( strtolower( $db_cap ) ) {
            case 'collation' :    // @since 2.5.0
            case 'group_concat' : // @since 2.7.0
            case 'subqueries' :   // @since 2.7.0
		return version_compare( $version, '4.1', '>=' );
            case 'set_charset' :
		return version_compare( $version, '5.0.7', '>=' );
            case 'utf8mb4' :      // @since 4.1.0
                if ( version_compare( $version, '5.5.3', '<' ) ) {
                        return false;
                }
                if ( $this->use_mysqli ) {
                        $client_version = mysqli_get_client_info();
                } else {
                        $client_version = mysql_get_client_info();
                }

                /*
                 * libmysql has supported utf8mb4 since 5.5.3, same as the MySQL server.
                 * mysqlnd has supported utf8mb4 since 5.0.9.
                 */
                if ( false !== strpos( $client_version, 'mysqlnd' ) ) {
                        $client_version = preg_replace( '/^\D+([\d.]+).*/', '$1', $client_version );
                        return version_compare( $client_version, '5.0.9', '>=' );
                } else {
                        return version_compare( $client_version, '5.5.3', '>=' );
                }
	}

        return false;
    }

    /**
     * Retrieve the name of the function that called idb.
     *
     * Searches up the list of functions until it reaches
     * the one that would most logically had called this method.
     *
     * @since 2.5.0
     *
     * @return string The name of the calling function
     */
    public function get_caller() {
        $trace = array_reverse(debug_backtrace());
        $caller = array();

        foreach ($trace as $call) {
            if (isset($call['class']) && __CLASS__ == $call['class'])
                continue; // Filter out idb calls.
            $caller[] = isset($call['class']) ? "{$call['class']}->{$call['function']}" : $call['function'];
        }

        return join(', ', $caller);
    }
    
    /**
     * Retrieves the MySQL server version.
     *
     * @since 2.7.0
     *
     * @return null|string Null on failure, version number on success.
     */
    public function db_version() {
        if ( $this->use_mysqli ) {
                $server_info = mysqli_get_server_info( $this->dbh );
        } else {
                $server_info = mysql_get_server_info( $this->dbh );
        }
        return preg_replace( '/[^0-9.].*/', '', $server_info );
    }
}