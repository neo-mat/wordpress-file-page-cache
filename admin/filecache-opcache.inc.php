<?php
namespace O10n;

/**
 * PHP Opcache admin template
 *
 * @package    optimization
 * @subpackage optimization/admin
 * @author     Optimization.Team <info@optimization.team>
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * OPcache GUI
 *
 * A simple but effective single-file GUI for the OPcache PHP extension.
 *
 * @author Andrew Collington, andy@amnuts.com
 * @version 2.4.0
 * @link https://github.com/amnuts/opcache-gui
 * @license MIT, http://acollington.mit-license.org/
 */


/*
 * User configuration
 */

$options = [
    'allow_filelist' => true,          // show/hide the files tab
    'allow_invalidate' => true,          // give a link to invalidate files
    'allow_reset' => true,          // give option to reset the whole cache
    'allow_realtime' => true,          // give option to enable/disable real-time updates
    'refresh_time' => 5,             // how often the data will refresh, in seconds
    'size_precision' => 2,             // Digits after decimal point
    'size_space' => false,         // have '1MB' or '1 MB' when showing sizes
    'charts' => true,          // show gauge chart or just big numbers
    'debounce_rate' => 250,           // milliseconds after key press to send keyup event when filtering
    'cookie_name' => 'opcachegui',  // name of cookie
    'cookie_ttl' => 365,            // days to store cookie
    'admin_base' => $view->admin_base()
];


class OpCacheService
{
    protected $data;
    protected $options;
    protected $defaults = [
        'allow_filelist' => true,
        'allow_invalidate' => true,
        'allow_reset' => true,
        'allow_realtime' => true,
        'refresh_time' => 5,
        'size_precision' => 2,
        'size_space' => false,
        'charts' => true,
        'debounce_rate' => 250,
        'cookie_name' => 'opcachegui',
        'cookie_ttl' => 365
    ];

    private function __construct($options = [])
    {
        $this->options = array_merge($this->defaults, $options);
        $this->data = $this->compileState();
    }

    public static function init($options = [])
    {
        $self = new self($options);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            if (isset($_GET['reset']) && $self->getOption('allow_reset')) {
                echo '{ "success": "' . ($self->resetCache() ? 'yes' : 'no') . '" }';
            } elseif (isset($_GET['invalidate']) && $self->getOption('allow_invalidate')) {
                echo '{ "success": "' . ($self->resetCache($_GET['invalidate']) ? 'yes' : 'no') . '" }';
            } else {
                echo json_encode($self->getData((empty($_GET['section']) ? null : $_GET['section'])));
            }
            exit;
        } elseif (isset($_GET['reset']) && $self->getOption('allow_reset')) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            $self->resetCache();

            Core::get('admin')->add_notice('PHP Opcache cache reset.', 'admin', 'SUCCESS');

