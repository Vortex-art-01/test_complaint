-- =====================================================================
-- Модуль «Жалобы». Миграция 001: ключи существующих таблиц.
--
-- Цели составных внешних ключей модуля:
--   test_app      (id, buy_id)                         <- test_app_complaint
--   test_app_lots (id, test_app_id, test_buy_lots_id)  <- test_app_complaint_lots
-- id — первичный ключ, поэтому дубликатов в этих парах быть не может.
--
-- CREATE INDEX CONCURRENTLY не блокирует запись, но не работает внутри
-- транзакции: файл выполняется отдельно, БЕЗ --single-transaction.
-- ADD CONSTRAINT ... USING INDEX берёт короткую эксклюзивную блокировку;
-- lock_timeout не даёт ей встать в очередь за долгими транзакциями.
-- Если индекс не построился (сбой посреди файла), выполните 001 down
-- и повторите файл.
-- =====================================================================

SET lock_timeout = '5s';

CREATE UNIQUE INDEX CONCURRENTLY uq_test_app_id_buy
    ON test_app (id, buy_id);

ALTER TABLE test_app
    ADD CONSTRAINT uq_test_app_id_buy
    UNIQUE USING INDEX uq_test_app_id_buy;

CREATE UNIQUE INDEX CONCURRENTLY uq_test_app_lots_id_app_buy_lot
    ON test_app_lots (id, test_app_id, test_buy_lots_id);

ALTER TABLE test_app_lots
    ADD CONSTRAINT uq_test_app_lots_id_app_buy_lot
    UNIQUE USING INDEX uq_test_app_lots_id_app_buy_lot;
