/*
 * Модуль «Жалобы»: действия по кнопкам и формам, ошибки полей, счётчик символов.
 * ES5 + jQuery 1.10. Ответ сервера: {status: 1|2, message, errors, redirect, reload, data}.
 *
 *   data-complaints-post="url"   кнопка: POST без полей (data-confirm, data-save-form)
 *   form[data-complaints-form]   форма: POST FormData на action (data-confirm)
 *   data-complaints-sign="url"   подпись ЭЦП: data-source — адрес документа, data-form — поля формы
 */
(function ($, window, document) {
    'use strict';

    var config = window.ComplaintsConfig || {csrfName: 'ci_csrf_token', messages: {}};
    var messages = config.messages || {};
    var STATUS_OK = 1;

    function csrfToken() {
        var match = document.cookie.match('(^|;)\\s*' + config.csrfName + '\\s*=\\s*([^;]+)');
        if (match) {
            return decodeURIComponent(match.pop());
        }
        var meta = $('meta[name="' + config.csrfName + '"]');

        return meta.length ? meta.attr('content') : '';
    }

    function panelOf(element) {
        var panel = $(element).closest('.complaints');

        return panel.length ? panel : $('.complaints').first();
    }

    function fieldOf(name) {
        return $('[name="' + name.replace(/\.([^.]+)/g, '[$1]') + '"]').first();
    }

    function clearErrors(panel) {
        panel.find('.has-error').removeClass('has-error');
        panel.find('.complaints-field-error').remove();
        panel.find('.complaints-errors').hide().text('');
        panel.find('.complaints-message').hide().text('');
    }

    function showErrors(element, response) {
        var panel = panelOf(element);
        var box = panel.find('.complaints-errors').first();
        clearErrors(panel);
        if (response && response.errors) {
            $.each(response.errors, function (name, text) {
                var group = fieldOf(name).closest('.form-group');
                if (group.length) {
                    group.addClass('has-error').append($('<p class="help-block complaints-field-error"></p>').text(text));
                }
            });
        }
        box.text(response && response.message ? response.message : messages.requestFailed).show();
        $('html, body').animate({scrollTop: Math.max(box.offset().top - 80, 0)}, 200);
    }

    function showMessage(element, text) {
        if (text) {
            panelOf(element).find('.complaints-message').first().text(text).show();
        }
    }

    function handle(element, response) {
        if (!response || response.status !== STATUS_OK) {
            showErrors(element, response);

            return;
        }
        clearErrors(panelOf(element));
        if (response.redirect) {
            window.location.href = response.redirect;
        } else if (response.reload) {
            window.location.reload();
        } else {
            showMessage(element, response.message);
        }
    }

    function parse(xhr) {
        try {
            return $.parseJSON(xhr.responseText);
        } catch (error) {
            return null;
        }
    }

    /**
     * POST на сервер модуля; data — FormData или объект полей.
     */
    function post(element, url, data, done) {
        var isFormData = typeof window.FormData !== 'undefined' && data instanceof window.FormData;
        var button = $(element);
        if (isFormData) {
            data.append(config.csrfName, csrfToken());
        } else {
            data = $.extend({}, data);
            data[config.csrfName] = csrfToken();
        }

        button.prop('disabled', true);
        $.ajax({
            type: 'POST',
            url: url,
            data: data,
            dataType: 'json',
            cache: false,
            processData: !isFormData,
            contentType: isFormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8'
        }).done(function (response) {
            if (done) {
                done(response);
            } else {
                handle(element, response);
            }
        }).fail(function (xhr) {
            showErrors(element, parse(xhr));
        }).always(function () {
            button.prop('disabled', false);
        });
    }

    /**
     * Сначала сохраняет форму (data-save-form), затем выполняет действие.
     */
    function saveThen(element, formId, next) {
        var form = formId ? document.getElementById(formId) : null;
        if (!form) {
            next();

            return;
        }
        post(element, form.action, new window.FormData(form), function (response) {
            if (response && response.status === STATUS_OK) {
                next();
            } else {
                showErrors(element, response);
            }
        });
    }

    function confirmed(element) {
        var question = $(element).attr('data-confirm');

        return !question || window.confirm(question);
    }

    $(document).on('click', '[data-complaints-post]', function (event) {
        var button = this;
        event.preventDefault();
        if (!confirmed(button)) {
            return;
        }
        saveThen(button, $(button).attr('data-save-form'), function () {
            post(button, $(button).attr('data-complaints-post'), {});
        });
    });

    $(document).on('submit', 'form[data-complaints-form]', function (event) {
        var form = this;
        var submit = $(form).find('[type="submit"]').get(0) || form;
        event.preventDefault();
        if (!confirmed(form)) {
            return;
        }
        post(submit, form.action, new window.FormData(form));
    });

    $(document).on('click', '[data-complaints-sign]', function (event) {
        var button = this;
        var formId = $(button).attr('data-form');
        event.preventDefault();
        if (!confirmed(button)) {
            return;
        }
        post(button, $(button).attr('data-source'), {}, function (response) {
            if (!response || response.status !== STATUS_OK) {
                showErrors(button, response);

                return;
            }
            showMessage(button, messages.signing);
            window.ComplaintsSigner.sign(response.data.document, function (error, signature) {
                var data;
                if (error) {
                    showErrors(button, {message: error});

                    return;
                }
                data = formId ? new window.FormData(document.getElementById(formId)) : new window.FormData();
                data.append('signature', signature);
                post(button, $(button).attr('data-complaints-sign'), data);
            });
        });
    });

    function updateCounter(textarea) {
        var max = parseInt($(textarea).attr('data-maxlength'), 10);
        var left = max - $(textarea).val().length;
        $('.complaints-counter[data-for="' + textarea.id + '"]')
            .text(String(messages.left || '{count}').replace('{count}', left))
            .toggleClass('text-danger', left < 0);
    }

    $(document).on('input keyup', 'textarea[data-maxlength]', function () {
        updateCounter(this);
    });

    $(function () {
        $('textarea[data-maxlength]').each(function () {
            updateCounter(this);
        });
    });
}(jQuery, window, document));
