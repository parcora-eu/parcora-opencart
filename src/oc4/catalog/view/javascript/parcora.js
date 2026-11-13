// Parcora pickup point picker for the default OpenCart 4 checkout.
// Adds a point list under each Parcora pickup point service in the shipping
// method modal, and blocks "Continue" until a point is chosen.
(function ($) {
    if (window.parcoraLoaded) {
        return;
    }

    window.parcoraLoaded = true;

    var quotes = {};

    // The core quote handler builds the modal in its success callback; global
    // ajaxSuccess runs right after it, so the radios already exist here.
    $(document).ajaxSuccess(function (event, xhr, settings) {
        if (settings.url.indexOf('checkout/shipping_method.quote') === -1 || !xhr.responseJSON || !xhr.responseJSON['shipping_methods'] || !xhr.responseJSON['shipping_methods']['parcora']) {
            return;
        }

        quotes = {};

        $.each(xhr.responseJSON['shipping_methods']['parcora']['quote'], function (key, quote) {
            quotes[quote['code']] = quote;

            if (quote['parcora_delivers_to'] !== 'pickup_point') {
                return;
            }

            var $radio = $('#form-shipping-method input[name=\'shipping_method\'][value=\'' + quote['code'] + '\']');

            var $picker = $('<div class="parcora-picker ms-4 mb-3" style="display: none;"></div>').attr('data-code', quote['code']);

            $picker.append($('<input type="text" class="form-control form-control-sm mb-2"/>').attr('placeholder', parcora.text.search));
            $picker.append('<select class="form-select form-select-sm" size="6"></select>');
            $picker.append('<div class="parcora-message form-text"></div>');

            // A point chosen before (the quote name carries it) counts as chosen
            $picker.data('chosen', !!quote['parcora_pickup_point']);

            $radio.closest('.form-check').after($picker);
        });

        toggle();
    });

    function toggle() {
        var code = $('#form-shipping-method input[name=\'shipping_method\']:checked').val();

        $('.parcora-picker').each(function () {
            var $picker = $(this);

            if ($picker.attr('data-code') === code) {
                $picker.show();

                if (!$picker.data('loaded')) {
                    load($picker, '');
                }
            } else {
                $picker.hide();
            }
        });
    }

    function load($picker, search) {
        var $message = $picker.find('.parcora-message');
        var $select = $picker.find('select');

        $picker.data('loaded', true);
        $message.removeClass('text-danger').text(parcora.text.loading);

        $.ajax({
            url: parcora.points,
            data: {code: $picker.attr('data-code'), q: search},
            dataType: 'json',
            success: function (json) {
                $select.empty();

                if (json['error']) {
                    $message.addClass('text-danger').text(json['error']);

                    return;
                }

                $.each(json['points'], function (i, point) {
                    $select.append($('<option/>').val(point['id']).text(point['name'] + ' — ' + point['address']));
                });

                $message.text(json['points'].length ? parcora.text.choose : parcora.text.none);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
            }
        });
    }

    $(document).on('change', '#form-shipping-method input[name=\'shipping_method\']', toggle);

    var timer;

    $(document).on('input', '.parcora-picker input', function () {
        var $picker = $(this).closest('.parcora-picker');
        var search = $(this).val();

        clearTimeout(timer);

        timer = setTimeout(function () {
            load($picker, search);
        }, 400);
    });

    $(document).on('change', '.parcora-picker select', function () {
        var $picker = $(this).closest('.parcora-picker');
        var code = $picker.attr('data-code');
        var $message = $picker.find('.parcora-message');

        $picker.data('chosen', false).data('saving', true);

        $.ajax({
            url: parcora.point,
            type: 'post',
            data: {code: code, id: $(this).val()},
            dataType: 'json',
            complete: function () {
                $picker.data('saving', false);
            },
            success: function (json) {
                if (json['error']) {
                    $message.addClass('text-danger').text(json['error']);

                    return;
                }

                $picker.data('chosen', true);
                $message.removeClass('text-danger').text('');

                // The core handler copies this label into the checkout field
                $('#form-shipping-method input[name=\'shipping_method\'][value=\'' + code + '\']').closest('.form-check').find('label').html(json['name'] + ' - ' + quotes[code]['text']);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
            }
        });
    });

    // Bound on body so it runs before the core handler, which is bound on document
    $('body').on('submit', '#form-shipping-method', function (e) {
        var code = $('#form-shipping-method input[name=\'shipping_method\']:checked').val();
        var $picker = $('.parcora-picker[data-code=\'' + code + '\']');

        if ($picker.length && (!$picker.data('chosen') || $picker.data('saving'))) {
            e.preventDefault();
            e.stopPropagation();

            $picker.find('.parcora-message').addClass('text-danger').text(parcora.text.required);
        }
    });
})(jQuery);
