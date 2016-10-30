var $ = jQuery,
	WPAM = {
		Utils        : {
			padDate            : function (digit) {
				'use strict';
				return (10 > digit) ? '0' + digit : digit;
			},
			getFormattedNowDate: function () {
				'use strict';
				var date = new Date();
				return date.getFullYear() + '-' + WPAM.Utils.padDate(date.getMonth() + 1) + '-' + WPAM.Utils.padDate(date.getDate());
			},
			getAssetMeta       : function (asset, duplicate) {
				'use strict';
				var timeLen = parseInt($('.timeLen', asset).val()),
					timeTerm = $('.timeTerm', asset).val();
				return {
					id       : $(asset).attr('id').replace(/^\D+/g, ''),
					name     : $('.niceName input', asset).val(),
					ext      : $('.fileExt', asset).text(),
					expires  : ('never' !== timeTerm) ? timeLen + ' ' + timeTerm : timeTerm,
					secure   : $('.secureFile', asset).prop('checked') ? 'true' : 'false',
					enabled  : $('.enableFile', asset).prop('checked') ? 'true' : 'false',
					base_date: ($('.extendFile input', asset).prop('checked')) ? WPAM.Utils.getFormattedNowDate() : $('.baseDate', asset).val(),
					order    : $(asset).closest('#attached_assets').length ? $('#attached_assets li').index(asset) : 0
				}
			},
			getOrder           : function () {
				'use strict';
				var order = [];
				$('#attached_assets .assets li').each(function (i, asset) {
					order[i] = asset.getAttribute('id').replace(/^\D+/g, '');
				});

				return order;
			},
			isDraft            : function () {
				'use strict';
				return 'Draft' === $('#post-status-display').text().trim();
			},
			isNewPost          : function () {
				'use strict';
				return -1 !== window.location.href.indexOf('post-new.php');
			},
			redirectToEditPost : function () {
				'use strict';
				var newEditPostPath = location.pathname.replace('post-new.php', 'post.php') + '?post=' + $('#post_ID').val() + '&action=edit';
				if (history.pushState) {
					history.pushState(null, '', newEditPostPath);
				} else {
					window.location.href = newEditPostPath;
				}
			},
			alreadyAttached    : function (asset_id) {
				return $('#attached_assets li#asset_' + asset_id).length > 0;
			}
		},
		Ajax         : {
			reOrderAssets   : function () {
				'use strict';
				var params = {
					action : 'order_assets',
					id     : document.getElementById('post_ID').value,
					order  : WPAM.Utils.getOrder(),
					amNonce: AM_Ajax.amNonce
				};

				$.post(AM_Ajax.ajaxurl, params, function (response) {
					$.each(response, function (i, asset_id) {
						var asset = document.getElementById('asset_' + asset_id);
						WPAM.UI.onUpdateEffect(asset);
					});
				});
			},
			trashAsset      : function (asset) {
				'use strict';
				var params = {
					action : 'trash_asset',
					id     : asset.attr('id').replace(/^\D+/g, ''),
					amNonce: AM_Ajax.amNonce
				};

				$.post(AM_Ajax.ajaxurl, params, function (response) {
					if ('success' === response) {
						$(asset).remove();
					}
				});
			},
			checkAssetParent: function (asset) {
				'use strict';
				var params = {
					action : 'parent_asset',
					ID     : asset.id,
					amNonce: AM_Ajax.amNonce
				};

				$.post(AM_Ajax.ajaxurl, params, function (response) {
					var post_id = document.getElementById('post_ID').value,
						note;
					if (parseInt(response) > 0) {
						if (parseInt(post_id) === parseInt(response) && WPAM.Utils.alreadyAttached(asset.id)) {
							note = '<p class="ob-message"><em><strong>Note:</strong> This has already been attached to <strong>this</strong> post.<br>Unless you duplicate the file, settings you assign here will override the existing settings.</em></p>';
							$('#pending_' + asset.id + ' .obfuscateFilename')
								.after(note);
							$('#pending_' + asset.id + ' .obfuscateFilename').prop('checked', true);
						} else if (parseInt(response) > 0 && parseInt(post_id) !== parseInt(response)) {
							note = '<p class="ob-message"><em><strong>Note:</strong> This has already been attached to <strong>another</strong> post, the file will be duplicated.</em></p>';
							$('#pending_' + asset.id + ' .obfuscateFilename')
								.prop('checked', true)
								.prop('readonly', true)
								.prop('disabled', true)
								.after(note);
						}
					}
				});
			},
			updateAsset     : function (asset) {
				'use strict';
				var params = {
					action   : 'update_asset',
					post_id  : $('#post_ID').val(),
					meta     : WPAM.Utils.getAssetMeta(asset),
					duplicate: false,
					amNonce  : AM_Ajax.amNonce
				};

				WPAM.UI.removeEditControls(asset);

				$.post(AM_Ajax.ajaxurl, params, function (response) {
					WPAM.UI.updateDateExpiry(asset, response);
					WPAM.UI.onUpdateEffect(asset);
				});

				WPAM.UI.disableEditFields(asset);
			},
			attachAsset     : function (asset) {
				'use strict';
				var params = {
					action   : 'attach_asset',
					post_id  : $('#post_ID').val(),
					isNew    : WPAM.Utils.isNewPost() ? 'true' : 'false',
					meta     : WPAM.Utils.getAssetMeta(asset),
					duplicate: $('.obfuscateFilename', asset).prop('checked'),
					amNonce  : AM_Ajax.amNonce
				};

				$.post(AM_Ajax.ajaxurl, params, function (response) {
					var asset_elem = document.getElementById($(asset).attr('id'));
					asset_elem.parentNode.removeChild(asset_elem);
					asset_elem = WPAM.UI.updateAssetElement(asset_elem, response);
					if ($('#attached_assets #' + $(asset_elem).attr('id')).length) {
						$('#attached_assets #' + $(asset_elem).attr('id')).replaceWith(asset_elem);
					} else {
						$('#attached_assets .assets ul').append(asset_elem);
					}
					WPAM.Ajax.reOrderAssets();

					if (WPAM.Utils.isNewPost()) {
						WPAM.Utils.redirectToEditPost();
					}
				});

			}
		},
		UI           : {
			updateAssetElement : function (asset_elem, data) {
				'use strict';
				var link = ( WPAM.Utils.isDraft() ) ? 'Please publish to activate links' : data.post_vals.url;
				asset_elem.id = 'asset_' + data.asset_vals.id;
				$('.remove.corner', asset_elem).removeClass('remove').addClass('edit').text('edit');
				$('.duplicate', asset_elem).remove();
				$('.assetVal', asset_elem).prop('disabled', true);
				$(asset_elem).append(WPAM.UI.getAssetUrlElem(link));

				return asset_elem;
			},
			getAssetUrlElem    : function (link) {
				'use strict';
				return '<p class="linkElem">Link:' +
					'<input class="assetLink" readonly="readonly" type="text" value="' + link + '">' +
					'<a href="' + link + '" target="_BLANK">view</a></p>' +
					'<div class="assetHits">Hits: 0</div>';
			},
			onUpdateEffect     : function (asset) {
				'use strict';
				$(asset).animate({
					backgroundColor: '#EFFAFF'
				}, function () {
					$(this).animate({
						backgroundColor: '#FFFFFF'
					})
				});
			},
			toggleTimeLen      : function (asset) {
				'use strict';
				if ('never' === $('.timeTerm', asset).val()) {
					$('.timeLen', asset).hide();
				} else {
					$('.timeLen', asset).show();
					if ('' === $('.timeLen', asset).val()) {
						$('.timeLen', asset).val(3);
					}
				}
			},
			enableEditFields   : function (asset) {
				'use strict';
				$('.assetVal', asset).each(function (i, elem) {
					if ($(elem).prop('disabled') === true) {
						var val = ($(elem).is(':checkbox')) ? $(elem).prop('checked') : $(elem).val();
						$(elem).data('val', val).prop('disabled', false);
					}
				});
			},
			cancelEditFields   : function (asset) {
				'use strict';
				$('.assetVal', asset).each(function (i, elem) {
					if ($(elem).is(':checkbox')) {
						$(elem).prop('checked', $(elem).data('val'));
					} else {
						$(elem).val($(elem).data('val'));
					}
					$(elem).prop('disabled', true);
				});
			},
			disableEditFields  : function (asset) {
				'use strict';
				$('.assetVal', asset).each(function (i, elem) {
					$(elem).prop('disabled', true);
				});
			},
			cancelRemoveButtons: '<span class="corner cancel">cancel</span><span class="corner trash submitdelete">remove file</span>',
			extendCheckbox     : '<span class="extendFile"> Extend? <input type="checkbox" name="extend" class="assetVal"></span>',
			addEditControls    : function (asset) {
				'use strict';
				$('.edit', asset)
					.addClass('button button-small done')
					.removeClass('edit')
					.text('done')
					.before(WPAM.UI.cancelRemoveButtons);
				$('.expires', asset).append(WPAM.UI.extendCheckbox);
			},
			removeEditControls : function (asset) {
				'use strict';
				$('.cancel, .trash, .expires .extendFile', asset).remove();
				$('.done', asset)
					.addClass('edit')
					.removeClass('button button-small done')
					.text('edit');
			},
			updateDateExpiry   : function (asset, response) {
				'use strict';
				$('.assetMeta i', asset).text('(' + response.asset_vals.expiry_date + ')');
				if (response.asset_vals.has_expired) {
					$('.assetMeta i', asset).addClass('expired');
				} else {
					$('.assetMeta i', asset).removeClass('expired');
				}
			},
			renderAssetForm    : function (assets) {
				'use strict';

				$.each(assets, function (i, asset) {
					WPAM.Ajax.checkAssetParent(asset);
					$('#filelist').append(WPAM.UI.buildAttachmentForm(asset));
					$('#asset_attach_button').show();
				});
			},
			buildAttachmentForm: function (asset) {
				'use strict';
				var fileExt = asset.filename.split('.').pop(),
					niceName = asset.title,
					niceNameDiv = '<div class="niceName"><input class="assetVal" type="text" value="' + niceName + '">.<span class="fileExt">' + fileExt + '</span></div>',
					timeLen = '<input class="timeLen assetVal" type="number" value="3">',
					timeUnit = '<select class="timeTerm assetVal"><option value="day">Day(s)</option><option value="week">Week(s)</option><option value="month" selected="selected">Month(s)</option><option value="year">Year(s)</option><option value="never">Never</option></select>',
					baseDate = '<input type="hidden" name="base_date" class="baseDate" value="' + WPAM.Utils.getFormattedNowDate() + '">',
					expires = '<p>When should this file expire? <span class="expires">' + timeLen + ' ' + timeUnit + '</span>' + baseDate + '</p>',
					secureFile = '<span>Secure this file? <input class="secureFile assetVal" type="checkbox"></span>',
					enableFile = '<span>Enable this file? <input class="enableFile assetVal" type="checkbox" checked="checked"></span>',
					obfuscateFilename = '<p class="duplicate">Duplicate and obfuscate file on server? <input class="obfuscateFilename assetVal" type="checkbox"></p>',
					removeBtn = '<span class="remove corner" title="remove">&times;</span>';

				return '<li id="pending_' + asset.id + '" class="asset">' + niceNameDiv + '<hr><div class="assetMeta">' + expires + baseDate + '<p>' + secureFile + enableFile + '</p>' + obfuscateFilename + '</div>' + removeBtn + '</li>';
			}
		},
		MediaUploader: {
			render: function () {
				'use strict';
				var file_frame, assets;

				if (undefined !== file_frame) {
					file_frame.open();
					return;
				}

				file_frame = wp.media.frames.file_frame = wp.media({
					title   : 'Select assets to attach',
					frame   : 'select',
					multiple: true
				});

				file_frame.on('select', function () {

					// Read the JSON data returned from the Media Uploader
					assets = file_frame.state().get('selection').toJSON();

					WPAM.UI.renderAssetForm(assets);
				});

				file_frame.open();
			}
		}
	};


