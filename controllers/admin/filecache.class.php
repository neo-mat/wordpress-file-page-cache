<?php
namespace O10n;

/**
 * File Cache Admin Controller
 *
 * @package    optimization
 * @subpackage optimization/controllers/admin
 * @author     Optimization.Team <info@optimization.team>
 */
if (!defined('ABSPATH')) {
    exit;
}

class AdminFilecache extends ModuleAdminController implements Module_Admin_Controller_Interface
{
    protected $admin_base = 'tools.php';

    // tab menu
    protected $tabs = array(
        'intro' => array(
            'title' => '<span class="dashicons dashicons-admin-home"></span>',
            'title_attr' => 'Intro'
        ),
        'settings' => array(
            'title' => 'Page Cache',
            'title_attr' => 'Page Cache Settings'
        ),
        'preload' => array(
            'title' => 'Preload',
            'title_attr' => 'Preload Cache'
        ),
        'clear' => array(
            'title' => 'Clear',
            'title_attr' => 'Clear cache'
        ),
        'opcache' => array(
            'title' => 'PHP Opcache',
            'title_attr' => 'PHP Opcache Status',
            'subtabs' => array(
                'overview' => array(
                    'title' => 'Overview',
                    'title_attr' => 'PHP Opcache overview',
                    'href' => '#overview',
                    'attrs' => 'data-for="overview"'
                ),
                'fileusage' => array(
                    'title' => 'File Usage',
                    'title_attr' => 'File Usage',
                    'href' => '#files',
                    'attrs' => 'data-for="files"'
                ),
                'reset' => array(
                    'title' => '<span class="dashicons dashicons-update" style="font-size:16px;height:16px;line-height:16px;"></span> Reset Cache',
                    'title_attr' => 'Reset PHP Opcache',
                    'attrs' => 'id="resetCache" onclick="return confirm(\'Are you sure you want to reset the cache?\');"'
                )
            )
        )
    );
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
            'AdminView',
            'AdminAjax',
            'filecache',
            'json',
            'file',
            'shutdown'
        ));
    }

    /**
     * Setup controller
     */
    protected function setup()
    {

        // test preload query
        add_action('wp_ajax_o10n_test_query', array( $this, 'ajax_test_query'), 10);
        
        // query preload status
        add_action('wp_ajax_o10n_preload_status', array( $this, 'ajax_preload_status'), 10);

        // (re-)start preload processor
        add_action('wp_ajax_o10n_preload_start', array( $this, 'ajax_preload_start'), 10);

        // (re-)start preload processor
        add_action('wp_ajax_o10n_preload_stop', array( $this, 'ajax_preload_stop'), 10);

        // settings link on plugin index
        add_filter('plugin_action_links_' . $this->core->modules('filecache')->basename(), array($this, 'settings_link'));

        // meta links on plugin index
        add_filter('plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2);

        // title on plugin index
        add_action('pre_current_active_plugins', array( $this, 'plugin_title'), 10);

        // admin options page
        add_action('admin_menu', array($this, 'admin_menu'), 50);

        // admin options page
        add_action('admin_init', array($this, 'admin_init'), 50);
    }
    
    /**
     * Admin init
     */
    public function admin_init()
    {
        if (isset($_GET['clear-cache']) && preg_match('|http(s)?://|Ui', $_GET['clear-cache'])) {
            $this->filecache->delete_cache($_GET['clear-cache']);
            
            if (wp_redirect($_GET['clear-cache'])) {
                exit;
            }
        }
    }
    
    /**
     * Admin menu option
     */
    public function admin_menu()
    {
        global $submenu;

        // WPO plugin or more than 1 optimization module, add to optimization menu
        if (defined('O10N_WPO_VERSION') || count($this->core->modules()) > 1) {
            add_submenu_page('o10n', __('File Page Cache', 'o10n'), __('File Cache', 'o10n'), 'manage_options', 'o10n-filecache', array(
                 &$this->AdminView,
                 'display'
             ));

            // change base to admin.php
            $this->admin_base = 'admin.php';
        } else {

            // add menu entry
            add_submenu_page('tools.php', __('File Page Cache', 'o10n'), __('File Cache', 'o10n'), 'manage_options', 'o10n-filecache', array(
                 &$this->AdminView,
                 'display'
             ));
        }

        // set reset url
        $this->tabs['opcache']['subtabs']['reset']['href'] = add_query_arg(array( 'page' => 'o10n-filecache', 'tab' => 'opcache', 'reset' => 1 ), admin_url($this->admin_base));
    }

    /**
     * Settings link on plugin overview.
     *
     * @param  array $links Plugin settings links.
     * @return array Modified plugin settings links.
     */
    final public function settings_link($links)
    {
        $settings_link = '<a href="'.esc_url(add_query_arg(array('page' => 'o10n-filecache','tab' => 'settings'), admin_url($this->admin_base))).'">'.__('Settings').'</a>';
        array_unshift($links, $settings_link);

        return $links;
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
     * Show row meta on the plugin screen.
     */
    final public function plugin_row_meta($links, $file)
    {
        if ($file == $this->core->modules('filecache')->basename()) {
            $lgcode = strtolower(get_locale());
            if (strpos($lgcode, '_') !== false) {
                $lgparts = explode('_', $lgcode);
                $lgcode = $lgparts[0];
            }
            if ($lgcode === 'en') {
                $lgcode = '';
            }
            
            $plugin_links = $this->help_tab();

            if ($plugin_links && isset($plugin_links['github'])) {
                $row_meta = array(
                    'o10n_version' => '<a href="'.trailingslashit($plugin_links['github']).'releases/" target="_blank" title="' . esc_attr(__('View Version History', 'o10n')) . '" style=""><span class="dashicons dashicons-clock"></span> ' . __('Version History', 'o10n') . '</a>'
                );
            }

            return array_merge($links, $row_meta);
        }

        return (array) $links;
    }

    /**
     * Plugin title modification
     */
    public function plugin_title()
    {
        ?><script>jQuery(function($){var r=$('*[data-plugin="<?php print $this->core->modules('filecache')->basename(); ?>"]');
            $('.plugin-title strong',r).html('<?php print $this->core->modules('filecache')->name(); ?><a href="https://optimization.team" class="g100" style="font-size: 10px;float: right;font-weight: normal;opacity: .2;line-height: 14px;">O10N</span>');
            var d=$('.plugin-description',r).html();$('.plugin-description',r).html(d.replace('Google PageSpeed','<a href="https://developers.google.com/speed/pagespeed/insights/" target="_blank">Google PageSpeed</a>').replace('Google Lighthouse','<a href="https://developers.google.com/web/tools/lighthouse/" target="_blank">Google Lighthouse</a>').replace('ThinkWithGoogle.com','<a href="https://testmysite.thinkwithgoogle.com/" target="_blank">ThinkWithGoogle.com</a>').replace('Excellent','<span style="font-style:italic;color:#079c2d;">Excellent</span>'));
});</script><?php
    }

    /**
     * Test preload query
     */
    final public function ajax_test_query()
    {

        // process AJAX request
        $request = $this->AdminAjax->request();

        $query = $request->data('query');
        if ($query) {
            try {
                $query = $this->json->parse($query, true);
            } catch (\Exception $err) {
                $request->output_errors($err->getMessage());
            }
        }

        // test query
        try {
            $result = $this->filecache->preload_query($query);
        } catch (Exception $err) {
            $request->output_errors($err->getMessage());
        }

        $request->output_ok(false, array(number_format_i18n(count($result), 0)));
    }

    /**
     * Start preload processor
     */
    final public function ajax_preload_start()
    {
        // process AJAX request
        $request = $this->AdminAjax->request();

        header('Content-Type: application/json');
        print json_encode(array('ok' => true));

        $this->shutdown->add(array($this->filecache, 'preload_processor'));
        exit;
    }

    /**
     * Stop preload processor
     */
    final public function ajax_preload_stop()
    {
        // process AJAX request
        $request = $this->AdminAjax->request();

        $cache_dir = $this->file->directory_path('page-cache');
        $config_file = $cache_dir . 'preload-processor.php';
        if (file_exists($config_file)) {
            try {
                $this->file->put_contents($config_file, json_encode(array()));
            } catch (\Exception $err) {
                throw new Exception('Failed to write preload processor status: ' . $err->getConfig(), 'filecache');
            }
        }

        $request->output_ok(false, 1);
    }

    /**
     * Return preload status
     */
    final public function ajax_preload_status()
    {

        // process AJAX request
        $request = $this->AdminAjax->request();

        $prev_perc = $request->data('perc');

        $preload_status = $this->filecache->preload_status();
        if (!$preload_status) {
            $perc = -1;
        } else {
            if ($preload_status['completed']) {
                $perc = 100;
            } else {
                if ($preload_status && isset($preload_status['urls'])) {
                    $total = count($preload_status['urls']);
                    $pending = 0;
                    foreach ($preload_status['urls'] as $url) {
                        if (!isset($url['status'])) {
                            $pending++;
                        }
                    }
                    if ($pending > 0) {
                        $perc = round(100 - ($pending / ($total / 100)));
                    } else {
                        $perc = 100;
                    }
                } else {
                    $perc = 0;
                }
            }

            if (intval($prev_perc) === intval($perc)) {
                $perc = -1;
            }
        }

        $result = array($perc);

        if ($perc !== -1) {
            $result[1] = $this->preload_status_filelist($preload_status);
            $result[2] = $this->preload_status_speed($preload_status);
            $result[3] = 'Preloading <span style="color:black;">' . \number_format_i18n(count($preload_status['urls']), 0) . '</span> URLs. <span style="color:black;">'. \number_format_i18n($pending, 0) . '</span> URLs pending.';
        }

        $request->output_ok(false, $result);
    }

    /**
     * Return preload status file list table rows
     */
    final public function preload_status_filelist($preload_status = false)
    {
        // preload status
        if (!$preload_status) {
            $preload_status = $this->filecache->preload_status();
        }

        $rows = '';

        $urls = ($preload_status && isset($preload_status['urls'])) ? $preload_status['urls'] : false;
        if ($urls) {
            $urls = array_reverse($urls);
            $count = 0;
            $first_preload = false;
            $last_preload = false;
            $preload_count = 0;
            foreach ($urls as $url => $url_status) {
                if (!isset($url_status['status']) || !isset($url_status['start'])) {
                    continue;
                }

                if (!$first_preload || $url_status['start'] < $first_preload) {
                    $first_preload = $url_status['start'];
                }
                if (!$last_preload || $url_status['start'] > $last_preload) {
                    $last_preload = $url_status['start'];
                }
                $preload_count++;

                if ($count >= 10) {
                    break;
                }

                $start_time = round($url_status['start']);
                if (date('Ymd', time()) == date('Ymd', $start_time)) {
                    $time = date('H:i:s', $start_time);
                } else {
                    $time = \human_time_diff($start_time, \current_time('timestamp')) . ' ago';
                }
            
                $parsed_url = parse_url($url);
                if ($parsed_url['host'] !== $_SERVER['HTTP_HOST']) {
                    $path = $url;
                } else {
                    $path = (isset($parsed_url['path']) && $parsed_url['path']) ? $parsed_url['path'] : '/';
                    if (isset($parsed_url['query'])) {
                        $path .= '?' . $parsed_url['query'];
                    }
                }

                $speed_time = ($url_status['end'] - $url_status['start']);
                if ((string) number_format($speed_time, 2, '.', '') === '0.00') {
                    $speed = \number_format_i18n($speed_time, 5) . 's';
                } else {
                    $speed = \number_format_i18n($speed_time, 2) . 's';
                }

                $status = '';
                if ($url_status['status'][0] > 1) {
                    if ($url_status['status'][1]) {
                        $status = 'Stale cache ('.\human_time_diff($url_status['status'][0], \current_time('timestamp')).' old, expired for '.$url_status['status'][1].'s)';
                    } else {
                        $status = 'In cache ('.\human_time_diff($url_status['status'][0], \current_time('timestamp')).' old)';
                    }
                } else {
                    switch ((string)$url_status['status'][0]) {
                        case "-1":
                            $status = 'Disabled';
                        break;
                        case "-2":
                            $status = 'Empty HTML';
                        break;
                        case "-3":
                            $status = 'Bypass policy';
                        break;
                        case "-4":
                            $status = 'Updated';
                            if (isset($url_status['status'][1]) && $url_status['status'][1]) {
                                $status .= ' (forced)';
                            }
                        break;
                    }
                }

                $rows .= '
<tr>
    <td class="d">' . $time .'</td>
    <td class="l"><a href="' . $url . '" target="_blank">' . $path . '</a></td>
    <td class="t">' . $speed . '</td>
    <td class="s">' . $status . '</td>
</tr>';
                $count++;
            }
        }

        return $rows;
    }

    /**
     * Return preload speed
     */
    final public function preload_status_speed($preload_status = false)
    {
        // preload status
        if (!$preload_status) {
            $preload_status = $this->filecache->preload_status();
        }

        $first_preload = false;
        $last_preload = false;
        $preload_count = 0;

        $urls = ($preload_status && isset($preload_status['urls'])) ? $preload_status['urls'] : false;
        if ($urls) {
            $urls = array_reverse($urls);

            foreach ($urls as $url => $url_status) {
                if (!isset($url_status['status']) || !isset($url_status['start'])) {
                    continue;
                }

                if (!$first_preload || $url_status['start'] < $first_preload) {
                    $first_preload = $url_status['start'];
                }
                if (!$last_preload || $url_status['start'] > $last_preload) {
                    $last_preload = $url_status['start'];
                }
                $preload_count++;
            }
        }

        if ($preload_count <= 1 || !$last_preload || $first_preload === $last_preload) {
            return 'Speed: unknown';
        } else {
            $time_elapsed = $last_preload - $first_preload;
            if ($time_elapsed <= 0) {
                return 'Speed: unknown';
            } else {
                $speed = 1 / ($time_elapsed / $preload_count);

                return 'Speed: '.number_format_i18n($speed, 2).' URLs per second (max. '.number_format_i18n($speed * 86400, 0).' URLs per day)';
            }
        }
    }
}
