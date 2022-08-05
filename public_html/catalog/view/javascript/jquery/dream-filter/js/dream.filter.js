/**
 * Dream Filter v2.5
 * @link http://dreamfilter.ru/
 * @license Commercial
 * @copyright Copyright (c) 2016-2020 reDream
 * @author Ivan Grigorev <ig@redream.ru>
 */
(function($) {
    $.fn.dreamFilter = function(options) {
	    options = $.extend({
		    search_mode     : 'auto',
		    disable_null    : 'disable',
		    show_count      : true,
		    show_picked     : true,
		    decodeURI       : true,
		    loader          : '',
		    callbackBefore  : false,
		    callbackAfter   : false,
		    truncate        : { mode : 'none' },
		    mobile          : { mode : 'none' },
		    ajax            : { enable : false },
		    popper          : { enable: false },
		    filters         : {}
	    }, options);

	    var form = this,
		    action = form.attr('action'),
		    widget = $('#' + options.widget_id),
		    popper,
		    popover = $('#' + options.popper.id),
		    grid_btn,
		    grid4_btn,
		    list_btn,
		    price_btn,
		    compact_btn;

        //Filter init
        var init = function() {
	        form.addClass('initialized');

	        if(options.ajax.enable) {
		        $(options.ajax.selector).prepend(options.loader);
		        ajaxInit();
	        }

            if($(window).innerWidth() < options.mobile.width) {
	            mobileView();
            }
            initTruncate();
        };

        //Clear parameters
	    var clearFilter = function(id, submit) {
		    submit = (typeof submit === 'undefined') ? true : submit;

		    var group = $('#' + id),
			    input = group.find('input, select'),
			    pick = form.find('.rdf-picked [data-clear="' + id + '"]'),
			    clear = group.find('[data-clear="' + id + '"]');

		    if(input.is(':checkbox') || input.is(':radio')) {
			    input.prop('checked', false);
			    input.removeAttr('checked');
		    } else {
			    if(input.hasClass('irs-hidden-input')) {
				    var slider = input.data('ionRangeSlider'),
					    from = slider.options.from_min ? slider.options.from_min : slider.options.min,
					    to = slider.options.to_max ? slider.options.to_max : slider.options.max;

				    slider.update({
					    from: from,
					    to: to
				    });
			    }
			    input.val('');
		    }
		    if(submit) {
			    if(options.search_mode === 'auto') {
				    form.submit();
			    } else if(options.popper.enable) {
				    updatePopper(group.closest('.panel'));
			    }
		    }
		    pick.remove();
		    clear.remove();
	    };

	    //Disable empty inputs before submit
	    var beforeSubmit = function() {
		    form.find('input, select').each(function(index) {
			    var input = $(this);
			    if(input.hasClass('irs-hidden-input') && input.closest('.slidewrapper').hasClass('irs-notinit')) {
				    input.val('');
			    }
			    if(!input.val()) {
				    input.prop('disabled', true);
			    }
		    });
	    };

	    //Reset disabled inputs after submit
	    var afterSubmit = function() {
		    form.find('input, select').filter(':disabled').each(function(index) {
			    $(this).prop('disabled', false);
		    });
	    };

        //Filter submit
        form.on('submit', function(e) {
	        e.preventDefault();

            beforeSubmit();
            hidePopper();
	        var fData = $(this).serialize(),
                url = action + (fData ? ((action.indexOf('?') > 0 ? '&' : '?') + fData) : '');

            if(options.ajax.enable) {
                getResults(url, true, true);
                afterSubmit();
	            if(options.mobile.mode === 'fixed' && options.mobile.autoclose && widget.hasClass('show')) {
		            $('#' + options.mobile.button_id).trigger('click');
	            }
            } else {
	            window.location.href = url;
            }
        });

	    //Filter reset
	    form.on('reset', function(e) {
		    e.preventDefault();
		    form.find('.rdf-filters input, .rdf-filters select').each(function(index) {
			    clearFilter($(this).data('id'), false);
		    });
		    form.submit();
	    });

	    //Clear trigger
	    form.on('click', '[data-clear]', function() {
		    clearFilter($(this).data('clear'));
	    });

	    //Change clear buttons
	    form.on('change', 'input:checkbox, input:radio', function() {
		    var id = $(this).data('id');
		    if ($(this).is(':radio')) {
			    $(this).closest('.rdf-group').find('[data-clear]').remove();
		    }
		    if($(this).is(':checked')) {
			    $('#' + id).find('.rdf-label').before('<span class="rdf-clear" data-clear="' + id + '">&times;</span>');
		    } else {
			    $('#' + id).find('.rdf-clear').remove();
		    }
	    });

	    //Change clear buttons
	    form.on('change', 'select, input:text', function() {
		    var id = $(this).data('id');
		    if($(this).val()) {
			    if(!$(this).closest('.input-group').find('[data-clear]').length) {
				    $(this).closest('.input-group').append('<span class="rdf-clear input-group-addon" data-clear="' + id + '">&times;</span>');
			    }
		    } else {
			    $(this).closest('.input-group').find('.rdf-clear').remove();
		    }
	    });

        //Auto submit
        if(options.search_mode === 'auto') {
	        form.on('change', 'input:not([type=hidden]), select', function() {
                form.submit();
            });
	        form.on('finish', 'input.irs-hidden-input', function() {
                form.submit();
            });
        }

        //Popper
	    var showPopper = function(offset) {
		    offset = offset || 0;

		    setTimeout(function() {
			    popper = new Popper(form, popover, {
				    placement: 'right-start',
				    modifiers: {
					    offset: {
						    offset: offset
					    },
					    computeStyle: {
						    gpuAcceleration: false
					    },
					    preventOverflow: {
						    enabled: true,
						    boundariesElement: 'viewport'
					    }
				    }
			    });
			    popover.fadeIn(200);
		    }, 200);
	    };

	    //Destroy popper if exist
	    var hidePopper = function() {
		    if(popper) {
			    popover.fadeOut(200, function(){
				    popper.destroy();
			    });
		    }
	    };

	    //Update popper text
	    var updatePopper = function(panel) {
		    var popperOffset = panel.offset().top + panel.outerHeight()/2 - popover.outerHeight()/2 - form.offset().top;

		    beforeSubmit();
		    $.ajax({
			    url: options.popper.action.replace(/&amp;/g, '&'),
			    type: 'get',
			    data: form.serialize(),
			    processData: false,
			    dataType: 'html',
			    beforeSend : function() {
	                if (typeof $.fn.button === 'function') {
						$('#' + options.popper.button_id).button('loading');
					}
			    },
			    success: function(data) {
				    popover.find('span').html(data);
				    showPopper(popperOffset);
			    },
			    complete : function() {
	                if (typeof $.fn.button === 'function') {
						$('#' + options.popper.button_id).button('reset');
					}
			    }
		    });
		    afterSubmit();
	    };

        if(options.popper.enable) {
            form.on('change', 'input:not([type=hidden]), select', function() {
                updatePopper($(this).closest('.panel'));
            });
	        form.on('finish', 'input.irs-hidden-input', function() {
                updatePopper($(this).closest('.panel'));
            });
            $(document).on('click', '#' + options.popper.button_id, function() {
                form.submit();
                hidePopper();
            });
            $(document).mouseup(function(e){
                if (!popover.is(e.target) && popover.has(e.target).length === 0) {
                    hidePopper();
                }
            });
        }

        //Truncate
        var initTruncate = function() {
	        switch (options.truncate.mode) {
		        case 'element':
			        form.find('.rdf-truncate-element .rdf-group').each(function(index) {
				        var group = $(this);
				        if (group.children().filter(':visible').length > options.truncate.elements) {
					        group.css('padding-bottom', 0);
					        group.parent().removeClass('rdf-full');
					        if(group.parent().hasClass('rdf-show')) {
						        truncateShow($(this), 0);
					        } else {
						        truncateHide($(this), 0);
					        }
				        } else {
					        group.parent().addClass('rdf-full');
					        group.css({'height' : '', 'padding-bottom' : ''});
				        }
				        group.css('max-height', 'none').show();
			        });
		        	break;
		        case 'height':
			        form.find('.rdf-truncate-height').each(function(index) {
				        if($(this).outerHeight() === parseInt(options.truncate.height)) {
					        if(options.truncate.scrollbar) {
						        if(!$(this).hasClass('scroll-wrapper')) {
							        $(this).scrollbar();
						        }
					        } else {
						        $(this).find('.rdf-group').css('padding-right', 0);
					        }
				        }
			        });
			        break;
		        case 'width':
			        if(options.truncate.scrollbar) {
				        form.find('.rdf-truncate-width').each(function(index) {
					        if(!$(this).hasClass('scroll-wrapper')) {
						        $(this).scrollbar();
					        }
				        });
			        }
			        break;
	        }
        };

	    var truncateHide = function(group, duration) {
		    duration = (typeof duration === 'undefined') ? 400 : duration;

		    var height = 0;
		    group.children().filter(':visible').each(function(index) {
			    if(index < options.truncate.elements) {
				    height += $(this).outerHeight(true);
			    }
		    });

		    group.animate({height: height + 'px'}, duration);
		    group.parent().removeClass('rdf-show');
	    };

	    var truncateShow = function(group, duration) {
		    duration = (typeof duration === 'undefined') ? 400 : duration;

		    var height = 0;
		    group.children().filter(':visible').each(function(index) {
			    height += $(this).outerHeight(true);
		    });
		    group.animate({height: height + 'px'}, duration, function() {
			    group.height('');
		    });
		    group.parent().addClass('rdf-show');
	    };

	    form.on('click', '[data-toggle="truncate-show"]', function() {
		    truncateShow($($(this).data('target')));
	    });
	    form.on('click', '[data-toggle="truncate-hide"]', function() {
		    truncateHide($($(this).data('target')));
	    });

	    form.on('shown.bs.collapse', '.panel-collapse', function() {
		    initTruncate();
	    });

        //Mobile
	    var mobileView = function() {

		    if(options.mobile.mode === 'button') {
			    if(!widget.data('view') !== 'mobile') {
				    widget.before('<div id="rdf-dummy"></div>');
				    widget.detach().prependTo(options.ajax.selector);
				    widget.data('view', 'mobile');
			    }
			    if(!form.hasClass('collapse')) {
				    form.addClass('collapse');
			    }
		    } else {
			    if(!widget.hasClass('rdf-mobile-view')) {
				    widget.before('<div id="rdf-dummy"></div>');
				    widget.detach().prependTo('body');
				    widget.addClass('rdf-mobile-view');
				    widget.css('top', options.mobile.indenting_top + 'px');
				    // widget.css(options.mobile.side, (-window.innerWidth - 10 + "px"));
					if (window.innerWidth < 768) {widget.css(options.mobile.side, (-window.innerWidth - 10 + "px"));}
					if  (window.innerWidth >= 768) {widget.css(options.mobile.side, (-window.innerWidth/2 - 10 + "px"));}
			    }
			    var formHeight = $(window).height() - options.mobile.indenting_top - options.mobile.indenting_bottom,
				    bodyHeight = formHeight - form.find('.rdf-header').outerHeight() - form.find('.rdf-footer').outerHeight() - 10;

			    form.css('max-height', formHeight + 'px');
			    form.find('.rdf-body').css('max-height', bodyHeight + 'px');
			    form.find('.rdf-filters').scrollbar();
		    }
	    };

	    var desktopView = function() {
		    if(options.mobile.mode === 'button') {
			    if(widget.data('view') === 'mobile') {
				    $('#rdf-dummy').after(widget.detach()).remove();
				    widget.data('view', 'desktop');
			    }
			    if(form.hasClass('collapse')) {
				    form.removeClass('collapse');
			    }
			    form.css('height', 'auto');
		    } else {
			    if (widget.hasClass('rdf-mobile-view')) {
				    widget.removeClass('rdf-mobile-view');
				    $('#rdf-dummy').after(widget.detach()).remove();
				    form.attr('style', '');
				    form.find('.rdf-body').attr('style', '');
			    }

			    widget.removeClass('show');
			    $('div.rdr-backdrop').remove();
		    }
	    };

	    if(options.mobile.mode !== 'none') {
		    $(window).on('resize', function() {
			    if($(window).innerWidth() < options.mobile.width) {
				    mobileView();
			    } else {
				    desktopView();
			    }
		    });
	    }

	    //Mobile fixed event
        if(options.mobile.mode === 'fixed') {
            $(document).on('click', '#' + options.mobile.button_id + ', .rdr-backdrop', function() {
                widget.toggleClass('show');
				$(window).resize();
                if(widget.hasClass('show')) {
	                if(options.mobile.backdrop) {
		                $('<div>', {class: 'rdr-backdrop'}).appendTo('body').fadeTo('swing', 0.7);
	                }
                    if(options.mobile.side === 'right') {
                        widget.animate({ right: 0 });
                    } else {
                        widget.animate({ left: 0 });
                    }
                } else {
	                if(options.mobile.backdrop) {
		                $('div.rdr-backdrop').fadeOut(function(){
		                	$(this).remove();
		                });
	                }
                    // if(options.mobile.side === 'right') {
                    //     widget.animate({ right: (-window.innerWidth - 10 + "px") });
                    // } else {
                    //     widget.animate({ left: (-window.innerWidth - 10 + "px") });
                    // }
					if (window.innerWidth < 768) {
						if(options.mobile.side === 'right') {
							widget.animate({ right: (-window.innerWidth - 10 + "px") });
						} else {
							widget.animate({ left: (-window.innerWidth - 10 + "px") });
						}
					}
					if (window.innerWidth >= 768) {
						if(options.mobile.side === 'right') {
							widget.animate({ right: (-window.innerWidth/2 - 10 + "px") });
						} else {
							widget.animate({ left: (-window.innerWidth/2 - 10 + "px") });
						}
					}

                }
            });
        }

        //Mobile button event
        if(options.mobile.mode === 'button') {
            $(document).on('click', '#dream_filter_toggler', function() {
	            form.collapse('toggle');
            });
        }

        //Ajax
        var ajaxInit = function() {
            if(options.ajax.sorter && options.ajax.sorter_type === 'select') {
                $(options.ajax.sorter).removeAttr('onchange');
            }
            if(options.ajax.limit && options.ajax.limit_type === 'select') {
                $(options.ajax.limit).removeAttr('onchange');
            }

            $(options.ajax.selector).addClass('rdf-container');

            try {
                var view = false;
                if($.cookie) {
                    view = $.cookie('display');
                } else if($.totalStorage) {
                    view = $.totalStorage('display');
                }
                if (view && typeof display === 'function') {
                    display(view);
                } else {
                    view = localStorage.getItem('display');
                    switch(view) {
                        case 'list':
                            if (typeof list_view === 'function') {
                                list_view();
                            } else {
                                $('#list-view').trigger('click');
                            }
                            break;
                        case 'compact':
                            if (typeof compact_view === 'function') {
                                compact_view();
                            } else {
                                $('#compact-view').trigger('click');
                            }
                            break;
                        case 'price':
                            if (typeof price_view === 'function') {
                                price_view();
                            } else {
                                $('#price-view').trigger('click');
                            }
                            break;
                        case 'grid4':
                            if (typeof grid_view4 === 'function') {
	                            grid_view4();
                            } else {
                                $('#grid-view4').trigger('click');
                            }
                            break;
                        default:
                            if (typeof grid_view === 'function') {
                                grid_view();
                            } else {
                                $('#grid-view').trigger('click');
                            }
                    }
                }
            } catch(e) {
                console.error("Display error " + e.name + ":" + e.message + "\n" + e.stack);
            }
        };

        //Loading results
        var getResults = function(url, push, reload) {
	        if(window.location.protocol === 'https:') {
		        url = url.replace(/^http:\/\//g, "https://");
	        }
            var getUrl = url,
                filter = '';

	        getUrl += (getUrl.indexOf('?') > 0 ? '&' : '?') + 'rdf-ajax=1';

            if(reload && options.disable_null !== 'leave') {
	            getUrl += '&rdf-reload=1&rdf-module=' + options.module;
            } else {
                reload = false;
            }

	        if(options.decodeURI) {
		        url = decodeURIComponent(url);
	        }

            if (options.callbackBefore && typeof options.callbackBefore === 'function') {
	            options.callbackBefore(url);
            }

            $.ajax({
                url: getUrl,
                type: 'get',
                processData: false,
                dataType: reload ? 'json' : 'html',
                beforeSend : function() {
	                if (typeof $.fn.button === 'function') {
		                form.find('.rdf-footer button').button('loading');
	                }
                    $(options.ajax.selector).addClass('rdf-loading');
                    if($('#grid-view').length) {
	                    grid_btn = $('#grid-view').clone(true);
                    }
                    if($('#grid-view4').length) {
	                    grid4_btn = $('#grid-view4').clone(true);
                    }
                    if($('#list-view').length) {
	                    list_btn = $('#list-view').clone(true);
                    }
                    if($('#price-view').length) {
	                    price_btn = $('#price-view').clone(true);
                    }
                    if($('#compact-view').length) {
	                    compact_btn = $('#compact-view').clone(true);
                    }
                },
                success: function(data) {
                    var content = (reload && (typeof data.html !== 'undefined')) ? data.html : data;

                    if($(options.ajax.selector).find('#' + options.widget_id).length) {
                        filter = widget.detach();
                        $(content).find('#' + options.widget_id).remove();
                    } else if($(options.ajax.selector).find('#rdf-dummy').length) {
                        filter = $('#rdf-dummy').detach();
                    }

                    var html = ($(content).attr('id') === options.ajax.selector) ? $(content) : $(content).find(options.ajax.selector);

                    $(options.ajax.selector).children(':not(.rdf-loader)').remove();
                    $(options.ajax.selector).append(html.html()).prepend(filter);

	                if(options.ajax.pagination && !html.find(options.ajax.pagination).length) {
		                $(options.ajax.pagination).replaceWith($(content).find(options.ajax.pagination));
	                }
	                if(options.ajax.sorter && !html.find(options.ajax.sorter).length) {
		                $(options.ajax.sorter).replaceWith($(content).find(options.ajax.sorter));
	                }
	                if(options.ajax.limit && !html.find(options.ajax.limit).length) {
		                $(options.ajax.limit).replaceWith($(content).find(options.ajax.limit));
	                }

                    if(grid_btn) {
	                    $('#grid-view').replaceWith(grid_btn);
                    }
                    if(grid4_btn) {
	                    $('#grid-view4').replaceWith(grid4_btn);
                    }
                    if(list_btn) {
	                    $('#list-view').replaceWith(list_btn);
                    }
                    if(price_btn) {
	                    $('#price-view').replaceWith(price_btn);
                    }
                    if(compact_btn) {
	                    $('#compact-view').replaceWith(compact_btn);
                    }

                    if(reload && (typeof data.filters !== 'undefined')) {
                        recountFilters(data.filters);
                        initTruncate();
                    }
                    if(options.ajax.pushstate && push) {
                        history.pushState(null, null, url);
                    }
                    updatePicked();

                    if (options.callbackAfter && typeof options.callbackAfter === 'function') {
	                    options.callbackAfter(content);
                    }
	                ajaxInit();
                },
                error: function( jqXHR, textStatus, errorThrown ) {
                    console.error('jqXHR', jqXHR);
                    console.error('textStatus', textStatus);
                    console.error('errorThrown', errorThrown);
                },
                complete : function() {
                    setTimeout(function() {
                        $(options.ajax.selector).removeClass('rdf-loading');
	                    if (typeof $.fn.button === 'function') {
		                    form.find('.rdf-footer button').button('reset');
	                    }
                        if(options.ajax.scroll) {
                            $('body,html').animate({
                                scrollTop: options.ajax.offset
                            }, 500);
                        }
                    }, 300);
                }
            });
        };

        //Filter reload
        var recountFilters = function(filters) {
            $.each(options.filters, function(id, filter) {
                var panel = $('#' + id);

                if(typeof filters[id] === 'undefined') {
                    if(options.disable_null === 'hide') {
                        panel.hide();
                    }
                } else if(panel.is(':hidden')) {
                    panel.show();
                }

                if(filter.values) {
	                var prefix = '',
		                types_with_prefix = ['checkbox', 'multiimage'];
                	if(types_with_prefix.indexOf(filter.type) !== -1 && panel.find('input:checked').length) {
		                prefix = '+';
	                }

                    $.each(filter.values, function(val_id, count) {
                        var val = $('#' + val_id);

                        if(val) {
                            if(val.is('option')) {
                                var text = val.text().replace(/\(.*\)/gm, "");
                                if(typeof filters[id] !== 'undefined' && typeof filters[id].values[val_id] !== 'undefined') {
                                    val.prop('disabled', false);
                                    if(val.is(':hidden')) {
                                        val.show();
                                    }
                                    if(options.show_count) {
                                        val.html(text + '(' + prefix + filters[id].values[val_id] + ')');
                                    }
                                } else {
                                    if(options.disable_null === 'disable' && !val.is(':selected')) {
                                        val.prop('disabled', true);
                                    } else if(options.disable_null === 'hide') {
                                        val.hide();
                                    }
                                    if(options.show_count) {
                                        val.html(text);
                                    }
                                }
                            } else {
                                if(typeof filters[id] !== 'undefined' && typeof filters[id].values[val_id] !== 'undefined') {
                                    if(options.disable_null === 'disable') {
                                        val.fadeTo('fast', 1);
                                    } else if(val.is(':hidden')) {
                                        val.show();
                                    }
                                    val.find('input').prop('disabled', false);

                                    if(options.show_count) {
                                        val.find('.rdf-label').html(prefix + filters[id].values[val_id]);
                                    }
                                } else {
                                    if(!val.find('input').is(':checked')) {
                                        if(options.disable_null === 'disable') {
                                            val.fadeTo('slow', 0.5);
                                        } else if(options.disable_null === 'hide') {
                                            val.hide();
                                        }
                                        val.find('input').prop('disabled', true);
                                    }
                                    if(options.show_count) {
                                        val.find('.rdf-label').html('');
                                    }
                                }
                            }
                        }
                    });
                } else if(filter.range || filter.slider) {
                    var input = $('#' + filter.input_id),
                        slider = input.data('ionRangeSlider');

                    if(slider && !input.val()) {
                        var min = slider.options.from_min !== null ? slider.options.from_min : slider.options.min,
                            max = slider.options.to_max !== null ? slider.options.to_max : slider.options.max;
                            update = {};

                        if(typeof filters[id] !== 'undefined' && (filters[id].range || filters[id].slider)) {
                            if(filter.range && filters[id].range) {
                                if(filters[id].range.min !== min) {
                                    update.from_min = filters[id].range.min;
                                    update.to_min = filters[id].range.min;
                                    update.from = filters[id].range.min;
                                }
                                if(filters[id].range.max !== max) {
                                    update.from_max = filters[id].range.max;
                                    update.to_max = filters[id].range.max;
                                    update.to = filters[id].range.max;
                                }
                            }

                            if(filter.slider && filters[id].slider) {
                                var range_min,
                                    range_max;

	                            $.each(filter.slider, function(val_index, val) {
                                    if(filters[id].slider.indexOf(String(val)) !== -1) {
	                                    range_min = (typeof range_min === 'undefined') ? val_index : range_min;
			                            range_max = val_index;
		                            }
                                });
                                if(typeof range_min !== 'undefined' && range_min !== min) {
                                    update.from_min = range_min;
                                    update.to_min = range_min;
                                    update.from = range_min;
                                }

                                if(typeof range_max !== 'undefined' && range_max !== max) {
                                    update.from_max = range_max;
                                    update.to_max = range_max;
                                    update.to = range_max;
                                }
                            }
                            if(slider.options.disable) {
                                update.disable = false;
                            }
                        } else {
                            update.disable = true;
                        }

                        if(!$.isEmptyObject(update)) {
                            slider.update(update);
                            input.val('');
                        }
                    }
                }
            });
        };

        $("#btnClose").click(function(){
            $('#' + options.mobile.button_id).trigger('click');
        });

        //Update picked filters
        var updatePicked = function() {
            form.find('.rdf-picked').html('');
            if(options.show_picked) {
                var picked = [];

                $.each(options.filters, function(f_id, filter) {
                    var panel = $('#' + f_id);

                    if(filter.values) {
                        $.each(panel.find('input:checked'), function(i) {
                            picked.push({
                                id: $(this).attr('data-id'),
                                name: filter.type === 'type_single' ? '' : filter.title,
                                value: $.trim($(this).closest('label').text())
                            });
                        });
                        $.each(panel.find('option:selected'), function(i) {
                            if($(this).val()) {
                                picked.push({
                                    id: f_id,
                                    name: filter.title,
                                    value: $.trim($(this).text().replace(/\(.*\)/gm, ""))
                                });
                            }
                        });
                    } else if(filter.input_id) {
                        var input = $('#' + filter.input_id);
                        if(input && input.val()) {
                            if(input.hasClass('irs-hidden-input')) {
                                var slider = input.data('ionRangeSlider'),
                                    pick = '',
                                    prettyFrom = (typeof slider.options.p_values[slider.result.from] !== 'undefined') ? slider.options.p_values[slider.result.from] : (slider.result.from_value ? slider.result.from_value : slider.result.from),
                                    prettyTo = (typeof slider.options.p_values[slider.result.to] !== 'undefined') ? slider.options.p_values[slider.result.to] : (slider.result.to_value ? slider.result.to_value : slider.result.to);

                                pick += slider.options.prefix;
                                if(prettyFrom === prettyTo) {
	                                pick += prettyFrom;
                                } else {
	                                pick += prettyFrom + ' &mdash; ' + prettyTo;
                                }
                                pick += slider.options.postfix;

                                picked.push({
                                    id: f_id,
                                    name: filter.title,
                                    value: pick
                                });
                            } else {
                                picked.push({
                                    id: f_id,
                                    name: filter.title,
                                    value: input.val()
                                });
                            }
                        }
                    }
                });
                $.each(picked, function(i, pick) {
                    form.find('.rdf-picked').append(
                        '<button type="button" data-clear="' + pick.id + '" class="btn btn-default btn-xs">' +
                        (pick.name ? (pick.name + ': ') : '') + pick.value +
                        '<i>&times;</i></button>'
                    );
                });
            }
        };

	    //Ajax filter
	    if(options.ajax.enable) {
		    //Popstate
		    if(options.ajax.pushstate) {
			    $(window).on('popstate', function(e) {
				    getResults(location.href);
			    });
		    }

		    //Pagination
		    if(options.ajax.pagination) {
			    $(document).on('click', options.ajax.pagination + ' a[href]', function(e) {
				    e.preventDefault();
				    getResults($(this).attr('href'), true);
				    return false;
			    });
		    }

		    //Sort
		    if(options.ajax.sorter) {
			    if(options.ajax.sorter_type === 'button') {
				    $(document).on('click', options.ajax.sorter + ' a[href]', function(e) {
					    e.preventDefault();
					    var href = $(this).attr('href'),
						    sort = href.match('sort=([A-Za-z.]+)'),
						    order = href.match('order=([A-Z]+)');

					    form.find('input[name="sort"]').val(sort[1]);
					    form.find('input[name="order"]').val(order[1]);

					    getResults(href, true);
					    return false;
				    });
			    } else {
				    $(document).on('change', options.ajax.sorter, function(e) {
					    e.preventDefault();
					    var href = $(this).val(),
						    sort = href.match('sort=([A-Za-z.]+)'),
						    order = href.match('order=([A-Z]+)');

					    form.find('input[name="sort"]').val(sort[1]);
					    form.find('input[name="order"]').val(order[1]);

					    getResults(href, true);
				    });
			    }
		    }

		    //Limit
		    if(options.ajax.limit) {
			    if(options.ajax.limit_type === 'button') {
				    $(document).on('click', options.ajax.limit + ' a[href]', function(e) {
					    e.preventDefault();
					    var href = $(this).attr('href'),
						    limit = href.match('limit=([0-9]+)');

					    form.find('input[name="limit"]').val(limit[1]);

					    getResults(href, true);
					    return false;
				    });
			    } else {
				    $(document).on('change', options.ajax.limit, function(e) {
					    e.preventDefault();
					    var href = $(this).val(),
						    limit = href.match('limit=([0-9]+)');

					    form.find('input[name="limit"]').val(limit[1]);

					    getResults(href, true);
				    });
			    }
		    }
	    }

	    init();
	    return this;
    };
})(jQuery);
