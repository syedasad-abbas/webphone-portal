ALTER TABLE carriers
    ADD COLUMN IF NOT EXISTS caller_id_required BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE carriers
    ALTER COLUMN caller_id_required SET DEFAULT false;

ALTER TABLE carriers
    ALTER COLUMN default_caller_id DROP NOT NULL;