            die('<script>document.location.replace(\''.add_query_arg(array( 'page' => 'o10n-filecache', 'tab' => 'opcache' ), admin_url($options['admin_base'])).'\');</script>');
        } elseif (isset($_GET['invalidate']) && $self->getOption('allow_invalidate')) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            $self->resetCache($_GET['invalidate']);

            Core::get('admin')->add_notice('PHP Opcache cache invalidated for file <code>'.esc_html($_GET['invalidate']).'</code>.', 'admin', 'SUCCESS');

            
            die('<script>document.location.replace(\''.add_query_arg(array( 'page' => 'o10n-filecache', 'tab' => 'opcache' ), admin_url($options['admin_base'])).'\');</script>');
        }

        return $self;
    }

    public function getOption($name = null)
    {
        if ($name === null) {
            return $this->options;
        }

        return (isset($this->options[$name])
            ? $this->options[$name]
            : null
        );
    }

    public function getData($section = null, $property = null)
    {
        if ($section === null) {
            return $this->data;
        }
        $section = strtolower($section);
        if (isset($this->data[$section])) {
            if ($property === null || !isset($this->data[$section][$property])) {
                return $this->data[$section];
            }

            return $this->data[$section][$property];
        }

        return null;
    }

    public function canInvalidate()
    {
        return ($this->getOption('allow_invalidate') && function_exists('opcache_invalidate'));
    }

    public function resetCache($file = null)
    {
        $success = false;
        if ($file === null) {
            $success = opcache_reset();
        } elseif (function_exists('opcache_invalidate')) {
            $success = opcache_invalidate(urldecode($file), true);
        }
        if ($success) {
            $this->compileState();
        }

        return $success;
    }

    protected function size($size)
    {
        $i = 0;
        $val = array('b', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        while (($size / 1024) > 1) {
            $size /= 1024;
            ++$i;
        }

        return sprintf(
            '%.'.$this->getOption('size_precision').'f%s%s',
            $size,
            ($this->getOption('size_space') ? ' ' : ''),
            $val[$i]
        );
    }

    protected function compileState()
    {
        $status = opcache_get_status();
        $config = opcache_get_configuration();

        $files = [];
        if (!empty($status['scripts']) && $this->getOption('allow_filelist')) {
            uasort($status['scripts'], function ($a, $b) {
                return $a['hits'] < $b['hits'];
            });
            foreach ($status['scripts'] as &$file) {
                $file['full_path'] = str_replace('\\', '/', $file['full_path']);
                $file['readable'] = [
                    'hits' => number_format($file['hits']),
                    'memory_consumption' => $this->size($file['memory_consumption'])
                ];
            }
            $files = array_values($status['scripts']);
        }

        $overview = array_merge(
            $status['memory_usage'],
            $status['opcache_statistics'],
            [
                'used_memory_percentage' => round(100 * (
                        ($status['memory_usage']['used_memory'] + $status['memory_usage']['wasted_memory'])
                        / $config['directives']['opcache.memory_consumption']
                )),
                'hit_rate_percentage' => round($status['opcache_statistics']['opcache_hit_rate']),
                'wasted_percentage' => round($status['memory_usage']['current_wasted_percentage'], 2),
                'readable' => [
                    'total_memory' => $this->size($config['directives']['opcache.memory_consumption']),
                    'used_memory' => $this->size($status['memory_usage']['used_memory']),
                    'free_memory' => $this->size($status['memory_usage']['free_memory']),
                    'wasted_memory' => $this->size($status['memory_usage']['wasted_memory']),
                    'num_cached_scripts' => number_format($status['opcache_statistics']['num_cached_scripts']),
                    'hits' => number_format($status['opcache_statistics']['hits']),
                    'misses' => number_format($status['opcache_statistics']['misses']),
                    'blacklist_miss' => number_format($status['opcache_statistics']['blacklist_misses']),
                    'num_cached_keys' => number_format($status['opcache_statistics']['num_cached_keys']),
                    'max_cached_keys' => number_format($status['opcache_statistics']['max_cached_keys']),
                    'interned' => null,
                    'start_time' => date('Y-m-d H:i:s', $status['opcache_statistics']['start_time']),
                    'last_restart_time' => (
                        $status['opcache_statistics']['last_restart_time'] == 0
                            ? 'never'
                            : date('Y-m-d H:i:s', $status['opcache_statistics']['last_restart_time'])
                        )
                ]
            ]
        );

        if (!empty($status['interned_strings_usage'])) {
            $overview['readable']['interned'] = [
                'buffer_size' => $this->size($status['interned_strings_usage']['buffer_size']),
                'strings_used_memory' => $this->size($status['interned_strings_usage']['used_memory']),
                'strings_free_memory' => $this->size($status['interned_strings_usage']['free_memory']),
                'number_of_strings' => number_format($status['interned_strings_usage']['number_of_strings'])
            ];
        }

        $directives = [];
        ksort($config['directives']);
        foreach ($config['directives'] as $k => $v) {
            $directives[] = ['k' => $k, 'v' => $v];
        }

        $version = array_merge(
            $config['version'],
            [
                'php' => phpversion(),
                'server' => empty($_SERVER['SERVER_SOFTWARE']) ? '' : $_SERVER['SERVER_SOFTWARE'],
                'host' => (
                    function_exists('gethostname')
                    ? gethostname()
                    : (
                        php_uname('n')
                        ?: (
                            empty($_SERVER['SERVER_NAME'])
                            ? $_SERVER['HOST_NAME']
                            : $_SERVER['SERVER_NAME']
                        )
                    )
                )
            ]
        );

        return [
            'version' => $version,
            'overview' => $overview,
            'files' => $files,
            'directives' => $directives,
            'blacklist' => $config['blacklist'],
            'functions' => get_extension_funcs('Zend OPcache')
        ];
    }
}


