<?php

function load_styles_all_pages() {
    wp_enqueue_style( 'showcaseidx-admin', plugin_dir_url( dirname( __FILE__ ) ) . 'css/admin.css' );
}
add_action( 'admin_enqueue_scripts', 'load_styles_all_pages' );

// Register a global menu
function showcaseidx_globalmenu_init($wp_admin_bar) {
    // header node
    $wp_admin_bar->add_node([
        'id'    => 'showcaseidx_globalmenu_root',
        'title' => 'Showcase IDX',
        'href'  => admin_url( "admin.php?page=showcaseidx" ),
        'meta'  => array(
            'title' => 'Quick Links for your Showcase IDX Account',
            )
    ]);

    // drop-down menu items
    $wp_admin_bar->add_node([
        'id'     => 'showcaseidx_globalmenu_idx_plugin_setup',
        'parent' => 'showcaseidx_globalmenu_root',
        'title'  => 'Plugin Setup',
        'href'   => admin_url( "admin.php?page=showcaseidx" ),
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'showcaseidx_globalmenu_idx_dashboard',
        'parent' => 'showcaseidx_globalmenu_root',
        'title'  => 'IDX Dashboard',
        'href'   => 'https://admin.showcaseidx.com',
        'meta'  => array(
            'target' => '_blank',
            )
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'showcaseidx_globalmenu_support',
        'parent' => 'showcaseidx_globalmenu_root',
        'title'  => 'Support Center',
        'href'   => 'https://support.showcaseidx.com/hc/en-us',
        'meta'  => array(
            'target' => '_blank',
            )
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'showcaseidx_globalmenu_facebook_ug',
        'parent' => 'showcaseidx_globalmenu_root',
        'title'  => 'Facebook User Group',
        'href'   => 'https://www.facebook.com/groups/ShowcaseUserGroup/',
        'meta'  => array(
            'target' => '_blank',
            )
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'showcaseidx_globalmenu_video_tutorials',
        'parent' => 'showcaseidx_globalmenu_root',
        'title'  => 'Video Tutorials',
        'href'   => 'https://showcaseidx.com/video-tutorials/',
        'meta'  => array(
            'target' => '_blank',
            )
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'showcaseidx_globalmenu_blog',
        'parent' => 'showcaseidx_globalmenu_root',
        'title'  => 'Blog',
        'href'   => 'https://showcaseidx.com/blog/',
        'meta'  => array(
            'target' => '_blank',
            )
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'showcaseidx_globalmenu_changelog',
        'parent' => 'showcaseidx_globalmenu_root',
        'title'  => 'Changelog',
        'href'   => 'https://changelog.showcaseidx.com/',
        'meta'  => array(
            'target' => '_blank',
            )
    ]);
}
add_action('admin_bar_menu', 'showcaseidx_globalmenu_init', 999);

