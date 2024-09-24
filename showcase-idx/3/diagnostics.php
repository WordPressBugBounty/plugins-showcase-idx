<?php

/**
 * Code to support our Plugin diagnostics.
 */

function showcase_render_diagnostics_page( WP $wp ) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php' );
    require_once(__DIR__ . '/opcache.php');

    $enableTemplate = true;
    $showcaseidx_diagnostic_shortcode = '[showcaseidx_map]';    // this shortcode will display on the diag page
    $wp_theme = wp_get_theme();

    $active_plugins = [];
    $showcaseidx_plugin = null;
    foreach (get_option('active_plugins') as $plugin_file) {
      $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
      if (strpos($plugin_file, 'showcaseidx.php') !== false) {
        $showcaseidx_plugin = $data;
      } else {
        $active_plugins[$plugin_file] = $data;
      }
    }

    global $wp_version;
    $data = [
        'wp_version'                => $wp_version,
        'php_version'               => phpversion(),
        'php_sapi_name'             => php_sapi_name(),
        'php_memory_limit'          => ini_get('memory_limit'),
        'php_bootstrap_time'        => round(microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT'], 3),
        'wp_theme'                  => $wp_theme->get('Name'),
        'wp_theme_version'          => $wp_theme->get('Version'),
        'active_plugins'            => $active_plugins,
        'showcaseidx_plugin'        => $showcaseidx_plugin,
        'showcaseidx_website_uuid'  => get_option('showcaseidx_website_uuid'),
        'showcaseidx_install_id'    => get_option('showcaseidx_install_id'),
    ];

    // run sidx shortcode
    $t0 = microtime(TRUE);
    $showcaseidx_diagnostic_shortcode_output = do_shortcode($showcaseidx_diagnostic_shortcode);
    if ($showcaseidx_diagnostic_shortcode_output === $showcaseidx_diagnostic_shortcode) {
        $showcaseidx_diagnostic_shortcode_output = "<em><strong>SHORTCODE {$showcaseidx_diagnostic_shortcode} NOT PROCESSED!</strong></em>";
    } else if (empty($showcaseidx_diagnostic_shortcode_output)) {
        $showcaseidx_diagnostic_shortcode_output = "<em><strong>SHORTCODE {$showcaseidx_diagnostic_shortcode} RETURNED NO CONTENT!</strong></em>";
    }
    $data['sidx_shortcode_exec_time'] = number_format(microtime(TRUE) - $t0, 3, ".", ",");

    $opcache = \OpcacheGui\OpCacheService::init()->getData();

    if ($enableTemplate) {
        get_header();
    } else {
        echo "<html><body>";
    }
    echo <<<HTML
<style type-="text/css">
table td, table th {
    word-break: normal;
}
table th {
    text-align: left;
}
.help {
    display: block;
    font-style: italic;
    font-size: 75%;
}
div.sidx_diagnostic_body {
    width: 80%;
    margin: auto;
}
ul.warnings li {
    color: red;
    font-weight: bold;
}
</style>
        <div class=sidx_diagnostic_body>
        <h1>Showcase IDX Diagnostics</h1>
        <table border=1 cellpadding=5 cellspacing=0 style="width: 75%; margin: auto;">
            <tr>
                <th colspan=2>ShowcaseIDX Plugin Info</th>
            </tr>
            <tr>
                <td width="70%">Version</td>
                <td width="30%">{$showcaseidx_plugin['Version']}</td>
            </tr>
            <tr>
                <td width="50%">Website UUID</td>
                <td width="50%" nowrap>{$data['showcaseidx_website_uuid']}</td>
            </tr>
            <tr>
                <td width="50%">Installation UUID</td>
                <td width="50%" nowrap>{$data['showcaseidx_install_id']}</td>
            </tr>
            <tr>
                <th colspan=2>Runtime Execution Data</th>
            </tr>
            <tr>
                <td width="70%">PHP Script Time to Bootstrap SIDX Plugin</td>
                <td width="30%">{$data['php_bootstrap_time']} seconds</td>
            </tr>
            <tr>
                <td>PHP Script Time to Load Showcase IDX <a href="#shortcode">[showcaseidx_search] shortcode</a></td>
                <td>{$data['sidx_shortcode_exec_time']} seconds</td>
            </tr>
            <tr>
                <th colspan=2>Wordpress Config</th>
            </tr>
            <tr>
                <td>Wordpress Version</td>
                <td>{$data['wp_version']}</td>
            </tr>
            <tr>
                <td>Current Theme</td>
                <td>{$data['wp_theme']} v{$data['wp_theme_version']}</td>
            </tr>
            <tr>
                <th colspan=2>PHP Data</th>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td>{$data['php_version']}</td>
            </tr>
            <tr>
                <td>PHP API</td>
                <td>{$data['php_sapi_name']}</td>
            </tr>
            <tr>
                <td>PHP Memory Limit</td>
                <td>{$data['php_memory_limit']}</td>
            </tr>
            <tr>
                <th colspan=2>Wordpress Active Plugins</th>
            </tr>
HTML;
    foreach ($data['active_plugins'] as $plugin_file => $plugin_data) {
        echo "
            <tr>
                <td>{$plugin_file}</td>
                <td><pre>" . var_export($plugin_data,true) . "</pre></td>
            </tr>
        ";
    }

    echo <<<HTML
            <tr>
                <th colspan=2>PHP Opcache</th>
            </tr>
HTML;
if ($opcache['enabled']) {
    echo <<<HTML
            <tr>
                <td>Extension Installed</td>
                <td>{$opcache['version']['opcache_product_name']}</td>
            </tr>
            <tr>
                <td>
                    Configuration: opcache.validate_timestamps
                    <div class=help>Boolean. If enabled, OPcache will check for updated scripts every opcache.revalidate_freq seconds. When this directive is disabled, you must reset OPcache manually via opcache_reset(), opcache_invalidate() or by restarting the Web server for changes to the filesystem to take effect.
                </td>
                <td>{$opcache['directives']['opcache.validate_timestamps']}</td>
            </tr>
            <tr>
                <td>
                    Configuration: opcache.revalidate_freq
                    <div class=help>Integer. How often to check script timestamps for updates, in seconds. 0 will result in OPcache checking for updates on every request.<br />
                    <br />This configuration directive is ignored if opcache.validate_timestamps is disabled.
                    </div>
                </td>
                <td>{$opcache['directives']['opcache.revalidate_freq']}</td>
            </tr>
            <tr>
                <td>
                    Hit Rate
                    <div class=help>A "hit" is a compiled php script that is loaded from cache, rather than re-compiled each request. If cache is installed and working property, you should see a hit rate well above 50%. Below 10% indicates the cache isn’t being used usefully.</div>
                </td>
                <td>{$opcache['overview']['hit_rate_percentage']}%</td>
            </tr>
            <tr>
                <td>
                    Num Cached Scripts
                    <div class=help>Shows how many php scripts are in the cache. In a normally-functioning opcache, there should be many hundreds-to-thousands of files. Seeing less than this is an indicator that the cache isn't being used usefully.</div>
                </td>
                <td>{$opcache['overview']['num_cached_scripts']} (out of {$opcache['directives']['opcache.max_accelerated_files']} max)</td>
            </tr>
HTML;
        } else {
    echo <<<HTML
            <tr>
                <td>Opcache Enabled</td>
                <td>No. {$opcache['version']['opcache_product_name']}</td>
            </tr>
HTML;
        }
    echo "</table>";

    echo "<a name=shortcode><h2>Showcase IDX Search Test</h2></a><p><em>{$showcaseidx_diagnostic_shortcode} shortcode output:</em></p>";
    echo "<div>{$showcaseidx_diagnostic_shortcode_output}</div>";
    echo "</div>";

    if ($enableTemplate) {
        get_footer();
    } else {
        echo "</body></html>";
    }
    exit;
}

