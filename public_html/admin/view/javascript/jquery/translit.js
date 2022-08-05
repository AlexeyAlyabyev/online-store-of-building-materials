// version 3x for opencart-russia.ru

var ru2en = {
	fromChars : 'абвгдезиклмнопрстуфыэйхёц',
	toChars : 'abvgdeziklmnoprstufyejhec',
	biChars : {'ж':'zh','ч':'ch','ш':'sh','щ':'sch','ю':'yu','я':'ya','&':'-and-'},
	vowelChars : 'аеёиоуыэюя',
	translit : function(str) {
		str = str.replace(/[_\s\.,?!\[\](){}\\\/"':; ]+/g, '-')
						.toLowerCase()
						.replace(new RegExp('(ь|ъ)(['+this.vowelChars+'])', 'g'), 'j$2')
						.replace(/(ь|ъ)/g, '');

		var _str = '';
		for (var x=0; x<str.length; x++)
			if ((index = this.fromChars.indexOf(str.charAt(x))) > -1)
				_str += this.toChars.charAt(index);
			else
				_str += str.charAt(x);
		str = _str;

		var _str = '';
		for (var x=0; x<str.length; x++)
			if (this.biChars[str.charAt(x)])
				_str += this.biChars[str.charAt(x)];
			else
				_str += str.charAt(x);
		str = _str;

		str = str.replace(/j{2,}/g, 'j')
						.replace(/[^-0-9a-z]+/g, '')
						.replace(/-{2,}/g, '-')
						.replace(/^-+|-+$/g, '');

		return str;
	}
}

function setTranslit(src, dst, force){
	var srcVal, dstVal;
	if (src.val() != undefined){
		src.change(function(){
			if ($("input[type=hidden][value=12]").parent().next().find("textarea").val() != undefined) {
				srcVal = src.val() + " " + $("input[type=hidden][value=12]").parent().next().find("textarea").val();
			}
			else if ($("input[type=hidden][value=51]").parent().next().find("textarea").val() != undefined)
				srcVal = src.val() + " " + $("input[type=hidden][value=51]").parent().next().find("textarea").val();
			else
				srcVal = src.val();
			dstVal = $('input[name ^="'+dst+'"]').val();

			if (force || (dstVal == ''))
				setTimeout(function(){
					$('input[name ^="'+dst+'"]').val(ru2en.translit(srcVal));
				},100);
		});
	}
	$("a[href=#tab-seo]").click(function(){
		if ($("input[type=hidden][value=12]").parent().next().find("textarea").val() != undefined) {
			srcVal = src.val() + " " + $("input[type=hidden][value=12]").parent().next().find("textarea").val();
		}
		else if ($("input[type=hidden][value=51]").parent().next().find("textarea").val() != undefined)
			srcVal = src.val() + " " + $("input[type=hidden][value=51]").parent().next().find("textarea").val();
		else
			srcVal = src.val();
		dstVal = $('input[name ^="'+dst+'"]').val();
		console.log(srcVal);
		if (force || (dstVal == ''))
			setTimeout(function(){
				$('input[name ^="'+dst+'"]').val(ru2en.translit(srcVal));
			},100);
	});
}

$(document).ready(function(){
	// Products
	$("[name*='product_description'][id*='input-name']").length ? setTranslit($("[name*='product_description'][id*='input-name']"), 'product_seo_url', true) : "";
	// Info Articles
	$("[name*='information_description'][id*='input-name']").length ? setTranslit($("[name*='information_description'][id*='input-name']"), 'information_seo_url', false) : "";
	// Categories
	$("[name*='category_description'][id*='input-name']").length ? setTranslit($("[name*='category_description'][id*='input-name']"), 'category_seo_url', false) : "";
	// Manufacturer
	$("[name='name']").length ? setTranslit($("[name='name']"), 'manufacturer_seo_url', true) : "";
});
