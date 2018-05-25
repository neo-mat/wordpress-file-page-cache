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
            'shutdown'
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

        // served stale cache
        if (defined('O10N_FILECACHE_SERVED_STALE')) {
            $this->shutdown->add(array($this, 'update_stale'));
            exit;
        }

        // File Page Cache requires SSL
        if ($this->options->bool('filecache.enabled')) {

            // verify if page is cached
            if ((is_null($this->cache_enabled) || $this->cache_enabled) && !defined('O10N_NO_PAGE_CACHE') && !isset($_GET['o10n-no-cache'])) {

                // output cache
                if (!defined('O10N_FILECACHE_ADVANCED_OUTPUT')) {
                    require $this->core->modules('filecache')->dir_path() . 'output-cache.php';
                }
        
                // add filter for page cache
                add_filter('o10n_html_final', array( $this, 'update_cache' ), 1000, 1);
            }
        }
    }

    /**
     * Store page in cache
     *
     * @param string $buffer HTML buffer
     */
    final public function update_cache($buffer)
    {
        if ((!is_null($this->cache_enabled) && !$this->cache_enabled) || defined('O10N_NO_PAGE_CACHE') || isset($_GET['o10n-no-cache'])) {
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

        if ($this->match_policy($bypass_policy, 'exclude')) {
            //return $buffer; // bypass cache
        }

        // verify cache policy
        if (!is_null($this->cache_enabled)) {
            $cache = $this->cache_enabled;
        } elseif ($this->options->bool('filecache.filter.enabled')) {
            $cache = $this->match_policy($this->options->get('filecache.filter.config', array()), $this->options->get('filecache.filter.type', 'include'));

            // exclude from cache
            if (isset($cache['bypass']) && $cache['bypass']) {
                $cache = false;
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
                $cachedata = array(gzdeflate($buffer, 9, FORCE_GZIP), $response_headers);
            } else {
                $cachedata = gzdeflate($buffer, 9, FORCE_GZIP);
                $cachemeta[4] = $response_headers;
            }

            // store in cache
            $this->cache->put('filecache', 'page', $cachehash, $cachedata, false, false, $opcache, $cachemeta, true);

            header('X-O10n-Cache: MISS');
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

        // wp HTTP API request

        file_put_contents(trailingslashit(O10N_CACHE_DIR) . 'test.txt', 'test');
    }

    /**
     * Enable/disable page cache
     *
     * @param bool $state Enabled state
     */
    final public function enable($state = true)
    {
        $this->cache_enabled = $state;
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
                        if (isset($method_cache[$method])) {
                            $result = $method_cache[$method];
                        } else {
                            $result = $method_cache[$method] = call_user_func($method);
                        }
                    } else {
                        $arguments_key = json_encode($arguments);

                        if (isset($method_cache[$method]) && isset($method_cache[$method][$arguments_key])) {
                            $result = $method_cache[$method][$arguments_key];
                        } else {
                            if (!isset($method_param_cache[$method])) {
                                $method_param_cache[$method] = array();
                            }
                            $result = $method_param_cache[$method][$arguments_key] = call_user_func_array($method, $arguments);
                        }
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
        $request = array();
        if ($request_headers) {
            $request['headers'] = $request_headers;
        }

        // force cache update
        if ($force_cache_update) {
            if (!isset($request['headers'])) {
                $request['headers'] = array();
            }
            $request['headers']['x-o10n-fc-force'] = 1;
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

        // OK
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
            array(
                'method' => 'get_pages',
                'link_method' => 'get_permalink',
                'priority' => 1
            ),
            array(
                'method' => 'get_posts',
                'link_method' => 'get_permalink',
                'priority' => 5
            ),
            array(
                'method' => 'get_categories',
                'arguments' => array(
                    'hide_empty' => true,
                    'depth' => 0,
                    'hierarchical' => true,
                    'orderby' => 'count',
                    'order' => 'desc'
                ),
                'link_method' => 'get_category_link',
                'priority' => 10
            ),
            array(
                'method' => 'get_terms',
                'arguments' => array(
                    'taxonomy' => 'post_tag',
                    'orderby' => 'count',
                    'order' => 'desc',
                    'hide_empty' => true
                ),
                'link_method' => 'get_term_link',
                'priority' => 15
            )
        );
    }
}
