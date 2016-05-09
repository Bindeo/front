/**
 * User functionality
 */
var users = (function() {
    var init = function() {
        $('body').on('submit', 'form[name="close-account"]', closeAccount);
        $('body').on('change', '#pre_upload_email,#change_identity_value', showPassword);
        $('body').on('click', 'a[data-target="#modal-mail-verify"]', checkConfirmed);
        $('body').on('click', '#resend-confirmation', resendConfirmation);
        $('body').on('submit', '#change-email', changeEmail);
    };

    /**
     * Close the logged user account
     * @param event
     */
    var closeAccount = function(event) {
        event.preventDefault();
        var form = $(this);

        // Hide errors
        form.find('.form-group').removeClass('has-error');
        form.find('[data-name="password[error]"]').hide();

        // Create the promise
        var promise = main.sendSimpleForm(form);

        promise.then(
            // Done
            function(response) {
                $.publish('sending.forms', [form, false]);

                // Process the response
                if(response.result.success) {
                    // Successful
                    window.location.href = response.result.url;
                } else {
                    // Errors
                    form.find('.form-group').addClass('has-error');
                    form.find('[data-name="password[error]"]').show();
                }
            },
            // Fail
            function(response) {
                $.publish('sending.forms', [form, false]);
            }
        );
    };

    var showPassword = function() {
        $(this).parents('form').find('input[type="password"]:hidden').attr('required', 'required').parent().show();
    };

    /**
     * Check again if user is confirmed
     */
    var checkConfirmed = function() {
        var url = $(this).attr('data-url');
        main.sendRequest('/ajax/unconfirmed/check-confirmed').done(
            // Done
            function(response) {
                if(response.result.success) {
                    window.location.href = url;
                }
            }
        );
    };

    /**
     * Resend confirmation email
     */
    var resendConfirmation = function() {
        var id = $(this).attr('id');
        main.sendRequest('/ajax/unconfirmed/resend-token').done(function(response) {
            $.publish('main.notifications', [response.result.success, $('span[data-id="' + id + '"]').html()]);
        });

        return false;
    };

    /**
     * Change unconfirmed email or resend a verification email
     */
    var changeEmail = function() {
        var id = $(this).attr('id');
        var div = $('#field-email').parent();
        div.removeClass('has-error');
        div.find('span').hide();

        main.sendRequest('/ajax/unconfirmed/change-email', 'e='+encodeURIComponent($('#field-email').val())).done(function(response) {
            if (response.result.success) {
                $('#modal-mail-verify').modal('hide');
                $.publish('main.notifications', [response.result.success, $('span[data-id="resend-confirmation"]').html()]);
            } else {
                div.addClass('has-error');
                div.find('span[data-type="error"]').show();
                div.find('span[data-type="error-'+response.result.error+'"]').show();
            }
        });

        return false;
    };

    // Public methods
    return {
        init: init
    };
})();

$(document).ready(function() {
    users.init();
});