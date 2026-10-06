/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

(function($) {

	var rbs_init = 1;

	jQuery(".rbs_slider").bootstrapSlider({ });


	jQuery(document).on('change', '.rbs_action_element',  function(){
		var el = jQuery(this);
		var depends = el.data('depends');
		if(depends){
			//alert(depends);
			if(el.is(':checked')){
				jQuery(depends).show(rbs_init?0:'fast');
			} else {
				jQuery(depends).hide(rbs_init?0:'fast');	
			} 
		}
	});

	jQuery(document).on('change', '.rbs_action_element_select',  function(){
		var el = jQuery(this);
		if(el.data('depends')){
			var param 	= window[el.attr('id')+"_depends"];
			if(param!=false){
				jQuery.each(param, function(index, valItem) {
					jQuery(valItem).hide(rbs_init?0:'fast');
				});
				jQuery(param[el.val()]).show(rbs_init?0:'fast');
			}
		}
	});

	jQuery(document).on('change', '.rbs_checkbox label.btn', function(){
		var el = jQuery(this);
		el.parent().find('label.btn').removeClass('btn-info').addClass('btn-default');
		el.removeClass('btn-default').addClass('btn-info');
	});

	jQuery('.rbs_colums_auto').on('change', function(){
		var el = jQuery(this);
		if(el.is(':checked')){
			jQuery('#'+el.data('width-id')).attr('disabled', 'disabled');
			jQuery('#'+el.data('colums-id')).removeAttr('disabled');
		} else {
			jQuery('#'+el.data('colums-id')).attr('disabled', 'disabled');
			jQuery('#'+el.data('width-id')).removeAttr('disabled');
		}
	}).trigger('change');

	jQuery('.rbs_action_element').trigger('change');
	jQuery('.rbs_action_element_select').trigger('change');

	rbs_init= 0;

	jQuery(document).on("slide slideStop", ".rbs_font_slider.rbs_font_size", function(slideEvt) {
		var divObj = jQuery(this).data('font-demoid');
		jQuery('#'+divObj).css('font-size', slideEvt.value+'px');
	});

	jQuery(document).on("slide slideStop", ".rbs_font_slider.rbs_font_line", function(slideEvt) {
		var divObj = jQuery(this).data('font-demoid');
		jQuery('#'+divObj).css('line-height', slideEvt.value+'%');
	});

	jQuery('body').on("change", ".rbs_fontParams", function() {
		var divObj 		= jQuery(this).data('font-demoid'),
			fontOptions = jQuery(this).data('font-option'),
			check 		= jQuery(this).is(':checked');

		switch(fontOptions){
			case 'bold':
				jQuery('#'+divObj).css('font-weight', check?'bold':'normal');
				break;
			case 'italic': 
				jQuery('#'+divObj).css('font-style', check?'italic':'normal');
				break;
			case 'underline':
				jQuery('#'+divObj).css('text-decoration', check?'underline':'none');
				break;
		}
	});

	var rbsBlockId = { 
		"block1":jQuery('#rsg_lightbox_metabox'), 
		"block2":jQuery('#rsg_size_metabox'), 
		"block3":jQuery('#rsg_view_metabox'), 
		"block4":jQuery('#rsg_hover_metabox'), 
		"block5":jQuery('#rsg_button_metabox'),
		"block6":jQuery('#rsg_loading_metabox'),
		"block7":jQuery('#rsg_polaroid_metabox'),
		"block8":jQuery('#rsg_css_metabox'),
	},
	rbsAllBlockClass = function (type){
		jQuery.each( rbsBlockId, function(index, el) {
			if(type!=0){
				el.find('.inside').addClass('rbs_disabled_block');
				if( el.find('.hndle > .rbs_info_clone_text').length==0  ){
					el.find('.hndle > span').after(
						jQuery('<span class="rbs_info_clone_text"> <span class="dashicons dashicons-info"></span> </span>')
							.append(document.createTextNode('[' + rbs_toolbox_translation.rbs_info_clone_text + ']'))
					);
				}
			} else {
				el.find('.inside').removeClass('rbs_disabled_block');	
				el.find('.hndle > .rbs_info_clone_text').remove();
			} 
		});
	};

	jQuery('#rsg_options').on("change", function(){
		rbsAllBlockClass( jQuery(this).val() );
	}).trigger('change');

})(jQuery);