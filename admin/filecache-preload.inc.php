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

// default query config
$default_query_config = $view->preload_default_query();

// example
$query_example = json_decode(json_encode($default_query_config), true);
$query_example[0]['force_update'] = true;
$query_example[0]['headers'] = array(
    'x-custom-request-header' => '1'
);
$query_example[1]['force_update'] = 3600;
$query_example[1]['wp_http'] = array(
    'timeout' => 120,
    'httpversion' => '1.1',
    'user-agent' => 'Custom User Agent (gzip)',
    'cookies' => array(
        array( 'name' => 'cookie_name', 'value' => 'cookie_value' )
    )
);
$query_example = $view->json_encode($query_example);

// preload status
$preload_status = $view->preload_status();
$preload_status_files = $preload_status[1];
$preload_status_speed = $preload_status[2];
$preload_status = $preload_status[0];

?>

<table class="form-table">
    <tr valign="top">
        <th scope="row">Preload Cache</th>
        <td>
            
            <label><input type="checkbox" name="o10n[filecache.preload.enabled]" data-json-ns="1" value="1"<?php $checked('filecache.preload.enabled'); ?>> Enable
</label>
            <p class="description" style="margin-bottom:1em;">When enabled, pages are automatically preloaded using <a href="https://codex.wordpress.org/HTTP_API" target="_blank">WordPress HTTP API</a>.</p>

            <div class="suboption" data-ns="filecache.preload"<?php $visible('filecache.preload'); ?>>
                <label><input type="checkbox" name="o10n[filecache.preload.interval.enabled]" data-json-ns="1" value="1"<?php $checked('filecache.preload.interval.enabled'); ?>> Preload start interval
