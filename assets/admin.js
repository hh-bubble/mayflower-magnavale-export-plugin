/**
 * Mayflower Magnavale Export — Admin JavaScript
 *
 * Handles the SFTP connection test button on the settings page.
 * The manual export button has its own inline JS in the view template.
 *
 * @package MayflowerMagnavaleExport
 */

jQuery(document).ready(function($) {

    /**
     * Test SFTP Connection button
     */
    $('#mme-test-sftp').on('click', function() {
        var $btn    = $(this);
        var $result = $('#mme-test-result');

        $btn.prop('disabled', true).text('Testing...');
        $result.text('').css('color', '');

        $.ajax({
            url: mme_ajax.url,
            type: 'POST',
            data: {
                action: 'mme_test_sftp',
                nonce: mme_ajax.sftp_nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.text('✓ ' + response.message).css('color', 'green');
                } else {
                    $result.text('✗ ' + response.message).css('color', 'red');
                }
                $btn.prop('disabled', false).text('Test SFTP Connection');
            },
            error: function() {
                $result.text('✗ Request failed — check server logs.').css('color', 'red');
                $btn.prop('disabled', false).text('Test SFTP Connection');
            }
        });
    });

});
