/**
 * Advanced Post Duplicator Admin JavaScript
 */

(function($) {
	'use strict';

	/**
	 * Toggle offset date fields
	 */
	function toggleOffsetFields() {
		var $toggle = $('.apd-offset-date-toggle');
		var $fields = $('.apd-offset-fields');

		$toggle.on('change', function() {
			if ($(this).is(':checked')) {
				$fields.slideDown();
			} else {
				$fields.slideUp();
			}
		});
	}

	/**
	 * Add duplicate button to Block Editor (Gutenberg)
	 */
	function addBlockEditorButton() {
		if (typeof wp === 'undefined' || typeof wp.plugins === 'undefined' || typeof apdEditor === 'undefined') {
			return;
		}

		var el = wp.element.createElement;
		var registerPlugin = wp.plugins.registerPlugin;
		var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
		var Button = wp.components.Button;
		var useSelect = wp.data.useSelect;
		var __ = wp.i18n.__;

		var DuplicateButton = function() {
			var postId = useSelect(function(select) {
				if (typeof select('core/editor') !== 'undefined') {
					return select('core/editor').getCurrentPostId();
				}
				return 0;
			});

			if (!postId || postId === 0 || !apdEditor.duplicateUrl) {
				return null;
			}

			// Build the duplicate URL with the current post ID
			// Replace only the post ID in the query string parameter, not other digits (like port numbers)
			var duplicateUrl = apdEditor.duplicateUrl.replace(/([?&])post=\d+/, '$1post=' + postId);

			return el(
				PluginPostStatusInfo,
				{
					className: 'apd-duplicate-status-info'
				},
				el(
					Button,
					{
						isLink: true,
						className: 'apd-block-editor-duplicate',
						onClick: function() {
							window.location.href = duplicateUrl;
						}
					},
					el('span', {
						className: 'dashicons dashicons-admin-page',
						style: { marginRight: '5px', verticalAlign: 'middle', fontSize: '16px', width: '16px', height: '16px' }
					}),
					__('Duplicate', 'advanced-post-duplicator')
				)
			);
		};

		registerPlugin('apd-duplicate-button', {
			render: DuplicateButton,
			icon: 'admin-page'
		});
	}

	/**
	 * Initialize
	 */
	$(document).ready(function() {
		toggleOffsetFields();
	});

	// Initialize Block Editor button when WordPress is ready
	if (typeof wp !== 'undefined' && wp.domReady) {
		wp.domReady(addBlockEditorButton);
	} else {
		// Fallback for when wp.domReady is not available
		$(document).ready(function() {
			setTimeout(addBlockEditorButton, 100);
		});
	}

})(jQuery);