// Register the Dashboard widget
function showcaseidx_dashboard_widget_init() {
    wp_add_dashboard_widget( 'showcaseidx_dashboard_widget', 'Showcase IDX', 'showcaseidx_dashboard_widget_renderer');

    // Initial sorting to have widget placed on top
    // http://qnimate.com/change-position-of-wordpress-dashboard-widget/
    global $wp_meta_boxes;
    $normal_dashboard                             = $wp_meta_boxes['dashboard']['normal']['core']; 
    $example_widget_backup                        = array( 'showcaseidx_dashboard_widget' => $normal_dashboard['showcaseidx_dashboard_widget']);
    unset( $normal_dashboard['showcaseidx_dashboard_widget'] );
    $sorted_dashboard                             = array_merge( $example_widget_backup, $normal_dashboard );
    $wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
}
function showcaseidx_dashboard_widget_renderer() {
    wp_enqueue_style( 'showcaseidx-admin', plugin_dir_url( dirname( __FILE__ ) ) . 'css/admin.css' );
    echo '<img class="showcaseidx_dashboard_widget_sidx_logo" src="' . plugin_dir_url( dirname( __FILE__ ) ) . 'images/showcaseidx_greenwhite_text_logo_trans.png" /><br />';
    echo '<div style="text-align: center; margin-top: 2em;">';
    echo '<a class="button button-primary showcaseidx_dashboard_widget_button" href="https://admin.showcaseidx.com ">IDX Dashboard</a>';
    echo '<a class="button button-primary showcaseidx_dashboard_widget_button" href="https://support.showcaseidx.com/hc/en-us ">Support Center</a>';
    echo '<a class="button button-primary showcaseidx_dashboard_widget_button" href="https://showcaseidx.com/">Showcase IDX</a>';
    echo '</div>';

    echo "<hr />";

    $articleRenderer = function($title, $link, $dts, $category) {
        echo '<div class="showcaseidx_dashboard_widget_post">';
        echo "<div class=\"showcaseidx_dashboard_widget_post_title\"><a href=\"{$link}\" target=\"_blank\">{$title}</a></div>";
        $pubDate = strtotime($dts);
        echo "<div class=\"showcaseidx_dashboard_widget_post_dts\">" . date('m/d', $pubDate) . " - {$category}</div>";
        echo "</div>";
    };

    // changelog
    echo '<div class="showcaseidx_dashboard_widget_section">';
    echo '<h2>Latest Product Update</h2>';
    echo '<a href="https://changelog.showcaseidx.com" target="_blank">view all updates</a>';
    echo '</div>';
    $response = wp_remote_get('https://changelog.showcaseidx.com/rss', array('timeout' => 5));
    if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
        $showcaseidx_changelog = wp_remote_retrieve_body( $response );
        $changesXML = new SimpleXMLElement($showcaseidx_changelog);
        for ($i = 0; $i < 2; $i++) {
            $item = $changesXML->channel->item[$i];
            $articleRenderer($item->title, $item->link, $item->pubDate, $item->category);
        }
    } else {
        echo "Check back later...";
    }

    echo "<hr />";

    // latest blog posts
    echo '<div class="showcaseidx_dashboard_widget_section">';
    echo '<h2>From The Blog</h2>';
    echo '<a href="https://showcaseidx.com/blog/" target="_blank">view all posts</a>';
    echo '</div>';
    $response = wp_remote_get('https://showcaseidx.com/feed/', array('timeout' => 5));
    if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
        $showcaseidx_changelog = wp_remote_retrieve_body( $response );
        $changesXML = new SimpleXMLElement($showcaseidx_changelog);
        for ($i = 0; $i < 2; $i++) {
            $item = $changesXML->channel->item[$i];
            $articleRenderer($item->title, $item->link, $item->pubDate, $item->category);
        }
    } else {
        echo "Check back later...";
    }
}
add_action('wp_dashboard_setup', 'showcaseidx_dashboard_widget_init' );


add_action( 'admin_init', 'showcaseidx_admin_init' );
function showcaseidx_admin_init() {
  register_setting( 'showcaseidx-settings-group', 'showcaseidx_search_page', 'showcaseidx_search_page_sanitize' );
  register_setting( 'showcaseidx-reset-settings-group', 'showcaseidx_deprovision_install_id' );
}

function showcaseidx_search_page_sanitize($input) {
  return trim(trim($input), '/');
}

add_action('current_screen', 'showcaseidx_redirect_admin_page');
function showcaseidx_redirect_admin_page() {
  if ( get_current_screen()->base == 'toplevel_page_showcaseidx' && isset( $_GET["showcaseidx_change_version"] ) ) {
    update_option( 'showcaseidx_product_version', '2' );
    wp_redirect( admin_url( '/admin.php?page=showcaseidx' ) );
    exit();
  }
}

add_action( 'admin_menu', 'showcaseidx_admin_menu' );
function showcaseidx_admin_menu() {
  $admin_page = add_menu_page(
    'Showcase IDX Admin',
    'Showcase IDX',
    'manage_options',
    'showcaseidx',
    'showcaseidx_admin_page',
    plugin_dir_url( dirname( __FILE__ ) ) . 'images/menu.png',
    '100.100100'
  );

  add_action( 'load-' . $admin_page, 'showcaseidx_admin_page_enqueue' );
}

if (get_option('showcaseidx_website_uuid') == '') {
  add_action( 'admin_notices', 'showcaseidx_admin_render_banner' );
}
function showcaseidx_admin_render_banner() {
  $admin_url = admin_url( '/admin.php?page=showcaseidx' );
  echo <<<BANNER
    <div id="message" class="updated">
      <div class="sidx-plugin-activation-banner">
        <p>Your Showcase IDX plugin is ready to connect to your Showcase IDX account. <a href="{$admin_url}">Click here to get started</a>.</p>
      </div>
    </div>
BANNER;
}


