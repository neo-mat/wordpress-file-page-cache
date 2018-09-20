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
        <td>
            <h5 class="h">&nbsp;Clear cache list</h5>
            <textarea class="json-array-lines" name="o10n[filecache.clear]" data-json-type="json-array-lines" placeholder="/path/to/post
or ...
https://domain.com/path/" style="height:250px"></textarea>
            <p class="description">Enter a list of paths or full URLs to clear the cache for. </p>
        </td>
    </tr>
    </table>
<?php
    submit_button(__('Clear Cache'), 'primary large', 'is_submit', false);
?>
<br /><br />
<hr />
<p>The following button will clear the cache for all pages.</p>
<a href="<?php print Core::get('cache')->flush_url('filecache');?>" class="button button-primary button-large"><span class="dashicons dashicons-trash"></span> Flush file cache</a>

<p>You can clear the cache from PHP using the method <code>\O10n\page_cache_clear([$urls])</code>. When no $urls is provided, the cache for the current page is cleared. $urls can be a string (full URL) or an array of URLs.</p>

<p>The following example shows a trick to clear the cache of a page using <code>Ctrl-Shift-R</code> (force browser cache reload) by first setting a secret cookie via the browser console (e.g. <code>document.cookie = "secrect-cookie-cache-admin=secret-code; expires= Thu, 21 Aug 2022 20:00:00 UTC ;domain=domain.com"</code>).</p>

<h3>wp-config.php</h3>
<pre style="padding:10px;border:solid 1px #efefef;">
// bypass wp-content/advanced-cache.php to allow functions.php to provoke cache clear method
if (isset($_COOKIE['secrect-cookie-cache-admin']) &amp;&amp; intval($_COOKIE['secrect-cookie-cache-admin']) === 'secret-code' &amp;&amp; isset($_SERVER['HTTP_PRAGMA']) &amp;&amp; $_SERVER['HTTP_PRAGMA'] === 'no-cache') {
    define('O10N_BYPASS_ADVANCED_CACHE', true);
}
</pre>

<h3>functions.php</h3>
<pre style="padding:10px;border:solid 1px #efefef;">
// clear cache for current page when forcing cache-bypass in the browser (Ctrl-Shift-R, HTTP header pragma:no-cache)
if (isset($_COOKIE['secrect-cookie-cache-admin']) &amp;&amp; intval($_COOKIE['secrect-cookie-cache-admin']) === 'secret-code' &amp;&amp; isset($_SERVER['HTTP_PRAGMA']) &amp;&amp; $_SERVER['HTTP_PRAGMA'] === 'no-cache') {
    \O10n\page_cache_clear(); // clear cache for current page
}
</pre>

<hr />
<?php

// print form header
$this->form_end();
