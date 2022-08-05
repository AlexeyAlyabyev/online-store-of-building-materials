<?php
return array(
	'theme_lightshop' => array(
		'view' => array(
			'skin' => 'light',
			'color' => 'sapphire',
		),
		'settings' => array(
			'selector' => '.catalogue__content',
			'pagination_selector' => '.catalogue__pagination',
			'sorter_selector' => '.catalogue__sort-sorts select',
			'limit_selector' => '.catalogue__sort-limits select',
			'callback_after_enable' => true,
			'callback_after' => "function(content) {\n\tdrop();\n\tdropSelect();\n\tinitCustomSelect($('select'));\n\tsimpleSlider();\n}"
		)
	),
	'moneymaker2' => array(
		'settings' => array(
			'sorter_type' => 'button',
			'sorter_selector' => '#input-sort',
			'limit_type' => 'button',
			'limit_selector' => '#input-limit',
			'callback_after_enable' => true,
			'callback_after' => "function(content) {\n\tif ($('.hidden-xs').is(':visible')) { $('.dropdown-toggle').dropdownHover({delay: 100, hoverDelay: 100}); }\n\t$(window).trigger('resize');\n}"
		)
	),
	'oct_techstore' => array(
		'settings' => array(
			'callback_after_enable' => true,
			'callback_after' => "function(content) {\n\t$('img.lazy').lazyload({ effect : 'fadeIn' });\n}"
		)
	),
	'newstore' => array(
		'view' => array(
			'skin' => 'outline'
		),
		'settings' => array(
			'callback_after_enable' => true,
			'callback_after' => "function(content) {\n\tif ($('.ns-smv .pagination li.active').next('li').length > 0) {\n\t\t$('.pagination').before('<div id=\"showmore\" style=\"padding-bottom: 15px;\"><div id=\"ajaxloading\"></div><a onclick=\"showmore()\">' + text_showmore + '</a></div>');\n\t}\n}"
		)
	)
);