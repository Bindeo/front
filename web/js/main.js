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
        $('body').on('submit', 'form[data-action="ajax-form"]', sendStandardForm);
        $('body').on('click', 'a[data-locale]', changeLocale);
        $('body').on('click', 'a[data-action="dismiss-cookies"]', dismissCookies);
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

        /**
         * Storage and stamps data
         */
        $.subscribe('freespace.user', function(event, data) {
            $('#freespace').html(data / 1024);
            $('[data-freespace]').attr('data-freespace', data * 1024 * 1024);
        });
        $.subscribe('usedspace.user', function(event, data) {
            $('#usedspace').html(data);
        });
        $.subscribe('freestamps.user', function(event, data) {
            $('#freestamps').html(data);
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
     * Send a standard form
     * @param event
     */
    var sendStandardForm = function(event) {
        event.preventDefault();
        sendForm($(this));
    };

    /**
     * Create the promise of sending a form
     * @param form
     * @returns Promise
     */
    var sendSimpleForm = function(form) {
        // Publish the start sending status

        return $.when($.ajax({
            url       : form.attr('action'),
            type      : "post",
            dataType  : "json",
            data      : form.serialize(),
            async     : true,
            beforeSend: function() {
                $.publish('sending.forms', [form, true]);
            }
        }));
    };

    /**
     * Send a standard form via ajax
     * @returns Promise
     */
    var sendForm = function(form) {
        // Create the promise
        var promise = sendSimpleForm(form);

        promise.then(
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
                    } else {
                        form.replaceWith(response.result.form);
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

        return promise;
    };

    /**
     * Change not logged users locale
     */
    var changeLocale = function(event) {
        event.preventDefault();

        $.when(sendRequest($(this).attr('data-url'), 'l=' + $(this).attr('data-locale'))).done(function(response) {
            window.location.reload();
        });
    };

    var dismissCookies = function(event) {
        $(this).parents('.cookies').remove();
        return false;
    };

    // Public methods
    return {
        init          : init,
        sendSimpleForm: sendSimpleForm,
        sendForm      : sendForm,
        sendRequest   : sendRequest
    };
})();

/**
 * Scroll paginator class
 */
var paginator = (function() {
    var promise;
    /**
     * Current page of the paginator
     */
    var currentPage;

    var dataFunc;
    var dataUrl;
    var paginatorContainer;
    var loading;

    var init = function(container, url, func) {
        paginatorContainer = container[0] == $(window)[0] ? $('body') : container;
        currentPage = 1;
        dataFunc = func;
        dataUrl = url;

        loading = paginatorContainer.find('[data-id="paginator"]');

        subscriptors();
        container.scroll(function() {
            pagination();
        });
    };

    var setCurrentPage = function(val) {
        currentPage = val;
    };

    var subscriptors = function() {
        /**
         * Paginator loading
         */
        $.subscribe('loading.paginator', function(event, status) {
            if(status) {
                paginatorContainer.find('[data-id="paginator"]').show();
            } else {
                paginatorContainer.find('[data-id="paginator"]').hide();
            }
        });
    };

    /**
     * @return Promise
     */
    var pagination = function() {
        if(!paginatorContainer.find('[data-id="paginator"]').length) return null;

        var paginator = paginatorContainer.find('[data-id="paginator"]').prev();

        // Detect when we need to paginate
        if(paginator.offset().top <= $(window).scrollTop() + window.innerHeight + 200) {
            if(promise) return promise;
            else {
                // Initialize the deferred and the promise
                var deferred = $.Deferred();
                promise = deferred.promise();
                promise.done(function() {
                    currentPage += 1;
                    promise = null;
                    console.log(currentPage);
                }).fail(function() {
                    currentPage -= 1;
                });

                // Paginate
                $.publish('loading.paginator', true);
                data = dataFunc(currentPage + 1);

                // Ajax call
                $.when(main.sendRequest(dataUrl, data)).then(
                    // done
                    function(response) {
                        if(response.result.success) {
                            // Añadimos el html devuelto
                            paginatorContainer.find('[data-id="paginator"]').replaceWith(response.result.html);
                            deferred.resolve();
                        } else {
                            deferred.reject();
                        }

                    },
                    // fail
                    function(response) {
                        deferred.reject();
                    }
                );

                return promise;
            }
        }
    };

    // Public methods
    return {
        init          : init,
        setCurrentPage: setCurrentPage
    };
})();

$(document).ready(function() {
    main.init();
});