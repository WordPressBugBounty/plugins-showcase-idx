<?php

// APP CONSTANTS
define('SHOWCASEIDX_QUERY_VAR_SEARCH',              'ShowcaseIDX');
define('SHOWCASEIDX_SEARCH_DEFAULT_URL_NAMESPACE',  'properties' );

define('SHOWCASEIDX_QUERY_VAR_SEO_TITLE',           'ShowcaseSeoTitle');
define('SHOWCASEIDX_QUERY_VAR_SEO_XMLSITEMAP',      'ShowcaseSeoXmlSitemap');
define('SHOWCASEIDX_QUERY_VAR_SEO_KEYWORDS',        'ShowcaseSeoKeywords');
define('SHOWCASEIDX_QUERY_VAR_SEO_DESCRIPTION',     'ShowcaseSeoDescription');
define('SHOWCASEIDX_QUERY_VAR_SEO_URL',             'ShowcaseSeoURL');
define('SHOWCASEIDX_QUERY_VAR_SEO_IMAGE',           'ShowcaseSeoImage');
define('SHOWCASEIDX_QUERY_VAR_COMMUNITY',           'CommunityId');
define('SHOWCASEIDX_QUERY_VAR_LISTINGS',            'Listings');
define('SHOWCASEIDX_QUERY_VAR_LISTINGS_PAGENUM',    'ListingsPageNum');
define('SHOWCASEIDX_QUERY_VAR_LISTING',             'ListingId');
define('SHOWCASEIDX_QUERY_VAR_SITEMAP',             'Sitemap');

// Plugin Bootstrap / hook installation
function showcaseidx_plugin_setup() {
    // defaults for our options
    add_option('showcaseidx_api_v2_host',               'https://idx.showcaseidx.com');
    add_option('showcaseidx_cdn_host',                  'https://cdn.showcaseidx.com');
    add_option('showcaseidx_api_key',                   '');
    add_option('showcaseidx_template',                  '');
    add_option('showcaseidx_setup_step',                '');
    add_option('showcaseidx_cache_version',             date('r'));
    add_option('showcaseidx_url_namespace',             SHOWCASEIDX_SEARCH_DEFAULT_URL_NAMESPACE);

    // enable widgets to run short codes
    add_filter('widget_text', 'do_shortcode', 11);

    // register all of our shortcodes
    add_shortcode('showcaseidx',                     'showcaseidx_show_app');
    add_shortcode('showcaseidx_hotsheet',            'showcaseidx_show_hotsheet');
    add_shortcode('showcaseidx_custom',              'showcaseidx_custom');
    add_shortcode('showcaseidx_widget_login',        'showcaseidx_widget_login');
    add_shortcode('showcaseidx_widget_register',     'showcaseidx_widget_register');
    add_shortcode('showcaseidx_widget_230',          'showcaseidx_widget_230');
    add_shortcode('showcaseidx_widget_465',          'showcaseidx_widget_465');
    add_shortcode('showcaseidx_widget_700',          'showcaseidx_widget_700');
    add_shortcode('showcaseidx_widget_930',          'showcaseidx_widget_930');
    add_shortcode('showcaseidx_widget_updated',      'showcaseidx_widget_updated');
    add_shortcode('showcaseidx_widget_last_updated', 'showcaseidx_widget_updated');
    add_shortcode('showcaseidx_widget_contact',      'showcaseidx_widget_contact');
    add_shortcode('showcaseidx_widget_agent',        'showcaseidx_widget_agent');
    add_shortcode('showcaseidx_widget_office',       'showcaseidx_widget_office');
    add_shortcode('showcaseidx_widget_featured',     'showcaseidx_widget_featured');
    add_shortcode('showcaseidx_widget_hotsheet',     'showcaseidx_widget_hotsheet');
    add_shortcode('showcaseidx_widget_omnibox',      'showcaseidx_widget_omnibox');
    add_shortcode('showcaseidx_widget_slideshow',    'showcaseidx_widget_slideshow');

    // install routing
    add_action('init',                                              'showcaseidx_install_routing');
    add_action('template_redirect',                                 'showcaseidx_router');

    // admin hooks
    add_action('admin_menu', 'showcaseidx_create_menu_page');
    add_action('admin_init', 'register_showcaseidx_settings');
}

function showcaseidx_get_prefix() {
    $prefix = get_option('showcaseidx_url_namespace');
    if (empty($prefix)) {
        $prefix = SHOWCASEIDX_SEARCH_DEFAULT_URL_NAMESPACE;
    }
    return $prefix;
}
