<?php
/**
 * Plugin Name: Dynamic Template Block
 */


// Register the block
function register_dynamic_template_block() {
    // Register Twig.js script from local static directory
    wp_register_script(
        'twigjs-library',
        plugins_url('static/twig_1.17.1.min.js', dirname(plugin_dir_path(__FILE__))),
        [],
        '1.17.1'
    );

    wp_register_script(
        'dynamic-template-block-editor',
        plugins_url('block.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'twigjs-library'],
        filemtime(plugin_dir_path(__FILE__) . 'block.js')
    );


    register_block_type('universal-blocks/dynamic-template', [
        'editor_script' => 'dynamic-template-block-editor',
        'render_callback' => 'render_dynamic_template_block',
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
}
add_action('init', 'register_dynamic_template_block');

use Timber\Timber;

// Server-side rendering with Timber / Twig
function render_dynamic_template_block($attributes, $content, $block) {
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
    $context_options = [];
    
    if (defined('REST_REQUEST') && REST_REQUEST && !empty($attributes['previewPostId'])) {
        $preview_post_id = intval($attributes['previewPostId']);
        if ($preview_post_id > 0 && get_post($preview_post_id)) {
            // Store original post for later restoration
            global $post;
            $original_post = $post;
            
            // Get preview post using Timber
            $preview_post = Timber::get_post($preview_post_id);
            $preview_post->setup();
            
            // Set context with preview post
            $context_options['post'] = $preview_post;
            $preview_context_active = true;
        }
    }

    $context = Timber::context($context_options);
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

                            if (is_array($value) || is_object($value)) {
                                $binding_value = $value;
                            } elseif (is_string($value)) {
                                $binding_value = $value;
                            }
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

    $rendered_content = Timber::compile_string($template_content, $context);
    
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
