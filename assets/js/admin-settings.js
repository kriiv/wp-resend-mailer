jQuery(document).ready(function($) {
    'use strict'; // Use strict mode

    const $testEmailButton = $('#send_test_email');
    const $testEmailInput = $('#test_email');
    const $testResultDiv = $('#test_result');

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

    $testResultDiv.on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').remove();
    });
}); 