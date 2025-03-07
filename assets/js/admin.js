jQuery(document).ready(function($) {
    console.log('%c WPVDB Admin JS loaded, version:', 'background: #f0f0f0; color: #333; font-size: 14px; padding: 5px;', wpvdb ? wpvdb.version : 'unknown');
    console.log('%c WPVDB AJAX URL:', 'background: #f0f0f0; color: #333; font-size: 14px; padding: 5px;', wpvdb ? wpvdb.ajaxUrl : 'unknown');
    console.log('%c WPVDB Nonce available:', 'background: #f0f0f0; color: #333; font-size: 14px; padding: 5px;', wpvdb && wpvdb.nonce ? 'Yes' : 'No');
    
    // Settings page specific functionality - provider fields toggle
    if (window.location.href.indexOf('page=wpvdb-settings') > -1) {
        console.log('WPVDB Settings page detected - initializing field toggling');
        
        // Handle API fields visibility regardless of which settings section is active
        function toggleApiFieldsVisibility() {
            var selectedProvider = $('#wpvdb_provider').val();
            console.log('WPVDB: Toggling fields for provider:', selectedProvider);
            
            // Debug output - check which elements we're finding
            var apiKeyFields = $('tr.api-key-field').length;
            var modelFields = $('tr.model-field').length;
            var providerSpecificFields = $('tr.provider-specific-field').length;
            
            console.log('WPVDB: Found fields:', {
                'apiKeyFields': apiKeyFields,
                'modelFields': modelFields,
                'providerSpecificFields': providerSpecificFields
            });
            
            // Hide all provider-specific fields
            $('tr.api-key-field, tr.model-field, tr.provider-specific-field').hide();
            
            // Show only the selected provider's fields
            $('#' + selectedProvider + '_api_key_field').show();
            $('#' + selectedProvider + '_model_field').show();
            
            // Show provider-specific fields with matching data attribute
            var specificFields = $('tr.provider-specific-field[data-provider="' + selectedProvider + '"]');
            console.log('WPVDB: Provider-specific fields for ' + selectedProvider + ':', specificFields.length);
            specificFields.show();
            
            // Show generic fields that apply to all providers
            var genericFields = $('tr.provider-specific-field[data-provider="all"]');
            console.log('WPVDB: Generic fields for all providers:', genericFields.length);
            genericFields.show();
        }
        
        // Run on page load
        console.log('WPVDB: Running toggleApiFieldsVisibility on page load');
        toggleApiFieldsVisibility();
        
        // Run when provider changes - use a more direct approach
        console.log('WPVDB: Setting up change handler for #wpvdb_provider');
        
        // First remove any existing handlers to avoid duplicates
        $('#wpvdb_provider').off('change.wpvdb');
        
        // Then add our handler with a namespace
        $('#wpvdb_provider').on('change.wpvdb', function() {
            console.log('WPVDB: Provider changed to:', $(this).val());
            toggleApiFieldsVisibility();
        });
        
        // Add a direct click handler to ensure the change is detected
        $('.wpvdb-settings-section').on('click', '#wpvdb_provider', function() {
            console.log('WPVDB: Provider dropdown clicked');
            // Set a timeout to check if the value changed after click
            setTimeout(function() {
                console.log('WPVDB: Checking if provider changed after click');
                toggleApiFieldsVisibility();
            }, 100);
        });
    }

    // Toggle post type checkboxes
    $('#wpvdb_auto_embed_toggle_all').on('change', function() {
        $('.wpvdb-post-type-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Handle settings inner tabs
    if (window.location.href.indexOf('page=wpvdb-settings') > -1) {
        // Get current section from URL or set default
        var currentSection = new URLSearchParams(window.location.search).get('section') || 'api';
        
        // No longer need to create tabs since they're now server-side
        
        // Add click handler for section tabs
        $('.wpvdb-section-nav a').on('click', function(e) {
            e.preventDefault();
            window.location.href = $(this).attr('href');
        });
        
        // No longer need to manually update visibility since it's handled server-side
        
        // Provider Change Handling - this runs on the settings page
        var currentProvider = $('#wpvdb_current_provider').val();
        var currentModel = $('#wpvdb_current_model').val();
        
        // Show/hide API key fields based on selected provider
        function toggleApiKeyFields() {
            // This is already handled by our document-level handler
            // But we'll keep this as a backup
            console.log('Section-specific toggleApiKeyFields called');
            toggleApiFieldsVisibility();
        }
        
        // Initialize field visibility
        toggleApiKeyFields();
        
        // Toggle fields when provider changes
        // Removed this handler as it's now handled by our main handler
        // $('#wpvdb_provider').on('change', function() {
        //     toggleApiKeyFields();
        // });
        
        // Handle form submission
        $('#wpvdb-settings-form').on('submit', function(e) {
            var newProvider = $('#wpvdb_provider').val();
            var newModel;
            
            if (newProvider === 'openai') {
                newModel = $('#wpvdb_openai_model').val();
            } else {
                newModel = $('#wpvdb_automattic_model').val();
            }
            
            // Check if provider or model changed
            if (newProvider !== currentProvider || newModel !== currentModel) {
                e.preventDefault();
                
                // Check if we need to confirm the change
                $.ajax({
                    url: wpvdb.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpvdb_validate_provider_change',
                        nonce: wpvdb.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.requires_reindex) {
                                // Show confirmation dialog
                                if (confirm('Changing the embedding provider or model requires re-indexing all content. This will delete ' + 
                                          response.data.embedding_count + ' existing embeddings. Do you want to continue?')) {
                                    // User confirmed, submit the form
                                    $('#wpvdb-settings-form').off('submit').trigger('submit');
                                } else {
                                    // User cancelled, reset the form
                                    $('#wpvdb_provider').val(currentProvider);
                                    toggleApiKeyFields();
                                }
                            } else {
                                // No embeddings exist, just submit the form
                                $('#wpvdb-settings-form').off('submit').trigger('submit');
                            }
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while validating the provider change.');
                    }
                });
            }
        });
    }

    /**
     * Status Page - Provider Change Confirmation
     */
    if (window.location.href.indexOf('page=wpvdb-status') > -1) {
        // Handle provider change confirmation
        $('#wpvdb-apply-provider-change, #wpvdb-apply-provider-change-tool').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('This will delete all existing embeddings and activate the new provider. Are you sure you want to continue?')) {
                $.ajax({
                    url: wpvdb.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpvdb_confirm_provider_change',
                        nonce: wpvdb.nonce,
                        cancel: false
                    },
                    beforeSend: function() {
                        $('#wpvdb-apply-provider-change, #wpvdb-apply-provider-change-tool').prop('disabled', true).text('Processing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                            $('#wpvdb-apply-provider-change, #wpvdb-apply-provider-change-tool').prop('disabled', false).text('Apply Change');
                        }
                    },
                    error: function() {
                        alert('An error occurred while confirming the provider change.');
                        $('#wpvdb-apply-provider-change, #wpvdb-apply-provider-change-tool').prop('disabled', false).text('Apply Change');
                    }
                });
            }
        });
        
        // Handle provider change cancellation
        $('#wpvdb-cancel-provider-change, #wpvdb-cancel-provider-change-tool').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('This will cancel the pending provider change. Are you sure?')) {
                $.ajax({
                    url: wpvdb.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpvdb_confirm_provider_change',
                        nonce: wpvdb.nonce,
                        cancel: 'true'
                    },
                    beforeSend: function() {
                        $('#wpvdb-cancel-provider-change, #wpvdb-cancel-provider-change-tool').prop('disabled', true).text('Processing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                            $('#wpvdb-cancel-provider-change, #wpvdb-cancel-provider-change-tool').prop('disabled', false).text('Cancel Change');
                        }
                    },
                    error: function() {
                        alert('An error occurred while cancelling the provider change.');
                        $('#wpvdb-cancel-provider-change, #wpvdb-cancel-provider-change-tool').prop('disabled', false).text('Cancel Change');
                    }
                });
            }
        });
        
        // Test Embedding Modal Functions
        // Open the modal when the button is clicked
        $('#wpvdb-test-embedding-button').on('click', function() {
            $('#wpvdb-test-embedding-modal').css('display', 'block');
            // Reset form and hide results
            $('#wpvdb-test-embedding-form')[0].reset();
            $('#wpvdb-test-embedding-results').hide();
            $('.wpvdb-status-message, .wpvdb-embedding-info').empty();
        });
        
        // Provider and model selection for test embedding is handled by the wpvdbModels script now
        
        // Handle test embedding form submission
        $('#wpvdb-test-embedding-form').on('submit', function(e) {
            e.preventDefault();
            
            var provider = $('#wpvdb-test-provider').val();
            // Use the new unified model select
            var model = $('#wpvdb-test-model').val();
            var text = $('#wpvdb-test-text').val();
            
            if (!text.trim()) {
                alert('Please enter some text to embed.');
                return;
            }
            
            // Show loading message
            $('#wpvdb-test-embedding-results').show();
            $('.wpvdb-status-message').html('<div class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></div> Generating embedding...');
            $('.wpvdb-embedding-info').empty();
            
            // Disable submit button
            $(this).find('button[type="submit"]').prop('disabled', true);
            
            // Submit AJAX request
            $.ajax({
                url: wpvdb.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpvdb_test_embedding',
                    nonce: wpvdb.nonce,
                    provider: provider,
                    model: model,
                    text: text
                },
                success: function(response) {
                    if (response.success) {
                        // Display results
                        $('.wpvdb-status-message').html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Embedding generated successfully!');
                        
                        var infoHtml = '<table class="widefat striped">' +
                            '<tr><th>Provider</th><td>' + response.data.provider + '</td></tr>' +
                            '<tr><th>Model</th><td>' + response.data.model + '</td></tr>' +
                            '<tr><th>Dimensions</th><td>' + response.data.dimensions + '</td></tr>' +
                            '<tr><th>Time</th><td>' + response.data.time + ' seconds</td></tr>' +
                            '<tr><th>Sample Values</th><td><pre>' + response.data.sample + '</pre></td></tr>' +
                            '</table>';
                        
                        $('.wpvdb-embedding-info').html(infoHtml);
                    } else {
                        // Show error
                        $('.wpvdb-status-message').html('<span class="dashicons dashicons-warning" style="color: red;"></span> Error: ' + response.data.message);
                    }
                },
                error: function() {
                    $('.wpvdb-status-message').html('<span class="dashicons dashicons-warning" style="color: red;"></span> Error: Network error occurred.');
                },
                complete: function() {
                    // Enable submit button
                    $('#wpvdb-test-embedding-form').find('button[type="submit"]').prop('disabled', false);
                }
            });
        });
    }

    /**
     * Embeddings Page Functionality
     */
    if (window.location.href.indexOf('page=wpvdb-embeddings') > -1) {
        // Provider and model selection for bulk embed is handled by the wpvdbBulkModels script now
        
        // Open the bulk embed modal
        $('#wpvdb-bulk-embed-button').on('click', function() {
            // Reset form and hide results
            $('#wpvdb-bulk-embed-form')[0].reset();
            $('#wpvdb-bulk-embed-results').hide();
            $('.wpvdb-status-message').empty();
        });
        
        // Handle view full content button click using delegation
        $(document).on('click', '.wpvdb-view-full', function() {
            var embedId = $(this).data('id');
            var modal = $('#wpvdb-full-content-modal');
            
            // Show the modal with loading state
            modal.css('display', 'block');
            
            $.ajax({
                url: wpvdb.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpvdb_get_embedding_content',
                    nonce: wpvdb.nonce,
                    id: embedId
                },
                success: function(response) {
                    if (response.success) {
                        $('.wpvdb-full-content').text(response.data.content);
                    } else {
                        $('.wpvdb-full-content').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $('.wpvdb-full-content').html('<div class="notice notice-error"><p>Error fetching content. Please try again.</p></div>');
                }
            });
        });
        
        // Moved misplaced handler to proper location
        $('#wpvdb-bulk-embed').on('click', function(e) {
            e.preventDefault();
            $('#wpvdb-bulk-embed-modal').css('display', 'block');
            $('#wpvdb-bulk-embed-form')[0].reset();
            $('#wpvdb-bulk-embed-results').hide();
            $('.wpvdb-status-message').empty();
        });
        
        // Handle delete embedding button click using delegation
        $(document).on('click', '.wpvdb-delete-embedding', function(e) {
            e.preventDefault();
            
            var embedId = $(this).data('id');
            var row = $(this).closest('tr');
            
            if (confirm('Are you sure you want to delete this embedding? This action cannot be undone.')) {
                $.ajax({
                    url: wpvdb.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpvdb_delete_embedding',
                        nonce: wpvdb.nonce,
                        id: embedId
                    },
                    beforeSend: function() {
                        // Disable the link and show loading state
                        row.css('opacity', '0.5');
                    },
                    success: function(response) {
                        if (response.success) {
                            row.fadeOut(300, function() {
                                row.remove();
                                
                                // If no more rows, refresh the page to show the "no embeddings" message
                                if ($('table.wp-list-table tbody tr').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            alert('Error: ' + response.data.message);
                            row.css('opacity', '1');
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the embedding.');
                        row.css('opacity', '1');
                    }
                });
            }
        });
        
        // Handle the bulk embed button click instead of form submission
        $('#wpvdb-generate-embeddings-btn').on('click', function(e) {
            console.log('%c WPVDB Generate Embeddings Button Clicked', 'background: #f0f0f0; color: #333; font-size: 14px; padding: 5px;');
            
            var postType = $('#wpvdb-post-type').val();
            var limit = $('#wpvdb-limit').val();
            var provider = $('#wpvdb-provider').val();
            var model = $('#wpvdb-model').val();
            
            console.log('%c WPVDB Form Values:', 'background: #f0f0f0; color: #333; font-size: 14px; padding: 5px;', { postType, limit, provider, model });
            
            // Show the results area with progress bar
            $('#wpvdb-bulk-embed-results').show();
            $('.wpvdb-progress-bar').css('width', '0%');
            $('.wpvdb-status-message').html('Fetching posts to embed...');
            
            // Disable the form
            $('#wpvdb-bulk-embed-form').find('input, select, button').prop('disabled', true);
            
            // First, get the list of posts to process
            $.ajax({
                url: wpvdb.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpvdb_get_posts_for_indexing',
                    nonce: wpvdb.nonce,
                    post_type: postType,
                    limit: limit
                },
                success: function(response) {
                    console.log('%c WPVDB AJAX Response:', 'background: #f0f0f0; color: #333; font-size: 14px; padding: 5px;', response);
                    if (response.success) {
                        if (response.data.posts.length === 0) {
                            $('.wpvdb-status-message').html('No posts found to embed.');
                            $('#wpvdb-bulk-embed-form').find('input, select, button').prop('disabled', false);
                            return;
                        }
                        
                        // We have posts, start the embedding process
                        processPosts(response.data.posts, provider, model);
                    } else {
                        $('.wpvdb-status-message').html('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                        $('#wpvdb-bulk-embed-form').find('input, select, button').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('%c WPVDB AJAX Error:', 'background: #f04040; color: white; font-size: 14px; padding: 5px;', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    $('.wpvdb-status-message').html('Error fetching posts to embed. Please check the browser console for details.');
                    $('#wpvdb-bulk-embed-form').find('input, select, button').prop('disabled', false);
                }
            });
        });
        
        // Process posts one by one
        function processPosts(posts, provider, model) {
            var total = posts.length;
            var processed = 0;
            var successful = 0;
            var failed = 0;
            
            function processNext() {
                if (processed >= total) {
                    // All done
                    $('.wpvdb-progress-bar').css('width', '100%');
                    $('.wpvdb-status-message').html('Completed! Successfully embedded ' + successful + ' of ' + total + ' posts.');
                    $('#wpvdb-bulk-embed-form').find('input, select, button').prop('disabled', false);
                    
                    // Refresh page after a delay to show the new embeddings
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    return;
                }
                
                var post = posts[processed];
                $('.wpvdb-status-message').html('Processing ' + (processed + 1) + ' of ' + total + ': "' + post.title + '" (ID: ' + post.id + ')');
                
                $.ajax({
                    url: wpvdb.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpvdb_bulk_embed',
                        nonce: wpvdb.nonce,
                        post_ids: [post.id],
                        provider: provider,
                        model: model
                    },
                    success: function(response) {
                        processed++;
                        
                        if (response.success) {
                            successful++;
                        } else {
                            failed++;
                        }
                        
                        // Update progress bar
                        var progress = Math.floor((processed / total) * 100);
                        $('.wpvdb-progress-bar').css('width', progress + '%');
                        
                        // Process the next post
                        processNext();
                    },
                    error: function() {
                        processed++;
                        failed++;
                        
                        // Update progress bar
                        var progress = Math.floor((processed / total) * 100);
                        $('.wpvdb-progress-bar').css('width', progress + '%');
                        
                        // Process the next post despite the error
                        processNext();
                    }
                });
            }
            
            // Start processing
            processNext();
        }
    }
    
    /**
     * Global Modal Handlers
     */
    // Close modals when clicking the close button or cancel button
    $(document).on('click', '.wpvdb-modal-close, .wpvdb-modal-cancel', function() {
        $('.wpvdb-modal').css('display', 'none');
    });
    
    // Close modals when clicking outside the modal content
    $(document).on('click', '.wpvdb-modal', function(event) {
        if ($(event.target).hasClass('wpvdb-modal')) {
            $('.wpvdb-modal').css('display', 'none');
        }
    });
}); 