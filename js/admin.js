(function ($)
{
    $(document).ready(function ()
	{
		//hover states on the static widgets
		$('#dialog_link, ul#icons li').hover(
			function() { $(this).addClass('ui-state-hover'); }, 
			function() { $(this).removeClass('ui-state-hover'); }
		);

		// Button
		$("#divButton, #linkButton, #submitButton, #inputButton").button();

		// Button Set
		$("#run-watermark, #thumbnail-select, #watermark-type, #cpt-select, #cpt-specific").buttonset();

		$('#df_option_everywhere, #df_option_cpt').change(function()
		{
			if($('#cpt-specific input[name=df_watermark_cpt_on]:checked').val() == 'everywhere')
			{
				$('#cpt-select').fadeOut('slow');
			}
			else if($('#cpt-specific input[name=df_watermark_cpt_on]:checked').val() == 'specific')
			{
				$('#cpt-select').fadeIn('slow');
			}
		});
	});
})(jQuery);