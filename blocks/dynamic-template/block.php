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
                'default' => '<div class="wp-block-dynamic-template">
    <p>{{ content }}</p>
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
    $template_content = $attributes['twigTemplate'] ?? '<div class="wp-block-dynamic-template"><p>{{ content }}</p></div>';

    // Get block wrapper attributes including classes
    $wrapper_attributes = get_block_wrapper_attributes();

    // Extract classes from wrapper attributes
    $editor_classes = '';
    if (preg_match('/class="([^"]*)"/', $wrapper_attributes, $matches)) {
        $editor_classes = $matches[1];
    }

    // Process context bindings
    $context = [
        'attributes' => [],
        'editor_classes' => $editor_classes
    ];

    if (isset($block->parsed_block['attrs']['metadata']['bindings'])) {
        $bindings = $block->parsed_block['attrs']['metadata']['bindings'];

        if (isset($attributes['contextBindings']) && is_array($attributes['contextBindings'])) {
            foreach ($attributes['contextBindings'] as $index => $binding) {
                if (!empty($binding['variableName'])) {
                    $binding_key = 'contextBinding' . $index;
                    $binding_value = '';

                    // Check if this binding exists in metadata
                    if (isset($bindings[$binding_key])) {
                        $binding_source = $bindings[$binding_key]['source'];
                        $binding_args = isset($bindings[$binding_key]['args']) ? $bindings[$binding_key]['args'] : [];

                        $registry = WP_Block_Bindings_Registry::get_instance();
                        $source = $registry->get_registered($binding_source);

                        if ($source) {
                            $value = $source->get_value($binding_args, $block, $binding_key);
                            if (is_array($value)) {
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

    // If we're in the editor, check if we should show preview labels or rendered content
    if (defined('REST_REQUEST') && REST_REQUEST) {
        // Check if any preview values are set
        $has_preview_values = false;
        if (isset($attributes['contextBindings']) && is_array($attributes['contextBindings'])) {
            foreach ($attributes['contextBindings'] as $binding) {
                if (!empty($binding['preview_value'])) {
                    $has_preview_values = true;
                    break;
                }
            }
        }

        // If no preview values are set, show the default label preview
        if (!$has_preview_values) {
            $source_labels = [];
            if (isset($attributes['contextBindings']) && is_array($attributes['contextBindings'])) {
                foreach ($attributes['contextBindings'] as $binding) {
                    if (!empty($binding['source'])) {
                        $registry = WP_Block_Bindings_Registry::get_instance();
                        $source = $registry->get_registered($binding['source']);
                        if ($source && isset($source->label)) {
                            $source_labels[] = $source->label;
                        } else {
                            $source_labels[] = $binding['source'];
                        }
                    }
                }
            }

            $source_list = empty($source_labels) ? 'No bindings' : implode(', ', $source_labels);
            return '<div>{{' . $source_list . '}}</div>';
        }
    }

    return $rendered_content;
}
