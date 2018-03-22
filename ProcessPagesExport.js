jQuery(document).ready(function() {

	// @kongondo @note: ORIGINAL CODE adapted for 'on' vs 'live' changes, lack of showIf support in older PW versions, etc

	// @kongndo: @note: no need for this; we are not using tabs
	/* $('#ProcessPagesExport').WireTabs({
		items: $(".Inputfields li.WireTab"),
		id: 'ProcessPagesExportImportTabs'
	}); */
	
	// @kongondo: @note: we don't need this; it is for import but we only do exports
	/* $(document).on('change', 'input.import-confirm', function() {
		var $item = $(this).closest('.import-form-item');
		if(!$(this).val().length) {
			$item.addClass('import-form-item-fail');
		} else {
			$item.removeClass('import-form-item-fail');
		}
	});

	// @kongondo: @note: we don't have 'on' in older jQuery, so use .focus() instead
	// select all in export_json field on focus
	$('#export_json').on('focus', function(e) {
		$(this).one('mouseup', function() {
			$(this).select();
			return false;
		}).select();
	}); */

	



	/************** @kongondo **************/

	/* @kongondo @note:
		- processwire 2.2, 2.3, 2.4 and 2.5
		- for 2.2 and 2.3 workaround for lack of inputfield dependency (showIf)
		- for 2.4 and 2.5 only for workaround for $showIf = 'export_type=specific|parent|selector'; JS error
	*/
	// @note: we added class 'ProcessPagesExportCustom' to the form to target it for above uses
	var $form = $("form#ProcessPagesExport.ProcessPagesExportCustom");
	if ($form.length) {
		// export type radios
		var $exportTypes = $("li.Inputfield_export_type").find("input[name='export_type']");		
		$exportTypes.change(function () {
			var $exportType = $(this).val();
			if ($exportType) {
				// ## for all pw versions < 2.6: show 'export to' and 'export fields' inputs ##
				//$("li.Inputfield_export_fields, li.Inputfield_export_to").show();
				$("li.Inputfield_export_fields, li.Inputfield_export_to").fadeIn("slow");				
				// ## for processwire 2.2 and 2.3 ##
				// export-pages-specific options
				if ($form.hasClass("PW_23")) showIfDependency($exportType);
			}
		});

		var $textarea = $form.find('#export_json');
		

		if ($form.hasClass("PW_23") || $form.hasClass("PW_24")) {	
			if ($textarea.length) {
				var $textareaValue = JSON.parse($textarea.val());
				$textarea.text(JSON.stringify($textareaValue,null,'\t'));
			} 
		}
	
		// for all PW versions: select all in export_json field on focus
		$textarea.focus(function(e) {
			$(this).one('mouseup', function() {
				$(this).select();
				return false;
			}).select();
		});
	
	
	}
	 	
});

/**
 * Show/Hide inputs depending on export type input value.
 * 
 * @param string exportType The export type: 'specicif|parent|selector'.
 * 
 */
function showIfDependency(exportType) {	
	if (exportType == 'specific') {
		$("li.Inputfield_pages_parent, li.Inputfield_options_parent, li.Inputfield_pages_selector").hide();
		$("li.Inputfield_pages_specific").show();
	}
	else if (exportType == 'parent') {
		$("li.Inputfield_pages_specific, li.Inputfield_pages_selector").hide();
		$("li.Inputfield_pages_parent, li.Inputfield_options_parent").show();					
	}
	else if (exportType == 'selector') {
		$("li.Inputfield_pages_specific, li.Inputfield_pages_parent, li.Inputfield_options_parent").hide();
		$("li.Inputfield_pages_selector").show();					
	}	
}