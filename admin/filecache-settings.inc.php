<?php
namespace O10n;

/**
 * File page cache admin template
 *
 * @package    optimization
 * @subpackage optimization/admin
 * @author     Optimization.Team <info@optimization.team>
 */
if (!defined('ABSPATH') || !defined('O10N_ADMIN')) {
    exit;
}

// print form header
$this->form_start(__('File Page Cache', 'o10n'), 'filecache');

?>

<table class="form-table">
    <tr valign="top">
        <th scope="row">Page Cache</th>
        <td>
            
            <label><input type="checkbox" name="o10n[filecache.enabled]" data-json-ns="1" value="1"<?php $checked('filecache.enabled'); ?>> Enable
</label>
            <p class="description" style="margin-bottom:1em;">When enabled, HTML pages are cached using static files.</p>

            <div class="suboption" data-ns="filecache"<?php $visible('filecache'); ?>>

<?php
    // verify availability of advanced-cache.php
    $advanced_cache_file = WP_CONTENT_DIR . '/advanced-cache.php';
    if (!file_exists($advanced_cache_file)) {
        print '<div class="info_yellow" style="line-height: 24px;padding-bottom: 8px;padding-top: 8px;"><strong><span class="dashicons dashicons-dashboard"></span></strong> Install <a href="https://make.wordpress.org/core/2016/08/13/global-overloading-in-advanced-cache-php/" target="_blank">advanced-cache.php</a> to improve cache performance (serve cache before MySQL). To enable this feature you need to add <code>define(\'WP_CACHE\', true);</code> in wp-config.php.
        <p><label><input type="checkbox" value="1" name="o10n[filecache.advanced_cache]" /> Create advanced-cache.php</label></p></div>';
    } else {
        $advanced_cache_content = file_get_contents($advanced_cache_file);
        if (strpos($advanced_cache_content, $view->module->dir_path() . 'output-cache.php') === false) {
            print '<div class="info_yellow" style="line-height: 24px;padding-bottom: 8px;padding-top: 8px;"><strong><span class="dashicons dashicons-dashboard"></span></strong> The installed <a href="https://make.wordpress.org/core/2016/08/13/global-overloading-in-advanced-cache-php/" target="_blank">advanced-cache.php</a> is from a different plugin. advanced-cache.php can improve performance (serve cache before MySQL). Overwrite advanced-cache.php with the version of this plugin (<a href="' . $view->module->dir_url() . 'advanced-cache.sample.txt" download="advanced-cache.php">download sample</a>) or manually include <code title="'.esc_attr($view->module->dir_path() . 'output-cache.php').'">'.$this->file->safe_path($view->module->dir_path()).'output-cache.php</code> in the existing advanced-cache.php file.
                <p><label><input type="checkbox" value="1" name="o10n[filecache.advanced_cache]" /> Overwrite advanced-cache.php</label></p>
            </div>';
        } else {
            if (!defined('WP_CACHE') || !WP_CACHE) {
                print '<div class="info_yellow" style="line-height: 20px;padding-bottom: 5px;padding-top: 8px;"><strong><span class="dashicons dashicons-dashboard"></span></strong> <a href="https://make.wordpress.org/core/2016/08/13/global-overloading-in-advanced-cache-php/" target="_blank">advanced-cache.php</a> is installed successfully however, to enable it in WordPress you need to add <code>define(\'WP_CACHE\', true);</code> in wp-config.php.</p>
                </div>';
            } else {
                print '<div class="ok_green" style="border-width:1px;line-height: 20px;padding-bottom: 5px;padding-top: 8px;"><strong><span class="dashicons dashicons-dashboard"></span></strong> <a href="https://make.wordpress.org/core/2016/08/13/global-overloading-in-advanced-cache-php/" target="_blank">advanced-cache.php</a> is installed successfully.</p>
                </div>';
            }
        }
    }
