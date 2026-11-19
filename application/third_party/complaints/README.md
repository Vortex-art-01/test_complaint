# Модуль «Жалобы»

Жалобы поставщиков на итоги закупок ТОО «СК-Фармация» и решения уполномоченного
органа по ним: подача с подписью руководителя, сбор жалоб после срока обжалования,
рассмотрение по лотам, маршрут согласования с ЭЦП, исполнение решения организатором.

Ядро модуля (Domain, Application) не зависит от CodeIgniter и проекта: всё, что знает
только проект, проходит через порты `src/Application/Port`. Адаптеры портов, которые
нужно подключить к сервисам проекта, отмечены комментарием
`// ПРОЕКТ: заменить вызовом существующего сервиса` с описанием, что должен делать вызов.

## Состав

```
database/complaints/                      миграции 001–004 (up и down)
application/config/complaints.php         настройки модуля
application/controllers/complaint_*.php   страницы и действия (CodeIgniter 2)
application/libraries/Complaints_module.php      точка входа модуля
application/libraries/Complaints_controller.php  общая часть контроллеров
application/helpers/complaints_helper.php        помощники представлений
application/views/complaints/             экраны (Bootstrap 3.1.1, как на портале)
application/language/russian|kazakh/complaints_lang.php
assets/complaints/                        complaints.js, complaints.signer.js (NCALayer), complaints.css
application/third_party/complaints/
    bootstrap.php                         автозагрузка классов Complaints\ (без Composer)
    src/Domain                            сущности, статусы, переходы, правила решений
    src/Application                       сценарии, порты, интерфейсы репозиториев
    src/Infrastructure                    SQL-репозитории (PDO), PDF-шаблоны, адаптеры
    src/Bootstrap                         контейнер и корень композиции
    templates/pdf                         двуязычные шаблоны PDF жалобы, ответа, сводного документа
    tests                                 PHPUnit: Domain, Application, Infrastructure, Web, Integration
```

## Установка на сервер (Linux)

1. Скопировать файлы в те же каталоги проекта.

2. Миграции 001–004 выполняются одной транзакцией:

   ```bash
   psql -v ON_ERROR_STOP=1 --single-transaction -f database/complaints/001_reference_tables.up.sql -f database/complaints/002_trd_buy_appeal_dates.up.sql -f database/complaints/003_trd_complaint.up.sql -f database/complaints/004_app_complaint.up.sql
   ```

   - 001 — справочники `ref_*` с заданными id: на них опирается код модуля
     (`Infrastructure/Persistence/ReferenceIds`), id менять нельзя;
   - 002 — `trd_buy.appeal_start_date`, `appeal_end_date` (без DEFAULT, таблица не
     переписывается; `lock_timeout` 5 с);
   - 003 — `trd_complaint`, `trd_complaint_member`, `trd_complaint_lots`,
     `trd_complaint_date`, `trd_complaint_docs`;
   - 004 — `app_complaint`, `app_complaint_answer`, `app_complaint_lots`, `app_complaint_docs`.

   Внешние ключи ведут на `trd_buy`, `trd_buy_lot`, `trd_app`, `trd_app_lot`. Имена
   трёх последних выведены из столбцов схемы — если в проекте таблицы называются иначе,
   поправьте `REFERENCES` в 003–004 и `project_schema` в конфиге.

   Откат — тоже одной транзакцией, в обратном порядке:

   ```bash
   psql -v ON_ERROR_STOP=1 --single-transaction -f database/complaints/004_app_complaint.down.sql -f database/complaints/003_trd_complaint.down.sql -f database/complaints/002_trd_buy_appeal_dates.down.sql -f database/complaints/001_reference_tables.down.sql
   ```

3. Заполнить в `application/config/complaints.php` значения с пометкой ПРОЕКТ:
   `authority_org_id`, `complainable_method_ids`, `role_map`, `project_schema`,
   `eligibility`, `holidays` (по постановлению о переносе выходных), `assets`.
   Модуль проверяет конфиг при первом запросе и не стартует с неверными значениями.

