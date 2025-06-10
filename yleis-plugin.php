<?php

/*
   Plugin Name: Yleis Plugin
   Description: Custom code for different projects.
   Version: 1.0
   Author: Jarkko Saltiola / Netura
   Author URI: https://netura.fi
 */

// Check if Twig is available, if not load from vendor
if (!class_exists('\Twig\Environment')) {
    if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
        require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Yleis Plugin: Twig library not found. Please run "composer install" in the plugin directory.</p></div>';
        });
        return;
    }
}

require("blocks/dynamic-template/block.php");
// require("blocks/twig-binding/block.php");  // uses only Twig, no Timber dependency
