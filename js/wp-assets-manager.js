(function($){
	function reOrderAssets() {
		var order = [];
		$('.assets ul li').each(function(i,elem) {
			order[i] = $(elem).attr('id');
		});
		var params 	= {
			// wp ajax action
			action 	: 'order_assets',					
			// asset to delete
			ID 		: $('[name=post_id]').val(),
			order 	: order,
			// send the nonce along with the request
			amNonce : AM_Ajax.amNonce
		};
		$.post( AM_Ajax.ajaxurl, params, function( response ) {					
			// console.log(response);
		});
	}
	
	$.pluploadJQ = function(el, plupload_config, options){
		var base = this;
		
		base.$el = $(el);
		base.el = el;
		
		base.$el.data("pluploadJQ", base);
		
		base.init = function(){
			if($.browser.msie) { //  if IE run silverlight, flash
				plupload_config.runtimes = "silverlight,flash,html5,html4";
			}

			base.options = $.extend({},$.pluploadJQ.defaultOptions, options);
			base.options.filelist = $('#' + base.options.filelist);
			base.options.uploadButton = $('#' + base.options.uploadButton);
			
			base.uploader = new plupload.Uploader(plupload_config);
			base.uploader.init();
			
			// file added
			base.uploader.bind('FilesAdded', function(up, files){
				var namePieces, fileExt, niceName, removeBtn, timeLen, secureFile, enableFile, baseDate, date;
				date 		= new Date();
				timeLen = 'When should this file expire? <input class="timeLen assetVal" type="number" value="3"> <select class="timeTerm assetVal"><option value="day">Day(s)</option><option value="week">Week(s)</option><option value="month" selected="selected">Month(s)</option><option value="year">Year(s)</option><option value="never">Never</option></select>';
				secureFile = 'Secure this file? <input class="secureFile assetVal" type="checkbox">';
				enableFile = 'Enable this file? <input class="enableFile assetVal" type="checkbox" checked="checked">';
				removeBtn = '<span class="remove corner" title="remove">&times;</span>';
				baseDate = '<input type="hidden" name="base_date" class="baseDate" value="' + date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate() + '">';
				$(base.options.filelist).html('<ul/>');
				$.each(up.files, function(i, file) {
					namePieces = file.name.split('.');
					fileExt = namePieces.pop();
					niceName = namePieces.join('.');
					$('ul', base.options.filelist).append('<li id="' + file.id + '" class="asset"><div class="niceName"><input class="assetVal" type="text" value="' + niceName + '">.<span class="fileExt">' + fileExt + '</span> (' + file.size + ' B)</div><hr><div class="assetMeta">' + timeLen + '<br>' + secureFile + '<br>' + enableFile + baseDate + '</div>' + removeBtn + '</li>');
				});
				$('ul', base.options.filelist).sortable();
				base.options.uploadButton.show();
			});
			
			// show progress
			base.uploader.bind('UploadProgress', function(up, file) {
				var niceName, fileElem;
				fileElem = $('#' + file.id);
				$('.remove', fileElem).addClass('percent').removeClass('remove').text(file.percent + '%');				
			});
			
			// file uploaded
			base.uploader.bind('FileUploaded', function(up, file, info) {
				var fileElem, niceName, timeLen, date;
                fileElem 	= $('#' + file.id);
				niceName 	= $('.niceName input', fileElem).val();
				timeLen 	= $('.timeLen', fileElem).val();
				$('input, select', fileElem).prop('disabled', true);
				
				if (isNaN(parseInt(info.response))) {
					fileElem.prepend(info.response + "<br>");
					$('.dismiss', fileElem).hide();
					$('.percent', fileElem).removeClass('percent').addClass('remove').html('&times;');
				} else {
					$('.percent', fileElem).removeClass('percent').addClass('edit').text('edit');
	                // Called when a file has finished uploading
					// when file is uploaded update meta
	                var params = {
						// wp ajax action
						action : 'update_asset',					
						// vars
						vals : {
							fID 	: file.id,
							post	: { 
								ID		: $('[name=post_id]').val(),
								title 	: $('#title').val()
							},
							asset 	: {
								ID 			: info.response,
								name 		: niceName,
								ext			: $('.fileExt', fileElem).text(),
								expires 	: ('never' === $('.timeTerm', fileElem).val()) ? 'never' : (timeLen + ' ' + $('.timeTerm', fileElem).val() + ((timeLen > 1) ? 's' : '')),
								secure		: $('.secureFile', fileElem).prop('checked'),
								enabled		: $('.enableFile', fileElem).prop('checked'),
								base_date 	: $('.baseDate', fileElem).val(),
								status		: 'new',
								order		: 0
							}
						},
						// send the nonce along with the request
						amNonce : AM_Ajax.amNonce
					};
				
	                $.post( AM_Ajax.ajaxurl, params, function( response ) {
	                	$('[name=base_date]', fileElem).before('<br>Link: <input class="assetLink" readonly="readonly" type="text" value="' + (('publish' === response.post_vals.post_status) ? response.post_vals.url : 'Please publish to activate links') + '">' + (('publish' === response.post_vals.post_status) ? ' <a href="' + response.post_vals.url + '" target="_BLANK">view</a>' : ''));
						if (null !== response.post_vals.post_name) {
							$('[name=post_url]').val(response.post_vals.post_name);
						}
						fileElem.attr('id', info.response).appendTo('#attached_assets .assets ul');
					});
				}
            });
			
			// upload complete
			base.uploader.bind('UploadComplete', function(up, file) {
				// hide upload button
				base.options.uploadButton.hide();
				
				// set url to edit post url
				window.history.pushState("","", "post.php?post=" + $('[name=post_id]').val() + "&action=edit");
				// make page look like it was saved
				$('.wrap h2').text('Edit Post');

				// set url in UI
				if (0 < $('#editable-post-name').length) {
					$('#edit-slug-buttons').remove();
					$('#editable-post-name').before($('[name=post_url]').val()).remove();
					$('#editable-post-name-full').text($('[name=post_url]').val());
					$('[name=post_name]').val($('[name=post_url]').val());
				}
				reOrderAssets();
				base.uploader.splice();
			});
			
			base.uploader.bind('BeforeUpload', function(up, file) {
				// do something
			});
						
			// upload
			base.options.uploadButton.on('click', function(e){
				e.preventDefault();
				if ('' ==! $('#title').val()) {
					base.uploader.refresh();
					base.uploader.start();
				} else {
					$('html, body').animate({
				        scrollTop: $("#wpbody").offset().top
				    }, 200);
				    $('#title').focus();
				    $('#title-prompt-text').text('Enter title before uploading');
				    $('#titlewrap').css({'border' : '2px solid red'});				    
				}
			});
			
			// remove added file
			$(base.options.filelist).on('click', '.remove', function(e){
				var fileID = $(this).closest('li').attr('id');
				base.uploader.removeFile(base.uploader.getFile(fileID));
				$('#' + fileID).remove();
			});
		
		};
		base.init();
	
	};
	
	$.pluploadJQ.defaultOptions = {
        filelistID: "filelist",
        uploadButton: "upload_asset"
    };
	
	$.fn.pluploadJQ = function(plupload_config, options){
		return this.each(function(){
			(new $.pluploadJQ(this, plupload_config, options));
		});
	};
	
	$(document).ready(function(){
		if ($('body').hasClass('post-php') || $('body').hasClass('post-new-php')) {
			
			// reset title style
			$('#title').focus(function(){
				$('#titlewrap').css({'border' : '0'});
				$('#title-prompt-text').text('Enter title here');
			});
			
			// on click, highlight link
			$('.assets').on('click', '.assetLink', function(e){
				$(this).select();
			});
			
			// reorder init
			$('.assets ul').sortable({
				stop: reOrderAssets
			});
			
			// initiate plupload
			wpUploaderInit.multipart_params.post_id = $('[name=post_id]').val();
			
			// remove url edit
			$('#plupload-browse-button').pluploadJQ(wpUploaderInit, {filelist : "filelist"});
			if (0 < $('#editable-post-name').length) {
				$('#edit-slug-buttons').remove();
				$('#editable-post-name').before($('#editable-post-name').text()).remove();
			}
			
			// adds drag-drop styles
			$('#plupload-upload-ui').addClass('drag-drop');
			$('#drag-drop-area').bind('dragover.wp-uploader', function(){ // dragenter doesn't fire right :(
				$('#plupload-upload-ui').addClass('drag-over');
			}).bind('dragleave.wp-uploader, drop.wp-uploader', function(){
				$('#plupload-upload-ui').removeClass('drag-over');
			});
			$('.upload-flash-bypass').hide();
			
			$('#attached_assets').bind('setOrder', function(){
				// send order here
			});
			
			// show/hide timeLen
			$('#upload_assets, #attached_assets').on('change', '.timeTerm', function(e){
				var asset = $(this).closest('.asset');
				if('never' === $(this).val()) {
					$('.timeLen', asset).hide();
				} else {
					$('.timeLen', asset).show();
				}
			});
			
			// edit asset meta
			$('.assets').on('click', '.edit', function(e){
				var asset, val;
				asset = $(this).closest('.asset');
				$(this).addClass('button button-small').addClass('done').removeClass('edit').text('done').before('<span class="corner cancel">cancel</span><span class="corner trash submitdelete">remove file</span>');
				$('.expires', asset).append('<span class="extendFile"> Extend? <input type="checkbox" name="extend" class="assetVal"></span>');
				$('.assetVal', asset).each(function(i,elem){
					if ($(elem).prop('disabled') === true) {
						val = ($(elem).is(':checkbox')) ? $(elem).prop('checked') : $(elem).val();
						$(elem).data('val', val).prop('disabled', false);
					}
				});
			});
			
			// cancel asset edit
			$('.assets').on('click', '.cancel', function(e){
				var asset = $(this).closest('.asset');
				$('.cancel, .trash', asset).remove();
				$('.done', asset).addClass('edit').removeClass('button button-small done').text('edit');
				$('.expires .extendFile', asset).remove();
				$('.assetVal', asset).each(function(i,elem){
					if ($(elem).is(':checkbox')) {
						$(elem).prop('checked', $(elem).data('val'));
					} else {							
						$(elem).val($(elem).data('val'));
					}
					$(elem).prop('disabled', true);
				});
			});
			
			// update asset edit
			$('.assets').on('click', '.done', function(e){
				var asset, timeLen, params, date;
				asset 	= $(this).closest('.asset');
				$('.cancel, .trash', asset).remove();
				$('.done', asset).addClass('edit').removeClass('button button-small done').text('edit');
				timeLen = $('.timeLen', asset).val();
				date 	= new Date();
				
				params = {
					// wp ajax action
					action : 'update_asset',					
					// vars
					vals : {
						post	: { 
							ID		: $('[name=post_id]').val(),
							name 	: $('#editable-post-name-full').text(),
							title 	: $('#title').val()
						},
						asset 	: {
							ID 			: $(asset).attr('id'),
							name 		: $('.niceName input', asset).val(),
							ext			: $('.fileExt', asset).text(),
							expires 	: ('never' === $('.timeTerm', asset).val()) ? 'never' : (timeLen + ' ' + $('.timeTerm', asset).val() + ((timeLen > 1) ? 's' : '')),
							secure		: $('.secureFile', asset).prop('checked'),
							enabled		: $('.enableFile', asset).prop('checked'),
							base_date	: ($('.extendFile input', asset).prop('checked')) ? date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate()  : $('.baseDate', asset).val(),
							status		: 'update',
							order		: $(asset).index()
						}
					},
					// send the nonce along with the request
					amNonce : AM_Ajax.amNonce
				};
			
				$('.expires .extendFile', asset).remove();
				
				$.post( AM_Ajax.ajaxurl, params, function( response ) {
					$('.assetMeta i', asset).text('(' + response.asset_vals.expiry_date + ')');
					if ( response.asset_vals.has_expired ) {
						$('.assetMeta i', asset).addClass('expired');
					} else {
						$('.assetMeta i', asset).removeClass('expired');
					}
					asset.animate({
						backgroundColor: '#EFFAFF'
					}, function() {
						$(this).animate({
							backgroundColor: '#FFFFFF'
						})
					});
				});
				
				$('.assetVal', asset).each(function(i,elem){
					$(elem).prop('disabled', true);
				});
			});
			
			// unattach file
			$('.assets').on('click', '.trash', function(e){
				var asset 	= $(this).closest('.asset');
				var params 	= {
					// wp ajax action
					action 	: 'trash_asset',					
					// asset to delete
					ID 		: $(asset).attr('id'),
					// send the nonce along with the request
					amNonce : AM_Ajax.amNonce
				};
				$.post( AM_Ajax.ajaxurl, params, function( response ) {
					if( 'success' === response ) {
						$(asset).remove();
					}
				});

			});
			
			$('.asset .assetVal').bind('keypress', function(e){
				var code = e.keyCode || e.which;
				 if(code == 13) { 
				 	e.preventDefault();
				 	var asset 	= $(this).closest('.asset');
				 	$('.button.done', asset).click();
				 }
			});
		}
	});
})(jQuery);	