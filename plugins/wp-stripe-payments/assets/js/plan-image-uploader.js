(function ($) {
    'use strict';

    var frame = null;
    var $input = $('#wp_sp_image');
    var $selectButton = $('#wp_sp_select_image');
    var $removeButton = $('#wp_sp_remove_image');
    var $preview = $('#wp_sp_image_preview');
    var $previewImage = $('#wp_sp_image_preview_img');

    if ($input.length === 0 || $selectButton.length === 0) {
        return;
    }

    function togglePreview(url) {
        if (url) {
            $previewImage.attr('src', url);
            $preview.show();
            $removeButton.show();
            return;
        }

        $previewImage.attr('src', '');
        $preview.hide();
        $removeButton.hide();
    }

    togglePreview($input.val());

    $selectButton.on('click', function (event) {
        event.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Select image',
            button: {
                text: 'Use this image'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment && attachment.url ? attachment.url : '';
            $input.val(url);
            togglePreview(url);
        });

        frame.open();
    });

    $removeButton.on('click', function (event) {
        event.preventDefault();
        $input.val('');
        togglePreview('');
    });
})(jQuery);
