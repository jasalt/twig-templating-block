(function(blocks, element, blockEditor, components) {
    var el = element.createElement;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var ToggleControl = components.ToggleControl;
    var ComboboxControl = components.ComboboxControl;
    var PanelBody = components.PanelBody;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var ServerSideRender = wp.serverSideRender;


    blocks.registerBlockType('universal-blocks/dynamic-template', {
        title: 'Dynamic Template',
        icon: 'embed-generic',
        category: 'text',
        apiVersion: 2,

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            // Check if we're editing a wp_template
            var isTemplate = wp.data && wp.data.select('core/editor') &&
                wp.data.select('core/editor').getCurrentPostType() === 'wp_template';

            // Get available template parts
            var getTemplateParts = function() {
                try {
                    if (wp.data && wp.data.select('core')) {
                        var templateParts = wp.data.select('core').getEntityRecords('postType', 'wp_template_part', { per_page: -1 });
                        return templateParts || [];
                    }
                } catch (e) {
                    console.warn('Could not access template parts:', e);
                }
                return [];
            };
            var templateParts = getTemplateParts();

            // Get available binding sources
            var getBindingSources = function() {
                try {
                    if (wp.blocks && wp.blocks.getBlockBindingsSources) {
                        var bindingSources = wp.blocks.getBlockBindingsSources();
                        return Object.keys(bindingSources).map(function(sourceKey) {
                            var source = bindingSources[sourceKey];
                            var label = source.label || sourceKey;
                            return {
                                value: sourceKey,
                                label: label + ' (' + sourceKey + ')'
                            };
                        });
                    }
                } catch (e) {
                    console.warn('Could not access block bindings sources:', e);
                }
                return [];
            };



            var bindingSources = getBindingSources();

            // Function to render default preview (binding labels)
            var renderDefaultPreview = function() {
                var contextBindings = attributes.contextBindings || [];
                var sourceLabels = [];

                contextBindings.forEach(function(binding) {
                    if (binding.source) {
                        var sourceObj = bindingSources.find(function(s) { return s.value === binding.source; });
                        var sourceLabel = sourceObj ? sourceObj.label.split(' (')[0] : binding.source;
                        sourceLabels.push(sourceLabel);
                    }
                });

                var bindingText = sourceLabels.length === 0 ? 'No bindings' : sourceLabels.join(', ');

                return el('div', {}, '{{ ' + bindingText + ' }}');
            };

            // Function to render TwigJS preview
            var renderTwigJSPreview = function() {
              var template = attributes.twigTemplate || '<div class="{{ editor_classes }}"><div>{{ content }}</div></div>';
                var contextBindings = attributes.contextBindings || [];

                try {
                    var twigTemplate = Twig.twig({
                        data: template
                    });

                    // Create context with preview values or binding labels
                    var context = {
                        editor_classes: blockProps.className || ''
                    };

                    contextBindings.forEach(function(binding) {
                        if (binding.variableName) {
                            if (binding.preview_value) {
                                context[binding.variableName] = binding.preview_value;
                            } else if (binding.source) {
                                var sourceObj = bindingSources.find(function(s) { return s.value === binding.source; });
                                var sourceLabel = sourceObj ? sourceObj.label.split(' (')[0] : binding.source;
                                var argsSuffix = '';
                                if (binding.arguments) {
                                    try {
                                        var parsedArgs = JSON.parse(binding.arguments);
                                        argsSuffix = ': ' + JSON.stringify(parsedArgs);
                                    } catch (e) {
                                        argsSuffix = ': [Invalid JSON]';
                                    }
                                }
                              context[binding.variableName] = '{{ ' + sourceLabel + argsSuffix + ' }}';
                            }
                        }
                    });

                    var renderedHtml = twigTemplate.render(context);

                    return el('div', {
                        dangerouslySetInnerHTML: { __html: renderedHtml }
                    });
                } catch (error) {
                    console.error('Error rendering Twig template:', error);
                    return el('div', { className: 'template-error' }, 'Error rendering template: ' + error.message);
                }
            };

            // Function to get preview content based on mode
            var getPreviewContent = function() {
                var previewMode = attributes.previewMode || 'default';

                switch (previewMode) {
                    case 'server-side':
                        return el(ServerSideRender, {
                            block: 'universal-blocks/dynamic-template',
                            attributes: attributes
                        });
                    case 'twigjs':
                        return renderTwigJSPreview();
                    default:
                        return renderDefaultPreview();
                }
            };

            // Add template parts section if there are any
            var templatePartsSection = null;
            if (templateParts && templateParts.length > 0) {
                templatePartsSection = el(PanelBody, { title: 'Available Template Parts', key: 'available-template-parts' },
                    el('p', { style: { marginBottom: '16px' } },
                        'To include in Twig template, use: {{ include_template_part(\'slug-here\') }}'
                    ),
                    el('ul', { style: { paddingLeft: '1.5rem', listStyleType: 'disc' } },
                        templateParts.map(function(part) {
                            return el('li', { key: part.id }, part.slug + (part.title ? ' (' + part.title.rendered + ')' : ''));
                        })
                    )
                );
            } else {
                templatePartsSection = el(PanelBody, { title: 'Available Template Parts', key: 'available-template-parts' },
                    el('p', {}, 'No template parts found.'),
                    el('p', { style: { fontStyle: 'italic' } }, 'Once available, include them in Twig template with: {{ include_template_part(\'slug-here\') }}')
                );
            }

            return el('div', blockProps, [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'Block Bindings' },
                        // Context bindings
                        el('div', {}, [
                            el('h4', {}, 'Context Bindings'),

                            // Render existing context bindings
                            (attributes.contextBindings || []).map(function(binding, index) {
                                return el('div', {
                                    key: index,
                                    style: {
                                        border: '1px solid #ddd',
                                        padding: '10px',
                                        marginBottom: '10px',
                                        borderRadius: '4px'
                                    }
                                }, [
                                    el(TextControl, {
                                      label: 'Variable Name',
                                        value: binding.variableName || 'content',
                                        onChange: function(value) {
                                            var newBindings = [...(attributes.contextBindings || [])];
                                            newBindings[index] = { ...newBindings[index], variableName: value };
                                            setAttributes({ contextBindings: newBindings });
                                        }
                                    }),

                                    el(ComboboxControl, {
                                      label: 'Binding Source',
                                        value: binding.source || '',
                                        options: bindingSources,
                                        maxSuggestions: 10,
                                        onChange: function(value) {
                                            var newBindings = [...(attributes.contextBindings || [])];
                                            var bindingKey = 'contextBinding' + index;
                                            newBindings[index] = { ...newBindings[index], source: value, bindingKey: bindingKey };
                                            setAttributes({ contextBindings: newBindings });

                                            // Update metadata bindings
                                            var newMetadata = JSON.parse(JSON.stringify(attributes.metadata || {}));
                                            newMetadata.bindings = newMetadata.bindings || {};
                                            newMetadata.bindings[bindingKey] = { source: value };
                                            setAttributes({ metadata: newMetadata });
                                        }
                                    }),

                                    el(TextareaControl, {
                                      label: 'Binding Arguments (Optional)',
                                        help: 'JSON object with arguments for the binding source, e.g. {"key": "my_meta"} that gets passed to binding value callback.',
                                        value: binding.arguments || '',
                                        onChange: function(value) {
                                            var newBindings = [...(attributes.contextBindings || [])];
                                            var bindingKey = 'contextBinding' + index;
                                            newBindings[index] = { ...newBindings[index], arguments: value, bindingKey: bindingKey };
                                            setAttributes({ contextBindings: newBindings });

                                            // Update metadata bindings
                                            var newMetadata = JSON.parse(JSON.stringify(attributes.metadata || {}));
                                            newMetadata.bindings = newMetadata.bindings || {};
                                            newMetadata.bindings[bindingKey] = newMetadata.bindings[bindingKey] || {};
                                            if (value) {
                                                try {
                                                    var parsedArgs = JSON.parse(value);
                                                    newMetadata.bindings[bindingKey].args = parsedArgs;
                                                } catch (e) {
                                                    // If JSON is invalid, don't update args
                                                    console.warn('Invalid JSON in binding arguments:', e);
                                                }
                                            } else {
                                                delete newMetadata.bindings[bindingKey].args;
                                            }
                                            setAttributes({ metadata: newMetadata });
                                        },
                                        rows: 3
                                    }),

                                    el(TextControl, {
                                      label: 'Preview Value (Optional)',
                                        help: 'Default value to show in editor preview rendered with TwigJS. It is experimental and needs to be set it in Preview Settings below to be effective.',
                                        value: binding.preview_value || '',
                                        onChange: function(value) {
                                            var newBindings = [...(attributes.contextBindings || [])];
                                            newBindings[index] = { ...newBindings[index], preview_value: value };
                                            setAttributes({ contextBindings: newBindings });
                                        }
                                    }),

                                    el('button', {
                                        className: 'button button-secondary',
                                        style: { marginTop: '10px' },
                                        onClick: function() {
                                            var newBindings = [...(attributes.contextBindings || [])];
                                            var bindingKey = 'contextBinding' + index;
                                            newBindings.splice(index, 1);
                                            setAttributes({ contextBindings: newBindings });

                                            // Remove from metadata bindings
                                            var newMetadata = JSON.parse(JSON.stringify(attributes.metadata || {}));
                                            if (newMetadata.bindings && newMetadata.bindings[bindingKey]) {
                                                delete newMetadata.bindings[bindingKey];
                                            }
                                            setAttributes({ metadata: newMetadata });
                                        }
                                    }, 'Remove')
                                ]);
                            }),

                            // Add new binding button
                            el('button', {
                                className: 'button button-primary',
                                onClick: function() {
                                    var newBindings = [...(attributes.contextBindings || [])];
                                    newBindings.push({
                                        variableName: 'content',
                                        source: '',
                                        arguments: '',
                                        preview_value: '',
                                        bindingKey: 'contextBinding' + newBindings.length
                                    });
                                    setAttributes({ contextBindings: newBindings });
                                }
                            }, 'Add new context binding')
                        ])
                    ),
                    el(PanelBody, { title: 'Template Settings' },
                        el(TextareaControl, {
                            label: 'Twig Template',
                            help: 'Use {{ variableName }} to access context binding variables. To include template parts, use {{ include_template_part(\'part-slug\') }}. Available parts are listed above. For editor styling (font size etc.), include class="{{ editor_classes }}" in your template elements. Template parts must be registered in theme and visible in editor.',
                            value: attributes.twigTemplate,
                            onChange: function(value) {
                                setAttributes({ twigTemplate: value });
                            },
                            rows: 10
                        })
                    ),
                    el(PanelBody, { title: 'Preview Settings' },
                        el(ComboboxControl, {
                            label: 'Preview Mode',
                            help: 'Choose how to render the preview in the editor.',
                            value: attributes.previewMode || 'default',
                            options: [
                                { value: 'default', label: 'Default (Binding Labels)' },
                                { value: 'server-side', label: 'Server-Side Rendered' },
                              { value: 'twigjs', label: 'TwigJS Rendered (Beta)' }
                            ],
                            onChange: function(value) {
                                setAttributes({ previewMode: value });
                            }
                        }),

                        // Preview Context (only for wp_template)
                        isTemplate ? el(TextControl, {
                            label: 'Template Preview Post ID',
                            help: 'Enter a Post ID to preview template with actual data with server-side rendered preview. This is required as template preview does not have a specific Post ID. Set Preview Mode above to Server-Side Rendered to see the effect.',
                            value: attributes.previewPostId || '',
                            onChange: function(value) {
                                setAttributes({ previewPostId: value });
                            }
                        }) : null
                    ),
                    templatePartsSection
                ),

                // Render preview based on selected mode
                getPreviewContent()
            ]);
        },

        save: function() {
            return null; // Use server-side rendering
        }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
));
