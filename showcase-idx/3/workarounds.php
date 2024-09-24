<?php

function showcaseidx_apply_workarounds() {
  // Post metadata fields
  add_filter( 'get_post_metadata', function( $value, $post_id, $meta_key, $single ) {
    if ( $post_id == PHP_INT_MAX ) {
      switch ( $meta_key ) {
        case '_et_pb_page_layout': return 'et_full_width_page';
        case '_et_pb_use_builder': return 'on';
      }
    }
  }, PHP_INT_MAX, 4);

  // Divi
  if ( defined( 'ET_CORE_VERSION' ) ) {
    add_filter( 'the_content', 'showcaseidx_divi_content_wrap' );
  }

  // Page links
  add_filter( 'the_permalink', function() { return home_url( '/' . get_option( 'showcaseidx_search_page' ) . '/' ); });
  add_filter( 'pre_get_shortlink', function() { return home_url( '/' . get_option( 'showcaseidx_search_page' ) . '/' ); });

  // Remove elements/filters
  add_filter( 'get_edit_post_link', '__return_empty_string') ;
  remove_filter( 'the_content', 'wpautop' );
  remove_filter( 'the_excerpt', 'wpautop' );

  // Don't redirect to the canonical URL
  remove_filter( 'template_redirect', 'redirect_canonical' );

  // Disable this thing: https://github.com/Automattic/advanced-post-cache/blob/master/advanced-post-cache.php
  // The bug we were seeing is that advanced-post-cache is hooking posts_results, which runs after posts_pre_query, and
  // they were replacing the page we "generated" with the one its caching system thought was correct (typically the home page).
  // This effectively killed our app!
  // Built-in to Wordpress.com
  add_filter('advanced_post_cache_skip_for_post_type', '__return_true');
}

function showcaseidx_divi_content_wrap( $content ) {
  return "<div class='et_pb_section'><div class='et_pb_row'><div class='et_pb_column et_pb_column_4_4'>$content</div></div></div>";
}
