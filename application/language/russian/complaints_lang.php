<?php

/*
 * Модуль «Жалобы»: строки на русском. Файл содержит только $lang и загружается
 * как CodeIgniter ($this->lang->load('complaints', 'russian')), так и ядром
 * модуля для текстов уведомлений.
 */

// Ошибки сценариев (ключи ApplicationException::messageKey()).
$lang['complaints_error_access_denied'] = 'Недостаточно прав для этого действия.';
$lang['complaints_error_not_authenticated'] = 'Войдите в систему, чтобы продолжить.';
$lang['complaints_error_not_found'] = 'Запись не найдена.';
$lang['complaints_error_status_conflict'] = 'Статус записи изменился. Обновите страницу.';
$lang['complaints_error_integration'] = 'Сервис временно недоступен. Повторите попытку позже.';
$lang['complaints_error_pdf'] = 'Не удалось сформировать PDF. Повторите попытку позже.';
$lang['complaints_error_invalid_input'] = 'Некорректные данные формы.';
$lang['complaints_error_required'] = 'Заполните поле.';
$lang['complaints_error_buy_not_found'] = 'Закупка с таким номером не найдена.';
$lang['complaints_error_buy_not_complainable'] = 'Эту закупку нельзя обжаловать в модуле.';
$lang['complaints_error_no_application'] = 'Ваша организация не подавала заявку на эту закупку.';
$lang['complaints_error_appeal_closed'] = 'Срок обжалования истёк или ещё не начался.';
$lang['complaints_error_lot_not_complainable'] = 'По этому лоту нельзя подать жалобу.';
$lang['complaints_error_duplicate_complaint'] = 'На этот лот заявки уже есть жалоба в текущем сроке обжалования.';
$lang['complaints_error_head_not_found'] = 'Выберите руководителя организации.';
$lang['complaints_error_phone_format'] = 'Укажите телефон в формате +7 700 000 00 00.';
$lang['complaints_error_description_length'] = 'Суть жалобы — не более {max} символов.';
$lang['complaints_error_file_empty'] = 'Файл пуст.';
$lang['complaints_error_file_type'] = 'Недопустимый тип файла. Разрешены: {types}.';
$lang['complaints_error_file_size'] = 'Файл больше {size} МБ.';
$lang['complaints_error_file_count'] = 'Можно приложить не более {count} файлов.';
$lang['complaints_error_document_changed'] = 'Документ изменился после выдачи на подпись. Подпишите заново.';
$lang['complaints_error_not_signed'] = 'Жалоба ещё не подписана руководителем.';
$lang['complaints_error_signature_format'] = 'Подпись получена в неверном формате.';
$lang['complaints_error_signature_invalid'] = 'Подпись ЭЦП не прошла проверку.';
$lang['complaints_error_signature_expired'] = 'Срок действия сертификата ЭЦП истёк.';
$lang['complaints_error_signature_revoked'] = 'Сертификат ЭЦП отозван.';
$lang['complaints_error_signature_iin'] = 'ИИН в сертификате не совпадает с ИИН подписанта.';
$lang['complaints_error_signature_bin'] = 'БИН в сертификате не совпадает с БИН организации.';
$lang['complaints_error_signature_not_expected'] = 'От вас сейчас не ожидается подпись этого документа.';
$lang['complaints_error_method_required'] = 'Сначала выберите способ рассмотрения.';
$lang['complaints_error_verdict_required'] = 'Укажите решение по жалобе.';
$lang['complaints_error_answer_empty'] = 'Введите текст ответа.';
$lang['complaints_error_answer_length'] = 'Ответ — не более {max} символов.';
$lang['complaints_error_answer_formed'] = 'PDF ответа сформирован. Чтобы изменить ответ, нажмите «Сбросить».';
$lang['complaints_error_decision_type_not_allowed'] = 'Такое решение по лоту не согласуется с решениями по жалобам.';
$lang['complaints_error_decision_incomplete'] = 'Не по всем лотам заполнены решения и сформированы ответы: {lots}.';
$lang['complaints_error_consolidated_missing'] = 'Сначала сформируйте сводный документ.';
$lang['complaints_error_consolidated_outdated'] = 'Сводный документ уже подписывался. Сформируйте его заново.';
$lang['complaints_error_route_member'] = 'Выберите сотрудника уполномоченного органа с нужной ролью.';
$lang['complaints_error_route_distinct'] = 'Рассматривающий, согласующий и утверждающий должны быть разными людьми.';
$lang['complaints_error_nothing_to_execute'] = 'Нет лотов, ожидающих этого действия.';

// Уведомления: {number} — номер жалобы, {buy} — номер закупки, {lot} — номер лота,
// {decision} — номер решения, {count} — количество, {date} — дата и время.
$lang['complaints_notification_complaint_awaiting_signature'] = 'Жалоба {number} по закупке {buy}, лот {lot}, ожидает вашей подписи ЭЦП. Подготовил: {author}.';
$lang['complaints_notification_complaint_signed'] = 'Жалоба {number} подписана руководителем. Её можно подать.';
$lang['complaints_notification_complaints_received'] = 'По закупке {buy} поступили жалобы: {count}. Срок рассмотрения — до {date}.';
$lang['complaints_notification_decision_for_coordination'] = 'Решение по жалобам на закупку {buy} направлено вам на согласование.';
$lang['complaints_notification_decision_for_approval'] = 'Решение по жалобам на закупку {buy} направлено вам на утверждение.';
$lang['complaints_notification_decision_recalled'] = 'Решение по жалобам на закупку {buy} отозвано рассматривающим.';
$lang['complaints_notification_decision_approved'] = 'Решение {decision} по жалобам на закупку {buy} утверждено.';
$lang['complaints_notification_complaint_answered'] = 'Жалоба {number} рассмотрена. Ответ доступен в разделе «Жалобы».';
$lang['complaints_notification_decision_requires_execution'] = 'Решение {decision} по жалобам на закупку {buy} требует исполнения до {date}.';
$lang['complaints_notification_decision_without_execution'] = 'Решение {decision} по жалобам на закупку {buy} утверждено, исполнение не требуется.';
$lang['complaints_notification_decision_executed'] = 'Решение {decision} по жалобам на закупку {buy} исполнено.';
$lang['complaints_notification_review_deadline'] = 'Срок рассмотрения жалоб по закупке {buy} истекает {date}.';
$lang['complaints_notification_execution_deadline'] = 'Срок исполнения решения по жалобам на закупку {buy} истекает {date}.';
