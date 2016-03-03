var files = (function() {
    var init = function() {
        // Setup the fileupload object
        fileUpload();
        $('body').on('click', '[data-action="remove-uploaded-file"]', removeUploadedFile);
        $('body').on('change', '#pre_upload_email', showPassword);
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
                dragleave        : function(e, data) {
                    $.publish('drop_zone.files', [false]);
                },
                add              : function(e, data) {
                    $.publish('drop_zone.files', [false]);

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

        return false;
    };

    var showPassword = function() {
        $(this).parents('form').find('input[type="password"]:hidden')/*.attr('required', 'required')*/.parent().show();
    };

    // Public methods
    return {
        init: init
    };
})();

$(document).ready(function() {
    files.init();
});