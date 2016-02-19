$(function() {

    /**
     * Drag
     */
    var dropArea = $(document);
    var dropLayer = $('.drop');
    var dropLastEvent = '';

    dropArea.on(
        'dragover',
        function(e) {
            dropLastEvent = 'dragover';
            e.preventDefault();
            e.stopPropagation();
        }
    );

    dropArea.on(
        'dragenter',
        function(e) {
            if($(e.target).attr('id') != 'drop-area') {
                dropLastEvent = 'dragenter';
                e.preventDefault();
                e.stopPropagation();
                if($(e.target).prop('tagName') == 'HTML') {
                    dropLayer.show();
                }
            }
        }
    );

    dropArea.on(
        'dragleave',
        function(e) {
            e.preventDefault();
            e.stopPropagation();
            if(dropLastEvent == 'dragover') {
                dropLayer.hide();
            }
            dropLastEvent = 'dragleave';
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
                }
            }
        }
    );

});