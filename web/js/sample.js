$(function() {

    /**
     * Drag
     */
    var dropArea = $(window);
    var dropLayer = $('.drop');
    var dragEvent = '';

    dropArea.on(
        'dragstart',
        function(e) {
            e.preventDefault();
        }
    );

    dropArea.on(
        'dragover',
        function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
            dragEvent = 'dragover';
        }
    );

    dropArea.on(
        'dragenter',
        function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropLayer.show();
        }
    );

    dropArea.on(
        'dragleave',
        function(e) {
            e.preventDefault();
            e.stopPropagation();
            if(dragEvent == 'dragover') {
                dropLayer.hide();
            }
            dragEvent = 'dragleave';
        }
    );

    /**
     * Drop
     */
    dropArea.on(
        'drop',
        function(e) {
            dropLayer.hide();
            if(e.originalEvent.dataTransfer) {
                if(e.originalEvent.dataTransfer.files.length) {
                    e.preventDefault();
                    e.stopPropagation();

                    /**
                     * Upload files
                     */
                    console.log('Files dropped in: e.originalEvent.dataTransfer.files');
                    /**
                     * Actions
                     *
                     * Hide Library area
                     * Show file upload form
                     */

                }
            }
        }
    );

});