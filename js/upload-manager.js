jQuery(document).ready(function($) {
	watermarkFileUpload = {
		frame: function() {
			if ( this._frameWatermark )
				return this._frameWatermark;

			this._frameWatermark = wp.media({
				title: upload_manager_args.title,
				frame: upload_manager_args.frame,
				button: upload_manager_args.button,
				multiple: upload_manager_args.multiple,
				library: {
					type: 'image'
				}
			});

			this._frameWatermark.on( 'open', this.updateFrame ).state('library').on( 'select', this.select );
			return this._frameWatermark;
		},
		select: function() {
			var attachment = this.frame.state().get('selection').first();
			$('#upload_image').val(attachment.attributes.id);

			if($('span#previewImg_image').length > 0)
			{
				$('span#previewImg_image').replaceWith('<img id="previewImg_image" src="'+attachment.attributes.url+'" alt="" width="300" />');
			}
			else 
			{
				$('img#previewImg_image').attr('src', attachment.attributes.url);
			}

			var img = new Image();
			img.src = attachment.attributes.url;

			$('div#previewImg_imageDiv img#previewImg_image').show();
			$('div#previewImg_imageDiv span#previewImg_image_hidden').hide();
			$('p#previewImg_imageDivSize').show();

			img.onload = function()
			{
				$('#previewImg_imageDivSize').html(upload_manager_args.originalSize+': <strong>'+this.width+'</strong>px / <strong>'+this.height+'</strong>px');
			}
		},
		init: function() {
			$('#wpbody').on('click', 'input#upload_image_button', function(e) {
				e.preventDefault();
				watermarkFileUpload.frame().open();
			});
		}
	};

	watermarkFileUpload.init();

	$(document).on('click', '#turn_off_image_button', function(event) {
		$('#upload_image').val(0);
		$('div#previewImg_imageDiv img#previewImg_image').hide();
		$('div#previewImg_imageDiv span#previewImg_image_hidden').show();
		$('p#previewImg_imageDivSize').hide();
	});
});