/*
 * Подпись ЭЦП через NCALayer: отсоединённая CMS над байтами PDF (base64).
 * ES5. Сервер проверяет подпись над теми же байтами (SignatureVerifier).
 */
(function (window) {
    'use strict';

    var NCALAYER = 'wss://127.0.0.1:13579/';

    function messages() {
        return (window.ComplaintsConfig && window.ComplaintsConfig.messages) || {};
    }

    /**
     * callback(error, cmsBase64): error — текст для пользователя.
     */
    function sign(documentBase64, callback) {
        // ПРОЕКТ: заменить вызовом существующего сервиса, если в портале уже есть обёртка
        // NCALayer (подпись заявок и протоколов): вызвать её с теми же параметрами —
        // хранилище PKCS12, ключ SIGNATURE, createCMSSignatureFromBase64 без вложения
        // данных (detached) — и вернуть CMS в callback.
        var socket;
        var finished = false;

        function finish(error, result) {
            if (finished) {
                return;
            }
            finished = true;
            try {
                socket.close();
            } catch (ignore) {
                // соединение уже закрыто
            }
            callback(error, result);
        }

        try {
            socket = new window.WebSocket(NCALAYER);
        } catch (error) {
            callback(messages().signerUnavailable);

            return;
        }

        socket.onopen = function () {
            socket.send(JSON.stringify({
                module: 'kz.gov.pki.knca.commonUtils',
                method: 'createCMSSignatureFromBase64',
                args: ['PKCS12', 'SIGNATURE', documentBase64, false]
            }));
        };
        socket.onmessage = function (event) {
            var response;
            try {
                response = JSON.parse(event.data);
            } catch (error) {
                finish(messages().signerUnavailable);

                return;
            }
            if (response.result && response.result.version) {
                return;
            }
            if (String(response.code) === '200' && response.responseObject) {
                finish(null, response.responseObject);

                return;
            }
            finish(response.message === 'action.canceled' ? messages().signerCancelled : (response.message || messages().signerUnavailable));
        };
        socket.onerror = function () {
            finish(messages().signerUnavailable);
        };
        socket.onclose = function () {
            finish(messages().signerUnavailable);
        };
    }

    window.ComplaintsSigner = {sign: sign};
}(window));
