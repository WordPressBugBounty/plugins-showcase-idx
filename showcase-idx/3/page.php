<?php

$showcase_error_codes = array (
  SHOWCASEIDX_ACCOUNT_STATUS_REVOKED => array (
    "code" => "28.2",
    "name" => "License Error",
    "main" => "The Showcase IDX license for this website is not active or doesn’t exist.",
    "desc" => "Please go to <a href='https://admin.showcaseidx.com'>admin.showcaseidx.com</a> to review your active website licenses."
  ),
  SHOWCASEIDX_ACCOUNT_STATUS_EXPIRED => array (
    "code" => "28.3",
    "name" => "Trial Expired",
    "main" => "Your Showcase IDX Trial Has Expired.",
    "desc" => "To activate your Showcase IDX account, go to <a href='https://admin.showcaseidx.com'>admin.showcaseidx.com</a> or check your email."
  ),
  SHOWCASEIDX_ACCOUNT_STATUS_INVALID => array (
    "code" => "28.1",
    "name" => "Provision Error",
    "main" => "This Showcase IDX website license is currently active in another WordPress install.",
    "desc" => "Please go to your Showcase IDX Plugin page in your WordPress dashboard to activate the license for this website."
  )
);

function showcase_get_error_code($status) {
  global $showcase_error_codes;
  return $showcase_error_codes[$status];
}

function showcase_error_page( $error ) {
  $body = "<div><strong>" . $error["main"] . "</strong></div>";
  $body .= "<p>" . $error["desc"] . "</p>";
  $body .= "<small>If you have questions or need help, please contact support at 1-800-478-0181 or email <a href='mailto:help@showcaseidx.com'>help@showcaseidx.com</a>.</small>";
  $body .= "<div style='font-family: monospace; color: #999;'>Code: " . $error["code"] . "</div>";

  return showcaseidx_create_page( $error["name"], $body );
}

function showcase_revoked_page() {
  $body  = "<p><img src='https://showcase-wp-showcase.netdna-ssl.com/wp-content/uploads/banner-1880x609.png'></p>";
  $body .= "<h1 style='text-align: center'>Showcase IDX Plugin is not active</h1>";
  $body .= "<p><strong>The Showcase IDX license for this website is not active or doesn't exist.</strong></p>";
  $body .= "<h2>Have you recently downloaded the plugin?</h2>";
  $body .= "<p><a href='https://showcaseidx.com/get-started/'>Click here</a> to start your free trial or activate your license.</p>";
  $body .= "<p>";
  $body .=    "Have questions about adding ";
  $body .=    "<a href='https://showcaseidx.com/idx-search-and-results/'>MLS Listing Search</a>";
  $body .=    ", how to ";
  $body .=    "<a href='https://showcaseidx.com/state-of-the-art-mapping-idx/'>customize</a>";
  $body .=    " your mapping IDX, enabling social enrichment for your contacts in our ";
  $body .=    "<a href='https://showcaseidx.com/real-estate-crm-tools/'>Real Estate CRM Tools</a>";
  $body .=    ", or keeping ";
  $body .=    "<a href='https://showcaseidx.com/real-estate-listing-pages/'>visitors on your site</a>";
  $body .=    " longer with stunning listing pages? Our team will be happy to talk with you and answer any questions. Just ";
  $body .=    "<a href='https://showcaseidx.com/contact/'>schedule 15 minutes with us.</a>";
  $body .= "</p>";
  $body .= "<h2>Are you a current or previous customer?</h2>";
  $body .= "<p>";
  $body .=    "Please go to ";
  $body .=    "<a href='https://admin.showcaseidx.com/'>admin.showcaseidx.com</a>";
  $body .=    " to review your active website licenses.";
  $body .= "</p>";
  $body .= "<p>";
  $body .=    "If you have questions or need help, please contact support at 1-800-478-0181 or ";
  $body .=    "<a href='https://showcaseidx.com/contact/'>contact support here</a>.";
  $body .= "</p>";
  $body .= "<p>Code: 28.2</p>";

  return showcaseidx_create_page( "License Error", $body );
}

/***
 * Return the status of the account based on the received widget. 
 * @param array Widget JSON data
 * @return string One of the SHOWCASEIDX_ACCOUNT_STATUS_* constants.
 */
function showcase_extract_account_status($widget_json) {
  // analyze in a particular order
  // INVALID first
  // then REVOKED -- because if revoked & expired REVOKED is more material
  // then EXPIRED -- we allow expired for a few weeks before revoking
  // This order is used since it's the most helpful / least aggressive to display publicly.
  if ( $widget_json->installation_uuid != get_option( 'showcaseidx_install_id' ) ) {
    return SHOWCASEIDX_ACCOUNT_STATUS_INVALID;
  } elseif ($widget_json->revoked) {
    return SHOWCASEIDX_ACCOUNT_STATUS_REVOKED;
  } elseif ($widget_json->expired) {
    return SHOWCASEIDX_ACCOUNT_STATUS_EXPIRED;
  }
  return SHOWCASEIDX_ACCOUNT_STATUS_OK;
}

