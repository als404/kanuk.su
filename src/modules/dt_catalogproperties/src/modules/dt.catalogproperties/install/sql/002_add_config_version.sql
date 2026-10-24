-- Migration 002
-- Версионирование характеристик категорий
-- MySQL 8.4
-- Применять только один раз

ALTER TABLE dt_cp_section_config
    ADD COLUMN CONFIG_VERSION INT UNSIGNED NOT NULL DEFAULT 1;