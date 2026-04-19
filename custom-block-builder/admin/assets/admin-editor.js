/**
 * Custom Block Builder — Admin Editor JavaScript
 *
 * Initializes CodeMirror editors, generates test data forms from schema,
 * and handles AJAX-powered live preview in an iframe.
 */

(function ($) {
    'use strict';

    // ==========================================
    // State
    // ==========================================

    let cmSchema = null;
    let cmView = null;
    let cmCss = null;
    let cmJs = null;
    let previewTimer = null;
    let isAutoPreview = true;

    // ==========================================
    // Initialize CodeMirror Editors
    // ==========================================

    function initCodeEditors() {
        // Schema Editor (JSON)
        const schemaEl = document.getElementById('cbb-schema-editor');
        if (schemaEl) {
            cmSchema = wp.codeEditor.initialize(schemaEl, {
                codemirror: {
                    mode: 'application/json',
                    lineNumbers: true,
                    lineWrapping: true,
                    matchBrackets: true,
                    autoCloseBrackets: true,
                    foldGutter: true,
                    gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
                    theme: 'default',
                    indentUnit: 2,
                    tabSize: 2,
                    indentWithTabs: false,
                },
            }).codemirror;

            cmSchema.on('change', function () {
                updateAvailableVars();
                updateTestDataForm();
                schedulePreview();
            });
        }

        // View Template Editor (PHP/HTML)
        const viewEl = document.getElementById('cbb-view-editor');
        if (viewEl) {
            cmView = wp.codeEditor.initialize(viewEl, {
                codemirror: {
                    mode: 'application/x-httpd-php',
                    lineNumbers: true,
                    lineWrapping: true,
                    matchBrackets: true,
                    matchTags: true,
                    autoCloseTags: true,
                    autoCloseBrackets: true,
                    theme: 'default',
                    indentUnit: 2,
                    tabSize: 2,
                },
            }).codemirror;

            cmView.on('change', function () {
                schedulePreview();
            });
        }

        // CSS Editor
        const cssEl = document.getElementById('cbb-css-editor');
        if (cssEl) {
            cmCss = wp.codeEditor.initialize(cssEl, {
                codemirror: {
                    mode: 'text/css',
                    lineNumbers: true,
                    lineWrapping: true,
                    matchBrackets: true,
                    autoCloseBrackets: true,
                    theme: 'default',
                    indentUnit: 2,
                    tabSize: 2,
                },
            }).codemirror;

            cmCss.on('change', function () {
                schedulePreview();
            });
        }

        // JS Editor
        const jsEl = document.getElementById('cbb-js-editor');
        if (jsEl) {
            cmJs = wp.codeEditor.initialize(jsEl, {
                codemirror: {
                    mode: 'text/javascript',
                    lineNumbers: true,
                    lineWrapping: true,
                    matchBrackets: true,
                    autoCloseBrackets: true,
                    theme: 'default',
                    indentUnit: 2,
                    tabSize: 2,
                },
            }).codemirror;

            cmJs.on('change', function () {
                schedulePreview();
            });
        }
    }

    // ==========================================
    // Parse Schema JSON
    // ==========================================

    function getSchema() {
        if (!cmSchema) return { fields: [] };

        try {
            const raw = cmSchema.getValue();
            const parsed = JSON.parse(raw);
            return parsed && parsed.fields ? parsed : { fields: [] };
        } catch (e) {
            return { fields: [] };
        }
    }

    // ==========================================
    // Update Available Variables Display
    // ==========================================

    function updateAvailableVars() {
        const container = document.getElementById('cbb-available-vars');
        if (!container) return;

        const schema = getSchema();

        if (!schema.fields || schema.fields.length === 0) {
            container.innerHTML = '';
            return;
        }

        let html = '<span style="font-size:12px;color:#64748b;margin-right:8px;">Available vars:</span>';
        const vars = [];
        collectFieldPaths(schema.fields, '', vars);
        vars.forEach(function (varName) {
            html += '<span class="cbb-var-badge">$' + escapeHtml(varName) + '</span>';
        });

        container.innerHTML = html;
    }

    function collectFieldPaths(fields, prefix, output) {
        if (!Array.isArray(fields)) return;

        fields.forEach(function (field) {
            if (!field || typeof field !== 'object') return;

            if (field.type === 'section') {
                collectFieldPaths(field.fields || [], prefix, output);
                return;
            }

            if (!field.name) return;

            const path = prefix ? prefix + '.' + field.name : field.name;

            if (field.type === 'group') {
                collectFieldPaths(field.fields || [], path, output);
                return;
            }

            output.push(path);
        });
    }

    // ==========================================
    // Generate Test Data Form
    // ==========================================

    function updateTestDataForm() {
        const container = document.getElementById('cbb-test-data-form');
        if (!container) return;

        const schema = getSchema();

        if (!schema.fields || schema.fields.length === 0) {
            container.innerHTML = '<p class="cbb-placeholder-text">Define a schema above to see test fields here.</p>';
            return;
        }

        container.innerHTML = renderFields(schema.fields);

        // Bind change events for auto-preview.
        $(container).find('input, textarea, select').off('input.cbb change.cbb').on('input.cbb change.cbb', function (e) {
            // Prevent event bubbling if needed, but here we want to catch all.
            
            // Update toggle label.
            if ($(this).hasClass('cbb-toggle-switch')) {
                $(this).siblings('span').text(this.checked ? 'Yes' : 'No');
            }
            schedulePreview();
        });

        bindMediaPickers(container);
        bindRepeaterEvents(container);
    }

    function renderFields(fields, pathPrefix = '', currentValues = {}) {
        let html = '';

        fields.forEach(function (field) {
            if (field.type === 'section') {
                html += '<div class="cbb-test-section">';
                html += '<h4 class="cbb-test-section-title">' + escapeHtml(field.label || 'Section') + '</h4>';
                if (field.fields && Array.isArray(field.fields)) {
                    html += renderFields(field.fields, pathPrefix, currentValues);
                }
                html += '</div>';
                return;
            }

            if (!field.name) return;

            const fieldPath = pathPrefix ? pathPrefix + '.' + field.name : field.name;
            const escapedPath = escapeHtml(fieldPath);
            const label = escapeHtml(field.label || field.name);
            const type = field.type || 'text';
            const fieldId = 'cbb-test-' + escapeHtml(fieldPath.replace(/\./g, '-'));

            if (type === 'group') {
                html += '<div class="cbb-test-group">';
                html += '<h5 class="cbb-test-group-title">' + label + '</h5>';
                if (field.fields && Array.isArray(field.fields)) {
                    html += renderFields(field.fields, fieldPath, currentValues[field.name] || {});
                }
                html += '</div>';
                return;
            }

            const rawVal = currentValues[field.name] !== undefined ? currentValues[field.name] : field.default;
            const valStr = rawVal !== undefined ? String(rawVal) : '';
            const escapedVal = escapeHtml(valStr);

            html += '<div class="cbb-field-group" data-field-type="' + escapeHtml(type) + '">';
            html += '<label class="cbb-field-label" for="' + fieldId + '">' + label;
            html += '<span class="cbb-field-type-badge">' + escapeHtml(type) + '</span>';
            html += '</label>';

            switch (type) {
                case 'text':
                    html += '<input type="text" id="' + fieldId + '" data-field="' + escapedPath + '" value="' + escapedVal + '" />';
                    break;

                case 'textarea':
                    html += '<textarea id="' + fieldId + '" data-field="' + escapedPath + '">' + escapedVal + '</textarea>';
                    break;

                case 'number':
                    html += '<input type="number" id="' + fieldId + '" data-field="' + escapedPath + '" value="' + escapedVal + '" />';
                    break;

                case 'color':
                    html += '<input type="color" id="' + fieldId + '" data-field="' + escapedPath + '" value="' + normalizeColorDefault(rawVal) + '" />';
                    break;

                case 'image':
                    {
                        const imageVal = normalizeImageDefault(rawVal);
                        html += '<div class="cbb-image-control">';
                        html += '<input type="hidden" id="' + fieldId + '" data-field="' + escapedPath + '" data-image-id="' + escapeHtml(String(imageVal.id)) + '" data-image-alt="' + escapeHtml(imageVal.alt) + '" value="' + escapeHtml(imageVal.url) + '" />';
                        html += '<div class="cbb-image-preview-wrap">';
                        if (imageVal.url) {
                            html += '<img src="' + escapeHtml(imageVal.url) + '" alt="' + escapeHtml(imageVal.alt || label) + '" class="cbb-image-preview" />';
                        } else {
                            html += '<div class="cbb-image-placeholder">No image selected</div>';
                        }
                        html += '</div>';
                        html += '<div class="cbb-image-actions">';
                        html += '<button type="button" class="button button-secondary cbb-image-select" data-target="' + escapedPath + '">Choose Image</button>';
                        html += '<button type="button" class="button-link-delete cbb-image-remove" data-target="' + escapedPath + '"' + (imageVal.url ? '' : ' style="display:none;"') + '>Remove</button>';
                        html += '</div>';
                        html += '</div>';
                    }
                    break;

                case 'select':
                    html += '<select id="' + fieldId + '" data-field="' + escapedPath + '">';
                    if (field.options && Array.isArray(field.options)) {
                        field.options.forEach(function (opt) {
                            const selected = String(opt) === String(rawVal) ? ' selected' : '';
                            html += '<option value="' + escapeHtml(opt) + '"' + selected + '>' + escapeHtml(opt) + '</option>';
                        });
                    }
                    html += '</select>';
                    break;

                case 'boolean':
                    const isChecked = !!rawVal;
                    const checkedAttr = isChecked ? ' checked' : '';
                    html += '<div class="cbb-toggle-wrapper">';
                    html += '<input type="checkbox" id="' + fieldId + '" data-field="' + escapedPath + '" class="cbb-toggle-switch"' + checkedAttr + ' />';
                    html += '<span>' + (isChecked ? 'Yes' : 'No') + '</span>';
                    html += '</div>';
                    break;

                case 'repeater':
                    const items = Array.isArray(rawVal) ? rawVal : [];
                    html += '<div class="cbb-repeater-container" data-field="' + escapedPath + '" data-schema=\'' + escapeHtml(JSON.stringify(field.fields || [])) + '\'>';
                    html += '<div class="cbb-repeater-items">';
                    items.forEach(function (item, index) {
                        html += renderRepeaterItem(field.fields || [], item, fieldPath, index);
                    });
                    html += '</div>';
                    html += '<div class="cbb-repeater-actions">';
                    html += '<button type="button" class="button button-secondary cbb-repeater-add" data-target="' + escapedPath + '">+ Add Item</button>';
                    html += '</div>';
                    html += '</div>';
                    break;

                default:
                    html += '<input type="text" id="' + fieldId + '" data-field="' + escapedPath + '" value="' + escapedVal + '" />';
            }

            html += '</div>';
        });

        return html;
    }

    function renderRepeaterItem(fields, values, parentPath, index) {
        let html = '<div class="cbb-repeater-item" data-index="' + index + '">';
        html += '<div class="cbb-repeater-item-header">';
        html += '<span class="cbb-repeater-item-handle dashicons dashicons-menu"></span>';
        html += '<span class="cbb-repeater-item-title">Item #' + (index + 1) + '</span>';
        html += '<button type="button" class="cbb-repeater-item-remove" title="Remove Item"><span class="dashicons dashicons-no-alt"></span></button>';
        html += '</div>';
        html += '<div class="cbb-repeater-item-content">';
        html += renderFields(fields, parentPath + '.' + index, values);
        html += '</div>';
        html += '</div>';
        return html;
    }

    // ==========================================
    // Collect Test Data
    // ==========================================

    function collectTestData() {
        const data = {};
        const schema = getSchema();

        if (!schema.fields) return data;

        const container = document.getElementById('cbb-test-data-form');
        extractFields(schema.fields, data, '', container);
        return data;
    }

    function extractFields(fields, data, pathPrefix, context) {
        if (!context) return;

        fields.forEach(function (field) {
            if (field.type === 'section') {
                if (field.fields && Array.isArray(field.fields)) {
                    extractFields(field.fields, data, pathPrefix, context);
                }
                return;
            }

            if (!field.name) return;

            const fieldPath = pathPrefix ? pathPrefix + '.' + field.name : field.name;

            if (field.type === 'group') {
                const groupValue = {};
                if (field.fields && Array.isArray(field.fields)) {
                    const groupPath = fieldPath;
                    extractFields(field.fields, groupValue, groupPath, context);
                }
                data[field.name] = groupValue;
                return;
            }

            if (field.type === 'repeater') {
                const repeaterContainer = context.querySelector('.cbb-repeater-container[data-field="' + fieldPath + '"]');
                const items = [];
                if (repeaterContainer) {
                    const itemEls = repeaterContainer.querySelectorAll(':scope > .cbb-repeater-items > .cbb-repeater-item');
                    itemEls.forEach(function (itemEl, index) {
                        const itemData = {};
                        const itemPath = fieldPath + '.' + index;
                        extractFields(field.fields || [], itemData, itemPath, itemEl);
                        items.push(itemData);
                    });
                }
                data[field.name] = items;
                return;
            }

            const el = context.querySelector('[data-field="' + fieldPath + '"]');
            if (!el) return;

            const type = field.type || 'text';

            switch (type) {
                case 'boolean':
                    data[field.name] = el.checked;
                    break;
                case 'number':
                    data[field.name] = parseFloat(el.value) || 0;
                    break;
                case 'image':
                    data[field.name] = el.value || '';
                    break;
                default:
                    data[field.name] = el.value;
            }
        });
    }

    function normalizeImageDefault(defaultValue) {
        if (defaultValue && typeof defaultValue === 'object') {
            return {
                url: defaultValue.url ? String(defaultValue.url) : '',
                id: defaultValue.id ? parseInt(defaultValue.id, 10) || 0 : 0,
                alt: defaultValue.alt ? String(defaultValue.alt) : '',
            };
        }

        if (typeof defaultValue === 'string') {
            return { url: defaultValue, id: 0, alt: '' };
        }

        return { url: '', id: 0, alt: '' };
    }

    function normalizeColorDefault(defaultValue) {
        const value = typeof defaultValue === 'string' ? defaultValue.trim() : '';
        return /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(value) ? value : '#000000';
    }

    function bindMediaPickers(container) {
        const $container = $(container);
        $container.off('click.cbbMedia').on('click.cbbMedia', '.cbb-image-select, .cbb-image-remove', function (event) {
            event.preventDefault();
            const target = $(this).data('target');
            const $input = $container.find('[data-field="' + target + '"]');
            const $wrap = $input.closest('.cbb-image-control');
            const $removeBtn = $wrap.find('.cbb-image-remove');

            if ($(this).hasClass('cbb-image-remove')) {
                $input.val('').attr('data-image-id', '0').attr('data-image-alt', '');
                $wrap.find('.cbb-image-preview-wrap').html('<div class="cbb-image-placeholder">No image selected</div>');
                $removeBtn.hide();
                schedulePreview();
                return;
            }

            if (typeof wp === 'undefined' || !wp.media) {
                window.alert('WordPress media library is not available on this screen.');
                return;
            }

            const mediaFrame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' },
            });

            mediaFrame.on('select', function () {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                const imageUrl = attachment.url || '';
                const imageAlt = attachment.alt || '';
                const imageId = attachment.id || 0;

                $input.val(imageUrl).attr('data-image-id', String(imageId)).attr('data-image-alt', imageAlt);

                if (imageUrl) {
                    const previewHtml = '<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(imageAlt || 'Selected image') + '" class="cbb-image-preview" />';
                    $wrap.find('.cbb-image-preview-wrap').html(previewHtml);
                    $removeBtn.show();
                } else {
                    $wrap.find('.cbb-image-preview-wrap').html('<div class="cbb-image-placeholder">No image selected</div>');
                    $removeBtn.hide();
                }
                schedulePreview();
            });

            mediaFrame.open();
        });
    }

    function bindRepeaterEvents(container) {
        const $container = $(container);

        $container.off('click.cbbRepeater').on('click.cbbRepeater', '.cbb-repeater-add, .cbb-repeater-item-remove', function (e) {
            e.preventDefault();

            if ($(this).hasClass('cbb-repeater-add')) {
                const $repeater = $(this).closest('.cbb-repeater-container');
                const $itemsWrap = $repeater.find('> .cbb-repeater-items');
                const fieldPath = $repeater.data('field');
                const schema = $repeater.data('schema') || [];
                const index = $itemsWrap.find('> .cbb-repeater-item').length;

                const itemHtml = renderRepeaterItem(schema, {}, fieldPath, index);
                const $item = $(itemHtml);
                
                $itemsWrap.append($item);
                
                // Re-bind media pickers for the new item.
                bindMediaPickers($item);
                
                // Re-bind repeater events if nested.
                bindRepeaterEvents($item);

                schedulePreview();
            } else if ($(this).hasClass('cbb-repeater-item-remove')) {
                const $item = $(this).closest('.cbb-repeater-item');
                const $itemsWrap = $item.parent();
                
                $item.remove();
                
                // Renumber remaining items.
                $itemsWrap.find('> .cbb-repeater-item').each(function (i) {
                    $(this).attr('data-index', i);
                    $(this).find('> .cbb-repeater-item-header .cbb-repeater-item-title').text('Item #' + (i + 1));
                    
                    // We also need to update data-field attributes of all inputs inside.
                    // This is complex for nested structures, but necessary if we use absolute paths in data-field.
                    // Actually, extractFields uses querySelector with absolute paths.
                    // Let's update them.
                });
                
                updateRepeaterPaths($itemsWrap);
                schedulePreview();
            }
        });
    }

    function updateRepeaterPaths($itemsWrap) {
        const $repeater = $itemsWrap.closest('.cbb-repeater-container');
        const parentPath = $repeater.data('field');

        $itemsWrap.find('> .cbb-repeater-item').each(function (index) {
            const oldIndex = $(this).attr('data-index');
            const newIndex = index;
            
            if (oldIndex != newIndex) {
                $(this).attr('data-index', newIndex);
                $(this).find('> .cbb-repeater-item-header .cbb-repeater-item-title').text('Item #' + (newIndex + 1));
                
                const oldPathBase = parentPath + '.' + oldIndex;
                const newPathBase = parentPath + '.' + newIndex;

                // Update all data-field attributes within this item.
                $(this).find('[data-field]').each(function() {
                    const currentPath = $(this).attr('data-field');
                    if (currentPath && currentPath.startsWith(oldPathBase)) {
                        const newPath = newPathBase + currentPath.substring(oldPathBase.length);
                        $(this).attr('data-field', newPath);
                        
                        if ($(this).attr('id') && $(this).attr('id').startsWith('cbb-test-')) {
                            $(this).attr('id', 'cbb-test-' + newPath.replace(/\./g, '-'));
                        }
                    }
                });

                // Also update data-target on buttons.
                $(this).find('[data-target]').each(function() {
                    const currentTarget = $(this).attr('data-target');
                    if (currentTarget && currentTarget.startsWith(oldPathBase)) {
                        const newTarget = newPathBase + currentTarget.substring(oldPathBase.length);
                        $(this).attr('data-target', newTarget);
                    }
                });
            }
        });
    }

    // ==========================================
    // Live Preview
    // ==========================================

    function schedulePreview() {
        if (!isAutoPreview) return;

        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            triggerPreview();
        }, 600);
    }

    function triggerPreview() {
        const statusEl = document.getElementById('cbb-preview-status');

        if (statusEl) {
            statusEl.textContent = '⏳ Rendering...';
            statusEl.className = 'cbb-preview-status loading';
        }

        const requestData = {
            action: 'cbb_preview_block',
            nonce: cbbAdmin.nonce,
            view: cmView ? cmView.getValue() : '',
            css: cmCss ? cmCss.getValue() : '',
            js: cmJs ? cmJs.getValue() : '',
            schema: cmSchema ? cmSchema.getValue() : '{"fields":[]}',
            test_data: JSON.stringify(collectTestData()),
        };

        $.post(cbbAdmin.ajaxUrl, requestData, function (response) {
            if (response.success) {
                renderPreviewIframe(response.data.html);

                if (statusEl) {
                    statusEl.textContent = '✅ Updated';
                    statusEl.className = 'cbb-preview-status success';
                }
            } else {
                if (statusEl) {
                    statusEl.textContent = '❌ Error: ' + (response.data?.message || 'Unknown');
                    statusEl.className = 'cbb-preview-status error';
                }
            }
        }).fail(function () {
            if (statusEl) {
                statusEl.textContent = '❌ Network error';
                statusEl.className = 'cbb-preview-status error';
            }
        });
    }

    function renderPreviewIframe(html) {
        const iframe = document.getElementById('cbb-preview-iframe');
        if (!iframe) return;

        const doc = iframe.contentDocument || iframe.contentWindow.document;

        const fullHtml = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #1e293b;
            background: #fff;
        }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    ${html}
</body>
</html>`;

        doc.open();
        doc.write(fullHtml);
        doc.close();
    }

    // ==========================================
    // Utilities
    // ==========================================

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ==========================================
    // Initialization
    // ==========================================

    $(document).ready(function () {
        initCodeEditors();
        updateAvailableVars();
        updateTestDataForm();

        // Manual preview button.
        $('#cbb-preview-btn').on('click', function (e) {
            e.preventDefault();
            triggerPreview();
        });

        // Auto-preview toggle.
        $('#cbb-auto-preview').on('change', function () {
            isAutoPreview = this.checked;
        });

        // Initial preview after a short delay (let CodeMirror initialize).
        setTimeout(function () {
            triggerPreview();
        }, 1000);
    });
})(jQuery);
