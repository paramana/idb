# idb

A MySQL database class for PHP, based on WordPress's `wpdb` with optional query caching (disk, Memcache or Memcached).

It started from [ezSQL](http://php.justinvincent.com) by Justin Vincent and is kept in sync with the [WordPress](https://wordpress.org/) database class (currently WordPress commit `23c1de7`, August 2025). The main addition over `wpdb` is the result cache, which originally came from ezSQL.

## Requirements

- PHP 8 with the `mysqli` extension
- For caching: write access to a cache directory, or the `memcache` / `memcached` PHP extension

## Installation

With Composer, add the repository and require the package:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/paramana/idb" }
    ],
    "require": {
        "paramana/idb": "dev-master"
    }
}
```

Or copy the files into your project and include `db.class.php`.

## Usage

```php
define('DEBUG', false); // required, see Configuration

require_once 'db.class.php';

$db = new idb('db_user', 'db_password', 'db_name', 'localhost');

// Read
$count = $db->get_var("SELECT COUNT(*) FROM users");
$user  = $db->get_row($db->prepare("SELECT * FROM users WHERE id = %d", 42));
$names = $db->get_col("SELECT name FROM users");
$rows  = $db->get_results("SELECT * FROM users", ARRAY_A);

// Write
$db->insert('users', ['name' => 'Ada', 'email' => 'ada@example.com'], ['%s', '%s']);
$new_id = $db->insert_id;

$db->update('users', ['name' => 'Ada L.'], ['id' => $new_id], ['%s'], ['%d']);
$db->delete('users', ['id' => $new_id], ['%d']);
```

The API matches `wpdb`, so the [WordPress `wpdb` reference](https://developer.wordpress.org/reference/classes/wpdb/) applies: `query()`, `prepare()`, `esc_like()`, `replace()`, `get_col_info()`, `show_errors()` / `hide_errors()`, and so on. Results can be returned as `OBJECT`, `OBJECT_K`, `ARRAY_A` or `ARRAY_N`.

Always use `prepare()` for queries that include user input.

## Configuration

Settings are read from constants. Define them before creating the `idb` object.

| Constant | Description |
| --- | --- |
| `DEBUG` | **Required.** When true, database errors are printed. Must be defined (even as `false`) or PHP will throw an error. |
| `DB_CHARSET` | Connection charset, e.g. `utf8mb4`. |
| `DB_COLLATE` | Connection collation. |
| `SAVEQUERIES` | When true, every query is stored in `$db->queries` with its run time and caller. |
| `MYSQL_CLIENT_FLAGS` | Extra flags passed to `mysqli_real_connect()`. |

## Caching

When enabled, the results of each query are cached using the query text as the key, and repeated queries are served from the cache instead of the database.

```php
define('DB_CACHE', true);
define('DB_CACHE_TYPE', 'disk');                // disk, memcache or memcached
define('DB_CACHE_DIR', __DIR__ . '/cache/db');  // disk cache only
define('DB_CACHE_TIMEOUT', 3600);               // seconds
```

| Constant | Default | Description |
| --- | --- | --- |
| `DB_CACHE` | `false` | Turns caching on. |
| `DB_CACHE_TYPE` | — | Cache backend: `disk`, `memcache` or `memcached`. Caching only runs when this is set. |
| `DB_CACHE_TIMEOUT` | `86400` | How long results stay cached, in seconds. |
| `DB_CACHE_INSERTS` | `false` | Also cache the results of `INSERT` queries. |
| `DB_CACHE_PREFIX` | `""` | Prefix added to cache keys. Useful when several apps share one Memcached server. |
| `DB_CACHE_DIR` | library folder | Disk backend: where cache files are written. Set this to a dedicated, writable folder, since clearing the cache deletes every file in it. |
| `DB_MEMCACHED_HOST` | `localhost` | Memcache/Memcached backend: server host. |
| `DB_MEMCACHED_PORT` | `11211` | Memcache/Memcached backend: server port. |

Disk cache files are compressed with `gzdeflate` and removed when they expire.

To clear the whole cache:

```php
$db->clear_cache();
```

## License

[LGPL-3.0](LICENSE)
