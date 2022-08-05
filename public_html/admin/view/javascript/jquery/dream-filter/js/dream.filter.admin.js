/**
 * Dream Filter v2.5
 * @link http://dreamfilter.ru/
 * @license Commercial
 * @copyright Copyright (c) 2016-2020 reDream
 * @author Ivan Grigorev <ig@redream.ru>
 */

var pluginpath = 'view/javascript/jquery/dream-filter/img/';

$(document).ready(function() {
    $('#filters-list').sortable({
        appendTo: document.body
    });
    templateImage();
    skinImage();
    buttonImage();
    sliderImage();
    shadowImage();
    pickedImage();
    countImage();
    nullImage();
	$("#view-template").trigger('change');
});

$(document).on('change', '.popover-content input, .popover-content select', function () {
	var parent = $(this).closest('.popover-parent');
	parent.find('.popover-value').html($(this).val());
	parent.find('.popover-input').val($(this).val());
});

$(document).on("click", "button.delete-filter", function(e) {
    $(this).closest('.list-group-item').fadeOut(300, function () {
        $(this).remove();
    });
});

$(document).on("click", "a.select-all", function(e) {
    e.preventDefault();
    $($(this).attr('href')).find("input[type='checkbox']").prop("checked", true);
    return false;
});

$(document).on("click", "a.select-none", function(e) {
    e.preventDefault();
    $($(this).attr('href')).find("input[type='checkbox']").removeAttr("checked");
    return false;
});

$(document).on("change", ".collapse-select", function() {
    var id = $(this).attr('id'),
        val = $(this).val();

    $('.' + id + '-collapse').each(function(i) {
        if($(this).hasClass('val-' + val)) {
            $(this).collapse('show');
        } else if($(this).hasClass('in')) {
            $(this).collapse('hide');
        }
    });
});

function templateImage() {
    var template = $('#view-template').val(),
        skin = $('#view-skin').val(),
        color = $('#view-cscheme').val(),
        path = pluginpath + skin + '/' + color + '/' + template + '/';

    var img = (template == 'vertical' ? $('#truncate-mode').val() : $('#truncate-hrz-mode').val()) + '.png';

    $('#template-image').attr('src', path + img);
}

$(document).on("change", "#view-template, #view-skin, #view-cscheme, #truncate-mode, #truncate-hrz-mode", function() {
    templateImage();
});

$(document).on("change", "#view-template", function() {
	var group_parent = $(this).closest('.form-group').parent();

	if(group_parent.length) {
		if ($(this).val() == "horizontal") {
			group_parent.attr('class', 'col-sm-6');
			group_parent.find('+ div').attr('class', 'col-sm-6');
			group_parent.find('.form-group').each(function () {
				$(this).children('label').attr('class', 'col-sm-4 control-label');
				$(this).children('div').attr('class', 'col-sm-8');
			});
			$('#template-image').css({
				'padding-top': '14px'
			});
		} else {
			group_parent.attr('class', 'col-sm-8');
			group_parent.find('+ div').attr('class', 'col-sm-4');
			group_parent.find('.form-group').each(function () {
				$(this).children('label').attr('class', 'col-sm-3 control-label');
				$(this).children('div').attr('class', 'col-sm-9');
			});
			$('#template-image').css();
		}
	} else if($(this).closest('table.form').length) {
		if ($(this).val() == "horizontal") {
			$('#template-image').parent('td').css({
				'width': '50%'
			});
		} else {
			$('#template-image').parent('td').css({
				'width': '30%'
			});
		}
	}
});

function skinImage() {
    var skin = $('#view-skin').val(),
        color = $('#view-cscheme').val(),
        path = pluginpath + skin + '/' + color + '/';

    var img = 'skin.png';

    $('#skin-image').attr('src', path + img);
}

$(document).on("change", "#view-skin, #view-cscheme", function() {
    skinImage();
});

function buttonImage() {
    var button = $('#view-button').val(),
        color = $('#view-cscheme').val(),
        path = pluginpath + 'buttons/' + button + '/' + color + '/';
    var img = 'buttons.png';

    $('#button-image').attr('src', path + img);
}

$(document).on("change", "#view-button, #view-cscheme", function() {
    buttonImage();
});

function sliderImage() {
    var skin = $('#view-skin').val(),
        color = $('#view-cscheme').val(),
        grid = $('#view-grid').val(),
        path = pluginpath + skin + '/' + color + '/';
    var img = grid == 1 ? 'slider-grid.png' : 'slider.png';

    $('#slider-image').attr('src', path + img);
}

$(document).on("change", "#view-skin, #view-cscheme, #view-grid", function() {
    sliderImage();
});

function shadowImage() {
    var skin = $('#view-skin').val(),
        color = $('#view-cscheme').val(),
        shadow = $('#view-shadow').val(),
        path = pluginpath + skin + '/' + color + '/';
    var img = shadow == 1 ? 'slider-shadow.png' : 'slider-wo-shadow.png';

    $('#shadow-image').attr('src', path + img);
}

$(document).on("change", "#view-skin, #view-cscheme, #view-shadow", function() {
    shadowImage();
});

function pickedImage() {
    var skin = $('#view-skin').val(),
        color = $('#view-cscheme').val(),
        picked = $('#view-picked').val(),
        path = pluginpath + skin + '/' + color + '/';
    if(picked == 1) {
        $('#picked-image').attr('src', path + 'picked.png');
    } else {
        $('#picked-image').attr('src', '');
    }
}

$(document).on("change", "#view-skin, #view-cscheme, #view-picked", function() {
    pickedImage();
});

function countImage() {
    var skin = $('#view-skin').val(),
        color = $('#view-cscheme').val(),
        count = $('#view-count').val(),
        path = pluginpath + skin + '/' + color + '/';
    var img = count == 1 ? 'count-show.png' : 'count-hide.png';

    $('#count-image').attr('src', path + img);
}

$(document).on("change", "#view-skin, #view-cscheme, #view-count", function() {
    countImage();
});

function nullImage() {
    var skin = $('#view-skin').val(),
        color = $('#view-cscheme').val(),
        view_null = $('#view-null').val(),
        path = pluginpath + skin + '/' + color + '/';
    if(view_null == 'hide') {
        $('#null-image').attr('src', '');
    } else {
        var img = view_null == 'leave' ? 'null-leave.png' : 'null-disable.png';
        $('#null-image').attr('src', path + img);
    }
}

$(document).on("change", "#view-skin, #view-cscheme, #view-null", function() {
    nullImage();
});