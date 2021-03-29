;
(function($){

// Docu Ready
$(document).ready(function() {
    var isAuthenticated = $('#cloud-ui')[0].dataset.pageUid ? true : false;

    //init SHORTCUTS
    if ($("#access-shortcuts-wrapper>ul").length > 0){
        $("#access-shortcuts-wrapper>ul").initShortcuts();
    }

    //Placeholder Fix (no Modernizr)
    $('.lt-ie10 [placeholder]').focus(function() {
        var input = $(this);
        if (input.val() == input.attr('placeholder')) {
            input.val('');
            input.removeClass('placeholder');
        }
    }).blur(function() {
        var input = $(this);
        if (input.val() == '' || input.val() == input.attr('placeholder')) {
            input.addClass('placeholder');
            input.val(input.attr('placeholder'));
        }
    }).blur();
    $('.lt-ie10 [placeholder]').parents('form').submit(function() {
        $(this).find('.lt-ie10 [placeholder]').each(function() {
            var input = $(this);
            if (input.val() == input.attr('placeholder')) {
                input.val('');
            }
        })
    });

    //Printable version
    $('.btn-printer').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    //Video RWD
    if ($(".content-video iframe").length > 0) {
        $(".content-video iframe").resizeEmbed();
    }

    //Filter
    $(".filter-label").click(function() {
        $(".filter-label").removeClass("active");
        var filter = $(this).addClass('active').data('filter');
        if (filter) {
            gallery.filter(filter);
        } else {
            gallery.unFilter();
        }
    });

    $('body').on('click', '[data-link]', function () {
        var link = jQuery(this).data('link');

        window.location = link;
    });

    const onRowCollapse = function($element, state) {
        var $buttonCollapse = $element.parents('.cloudcontentset').find('.button-collapse');
        var btnText = state ?  $buttonCollapse[0].dataset.stratOpen : $buttonCollapse[0].dataset.stratClose;

        $buttonCollapse.text(btnText);
    };

    const afterRowCollapse = function($element) {
        if (isAuthenticated) {
            window.dndZone.apply();
        }

        $('html, body').animate({
            'scrollTop': $element.offset().top - 200,
        }, 300);
    };

    $('body')
        .on('hide.bs.collapse', '.container.collapse.fade', function() {
            onRowCollapse($(this), false);
        })
        .on('show.bs.collapse', '.container.collapse.fade', function() {
            onRowCollapse($(this), true);
        })
        .on('hidden.bs.collapse', '.container.collapse.fade', function() {
            afterRowCollapse($(this));
        })
        .on('shown.bs.collapse', '.container.collapse.fade', function() {
            afterRowCollapse($(this));
        });

    // Tooltip initialization
    $('[data-toggle="tooltip"]').tooltip();

//END ready
});

// Window Resize
$(window).resize(function(){


// End window Resize
});

//initShortcuts
$.fn.initShortcuts = function(options) {
    var obj = $(this);
    obj.find('a').focus(function(e) {obj.css('height', 'auto'); });
    obj.find('a').blur(function(e) {obj.css('height', '0px'); });

    return this;
};

//FN Video RWD
$.fn.resizeEmbed = function(options) {
    var defaults = {
    };
    var options = $.extend(defaults, options);
    var obj = $(this);

    obj.each(function() {
        var newWidth = $(this).parent().width();
        $(this)
            // jQuery .data does not work on object/embed elements
            .attr('data-aspectRatio', this.height / this.width)
            .removeAttr('height')
            .removeAttr('width')
            .width(newWidth)
            .height(newWidth * $(this).attr('data-aspectRatio'));
    });

    $(window).on("resize",function() {
        obj.each(function() {
            var newWidth = $(this).parent().width();
            $(this)
                .width(newWidth)
                .height(newWidth * $(this).attr('data-aspectRatio'));
         });
    });

    return this;
}

$.fn.masterHead = function (config) {
    "use strict";

    var defaults = {
            bottom: 0
        },
        options = $.extend(defaults, config),
        obj = $(this),
        calculateHeight = function () {
            obj.height($(window).height() - obj.offset().top - options.bottom);
        };

    if (obj.length) {
        calculateHeight();
        $(window).on("resize", function () {
            calculateHeight();
        });
    }
};

// Privacy policy banner - OK button

var setCookie = function(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+ d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
};

function getCookie(name) {
    var value = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');

    return value ? value.pop() : null;
}

if (null === getCookie('accepted_cookies_policies')) {
    $('#privacy-policy-banner').removeClass('d-none');

    $(document).on('click', 'a', function () {
        setCookie('accepted_cookies_policies', 1, 180);
    });
}

$('#privacy-policy-banner .valid').on('click', function () {
    $(this).parents('#privacy-policy-banner').remove();
    setCookie('accepted_cookies_policies', 1, 180);
});

$('#privacy-policy-banner button').on('click', function () {
    $(this).parents('#privacy-policy-banner').remove();
});

var startPos = jQuery(window).scrollTop();

jQuery(window).on('scroll', function(e) {
   var currentPos = jQuery(window).scrollTop();

   if (Math.abs(currentPos - startPos) >= 500) {
        $('#privacy-policy-banner button.btn').parents('#privacy-policy-banner').remove();
        setCookie('accepted_cookies_policies', 1, 180);
   }
});

//
})(jQuery);
