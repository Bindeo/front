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
            $('div[data-name="' + name + '"] .file').hide();
            $('#' + name + '_name').val(data.originalFiles[0].name);
            $('#' + name + '_fileOrigName').val(data.originalFiles[0].name);
            $('#' + name).show();
            $('#' + name + '_done').html('');
        });

        /**
         * Control drop area
         */
        $.subscribe('drop_zone.files', function(event, status) {
            if(status) $('.drop').show();
            else $('.drop').hide();
        });

        /**
         * Files uploaded
         */
        $.subscribe('upload.files', function(event, result) {
            $('.load:visible').hide();

            if(result.success == true) {
                $('#' + result.name + '_path').val(result.path);
                $('#' + result.name + '_done').replaceWith(result.html);
            } else {
                $('#' + result.name).hide();
                $('#' + result.name + '_path').val('');
                $('#' + result.name + '_fileOrigName').val('');
                $('#' + result.name + '_done').html('');
                $.publish('errors.files', [result.name, result.error]);
            }
        });

        /**
         * Manage fileupload error layer
         */
        $.subscribe('errors.files', function(event, name, type) {
            var errordiv = $('[data-action="fileupload"][data-name="' + name + '"]').parent().find('[data-type="error-size"]');
            errordiv.find('li').hide();

            if(type == 'freespace') {
                errordiv.find('li[data-name="freespace"]').show();
                errordiv.slideDown();
            } else if(type == 'filesize') {
                errordiv.find('li[data-name="filesize"]').show();
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
     * Initialize fileuploads areas
     * @returns {boolean}
     */
    var fileUpload = function() {
        if(!$.fn.fileupload) return false;

        // Register subscribers of file upload process
        fileSubscribers();

        $('[data-action="fileupload"]').each(function() {
            var obj = $(this);
            var progressBar = obj.find('.load .progress-bar');

            obj.fileupload({
                sequentialUploads: true,
                maxNumberOfFiles : 1,
                autoUpload       : true,
                dragover         : function(e, data) {
                    $.publish('drop_zone.files', [true]);
                },
                add              : function(e, data) {
                    $.publish('drop_zone.files', [false]);

                    // Check if the user is confirmed
                    if(obj.attr('data-confirmed') != 1) {
                        data.abort();
                        window.location.href = '/data/upload';
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
                    data.submit();

                    return false;
                },
                start            : function(e, data) {
                    progressBar.css('width', '0%').html('0%');
                    obj.find('.load').show();
                },
                always           : function(e, data) {
                    $.publish('upload.files', [data.result]);
                },
                progress         : function(e, data) {
                    // Modify the progress bar
                    var progress = parseInt(data.loaded / data.total * 100, 10);
                    progressBar.css('width', progress + '%').html(progress + '%');
                }
            });
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