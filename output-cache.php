<?php
namespace O10n;

/**
 * File Cache Output Controller
 *
 * @package    optimization
 * @subpackage optimization/controllers
 * @author     Optimization.Team <info@optimization.team>
 */
if (!defined('ABSPATH')) {
    exit;
}

class Filecache_Output
{
    // instance
    protected static $instance = null;

    private $config;
    private $stale = false;

    /**
     * Construct output controller
     */
    final public function __construct()
    {
        define('O10N_FILECACHE_ADVANCED_OUTPUT', true);
    }

    /**
     * Serve cache
     */
    final public static function load()
    {
        // construct controller
        self::$instance = new self();

        // serve output
        self::$instance->output();
    }

    /**
     * Output file cache
     */
    final public function output()
    {
        if (
            // optimization disabled
            (defined('O10N_DISABLED') && O10N_DISABLED)

            // file cache plugin disabled
            or (defined('O10N_DISABLED_FILECACHE') && O10N_DISABLED_FILECACHE)

            // cache disabled
            or (defined('O10N_NO_PAGE_CACHE') || isset($_GET['o10n-no-cache']))
        ) {
            return false;
        }

        // preload request
        if (isset($_SERVER['HTTP_X_O10N_FC_FORCE_UPDATE'])) {
            return false;
        }

        // disable cache
        if (is_admin() || !isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET' || (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php')) {
            return false;
        }

        // start of page cache output process
        $start = microtime(true);

        // cache directory
        $cache_dir = (defined('O10N_CACHE_DIR')) ? $this->trailingslashit(O10N_CACHE_DIR) . 'page-cache/' : $this->trailingslashit(WP_CONTENT_DIR) . 'cache/o10n/page-cache/';

        // load file cache config
        $config_file = $cache_dir . 'config.php';

        // get config from opcache
        $this->config = $this->opcache($config_file);
        if (!$this->config) {
            return false;
        }

        // check enabled setting
        if (!$this->bool('filecache.enabled')) {
            return false;
        }

        // custom cache hash
        $hash_format = false;
        if ($this->bool('filecache.hash.enabled')) {
            $hash_format = $this->get('filecache.hash.config');
        }

        // create hash
        $cachehash = self::cache_hash($hash_format);

        $cache_hash_dir = $cache_dir;

        // lowercase
        $hash = strtolower($cachehash);

        // create 3 levels of 2-char subdirectories, [a-z0-9]
        $dir_blocks = array_slice(str_split($hash, 2), 0, 3);
        foreach ($dir_blocks as $block) {
            $cache_hash_dir .= $block  . '/';
        }

        $cache_hash_filename = substr($cachehash, 6);

        $cache_file = $cache_hash_dir . $cache_hash_filename . '.php';
        $cache_meta_file = $cache_file . '.meta';

        // load cache meta and check if cache exists
        $pagemeta = $this->opcache($cache_meta_file);

