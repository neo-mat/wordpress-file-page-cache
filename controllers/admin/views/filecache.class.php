<?php
namespace O10n;

/**
 * File Page Cache Admin View Controller
 *
 * @package    optimization
 * @subpackage optimization/controllers/admin
 * @author     Optimization.Team <info@optimization.team>
 */
if (!defined('ABSPATH')) {
    exit;
}

class AdminViewFilecache extends AdminViewBase
{
    protected static $view_key = 'filecache'; // reference key for view
    protected $module_key = 'filecache';

    // default tab view
    private $default_tab_view = 'intro';

    /**
     * Load controller
     *
     * @param  Core       $Core   Core controller instance.
     * @param  false      $module Module parameter not used for core view controllers
     * @return Controller Controller instance.
     */
    public static function &load(Core $Core)
    {
        // instantiate controller
        return parent::construct($Core, array(
            'json',
            'file',
            'options',
            'filecache',
            'AdminClient',
            'AdminFilecache',
            'json'
        ));
    }
    
    /**
     * Setup controller
     */
    protected function setup()
    {
        // WPO plugin
        if (defined('O10N_WPO_VERSION')) {
            $this->default_tab_view = 'optimization';
        }
        // set view etc
        parent::setup();
    }

    /**
     * Setup view
     */
    public function setup_view()
    {
        // process form submissions
        add_action('o10n_save_settings_verify_input', array( $this, 'verify_input' ), 10, 1);

        // enqueue scripts
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), $this->first_priority);
    }

    /**
     * Return help tab data
     */
    final public function help_tab()
    {
        $data = array(
            'name' => __('File Page Cache', 'o10n'),
            'github' => 'https://github.com/o10n-x/wordpress-file-page-cache',
            //'wordpress' => 'https://wordpress.org/support/plugin/http2-optimization',
            'docs' => 'https://github.com/o10n-x/wordpress-file-page-cache/tree/master/docs'
        );

        return $data;
    }

    /**
     * Enqueue scripts and styles
     */
    final public function enqueue_scripts()
    {
        // skip if user is not logged in
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }

        // set module path
        $this->AdminClient->set_config('module_url', $this->module->dir_url());

        $tab = (isset($_REQUEST['tab'])) ? trim($_REQUEST['tab']) : $this->default_tab_view;
        switch ($tab) {
            case "preload":

                // file cache styles
                wp_enqueue_style('o10n_view_filecache', $this->module->dir_url() . 'admin/css/view-filecache.css');

                // global admin script
                wp_enqueue_script('o10n_view_filecache', $this->module->dir_url() . 'admin/js/view-filecache.js', array( 'jquery', 'o10n_cp' ), $this->module->version());
            break;
            case "opcache":
            
                // file cache styles
                wp_enqueue_style('o10n_view_filecache_opcache', $this->module->dir_url() . 'admin/css/view-filecache-opcache.css');

                // global admin script
                wp_enqueue_script('o10n_view_filecache_opcache', $this->module->dir_url() . 'admin/js/view-filecache-react.js', array( 'jquery', 'o10n_cp' ), $this->module->version());

            break;
            default:
                
            break;
        }
    }


    /**
     * Return view template
     */
    public function template($view_key = false)
    {
        // template view key
        $view_key = false;

        $tab = (isset($_REQUEST['tab'])) ? trim($_REQUEST['tab']) : $this->default_tab_view;
        switch ($tab) {
            case "settings":
            case "preload":
            case "opcache":
            case "intro":
                $view_key = 'filecache-' . $tab;
            break;
            default:
                throw new Exception('Invalid view ' . esc_html($view_key), 'core');
            break;
        }

        return parent::template($view_key);
    }
    
    /**
     * Verify settings input
     *
     * @param  object   Form input controller object
     */
    final public function verify_input($forminput)
    {
        // File Page Cache Settings

        $tab = (isset($_REQUEST['tab'])) ? trim($_REQUEST['tab']) : 'o10n';

        switch ($tab) {
            case "settings":

                $forminput->type_verify(array(
                    'filecache.enabled' => 'bool',
                    'filecache.expire' => 'int',
                    'filecache.filter.enabled' => 'bool',
                    'filecache.filter.type' => 'string',

                    'filecache.bypass.enabled' => 'bool',

                    'filecache.headers.enabled' => 'bool',
                    'filecache.headers.type' => 'string',

                    'filecache.hash.enabled' => 'bool',

                    'filecache.opcache.enabled' => 'bool',
                    'filecache.opcache.filter.enabled' => 'bool',
                    'filecache.opcache.filter.type' => 'string',
                    'filecache.replace' => 'json-array',

                    'filecache.stale.enabled' => 'bool',
                    'filecache.stale.max_age' => 'int-empty'
                ));


                // file cache policy filter
                if ($forminput->bool('filecache.enabled')) {
                    if ($forminput->bool('filecache.filter.enabled')) {
                        $forminput->type_verify(array(
                            'filecache.filter.config' => 'json-array'
                        ));
                    }
                }

                // bypass policy filter
                if ($forminput->bool('filecache.bypass.enabled')) {
                    if ($forminput->bool('filecache.bypass.enabled')) {
                        $forminput->type_verify(array(
                            'filecache.bypass.config' => 'json-array'
                        ));
                    }
                }

                // headers policy filter
                if ($forminput->bool('filecache.headers.enabled')) {
                    if ($forminput->bool('filecache.headers.enabled')) {
                        $forminput->type_verify(array(
                            'filecache.headers.config' => 'json-array'
                        ));
                    }
                }

                // hash format
                if ($forminput->bool('filecache.hash.enabled')) {
                    if ($forminput->bool('filecache.hash.enabled')) {
                        $forminput->type_verify(array(
                            'filecache.hash.config' => 'json-array'
                        ));
                    }
                }

                // opcache policy filter
                if ($forminput->bool('filecache.opcache.enabled')) {
                    if ($forminput->bool('filecache.opcache.filter.enabled')) {
                        $forminput->type_verify(array(
                            'filecache.opcache.filter.config' => 'json-array'
                        ));
                    }
                }

                // create advanced-cache.php
                if ($forminput->bool('filecache.advanced_cache')) {

                    // advanced-cache.php location
                    $advanced_cache_file = WP_CONTENT_DIR . '/advanced-cache.php';

                    // create or overwrite file
                    try {
                        $this->file->put_contents($advanced_cache_file, '<?php
namespace O10n;

/**
 * WordPress cache performance enhancement
 *
 * @package    optimization
 * @subpackage optimization/admin
 * @author     Optimization.Team <info@optimization.team>
 */

$output_cache_controller = \'' . $this->core->modules('filecache')->dir_path() . 'output-cache.php\';
if (file_exists($output_cache_controller)) {
    require $output_cache_controller;
}
');
                    } catch (\Exception $err) {
                        $forminput->error('advanced-cache.php', $err);
                    }
                }

                // store config in opcache
                add_action('o10n_forminput_saved', array($this, 'save_config'));

            break;
            case "preload":

                $forminput->type_verify(array(
                    'filecache.preload.enabled' => 'bool',
                    'filecache.preload.interval.enabled' => 'bool',
                    'filecache.preload.interval.interval' => 'int-empty',
                    'filecache.preload.start' => 'string',
                    'filecache.preload.query' => 'json-array',
                    'filecache.preload.http_interval' => 'int-empty'
                ));

            break;
        }
    }
    
    /**
     * Save PHP Opcache config
     */
    final public function save_config()
    {
        $cache_dir = $this->file->directory_path('page-cache');
        $config_file = $cache_dir . 'config.php';
        
        // get config
        $config = $this->options->get('filecache.*', false, true);

        // store in PHP Opcache
        $this->file->put_opcache($config_file, $config);
    }

    /**
     * Return default query configuration
     */
    final public function preload_default_query()
    {
        return $this->filecache->preload_default_query();
    }

    /**
     * Return preload processor status
     */
    final public function preload_status()
    {
        return array($this->filecache->preload_status(), $this->AdminFilecache->preload_status_filelist(), $this->AdminFilecache->preload_status_speed());
    }

    /**
     * Return beautified JSON
     */
    final public function json_encode($array)
    {
        return $this->json->beautify($array);
    }
}
