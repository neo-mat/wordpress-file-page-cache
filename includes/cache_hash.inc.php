<?php
namespace O10n;

/**
 * Cache hash methods functions
 *
 * @package    optimization
 * @subpackage optimization/controllers
 * @author     Optimization.Team <info@optimization.team>
 */

define('O10N_CACHE_HASH_METHODS_LOADED', true);

// Return URL without query string
function page_cache_hash_no_query_string($request_url)
{
    $q = strpos($request_url, '?');
    if ($q !== false) {
        $request_url = substr($request_url, 0, $q);
    }

    return $request_url;
}
