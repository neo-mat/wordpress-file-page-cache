<?php
namespace O10n;

/**
 * File Cache Controller
 *
 * @package    optimization
 * @subpackage optimization/controllers
 * @author     Optimization.Team <info@optimization.team>
 */
if (!defined('ABSPATH')) {
    exit;
}

class Filecache extends Controller implements Controller_Interface
{
    private $cache_enabled = null;
    private $opcache_enabled = null;
    private $cache_expire = 86400;

    private $is_preload = false;

    private $cache_dir;

    /**
     * Load controller
     *
     * @param  Core       $Core Core controller instance.
     * @return Controller Controller instance.
     */
    public static function &load(Core $Core)
    {
        // instantiate controller
        return parent::construct($Core, array(
            'url',
            'env',
            'options',
            'cache',
            'http',
            'shutdown',
            'file',
            'json'
        ));
    }

    /**
     * Setup controller
     */
    protected function setup()
    {
        if (!$this->env->is_optimization()) {
            return;
        }

        // return preload status
        if (isset($_SERVER['HTTP_X_O10N_FC_PRELOAD'])) {
            $this->is_preload = true;
        }

        // check if page cache is enabled
        if ($this->enabled()) {

            // cache directory
            $this->cache_dir = $this->file->directory_path('page-cache');

            // background update
            $this->stale_background_update();

            // output cache
            if (!defined('O10N_FILECACHE_ADVANCED_OUTPUT')) {
                require $this->core->modules('filecache')->dir_path() . 'output-cache.php';
            } else {

                // verify config

                // load file cache config
                $config_file = $this->cache_dir . 'config.php';

                if (!file_exists($config_file)) {

                    // get config
                    $config = $this->options->get('filecache.*', false, true);

                    // store in PHP Opcache
                    try {
                        $this->file->put_opcache($config_file, $config);

                        // retry cache
                        Filecache_Output::serve();

                        // background update
                        $this->stale_background_update();
                    } catch (\Exception $e) {
                        // failed
                    }
                }
            }
    
            // add filter for page cache
            add_filter('o10n_html_final', array( $this, 'update_cache' ), 1000, 1);
        } else {
            if ($this->is_preload) {

                // disabled
                $this->output_preload_status(-1);
            }
        }
    }

    /**
     * Get / set enabled state of page cache
     *
     * @param bool $state Enabled state
     */
    final public function enabled($state = null)
    {

        // set state
        if (!is_null($state)) {
            return $this->cache_enabled = $state;
        }

        if (!is_null($this->cache_enabled)) {
            return $this->cache_enabled;
        }

        if (defined('O10N_NO_PAGE_CACHE') || isset($_GET['o10n-no-cache'])) {
            return $this->cache_enabled = false;
        }

        // disable cache
        if (is_admin() || !isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET' || (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php')) {
            return $this->cache_enabled = false;
        }