?>

                <label><input type="checkbox" value="1" name="o10n[filecache.filter.enabled]" data-json-ns="1"<?php $checked('filecache.filter.enabled'); ?> /> Enable cache policy</label>
                <span data-ns="filecache.filter"<?php $visible('filecache.filter'); ?>>
                    <select name="o10n[filecache.filter.type]" data-ns-change="filecache.filter" data-json-default="<?php print esc_attr(json_encode('include')); ?>">
                        <option value="include"<?php $selected('filecache.filter.type', 'include'); ?>>Include by default</option>
                        <option value="exclude"<?php $selected('filecache.filter.type', 'exclude'); ?>>Exclude by default</option>
                    </select>
                </span>
                <p class="description">The cache policy filter enables to cache pages based on a include/exclude policy. To exclude pages from cache, you can also use the dedicated bypass policy (see below).</p>
            </div>

            <div class="suboption" data-ns="filecache.filter"<?php $visible('filecache.filter'); ?>>
                <h5 class="h">&nbsp;Cache Policy</h5>
                    <div id="filecache-filter-config"><div class="loading-json-editor"><?php print __('Loading JSON editor...', 'o10n'); ?></div></div>
                    <input type="hidden" class="json" name="o10n[filecache.filter.config]" data-json-type="json-array" data-json-editor-height="auto" data-json-editor-init="1" value="<?php print esc_attr($json('filecache.filter.config')); ?>" />
                    <p class="description">Enter a JSON array with objects. (<a href="javascript:void(0);" onclick="jQuery('#cache_policy_example').fadeToggle();">show example</a>)</p>
                    <div class="info_yellow" id="cache_policy_example" style="display:none;"><strong>Example:</strong> <pre class="clickselect" title="<?php print esc_attr('Click to select', 'optimization'); ?>" style="cursor:copy;padding: 10px;margin: 0 1px;margin-top:5px;font-size: 13px;">[
    "page-url",
    "/other/page/url",
    {
        "match": "uri",
        "string": "/page-uri-(x|y)/",
        "regex": true,
        "expire": 1800,
        "stale": 3600
    },
    {
        "match": "condition",
        "method": "is_page",
        "expire": 86400,
        "stale": false
    },
    {
        "match": "condition",
        "method": "is_page",
        "arguments": [[1,6,19]],
        "expire": 3600
    },
    {
        "match": "condition",
        "method": "is_user_logged_in",
        "bypass": true
    }
]</pre></div>
            </div>

            <div class="suboption" data-ns="filecache"<?php $visible('filecache'); ?>>
                <label><input type="checkbox" value="1" name="o10n[filecache.bypass.enabled]" data-json-ns="1"<?php $checked('filecache.bypass.enabled'); ?> /> Enable cache bypass policy</label>
                <p class="description">By default, cache is bypassed for logged in users. This option enables to define a custom bypass policy.</p>

                <p class="suboption info_yellow">You can manually enable or disable the page cache using the method <code>\O10n\page_cache(true|false);</code>.</p>
            </div>

            <div class="suboption" data-ns="filecache.bypass"<?php $visible('filecache.bypass'); ?>>
                <h5 class="h">&nbsp;Cache Bypass Policy</h5>
                    <div id="filecache-bypass-config"><div class="loading-json-editor"><?php print __('Loading JSON editor...', 'o10n'); ?></div></div>
                    <input type="hidden" class="json" name="o10n[filecache.bypass.config]" data-json-type="json-array" data-json-editor-height="auto" data-json-editor-init="1" value="<?php print esc_attr($json('filecache.bypass.config', array( array( "match" => "condition", "method" => "is_user_logged_in" ) ))); ?>" />
                    <p class="description">Enter a JSON array with objects. (<a href="javascript:void(0);" onclick="jQuery('#cache_bypass_policy_example').fadeToggle();">show example</a>)</p>
                    <div class="info_yellow" id="cache_bypass_policy_example" style="display:none;"><strong>Example:</strong> <pre class="clickselect" title="<?php print esc_attr('Click to select', 'optimization'); ?>" style="cursor:copy;padding: 10px;margin: 0 1px;margin-top:5px;font-size: 13px;">[
    "page-url",
    "/other/page/url",
    {
        "match": "condition",
        "method": "is_user_logged_in"
    },
    {
        "match": "uri",
        "string": "/page-uri-(x|y)/",
        "regex": true
    },
    {
        "match": "condition",
        "method": "is_page",
        "arguments": [[1,6,19]]
    }
]</pre></div>
            </div>

            <div class="suboption" data-ns="filecache"<?php $visible('filecache'); ?>>
                <label><input type="checkbox" value="1" name="o10n[filecache.headers.enabled]" data-json-ns="1"<?php $checked('filecache.hash.enabled'); ?> /> Enable HTTP header cache policy</label>
                <span data-ns="filecache.headers"<?php $visible('filecache.headers'); ?>>
                    <select name="o10n[filecache.headers.type]" data-ns-change="filecache.headers" data-json-default="<?php print esc_attr(json_encode('include')); ?>">
                        <option value="include"<?php $selected('filecache.headers.type', 'include'); ?>>Include by default</option>
                        <option value="exclude"<?php $selected('filecache.headers.type', 'exclude'); ?>>Exclude by default</option>
                    </select>
                </span>
                <p class="description">By default, all HTTP headers are included in the cache. This option enables to define what headers should be included and to add custom headers for pages served from cache.</p>
            </div>

            <div class="suboption" data-ns="filecache.headers"<?php $visible('filecache.headers'); ?>>
             <h5 class="h">&nbsp;HTTP Header Cache Policy</h5>
            <div id="filecache-headers-config"><div class="loading-json-editor"><?php print __('Loading JSON editor...', 'o10n'); ?></div></div>
            <input type="hidden" class="json" name="o10n[filecache.headers.config]" data-json-type="json-array" data-json-editor-height="auto" data-json-editor-init="1" value="<?php print esc_attr($json('filecache.headers.config')); ?>" />
            <p class="description">Enter a JSON array with with objects. (<a href="javascript:void(0);" onclick="jQuery('#http_header_policy_example').fadeToggle();">show example</a>)</p>
            <div class="info_yellow" id="http_header_policy_example" style="display:none;"><strong>Example:</strong> <pre class="clickselect" title="<?php print esc_attr('Click to select', 'optimization'); ?>" style="cursor:copy;padding: 10px;margin: 0 1px;margin-top:5px;font-size: 13px;">[
    "x-header-to-include",
    {
        "match": "cookie",
        "remove": true
    },
    {
        "match": "/custom-header-value/i",
        "regex": true,
        "remove": true
    },
    {
        "key": "x-my-custom-header",
        "value": "OK"
    }
]</pre></div>
            </div>

            <div class="suboption" data-ns="filecache"<?php $visible('filecache'); ?>>
                <label><input type="checkbox" value="1" name="o10n[filecache.hash.enabled]" data-json-ns="1"<?php $checked('filecache.hash.enabled'); ?> /> Custom cache hash</label>
                <p class="description">By default the cache MD5 hash is calculated based on the request URL. This option enables to customize the hash using PHP variables and methods to support caching of dynamic content (multiple cache versions for the same URL).</p>
            </div>

            <div class="suboption" data-ns="filecache.hash"<?php $visible('filecache.hash'); ?>>
             <h5 class="h">&nbsp;Cache Hash Format</h5>
            <div id="filecache-hash-config"><div class="loading-json-editor"><?php print __('Loading JSON editor...', 'o10n'); ?></div></div>
            <input type="hidden" class="json" name="o10n[filecache.hash.config]" data-json-type="json-array" data-json-editor-height="auto" data-json-editor-init="1" value="<?php print esc_attr($json('filecache.hash.config', array('request_uri'))); ?>" />
            <p class="description">Enter a JSON array with hash variables. (<a href="javascript:void(0);" onclick="jQuery('#hash_example').fadeToggle();">show example</a>)</p>
            <div class="info_yellow" id="hash_example" style="display:none;"><strong>Example:</strong> <p class="description">The example shows how to calculate the cache hash based on the request URL without a query string. The <code>page_cache_hash_no_query_string</code> method is provided by the File Cache plugin and strips the query string from the URL. The example cache hash config will return the same cache for pages with any query string and it can add security to prevent a cache storage attack. You can manually set a cache hash for individual pages using the cache policy configuration to cache specific query strings while caching random query strings is prevented.<pre class="clickselect" title="<?php print esc_attr('Click to select', 'optimization'); ?>" style="cursor:copy;padding: 10px;margin: 0 1px;margin-top:5px;font-size: 13px;">[
  {
    "method": "page_cache_hash_no_query_string"
  }
]</pre></div>
            </div>

            <div class="suboption" data-ns="filecache"<?php $visible('filecache'); ?>>
                <h5 class="h">&nbsp;Cache Expire</h5>
                <input type="number" style="width:120px;" min="1" name="o10n[filecache.expire]" value="<?php $value('filecache.expire', 86400); ?>" placeholder="86400" />
                <p class="description">Enter a time in seconds for the cache to expire. The default is <code>86400</code> seconds (1 day).</p>

                <p class="suboption info_yellow">You can set the expire time of a page using the method <code>\O10n\page_cache_expire([time_in_seconds]);</code> and in the cache policy JSON <code>{ "expire": 3600 }</code>.</p>
            </div>

            <div class="suboption" data-ns="filecache"<?php $visible('filecache'); ?>>
                <label><input type="checkbox" value="1" name="o10n[filecache.stale.enabled]" data-json-ns="1"<?php $checked('filecache.stale.enabled'); ?> /> Serve stale cache on update</label>
                <p class="description">This option will serve an expired cache to visitors while the cache is updated in the background.</p>
            </div>

            <div class="suboption" data-ns="filecache.stale"<?php $visible('filecache.stale'); ?>>
                <h5 class="h">&nbsp;Maximum Stale Cache Age</h5>
                <input type="number" style="width:120px;" min="1" name="o10n[filecache.stale.max_age]" value="<?php $value('filecache.stale.max_age'); ?>" placeholder="Always" />
                <p class="description">Enter a maximum time in seconds to serve expired cache. The default is none (always serve stale cache).</p>
            </div>
        </td>
    </tr>

    <tr valign="top" data-ns="filecache"<?php $visible('filecache'); ?>>
        <th scope="row">PHP Opcache</th>
        <td>
            
            <label><input type="checkbox" name="o10n[filecache.opcache.enabled]" data-json-ns="1" value="1"<?php $checked('filecache.opcache.enabled'); ?>> Enable
