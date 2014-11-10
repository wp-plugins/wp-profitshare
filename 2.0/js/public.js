jQuery(document).ready(function($) {
	$("a.pslinks").mouseover(function() {
		jQuery(this).children(".tip").show();
	});
        $("a.pslinks").mouseout(function() {
		jQuery(this).children(".tip").hide();
	});
});