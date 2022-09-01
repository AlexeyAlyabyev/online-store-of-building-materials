$(document).ready(function() {
	// Override summernotes image manager
	$('[data-toggle=\'summernote\']').each(function() {
		var element = this;

		if ($(this).attr('data-lang')) {
			$('head').append('<script type="text/javascript" src="view/javascript/summernote/lang/summernote-' + $(this).attr('data-lang') + '.js"></script>');
		}

		$(element).summernote({
			lang: $(this).attr('data-lang'),
			disableDragAndDrop: true,
			height: 300,
			emptyPara: '',
			codemirror: { // codemirror options
				mode: 'text/html',
				htmlMode: true,
				lineNumbers: true,
				theme: 'monokai'
			},
			fontSizes: ['12', '14', '16', '18', '20', '22', '24', '28', '32', '48', '64', '72', '96', '128'],
			toolbar: [
					['cleaner',['cleaner']], // The Button
					['style',['style']],
					['font',['bold','italic','underline','clear']],
					['fontname',['fontname']],
					['fontsize', ['fontsize']],
					['color',['color']],
					['para',['ul','ol','paragraph']],
					['height',['height']],
					['table',['table']],
					['insert',['media','link','hr']],
					['view',['fullscreen','codeview']]
			],
			cleaner: {
					action: 'both',
					icon: '<i class="note-icon">[Your Button]</i>',
					keepHtml: true,
					keepTagContents: ['span'], //Remove tags and keep the contents
					badTags: ['applet', 'col', 'colgroup', 'embed', 'noframes', 'noscript', 'script', 'style', 'title', 'meta', 'link', 'head'], //Remove full tags with contents
					badAttributes: ['bgcolor', 'border', 'height', 'cellpadding', 'cellspacing', 'lang', 'start', 'style', 'valign', 'width'],
					limitChars: false,
					limitDisplay: 'both',
					limitStop: false,
					notTimeOut: 850, //time before status message is hidden in miliseconds
					imagePlaceholder: 'https://via.placeholder.com/200' // URL, or relative path to file.
			},
			popover: {
           		image: [
					['custom', ['imageAttributes']],
					['imagesize', ['imageSize100', 'imageSize50', 'imageSize25']],
					['float', ['floatLeft', 'floatRight', 'floatNone']],
					['remove', ['removeMedia']]
				],
			},
			buttons: {
    			image: function() {
					var ui = $.summernote.ui;

					// create button
					var button = ui.button({
						contents: '<i class="note-icon-picture" />',
						tooltip: $.summernote.lang[$.summernote.options.lang].image.image,
						click: function () {
							$('#modal-image').remove();

							$.ajax({
								url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token'),
								dataType: 'html',
								beforeSend: function() {
									$('#button-image i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
									$('#button-image').prop('disabled', true);
								},
								complete: function() {
									$('#button-image i').replaceWith('<i class="fa fa-upload"></i>');
									$('#button-image').prop('disabled', false);
								},
								success: function(html) {
									$('body').append('<div id="modal-image" class="modal">' + html + '</div>');

									$('#modal-image').modal('show');

									$('#modal-image').delegate('a.thumbnail', 'click', function(e) {
										e.preventDefault();

										$(element).summernote('insertImage', $(this).attr('href'));

										$('#modal-image').modal('hide');
									});
								}
							});
						}
					});

					return button.render();
				}
  			}
		});
	});
});
