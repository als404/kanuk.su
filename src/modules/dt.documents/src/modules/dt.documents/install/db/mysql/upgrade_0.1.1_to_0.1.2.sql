-- Run once on the test DB after checking INFORMATION_SCHEMA.
-- Existing 48 imported materials keep their legacy matching semantics.
ALTER TABLE dt_doc_document
    ADD COLUMN MATCH_MODE VARCHAR(24) NOT NULL DEFAULT 'legacy' AFTER TYPE;
