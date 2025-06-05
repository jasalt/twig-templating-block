<?php

/*
   Plugin Name: Yleis Plugin
   Description: Custom code for different projects.
   Version: 1.0
   Author: Jarkko Saltiola / Netura
   Author URI: https://netura.fi
 */

require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php'); // TODO check if Twig is available, run if not AI!

require("blocks/dynamic-template/block.php");
