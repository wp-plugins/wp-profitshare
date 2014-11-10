jQuery().ready(function($) {
    var tip_display = $('input#tip_display:checked').val();
    if (tip_display == 'y') {
        toggleTips(1);
    }

    var image_custom_uploader, attachment;
    $('.upload_image_button').click(function(e) {
        e.preventDefault();

        //If the uploader object has already been created, reopen the dialog
        if (image_custom_uploader) {
            image_custom_uploader.open();
            return;
        }

        //Extend the wp.media object
        image_custom_uploader = wp.media.frames.file_frame = wp.media({
            title: 'Upload image',
            button: {
                text: 'Choose image'
            },
            multiple: false
        });

        //When a file is selected, grab the URL and set it as the text field's value
        image_custom_uploader.on('select', function() {
            attachment = image_custom_uploader.state().get('selection').first().toJSON();
            
            if (attachment.url != '') {
                $('.upload_image_input').val(attachment.url);
            }
        });

        //Open the uploader dialog
        image_custom_uploader.open();
    });
});

function toggleTips(show) {
    if (show == 1) {
        jQuery('.tip_display_1').removeClass('hide_display');
    } else {
        jQuery('.tip_display_1').addClass('hide_display');
    }
}


