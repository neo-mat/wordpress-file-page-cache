<?php
namespace O10n;

/**
 * Filecache Invalidation Admin Controller
 *
 * @package    optimization
 * @subpackage optimization/controllers/admin
 * @author     Optimization.Team <info@optimization.team>
 */
if (!defined('ABSPATH')) {
    exit;
}

class AdminFilecacheinvalidation extends ModuleAdminController implements Module_Admin_Controller_Interface
{

    // invalidation status update interval
    private $invalidation_status_update_interval = 60;

    private $budget = 1000; // 1000 free invalidations per month
    private $overusage_price = 0.005; // price per invalidation for overusage

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
            'admin',
            'options',
            'shutdown',
            'filecache',
            'AdminCloudfront'
        ));
    }

    /**
     * Setup controller
     */
    protected function setup()
    {
        // check if invalidation is enabled
        if (!$this->options->bool('filecache.invalidation.enabled', true)) {
            // return;
        }

        // add admin bar menu
        add_action('edit_form_top', array( $this, 'post_invalidation_button'), $this->first_priority);

        // mark post edit form for script queue
        add_action('add_meta_boxes', array( $this, 'mark_edit_form'), $this->first_priority);

        // add admin bar menu
        add_action('admin_enqueue_scripts', array( $this, 'post_invalidation_button_style'), $this->first_priority);

        // handle purge requests when saving posts
        add_action('save_post', array( $this, 'save_post'), $this->first_priority, 1);
    }
    
    /**
     * Mark post edit form
     */
    final public function mark_edit_form()
    {
        $this->edit_form = true;
    }
    
    /**
     * Admin bar option
     *
     * @param  object       Admin bar object
     */
    final public function post_invalidation_button_style()
    {

        // post edit form and admin permissions
        if (!$this->edit_form || !$this->admin->is_admin()) {
            return;
        }

        // invalidation button CSS
        wp_enqueue_style('o10n_filecache_post_invalidation', $this->core->modules('filecache')->dir_url() . 'admin/css/view-filecache-admin.css');
    }

    /**
     * Admin bar option
     *
     * @param  object       Admin bar object
     */
    final public function post_invalidation_button($admin_bar)
    {
        // verify admin permissions
        if (!$this->admin->is_admin() || !$user = wp_get_current_user()) {
            return;
        }

        $default_invalidation = get_user_meta($user->ID, 'filecache_page_cache_default_invalidation', true); ?>
<div id="filecache_invalidate_container" style="display:none;"><hr />
    <div class="lnk">File Page Cache: 
        <label><input type="checkbox" name="o10n_filecache_purge" onchange="jQuery('#filecache-save-default').show();" value="1"<?php if ($default_invalidation) {
            print ' checked="checked"';
        } ?>> Purge</label>
        
        <p id="filecache-save-default" style="display:none;margin:0px;"><label><input type="checkbox" name="o10n_filecache_purge_default" value="1"> Save default purge setting</label></p>
    </div>
</div>
        <?php
    }

    /**
     * Process purge request in save post action
     */
    final public function save_post($post_id)
    {
        // get user
        if (!$user = wp_get_current_user()) {
            return;
        }

        // purge request
        $purge = (isset($_REQUEST['o10n_filecache_purge']) && $_REQUEST['o10n_filecache_purge']) ? true : false;

        if ($purge) {

            // clear cache of page cache related plugins
            $this->purge_plugin_caches();
            
            // clear file cache
            $url = get_permalink($post_id);
            $this->filecache->delete_cache($url);
        }

        // update default setting
        if (isset($_REQUEST['o10n_filecache_purge_default']) && $_REQUEST['o10n_filecache_purge_default']) {
            update_user_meta($user->ID, 'filecache_page_cache_default_invalidation', $purge);
        }
    }

    /**
     * Clear cache of page cache related plugins
     */
    final public function purge_plugin_caches()
    {

        // verify admin permissions
        if (!$this->admin->is_admin()) {
            return;
        }

        if (class_exists('\autoptimizeCache')) {
            \autoptimizeCache::clearall();
        }

        if (function_exists('\rocket_clean_domain')) {
            \rocket_clean_domain();
        } elseif (function_exists('\wp_cache_clear_cache')) {
            if (\is_multisite()) {
                $blog_id = \get_current_blog_id();
                \wp_cache_clear_cache($blog_id);
            } else {
                \wp_cache_clear_cache();
            }
        } elseif (has_action('cachify_flush_cache')) {
            do_action('cachify_flush_cache');
        } elseif (function_exists('\w3tc_pgcache_flush')) {
            \w3tc_pgcache_flush();
        } elseif (function_exists('\wp_fast_cache_bulk_delete_all')) {
            \wp_fast_cache_bulk_delete_all(); // still to retest
        } elseif (class_exists("\WpFastestCache")) {
            $wpfc = new \WpFastestCache();
            $wpfc->deleteCache();
        } elseif (class_exists("\c_ws_plugin__qcache_purging_routines")) {
            \c_ws_plugin__qcache_purging_routines::purge_cache_dir(); // quick cache, still to retest
        } elseif (class_exists("\zencache")) {
            \zencache::clear();
        } elseif (class_exists("\comet_cache")) {
            \comet_cache::clear();
        } elseif (class_exists("\WpeCommon")) {
            if (apply_filters('autoptimize_flush_wpengine_aggressive', false)) {
                if (method_exists("\WpeCommon", "purge_memcached")) {
                    \WpeCommon::purge_memcached();
                }
                if (method_exists("\WpeCommon", "clear_maxcdn_cache")) {
                    \WpeCommon::clear_maxcdn_cache();
                }
            }
            if (method_exists("\WpeCommon", "purge_varnish_cache")) {
                \WpeCommon::purge_varnish_cache();
            }
        } elseif (function_exists('\sg_cachepress_purge_cache')) {
            \sg_cachepress_purge_cache();
        } elseif (file_exists(WP_CONTENT_DIR.'/wp-cache-config.php') && function_exists('\prune_super_cache')) {
            // fallback for WP-Super-Cache
            global $cache_path;
            if (\is_multisite()) {
                $blog_id = \get_current_blog_id();
                \prune_super_cache(\get_supercache_dir($blog_id), true);
                \prune_super_cache($cache_path . 'blogs/', true);
            } else {
                \prune_super_cache($cache_path.'supercache/', true);
                \prune_super_cache($cache_path, true);
            }
        }

        $this->admin->add_notice('Cache of page cache related plugins cleared.', 'filecache', 'SUCCESS', array('persist' => 'expire','max-views' => 1));
    }
}
