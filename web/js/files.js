var files = (function() {
    var init = function() {
        // Setup the fileupload object
        fileUpload();
        $('body').on('click', '[data-action="remove-uploaded-file"]', removeUploadedFile);
        $('body').on('submit', 'form[name="upload_file"]', sendFormFile);
        $('body').on('click', 'form[name="upload_file"] a[name="options"]', uploadOptions);
        $('body').on('click', 'form[name="upload_file"] [data-action="add-signer"]', addSigner);
        $('body').on('click', 'form[name="upload_file"] [data-action="remove-signer"]', removeSigner);
        // Library
        $('body').on('click', '[data-id="fileFilters"] ul li a', chooseFilter);
        $('body').on('keyup', '[data-id="fileFilters"] input', function(e) {
            if(e.keyCode == 13) {
                chooseFilter();
            }
        });
        // Sign files
        $('body').on('click', '[data-target="#modal-sign"]', requestCode);
        $('body').on('submit', 'form[name="signer"]', sendFormSigner);

        pagination();
    };

    /**
     * Define subscriber for files
     */
    var fileSubscribers = function() {
        /**
         * Files added
         */
        $.subscribe('add.files', function(event, name, data) {
            $('.alert').hide();

            if(name == "upload_file") {
                $('div[data-name="' + name + '"] .file').hide();
                $('#' + name + '_fileOrigName').val(data.files[0].name);
                $('#modal-prepare [data-id="to-sign"]').hide();
                $('#modal-prepare').modal('show');
                $('#' + name + '_done').html('');
            }

            // Loading bar
            var loadOrig = $(".load:hidden");
            var newBar = loadOrig.clone();
            newBar.attr('id', data.files[0].name).show();
            loadOrig.after(newBar);
        });

        /**
         * Control drop area
         */
        $.subscribe('drop_zone.files', function(event, status) {
            if(status) $('.drop').show();
            else $('.drop').hide();
        });

        /**
         * Files uploaded, general subscriber
         */
        $.subscribe('upload.files', function(event, name, data) {
            var result = data.result;
            $('[id="' + data.files[0].name + '"').remove();

            if(!result.success) {
                if(result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    $.publish('errors.files', [result.name, result.error]);
                }
            }
        });

        /**
         * Files uploaded, upload_file subscriber
         */
        $.subscribe('upload.files', function(event, name, data) {
            if(name == "upload_file") {
                var result = data.result;
                if(result.success) {
                    $('#' + result.name + '_path').val(result.path);
                    $('#' + result.name + '_done').replaceWith(result.html);
                } else {
                    $('#modal-prepare').modal('hide');
                    $('#' + result.name + '_path').val('');
                    $('#' + result.name + '_fileOrigName').val('');
                    $('#' + result.name + '_done').html('');
                }
            }
        });

        /**
         * Files uploaded, upload_file_bulk subscriber
         */
        $.subscribe('upload.files', function(event, name, data) {
            if(name == "upload_file_bulk") {
                var result = data.result;
                if(result.success) {
                    // Add the prototype
                    var container = $('#upload_file');
                    var number = container.find('li').length;
                    var prototype = $(container.attr('data-prototype').replace(/__name__/g, number));

                    // Set file data
                    prototype.find('[id="bulk_transaction_files_' + number + '_path"]').val(result.path);
                    prototype.find('[id="bulk_transaction_files_' + number + '_fileOrigName"]').val(result.filename);
                    prototype.find('span[data-name="fileOrigName"]').html(result.filename);

                    // Append the prototype
                    container.append(prototype);

                    // Show submit
                    container.parent().find('[type="submit"]').show();
                }
            }
        });

        /**
         * Files uploaded, upload_file_bulk_verify subscriber
         */
        $.subscribe('upload.files', function(event, name, data) {
            if(name == "upload_file_bulk_verify") {
                var result = data.result.result;
                if(result.success && result.html) {
                    $('section[data-type="main"]').replaceWith(result.html);
                }
            }
        });

        /**
         * Manage fileupload error layer
         */
        $.subscribe('errors.files', function(event, name, type) {
            var errordiv = $('[data-action="fileupload"][data-name="' + name + '"]').find('[data-type="error-size"]');
            errordiv.find('li').hide();

            if(type == 'freespace') {
                errordiv.find('li[data-name="freespace"]').show();
                errordiv.slideDown();
            } else if(type == 'filesize') {
                errordiv.find('li[data-name="filesize"]').show();
                errordiv.slideDown();
            } else if(type == 'maxfiles') {
                errordiv.find('li[data-name="maxfiles"]').show();
                errordiv.slideDown();
            } else {
                errordiv.hide();
            }
        });

        /**
         * Manage files list filter
         */
        $.subscribe('listFilters.files', function(event) {
            if($('#fileList').length) {
                var params = '';
                $('[data-id="fileFilters"] li.active').each(function() {
                    if($(this).attr('data-type') != undefined) {
                        if(params != '') params += '&';
                        params += $(this).attr('data-type') + '=' + $(this).attr('data-value');
                    }
                });

                // Text
                var text = $('[data-id="fileFilters"] input[data-type="name"]');
                if(text.val()) {
                    if(params != '') params += '&';
                    params += 'name=' + text.val();
                }

                // Ajax request to render new files list
                $.when(main.sendRequest('/data/library', params)).done(function(response) {
                    if(response.result.success) {
                        var fileList = $('#fileList');
                        fileList.hide().html(response.result.html);
                        fileList.slideDown('fast');
                        paginator.setCurrentPage(1);
                    }
                });
            }
        });

        /**
         * Manage space formatted numbers of the user
         */
        $.subscribe('space.files', function(event, result) {
            $('[data-id="freespace"]').html(result.freespace);
            $('[data-id="usedspace"]').html(result.usedspace);
        });
    };

    /**
     * Deferred generator of fileUpload
     * @param obj
     * @returns {*}
     */
    var fileUploadDeferred = function(obj) {
        obj.fileupload({
            autoUpload: true
        });

        return obj;
    };

    /**
     * Initialize fileuploads areas
     * @returns {boolean}
     */
    var fileUpload = function() {
        if(!$.fn.fileupload) return false;

        // Register subscribers of file upload process
        fileSubscribers();

        $('[data-action="fileupload"]').each(function() {
            var obj = $(this);
            var numFiles = obj.attr('data-numfiles');
            if(!numFiles) numFiles = 1;

            // Create the promise
            var promise = fileUploadDeferred(obj);

            // Assign events to promise
            promise.on('fileuploadadd', function(e, data) {
                // File added
                $.publish('drop_zone.files', [false]);

                // Check if the user is confirmed
                if(obj.attr('data-confirmed') != 1) {
                    data.abort();
                    window.location.href = '/data/upload';
                }

                // Check maximum number of files
                if(data.originalFiles.length > numFiles) {
                    data.abort();
                    $.publish('errors.files', [obj.attr('data-name'), 'maxfiles']);
                    return false;
                }

                // Check the max size
                var filesize = obj.attr('data-maxfilesize');
                if(filesize > 0 && data.files[0].size > filesize) {
                    data.abort();
                    $.publish('errors.files', [obj.attr('data-name'), 'filesize']);
                    return false;
                }

                // Check free storage left
                var freespace = obj.attr('data-freespace');
                if(freespace > 0 && data.files[0].size > freespace) {
                    data.abort();
                    $.publish('errors.files', [obj.attr('data-name'), 'freespace']);
                    return false;
                }

                // No errors
                $.publish('errors.files', [obj.attr('data-name')]);
                $.publish('add.files', [obj.attr('data-name'), data]);
            }).on('fileuploadalways', function(e, data) {
                // Finish file upload
                $.publish('upload.files', [obj.attr('data-name'), data]);
            }).on('fileuploadprogress', function(e, data) {
                // Modify the progress bar
                var progress = parseInt(data.loaded / data.total * 100, 10);
                $('[id="' + data.files[0].name + '"').find('.progress-bar').css('width', progress + '%').html(progress + '%');
            });
        });

        // Catch dragover event
        $(window).on('dragover', function(e, data) {
            $.publish('drop_zone.files', [true]);
        });

        // Catch dragleave event
        $('body').on('dragleave', '.drop', function(e, data) {
            $.publish('drop_zone.files', [false]);
        });
    };

    /**
     * Remove a file in the upload page
     * @returns {boolean}
     */
    var removeUploadedFile = function() {
        var name = $(this).parents('[data-action="fileupload"]').attr('data-name');

        // Clean all related fields
        $('#' + name + '_done').html('');
        $('form[name="' + name + '"]').find('input[name="' + name + '[path]"]').val('');
        $('div[data-name="' + name + '"] .file').show();

        return false;
    };

    /**
     * Send fileupload form
     * @param event
     */
    var sendFormFile = function(event) {
        event.preventDefault();
        main.sendForm($(this)).done(function(response) {
            if(response.result.success) {
                $(".modal-backdrop:visible").hide();
                $.publish('listFilters.files');
                $.publish('space.files', response.result);
            }
        });
    };

    /**
     * Choose filters in library
     */
    var chooseFilter = function(event) {
        var refresh = false;

        if($(this)[0] == $(window)[0]) {
            refresh = true;
        } else {
            event.preventDefault();
            if(!$(this).parent().hasClass('active')) {
                // Active the filter
                $(this).parents('ul').find('.active').removeClass('active');
                $(this).parent().addClass('active');
                refresh = true;
            }
        }

        // Publish the result
        if(refresh) $.publish('listFilters.files');
    };

    /**
     * Initialize paginator
     */
    var pagination = function() {
        paginator.init($(window), '/data/library', function(page) {
            var params = 'page=' + page;
            $('[data-id="fileFilters"] li.active').each(function() {
                if($(this).attr('data-type') != undefined) {
                    params += '&' + $(this).attr('data-type') + '=' + $(this).attr('data-value');
                }
            });

            // Text
            var text = $('[data-id="fileFilters"] input[data-type="name"]');
            if(text.val()) {
                if(params != '') params += '&';
                params += 'name=' + text.val();
            }

            return params;
        });
    };

    /**
     * Add new signer
     */
    var addSigner = function() {
        // Get prototype and signers number
        var container = $('div[data-id="to-sign"] ul[data-prototype]');
        var number = container.find('>li').length;

        // Instantiate prototype
        var prototype = $(container.attr('data-prototype').replace(/__name__/g, number));

        // Append the prototype
        container.append(prototype);
        
        // If we are in development, default country 

        // Initialize mobile prefix plugin in phone fields
        prototype.find('input[data-name="mobile-phone"]').intlTelInput({
            geoIpLookup: function(callback) {
                $.get("http://ipinfo.io", function() {}, "jsonp").always(function(resp) {
                    var countryCode = (resp && resp.country) ? resp.country : "";
                    callback(countryCode);
                });
            },
            initialCountry: "es",
            utilsScript: "/libs/intl-tel-input_8.5.2/js/utils.js"
        });

        return false;
    };

    /**
     * Remove signer
     */
    var removeSigner = function() {
        var li = $(this).parents('li');

        // Subtract one element to ids
        li.nextAll().each(function() {
            // We need to replace all ids in element row to
            var oldId = $(this).prevAll().length;
            var newId = oldId - 1;
            var reg = new RegExp("_" + oldId + "_", "g");
            var reg2 = new RegExp("\\[" + oldId + "\\]", "g");
            var html = $(this).html().replace(reg, '_' + newId + '_').replace(reg2, '[' + newId + ']');
            $(this).html(html);
        });

        // Remove element
        li.remove();

        return false;
    };

    /**
     * Options to upload the file
     */
    var uploadOptions = function(event) {
        event.preventDefault();

        // Set selected mode in form
        var mode = $(this).attr('data-mode');
        $('#upload_file_mode').val(mode);

        if(mode == 'N') {
            // Send form
            $(this).parents('form').submit();
        } else {
            // Remove previous signers
            $('div[data-id="to-sign"] ul[data-prototype]').attr('data-signers', 0).html('');

            // Types of signatures
            var type = $(this).attr('data-type');
            $('#upload_file_signType').val(type);

            if(type == 'A' || type == 'O') {
                // To sign
                $(this).parents('form').find('[data-id="to-sign"]').show();

                // Add signer
                addSigner();
            } else {
                // Submit form
                $(this).parents('form').submit();
            }
        }

        // Hide options
        $('div[data-name="upload-options"]').hide();
    };

    /**
     * Request via ajax call a new signature code generation
     */
    var requestCode = function(event) {
        main.sendRequest('/ajax/generate-sign-code/' + $(this).attr('data-token'));
    };

    /**
     * Send signer form
     * @param event
     */
    var sendFormSigner = function(event) {
        event.preventDefault();
        main.sendForm($(this)).done(function(response) {
            if(response.result.success) {
                $(".modal-backdrop").remove();
            }
        });
    };

    // Public methods
    return {
        init: init
    };
})();

$(document).ready(function() {
    files.init();
});