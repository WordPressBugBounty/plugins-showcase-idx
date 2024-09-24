<?php

$showcaseidx_assets = null;
$showcaseidx_should_enqueue_assets = false;

add_action('wp_enqueue_scripts', 'showcaseidx_register_resources');
function showcaseidx_register_resources($hook) {
  global $showcaseidx_assets, $showcaseidx_should_enqueue_assets;

  $response = wp_remote_get( SHOWCASEIDX_SEARCH_HOST . '/app/assets', array( 'timeout' => 5, 'httpversion' => '1.1' ) );

  if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
    $showcaseidx_assets = json_decode( wp_remote_retrieve_body( $response ) );

    foreach ( $showcaseidx_assets->css as $key => $src ) {
      wp_register_style( "showcaseidx-css-$key", $src );
    }

    foreach ( $showcaseidx_assets->js as $key => $src ) {
      wp_register_script( "showcaseidx-js-$key", $src, array(), false, true );
    }

    if ( $showcaseidx_should_enqueue_assets ) {
      showcaseidx_enqueue_resources();
    }
  }
}

function showcaseidx_enqueue_resources() {
  global $showcaseidx_assets, $showcaseidx_should_enqueue_assets;

  if ( $showcaseidx_assets ) {
    foreach ( $showcaseidx_assets->css as $key => $src ) {
      wp_enqueue_style( "showcaseidx-css-$key" );
    }

    foreach ( $showcaseidx_assets->js as $key => $src ) {
      wp_enqueue_script( "showcaseidx-js-$key" );
    }
  } else {
    $showcaseidx_should_enqueue_assets = true;
  }
}
