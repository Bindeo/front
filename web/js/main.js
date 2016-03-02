// Publish/subscribe pattern
(function($) {

    var o = $({});

    $.subscribe = function() {
        o.on.apply(o, arguments);
    };

    $.unsubscribe = function() {
        o.off.apply(o, arguments);
    };

    $.publish = function() {
        o.trigger.apply(o, arguments);
    };

}(jQuery));

var main = (function() {
    var init = function() {
        subscriptors();
        $('body').on('submit', 'form[data-action="ajax-form"]', sendForm);
    };

    var subscriptors = function() {
        /**
         * General notifications
         * @example: $.publish(true, 'Well done!')
         */
        $.subscribe('main.notifications', function(event, success, message) {
            var obj = $('#notifications');
            obj.find('.alert').removeClass('alert-success alert-danger').addClass(success ? 'alert-success' : 'alert-danger').html(message);
            obj.show();
        });

        /**
         * Listen to the sending event
         */
        $.subscribe('sending.forms', function(event, form, status) {
            form.find('[type="submit"]').prop("disabled", status);
        });
    };

    /**
     * Global method for sending ajax requests
     * @param url
     * @param params
     * @returns Deferred
     */
    var sendRequest = function(url, params) {
        return $.ajax({
            url     : url,
            data    : params,
            type    : "post",
            dataType: "json",
            async   : true
        });
    };

    /**
     * Send a standard form via ajax
     * @returns Promise
     */
    var sendForm = function(event) {
        event.preventDefault();
        // Publish the start sending status
        var form = $(this);

        var promise = $.when($.ajax({
            type      : "post",
            dataType  : "json",
            data      : form.serialize(),
            async     : true,
            beforeSend: function() {
                $.publish('sending.forms', [form, true]);
            }
        }));

        return promise.then(
            // Done
            function(response) {
                $.publish('sending.forms', [form, false]);

                // Process the response
                if(response.result.success) {
                    // Successful
                    if(response.result.redirect) {
                        // Redirect
                        window.location.href = response.result.redirect;
                    } else if(response.result.message) {
                        // General notification message
                        $.publish('main.notifications', [true, response.result.message]);
                    } else if(response.result.html) {
                        $('section[data-type="main"]').replaceWith(response.result.html);
                    }
                } else {
                    // Errors
                    form.replaceWith(response.result.form);
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
        init       : init,
        sendRequest: sendRequest
    };
})();

$(document).ready(function() {
    main.init();
});