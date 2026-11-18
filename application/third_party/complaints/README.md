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

2. Миграция 001 создаёт уникальные ключи на `test_app` и `test_app_lots`
   через `CREATE INDEX CONCURRENTLY`, поэтому выполняется без общей транзакции:

   ```bash
   psql -v ON_ERROR_STOP=1 -f database/complaints/001_existing_tables_keys.up.sql
   ```

   Миграции 002–004 — одной транзакцией:

   ```bash
   psql -v ON_ERROR_STOP=1 --single-transaction -f database/complaints/002_buy_complaint.up.sql -f database/complaints/003_app_complaint.up.sql -f database/complaints/004_complaint_documents.up.sql
   ```

   Откат — файлы `*.down.sql` в обратном порядке (004, 003, 002, затем 001).

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
   | `src/Infrastructure/Project/ProjectFileStorage.php` | файловое хранилище проекта: сохранить, прочитать, описать файл |
   | `src/Infrastructure/Project/ProjectSignatureVerifier.php` | проверка CMS-подписи НУЦ РК тем же сервисом, что для протоколов |
   | `src/Infrastructure/Project/ProjectNotifier.php` | уведомление в личный кабинет пользователя и организации |
   | `src/Infrastructure/Project/ProjectLotGateway.php` | дата протокола итогов по лоту, статус закупки, отмена лота, возврат на пересмотр, завершение пересмотра |
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

Жалоба (`test_app_complaint.status`):
`draft → awaiting_signature → signed → submitted → in_review → reviewed`;
из `awaiting_signature` и `signed` — назад в `draft` (подпись аннулируется);
`submitted → withdrawn`, из `withdrawn` — снова `submitted` (подпись сохраняется) или `draft`.

Решение (`test_buy_complaint.status`):
`in_review → on_coordination → on_approval → approved`, без согласующего
`in_review → on_approval`; до подписи утверждающего — `recalled`, затем снова в маршрут
или `in_review`. Исполнение: `not_required` или `in_progress → executed`;
лоты: `pending → executed` (отмена) или `pending → started → executed` (пересмотр итогов).

## Решения, принятые в модуле

- Право на жалобу — по статусам заявки и лота из конфига (по умолчанию любая поданная заявка).
- Один уполномоченный орган на все закупки (`authority_org_id`).
- Сроки: обжалование 3, рассмотрение 3, исполнение 2 рабочих дня; срок начинается в 00:00
  первого рабочего дня после события и заканчивается в 00:00 после последнего рабочего дня;
  на экранах — 23:59. Сроки рассмотрения и исполнения информативные, о них напоминает cron.
- Одна неудалённая жалоба на лот заявки в одном сроке обжалования; жалоба — на один лот.
- Подписывает только выбранный руководитель; ИИН сертификата сверяется с ИИН руководителя,
  БИН — с БИН организации. Подать может автор или руководитель.
- Решение закрепляется за рассматривающим при первом действии; снять закрепление
  может утверждающий того же органа.
- Смена способа рассмотрения удаляет ответы и их PDF, вердикты и решения по лотам сохраняются.
- PDF ответа: «Сформировать» фиксирует ответ, «Сбросить» снова открывает правку;
  сводный документ содержит реестр PDF ответов с SHA-256 и подписывается всем маршрутом.
- Отзыв с согласования аннулирует все подписи; повторная отправка — с новой версией
  сводного документа.
- Все жалобы по лоту отклонены — решение по лоту «Без изменений» ставится само.
- Номер решения: «Р-<номер закупки>», следующий цикл обжалования той же закупки — «Р-<номер>/2».
- Модуль меняет закупку только через отмену лота и возврат на пересмотр (порт ProcurementLotGateway).
- Поставщик видит свой ответ, его PDF с SHA-256 и подписантов; файлы подписей
  сводного документа ему не выдаются.
- Файлы модуля отдаёт контроллер `complaint_files` с проверкой прав, а не общая ссылка хранилища.
- PDF двуязычные (ru/kz); казахские формулировки шаблонов и языковых файлов стоит сверить
  с принятыми в проекте.
