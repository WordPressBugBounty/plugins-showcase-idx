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

  $response = wp_remote_get( $api_url . $lead_uuid, array(
      'timeout' => 5,
      'httpversion' => '1.1',
      'headers' => [
          'Origin' => home_url(),
      ]
  ) );

    if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
        $cookies = wp_remote_retrieve_header( $response, 'set-cookie' );

        if ( $cookies ) {
            showcaseidx_set_cookies( $cookies );
        }

        header( 'Content-Type: ' . wp_remote_retrieve_header( $response, 'content-type' ) );
        echo wp_remote_retrieve_body( $response );
        exit;
    }
}

function showcaseidx_set_cookies( $cookies ) {
    $current_domain = parse_url( home_url(), PHP_URL_HOST );
    $default_expiry = time() + (90 * DAY_IN_SECONDS); // 3 months

    foreach ( (array) $cookies as $cookie ) {
        $parsed = showcaseidx_parse_cookie_header( $cookie );
        if ( ! $parsed ) {
            continue;
        }

        $cookie_options = array(
            'expires'  => $parsed['expires'] ?: $default_expiry,
            'path'     => $parsed['path'],
            'domain'   => $current_domain,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        );

        if ( version_compare( PHP_VERSION, '7.3.0', '>=' ) ) {
            setcookie( $parsed['name'], $parsed['value'], $cookie_options );
        } else {
            $cookie_string = sprintf(
                '%s=%s; expires=%s; path=%s; domain=%s; samesite=%s',
                $parsed['name'],
                $parsed['value'],
                gmdate( 'D, d M Y H:i:s T', $cookie_options['expires'] ),
                $cookie_options['path'],
                $cookie_options['domain'],
                $cookie_options['samesite']
            );
            if ( $cookie_options['secure'] ) {
                $cookie_string .= '; secure';
            }
            if ( $cookie_options['httponly'] ) {
                $cookie_string .= '; httponly';
            }
            header( 'Set-Cookie: ' . $cookie_string, false );
        }
    }
}

function showcaseidx_parse_cookie_header( $cookie_header ) {
    $parts = explode( ';', $cookie_header );
    $cookie = [];
    foreach ( $parts as $index => $part ) {
        $part = trim( $part );
        if ( $index === 0 ) {
            // First part is always name=value
            list( $name, $value ) = explode( '=', $part, 2 );
            $cookie['name'] = $name;
            $cookie['value'] = $value;
        } else {
            if ( stripos( $part, 'expires=' ) === 0 ) {
                $cookie['expires'] = strtotime( substr( $part, 8 ) );
            } elseif ( stripos( $part, 'path=' ) === 0 ) {
                $cookie['path'] = substr( $part, 5 );
            } elseif ( stripos( $part, 'domain=' ) === 0 ) {
                $cookie['domain'] = substr( $part, 7 );
            }
        }
    }
    // Set defaults if not present
    if ( !isset( $cookie['expires'] ) ) $cookie['expires'] = 0;
    if ( !isset( $cookie['path'] ) ) $cookie['path'] = '/';
    return $cookie;
}

function showcaseidx_get_cookies() {
    $wp_cookies = array();

    if ( isset( $_COOKIE['sidx_token'] ) ) {
        $wp_cookie = new WP_Http_Cookie( array(
            'name'  => 'sidx_token',
            'value' => $_COOKIE['sidx_token'],
            'path'  => '/',
            'domain' => '',
        ) );
        $wp_cookies[] = $wp_cookie;
    }

    return $wp_cookies;
}