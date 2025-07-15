<?php

/*
 * Plugin Name: Twig Templating Block
 * Description: Write Twig templates in Site Editor with Block Bindings support to fill the gap. Powered by Timber.
 * Version: 0.1.0
 * Author: Jarkko Saltiola
 * Author URI: https://jasalt.dev
 */

// Check if Twig is available, if not load from vendor
// TODO check for Timber
if (!class_exists('\Twig\Environment')) {
    if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
        require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Twig Templating Block: Twig library not found. Please run "composer install" in the plugin directory.</p></div>';
        });
        return;
    }
}

use Timber\Timber;

// Register the block
function register_twig_templating_block() {
	// Register Twig.js script from local static directory
	wp_register_script(
		'twigjs-library',
		plugins_url('static/twig_1.17.1.min.js', dirname(plugin_dir_path(__FILE__))),
		[],
		'1.17.1'
	);

	wp_register_script(
		'twig-templating-block-editor',
		plugins_url('twig-templating-block.js', __FILE__),
		['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'twigjs-library'],
		filemtime(plugin_dir_path(__FILE__) . 'twig-templating-block.js')
	);


	register_block_type('jasalt/twig-templating-block', [
		'editor_script' => 'twig-templating-block-editor',
		'render_callback' => 'render_twig_templating_block',
		'attributes' => [
			'twigTemplate' => [
				'type' => 'string',
				'default' => '<div class="{{ editor_classes }}">
<div>{{ content }}</div>
</div>'
			],
			'metadata' => [
				'type' => 'object',
				'default' => (object) []
			],
			'contextBindings' => [
				'type' => 'array',
				'default' => []
			],
			'previewMode' => [
				'type' => 'string',
				'default' => 'default'
			],
			'previewPostId' => [
				'type' => 'string',
				'default' => ''
			]
		],
		'supports' => [
			'align' => true,
			'html' => false,
			'typography' => [
				'fontSize' => true
			]
		],
		'uses_context' => ['postId', 'postType']
	]);

	// Add block editor specific Twig functions globally to Timber
	add_filter( 'timber/twig', function( $twig ) {
		// Call block binding (alternatively) from Twig function, useful e.g.
		// inside Twig foreach loop to access item block binding values.
		$twig->addFunction( new \Twig\TwigFunction('call_block_binding', function ($source, $args = [], $global_context_overrides = []) {

			// Normally block binding callback accesses global state defined via `uses_context` in `register_block_type` and it works as
			// expected within templates and Query Loop block, or when calling template parts / patterns for rendering.

			// When looping in Twig, to have block binding calls work as expected, the global state change needs to be overridden during
			// block binding call.

			// $global_context_overrides is associative array and allows overriding global state.
			// While it's expected to support `uses_context` values as keys, currently only postID support is implemented.

			// Usage example:
			// {% set status = call_block_binding('my-plugin/my-binding', [], {'postID': post.id}) %}

			if (!class_exists('WP_Block_Bindings_Registry')) {
				return '';
			}

			$registry = WP_Block_Bindings_Registry::get_instance();
			$source_obj = $registry->get_registered($source);
			if (!$source_obj) {
				return '';
			}

			// Create a dummy block to use for context
			$dummy_block = (object)[
				'attributes' => [],
				'parsed_block' => [
					'attrs' => [
						'metadata' => [
							'bindings' => []
						]
					]
				]
			];

			$block_binding_key = 'twig_binding_' . uniqid();  // HACK

			global $post;
			$original_post = $post;

			// Override global post if postID is provided in context overrides
			if (!empty($global_context_overrides['postID'])) {
				$temp_post = get_post($global_context_overrides['postID']);
				if ($temp_post) {
					$post = $temp_post;
					setup_postdata($post);
				}
			}

			$value = $source_obj->get_value($args, $dummy_block, $block_binding_key);

			// Restore original post if it was overridden
			if (!empty($global_context_overrides['postID'])) {
				$post = $original_post;
				setup_postdata($post);
			}

			return $value;
		}));

		$twig->addFunction( new \Twig\TwigFunction('include_pattern', function ($slug, $postID = null) {
			if (!$slug || !is_string($slug)) return '';

			// TODO should take into account block theme pattern file rendering
			$patterns = get_posts([
				'post_type' => 'wp_block',
				'name' => $slug,
				'posts_per_page' => 1,
				'post_status' => 'publish'
			]);

			if (empty($patterns)) return '';

			global $post;
			$original_post = $post;

			// Temporarily switch global post if postID is provided
			if ($postID) {
				$temp_post = get_post($postID);
				if ($temp_post) {
					$post = $temp_post;
					setup_postdata($post);
				}
			}

			$content = do_blocks($patterns[0]->post_content);

			// Restore original post
			if ($postID) {
				$post = $original_post;
				setup_postdata($post);
			}

			return $content;
		}));

		$twig->addFunction( new \Twig\TwigFunction('include_template_part', function ($template_part_id, $postID = null) {
			// If the user passes the template part slug without theme, then we use current theme's slug
			$full_id = $template_part_id;
			// Check if the ID has the double-slash separator
			if (strpos($template_part_id, '//') === false) {
				$theme = wp_get_theme();
				$full_id = $theme->get_stylesheet() . '//' . $template_part_id;
			}

			$template_part = get_block_template($full_id, 'wp_template_part');

			if (!$template_part || empty($template_part->content)) {
				return '';
			}

			global $post;
			$original_post = $post;

			// Temporarily switch global post if postID is provided
			if ($postID) {
				$temp_post = get_post($postID);
				if ($temp_post) {
					$post = $temp_post;
					setup_postdata($post);
				}
			}

			$content = do_blocks($template_part->content);  // TODO does not use global block id ... in inner timber context the post is global post, not the template part post?
			                       // TwigContextBug250625_102331.png

			// Restore original post
			if ($postID) {
				$post = $original_post;
				setup_postdata($post);
			}

			return $content;
		}));
		return $twig;
	});
}
add_action('init', 'register_twig_templating_block');

// Server-side rendering with Timber / Twig
function render_twig_templating_block($attributes, $content, $block) {
	$template_content = $attributes['twigTemplate'] ?? '<div class="{{ editor_classes }}">
<div>{{ content }}</div>
</div>';

	// Get block wrapper attributes including classes
	$wrapper_attributes = get_block_wrapper_attributes();

	// Extract classes from wrapper attributes
	$editor_classes = '';
	if (preg_match('/class="([^"]*)"/', $wrapper_attributes, $matches)) {
		$editor_classes = $matches[1];
	}

	// Handle preview context setup using Timber's approach
	$original_post = null;
	$preview_context_active = false;
	$timber_post = null;

	if (defined('REST_REQUEST') && REST_REQUEST && !empty($attributes['previewPostId'])) {
		$preview_post_id = intval($attributes['previewPostId']);
		if ($preview_post_id > 0 && get_post($preview_post_id)) { // Set context with preview post
			global $post;
			$original_post = $post;  // Store original for later restoration

			$post = get_post($preview_post_id);
			setup_postdata($post);
			$preview_context_active = true;

			$timber_post = Timber::get_post($preview_post_id);
		}
	} else {
		$timber_post = Timber::get_post();
	}

	$context = Timber::context_global();
	$context['post'] = $timber_post;

	// NOTE: might not work as expected on taxonomy pages, the term needs to be set here conditionally e.g.
	// https://github.com/timber/timber/blob/e974e252851af262426319aca4991fb09afbe6b1/src/Timber.php#L1204

	$context['attributes'] = [];
	$context['editor_classes'] = $editor_classes;

	if (isset($block->parsed_block['attrs']['metadata']['bindings'])) {
		$bindings = $block->parsed_block['attrs']['metadata']['bindings'];

		if (isset($attributes['contextBindings']) && is_array($attributes['contextBindings'])) {
			foreach ($attributes['contextBindings'] as $index => $binding) {
				if (!empty($binding['variableName'])) {
					$binding_key = 'contextBinding' . $index;
					$binding_value = '';

					// Read binding arguments from JSON
					$binding_arguments = [];
					if (!empty($binding['arguments'])) {
						$decoded_args = json_decode($binding['arguments'], true);
						if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_args)) {
							$binding_arguments = $decoded_args;
						}
					}

					// Get binding source and use parsed arguments
					if (!empty($binding['source'])) {
						$binding_source = $binding['source'];

						$registry = WP_Block_Bindings_Registry::get_instance();
						$source = $registry->get_registered($binding_source);

						if ($source) {
							$value = $source->get_value($binding_arguments, $block, $binding_key);
							$binding_value = $value;
						}
					}

					// Use preview value if in editor and no binding value was found
					if (defined('REST_REQUEST') && REST_REQUEST && empty($binding_value) && !empty($binding['preview_value'])) {
						$binding_value = $binding['preview_value'];
					}

					$context[$binding['variableName']] = $binding_value;
				}
			}
		}
	}

	$rendered_content = '';
	try {
		$rendered_content = Timber::compile_string($template_content, $context);
	} catch (\Exception $e) {
		error_log('Error rendering Timber Templating Block: ' . $e->getMessage());
		if (current_user_can('manage_options')) {
			// Show detailed error to admins
			$rendered_content = '<div class="error" style="border:2px dashed red; padding: 20px;">'
				. '<strong>' . esc_html__('Twig Error:', 'jasalt') . '</strong> '
				. esc_html($e->getMessage())
				. '</div>';
		} else {
			// Generic message for non-admins
			$rendered_content = '<div class="error">'
				. esc_html__('Block rendering error. Please contact administrator.', 'jasalt')
				. '</div>';
		}
	}

	// Restore original global context after Timber compilation
	if ($preview_context_active && $original_post) {
		global $post;
		$post = $original_post;
		setup_postdata($original_post);
	}


	// If we're in the editor, check if we should show preview labels or rendered content (Disabled for now, only used with TwigJS)
	// if (defined('REST_REQUEST') && REST_REQUEST) { //
	//     // Check if any preview values are set
	//     $has_preview_values = false;
	//     if (isset($attributes['contextBindings']) && is_array($attributes['contextBindings'])) {
	//         foreach ($attributes['contextBindings'] as $binding) {
	//             if (!empty($binding['preview_value'])) {
	//                 $has_preview_values = true;
	//                 break;
	//             }
	//         }
	//     }

	//     // If no preview values are set, show the default label preview
	//     if (!$has_preview_values) {
	//         $source_labels = [];
	//         if (isset($attributes['contextBindings']) && is_array($attributes['contextBindings'])) {
	//             foreach ($attributes['contextBindings'] as $binding) {
	//                 if (!empty($binding['source'])) {
	//                     $registry = WP_Block_Bindings_Registry::get_instance();
	//                     $source = $registry->get_registered($binding['source']);
	//                     if ($source && isset($source->label)) {
	//                         $source_labels[] = $source->label;
	//                     } else {
	//                         $source_labels[] = $binding['source'];
	//                     }
	//                 }
	//             }
	//         }

	//         $source_list = empty($source_labels) ? 'No bindings' : implode(', ', $source_labels);
	//         return '<div>{{' . $source_list . '}}</div>';
	//     }
	// }

	return $rendered_content;
}
