-- Модуль «Жалобы»: откат справочников. Выполнять после отката 004, 003.
DROP TABLE IF EXISTS ref_app_complaint_lots_verdict;
DROP TABLE IF EXISTS ref_app_complaint_docs_type;
DROP TABLE IF EXISTS ref_app_complaint_st;
DROP TABLE IF EXISTS ref_trd_complaint_date_type;
DROP TABLE IF EXISTS ref_trd_complaint_lots_decision;
DROP TABLE IF EXISTS ref_trd_complaint_docs_type;
DROP TABLE IF EXISTS ref_trd_complaint_role;
DROP TABLE IF EXISTS ref_complaint_review_method;
DROP TABLE IF EXISTS ref_trd_complaint_st;
