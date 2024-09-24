<?php
global $wp_version;
$showcaseidx_seo_data = array('url' => NULL);

require_once(ABSPATH . "wp-admin/includes/plugin.php");
require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/widgets.php");
require_once(dirname(__FILE__) . "/admin.php");

// install our plugin
add_action('plugins_loaded', 'showcaseidx_plugin_setup');
add_action('plugins_loaded', 'showcaseidx_plugin_migration');
register_activation_hook(__FILE__, 'showcaseidx_activation_hook');

register_activation_hook(__FILE__, 'showcaseidx_cachebust_activation');
register_activation_hook(__FILE__, 'showcaseidx_install_rewrite_rules');
register_activation_hook(__FILE__, 'showcaseidx_bust_cache');
add_action('showcaseidx_cachebust', 'showcaseidx_bust_cache');
register_deactivation_hook(__FILE__, 'showcaseidx_cachebust_deactivation');

function showcaseidx_get_version() {
    $plugin_data = get_plugin_data( __FILE__ );
    $plugin_version = $plugin_data['Version'];
    return $plugin_version;
}

function showcaseidx_cachebust_activation() {
    wp_schedule_event(time(), 'hourly', 'showcaseidx_cachebust');
}

function showcaseidx_cachebust_deactivation() {
    wp_clear_scheduled_hook('showcaseidx_cachebust');
}

function showcaseidx_add_scripts() {
    $cdn_host = get_option('showcaseidx_cdn_host');
    wp_enqueue_script("showcaseidx_js", "$cdn_host/js/mydx2.js", array(), null, true);
    wp_enqueue_style("showcaseidx_css", "$cdn_host/css/screen.css");
}
add_action( 'wp_enqueue_scripts', 'showcaseidx_add_scripts' );

function showcaseidx_seo_listing_url_regex_callback($matches)
{
    $baseUrl = showcaseidx_base_url();
    list($full, $appUrl, $title) = $matches;
    $titleUrlEncoded = str_replace(" ", "_", $title);
    return "<a href=\"{$baseUrl}/{$titleUrlEncoded}/{$appUrl}\" title=\"{$title}\"";
}