</label>
                <p class="description" style="margin-bottom:1em;">By default, the preload processor is started once per day on a configurable time. This option enables to use a interval in seconds.</p>
            </div>

            <div class="suboption" data-ns="filecache.preload.interval"<?php $visible('filecache.preload.interval'); ?>>
                <h5 class="h">&nbsp;Preload Interval</h5>
                <input type="number" style="width:120px;" min="1" name="o10n[filecache.preload.interval.interval]" value="<?php $value('filecache.preload.interval.interval', 86400); ?>" placeholder="86400" />
                <p class="description">Enter a interval time in seconds for the preload processor to start.</p>
            </div>

            <div data-ns="filecache.preload"<?php $visible('filecache.preload'); ?>>
            <div class="suboption" data-ns="filecache.preload" data-ns-hide="filecache.preload.interval"<?php $invisible('filecache.preload.interval'); ?>>
                <h5 class="h">&nbsp;Preload Start</h5>
                <input type="time" name="o10n[filecache.preload.start]" value="<?php $value('filecache.preload.start', '04:00'); ?>" placeholder="4:00" pattern="[0-9]{1,2}:[0-9]{2}" />
                <p class="description">Enter a time at which to start the preload processor.</p>
            </div>
            </div>

            <p class="suboption info_yellow" data-ns="filecache.preload"<?php $visible('filecache.preload'); ?>><strong><span class="dashicons dashicons-backup"></span></strong> The preload processor is triggered by a WordPress Scheduled Event. To make sure that the cron is started at the exect specified time, consider to trigger wp-cron.php via a server cron job.</p>


        </td>
    </tr>
    <tr valign="top" data-ns="filecache.preload"<?php $visible('filecache.preload'); ?>>
        <th scope="row">URL Query</th>
        <td>
    		<p class="description">By default all post, page, category and tag URLs are preloaded. You can fine tune the query configuration with custom methods and arguments to enhance the efficiency of the preload processor.</p>

    		<div style="margin-top:5px;">
                <div id="filecache-preload-query"><div class="loading-json-editor"><?php print __('Loading JSON editor...', 'o10n'); ?></div></div>
                <input type="hidden" class="json" name="o10n[filecache.preload.query]" data-json-type="json-array" data-json-editor-height="auto" data-json-editor-init="1" value="<?php print esc_attr($json('filecache.preload.query', $default_query_config)); ?>" />
                <p class="description">Enter a JSON array with objects. (<a href="javascript:void(0);" onclick="jQuery('#preload_query_example').fadeToggle();">show example</a>)</p>
                <div class="info_yellow" id="preload_query_example" style="display:none;"><strong>Example:</strong> <pre class="clickselect" title="<?php print esc_attr('Click to select', 'optimization'); ?>" style="cursor:copy;padding: 10px;margin: 0 1px;margin-top:5px;font-size: 13px;"><?php print $query_example; ?></pre></div>
                </div>

                <div style="margin-top: 5px;"><button type="button" class="button button-tiny" id="test_query">Test Query</button> <span class="query-test-result"></span></div>
            </div>

            <div class="suboption">
                <h5 class="h">&nbsp;HTTP Request Interval</h5>
                <input type="number" style="width:120px;" min="1" name="o10n[filecache.preload.http_interval]" value="<?php $value('filecache.preload.http_interval'); ?>" placeholder="No restriction" />
                <p class="description">Enter a minimum interval time in milliseconds (ms) between HTTP requests.</p>
            </div>

        </td>
    </tr>

    <tr valign="top" data-ns="filecache.preload"<?php $visible('filecache.preload'); ?>>
        <th scope="row">Preload Status<a name="status">&nbsp;</a></th>
        <td>

            <?php

                $urls = false;
                if (!$preload_status || !isset($preload_status['urls']) || empty($preload_status['urls'])) {
                    ?>
<div class="w3-light-grey" id="preload_status">
    <div class="w3-container" style="width:20%">IDLE</div>
</div>
<?php
                } elseif (isset($preload_status['completed'])) {
                    ?>
<div class="w3-light-grey" id="preload_status">
    <div class="w3-container w3-green" style="width:100%">100%<span style="font-weight:normal;font-style:italic;">&nbsp;&nbsp; Completed <?php printf(\_x('%s ago', '%s = human-readable time difference', 'o10n'), \human_time_diff($preload_status['completed'], \current_time('timestamp'))); ?></span></div>
</div>
<?php
                } elseif (isset($preload_status['urls'])) {
                    $total = count($preload_status['urls']);
                    $pending = 0;
                    foreach ($preload_status['urls'] as $url) {
                        if (!isset($url['status'])) {
                            $pending++;
                        }
                    }
                    if ($pending > 0) {
                        $perc = 100 - ($pending / ($total / 100));
                    } else {
                        $perc = 100;
                    } ?>
    <p class="description preload_status_desc">Preloading <span style="color:black;"><?php print \number_format_i18n(count($preload_status['urls']), 0); ?></span> URLs. <span style="color:black;"><?php print \number_format_i18n($pending, 0); ?></span> URLs pending.</p>
    <div class="w3-light-grey" id="preload_status">
        <div class="w3-container w3-green" style="width:<?php print (string)$perc; ?>%"><?php print (string)$perc; ?>%</div>
    </div>
<?php
                }
            ?>

			<p style="float:right;" class="preload_buttons">
                <button type="button" class="button"><span class="dashicons dashicons-update"></span> Refresh</button>
                <button type="button" class="button"><span class="dashicons dashicons-controls-play"></span> Start</button>
                <button type="button" class="button"<?php print((!$preload_status || isset($preload_status['completed'])) ? ' style="display:none;"' : ''); ?>><span class="dashicons dashicons-controls-pause"></span> Stop</button>
            </p>


            <?php
            $urls = ($preload_status && isset($preload_status['urls'])) ? $preload_status['urls'] : false;
            if ($urls) {
                $urls = array_reverse($urls); ?>
            <table cellpadding="0" cellspacing="0" border="0" class="status preload_status_files">
                <thead>
                <tr>
                    <td>Date</td>
                    <td>URL</td>
                    <td>Speed</td>
                    <td>Status</td>
                </tr>
                </thead>
                <tbody>
                    <?php print $preload_status_files; ?>
                </tbody>
            </table>

            <p class="preload_status_speed">
            <?php 

                print $preload_status_speed;
            }
            ?>
            </p>
        </td>
    </tr>
    </table>


<hr />
<?php
    submit_button(__('Save'), 'primary large', 'is_submit', false);

// print form header
$this->form_end();
