<?php

/************************************************
 * Code related to the Admin area of the plugin.
 */

add_action( 'admin_menu', 'showcaseidx_create_menu_page' );
add_action( 'admin_init', 'register_showcaseidx_settings' );

function showcaseidx_create_menu_page() {
  add_menu_page(
    'Showcase IDX Admin',
    'Showcase IDX',
    'manage_options',
    'showcaseidx',
    'display_showcase_settings',
    plugin_dir_url( dirname( __FILE__ ) ) . 'images/menu.png',
    '100.1337'
  );
}

function register_showcaseidx_settings() {
    register_setting( 'showcase-settings-group', 'showcaseidx_api_v2_host');
    register_setting( 'showcase-settings-group', 'showcaseidx_cdn_host');
    register_setting( 'showcase-settings-group', 'showcaseidx_api_key');
    register_setting( 'showcase-settings-group', 'showcaseidx_template');
    register_setting( 'showcase-settings-group', 'showcaseidx_setup_step');
    register_setting( 'showcase-settings-group', 'showcaseidx_url_namespace', 'showcaseidx_sanitize_url_namespace');

    // this is a fake, unusued setting, which makes it easy to only flush out our rewrite rules (expensive) when our plugin's admin panel is saved
    register_setting( 'showcase-settings-group', 'showcaseidx_fake', 'showcaseidx_once_per_admin_save_hack_via_sanitizer');
}

function showcaseidx_sanitize_url_namespace($input)
{
    $input = trim($input);
    $input = trim($input, '/');
    return $input;
}

function showcaseidx_once_per_admin_save_hack_via_sanitizer($input)
{
    showcaseidx_refresh_setup_expensive();
    return $input;
}

function showcaseidx_refresh_setup_expensive()
{
    showcaseidx_install_rewrite_rules();
    showcaseidx_bust_cache();
}

function showcaseidx_option($value, $label, $selected) {
    $value = htmlspecialchars($value);
    $label = htmlspecialchars($label);
    $selected = ($selected == $value) ? ' selected ' : NULL;
    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
}

add_action('current_screen', 'showcaseidx_redirect_admin_page');
function showcaseidx_redirect_admin_page()
{
  if (get_current_screen()->base == 'toplevel_page_showcaseidx' && isset($_GET["showcaseidx_change_version"])) {
    update_option('showcaseidx_product_version', '3');
    wp_redirect(admin_url('/admin.php?page=showcaseidx'));
    exit();
  }
}