</label>
            <p class="description" style="margin-bottom:1em;">When enabled, cache files are stored in PHP Opcache.</p>

            <div class="suboption" data-ns="filecache.opcache"<?php $visible('filecache.opcache'); ?>>
                <label><input type="checkbox" value="1" name="o10n[filecache.opcache.filter.enabled]" data-json-ns="1"<?php $checked('filecache.opcache.filter.enabled'); ?> /> Enable PHP Opcache policy</label>
                <span data-ns="filecache.opcache.filter"<?php $visible('filecache.opcache.filter'); ?>>
                    <select name="o10n[filecache.opcache.filter.type]" data-ns-change="filecache.opcache.filter" data-json-default="<?php print esc_attr(json_encode('include')); ?>">
                        <option value="include"<?php $selected('filecache.opcache.filter.type', 'include'); ?>>Include by default</option>
                        <option value="exclude"<?php $selected('filecache.opcache.filter.type', 'exclude'); ?>>Exclude by default</option>
                    </select>
                </span>
                <p class="description">Due to the limited size of PHP Opcache, it may be required to specifically define the pages that use PHP Opcache. The filter enables to include or exclude pages from PHP Opcache.</p>

                <div class="suboption info_yellow">You can enable or disable PHP Opcache using the method <code>\O10n\page_cache_opcache(true|false);</code>.

                <p>To display the PHP Opcache status header, enable <code>O10N_DEBUG</code> in wp-config.php.</p>
                </div>
            </div>

            <br />

            <!--div class="suboption" data-ns="filecache"<?php $visible('filecache'); ?>>
                <label><input type="checkbox" value="1" name="o10n[filecache.headers.timing]" data-json-ns="1"<?php $checked('filecache.headers.timing'); ?> /> Include <code>X-O10n-Cache</code> header with cache performance timing.</label>
                
            </div-->