function showcaseidx_router()
{
    global $wp_query;
    $api_host = get_option('showcaseidx_api_v2_host');

    if (array_key_exists(SHOWCASEIDX_QUERY_VAR_SEO_XMLSITEMAP, $wp_query->query_vars)) {
        //  Main Search page
        $page = array_key_exists('page_num', $wp_query->query_vars) ? $wp_query->query_vars['page_num'] : 'index';
        $url = get_option('showcaseidx_api_v2_host') . "/seo/" . get_option('showcaseidx_api_key') . "/sitemap-$page.xml";
        $content = showcaseidx_simple_fetch($url);
        header('Content-Type: application/xml');
        echo $content;
        exit;
    }

    if (array_key_exists(SHOWCASEIDX_QUERY_VAR_LISTINGS, $wp_query->query_vars)) {
        // Index page for SEO pages (/all), and pagination pages

        // pages go 0..n
        $currentPageNum = (int) $wp_query->get(SHOWCASEIDX_QUERY_VAR_LISTINGS_PAGENUM);
        $apiKey = get_option('showcaseidx_api_key');

        // get root "all" page
        $proxyContentBaseUrl = "$api_host/seo/{$apiKey}/"; // trailing / required
        $sitemap = showcaseidx_fetch($proxyContentBaseUrl);

        $pageMatches = array();
        $count = preg_match_all('/<a href="([^"]+)"/', $sitemap, $pageMatches);
        if ($count === 0)
        {
            wp_redirect( showcaseidx_base_url(), 302 );
            exit();
        }
        $lastPageNum = $count;

        $currentPageName = $pageMatches[1][$currentPageNum];
        $proxyContentPageUrl = "{$proxyContentBaseUrl}{$currentPageName}";
        $currentPageContent = showcaseidx_fetch($proxyContentPageUrl);

        // page content
        $content = preg_replace_callback('/<a href="#\/listings\/([^"]+)" data-url="([^"]+)"/', 'showcaseidx_seo_listing_url_regex_callback', $currentPageContent);

        // pagination
        $seoBaseUrl = showcaseidx_base_url() . '/all/';
        $seoCurrentUrl = $seoBaseUrl;

        if ($currentPageNum != 0)
        {
            $seoCurrentUrl .= "{$currentPageNum}/";
            $prevPageNum = $currentPageNum-1;
            $content .= '<link rel="prev" href="' . $seoBaseUrl . $prevPageNum . '" />';
            $content .= '<a href="' . $seoBaseUrl . $prevPageNum . '">prev</a>';
            $content .= ' ';
        }
        if ($currentPageNum < $lastPageNum)
        {
            $nextPageNum = $currentPageNum+1;
            $content .= '<link rel="next" href="' . $seoBaseUrl . $nextPageNum . '" />';
            $content .= '<a href="' . $seoBaseUrl . $nextPageNum . '">next</a>';
        }

        showcaseidx_seoify('Real Estate For Sale & For Rent', 'All listings For Sale & For Rent in the MLS.', 'real estate for sale, real estate for rent', NULL, $seoCurrentUrl);
        showcaseidx_display_templated("<h1>Real Estate For Sale and For Rent</h1>{$content}");
    }

    if (array_key_exists(SHOWCASEIDX_QUERY_VAR_LISTING, $wp_query->query_vars)) {
        // SEO page for listing
        $seo = urldecode($wp_query->get(SHOWCASEIDX_QUERY_VAR_SEO_TITLE));
        $apiKey = get_option('showcaseidx_api_key');
        $listingId = trim($wp_query->get(SHOWCASEIDX_QUERY_VAR_LISTING), ' /');
        $defaultAppUrl = "/listings/{$listingId}";
        $seoUrl = showcaseidx_base_url() . "/" . $seo . "/{$listingId}/";
        $seoDetail = json_decode(showcaseidx_fetch("$api_host/seo_listing/{$listingId}?website_id={$apiKey}"), true);
        $content = showcaseidx_generate_app($seoDetail["listing"], $defaultAppUrl);

        showcaseidx_seoify($seoDetail["title"], $seoDetail["meta_description"], $seoDetail["meta_keywords"], $seoDetail["image"], $seoUrl);

        showcaseidx_display_templated($content);
    }

    if (array_key_exists(SHOWCASEIDX_QUERY_VAR_SITEMAP, $wp_query->query_vars)) {
        $content = showcaseidx_post("$api_host/seo/intermediary/" . $wp_query->get(SHOWCASEIDX_QUERY_VAR_SITEMAP), array(
            'namespace' => showcaseidx_base_url(),
            'api_key' => get_option('showcaseidx_api_key'),
            'query' => $wp_query->get(SHOWCASEIDX_QUERY_VAR_SITEMAP)));
        showcaseidx_display_templated( $content );
    }
}

function showcaseidx_install_routing() {
    global $wp_rewrite;

    // Setting verbose_rules to false prevents these rewrites from being written to .htaccess. This is necessary so
    // that Apache doesn't overwrite our $matches
    $wp_rewrite->use_verbose_rules = false;

    // shared stuff
    add_rewrite_tag('%' . SHOWCASEIDX_QUERY_VAR_SEO_TITLE . '%', '([^&]+)');

    // map XML Sitemap index
    add_rewrite_rule(
        showcaseidx_get_prefix() . '/xmlsitemap/?$',
        'index.php?' . SHOWCASEIDX_QUERY_VAR_SEO_XMLSITEMAP . '=true',
        'top'
    );

    // map XML Sitemap page
    add_rewrite_rule(
        showcaseidx_get_prefix() . '/xmlsitemap/(\d+)/?$',
        'index.php?' . SHOWCASEIDX_QUERY_VAR_SEO_XMLSITEMAP . '=true&page_num=$matches[1]',
        'top'
    );
    add_rewrite_tag('%' . SHOWCASEIDX_QUERY_VAR_SEO_XMLSITEMAP . '%', '([^&]+)');
    add_rewrite_tag('%page_num%', '(\d+)');

    // map LISTING pages
    add_rewrite_rule(
        showcaseidx_get_prefix() . '/(.*)/([0-9]+_\\S+)/?$',
        'index.php?' . SHOWCASEIDX_QUERY_VAR_LISTING . '=$matches[2]&' . SHOWCASEIDX_QUERY_VAR_SEO_TITLE . '=$matches[1]',
        'top'
    );
    add_rewrite_tag('%' . SHOWCASEIDX_QUERY_VAR_LISTING . '%', '([^&]+)');

    add_rewrite_rule(
        showcaseidx_get_prefix() . '/(sitemap/?.*)/?$',
        'index.php?' . SHOWCASEIDX_QUERY_VAR_SITEMAP . '=$matches[1]',
        'top'
    );
    add_rewrite_tag('%' . SHOWCASEIDX_QUERY_VAR_SITEMAP . '%', '([^&]+)');
}

