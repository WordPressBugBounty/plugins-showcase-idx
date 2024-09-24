<?php

function showcaseidx_setup_seo( $metadata ) {
  // Don't bother if we don't have metadata
  if ( !$metadata ) return;

  $canonical = isset( $metadata->canonical ) ? ( function () use ( $metadata ) {
    return $metadata->canonical;
  } ) : '__return_false';
  
  add_filter('wpseo_canonical', $canonical);

  // Page Title
  $title_filter = function ( $title, $sep = "-" ) use ( $metadata ) {
    // if our metadata title is already in title, don't do anything
    if (stripos($title, $metadata->title) !== false) {
      return $title;
    }

    $better_title = $metadata->title;
    if (!empty($title)) {
      $better_title .=  "{$sep} {$title}";
    }

    return $better_title;
  };

  // Social tags
  $metaProps = [];
  $foundN = preg_match_all('/<meta [^>]*? property="og:[^"]*" content="[^"]*"\s*\/>/', $metadata->meta, $metaMatches);
  if ($foundN) {
    foreach ($metaMatches[0] as $meta) {
      preg_match('/property="(?<property>[^"]+)"\s+content="(?<content>[^"]+)"/', $meta, $m);
      $metaProps[$m['property']] = $m['content'];
    }
  }

  // the wp_title is the OLD pre WP 4.4 way of hooking title; have it here for completeness
  add_filter( 'wp_title', $title_filter, 10, 2 );
  // most modern themes use wp_head() which gets title via the pre_get_document_title hook.
  add_filter( 'pre_get_document_title', $title_filter);

  // Meta Tags
  add_action( 'wp_head', function () use ( $metadata ) {
    echo $metadata->meta;
  }, 1);

  // Jetpack -- disable SEO (so it will use ours)
  add_filter( 'jetpack_disable_seo_tools', '__return_false' );

  // Yoast -- http://hookr.io/plugins/yoast-seo/4.4/hooks/
  // OR -- https://developer.yoast.com/customization/yoast-seo/disabling-yoast-seo/
  add_filter( 'wpseo_title', $title_filter );

  add_filter( 'wpseo_metakey',       '__return_false' );
  add_filter( 'wpseo_prev_rel_link', '__return_false' );
  add_filter( 'wpseo_next_rel_link', '__return_false' );

  add_filter( 'wpseo_opengraph_site_name', '__return_false' );

  add_filter( 'wpseo_twitter_metatag_key', function() { return 'disabled'; } );
  add_filter( 'wpseo_twitter_card_type',   '__return_false' );

  // the rest of these we map thru from metadata returned from peggy
  $yoastHooks = [
    'wpseo_metadesc'              => 'og:description',
    // opengraph
    'wpseo_opengraph_url'         => 'og:url',
    'wpseo_opengraph_desc'        => 'og:description',
    'wpseo_opengraph_title'       => 'og:title',
    'wpseo_opengraph_type'        => 'og:type',
    'wpseo_opengraph_image'       => 'og:image',
    'wpseo_opengraph_image_size'  => ['og:image:width', 'og:image:height'],
    // twitter
    'wpseo_twitter_title'         => 'og:title',
    'wpseo_twitter_description'   => 'og:description',
    'wpseo_twitter_image'         => 'og:image',
  ];
  foreach ($yoastHooks as $yoastHook => $srcFrom) {
    $d = null;
    if (is_array($srcFrom)) {
      $d = array_map(function($p) use ($metaProps) {
        return isset($metaProps[$p]) ? $metaProps[$p] : "";
      }, $srcFrom);
    } else {
      $d = isset($metaProps[$srcFrom]) ? $metaProps[$srcFrom] : null;
    }

    if ($d) {
      add_filter( $yoastHook , function() use ($d) { return $d; });
    } else {
      add_filter( $yoastHook , '__return_false' );
    }
  }
}
