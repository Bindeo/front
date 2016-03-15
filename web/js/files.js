var files = (function() {
    var init = function() {
        // Setup the fileupload object
        fileUpload();
        $('body').on('click', '[data-action="remove-uploaded-file"]', removeUploadedFile);
        $('body').on('submit', 'form[name="upload_file"]', sendFormFile);
        // Library
        $('body').on('click', '[data-id="fileFilters"] ul li a', chooseFilter);
        $('body').on('keyup', '[data-id="fileFilters"] input', function(e) {
            if(e.keyCode == 13) {
                chooseFilter();
            }
        });
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

            if(name != "upload_file_bulk") {
                $('div[data-name="' + name + '"] .file').hide();
                $('#' + name + '_name').val(data.files[0].name);
                $('#' + name + '_fileOrigName').val(data.files[0].name);
                $('#' + name).show();
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

            if(result.success != true) {
                $.publish('errors.files', [result.name, result.error]);
            }
        });

        /**
         * Files uploaded, upload_file subscriber
         */
        $.subscribe('upload.files', function(event, name, data) {
            if(name == "upload_file") {
                var result = data.result;
                if(result.success == true) {
                    $('#' + result.name + '_path').val(result.path);
                    $('#' + result.name + '_done').replaceWith(result.html);
                } else {
                    $('#' + result.name).hide();
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
                if(result.success == true) {
                    // Add the prototype
                    var container = $('#upload_file');
                    var number = container.find('li').length;
                    var prototype = $(container.attr('data-prototype').replace(/__name__/g, number));

                    // Set file data
                    prototype.find('[id="bulk_transaction_files_'+number+'_path"]').val(result.path);
                    prototype.find('[id="bulk_transaction_files_'+number+'_fileOrigName"]').val(result.filename);
                    prototype.find('span[data-name="fileOrigName"]').html(result.filename);

                    // Append the prototype
                    container.append(prototype);

                    // Show submit
                    container.parent().find('[type="submit"]').show();
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
            var load = obj.find('.load');
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
        $('#' + name).hide();
        $('#' + name + '_name').val('');
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

    // Public methods
    return {
        init: init
    };
})();

$(document).ready(function() {
    files.init();
});