function showcaseidx_admin_page_enqueue() {
  wp_enqueue_style( 'showcaseidx-admin', plugin_dir_url( dirname( __FILE__ ) ) . 'css/admin.css' );
}

function showcaseidx_admin_page() {
  showcaseidx_activation_check();
  ?>

  <div class="wrap sidx-admin">
    <h2 class="sidx-title">
      <img src="<?php echo plugin_dir_url( dirname( __FILE__ ) ); ?>images/logo.png" height="20">
      Showcase IDX
    </h2>

    <div class="card">
      <?php
      if ( !get_option( 'showcaseidx_website_uuid' ) ) {
        ?>
        <h1 class="center">Welcome to Showcase IDX!</h1>

        <div class="center-readable">
          <p>
            Thank you for installing the leading IDX plugin for Wordpress! 
          </p>

          <p>
            Websites powered by Showcase IDX get results. Send your clients automated search results, rank higher, and convert more.
          </p>

          <p>
            To activate this plugin, we need to connect it with a Showcase IDX account.
          </p>
        </div>

<div class="two-col row">
  <div class="two-col column">
          <p>
            <h2>Existing Member Login</h2>
            <a class="button button-primary" href="<?php echo SHOWCASEIDX_AGENT_HOST . '/provision/' . get_option( 'showcaseidx_install_id' ) . '?return_to=' . menu_page_url( 'showcaseidx', false ); ?>">Sign In</a>
          </p>
  </div>
  <div class="two-col column">
            <h2>Sign Up for a Free Trial</h2>
            <p><a href="https://showcaseidx.com/idx-free-trial/" target="_blank">Learn More</a></p>
            <div id="showcase-trial" data-reseller="1"/>
          </p>
        <script src="https://admin.showcaseidx.com/dist/trial.js"></script>
  </div>
</div>
        <?php
      } else {
        ?>
          <form method="post" action="options.php">
            <?php settings_fields( 'showcaseidx-settings-group' ); ?>
            <h3>Default Search Page URL</h3>
            <p class="description">This will be the primary search results page for Showcase IDX. Any links to listings will live under this URL. <a href="https://support.showcaseidx.com/hc/en-us/articles/360000256106-The-Default-Search-Page">Learn more about the default search page.</a></p>
            <p>
              <strong class="url"><?= get_home_url(); ?>/</strong>
              <input class="big-input" type="text" id="showcaseidx_search_page" name="showcaseidx_search_page" value="<?php echo get_option( 'showcaseidx_search_page' ); ?>"></td>
              <strong class="url">/</strong>
            </p>
            <p class="description"></p>

            <?php submit_button('Save Your Changes'); ?>
          </form>

          <hr>
          
          <form id="deprovision-form" method="post" action="options.php" onsumbit="return confirm('Are you sure you want to deactivate this plugin?')">
            <?php settings_fields( 'showcaseidx-reset-settings-group' ); ?>
            <h3>Deactivate This Plugin</h3>
            <p class="description">This will reset this plugin and the Showcase IDX website <strong><?= get_option( 'showcaseidx_website_name' ) ?></strong> will no longer be attached to this Wordpress Installation.</a></p>
            <p class="description">You may want to do this if...</p>
            <ul>
              <li>&bull; You activated the wrong Showcase IDX website.</li>
              <li>&bull; You want to install a different Showcase IDX website on this Wordpress Installation.</li>
              <li>&bull; You want to install this Showcase IDX website (<em><?= get_option( 'showcaseidx_website_name' ) ?></em>) on a different Wordpress Installation.</li>
            </ul>
            <p>
              <input type="hidden" id="showcaseidx_deprovision_install_id" name="showcaseidx_deprovision_install_id" value="<?= get_option( 'showcaseidx_install_id' ) ?>">
            </p>

            <p class="description" style="color: #D54E21;">This WILL NOT deactivate your account. Only this installation.</p>

            <?php submit_button('Deactivate'); ?>
          </form>

          <script>
            document.getElementById('deprovision-form').addEventListener('submit', function(event) {
              var confirmed = confirm("Are you sure you want to reset this plugin?");
              if (!confirmed) {
                event.preventDefault();
              }
            });
          </script>
        <?php
      }
    ?>
    </div>
  </div>

  <?php
}
