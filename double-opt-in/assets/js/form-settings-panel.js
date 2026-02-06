/**
 * Form Settings Panel JavaScript
 *
 * Handles the slide-out panel for form settings management.
 *
 * @package Forge12\DoubleOptIn
 * @since   4.1.0
 */

(function($) {
    'use strict';

    var DoiFormSettings = {
        /**
         * Current form ID being edited.
         */
        currentFormId: null,

        /**
         * Current form data.
         */
        currentFormData: null,

        /**
         * Current integration type.
         */
        currentIntegration: null,

        /**
         * Initialize the module.
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Toggle switch
            $(document).on('change', '.doi-toggle-input', function() {
                self.toggleForm($(this));
            });

            // Configure button
            $(document).on('click', '.doi-configure-btn', function() {
                var formId = $(this).data('form-id');
                var formTitle = $(this).data('form-title');
                var integration = $(this).data('integration') || '';
                self.openPanel(formId, formTitle, integration);
            });

            // Close panel
            $(document).on('click', '.doi-panel-close, .doi-panel-overlay, #doi-panel-cancel', function() {
                self.closePanel();
            });

            // Save settings
            $(document).on('click', '#doi-panel-save', function() {
                self.saveSettings();
            });

            // Escape key to close panel
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#doi-settings-panel').hasClass('open')) {
                    self.closePanel();
                }
            });

            // Placeholder click to insert
            $(document).on('click', '.doi-placeholders code', function() {
                var placeholder = $(this).text();
                var $body = $('#doi-body');
                var cursorPos = $body[0].selectionStart;
                var text = $body.val();
                $body.val(text.substring(0, cursorPos) + placeholder + text.substring(cursorPos));
                $body.focus();
                $body[0].selectionStart = $body[0].selectionEnd = cursorPos + placeholder.length;
            });

            // Toggle between select and manual input for recipient
            $(document).on('click', '#doi-toggle-recipient-input', function() {
                var $select = $('#doi-recipient-select');
                var $input = $('#doi-recipient');

                if ($select.is(':visible')) {
                    // Switch to manual input
                    $input.val($select.val()).show();
                    $select.hide();
                    $(this).find('.dashicons').removeClass('dashicons-edit').addClass('dashicons-list-view');
                } else {
                    // Switch to select
                    var currentVal = $input.val();
                    $select.show();
                    $input.hide();
                    // Try to select the current value
                    if ($select.find('option[value="' + currentVal + '"]').length) {
                        $select.val(currentVal);
                    }
                    $(this).find('.dashicons').removeClass('dashicons-list-view').addClass('dashicons-edit');
                }
            });

            // Sync select value to hidden input
            $(document).on('change', '#doi-recipient-select', function() {
                $('#doi-recipient').val($(this).val());
            });

            // Template selection change - toggle body editor vs custom template notice
            $(document).on('change', '#doi-template', function() {
                self.handleTemplateChange($(this).val());
            });

        },

        /**
         * Handle template selection change.
         * Shows/hides body editor based on whether a custom template is selected.
         *
         * @param {string} templateValue The selected template value.
         */
        handleTemplateChange: function(templateValue) {
            var isCustomTemplate = templateValue && templateValue.indexOf('custom_') === 0;
            var $bodyEditor = $('#doi-body-editor');
            var $customNotice = $('#doi-custom-template-notice');
            var $editBtn = $('#doi-edit-template-btn');

            if (isCustomTemplate) {
                // Custom template selected - hide body editor, show notice
                $bodyEditor.hide();
                $customNotice.show();

                // Update edit button link to include template ID
                var templateId = templateValue.replace('custom_', '');
                var editorUrl = doiFormSettings.editorUrl + '&template_id=' + templateId;
                $editBtn.attr('href', editorUrl);
            } else {
                // Standard template - show body editor, hide notice
                $bodyEditor.show();
                $customNotice.hide();
            }
        },

        /**
         * Toggle form enabled state.
         *
         * @param {jQuery} $toggle The toggle input element.
         */
        toggleForm: function($toggle) {
            var self = this;
            var formId = $toggle.data('form-id');
            var $label = $toggle.closest('.doi-toggle');

            $label.addClass('loading');

            $.ajax({
                url: doiFormSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'doi_toggle_form',
                    nonce: doiFormSettings.nonce,
                    form_id: formId
                },
                success: function(response) {
                    $label.removeClass('loading');

                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                    } else {
                        // Revert toggle
                        $toggle.prop('checked', !$toggle.prop('checked'));
                        self.showToast(response.data.message || doiFormSettings.i18n.error, 'error');
                    }
                },
                error: function(xhr) {
                    $label.removeClass('loading');
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    self.showErrorFromResponse(xhr);
                }
            });
        },

        /**
         * Open the settings panel.
         *
         * @param {number} formId The form ID.
         * @param {string} formTitle The form title.
         * @param {string} integration The integration type (optional).
         */
        openPanel: function(formId, formTitle, integration) {
            var self = this;
            var $panel = $('#doi-settings-panel');

            this.currentFormId = formId;
            this.currentIntegration = integration || '';

            // Update title
            $panel.find('.doi-panel-title').text(
                doiFormSettings.i18n.configure + ': ' + formTitle
            );

            // Show panel
            $panel.addClass('open');
            $('body').css('overflow', 'hidden');

            // Show loading, hide form
            $panel.find('.doi-panel-loading').show();
            $panel.find('.doi-settings-form').hide();

            // Load settings
            $.ajax({
                url: doiFormSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'doi_get_form_settings',
                    nonce: doiFormSettings.nonce,
                    form_id: formId,
                    integration: integration || ''
                },
                success: function(response) {
                    $panel.find('.doi-panel-loading').hide();

                    if (response.success) {
                        self.currentFormData = response.data;
                        self.populateForm(response.data);
                        $panel.find('.doi-settings-form').show();
                    } else {
                        self.showToast(response.data.message || doiFormSettings.i18n.error, 'error');
                        self.closePanel();
                    }
                },
                error: function(xhr) {
                    self.showErrorFromResponse(xhr);
                    self.closePanel();
                }
            });
        },

        /**
         * Close the settings panel.
         */
        closePanel: function() {
            var $panel = $('#doi-settings-panel');
            $panel.removeClass('open');
            $('body').css('overflow', '');
            this.currentFormId = null;
            this.currentFormData = null;
            this.currentIntegration = null;
        },

        /**
         * Populate the form with data.
         *
         * @param {Object} data The form data.
         */
        populateForm: function(data) {
            var settings = data.settings || {};
            var fields = data.fields || {};
            var templates = data.templates || {};
            var categories = data.categories || {};
            var pages = data.pages || {};

            // Form ID
            $('#doi-form-id').val(data.id);

            // Handle Enable/Disable field based on integration type
            var isElementor = this.currentIntegration === 'elementor';

            if (isElementor) {
                // For Elementor: show status badge instead of checkbox
                $('#doi-enable-field').hide();
                $('#doi-status-field').show();

                // Show appropriate badge based on enabled state
                if (settings.enabled) {
                    $('#doi-status-active').show();
                    $('#doi-status-inactive').hide();
                } else {
                    $('#doi-status-active').hide();
                    $('#doi-status-inactive').show();
                }
            } else {
                // For other integrations: show checkbox
                $('#doi-enable-field').show();
                $('#doi-status-field').hide();
                $('#doi-enabled').prop('checked', settings.enabled);
            }

            // Populate categories select
            var $category = $('#doi-category');
            $category.empty();
            $.each(categories, function(id, name) {
                $category.append($('<option>', { value: id, text: name }));
            });
            $category.val(settings.category || 0);

            // Populate conditions select (form fields)
            var $conditions = $('#doi-conditions');
            $conditions.empty();
            $conditions.append($('<option>', { value: 'disabled', text: doiFormSettings.i18n.disabled }));
            $.each(fields, function(name, label) {
                $conditions.append($('<option>', { value: name, text: '[' + name + ']' }));
            });
            $conditions.val(settings.conditions || 'disabled');

            // Populate confirmation page select
            var $page = $('#doi-confirmation-page');
            $page.empty();
            $.each(pages, function(id, title) {
                $page.append($('<option>', { value: id, text: title }));
            });
            $page.val(settings.confirmationPage || -1);

            // Populate recipient field (select + manual input)
            var $recipientSelect = $('#doi-recipient-select');
            var $recipientInput = $('#doi-recipient');
            var $recipientToggle = $('#doi-toggle-recipient-input');
            var hasFields = Object.keys(fields).length > 0;

            $recipientSelect.empty();
            $recipientSelect.append($('<option>', { value: '', text: '-- ' + doiFormSettings.i18n.disabled + ' --' }));
            $.each(fields, function(name, label) {
                var displayText = label !== name ? label + ' [' + name + ']' : '[' + name + ']';
                $recipientSelect.append($('<option>', { value: '[' + name + ']', text: displayText }));
            });

            var currentRecipient = settings.recipient || '';
            $recipientInput.val(currentRecipient);

            // Determine if we should show select or input
            var recipientInFields = false;
            if (currentRecipient && hasFields) {
                recipientInFields = $recipientSelect.find('option[value="' + currentRecipient + '"]').length > 0;
            }

            if (!hasFields || (currentRecipient && !recipientInFields)) {
                // No fields found or current value not in fields - show manual input
                $recipientSelect.hide();
                $recipientInput.show();
                $recipientToggle.find('.dashicons').removeClass('dashicons-edit').addClass('dashicons-list-view');
            } else {
                // Show select
                $recipientSelect.show().val(currentRecipient);
                $recipientInput.hide();
                $recipientToggle.find('.dashicons').removeClass('dashicons-list-view').addClass('dashicons-edit');
            }

            // Hide toggle button if no fields (manual input only)
            if (!hasFields) {
                $recipientToggle.hide();
            } else {
                $recipientToggle.show();
            }

            // Sender
            $('#doi-sender').val(settings.sender || '');
            $('#doi-sender-name').val(settings.senderName || '');

            // Subject
            $('#doi-subject').val(settings.subject || '');

            // Populate template select
            var $template = $('#doi-template');
            $template.empty();
            $.each(templates, function(key, label) {
                $template.append($('<option>', { value: key, text: label }));
            });
            var selectedTemplate = settings.template || 'blank';
            $template.val(selectedTemplate);

            // Body
            $('#doi-body').val(settings.body || '');

            // Consent Text (GDPR)
            $('#doi-consent-text').val(settings.consentText || '');

            // Handle template change to show/hide body editor
            this.handleTemplateChange(selectedTemplate);

            // Update form field placeholders
            var fieldsHtml = '';
            $.each(fields, function(name) {
                fieldsHtml += '<code>[' + name + ']</code> ';
            });
            $('.doi-form-fields-placeholders').html(fieldsHtml);

            // Populate field mapping grid
            this.populateFieldMapping(fields, settings.fieldMapping || {});

            /**
             * Trigger event for extensions (e.g., Pro version) to populate additional fields.
             *
             * @param {Object} data The full form data object.
             * @param {Object} settings The settings object.
             */
            $(document).trigger('doi:panel:populated', [data, settings]);
        },

        /**
         * Save the current settings.
         */
        saveSettings: function() {
            var self = this;
            var $panel = $('#doi-settings-panel');
            var $form = $('#doi-settings-form');

            if (!this.currentFormId) {
                return;
            }

            // Collect form data
            // For Elementor, preserve the enabled state from server (don't read checkbox)
            var isElementor = this.currentIntegration === 'elementor';
            var settings = {
                enabled: isElementor ? (this.currentFormData && this.currentFormData.settings ? this.currentFormData.settings.enabled : false) : $('#doi-enabled').is(':checked'),
                category: $('#doi-category').val(),
                conditions: $('#doi-conditions').val(),
                confirmationPage: $('#doi-confirmation-page').val(),
                recipient: $('#doi-recipient').val(),
                sender: $('#doi-sender').val(),
                senderName: $('#doi-sender-name').val(),
                subject: $('#doi-subject').val(),
                template: $('#doi-template').val(),
                body: $('#doi-body').val(),
                consentText: $('#doi-consent-text').val(),
                fieldMapping: this.collectFieldMapping()
            };

            /**
             * Trigger event for extensions (e.g., Pro version) to add additional fields to settings.
             * Extensions should modify the settings object directly.
             *
             * @param {Object} settings The settings object to be saved.
             * @param {number} formId The current form ID.
             */
            $(document).trigger('doi:panel:beforesave', [settings, this.currentFormId]);

            // Clear previous errors before saving
            self.clearFieldErrors();
            $panel.addClass('saving');

            $.ajax({
                url: doiFormSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'doi_save_form_settings',
                    nonce: doiFormSettings.nonce,
                    form_id: this.currentFormId,
                    settings: JSON.stringify(settings)
                },
                success: function(response) {
                    $panel.removeClass('saving');

                    if (response.success) {
                        self.showToast(response.data.message || doiFormSettings.i18n.saved, 'success');

                        // Update toggle state in table
                        var $toggle = $('.doi-toggle-input[data-form-id="' + self.currentFormId + '"]');
                        $toggle.prop('checked', response.data.enabled);

                        self.closePanel();
                    } else {
                        // Clear previous field errors
                        self.clearFieldErrors();

                        var message = response.data.message || doiFormSettings.i18n.error;
                        var errors = response.data.errors || {};
                        var errorKeys = Object.keys(errors);

                        if (errorKeys.length > 0) {
                            // Build detailed error list
                            var errorHtml = '<strong>' + message + '</strong><ul class="doi-toast-errors">';
                            for (var i = 0; i < errorKeys.length; i++) {
                                errorHtml += '<li>' + errors[errorKeys[i]] + '</li>';
                            }
                            errorHtml += '</ul>';

                            self.showToast(errorHtml, 'error', true);

                            // Highlight fields with errors
                            self.highlightFieldErrors(errors);
                        } else {
                            self.showToast(message, 'error');
                        }
                    }
                },
                error: function(xhr) {
                    $panel.removeClass('saving');
                    self.showErrorFromResponse(xhr);
                }
            });
        },

        /**
         * Show a toast notification.
         *
         * @param {string}  message   The message to show (can contain HTML for errors).
         * @param {string}  type      The toast type (success/error).
         * @param {boolean} hasDetail Whether the toast contains detailed error info.
         */
        showToast: function(message, type, hasDetail) {
            // Remove any existing toasts
            $('.doi-toast').remove();

            var cssClass = 'doi-toast ' + type + (hasDetail ? ' doi-toast-detailed' : '');
            var closeBtn = hasDetail ? '<button class="doi-toast-close" type="button">&times;</button>' : '';
            var $toast = $('<div class="' + cssClass + '">' + closeBtn + message + '</div>');
            $('body').append($toast);

            // Close button handler
            $toast.on('click', '.doi-toast-close', function() {
                $toast.removeClass('show');
                setTimeout(function() { $toast.remove(); }, 300);
            });

            setTimeout(function() {
                $toast.addClass('show');
            }, 10);

            // Auto-hide: longer for detailed errors
            var duration = hasDetail ? 8000 : 3000;
            setTimeout(function() {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, duration);
        },

        /**
         * Parse XHR error response and show appropriate error message.
         *
         * @param {Object} xhr The XMLHttpRequest object from jQuery AJAX error.
         */
        showErrorFromResponse: function(xhr) {
            var self = this;

            try {
                var response = JSON.parse(xhr.responseText);
                if (response && response.data) {
                    var message = response.data.message || doiFormSettings.i18n.error;
                    var errors = response.data.errors || {};
                    var errorKeys = Object.keys(errors);

                    if (errorKeys.length > 0) {
                        // Build detailed error list
                        var errorHtml = '<strong>' + message + '</strong><ul class="doi-toast-errors">';
                        for (var i = 0; i < errorKeys.length; i++) {
                            errorHtml += '<li>' + errors[errorKeys[i]] + '</li>';
                        }
                        errorHtml += '</ul>';

                        self.showToast(errorHtml, 'error', true);
                        self.highlightFieldErrors(errors);
                        return;
                    } else if (message) {
                        self.showToast(message, 'error');
                        return;
                    }
                }
            } catch (e) {
                // JSON parse failed, fall through to generic error
            }

            self.showToast(doiFormSettings.i18n.error, 'error');
        },

        /**
         * Highlight form fields that have validation errors.
         *
         * @param {Object} errors Field name => error message mapping.
         */
        highlightFieldErrors: function(errors) {
            // Map server field names to DOM element IDs
            var fieldMap = {
                'recipient':        'doi-recipient',
                'sender':           'doi-sender',
                'senderName':       'doi-sender-name',
                'subject':          'doi-subject',
                'body':             'doi-body',
                'template':         'doi-template',
                'confirmationPage': 'doi-confirmation-page',
                'category':         'doi-category',
                'conditions':       'doi-conditions',
                'consentText':      'doi-consent-text'
            };

            for (var fieldName in errors) {
                if (!errors.hasOwnProperty(fieldName)) continue;

                var elementId = fieldMap[fieldName];
                if (!elementId) continue;

                var $field = $('#' + elementId);
                if ($field.length) {
                    $field.addClass('doi-field-error');

                    // Add error message below the field
                    var $errorMsg = $('<span class="doi-field-error-msg">' + errors[fieldName] + '</span>');
                    $field.closest('.option').find('.input').append($errorMsg);
                }
            }
        },

        /**
         * Clear all field error highlights.
         */
        clearFieldErrors: function() {
            $('.doi-field-error').removeClass('doi-field-error');
            $('.doi-field-error-msg').remove();
        },

        /**
         * Populate the field mapping grid.
         *
         * @param {Object} fields       Available form fields.
         * @param {Object} fieldMapping Current field mapping settings.
         */
        populateFieldMapping: function(fields, fieldMapping) {
            var $grid = $('#doi-field-mapping-grid');
            $grid.empty();

            var standardPlaceholders = doiFormSettings.standardPlaceholders || {};
            var fieldKeys = Object.keys(fields || {});
            var hasFields = fieldKeys.length > 0;

            // Check if standardPlaceholders is empty
            if (Object.keys(standardPlaceholders).length === 0) {
                $grid.append('<p class="doi-mapping-empty">Standard placeholders not loaded.</p>');
                return;
            }

            // Create mapping rows for each standard placeholder
            $.each(standardPlaceholders, function(placeholderKey, placeholderLabel) {
                var $row = $('<div class="doi-mapping-row"></div>');

                // Placeholder label
                var $label = $('<div class="doi-mapping-label"></div>');
                $label.append('<code>[' + placeholderKey + ']</code>');
                $label.append('<span class="doi-mapping-label-text">' + placeholderLabel + '</span>');
                $row.append($label);

                // Arrow
                $row.append('<span class="doi-mapping-arrow">→</span>');

                // Field select
                var $selectWrapper = $('<div class="doi-mapping-field"></div>');
                var $select = $('<select name="fieldMapping[' + placeholderKey + ']" class="doi-mapping-select"></select>');

                // Add empty option
                $select.append($('<option>', {
                    value: '',
                    text: doiFormSettings.i18n.notMapped || '-- Not mapped --'
                }));

                // Add form fields as options
                if (hasFields) {
                    $.each(fields, function(fieldName, fieldLabel) {
                        var displayText = fieldLabel !== fieldName ? fieldLabel + ' [' + fieldName + ']' : '[' + fieldName + ']';
                        $select.append($('<option>', {
                            value: fieldName,
                            text: displayText
                        }));
                    });
                }

                // Set current value if exists
                var currentValue = (fieldMapping && fieldMapping[placeholderKey]) ? fieldMapping[placeholderKey] : '';
                // Remove brackets if present
                if (currentValue) {
                    currentValue = currentValue.replace(/^\[|\]$/g, '');
                }
                $select.val(currentValue);

                $selectWrapper.append($select);
                $row.append($selectWrapper);

                $grid.append($row);
            });

            // Show info message if no fields available
            if (!hasFields) {
                $grid.append('<p class="doi-mapping-info" style="margin-top: 10px; color: #666; font-size: 12px;"><em>' + (doiFormSettings.i18n.noFieldsFound || 'Note: No form fields detected. You can still configure the mapping manually by entering field names.') + '</em></p>');
            }
        },

        /**
         * Collect field mapping values from the form.
         *
         * @return {Object} Field mapping object.
         */
        collectFieldMapping: function() {
            var mapping = {};

            $('#doi-field-mapping-grid select.doi-mapping-select').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();

                if (name && value) {
                    // Extract placeholder key from name (e.g., "fieldMapping[doi_email]" -> "doi_email")
                    var match = name.match(/fieldMapping\[([^\]]+)\]/);
                    if (match && match[1]) {
                        mapping[match[1]] = value;
                    }
                }
            });

            return mapping;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        DoiFormSettings.init();
    });

})(jQuery);