function showcaseidx_wp_title($title, $sep = "---")
{
    global $wp_query;

    $localTitle = $wp_query->get(SHOWCASEIDX_QUERY_VAR_SEO_TITLE);
    $localTitle = trim($localTitle);
    $localTitle = urldecode($localTitle);
    $localTitle = htmlentities($localTitle);
    if (empty($localTitle)) {
        return $title;
    } else {
        return "{$localTitle} {$sep} {$title}";
    }
}

function showcaseidx_yoast_seo_url ($url) {
    global $showcaseidx_seo_data;
    return $showcaseidx_seo_data['url'];
}

function showcaseidx_seoify($title, $description = NULL, $keywords = NULL, $image = NULL, $canonicalUrl = NULL)
{
    global $wp_query;
    global $showcaseidx_seo_data;
    $showcaseidx_seo_data['url'] = $canonicalUrl;

    $wp_query->set(SHOWCASEIDX_QUERY_VAR_SEO_TITLE, $title);
    $wp_query->set(SHOWCASEIDX_QUERY_VAR_SEO_DESCRIPTION, $description);
    $wp_query->set(SHOWCASEIDX_QUERY_VAR_SEO_KEYWORDS, $keywords);
    $wp_query->set(SHOWCASEIDX_QUERY_VAR_SEO_URL, $canonicalUrl);

    if ($image) $wp_query->set(SHOWCASEIDX_QUERY_VAR_SEO_IMAGE, $image);

    add_filter('wpseo_canonical', 'showcaseidx_yoast_seo_url');
    add_filter('wp_title', 'showcaseidx_wp_title', 10, 2);
    add_filter('wpseo_title', 'showcaseidx_wp_title');
    add_action('wp_head', 'showcaseidx_wp_head');
    add_filter('wpseo_metadesc', '__return_false');
    add_filter('wpseo_metakey', '__return_false');
    add_filter('wpseo_prev_rel_link', '__return_false');
    add_filter('wpseo_next_rel_link', '__return_false');
}

function showcaseidx_wp_head()
{
    global $wp_query;

    $seoTitle = htmlentities(trim($wp_query->get(SHOWCASEIDX_QUERY_VAR_SEO_TITLE)));
    if (!empty($seoTitle))
    {
        echo "<meta property=\"og:title\" content=\"{$seoTitle}\" />\n";
    }

    $seoDescription = htmlentities(trim($wp_query->get(SHOWCASEIDX_QUERY_VAR_SEO_DESCRIPTION)));
    if (!empty($seoDescription))
    {
        echo "<meta name=\"description\" content=\"{$seoDescription}\" />\n";
        echo "<meta property=\"og:description\" content=\"{$seoDescription}\" />\n";
    }

    $seoKeywords = htmlentities(trim($wp_query->get(SHOWCASEIDX_QUERY_VAR_SEO_KEYWORDS)));
    if (!empty($seoKeywords))
    {
        echo "<meta name=\"keywords\" content=\"{$seoKeywords}\" />\n";
    }

    $seoImage = htmlentities(trim($wp_query->get(SHOWCASEIDX_QUERY_VAR_SEO_IMAGE)));
    if (!empty($seoImage))
    {
        echo "<meta property=\"og:image\" content=\"{$seoImage}\" />\n";
    }

    $seoUrl = htmlentities(trim($wp_query->get(SHOWCASEIDX_QUERY_VAR_SEO_URL)));
    if (!empty($seoUrl))
    {
        echo "<meta property=\"og:url\" content=\"{$seoUrl}\" />\n";
    }
}

