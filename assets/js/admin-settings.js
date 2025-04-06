jQuery(document).ready(function($) {
    'use strict'; // Use strict mode

    const $testEmailButton = $('#send_test_email');
    const $testEmailInput = $('#test_email');
    const $testResultDiv = $('#test_result');

    const $checkConnectionButton = $('#check_connection');
    const $connectionStatusDiv = $('#connection_status');

    $testEmailButton.on('click', function(e) {
        e.preventDefault();
        const testEmail = $testEmailInput.val();
        
        if (!testEmail || !testEmail.includes('@')) { // Simple check
            $testResultDiv.html('<div class="notice notice-error is-dismissible"><p>' + wpResendMailer.error_no_email + '</p></div>');
            return;
        }

        $testEmailButton.prop('disabled', true);
        $testResultDiv.html('<div class="notice notice-info is-dismissible"><p>' + wpResendMailer.sending_text + '</p></div>');

        $.ajax({
            url: wpResendMailer.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_resend_mailer_test',
                email: testEmail,
                nonce: wpResendMailer.nonce
            },
            success: function(response) {
                if (response.success) {
                    $testResultDiv.html('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>');
                } else {
                    const errorMessage = response.data || wpResendMailer.error_generic;
                    $testResultDiv.html('<div class="notice notice-error is-dismissible"><p>' + errorMessage + '</p></div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                $testResultDiv.html('<div class="notice notice-error is-dismissible"><p>' + wpResendMailer.error_generic + '</p></div>');
            },
            complete: function() {
                $testEmailButton.prop('disabled', false);
            }
        });
    });

    $checkConnectionButton.on('click', function(e) {
        e.preventDefault();

        // Disable button and show checking message
        $checkConnectionButton.prop('disabled', true);
        $connectionStatusDiv.html('<div class="notice notice-info is-dismissible"><p>' + wpResendMailer.checking_text + '</p></div>');

        $.ajax({
            url: wpResendMailer.ajax_url, // Use localized variable
            type: 'POST',
            data: {
                action: 'wp_resend_mailer_health_check', // Correct action name
                nonce: wpResendMailer.health_check_nonce // Use localized health check nonce
            },
            success: function(response) {
                if (response.success) {
                    $connectionStatusDiv.html('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>');
                } else {
                    // Display error message from server or a generic one
                    const errorMessage = response.data || wpResendMailer.error_generic;
                    $connectionStatusDiv.html('<div class="notice notice-error is-dismissible"><p>' + errorMessage + '</p></div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                $connectionStatusDiv.html('<div class="notice notice-error is-dismissible"><p>' + wpResendMailer.error_generic + '</p></div>');
            },
            complete: function() {
                // Re-enable the button regardless of success or error
                $checkConnectionButton.prop('disabled', false);
            }
        });
    });

    // Allow dismissing notices
    $testResultDiv.on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').remove();
    });
    $connectionStatusDiv.on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').remove();
    });
}); 