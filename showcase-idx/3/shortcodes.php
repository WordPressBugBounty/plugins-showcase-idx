<?php

add_shortcode( 'showcaseidx_signin',   showcaseidx_build_shortcode( 'authform' ) );
add_shortcode( 'showcaseidx_calculator', showcaseidx_build_shortcode( 'calculator' ) );
add_shortcode( 'showcaseidx_cma',      showcaseidx_build_shortcode( 'cmaform' ) );
add_shortcode( 'showcaseidx_contact',  showcaseidx_build_shortcode( 'contactform' ) );
add_shortcode( 'showcaseidx_hotsheet', showcaseidx_build_shortcode( 'hotsheet',   array( 'name' => '',
                                                                                         'hide_map' => '',
                                                                                         'hide' => '') ) );
add_shortcode( 'showcaseidx_search',   showcaseidx_build_shortcode( 'searchform', array( 'show' => '',
                                                                                         'hide' => '',
                                                                                         'background' => '',
                                                                                         'radius' => '',
                                                                                         'padding' => '',
                                                                                         'margin' => '',
                                                                                         'submit_text' => '',
                                                                                         'search_template_id' => '' ) ) );
add_shortcode( 'showcaseidx_map',      showcaseidx_build_shortcode( 'searchmap',  array( 'show' => '',
                                                                                         'hide' => '',
                                                                                         'height' => '',
                                                                                         'search_template_id' => '' ) ) );
add_shortcode( 'showcaseidx_nav',      showcaseidx_build_shortcode( 'nav',        array( 'hide' => '',
                                                                                         'render_signin' => '' ) ) );
add_shortcode( 'showcaseidx_search_results_count', showcaseidx_build_shortcode( 'searchresultscount',   array( 'search_template_id' => '',
                                                                                         'link_to_search' => '',
                                                                                         'link_to_search_target' => '') ) );

/**
 * This function takes the rendered code from the back-end and makes it appear in Wordpress.
 *
 * IMPORTANT: calling this function will take the SCRIPT code from the widget and enqueue it to be output in the footer.
 * The reason we shunt the script code away from inline is so that other WP plugins don't adulterate our code and break the javascript or html.
 * The reason this is so dangerous is that our code includes HTML in JSON which bad plugins think is actual HTML.
 *
 * @param JSON The widget response from the backend
 * @return the HTML that should be exported inline by the caller as appropriate
 */
function showcase_export_code($widget) {
    add_action('wp_footer', function() use ($widget) {
      echo $widget->widgetScript;
    });
    return $widget->widgetHTML;
}

/**
 * @param response the result of showcase_retrieve_widget
 * @return string to pass back to WP to render for the shortcode
 */
function showcase_render_widget_for_shortcode($response) {
  if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
    $widget = json_decode( wp_remote_retrieve_body( $response ) );
    showcaseidx_enqueue_resources();

    $status = showcase_extract_account_status($widget);
    switch ($status) {
      case SHOWCASEIDX_ACCOUNT_STATUS_OK:
        return showcase_export_code($widget);
        break;
      case SHOWCASEIDX_ACCOUNT_STATUS_EXPIRED:
      case SHOWCASEIDX_ACCOUNT_STATUS_INVALID:
      case SHOWCASEIDX_ACCOUNT_STATUS_REVOKED:
        $errorInfo = showcase_get_error_code($status);
        return "[{$errorInfo['name']} #{$errorInfo['code']} - {$errorInfo['main']}] <!-- {$errorInfo['desc']} -->";
        break;
      default:
        // should never happen
        return "[showcaseidx shortcode encountered an error]<!-- unexpected status: {$status} -->";
      }
  } else {
    return '[showcaseidx shortcode encountered an error]<!--' . print_r($response, true) . '-->';
  }
}

function showcaseidx_build_shortcode( $type, $allowed = array() ) {
  return function ( $attrs ) use ( $type, $allowed ) {
    $attrs = shortcode_atts( $allowed, $attrs, 'showcaseidx_' . $type );

    $response = showcase_retrieve_widget( $type, $attrs );
    return showcase_render_widget_for_shortcode($response);
  };
}

function showcaseidx_build_query( $query ) {

  if ( isset( $query["hide_map"] ) && $query["hide_map"] ) {
    if ( isset( $query["hide"] ) && $query["hide"] ) {
      $query["hide"] = $query["hide"] . ",map";
    } else {
      $query["hide"] = "map";
    }
  }
  if ( isset( $query["hide_map"] ) ) {
    unset( $query["hide_map"] );
  }

  $split_arrays = function( &$val, $key ) {
    if ( strpos( $val, ',' ) !== false ) {
      $val = explode( ',', $val );
    }
  };

  array_walk( $query, $split_arrays );

  return http_build_query( $query );
}

function showcase_retrieve_widget( $widget, $attrs ) {
  $cookies = array();
  foreach ( $_COOKIE as $name => $value ) {
    $cookies[] = new WP_Http_Cookie( array( 'name' => $name, 'value' => $value ) );
  }

  $query = $attrs;
  $query['website_uuid'] = get_option( 'showcaseidx_website_uuid' );
  $query['bc_prune_widget'] = 1;

  return wp_remote_post(
    SHOWCASEIDX_SEARCH_HOST . '/app/renderWidget/' . $widget . '?' . urldecode( showcaseidx_build_query( $query ) ),
    array(
      'timeout' => 10,
      'httpversion' => '1.1',
      'cookies' => $cookies
    )
  );
}