4. Подключить адаптеры портов к сервисам проекта:

   | Файл | Что подключить |
   |---|---|
   | `src/Infrastructure/Project/ProjectCurrentUserProvider.php` | вошедший пользователь, его организация и коды ролей |
   | `src/Infrastructure/Project/ProjectOrganizationDirectory.php` | организации (БИН, наименование и адрес ru/kz), пользователи (ФИО, ИИН, e-mail, должность), руководители организации |
   | `src/Infrastructure/Project/ConfiguredAuthorityResolver.php` | сотрудники органа с ролями «Рассматривающий / Согласующий / Утверждающий жалобы» |
   | `src/Infrastructure/Project/ProjectFileStorage.php` | файловое хранилище проекта: сохранить, прочитать, описать файл (id — `fls_stg_entity_id`) |
   | `src/Infrastructure/Project/ProjectFileSignatures.php` | подписи ЭЦП у файла хранилища: сохранить проверенную подпись, вернуть подписи файла |
   | `src/Infrastructure/Project/ProjectSignatureVerifier.php` | проверка CMS-подписи НУЦ РК тем же сервисом, что для протоколов |
   | `src/Infrastructure/Project/ProjectNotifier.php` | уведомление в личный кабинет пользователя и организации |
   | `src/Infrastructure/Project/ProjectLotGateway.php` | статус закупки, отмена лота, возврат на пересмотр, завершение пересмотра |
   | `src/Infrastructure/Pdf/ProjectHtmlToPdf.php` | генератор PDF проекта (или `pdf_converter = 'mpdf'`, если установлен mPDF 7+) |
   | `application/libraries/Complaints_controller.php` → `render()` | вывод экрана в общем макете портала |
   | `assets/complaints/js/complaints.signer.js` | существующая обёртка NCALayer, если она есть |

   Вместо правки этих файлов можно указать свои классы в `adapters` конфига
   (интерфейс порта => класс; класс должен быть загружен до первого запроса к модулю).

5. Ссылка на раздел в меню кабинета: `/ru/complaint_home` (`/kz/complaint_home`) —
   открывает реестр по роли пользователя. Если маршруты портала требуют явных правил
   для префикса языка, добавить в `application/config/routes.php`:

   ```php
   $route['(ru|kz)/(complaint_[a-z]+)(.*)'] = '$2$3';
   ```

6. Задачи cron (Linux), из каталога портала, где лежит `index.php`:

   ```bash
   */5 * * * * cd /var/www/portal && php index.php complaint_cron collect
   ```

   ```bash
   */15 * * * * cd /var/www/portal && php index.php complaint_cron complete_revisions
   ```

   ```bash
   7 9 * * * cd /var/www/portal && php index.php complaint_cron remind
   ```

   `/var/www/portal` — каталог портала на сервере. `collect` собирает поданные жалобы
   через `collection_grace_minutes` после окончания срока обжалования, `complete_revisions`
   отмечает лоты с опубликованным новым протоколом, `remind` напоминает о сроках.

7. Вложения до 20 МБ: в `php.ini` нужны `upload_max_filesize = 20M` и `post_max_size = 25M`.

## Проверка (Windows PowerShell, локально)

PHPUnit ставится только в каталог модуля и только для разработки (`require-dev`).

```powershell
cd application\third_party\complaints
```

```powershell
composer install
```

```powershell
vendor\bin\phpunit --testsuite unit
```

Синтаксис всех файлов модуля:

```powershell
Get-ChildItem -Recurse -Include *.php -Path ..\..\controllers\complaint_*.php, ..\..\libraries\Complaints_*.php, ..\..\helpers\complaints_helper.php, ..\..\views\complaints, ..\..\config\complaints.php, ..\..\language\*\complaints_lang.php, src, templates, tests | ForEach-Object { php -l $_.FullName } | Select-String -NotMatch 'No syntax errors'
```

Интеграционные тесты на PostgreSQL: схема `complaints_it` пересоздаётся при каждом
запуске, в неё ставятся заглушки таблиц проекта (`tests/Integration/fixtures`) и миграции.

```powershell
$env:COMPLAINTS_TEST_DSN = 'pgsql:host=127.0.0.1;port=5432;dbname=complaints_it'
```

```powershell
$env:COMPLAINTS_TEST_USER = 'complaints_it'
```

```powershell
$env:COMPLAINTS_TEST_PASSWORD = 'пароль тестовой роли'
```

```powershell
vendor\bin\phpunit --testsuite integration
```

## Статусы

Жалоба (`app_complaint.status_id`, `ref_app_complaint_st`):
1 черновик → 2 на подписи у руководителя → 3 подписана → 4 подана → 6 на рассмотрении → 7 рассмотрена;
из 2 и 3 — назад в черновик (PDF снимается с действия, подписывать придётся новую версию);
4 → 5 отозвана, из 5 — снова 4 (подпись сохраняется) или черновик.

