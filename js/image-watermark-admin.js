jQuery(document).ready(function($) {

	//hover states
	$('#dialog_link, ul#icons li').hover(
		function() { $(this).addClass('ui-state-hover'); }, 
		function() { $(this).removeClass('ui-state-hover'); }
	);

	//buttons
	$('#run-watermark, #jpeg-format, #thumbnail-select, #watermark-type, #cpt-select, #cpt-specific, #run-manual-watermark').buttonset();

	//enable watermark for
	$('#df_option_everywhere, #df_option_cpt').change(function()
	{
		if($('#cpt-specific input[name=df_watermark_cpt_on]:checked').val() === 'everywhere')
		{
			$('#cpt-select').fadeOut(300);
		}
		else if($('#cpt-specific input[name=df_watermark_cpt_on]:checked').val() === 'specific')
		{
			$('#cpt-select').fadeIn(300);
		}
	});
});