// It might be much cleaner to just hook `the_content` here: https://developer.wordpress.org/reference/hooks/the_content/
// the_content can be configured to process shortcodes AFTER the hook.
// And we might not have to do all of this less-than-robust posts_pre_query work in this case...
function showcase_render_search_page( WP $wp, $widget_url_path, $widget_url_query ) {
  global $showcase_error_codes;

  showcaseidx_enqueue_resources();

  $response = showcase_retrieve_app( $widget_url_path, $widget_url_query );

  $http_status_code = wp_remote_retrieve_response_code( $response );
  if ( $http_status_code == 200 ) {
    header( 'Set-Cookie: ' . wp_remote_retrieve_header( $response, 'set-cookie' ) );

    $widget = json_decode( wp_remote_retrieve_body( $response ) );

    if ( isset( $widget->http_status ) ) {
      add_action( 'wp', function() use ( $widget ) {
        status_header( $widget->http_status );
      } );
    }

    // generate a WP page...
    $account_status = showcase_extract_account_status($widget);
    switch ($account_status) {
    case SHOWCASEIDX_ACCOUNT_STATUS_REVOKED:
      $page = showcase_revoked_page();
      break;
    case SHOWCASEIDX_ACCOUNT_STATUS_EXPIRED:
      $page = showcase_error_page( $showcase_error_codes['expired'] );
      break;
    case SHOWCASEIDX_ACCOUNT_STATUS_INVALID:
      $page = showcase_error_page( $showcase_error_codes['invalid'] );
      break;
    case SHOWCASEIDX_ACCOUNT_STATUS_OK:
      // show a real SIDX search
      $existing_page = get_page_by_path( get_option( 'showcaseidx_search_page' ) );

      // if the site has configured a page at the default search path, be sure to use it
      if ( $existing_page ) {
        $page = $existing_page;

        // if the page already has the [showcaseidx] shortcode where they want it, it's all good
        // otherwise, we'll append what the shortcode normally produces to the end of the page.
        if ( !preg_match( '/\[showcaseidx\]/', $existing_page->post_content, $matches ) ) {
          $page->post_content = $page->post_content . '[showcaseidx]';
        }
      }
      // if the site doesn't already have a page configured, bootstrap one which just has our default shortcode
      else
      {
        $page = showcaseidx_create_page( $widget->metaData->title, '[showcaseidx]' );
      }
      break;
    }

    // apply these to whatever page we generate
    add_filter( 'posts_pre_query', function ( $posts, WP_Query $query ) use ( $page, $widget ) {
      if ( $query->is_main_query() )
      {
        add_filter( 'template_include', function( $template ) use ( $widget ) {
          if ( isset( $widget->http_status ) && $widget->http_status == '404' ) {
            status_header( 404 );
          } else {
            status_header( 200 );
          }

          return $template;
        });
        return showcaseidx_setup_query( $query, $page );
      }
    }, 10, 2 );

    add_shortcode( 'showcaseidx', function() use ($widget) {
      return showcase_export_code($widget);
    });

    showcaseidx_setup_seo( $widget->metaData );
    showcaseidx_apply_workarounds();
  } else {
    add_action( 'wp', function() use ( $http_status_code ) {
      if ($http_status_code == "") {
        $http_status_code = 500;
      }
      status_header( $http_status_code );
    } );
    $page = showcaseidx_create_page( 'Error', 'Error communicating with Showcase IDX' );
  }
}

function showcase_retrieve_app( $path, $query ) {
  $cookies = array();
  foreach ( $_COOKIE as $name => $value ) {
    $cookies[] = new WP_Http_Cookie( array( 'name' => $name, 'value' => $value ) );
  }

  parse_str( $query, $query_vars );

  $query_vars['website_uuid'] = get_option( 'showcaseidx_website_uuid' );
  $query_vars['bc_prune_widget'] = 1;

  return wp_remote_post(
    SHOWCASEIDX_SEARCH_HOST . '/app/render' . $path . '?' . http_build_query( $query_vars ),
    array(
      'timeout' => 10,
      'httpversion' => '1.1',
      'cookies' => $cookies,
      'body' => array_map( 'stripslashes', $_POST )
    )
  );
}

function showcaseidx_create_page( $title, $content ) {
  $post = array(
    'ID'             => PHP_INT_MAX,
    'post_title'     => $title,
    'post_name'      => sanitize_title( $title ),
    'post_content'   => $content,
    'post_excerpt'   => '',
    'post_parent'    => 0,
    'menu_order'     => 0,
    'post_type'      => 'page',
    'post_status'    => 'publish',
    'comment_status' => 'closed',
    'ping_status'    => 'closed',
    'comment_count'  => 0,
    'post_password'  => '',
    'to_ping'        => '',
    'pinged'         => '',
    'guid'           => get_home_url() . '/' . get_option( 'showcaseidx_search_page' ) . '/',
    'post_date'      => current_time( 'mysql' ),
    'post_date_gmt'  => current_time( 'mysql', 1 ),
    'post_author'    => is_user_logged_in() ? get_current_user_id() : 0,
    'filter'         => 'raw',
    'ancestors'      => array(),
    'is_virtual'     => TRUE
  );

  return new WP_Post( (object) $post );
}

function showcaseidx_setup_query( $query, $page ) {
  $query->is_page       = TRUE;
  $query->is_singular   = TRUE;
  $query->is_single     = FALSE;
  $query->is_search     = FALSE;
  $query->is_404        = FALSE;
  $query->is_home       = FALSE;
  $query->found_posts   = 1;
  $query->post_count    = 1;
  $query->max_num_pages = 1;

  $posts = array( $page );
  $post = $page;

  $GLOBALS['post'] = $post;

  $query->posts          = $posts;
  $query->post           = $post;
  $query->queried_object = $post;
  $query->virtual_page   = $post;

  $query->queried_object_id = $post->ID;
  $query->query_vars['p'] = $query->query_vars['page_id'] = $post->ID;

  return $posts;
}
