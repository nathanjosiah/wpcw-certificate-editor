jQuery(function($) {
	var $text = $('#certificate-text-placement');
	if(!$text.length) {
		// not in edit screen
		return;
	}
	var $preview = $('#certificate-preview-image');

	$('input#certificate-media-manager').click(function (e) {
		e.preventDefault();
		var image_frame;
		if (image_frame) {
			image_frame.open();
		}
		// Define image_frame as wp.media object
		image_frame = wp.media({
			title: 'Select Media',
			multiple: false,
			library: {
				type: 'image',
			}
		});

		image_frame.on('close', function () {
			// On close, get selections and save to the hidden input
			// plus other AJAX stuff to refresh the image preview
			var selection = image_frame.state().get('selection');
			var gallery_ids = new Array();
			var my_index = 0;
			selection.each(function (attachment) {
				gallery_ids[my_index] = attachment['id'];
				my_index++;
			});
			var ids = gallery_ids.join(",");
			jQuery('input#certificate-image-id').val(ids);
			Refresh_Image(ids);
		});

		image_frame.on('open', function () {
			// On open, get the id from the hidden input
			// and select the appropiate images in the media manager
			var selection = image_frame.state().get('selection');
			ids = jQuery('input#certificate-image-id').val().split(',');
			ids.forEach(function (id) {
				attachment = wp.media.attachment(id);
				attachment.fetch();
				selection.add(attachment ? [attachment] : []);
			});

		});

		image_frame.open();
	});


	$text.draggable({
		stop: function(event,ui) {
			$text.data('left',ui.position.left);
			$text.data('top',ui.position.top);

			var scale = $preview.get(0).naturalWidth / $preview.width();
			$('#certificate-position-x').val((ui.position.left) * scale  + ($text.width() / 2));
			$('#certificate-position-y').val((ui.position.top) * scale);
		}
	});

	$('.certificate-text-color').wpColorPicker({
		change: function(event,ui) {
			$text.css('color',ui.color.toString());
		}
	});

	$('#certificate-toggle-lines').on('change',function() {
		if($(this).is(':checked')) {
			$text.addClass('show-centering-lines');
			$('#certificate-centering-line').show();
		}
		else {
			$text.removeClass('show-centering-lines');
			$('#certificate-centering-line').hide();
		}
	});

	function resizePreview() {
		var scale = $preview.width() / $preview.get(0).naturalWidth;
		$text.css({
			transform: 'scale(' + (scale) + ')',
			left: (parseFloat($text.data('left')) * scale) + 'px',
			top: (parseFloat($text.data('top')) * scale) + 'px'
		});

	}
	$(window).on('resize',resizePreview);
	resizePreview();

	// Ajax request to refresh the image preview
	function Refresh_Image(the_id) {
		var data = {
			action: 'myprefix_get_image',
			id: the_id
		};

		$.get(ajaxurl, data, function (response) {

			if (response.success === true) {
				var $new_image = $(response.data.image);
				$preview.replaceWith($new_image);
				$preview = $new_image;
			}
		});
	}
});

