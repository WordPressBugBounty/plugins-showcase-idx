<?php

// SHORTCODE HANDLERS
// curried shortcode generators
function showcaseidx_widget_login() { return showcaseidx_generate_app(null, "/login"); }
function showcaseidx_widget_register() { return showcaseidx_generate_app(null, "/register"); }
function showcaseidx_widget_230() { return showcaseidx_generate_widget('widgets/230'); }
function showcaseidx_widget_465() { return showcaseidx_generate_widget('widgets/465'); }
function showcaseidx_widget_700() { return showcaseidx_generate_widget('widgets/700'); }
function showcaseidx_widget_930() { return showcaseidx_generate_widget('widgets/930'); }
function showcaseidx_widget_contact() { return showcaseidx_generate_widget('widgets/contact'); }
function showcaseidx_widget_omnibox() { return showcaseidx_generate_widget('widgets/omnibox'); }
function showcaseidx_widget_updated() { return showcaseidx_generate_widget('widgets/updated'); }
function showcaseidx_widget_featured() { return showcaseidx_generate_widget('widgets/listing/featured'); }
function showcaseidx_widget_agent() { return showcaseidx_generate_widget('widgets/listing/agent'); }
function showcaseidx_widget_office() { return showcaseidx_generate_widget('widgets/listing/office'); }
function showcaseidx_widget_slideshow($scParams) {
    $shortcodeAttrs = shortcode_atts(array(
        'name' => '',
        'limit' => null
    ), $scParams);
    $name = $shortcodeAttrs['name'];
    $limit = $shortcodeAttrs['limit'];
    return showcaseidx_generate_widget("widgets/slideshow?name=$name&limit=$limit");
}
function showcaseidx_widget_hotsheet($scParams) { return showcaseidx_generate_widget("widgets/listing/hotsheet?name=$scParams"); }
function showcaseidx_generate_widget($type)
{
    $config = showcaseidx_generate_config();
    $api_host    = get_option('showcaseidx_api_v2_host');

    $widgetUrl = "$api_host/{$type}";
    $widget = showcaseidx_cachable_fetch($widgetUrl);

    $searchHostPage = showcaseidx_base_url() . '/';
    $widget = str_replace('action_url', $searchHostPage, $widget);
    return <<<EOT
        {$config}
        {$widget}
EOT;
}

function showcaseidx_show_app($scParams) {
    $seoPlaceholder = '<a href="' . showcaseidx_base_url() . '/sitemap">View all listings</a>';
    return showcaseidx_generate_app($seoPlaceholder, NULL, NULL, $scParams = $scParams);
}

function showcaseidx_show_hotsheet($scParams) {
    $shortcodeAttrs = shortcode_atts(array(
        'type' => 'custom',                 // custom, agent, office
        'name' => '',                       // name of hotsheet; only referenced for type=custom
        'hide_map' => false,
        'hide_search' => false,
        'agent_id' => null,
        'office_id' => null,
        'limit' => null
    ), $scParams);
    $jsonEncoded = json_encode(array('hotsheet' => $shortcodeAttrs));

    // Get SEO listings for custom hotsheets
    if (isset($scParams['name'])) {
        echo "<noscript>";
        echo showcaseidx_post(get_option('showcaseidx_api_v2_host') . "/seo/hotsheet_listings",
            array("hotsheet_name" => $scParams['name'], "api_key" => get_option('showcaseidx_api_key')));
        echo "</noscript>";
    }
    return showcaseidx_generate_app("{$shortcodeAttrs['type']} hotsheet", NULL, $jsonEncoded);
}

function showcaseidx_custom($attrs, $content = null) {
  $attrs = shortcode_atts(array(
      'restrict' => NULL,
      'name' => NULL
  ), $attrs);

  $legacy_conf = array(
    'restrictDQL' => $attrs['restrict'],
    'searchName' => $attrs['name'],
    'preSelectedAttributes' => explode( "\n", wp_strip_all_tags( $content ) )
  );

  return showcaseidx_generate_app("", NULL, NULL, NULL, json_encode($legacy_conf));
}

/*************** HELPER FUNCTIONS FOR SHORTCODE GENERATORS **********************/
function showcaseidx_generate_app($seoPlaceholder = NULL, $defaultAppUrl = NULL, $customSearchConfig = NULL, $scParams = NULL, $legacy_conf = NULL) {
    $customSearchConfig = $customSearchConfig ?: showcaseidx_get_custom_widget_config();

    $config = showcaseidx_generate_config($customSearchConfig, $scParams, $legacy_conf);
    $api_host = get_option('showcaseidx_api_v2_host');
    $defaultAppUrl = $defaultAppUrl ? showcaseidx_generate_default_app_url($defaultAppUrl) : NULL;
    $widget = apply_filters('showcase_widget_content', showcaseidx_cachable_fetch("$api_host/wordpress_noscript"));

    return <<<EOT
        {$config}
        {$defaultAppUrl}
        <noscript>
            {$seoPlaceholder}
        </noscript>
        {$widget}
EOT;
}

function showcaseidx_generate_config($customSearchConfig = null, $scParams = NULL, $legacy_conf = NULL) {
    $api_key = get_option('showcaseidx_api_key');
    $api_root = get_option('showcaseidx_api_v2_host');
    $cdn_root = get_option('showcaseidx_cdn_host');

    $legacy_conf = $legacy_conf ?: 'null';
    $customSearchConfig = $customSearchConfig ?: 'null';
    $scParams = is_null($scParams) ? 'null' : json_encode($scParams);

    return <<<EOT
<div></div> <!-- Hack for some plugin removing script tags -->
<script type="text/javascript">
var SHOWCASE_CONF = window.SHOWCASE_CONF || {}
SHOWCASE_CONF.WEBSITE_ROOT = "{$api_root}";
SHOWCASE_CONF.CDN_ROOT = "{$cdn_root}";
SHOWCASE_CONF.WEBSITE_ID = "{$api_key}";
SHOWCASE_CONF.LEGACY_CONF = SHOWCASE_CONF.LEGACY_CONF || $legacy_conf;
SHOWCASE_CONF.SEARCH_CONF = SHOWCASE_CONF.SEARCH_CONF || $customSearchConfig;
SHOWCASE_CONF.scParams = SHOWCASE_CONF.scParams || $scParams;
</script>
EOT;
}

// grabs & sanitizes the "customSearchConfig" from the form data posted by widgets (see showcaseidx_generate_config)
function showcaseidx_get_custom_widget_config()
{
    $customConfig = NULL;
    if (isset($_POST['json']))
    {
        $customConfig = stripslashes($_POST['json']);
    }
    return $customConfig;
}

function showcaseidx_display_templated($content)
{
    $templateName = get_option('showcaseidx_template');
    // select template....
    echo get_header($templateName);
    echo '<div id="mydx-container">';
    echo $content;
    echo '</div>';
    echo get_footer($templateName);
    exit;
}

function showcaseidx_generate_default_app_url($url)
{
    return "<script>window.location.href = '#{$url}';</script>";    // @todo there's probably a better way to do this
}