        if ($pagemeta) {
            
            // 0 = timestamp
            // 1 = etag
            // 2 = PHP opcache
            // 3 = expire
            // 4 = headers (when opcache is disabled)

            // apply meta filter
            $pagemeta = apply_filters('o10n_page_cache_meta', $pagemeta);

            if (!$pagemeta) {
                return false;
            }

            // expired
            if (isset($pagemeta[3]) && ($pagemeta[0] + $pagemeta[3]) < time()) {

                // serve stale cache while cache is updated in the background
                if ($this->bool('filecache.stale.enabled')) {
                    $this->stale = (time() - ($pagemeta[0] + $pagemeta[3]));
                    $max_age = $this->get('filecache.stale.max_age');
                    if ($max_age && $this->stale > $max_age) {
                        return false;
                    }
                }
            }

            // get cache from PHP Opcache
            $gzipHTML = $responseHeaders = false;
            if ($pagemeta[2]) {
                $cachedata = $this->opcache($cache_file);
                if ($cachedata) {
                    if (isset($cachedata[0])) {
                        $gzipHTML = $cachedata[0];
                    }
                    if (isset($cachedata[1])) {
                        $responseHeaders = $cachedata[1];
                    }
                }
            } else {
                $gzipHTML = file_get_contents($cache_file);
                if (isset($pagemeta[4])) {
                    $responseHeaders = $pagemeta[4];
                }
            }
            
            // no cache data
            if (!$gzipHTML) {
                return false;
            }
   
            // return preload status
            if (isset($_SERVER['HTTP_X_O10N_FC_PRELOAD'])) {
                echo json_encode(array(
                    $pagemeta[0],
                    $this->stale
                ));

                if ($this->stale) {
                    $this->mark_stale();

                    return;
                } else {
                    exit;
                }
            }

            // cached headers
            $responseHeaders = apply_filters('o10n_page_cache_headers', $responseHeaders);
            if ($responseHeaders && !empty($responseHeaders)) {

                // add
                if (isset($responseHeaders[0]) && !empty($responseHeaders[0])) {
                    foreach ($responseHeaders[0] as $key => $value) {
                        header($key . ":" . $value);
                    }
                }

                // remove
                if (isset($responseHeaders[1]) && !empty($responseHeaders[1])) {
                    foreach ($responseHeaders[1] as $name) {
                        if (function_exists('header_remove')) {
                            header_remove($name);
                        } else {
                            header(sprintf('%s: ', $name), true);
                        }
                    }
                }
            }

            $utf8 = apply_filters('o10n_page_cache_utf8', true);
            if ($utf8) {
                header("Content-type: text/html; charset=UTF-8");
            } else {
                header("Content-type: text/html");
            }

            header("Last-Modified: ".gmdate("D, d M Y H:i:s", $pagemeta[0])." GMT");
            header("Etag: " . $pagemeta[1]);
            header('Vary: Accept-Encoding');

            // verify 304 status
            if (function_exists('apache_request_headers')) {
                $request = apache_request_headers();
                $modified = (isset($request[ 'If-Modified-Since' ])) ? $request[ 'If-Modified-Since' ] : null;
            } else {
                if (isset($_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ])) {
                    $modified = $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ];
                } else {
                    $modified = null;
                }
            }
            $last_modified = gmdate("D, d M Y H:i:s", $pagemeta[0]).' GMT';

            if (
                ($modified && $modified == $last_modified)
                || (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $pagemeta[1])
                ) {
                header("HTTP/1.1 304 Not Modified");
                exit;
            }

            // detect gzip support
            if (!isset($_SERVER[ 'HTTP_ACCEPT_ENCODING' ]) || (isset($_SERVER[ 'HTTP_ACCEPT_ENCODING' ]) && strpos($_SERVER[ 'HTTP_ACCEPT_ENCODING' ], 'gzip') === false)) {

                // uncompress for browsers that do not support GZIP
                $gzipHTML = gzdecode($gzipHTML);
            } else {

                // disable PHP output compression
                ini_set("zlib.output_compression", "Off");
                
                // set gzip output header
                header('Content-Encoding: gzip');
            }

            // add performance timing header
            $end = microtime(true);
            header('X-O10n-Cache: ' . number_format((($end - $start) * 1000), 5).'ms');
            
            // display opcache status
            if (defined('O10N_DEBUG') && O10N_DEBUG) {
                if ($pagemeta[2] && function_exists('opcache_is_script_cached')) {
                    header('X-O10n-Opcache: ' . (opcache_is_script_cached($cache_file) ? 'Yes' : 'Not in cache'));
                } else {
                    header('X-O10n-Opcache: Disabled');
                }
            }

            // add stale cache header
            if ($this->stale) {
                header('X-O10n-Cache-Stale: ' . $this->stale.'s');
            }

            header('Content-Length: ' . (function_exists('mb_strlen') ? mb_strlen($gzipHTML, '8bit') : strlen($gzipHTML)));

            // output cached HTML
            echo $gzipHTML;

            if (!$this->stale) {
                exit;
            } else {
                $this->mark_stale();
            }
        }
    }

    /**
     * Mark stale cache output
     */
    final private function mark_stale()
    {

        // mark stale cache (trigger background update)
        define('O10N_FILECACHE_SERVED_STALE', true);
        
        // avoid abortion of PHP process
        ignore_user_abort(true);
        
        if (function_exists('session_id') && session_id()) {
            session_write_close();
        }

        // PHP running under FastCGI
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (!headers_sent()) {
                header("Connection: close");
            }

            // flush output
            while (ob_get_level()) {
                ob_end_flush();
            }
            flush();
        }

        // capture output
        ob_start();
    }

    /**
     * Get option from config
     *
     * @param  string $key     Option key.
     * @param  string $Default Default value for non existing options.
     * @return mixed  Option data.
     */
    final public function get($key = false, $default = null)
    {
        // multi query
        if (substr($key, -2) === '.*') {
            $parent_key = substr($key, 0, -2);
            $keys = preg_grep('/'.preg_quote($parent_key).'\..*/', array_keys($this->config));

            $result = array();
            foreach ($keys as $key) {
                if (isset($this->config[$key])) {
                    $result[str_replace($parent_key.'.', '', $key)] = $this->config[$key];
                }
            }

            return $result;
        }

        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        if (!is_null($default)) {
            return $default;
        }

        return;
    }

    /**
     * Get boolean option from config
     *
     * @param  string  $key     Option key.
     * @param  string  $Default Default value for non existing options.
     * @return boolean True/false
     */
    final public function bool($keys, $default = false)
    {
        if (!is_array($keys)) {
            $keys = array($keys);
            $single = true;
        } else {
            $single = false;
        }
        foreach ($keys as $key) {
            if (isset($this->config[$key]) && is_bool($this->config[$key])) {
                if ($single || $this->config[$key]) {
                    return $this->config[$key];
                }
            } elseif (substr($key, -8) !== '.enabled') {
                $value = $this->bool($key . '.enabled');
                if ($single && is_bool($value)) {
                    return $value;
                } elseif ($value) {
                    return true;
                }
            }
        }

        return $default;
    }

    /**
     * Load PHP Opcache file
     */
    final private function opcache($file)
    {

        // get config from opcache
        try {

            // do not use file_exists to enable zero file IO (full memory) page cache

            $data = @include $file;
        } catch (\Exception $err) {
            return false;
        }

        return $data;
    }

    /**
     * Faster trailingslashit
     *
     * @link https://codex.wordpress.org/Function_Reference/trailingslashit
     *
     * @param string $path The path to add a trailing slash.
     */
    final private function trailingslashit($path, $separator = DIRECTORY_SEPARATOR)
    {
        return (substr($path, -1) === $separator) ? $path : $path . $separator;
    }

    /**
     * Calculate cache hash
     *
     * @param array $hash_format Custom hash format configuration
     */
    final public static function cache_hash($hash_format = false)
    {

        // environment variables
        $ssl = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
        $sp = strtolower($_SERVER['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
        $port = $_SERVER['SERVER_PORT'];
        $port = ((! $ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':'.$port;
        
        // host name
        $use_forwarded_host = apply_filters('o10n_pagecache_use_forwarded_host', false);
        $hostname = ($use_forwarded_host && isset($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null);
        $hostname = isset($host) ? $host : $_SERVER['SERVER_NAME'];
        $host = $hostname . $port;

        // request URL
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_url = $protocol . '://' . $host . $request_uri;

        if (!$hash_format || empty($hash_format)) {
            $hash_format = array('request_uri');
        }

        // construct cache hash
        $cache_hash_components = array();

        foreach ($hash_format as $component) {
            if (is_string($component)) {
                switch ($component) {
                    case "ssl":
                    case "protocol":
                    case "port":
                    case "hostname":
                    case "host":
                    case "request_uri":
                    case "request_url":
                        $cache_hash_components[] = $$component;
                    break;
                }
            } elseif (is_array($component) && isset($component['method'])) {
                if (function_exists($component['method']) && is_callable($component['method'])) {
                    $method = $component['method'];
                    $arguments = (isset($component['attributes'])) ? $component['attributes'] : null;

                    // call method
                    if ($arguments === null) {
                        $result = call_user_func($method);
                    } else {
                        $result = call_user_func_array($method, $arguments);
                    }

                    if (is_string($result) || is_numeric($result)) {
                        $cache_hash_components[] = (string)$result;
                    } else {
                        $cache_hash_components[] = json_encode($result);
                    }
                }
            }
        }

        // create hash
        return md5(implode(':', $cache_hash_components));
    }

    /**
     * Serve cache
     */
    final public static function serve()
    {
        self::$instance->output();
    }
}

// output cache
Filecache_Output::load();
