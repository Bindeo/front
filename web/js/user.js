/**
 * User functionality
 */
var user = (function() {
    var init = function() {
        $('body').on('submit', 'form[name="close-account"]', closeAccount);
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

    // Public methods
    return {
        init: init
    };
})();

$(document).ready(function() {
    user.init();
});