function display_showcase_settings() {
    if (isset($_GET["showcaseidx_remove_key"]) && !isset($_GET["settings-updated"])) {
        update_option('showcaseidx_api_key', '');
        update_option('showcaseidx_setup_step', '');
    }

    if (isset($_GET["showcaseidx_change_namespace"]) && !isset($_GET["settings-updated"])) {
        update_option('showcaseidx_setup_step', 'api_key');
    }

    $adminPanelUrl = home_url() . '/' . showcaseidx_get_prefix() . '/#/admin';
    $propertySearchBaseUrl = home_url() . '/' . showcaseidx_get_prefix();
    $current_key = get_option('showcaseidx_api_key');
    $current_namespace = get_option('showcaseidx_url_namespace');
    $api_host = get_option('showcaseidx_api_v2_host');
    $activated = false;

    if ($current_key) {
      $response_code = wp_remote_retrieve_response_code(wp_remote_get("$api_host/wp_status?key=$current_key&namespace=$current_namespace&full_root=$propertySearchBaseUrl"));
      if($response_code != 401) {
        $activated = true;
      }
    }

?>
<style type="text/css">
  .showcase-admin {
    padding: 15px;
    font-size: 16px;
    line-height: 1.5em;
    text-align: center;
  }

  .showcase-input {
    width: 300px;
    height: 50px;
    font-size: 18px;
  }

  .showcase-url {
    font-size: 18px;
  }

  .showcase-admin .button-primary {
    display: block;
    height: 50px;
    min-width: 150px;
    margin: 20px auto;
    font-size: 18px;
    line-height: 46px;
  }

  .showcase-admin .notice {
    width: 400px;
    margin: 0 auto;
  }

  .showcase-welcome {
    margin: 50px auto;
    text-align: center;
  }

  .showcase-morelinks {
    margin-top: 50px;
  }

  .showcase-screenshot {
    border: solid 1px #bbb;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
  }

  .showcase-success {
    padding: 10px;
    background-color: #6ac229;
    color: white;
  }
</style>

<div class="showcase-admin">

  <form method="post" action="options.php">
    <?php settings_fields( 'showcase-settings-group' ); ?>

    <?php if(isset($_GET["advanced"])) { ?>
      <label>API Host:
        <input class="showcase-input" type="text" name="showcaseidx_api_v2_host" value="<?php echo get_option('showcaseidx_api_v2_host'); ?>" />
      </label>

      <label>CDN Host:
        <input class="showcase-input" type="text" name="showcaseidx_cdn_host" value="<?php echo get_option('showcaseidx_cdn_host'); ?>" />
      </label>
    <?php } else { ?>
      <input class="showcase-input" type="hidden" name="showcaseidx_api_v2_host" value="<?php echo get_option('showcaseidx_api_v2_host'); ?>" />
      <input class="showcase-input" type="hidden" name="showcaseidx_cdn_host" value="<?php echo get_option('showcaseidx_cdn_host'); ?>" />
    <?php } ?>

    <?php if(!$activated) { ?>

      <h1 class="showcase-welcome">Welcome to Showcase IDX</h1>

      <?php if($current_key && !$activated) { ?>
        <div class="notice notice-error">
          <p>There was an error activating your API key.</p>
          <p>Check that it's entered correctly and try again.</p>
        </div>
      <?php } ?>

      <h2>Enter your API Key:</h2>

      <input class="showcase-input" type="text" name="showcaseidx_api_key" />
      <input type="hidden" name="showcaseidx_setup_step" value="api_key" />
      <input type="hidden" name="showcaseidx_url_namespace" value="<?php echo SHOWCASEIDX_SEARCH_DEFAULT_URL_NAMESPACE ?>" />

      <input type="submit" name="submit" class="button button-primary" value="Get Started">

      <div class="showcase-morelinks">
        <h3>Don't have an API Key? <a href="https://showcaseidx.com/welcome-to-showcase-idx/" target="_blank">Learn more about Showcase IDX</a></h3>
        <h3><a href="https://showcaseidx.com/plans-pricing/" target="_blank">Start a 10 Day Free Trial</a></h3>
      </div>

    <?php } else if (get_option("showcaseidx_setup_step") == "api_key") { ?>

      <h1>Showcase IDX Configuration</h1>
      <hr>

      <h3>Create a default search page</h3>

      <a href="http://setup.showcaseidx.com/lesson/set-up-your-first-search/" class="button button-primary" target="_blank">Learn how to create the default search page</a>

      <hr>

      <h3>After you have published your default search page:</h3>

      <p>You will see a Permalink at the top of the page editor.</p>
      <img class="showcase-screenshot" src="<?php echo plugin_dir_url( dirname( __FILE__ ) ) . 'images/editor-screenshot.png' ?>">
      <p>Copy the highlighted part of the URL without the slashes into the box below.</p>

      <strong class="showcase-url"><?php echo get_site_url(); ?>/</strong>
      <input class="showcase-input" type="text" name="showcaseidx_url_namespace" value="<?php echo get_option('showcaseidx_url_namespace'); ?>" />
      <strong class="showcase-url">/</strong>

      <input type="hidden" name="showcaseidx_api_key" value="<?php echo get_option('showcaseidx_api_key'); ?>" />
      <input type="hidden" name="showcaseidx_setup_step" value="namespace" />
      <input type="submit" name="submit" class="button button-primary" value="Finish">

    <?php } else { ?>

      <h1>Showcase IDX Configuration</h1>
      <hr>

      <h2 class="showcase-success">Your Showcase IDX plugin is installed</h2>

      <p>API Key: <strong><?php echo get_option('showcaseidx_api_key'); ?></strong> <a href="admin.php?page=showcaseidx&showcaseidx_remove_key=true">(Change)</a></p>
      <p>Default Search Page: <strong><?php echo get_site_url(); ?>/<?php echo showcaseidx_get_prefix(); ?></strong> <a href="admin.php?page=showcaseidx&showcaseidx_change_namespace=true">(Change)</a></p>
      <p>XML Sitemap: <strong><?php echo get_site_url(); ?>/<?php echo showcaseidx_get_prefix(); ?>/xmlsitemap/</strong></p>
      <p>Visitor Sitemap: <strong><?php echo get_site_url(); ?>/<?php echo showcaseidx_get_prefix(); ?>/sitemap/</strong></p>

      <hr>

      <p><a href="http://setup.showcaseidx.com/customize/" target="_blank">Learn how to customize Showcase IDX</a></p>
      <p><a href="http://support.showcaseidx.com/" target="_blank">Showcase IDX Support</a></p>

    <?php } ?>

  </form>

</div>

<!--Start of Zopim Live Chat Script-->
<script type="text/javascript">
window.$zopim||(function(d,s){var z=$zopim=function(c){z._.push(c)},$=z.s=
d.createElement(s),e=d.getElementsByTagName(s)[0];z.set=function(o){z.set.
_.push(o)};z._=[];z.set._=[];$.async=!0;$.setAttribute("charset","utf-8");
$.src="//v2.zopim.com/?3RpcH6qMHfKEJcqc8eZ6VOgw1zEhGVeL";z.t=+new Date;$.
type="text/javascript";e.parentNode.insertBefore($,e)})(document,"script");
</script>
<!--End of Zopim Live Chat Script-->

<?php

}