function showcaseidx_bust_cache()
{
    update_option('showcaseidx_cache_version', date('r'));
}

function showcaseidx_install_rewrite_rules()
{

    flush_rewrite_rules();
    showcaseidx_install_routing();
}

function showcaseidx_plugin_migration()
{
    $version = "showcase-version-" . showcaseidx_get_version();
    if (!get_option($version)) {
        add_action('init', 'showcaseidx_activation_hook');
        add_option($version, true);
    }
}

function showcaseidx_base_url()
{
    return home_url() . '/' . showcaseidx_get_prefix();
}

function showcaseidx_post($url, $params) {
    $response = wp_remote_post($url, array('timeout' => 60, 'body' => $params));
    return wp_remote_retrieve_body($response);
}

function showcaseidx_fetch($url)
{
    global $wp_query;
    $response = wp_remote_get($url, array('timeout' => 60));
    if (wp_remote_retrieve_response_code($response) != 200) {
        $wp_query->set_404();
        status_header( 404 );
        $error = "<center><h1>Sorry, that listing is no longer available.</h1></center><br><br>";
        showcaseidx_display_templated($error . showcaseidx_show_app(NULL));
        exit();
    }
    return wp_remote_retrieve_body($response);
}


function showcaseidx_simple_fetch($url)
{
    global $wp_query;
    $response = wp_remote_get($url, array('timeout' => 60));
    return wp_remote_retrieve_body($response);
}

function showcaseidx_cachable_fetch($seoContentURL)
{
    // cachably fetch content, with cache-busting (happens every time the admin is SAVED)
    $transient_id = 'showcaseidx-'.md5($seoContentURL . get_option('showcaseidx_cache_version'));   // max 45 chars!!! -- this is 44 *always*
    if (($seoContent = get_transient($transient_id)) === false) {
        $seoContent = "View all listings";

        // this code runs when there is no valid transient set
        $resp = wp_remote_get($seoContentURL, array('timeout' => 60));
        if ($resp instanceof WP_Error or wp_remote_retrieve_response_code($resp) != 200)
        {
            $seoContent = 'SEO PROXY ERROR, ' . print_r($resp, true);
        }
        else
        {
            $seoContent = wp_remote_retrieve_body($resp);
            if ($seoContent)
            {
                $ok = set_transient($transient_id, $seoContent, 1 * DAY_IN_SECONDS);
                if (!$ok)
                {
                    // do something....
                }
            }
        }
    }

    //$seoContent = substr($seoContent, 0, 1000); // for testing
    return $seoContent;
}

function showcaseidx_activation_hook()
{
    showcaseidx_ensure_minimum_php();
    showcaseidx_refresh_setup_expensive();
    showcaseidx_notify_hq_of_activation();
}

function showcaseidx_ensure_minimum_php()
{
  if ( version_compare( PHP_VERSION, '5.3', '<' ) )
  {
    deactivate_plugins( basename( __FILE__ ) );
    exit( 'The <strong>Showcase IDX</strong> plugin requires PHP version 5.3 or greater. You have version '.PHP_VERSION.' installed. Please contact your host to upgrade.' );
  }
}

function showcaseidx_notify_hq_of_activation()
{
    $blogInfo = array();
    foreach (array('admin_email', 'url', 'version') as $key) {
        $blogInfo[$key] = get_bloginfo($key);
    }

    $blogInfo['__cc_email'] = 'scott@showcasere.com';
    $blogInfo['__subject'] = 'Wordpress Activation!';

    $queryString = '';
    foreach ($blogInfo as $k => $v) {
        if ($queryString) $queryString .= "&";
        $v = urlencode($v);
        $queryString .= "{$k}={$v}";
    }
    $pingUrl = "http://showcasere.com/4/formmail.php?{$queryString}";

    wp_remote_get($pingUrl, array('timeout' => 60));
}
