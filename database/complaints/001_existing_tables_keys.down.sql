-- =====================================================================
-- Откат миграции 001. Выполнять после отката 004, 003 и 002:
-- на эти ключи ссылаются внешние ключи модуля.
-- Удаление ограничения удаляет и его индекс.
-- =====================================================================

SET lock_timeout = '5s';

ALTER TABLE test_app_lots DROP CONSTRAINT IF EXISTS uq_test_app_lots_id_app_buy_lot;
ALTER TABLE test_app      DROP CONSTRAINT IF EXISTS uq_test_app_id_buy;

-- Индексы, оставшиеся от неудачного CONCURRENTLY (ограничение не создано).
DROP INDEX IF EXISTS uq_test_app_lots_id_app_buy_lot;
DROP INDEX IF EXISTS uq_test_app_id_buy;