Решение (`trd_complaint.status_id`, `ref_trd_complaint_st`):
1 на рассмотрении → 2 на согласовании → 3 на утверждении → утверждено, без согласующего
1 → 3; до подписи утверждающего — 4 отозвано, затем снова в маршрут или на рассмотрение.
Утверждённое решение несёт статус исполнения: 5 исполнение не требуется, 6 на исполнении,
7 исполнено. Исполнение лота следует из дат `trd_complaint_lots`: ждёт исполнения →
исполнено (`execution_started_at` и `executed_at` при отмене) или ждёт → пересмотр
запущен (`execution_started_at`) → исполнено (`executed_at` при новом протоколе).

## Хранение

- Сроки решения — строки `trd_complaint_date` по видам (1 обжалование, 2 рассмотрение,
  3 исполнение), полуинтервал `[start_at, end_at)`.
- Участники маршрута — `trd_complaint_member`, одна строка на роль; кто назначил —
  `assigned_by_user_id`, `assigned_by_subject_id`.
- Документы и вложения — `app_complaint_docs` и `trd_complaint_docs`, id в модуле —
  `fls_stg_entity_id`; действующая версия — строка без `deleted_at`.
  - `app_complaint_docs`: 1 PDF жалобы, 2 приложение к жалобе, 3 PDF ответа по каждой жалобе,
    4 файл к нему (3 и 4 — у лота жалобы с этим ответом);
  - `trd_complaint_docs`: 1 сводный документ, 2 PDF общего ответа по лоту, 3 файл к нему
    (2 и 3 — у лота решения).
- Ответ — `app_complaint_answer` у лота решения; лот жалобы ссылается на него (`answer_id`):
  при ответе по каждой жалобе у каждого лота жалобы свой ответ, при общем ответе — один на лот.
- Счётчики `lots_count`, `app_complaint_count` пересчитываются при сборе жалоб.

## Решения, принятые в модуле

- Право на жалобу — по статусам заявки и лота из конфига (по умолчанию любая поданная заявка).
- Один уполномоченный орган на все закупки (`authority_org_id`); при сборе жалоб он
  записывается в `trd_complaint.crsubject_id`. Решение создаёт система, `cruser_id` пуст.
- Срок обжалования устанавливает проект в `trd_buy` при публикации протокола итогов:
  дни включительно, с 00:00 первого до 23:59 последнего дня (Asia/Almaty). Собранная
  жалоба хранит срок своего решения; новый срок закупки открывает новый цикл решения.
- Рассмотрение 3, исполнение 2 рабочих дня; срок начинается в 00:00 первого рабочего дня
  после события и заканчивается в 00:00 после последнего рабочего дня; на экранах — 23:59.
  Сроки рассмотрения и исполнения информативные, о них напоминает cron.
- Одна неудалённая и ещё не собранная в решение жалоба на лот заявки; жалоба — на один лот.
- Подписывает только выбранный руководитель; ИИН сертификата сверяется с ИИН руководителя,
  БИН — с БИН организации. Подать может автор или руководитель.
- Решение закрепляется за рассматривающим при первом действии; снять закрепление
  может утверждающий того же органа.
- Смена способа рассмотрения удаляет ответы, снимает их PDF и файлы; вердикты и решения
  по лотам сохраняются.
- PDF ответа: «Сформировать» фиксирует ответ, «Сбросить» снова открывает правку;
  сводный документ содержит реестр PDF ответов с SHA-256 и подписывается всем маршрутом.
- Подписи ЭЦП хранятся у файла в хранилище проекта (порт `FileSignatures`). Модуль
  проверяет подпись и сам определяет маршрут и очередь подписантов: жалобу подписывает
  руководитель (`director_id`), сводный документ — участники `trd_complaint_member`.
- Отзыв с согласования снимает сводный документ с действия и снимает согласующего
  и утверждающего; повторная отправка — с новой версией сводного документа.
- Все жалобы по лоту отклонены — решение по лоту «Без изменений» ставится само.
- Номер решения — его id; номер жалобы на экранах и в PDF — «Ж-» и id из шести цифр.
- История смены статусов не ведётся: в схеме нет журнала. Поле `step` модуль не заполняет.
- Модуль меняет закупку только через отмену лота и возврат на пересмотр (порт ProcurementLotGateway).
- Поставщик видит свой ответ, его PDF и подписантов сводного документа; сам сводный
  документ ему не выдаётся.
- Файлы модуля отдаёт контроллер `complaint_files` с проверкой прав, а не общая ссылка хранилища.
- PDF двуязычные (ru/kz); казахские формулировки шаблонов и языковых файлов стоит сверить
  с принятыми в проекте.