        return $this->cache_enabled = $this->options->bool('filecache.enabled');
    }

    /**
     * Handle background update
     */
    final private function stale_background_update()
    {
        // served stale cache
        if (defined('O10N_FILECACHE_SERVED_STALE')) {

            // clear output
            while (ob_get_level()) {
                ob_end_clean();
            }

            $this->shutdown->add(array($this, 'update_stale'));
            exit;
        }
    }

    /**
     * Store page in cache
     *
     * @param string $buffer HTML buffer
     */
    final public function update_cache($buffer)
    {
        // disabled
        if (!$this->enabled()) {
            if ($this->is_preload) {
                return json_encode(array(-1));
            }

            return $buffer;
        }

        // empty HTML
        if (trim($buffer) === '') {
            if ($this->is_preload) {
                return json_encode(array(-2));
            }

            return $buffer;
        }

        // cache expire
        $this->cache_expire = $this->options->get('filecache.expire', $this->cache_expire);

        // request URL
        $url = $this->url->request();

        // bypass policy
        if ($this->options->bool('filecache.bypass.enabled')) {
            $bypass_policy = $this->options->get('filecache.bypass.config', array());
        } else {
            $bypass_policy = array(
                array(
                    'match' => 'condition',
                    'method' => 'is_user_logged_in'
                )
            );
        }

        // apply bypass policy
        if ($this->match_policy($bypass_policy, 'exclude')) {
            if ($this->is_preload) {
                return json_encode(array(-3));
            }

            return $buffer; // bypass cache
        }

        // verify cache policy
        if (!is_null($this->cache_enabled)) {
            $cache = $this->cache_enabled;
        } elseif ($this->options->bool('filecache.filter.enabled')) {
            $cache = $this->match_policy($this->options->get('filecache.filter.config', array()), $this->options->get('filecache.filter.type', 'include'));

            // exclude from cache
            if (isset($cache['bypass']) && $cache['bypass']) {
                $cache = false;

                // preload status
                if ($this->is_preload) {
                    return json_encode(array(-3));
                }
            }
        } else {
            $cache = true;
        }

        if ($cache) {

            // custom cache hash
            $hash_format = false;
            if ($this->options->bool('filecache.hash.enabled')) {
                $hash_format = $this->options->get('filecache.hash.config');
            }

            // cache hash
            $cachehash = Filecache_Output::cache_hash($hash_format);

            if (is_array($cache) && isset($cache['expire']) && is_numeric($cache['expire']) && intval($cache['expire']) > 1) {
                $this->cache_expire = intval($cache['expire']);
            }

            // search & replace
            $replace = $this->options->get('filecache.replace');
            if (isset($replace) && is_array($replace) && !empty($replace)) {
                $rs = $rr = array();
                foreach ($replace as $object) {
                    if (!isset($object['search']) || trim($object['search']) === '') {
                        continue;
                    }

                    if (isset($object['regex']) && $object['regex']) {
                        $rs[] = $object['search'];
                        $rr[] = $object['replace'];
                    } else {
                        $s[] = $object['search'];
                        $r[] = $object['replace'];
                    }
                }

                if (!empty($s)) {
                    $buffer = str_replace($s, $r, $buffer);
                }
                if (!empty($rs)) {
                    try {
                        $buffer = @preg_replace($rs, $rr, $buffer);
                    } catch (\Exception $err) {
                        // @todo log error
                    }
                }
            }

            // opcache policy
            if (!is_null($this->opcache_enabled)) {
                $opcache = $this->opcache_enabled;
            } elseif ($this->options->bool('filecache.opcache.enabled', false)) {
                if ($this->options->bool('filecache.opcache.filter.enabled')) {
                    $opcache = $this->match_policy($this->options->get('filecache.opcache.filter.config', array()), $this->options->get('filecache.opcache.filter.type', 'include'));
                } else {
                    $opcache = true;
                }
            } else {
                $opcache = false;
            }

            // response headers
            $response_headers = $this->response_headers();

            // cache meta
            $cachemeta = array(
                time(),
                md5($buffer),
                $opcache,
                $this->cache_expire
            );
            
            // cache content
            if ($opcache) {
                $cachedata = array(gzencode($buffer, 9), $response_headers);
            } else {
                $cachedata = gzencode($buffer, 9);
                $cachemeta[4] = $response_headers;
            }

            // store in cache
            $this->cache->put('filecache', 'page', $cachehash, $cachedata, false, false, $opcache, $cachemeta, true);

            header('X-O10n-Cache: MISS');

            if ($this->is_preload) {
                return json_encode(array(-4, isset($_SERVER['HTTP_X_O10N_FC_FORCE_UPDATE'])));
            }
        } else {

            // preload status
            if ($this->is_preload) {
                return json_encode(array(-1));
            }
        }

        return $buffer;
    }

    /**
     * Update stale cache
     */
    final public function update_stale()
    {
        // request URL
        $url = $this->url->request();

        // get request headers
        if (!function_exists('getallheaders')) {
            foreach ($_SERVER as $name => $value) {
                /* RFC2616 (HTTP/1.1) defines header fields as case-insensitive entities. */
                if (strtolower(substr($name, 0, 5)) == 'http_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            $request_headers = $headers;
        } else {
            $request_headers = getallheaders();
        }

        // wp HTTP API request
        $this->preload($url, $request_headers, true);
    }

    /**
     * Enable/disable PHP Opcache
     *
     * @param bool $state Enabled state
     */
    final public function opcache($state = true)
    {
        $this->opcache_enabled = $state;
    }

    /**
     * Set cache expire time for page
     *
     * @param int $timestamp Time in seconds
     */
    final public function expire($timestamp)
    {
        $this->cache_expire = $timestamp;
    }

    /**
     * Match policy
     *
     * @param array $policy Policy config
     */
    final private function match_policy($policy, $filter_type = 'include')
    {
        $match = ($filter_type === 'include') ? true : false;

        if (!is_array($policy)) {
            return $match;
        }

        // request URL
        $url = $this->url->request();

        foreach ($policy as $condition) {
            if ($filter_type === 'include' && is_array($match)) {
                break;
            }

            // url match
            if (is_string($condition)) {
                $condition = array(
                    'match' => 'uri',
                    'string' => $condition,
                );
            }

            if (!is_array($condition)) {
                continue;
            }

            switch ($condition['match']) {
                case "uri":
                    if (isset($condition['regex']) && $condition['regex']) {
                        try {
                            if (preg_match($condition['string'], $url)) {
                                if ($filter_type === 'exclude') {
                                    return $condition;
                                } else {
                                    $match = $condition;
                                }
                            }
                        } catch (\Exception $err) {
                        }
                    } else {
                        if (strpos($url, $condition['string']) !== false) {
                            if ($filter_type === 'exclude') {
                                return $condition;
                            } else {
                                $match = $condition;
                            }
                        }
                    }
                break;
                case "condition":

                    // method to call
                    $method = (isset($condition['method'])) ? $condition['method'] : false;
        
                    // verify method
                    if (!$method || !function_exists($method)) {
                        $this->admin->add_notice('File page cache condition method does not exist ('.$method.').', 'filecache');
                        continue;
                    }

                    // parameters to apply to method
                    $arguments = (isset($condition['arguments'])) ? $condition['arguments'] : null;

                    // result to expect from method
                    $expected_result = (isset($condition['result'])) ? $condition['result'] : true;

                    // call method
                    if ($arguments === null) {
                        $result = call_user_func($method);
                    } else {
                        $result = call_user_func_array($method, $arguments);
                    }

                    // expected result is array of options
                    if (is_array($expected_result)) {
                        if (in_array($result, $expected_result, true)) {
                            if ($filter_type === 'exclude') {
                                return $condition;
                            } else {
                                $match = $condition;
                            }
                        }
                    } else {
                        if ($result === $expected_result) {
                            if ($filter_type === 'exclude') {
                                return $condition;
                            } else {
                                $match = $condition;
                            }
                        }
                    }
                break;
            }
        }

        return $match;
    }


    /**
     * Header policy
     *
     * @param array $policy Policy config
     */
    final private function match_header_policy($header, $policy, $filter_type = 'include')
    {
        $match = ($filter_type === 'include') ? true : false;

        if (!is_array($policy)) {
            return $match;
        }

        foreach ($policy as $condition) {
            if ($filter_type === 'include' && is_array($match)) {
                break;
            }

            // url match
            if (is_string($condition)) {
                $condition = array(
                    'match' => $condition,
                    'remove' => ($filter_type === 'include') ? true : false
                );
            }

            if (!is_array($condition)) {
                continue;
            }

            if (isset($condition['regex']) && $condition['regex']) {
                try {
                    if (preg_match($condition['match'], $header)) {
                        if ($filter_type === 'exclude') {
                            return $condition;
                        } else {
                            $match = $condition;
                        }
                    }
                } catch (\Exception $err) {
                }
            } else {
                if (stripos($header, $condition['match']) !== false) {
                    if ($filter_type === 'exclude') {
                        return $condition;
                    } else {
                        $match = $condition;
                    }
                }
            }
        }

        return $match;
    }

    /**
     * Return response headers
     */
    final private function response_headers()
    {
        $headers = array();
        if (function_exists('apache_response_headers')) {
            $headers = apache_response_headers();
        }
        if (empty($headers) && function_exists('headers_list')) {
            $headers = array();
            foreach (headers_list() as $hdr) {
                $header_parts = explode(':', $hdr, 2);
                $header_name = isset($header_parts[0]) ? trim($header_parts[0]) : '';
                $header_value = isset($header_parts[1]) ? trim($header_parts[1]) : '';

                $headers[$header_name] = $header_value;
            }
        }

        $cached_headers = array(
            array(), // add
            array() // remove
        );

        $headers_policy = false;
        if ($this->options->bool('filecache.headers.enabled')) {
            $headers_policy_type = $this->options->get('filecache.headers.type', 'include');
            $headers_policy = $this->options->get('filecache.headers.config', array());
        }

        // ignore headers
        $ignore_headers = apply_filters('o10n_page_cache_ignore_headers', array('last-modified', 'etag', 'content-encoding', 'content-type'));

        // apply header policy
        foreach ($headers as $key => $value) {
            foreach ($ignore_headers as $header) {
                if (stripos($key, $header) !== false) {
                    continue 2;
                }
            }

            if ($headers_policy === false) {
                $cached_headers[0][$key] = $value;
            } else {
                $policy = $this->match_header_policy($key . ': ' . $value, $headers_policy, $headers_policy_type);
                if ($policy) {
                    if (!isset($policy['remove']) || !$policy['remove']) {
                        $cached_headers[0][$key] = $value;
                    }
                }
            }
        }

        // add removed headers
        if ($this->core->module_loaded('security')) {
            $removed_headers = $this->core->get('securityheaders')->removed_headers();
            $cached_headers[1] = $removed_headers;
        }

        return $cached_headers;
    }

    /**
     * Preload cache for page
     *
     * @param string $url             The URL to preload
     * @param array  $request_headers The request headers to include
     */
    final public function preload($url = false, $request_headers = false, $force_cache_update = false)
    {
        if (!defined('O10N_PRELOAD_REQUEST')) {
            define('O10N_PRELOAD_REQUEST', true);
        }

        $request = array();
        $request['timeout'] = 30; // default timeout

        $request['headers'] = array();

        if ($request_headers && is_array($request_headers)) {
            $request['headers'] = $request_headers;
        }

        if (is_array($url) && isset($url['url'])) {
            if (isset($url['headers']) && is_array($url['headers'])) {
                $request['headers'] = array_merge($request['headers'], $url['headers']);
            }
            if (isset($url['wp_http']) && is_array($url['wp_http'])) {
                $request = array_merge($request, $url['wp_http']);
            }
            if (isset($url['force_update'])) {
                $force_cache_update = ($url['force_update']) ? true : false;
            }
            $url = $url['url'];
        }

        if (!is_string($url)) {
            throw new Exception('Invalid URL for file cache preload', 'filecache');
        }

        // force cache update
        if ($force_cache_update) {
            $request['headers']['x-o10n-fc-force-update'] = 1;
        }

        // mark preload request (prevent sending page data to save bandwidth)
        $request['headers']['x-o10n-fc-preload'] = 1;

        // get asset content
        try {
            $response = $this->http->get($url, $request, false);
        } catch (HTTPException $e) {
            throw new Exception('Failed to preload file cache for ' . esc_url($url) . ' Status: '.$e->getStatus().' Error: ' . $e->getMessage(), 'filecache');
        }

        // invalid status code
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            throw new Exception('Failed to preload file cache for ' . esc_url($url) . ' Status: ' . $status, 'filecache');
        }

        $body = wp_remote_retrieve_body($response);
        try {
            $status = $this->json->parse($body, true);
        } catch (\Exception $err) {
            throw new Exception('Failed to parse preload request result ' . esc_url($url) . ' Error: ' . $err->getMessage() . ' Response: ' . esc_html(htmlentities(substr($body, 0, 200), ENT_COMPAT, 'utf-8')), 'filecache');
        }

        // OK
        return $status;
    }

    /**
     * Return preload processor status
     *
     * @param  array $query_config Query configuration
     * @return array List with URLs to preload
     */
    final public function preload_status()
    {

        // load file cache config
        if (!$this->cache_dir) {
            $this->cache_dir = $this->file->directory_path('page-cache');
        }
        $config_file = $this->cache_dir . 'preload-processor.php';

        // get status of running processor
        $status = false;
        if (file_exists($config_file)) {
            $status = file_get_contents($config_file);

            // already started
            try {
                $status = $this->json->parse($status, true);
            } catch (\Exception $err) {
                @unlink($config_file);
                $status = false;
            }
        }

        return $status;
    }

    /**
     * Preload processor
     *
     * @param  array $query_config Query configuration
     * @return array List with URLs to preload
     */
    final public function preload_processor()
    {

        // disabled
        if (!$this->options->bool('filecache.preload.enabled')) {
            return;
        }

        ignore_user_abort(true);

        // load file cache config
        if (!$this->cache_dir) {
            $this->cache_dir = $this->file->directory_path('page-cache');
        }
        $config_file = $this->cache_dir . 'preload-processor.php';

        // restart processor?
        $restart = true;

        // get status of running processor
        $status = $this->preload_status();

        // interval based start
        if ($this->options->bool('filecache.preload.interval.enabled')) {
            $interval = $this->options->get('filecache.preload.interval.interval', 86400);
        } else {

            // timing based start
            $interval = false;
            $start_time = $this->options->get('filecache.preload.interval.start', '04:00');
            $restart_time = strtotime(date('Y-m-d') . ' ' . $start_time);
        }

        // http query interval in milliseconds
        $http_interval = $this->options->get('filecache.preload.http_interval', false);

        if ($status && is_array($status) && isset($status['date'])) {
            if ($interval) {

                // already running
                if ($status['date'] > (time() - $interval)) {
                    $restart = false;
                }
            } else {

                // no time for restart
                if ($restart_time > time()) {
                    $restart = false;
                }

                // already running
                if ($restart_time === $status['start_time']) {
                    $restart = false;
                }
            }
        }

        $restart = true;

        if ($restart) {
            $status = array();
            $status['date'] = time();
            if (!$interval) {
                $status['start_time'] = $restart_time;
            }

            // get query config
            $query_config = $this->options->get('filecache.preload.query');
            if (!$query_config || empty($query_config)) {
                $query_config = false;
            }

            // get URLs to query
            $status['urls'] = $this->preload_query($query_config);

            // write preload processor status
            try {
                $this->file->put_contents($config_file, json_encode($status));
            } catch (\Exception $err) {
                throw new Exception('Failed to write preload processor status: ' . $err->getConfig(), 'filecache');
            }
        }

        if (isset($status['urls']) && !empty($status['urls'])) {

            // get config hash before start, abort on change
            $start_hash = md5_file($config_file);

            // urls are sorted on priority
            foreach ($status['urls'] as $url => $url_config) {
                if (!is_array($url_config)) {
                    $url_config = $status['urls'][$url] = array();
                }

                // not yet completed
                if (!isset($url_config['status'])) {

                    // preload
                    $url_config['url'] = $url;
                    if (!isset($url_config['timeout'])) {
                        $url_config['timeout'] = 30;
                    }

                    $start = microtime(true);

                    // reset PHP execution time limit
                    set_time_limit((isset($url_config['timeout'])) ? $url_config['timeout'] : 100);

                    try {
                        $preload_status = $this->preload($url_config);
                    } catch (Exception $err) {
                        $preload_status = array(-6, $err->getMessage());
                    }

                    // verify preload processor status
                    if ($start_hash !== md5_file($config_file)) {

                        // abort
                        return;
                    }

                    $status['urls'][$url]['status'] = $preload_status;
                    $status['urls'][$url]['start'] = $start;
                    $status['urls'][$url]['end'] = microtime(true);

                    $status['last_preload'] = time();

                    // write preload processor status
                    try {
                        $this->file->put_contents($config_file, json_encode($status));
                    } catch (\Exception $err) {
                        throw new Exception('Failed to write preload processor status: ' . $err->getConfig(), 'filecache');
                    }

                    // update hash
                    $start_hash = md5_file($config_file);

                    $ms_elapsed = round((microtime(true) - $start) * 1000);
                    
                    $url_http_interval = $http_interval;
                    if (isset($url_config['http_interval'])) {
                        if (!$url_config['http_interval']) {
                            $url_http_interval = false;
                        } elseif (is_numeric($url_config['http_interval'])) {
                            $url_http_interval = $url_config['http_interval'];
                        }
                    }

                    if ($url_http_interval && $ms_elapsed < $url_http_interval) {

                        // wait
                        usleep(round(($url_http_interval - $ms_elapsed) * 1000));
                    }
                }
            }
        }

        $status['completed'] = time();

        // write preload processor status
        try {
            $this->file->put_contents($config_file, json_encode($status));
        } catch (\Exception $err) {
            throw new Exception('Failed to write preload processor status: ' . $err->getConfig(), 'filecache');
        }
    }

    /**
     * Query URLs for preload processor
     *
     * @param  array $query_config Query configuration
     * @return array List with URLs to preload
     */
    final public function preload_query($query_config = false)
    {
        if (!$query_config || empty($query_config)) {
            $query_config = $this->preload_default_query();
        }

        $urls = array();

        // config keys
        $config_keys = array(
            'priority', 'force_update', 'wp_http', 'headers'
        );

        if (is_array($query_config)) {
            foreach ($query_config as $config) {
                $url_config = array();
                foreach ($config_keys as $key) {
                    if (isset($config[$key])) {
                        $url_config[$key] = $config[$key];
                    }
                }

                if (isset($config['url'])) {
                    $urls[$config['url']] = (empty($url_config)) ? 1 : $url_config;
                } elseif (isset($config['method']) && isset($config['link_method'])) {

                    // verify methods
                    if (!function_exists($config['method']) || !is_callable($config['method'])) {
                        throw new Exception('Preload query: method does not exist or is not callable <code>' . esc_html($config['method']) . '</code>', 'filecache');
                    }

                    // verify link methods
                    if (!function_exists($config['link_method']) || !is_callable($config['link_method'])) {
                        throw new Exception('Preload query: link method does not exist or is not callable <code>' . esc_html($config['link_method']) . '</code>', 'filecache');
                    }


                    // parameters to apply to method
                    $arguments = (isset($config['arguments'])) ? $config['arguments'] : null;

                    // call method
                    try {
                        if ($arguments === null) {
                            $results = call_user_func($config['method']);
                        } else {
                            $results = call_user_func_array($config['method'], $arguments);
                        }
                    } catch (\Exception $e) {
                        throw new Exception('Preload URL query failed <code>' . esc_html($config['method'] . '('.json_encode($arguments).')') . '</code> ' . $e->getMessage(), 'filecache');
                    }
                    foreach ($results as $result) {
                        try {
                            // retrieve link
                            $url = call_user_func_array($config['link_method'], array($result));
                        } catch (\Exception $e) {
                            throw new Exception('Preload URL link method failed <code>' . esc_html($config['method'] . '('.json_encode($arguments).') :: ' . $config['link_method'] . '()') . '</code> ' . $e->getMessage(), 'filecache');
                        }

                        $urls[$url] = (empty($url_config)) ? array() : $url_config;
                    }
                }
            }
        }

        // sort based on priority
        uasort($urls, function ($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return 0;
            }

            return ($a['priority'] < $b['priority']) ? -1 : 1;
        });

        return $urls;
    }

    /**
     * Return default URL query config
     *
     * @param  array $query_config Query configuration
     * @return array List with URLs to preload
     */
    final public function preload_default_query()
    {
        return array(

            // front page
            array(
                'url' => home_url(),
                'priority' => 1
            ),
            array(
                'method' => 'get_pages',
                'link_method' => 'get_permalink',
                'priority' => 2
            ),
            array(
                'method' => 'get_posts',
                'link_method' => 'get_permalink',
                'priority' => 5
            ),
            array(
                'method' => 'get_categories',
                'arguments' => array(array(
                    'hide_empty' => true,
                    'depth' => 0,
                    'hierarchical' => true,
                    'orderby' => 'count',
                    'order' => 'desc'
                )),
                'link_method' => 'get_category_link',
                'priority' => 10
            ),
            array(
                'method' => 'get_terms',
                'arguments' => array(array(
                    'taxonomy' => 'post_tag',
                    'orderby' => 'count',
                    'order' => 'desc',
                    'hide_empty' => true
                )),
                'link_method' => 'get_term_link',
                'priority' => 15
            )
        );
    }

    /**
     * Output JSON status for preload processor
     */
    final private function output_preload_status($status)
    {
        if (!is_array($status)) {
            $status = array($status);
        }

        $status = json_encode($status);
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($status));
        echo $status;
        exit;
    }
}
