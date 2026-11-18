<?php

/*
 * «Шағымдар» модулі: қазақ тіліндегі жолдар. Аудармаларды жобада қабылданған
 * терминдермен салыстыру керек.
 */

// Сценарий қателері.
$lang['complaints_error_access_denied'] = 'Бұл әрекетке құқығыңыз жеткіліксіз.';
$lang['complaints_error_not_authenticated'] = 'Жалғастыру үшін жүйеге кіріңіз.';
$lang['complaints_error_not_found'] = 'Жазба табылмады.';
$lang['complaints_error_status_conflict'] = 'Жазбаның мәртебесі өзгерді. Бетті жаңартыңыз.';
$lang['complaints_error_integration'] = 'Сервис уақытша қолжетімсіз. Кейінірек қайталап көріңіз.';
$lang['complaints_error_pdf'] = 'PDF қалыптастыру мүмкін болмады. Кейінірек қайталап көріңіз.';
$lang['complaints_error_invalid_input'] = 'Форма деректері дұрыс емес.';
$lang['complaints_error_required'] = 'Өрісті толтырыңыз.';
$lang['complaints_error_buy_not_found'] = 'Мұндай нөмірлі сатып алу табылмады.';
$lang['complaints_error_buy_not_complainable'] = 'Бұл сатып алуға модульде шағым беру мүмкін емес.';
$lang['complaints_error_no_application'] = 'Сіздің ұйымыңыз бұл сатып алуға өтінім бермеген.';
$lang['complaints_error_appeal_closed'] = 'Шағымдану мерзімі өтіп кетті немесе әлі басталған жоқ.';
$lang['complaints_error_lot_not_complainable'] = 'Бұл лот бойынша шағым беру мүмкін емес.';
$lang['complaints_error_duplicate_complaint'] = 'Өтінімнің бұл лотына ағымдағы шағымдану мерзімінде шағым бар.';
$lang['complaints_error_head_not_found'] = 'Ұйым басшысын таңдаңыз.';
$lang['complaints_error_phone_format'] = 'Телефонды +7 700 000 00 00 форматында көрсетіңіз.';
$lang['complaints_error_description_length'] = 'Шағымның мәні — {max} таңбадан аспауы тиіс.';
$lang['complaints_error_file_empty'] = 'Файл бос.';
$lang['complaints_error_file_type'] = 'Файл түріне рұқсат жоқ. Рұқсат етілгені: {types}.';
$lang['complaints_error_file_size'] = 'Файл {size} МБ-тан үлкен.';
$lang['complaints_error_file_count'] = '{count} файлдан артық тіркеуге болмайды.';
$lang['complaints_error_document_changed'] = 'Құжат қол қоюға берілгеннен кейін өзгерді. Қайта қол қойыңыз.';
$lang['complaints_error_not_signed'] = 'Шағымға басшы әлі қол қойған жоқ.';
$lang['complaints_error_signature_format'] = 'Қолтаңба дұрыс емес форматта алынды.';
$lang['complaints_error_signature_invalid'] = 'ЭЦҚ тексеруден өтпеді.';
$lang['complaints_error_signature_expired'] = 'ЭЦҚ сертификатының мерзімі өтіп кетті.';
$lang['complaints_error_signature_revoked'] = 'ЭЦҚ сертификаты кері қайтарылды.';
$lang['complaints_error_signature_iin'] = 'Сертификаттағы ЖСН қол қоюшының ЖСН-імен сәйкес келмейді.';
$lang['complaints_error_signature_bin'] = 'Сертификаттағы БСН ұйымның БСН-імен сәйкес келмейді.';
$lang['complaints_error_signature_not_expected'] = 'Қазір сізден бұл құжатқа қол қою күтілмейді.';
$lang['complaints_error_method_required'] = 'Алдымен қарау тәсілін таңдаңыз.';
$lang['complaints_error_verdict_required'] = 'Шағым бойынша шешімді көрсетіңіз.';
$lang['complaints_error_answer_empty'] = 'Жауап мәтінін енгізіңіз.';
$lang['complaints_error_answer_length'] = 'Жауап — {max} таңбадан аспауы тиіс.';
$lang['complaints_error_answer_formed'] = 'Жауаптың PDF-і қалыптастырылды. Жауапты өзгерту үшін «Қалпына келтіру» батырмасын басыңыз.';
$lang['complaints_error_decision_type_not_allowed'] = 'Лот бойынша мұндай шешім шағымдар бойынша шешімдермен сәйкес келмейді.';
$lang['complaints_error_decision_incomplete'] = 'Барлық лоттар бойынша шешімдер толтырылмаған және жауаптар қалыптастырылмаған: {lots}.';
$lang['complaints_error_consolidated_missing'] = 'Алдымен жиынтық құжатты қалыптастырыңыз.';
$lang['complaints_error_consolidated_outdated'] = 'Жиынтық құжатқа бұрын қол қойылған. Оны қайта қалыптастырыңыз.';
$lang['complaints_error_route_member'] = 'Уәкілетті органның қажетті рөлі бар қызметкерін таңдаңыз.';
$lang['complaints_error_route_distinct'] = 'Қараушы, келісуші және бекітуші әртүрлі адамдар болуы тиіс.';
$lang['complaints_error_nothing_to_execute'] = 'Бұл әрекетті күтіп тұрған лоттар жоқ.';

// Хабарламалар.
$lang['complaints_notification_complaint_awaiting_signature'] = '{buy} сатып алуы, {lot} лоты бойынша {number} шағымы сіздің ЭЦҚ қолыңызды күтуде. Дайындаған: {author}.';
$lang['complaints_notification_complaint_signed'] = '{number} шағымына басшы қол қойды. Оны беруге болады.';
$lang['complaints_notification_complaints_received'] = '{buy} сатып алуы бойынша шағымдар түсті: {count}. Қарау мерзімі — {date} дейін.';
$lang['complaints_notification_decision_for_coordination'] = '{buy} сатып алуына берілген шағымдар бойынша шешім сізге келісуге жіберілді.';
$lang['complaints_notification_decision_for_approval'] = '{buy} сатып алуына берілген шағымдар бойынша шешім сізге бекітуге жіберілді.';
$lang['complaints_notification_decision_recalled'] = '{buy} сатып алуына берілген шағымдар бойынша шешімді қараушы кері қайтарып алды.';
$lang['complaints_notification_decision_approved'] = '{buy} сатып алуына берілген шағымдар бойынша {decision} шешімі бекітілді.';
$lang['complaints_notification_complaint_answered'] = '{number} шағымы қаралды. Жауап «Шағымдар» бөлімінде қолжетімді.';
$lang['complaints_notification_decision_requires_execution'] = '{buy} сатып алуына берілген шағымдар бойынша {decision} шешімін {date} дейін орындау қажет.';
$lang['complaints_notification_decision_without_execution'] = '{buy} сатып алуына берілген шағымдар бойынша {decision} шешімі бекітілді, орындау талап етілмейді.';
$lang['complaints_notification_decision_executed'] = '{buy} сатып алуына берілген шағымдар бойынша {decision} шешімі орындалды.';
$lang['complaints_notification_review_deadline'] = '{buy} сатып алуы бойынша шағымдарды қарау мерзімі {date} аяқталады.';
$lang['complaints_notification_execution_deadline'] = '{buy} сатып алуына берілген шағымдар бойынша шешімді орындау мерзімі {date} аяқталады.';
