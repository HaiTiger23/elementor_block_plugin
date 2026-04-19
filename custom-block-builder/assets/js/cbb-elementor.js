/**
 * Custom Block Builder — Elementor Editor Integration
 *
 * Handles AJAX-based preview refresh in the Elementor editor
 * when the block selection or field values change.
 */

(function ($) {
    'use strict';

    if (typeof elementor === 'undefined') {
        return;
    }

    /**
     * Refresh the widget preview when settings change.
     */
    function refreshWidgetPreview(widget) {
        // Force Elementor to re-render the widget via server-side render.
        widget.renderOnChange = true;
    }

    /**
     * Listen for the Custom Block widget controls to change.
     */
    elementor.hooks.addAction(
        'panel/open_editor/widget/cbb_custom_block',
        function (panel, model, view) {
            // When any setting changes on our widget, force a server re-render.
            model.on('change:settings', function () {
                // Use the built-in render method which triggers server-side rendering.
                if (view && typeof view.render === 'function') {
                    // Debounce to avoid excessive re-renders.
                    clearTimeout(view._cbbRenderTimeout);
                    view._cbbRenderTimeout = setTimeout(function () {
                        view.render();
                    }, 500);
                }
            });

            // Add a refresh button to the panel.
            const $panelContent = panel.$el.find('.elementor-panel-navigation');

            if ($panelContent.length && !$panelContent.find('.cbb-refresh-btn').length) {
                const $refreshBtn = $(
                    '<button type="button" class="cbb-refresh-btn" style="' +
                    'display:inline-flex;align-items:center;gap:4px;' +
                    'padding:6px 12px;margin:8px;' +
                    'background:#6366f1;color:#fff;border:none;border-radius:4px;' +
                    'font-size:12px;cursor:pointer;' +
                    '">🔄 Refresh Preview</button>'
                );

                $refreshBtn.on('click', function (e) {
                    e.preventDefault();
                    if (view && typeof view.render === 'function') {
                        view.render();
                    }
                });

                $panelContent.after($refreshBtn);
            }
        }
    );
})(jQuery);