</td></tr>
    
    <tr valign="top" data-ns="filecache.opcache.filter"<?php $visible('filecache.opcache.filter'); ?>>
        <th scope="row">&nbsp;</th>
        <td style="padding-top:0px;">
            <h5 class="h">&nbsp;PHP Opcache Policy</h5>
            <div id="filecache-opcache-filter-config"><div class="loading-json-editor"><?php print __('Loading JSON editor...', 'o10n'); ?></div></div>
            <input type="hidden" class="json" name="o10n[filecache.opcache.filter.config]" data-json-type="json-array" data-json-editor-height="auto" data-json-editor-init="1" value="<?php print esc_attr($json('filecache.opcache.filter.config')); ?>" />
            <p class="description">Enter a JSON array with objects. (<a href="javascript:void(0);" onclick="jQuery('#opcache_example').fadeToggle();">show example</a>)</p>
            <div class="info_yellow" id="opcache_example" style="display:none;"><strong>Example:</strong> <pre class="clickselect" title="<?php print esc_attr('Click to select', 'optimization'); ?>" style="cursor:copy;padding: 10px;margin: 0 1px;margin-top:5px;font-size: 13px;">[
    "page-url",
    "/other/page/url",
    {
        "match": "uri",
        "string": "/page-uri-(x|y)/",
        "regex": true
    },
    {
        "match": "condition",
        "method": "is_page",
        "arguments": [[1,6,19]]
    }
]</pre></div>
        </td>
    </tr>
    </table>


