<?php

add_filter( 'do_parse_request', 'showcaseidx_parse_request', -10, 2 );
function showcaseidx_parse_request( $continueParsingRequest, WP $wp ) {
  $url = parse_url( $_SERVER['REQUEST_URI'] );
  $home_url = parse_url( get_home_url() );
  $base = isset( $home_url['path'] ) ? '/' . trim( $home_url['path'], '/') . '/' : '/';

  // XML Sitemaps
  if ( sitemap_match($base, $url, $page) ) {
    header( 'Content-Type: application/xml' );
    print showcaseidx_get_xmlsitemap( $page );
    exit;
  }

  // Session Cookie Image
  if ( preg_match( '#^' . $base . get_option( 'showcaseidx_search_page' ) . '/signin/(.*)#', $url['path'], $matches ) ) {

    showcaseidx_get_signin_image( $matches[1] );
  }

  // Speed Test / Diagnostic tool
  if ( preg_match( '#^' . $base . get_option( 'showcaseidx_search_page' ) . '/diagnostics/?#', $url['path'], $matches ) ) {
      require_once(__DIR__ . "/diagnostics.php");
      showcase_render_diagnostics_page( $wp );
  }

  // Default Search Page -- this section needs to go last
  if ( preg_match( '#^' . $base . get_option( 'showcaseidx_search_page' ) . '($|/.*)#', $url['path'], $matches ) ) {
    // Flag WP to not continue parsing the request; we *OWN* our `showcaseidx_search_page` namespace!!!
    $continueParsingRequest = false;
    $wp->query_vars = [];

    // grab our info from the URL
    $path = $matches[1];

    if ( $path == '' ) {
      header( "HTTP/1.1 301 Moved Permanently" );
      header( "Location: " . get_home_url() . '/' . get_option( 'showcaseidx_search_page' ) . '/' );
      exit;
    }

    // render our page
    $query = !isset( $url['query'] ) ?: $url['query'];
    showcase_render_search_page( $wp, $path, $query );
    $continueParsingRequest = true;
  }

  return $continueParsingRequest;
}

function sitemap_match($base, $url, &$page) {
  // This is to match the following:

  // * xmlsitemap/
  // * xmlsitemap/:page/
  if ( preg_match( '#^' . $base . get_option( 'showcaseidx_search_page' ) . '/xmlsitemap/(p\d*/)?$#', $url['path'], $matches ) ) {
    $page = isset( $matches[1] ) ? $matches[1] : null;
    return true;
  }

  // * xmlsitemap
  if ( preg_match( '#^' . $base . get_option( 'showcaseidx_search_page' ) . '/xmlsitemap$#', $url['path'], $matches ) ) {
    $page = null;
    return true;
  }

  // * xmlsitemap/:page
  if ( preg_match( '#^' . $base . get_option( 'showcaseidx_search_page' ) . '/xmlsitemap/(p\d+)$#', $url['path'], $matches ) ) {
    $page = $matches[1] . '/';
    return true;
  }
}

function showcaseidx_get_xmlsitemap( $page = '' ) {
  $website_uuid = get_option( 'showcaseidx_website_uuid' );
  $api_url = SHOWCASEIDX_SEARCH_HOST . '/app/xmlsitemap/';

  $response = wp_remote_get( $api_url . $website_uuid . '/' . $page . '?p=1', array( 'timeout' => 30, 'httpversion' => '1.1' ) );

  if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
    return wp_remote_retrieve_body( $response );
  } else {
    return '';
  }
}

function showcaseidx_get_signin_image( $lead_uuid ) {
  $website_uuid = get_option( 'showcaseidx_website_uuid' );
  $api_url = SHOWCASEIDX_SEARCH_HOST . '/app/signin/image/';

  $response = wp_remote_get( $api_url . $lead_uuid, array( 'timeout' => 5, 'httpversion' => '1.1' ) );

  if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
    header( 'Set-Cookie: ' . wp_remote_retrieve_header( $response, 'set-cookie' ) );
    header( 'Content-Type: '. wp_remote_retrieve_header( $response, 'content-type' ) );

    print wp_remote_retrieve_body( $response );
    exit;
  }
}
