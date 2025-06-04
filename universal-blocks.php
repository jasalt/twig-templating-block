<?php

/*
   Plugin Name: Universal Blocks
   Description: Custom blocks for different projects.
   Version: 1.0
   Author: Jarkko Saltiola
   Author URI: https://netura.fi
 */

require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php'); // TODO check if Twig is available, run if not AI!

require("blocks/dynamic-template/block.php");

// Universal blocks

/*
 * Check if request is in block editor (REST request) context.
 */
// function ub_is_editor_preview() {
//     return defined('REST_REQUEST') && REST_REQUEST;
// }

// Add custom Twig function for editor preview
// add_filter('timber/twig/functions', function($functions) {
//     $functions['prevent_editor_clicks'] = [
//         'callable' => function() {
//             if (defined('REST_REQUEST') && REST_REQUEST) {
//                 return 'onclick="event.preventDefault()"';
//             }
//             return '';
//         },
//     ];
//     return $functions;
// });


// TODO rename handle <projectname>-blocks
// TODO dirs to blocks/<projecname>/block.php / js
//require("blocks/related-post-object-listing/block.php");
//require("blocks/term-list-with-image/block.php");
//require("blocks/related-post-object-link/block.php");

// require("bindings.php");

//require("blocks/flexible-binding/block.php");


//require("blocks/term-image/block.php");
//require("blocks/term-meta/block.php");
