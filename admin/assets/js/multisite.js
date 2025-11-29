/**
 * Multisite Cross-Site Duplication JavaScript
 */

(function($) {
	'use strict';

	var currentPage = 1;
	var selectedPosts = [];

	/**
	 * Initialize
	 */
	function init() {
		// Show post type row when sites are selected
		$('#apd_source_site, #apd_destination_site').on('change', function() {
			var sourceSite = $('#apd_source_site').val();
			var destSite = $('#apd_destination_site').val();

			if (sourceSite && destSite && sourceSite !== destSite) {
				$('#apd-post-type-row, #apd-post-search-row').show();
			} else {
				$('#apd-post-type-row, #apd-post-search-row, #apd-posts-list-row, #apd-options-row').hide();
				selectedPosts = [];
				updateDuplicateButton();
			}
		});

		// Search posts
		$('#apd-search-posts').on('click', function(e) {
			e.preventDefault();
			loadPosts(1);
		});

		$('#apd_post_search').on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				loadPosts(1);
			}
		});

		// Handle duplicate button
		$('#apd-duplicate-cross-site').on('click', function(e) {
			e.preventDefault();
			duplicatePosts();
		});

		// Load logs on page load
		loadLogs();
	}

	/**
	 * Load posts from source site
	 */
	function loadPosts(page) {
		var sourceSite = $('#apd_source_site').val();
		var postType = $('#apd_post_type').val();
		var search = $('#apd_post_search').val();

		if (!sourceSite || !postType) {
			return;
		}

		currentPage = page || 1;

		$('#apd-posts-list').html('<p>Loading posts...</p>');
		$('#apd-posts-list-row, #apd-options-row').show();

		$.ajax({
			url: apdMultisite.ajaxUrl,
			type: 'POST',
			data: {
				action: 'apd_get_site_posts',
				nonce: apdMultisite.nonce,
				site_id: sourceSite,
				post_type: postType,
				search: search,
				page: currentPage
			},
			success: function(response) {
				if (response.success) {
					displayPosts(response.data.posts);
					displayPagination(response.data.total_pages, currentPage);
				} else {
					$('#apd-posts-list').html('<p class="error">Error: ' + (response.data.message || 'Failed to load posts') + '</p>');
				}
			},
			error: function() {
				$('#apd-posts-list').html('<p class="error">Error loading posts. Please try again.</p>');
			}
		});
	}

	/**
	 * Display posts
	 */
	function displayPosts(posts) {
		if (!posts || posts.length === 0) {
			$('#apd-posts-list').html('<p>No posts found.</p>');
			return;
		}

		var html = '<ul class="apd-posts-list-items">';
		
		$.each(posts, function(index, post) {
			var checked = selectedPosts.indexOf(post.id) !== -1 ? 'checked' : '';
			html += '<li>';
			html += '<label>';
			html += '<input type="checkbox" class="apd-post-checkbox" value="' + post.id + '" ' + checked + '> ';
			html += '<strong>' + escapeHtml(post.title) + '</strong>';
			html += ' <span class="description">(' + post.status + ' - ' + post.date + ')</span>';
			html += '</label>';
			html += '</li>';
		});

		html += '</ul>';

		$('#apd-posts-list').html(html);

		// Handle checkbox changes
		$('.apd-post-checkbox').on('change', function() {
			var postId = parseInt($(this).val());
			
			if ($(this).is(':checked')) {
				if (selectedPosts.indexOf(postId) === -1) {
					selectedPosts.push(postId);
				}
			} else {
				selectedPosts = selectedPosts.filter(function(id) {
					return id !== postId;
				});
			}

			updateDuplicateButton();
		});
	}

	/**
	 * Display pagination
	 */
	function displayPagination(totalPages, currentPage) {
		if (totalPages <= 1) {
			$('#apd-posts-pagination').html('');
			return;
		}

		var html = '<div class="apd-pagination">';

		if (currentPage > 1) {
			html += '<a href="#" class="button apd-prev-page" data-page="' + (currentPage - 1) + '">Previous</a> ';
		}

		html += '<span>Page ' + currentPage + ' of ' + totalPages + '</span>';

		if (currentPage < totalPages) {
			html += ' <a href="#" class="button apd-next-page" data-page="' + (currentPage + 1) + '">Next</a>';
		}

		html += '</div>';

		$('#apd-posts-pagination').html(html);

		// Handle pagination clicks
		$('.apd-prev-page, .apd-next-page').on('click', function(e) {
			e.preventDefault();
			var page = parseInt($(this).data('page'));
			loadPosts(page);
		});
	}

	/**
	 * Update duplicate button state
	 */
	function updateDuplicateButton() {
		if (selectedPosts.length > 0) {
			$('#apd-duplicate-cross-site').prop('disabled', false);
		} else {
			$('#apd-duplicate-cross-site').prop('disabled', true);
		}
	}

	/**
	 * Duplicate posts
	 */
	function duplicatePosts() {
		if (selectedPosts.length === 0) {
			alert('Please select at least one post to duplicate.');
			return;
		}

		var sourceSite = $('#apd_source_site').val();
		var destSite = $('#apd_destination_site').val();
		var copyMedia = $('input[name="copy_media"]').is(':checked');
		var postStatus = $('select[name="post_status"]').val();

		if (!sourceSite || !destSite) {
			alert('Please select both source and destination sites.');
			return;
		}

		$('#apd-duplicate-cross-site').prop('disabled', true).text('Duplicating...');
		$('.spinner').addClass('is-active');
		$('#apd-duplication-results').hide();

		$.ajax({
			url: apdMultisite.ajaxUrl,
			type: 'POST',
			data: {
				action: 'apd_duplicate_cross_site',
				nonce: apdMultisite.nonce,
				source_site_id: sourceSite,
				destination_site_id: destSite,
				post_ids: selectedPosts,
				copy_media: copyMedia ? 1 : 0,
				post_status: postStatus
			},
			success: function(response) {
				$('#apd-duplicate-cross-site').prop('disabled', false).text('Duplicate to Destination Site');
				$('.spinner').removeClass('is-active');

				if (response.success) {
					displayResults(response.data);
					loadLogs();
					selectedPosts = [];
					$('.apd-post-checkbox').prop('checked', false);
					updateDuplicateButton();
				} else {
					alert('Error: ' + (response.data.message || 'Duplication failed'));
				}
			},
			error: function() {
				$('#apd-duplicate-cross-site').prop('disabled', false).text('Duplicate to Destination Site');
				$('.spinner').removeClass('is-active');
				alert('Error duplicating posts. Please try again.');
			}
		});
	}

	/**
	 * Display duplication results
	 */
	function displayResults(data) {
		var html = '<div class="notice notice-success"><p>';
		html += '<strong>Success:</strong> ' + data.success.length + ' post(s) duplicated successfully.';
		html += '</p></div>';

		if (data.errors.length > 0) {
			html += '<div class="notice notice-error"><p>';
			html += '<strong>Errors:</strong> ' + data.errors.length + ' post(s) failed to duplicate.';
			html += '<ul>';
			$.each(data.errors, function(index, error) {
				html += '<li>Post ID ' + error.source_post_id + ': ' + error.message + '</li>';
			});
			html += '</ul>';
			html += '</p></div>';
		}

		$('#apd-duplication-results').html(html).show();
	}

	/**
	 * Load logs
	 */
	function loadLogs() {
		$.ajax({
			url: apdMultisite.ajaxUrl,
			type: 'POST',
			data: {
				action: 'apd_get_duplication_logs',
				nonce: apdMultisite.nonce,
				limit: 20
			},
			success: function(response) {
				if (response.success && response.data.length > 0) {
					var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
					html += '<th>Time</th><th>Type</th><th>Message</th>';
					html += '</tr></thead><tbody>';

					$.each(response.data, function(index, log) {
						var typeClass = log.type === 'success' ? 'success' : 'error';
						html += '<tr class="' + typeClass + '">';
						html += '<td>' + log.timestamp + '</td>';
						html += '<td>' + (log.type || 'Error') + '</td>';
						html += '<td>' + escapeHtml(log.message) + '</td>';
						html += '</tr>';
					});

					html += '</tbody></table>';
					$('#apd-logs-container').html(html);
				}
			}
		});
	}

	/**
	 * Escape HTML
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);