(function ($) {
	'use strict';
	$(document).ready(function () {
		if ($('body').hasClass('post-type-asset')) {
			// on click, highlight link
			$('#attached_assets .assets').on('click', '.assetLink', function (e) {
				$(this).select();
			});

			// reorder init
			$('#attached_assets .assets ul').sortable({
				stop: WPAM.Ajax.reOrderAssets
			});

			// show/hide timeLen
			$('#upload_assets, #attached_assets').on('change', '.timeTerm', function (e) {
				var asset = $(this).closest('.asset');
				WPAM.UI.toggleTimeLen(asset);
			});

			// edit asset meta
			$('#attached_assets .assets').on('click', '.edit', function (e) {
				var asset = $(this).closest('.asset');
				WPAM.UI.addEditControls(asset);
				WPAM.UI.enableEditFields(asset);
			});

			// cancel asset edit
			$('#attached_assets .assets').on('click', '.cancel', function (e) {
				var asset = $(this).closest('.asset');
				WPAM.UI.removeEditControls(asset);
				WPAM.UI.cancelEditFields(asset);
			});

			// update asset edit
			$('#attached_assets .assets').on('click', '.done', function (e) {
				var asset = $(this).closest('.asset');
				WPAM.Ajax.updateAsset(asset);
			});

			$('#attached_assets .assets').on('click', '.trash', function (e) {
				var asset = $(this).closest('.asset');
				WPAM.Ajax.trashAsset(asset);
			});

			$('#attached_assets .asset .assetVal').bind('keypress', function (e) {
				var code = e.keyCode || e.which;
				if (code == 13) {
					e.preventDefault();
					var asset = $(this).closest('.asset');
					$('.button.done', asset).click();
				}
			});

			$('#asset_select_button').on('click', function (e) {
				e.preventDefault();
				WPAM.MediaUploader.render();
			});

			$('#asset_attach_button').on('click', function (e) {
				e.preventDefault();
				$('#filelist .asset').each(function (i, el) {
					WPAM.Ajax.attachAsset(el);
				});
				$('#asset_attach_button').hide();
			});

			$('#filelist').on('click', '.remove', function (e) {
				$(this).closest('.asset').remove();
				if (0 === $('#filelist .asset').length) {
					$('#asset_attach_button').hide();
				}
			});
		}
	});
})(jQuery);