<h3 style="margin-bottom:0px;" id="searchreplace">Search &amp; Replace</h3>
<?php $searchreplace = $get('filecache.replace', array()); ?>
<p class="description">This option enables to replace strings in the HTML before the page is cached. Enter JSON objects.</p>
<div id="filecache-replace"><div class="loading-json-editor"><?php print __('Loading JSON editor...', 'optimization'); ?></div></div>
<input type="hidden" class="json" name="o10n[filecache.replace]" data-json-type="json-array" data-json-editor-height="auto" data-json-editor-init="1" value="<?php print esc_attr($json('filecache.replace')); ?>" />

<div class="info_yellow"><strong>Example:</strong> <code id="html_search_replace_example" class="clickselect" data-example-text="show string" title="<?php print esc_attr('Click to select', 'optimization'); ?>" style="cursor:copy;">{"search":"string to match","replace":"newstring"}</code> (<a href="javascript:void(0);" data-example="html_search_replace_example" data-example-html="<?php print esc_attr(__('{"search":"|string to (match)|i","replace":"newstring $1","regex":true}', 'optimization')); ?>">show regular expression</a>) </div>

<p>To replace HTML before optimization, use the <a href="<?php print add_query_arg(array('page' => 'o10n', 'tab' => 'html'), admin_url('admin.php')); ?>">HTML Optimization</a> plugin.</p>
<p>You can also add a search and replace configuration using the PHP function <code>\O10n\search_replace_before_cache($search,$replace[,$regex])</code>. (<a href="javascript:void(0);" onclick="jQuery('#wp_html_search_replace_example').fadeToggle();">show example</a>)</p>

<div id="wp_html_search_replace_example" style="display:none;">
<pre style="padding:10px;border:solid 1px #efefef;">add_action('init', function () {

    /* String replace */
    \O10n\search_replace_before_cache('string', 'replace');

    /* Regular Expression */
    \O10n\search_replace_before_cache(array(
        '|regex (string)|i',
        '|regex2 (string)|i'
    ), array(
        '$1',
        'xyz'
    ), true);

}, 10);
</pre>
</div>

<hr />
<?php
    submit_button(__('Save'), 'primary large', 'is_submit', false);

// print form header
$this->form_end();