/*
 * Shouldn't need to alter anything else below here
 */

if (!function_exists('opcache_reset')) {
    print '<a href="http://php.net/manual/en/book.opcache.php" target="_blank">PHP/Zend OPcache</a> extension is not installed.';
} else {
    $ocEnabled = ini_get('opcache.enable');
    if (empty($ocEnabled)) {
        print '<a href="http://php.net/manual/en/book.opcache.php" target="_blank">PHP/Zend OPcache</a> extension is installed but not enabled in PHP.ini';
    } else {
        $opcache = OpCacheService::init($options); ?>

<div class="wrap">

    <div class="metabox-prefs">
        <div class="wrap about-wrap" style="position:relative;">

<div id="tabs">
    <div id="overview">
        <div class="container">
            <div id="counts"></div>
            <div id="info">
                <div id="generalInfo"></div>
                <div id="directives"></div>
                <div id="functions">
                    <table>
                        <thead>
                            <tr><th>Available functions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($opcache->getData('functions') as $func): ?>
                            <tr><td><a href="http://php.net/<?php echo $func; ?>" title="View manual page" target="_blank"><?php echo $func; ?></a></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <br style="clear:both;" />
            </div>
        </div>
    </div>
    <div id="files">
        <?php if ($opcache->getOption('allow_filelist')): ?>
        <form action="#">
            <label for="frmFilter">Start typing to filter on script path</label><br>
            <input type="text" name="filter" id="frmFilter">
        </form>
        <?php endif; ?>
        <div class="container" id="filelist"></div>
    </div>
</div>
<p class="poweredby">Powered by <a href="https://github.com/amnuts/opcache-gui" target="_blank">opcache-gui</a><span class="star"><a class="github-button" data-manual="1" href="https://github.com/amnuts/opcache-gui" data-icon="octicon-star" data-show-count="true" aria-label="Star amnuts/opcache-gui on GitHub">Star</a></span></p>
<script type="text/javascript">
    var realtime = false;
    var opstate = <?php echo json_encode($opcache->getData()); ?>;
    var canInvalidate = <?php echo json_encode($opcache->canInvalidate()); ?>;
    var useCharts = <?php echo json_encode($opcache->getOption('charts')); ?>;
    var allowFiles = <?php echo json_encode($opcache->getOption('allow_filelist')); ?>;
    var debounce = function(func, wait, immediate) {
        var timeout;
        wait = wait || 250;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) {
                    func.apply(context, args);
                }
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) {
                func.apply(context, args);
            }
        };
    };
    function keyUp(event){
        var compare = $('#frmFilter').val().toLowerCase();
        $('#filelist').find('table tbody tr').each(function(index){
            if ($(this).data('path').indexOf(compare) == -1) {
                $(this).addClass('hide');
            } else {
                $(this).removeClass('hide');
            }
        });
        $('#filelist table tbody').trigger('paint');
    };
    <?php if ($opcache->getOption('charts')): ?>
    var Gauge = function(el, colour) {
        this.canvas  = $(el).get(0);
        this.ctx     = this.canvas.getContext('2d');
        this.width   = this.canvas.width;
        this.height  = this.canvas.height;
        this.colour  = colour || '#079c2d';
        this.loop    = null;
        this.degrees = 0;
        this.newdegs = 0;
        this.text    = '';
        this.init = function() {
            this.ctx.clearRect(0, 0, this.width, this.height);
            this.ctx.beginPath();
            this.ctx.strokeStyle = '#e2e2e2';
            this.ctx.lineWidth = 30;
            this.ctx.arc(this.width/2, this.height/2, 100, 0, Math.PI*2, false);
            this.ctx.stroke();
            this.ctx.beginPath();
            this.ctx.strokeStyle = this.colour;
            this.ctx.lineWidth = 30;
            this.ctx.arc(this.width/2, this.height/2, 100, 0 - (90 * Math.PI / 180), (this.degrees * Math.PI / 180) - (90 * Math.PI / 180), false);
            this.ctx.stroke();
            this.ctx.fillStyle = this.colour;
            this.ctx.font = '60px sans-serif';
            this.text = Math.round((this.degrees/360)*100) + '%';
            this.ctx.fillText(this.text, (this.width/2) - (this.ctx.measureText(this.text).width/2), (this.height/2) + 20);
        };
        this.draw = function() {
            if (typeof this.loop != 'undefined') {
                clearInterval(this.loop);
            }
            var self = this;
            self.loop = setInterval(function(){ self.animate(); }, 1000/(this.newdegs - this.degrees));
        };
        this.animate = function() {
            if (this.degrees == this.newdegs) {
                clearInterval(this.loop);
            }
            if (this.degrees < this.newdegs) {
                ++this.degrees;
            } else {
                --this.degrees;
            }
            this.init();
        };
        this.setValue = function(val) {
            this.newdegs = Math.round(3.6 * val);
            this.draw();
        };
    }
    <?php endif; ?>

    $(function(){
        <?php if ($opcache->getOption('allow_realtime')): ?>
        function setCookie() {
            var d = new Date();
            var secure = (window.location.protocol === 'https:' ? ';secure' : '');
            d.setTime(d.getTime() + (<?php echo($opcache->getOption('cookie_ttl')); ?> * 86400000));
            var expires = "expires="+d.toUTCString();
            document.cookie = "<?php echo($opcache->getOption('cookie_name')); ?>=true;" + expires + ";path=/" + secure;
        };
        function removeCookie() {
            var secure = (window.location.protocol === 'https:' ? ';secure' : '');
            document.cookie = "<?php echo($opcache->getOption('cookie_name')); ?>=;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=/" + secure;
        };
        function getCookie() {
            var v = document.cookie.match('(^|;) ?<?php echo($opcache->getOption('cookie_name')); ?>=([^;]*)(;|$)');
            return v ? v[2] : null;
        };
        function updateStatus() {
            $('#toggleRealtime').removeClass('pulse');
            $.ajax({
                url: "#",
                dataType: "json",
                cache: false,
                success: function(data) {
                    $('#toggleRealtime').addClass('pulse');
                    opstate = data;
                    overviewCountsObj.setState({
                        data : opstate.overview
                    });
                    generalInfoObj.setState({
                        version : opstate.version,
                        start : opstate.overview.readable.start_time,
                        reset : opstate.overview.readable.last_restart_time
                    });
                    filesObj.setState({
                        data : opstate.files,
                        count_formatted : opstate.overview.readable.num_cached_scripts,
                        count : opstate.overview.num_cached_scripts
                    });
                    keyUp();
                }
            });
        }
        $('#toggleRealtime').click(function(){
            if (realtime === false) {
                realtime = setInterval(function(){updateStatus()}, <?php echo (int)$opcache->getOption('refresh_time') * 1000; ?>);
                $(this).text('Disable real-time update');
                setCookie();
            } else {
                clearInterval(realtime);
                realtime = false;
                $(this).text('Enable real-time update').removeClass('pulse');
                removeCookie();
            }
        });
        if (getCookie() == 'true') {
            realtime = setInterval(function(){updateStatus()}, <?php echo (int)$opcache->getOption('refresh_time') * 1000; ?>);
            $('#toggleRealtime').text('Disable real-time update');
        }
        <?php endif; ?>
        $('nav a[data-for]').click(function(){
            $('#tabs > div').hide();
            $('#' + $(this).data('for')).show();
            $('nav a[data-for]').removeClass('active');
            $(this).addClass('active');
            return false;
        });
        $(document).on('paint', '#filelist table tbody', function(event, params) {
            var trs = $('#filelist').find('tbody tr');
            trs.removeClass('alternate');
            trs.filter(':not(.hide):odd').addClass('alternate');
            filesObj.setState({showing: trs.filter(':not(.hide)').length});
        });
        $('#frmFilter').bind('keyup', debounce(keyUp, <?php echo $opcache->getOption('debounce_rate'); ?>));
    });

    var MemoryUsageGraph = React.createClass({
        getInitialState: function () {
            return {
                memoryUsageGauge: null
            };
        },
        componentDidMount: function () {
            if (this.props.chart) {
                this.state.memoryUsageGauge = new Gauge('#memoryUsageCanvas');
                this.state.memoryUsageGauge.setValue(this.props.value);
            }
        },
        componentDidUpdate: function () {
            if (this.state.memoryUsageGauge != null) {
                this.state.memoryUsageGauge.setValue(this.props.value);
            }
        },
        render: function () {
            if (this.props.chart == true) {
                return React.createElement("canvas", { id: "memoryUsageCanvas", width: "250", height: "250", "data-value": this.props.value });
            }
            return React.createElement(
                "p",
                null,
                React.createElement(
                    "span",
                    { className: "large" },
                    this.props.value
                ),
                React.createElement(
                    "span",
                    null,
                    "%"
                )
            );
        }
    });

    var HitRateGraph = React.createClass({
        getInitialState: function () {
            return {
                hitRateGauge: null
            };
        },
        componentDidMount: function () {
            if (this.props.chart) {
                this.state.hitRateGauge = new Gauge('#hitRateCanvas');
                this.state.hitRateGauge.setValue(this.props.value);
            }
        },
        componentDidUpdate: function () {
            if (this.state.hitRateGauge != null) {
                this.state.hitRateGauge.setValue(this.props.value);
            }
        },
        render: function () {
            if (this.props.chart == true) {
                return React.createElement("canvas", { id: "hitRateCanvas", width: "250", height: "250", "data-value": this.props.value });
            }
            return React.createElement(
                "p",
                null,
                React.createElement(
                    "span",
                    { className: "large" },
                    this.props.value
                ),
                React.createElement(
                    "span",
                    null,
                    "%"
                )
            );
        }
    });

    var MemoryUsagePanel = React.createClass({
        render: function () {
            return React.createElement(
                "div",
                { className: "moreinfo" },
                React.createElement(
                    "h3",
                    null,
                    "memory usage"
                ),
                React.createElement(
                    "div",
                    null,
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "total memory:"
                        ),
                        " ",
                        this.props.total
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "used memory:"
                        ),
                        " ",
                        this.props.used
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "free memory:"
                        ),
                        " ",
                        this.props.free
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "wasted memory:"
                        ),
                        " ",
                        this.props.wasted,
                        " (",
                        this.props.wastedPercent,
                        "%)"
                    )
                )
            );
        }
    });

    var StatisticsPanel = React.createClass({
        render: function () {
            return React.createElement(
                "div",
                { className: "moreinfo" },
                React.createElement(
                    "h3",
                    null,
                    "opcache statistics"
                ),
                React.createElement(
                    "div",
                    null,
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "number of cached files:"
                        ),
                        " ",
                        this.props.num_cached_scripts
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "number of hits:"
                        ),
                        " ",
                        this.props.hits
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "number of misses:"
                        ),
                        " ",
                        this.props.misses
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "blacklist misses:"
                        ),
                        " ",
                        this.props.blacklist_miss
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "number of cached keys:"
                        ),
                        " ",
                        this.props.num_cached_keys
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "max cached keys:"
                        ),
                        " ",
                        this.props.max_cached_keys
                    )
                )
            );
        }
    });

    var InternedStringsPanel = React.createClass({
        render: function () {
            return React.createElement(
                "div",
                { className: "moreinfo" },
                React.createElement(
                    "h3",
                    null,
                    "interned strings usage"
                ),
                React.createElement(
                    "div",
                    null,
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "buffer size:"
                        ),
                        " ",
                        this.props.buffer_size
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "used memory:"
                        ),
                        " ",
                        this.props.strings_used_memory
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "free memory:"
                        ),
                        " ",
                        this.props.strings_free_memory
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(
                            "b",
                            null,
                            "number of strings:"
                        ),
                        " ",
                        this.props.number_of_strings
                    )
                )
            );
        }
    });

    var OverviewCounts = React.createClass({
        getInitialState: function () {
            return {
                data: opstate.overview,
                chart: useCharts
            };
        },
        render: function () {
            var interned = this.state.data.readable.interned != null ? React.createElement(InternedStringsPanel, {
                buffer_size: this.state.data.readable.interned.buffer_size,
                strings_used_memory: this.state.data.readable.interned.strings_used_memory,
                strings_free_memory: this.state.data.readable.interned.strings_free_memory,
                number_of_strings: this.state.data.readable.interned.number_of_strings
            }) : '';
            return React.createElement(
                "div",
                null,
                React.createElement(
                    "div",
                    null,
                    React.createElement(
                        "h3",
                        null,
                        "memory"
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(MemoryUsageGraph, { chart: this.state.chart, value: this.state.data.used_memory_percentage })
                    )
                ),
                React.createElement(
                    "div",
                    null,
                    React.createElement(
                        "h3",
                        null,
                        "hit rate"
                    ),
                    React.createElement(
                        "p",
                        null,
                        React.createElement(HitRateGraph, { chart: this.state.chart, value: this.state.data.hit_rate_percentage })
                    )
                ),
                React.createElement(MemoryUsagePanel, {
                    total: this.state.data.readable.total_memory,
                    used: this.state.data.readable.used_memory,
                    free: this.state.data.readable.free_memory,
                    wasted: this.state.data.readable.wasted_memory,
                    wastedPercent: this.state.data.wasted_percentage
                }),
                React.createElement(StatisticsPanel, {
                    num_cached_scripts: this.state.data.readable.num_cached_scripts,
                    hits: this.state.data.readable.hits,
                    misses: this.state.data.readable.misses,
                    blacklist_miss: this.state.data.readable.blacklist_miss,
                    num_cached_keys: this.state.data.readable.num_cached_keys,
                    max_cached_keys: this.state.data.readable.max_cached_keys
                }),
                interned
            );
        }
    });

    var GeneralInfo = React.createClass({
        getInitialState: function () {
            return {
                version: opstate.version,
                start: opstate.overview.readable.start_time,
                reset: opstate.overview.readable.last_restart_time
            };
        },
        render: function () {
            return React.createElement(
                "table",
                null,
                React.createElement(
                    "thead",
                    null,
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "th",
                            { colSpan: "2" },
                            "General info"
                        )
                    )
                ),
                React.createElement(
                    "tbody",
                    null,
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "td",
                            null,
                            "Zend OPcache"
                        ),
                        React.createElement(
                            "td",
                            null,
                            this.state.version.version
                        )
                    ),
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "td",
                            null,
                            "PHP"
                        ),
                        React.createElement(
                            "td",
                            null,
                            this.state.version.php
                        )
                    ),
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "td",
                            null,
                            "Host"
                        ),
                        React.createElement(
                            "td",
                            null,
                            this.state.version.host
                        )
                    ),
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "td",
                            null,
                            "Server Software"
                        ),
                        React.createElement(
                            "td",
                            null,
                            this.state.version.server
                        )
                    ),
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "td",
                            null,
                            "Start time"
                        ),
                        React.createElement(
                            "td",
                            null,
                            this.state.start
                        )
                    ),
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "td",
                            null,
                            "Last reset"
                        ),
                        React.createElement(
                            "td",
                            null,
                            this.state.reset
                        )
                    )
                )
            );
        }
    });

    var Directives = React.createClass({
        getInitialState: function () {
            return { data: opstate.directives };
        },
        render: function () {
            var directiveNodes = this.state.data.map(function (directive) {
                var map = { 'opcache.': '', '_': ' ' };
                var dShow = directive.k.replace(/opcache\.|_/gi, function (matched) {
                    return map[matched];
                });
                var vShow;
                if (directive.v === true || directive.v === false) {
                    vShow = React.createElement('i', {}, directive.v.toString());
                } else if (directive.v === '') {
                    vShow = React.createElement('i', {}, 'no value');
                } else {
                    vShow = directive.v;
                }
                return React.createElement(
                    "tr",
                    { key: directive.k },
                    React.createElement(
                        "td",
                        { title: 'View ' + directive.k + ' manual entry' },
                        React.createElement(
                            "a",
                            { href: 'http://php.net/manual/en/opcache.configuration.php#ini.' + directive.k.replace(/_/g, '-'), target: "_blank" },
                            dShow
                        )
                    ),
                    React.createElement(
                        "td",
                        null,
                        vShow
                    )
                );
            });
            return React.createElement(
                "table",
                null,
                React.createElement(
                    "thead",
                    null,
                    React.createElement(
                        "tr",
                        null,
                        React.createElement(
                            "th",
                            { colSpan: "2" },
                            "Directives"
                        )
                    )
                ),
                React.createElement(
                    "tbody",
                    null,
                    directiveNodes
                )
            );
        }
    });

    var Files = React.createClass({
        getInitialState: function () {
            return {
                data: opstate.files,
                showing: null,
                allowFiles: allowFiles
            };
        },
        handleInvalidate: function (e) {
            e.preventDefault();
            if (realtime) {
                $.get('#', { invalidate: e.currentTarget.getAttribute('data-file') }, function (data) {
                    console.log('success: ' + data.success);
                }, 'json');
            } else {
                window.location.href = e.currentTarget.href;
            }
        },
        render: function () {
            if (this.state.allowFiles) {
                var fileNodes = this.state.data.map(function (file, i) {
                    var invalidate, invalidated;
                    if (file.timestamp == 0) {
                        invalidated = React.createElement(
                            "span",
                            null,
                            React.createElement(
                                "i",
                                { className: "invalid metainfo" },
                                " - has been invalidated"
                            )
                        );
                    }
                    if (canInvalidate) {
                        invalidate = React.createElement(
                            "span",
                            null,
                            ", ",
                            React.createElement(
                                "a",
                                { className: "metainfo", href: '?invalidate=' + file.full_path, "data-file": file.full_path, onClick: this.handleInvalidate },
                                "force file invalidation"
                            )
                        );
                    }
                    return React.createElement(
                        "tr",
                        { key: file.full_path, "data-path": file.full_path.toLowerCase(), className: i % 2 ? 'alternate' : '' },
                        React.createElement(
                            "td",
                            null,
                            React.createElement(
                                "div",
                                null,
                                React.createElement(
                                    "span",
                                    { className: "pathname" },
                                    file.full_path
                                ),
                                React.createElement("br", null),
                                React.createElement(FilesMeta, { data: [file.readable.hits, file.readable.memory_consumption, file.last_used] }),
                                invalidate,
                                invalidated
                            )
                        )
                    );
                }.bind(this));
                return React.createElement(
                    "div",
                    null,
                    React.createElement(FilesListed, { showing: this.state.showing }),
                    React.createElement(
                        "table",
                        null,
                        React.createElement(
                            "thead",
                            null,
                            React.createElement(
                                "tr",
                                null,
                                React.createElement(
                                    "th",
                                    null,
                                    "Script"
                                )
                            )
                        ),
                        React.createElement(
                            "tbody",
                            null,
                            fileNodes
                        )
                    )
                );
            } else {
                return React.createElement("span", null);
            }
        }
    });

    var FilesMeta = React.createClass({
        render: function () {
            return React.createElement(
                "span",
                { className: "metainfo" },
                React.createElement(
                    "b",
                    null,
                    "hits: "
                ),
                React.createElement(
                    "span",
                    null,
                    this.props.data[0],
                    ", "
                ),
                React.createElement(
                    "b",
                    null,
                    "memory: "
                ),
                React.createElement(
                    "span",
                    null,
                    this.props.data[1],
                    ", "
                ),
                React.createElement(
                    "b",
                    null,
                    "last used: "
                ),
                React.createElement(
                    "span",
                    null,
                    this.props.data[2]
                )
            );
        }
    });

    var FilesListed = React.createClass({
        getInitialState: function () {
            return {
                formatted: opstate.overview.readable.num_cached_scripts,
                total: opstate.overview.num_cached_scripts
            };
        },
        render: function () {
            var display = this.state.formatted + ' file' + (this.state.total == 1 ? '' : 's') + ' cached';
            if (this.props.showing !== null && this.props.showing != this.state.total) {
                display += ', ' + this.props.showing + ' showing due to filter';
            }
            return React.createElement(
                "h3",
                null,
                display
            );
        }
    });

    var overviewCountsObj = ReactDOM.render(React.createElement(OverviewCounts, null), document.getElementById('counts'));
    var generalInfoObj = ReactDOM.render(React.createElement(GeneralInfo, null), document.getElementById('generalInfo'));
    var filesObj = ReactDOM.render(React.createElement(Files, null), document.getElementById('filelist'));
    ReactDOM.render(React.createElement(Directives, null), document.getElementById('directives'));
</script>
<?php
    }
}
?>



        </div>
    </div>

</div>
