-- Модуль «Жалобы»: откат полей срока обжалования в закупке.
-- Столбцы заполняет публикация протокола итогов в проекте: откатывать
-- вместе с этим кодом проекта, иначе публикация протокола сломается.
ALTER TABLE trd_buy
    DROP COLUMN IF EXISTS appeal_end_date,
    DROP COLUMN IF EXISTS appeal_start_date;
