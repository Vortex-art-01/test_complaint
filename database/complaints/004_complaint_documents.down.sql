-- =====================================================================
-- Откат миграции 004. Выполняется первым при полном откате модуля.
-- =====================================================================

DROP TABLE IF EXISTS test_complaint_status_history;
DROP TABLE IF EXISTS test_complaint_attachments;
DROP TABLE IF EXISTS test_complaint_signatures;
DROP TABLE IF EXISTS test_complaint_documents;
