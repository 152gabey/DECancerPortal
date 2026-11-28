-- Phase 2: remove the old mutation fields after migration 02 has been run and
-- its review query returned no rows. Back up the database before running.
USE decancer_portal;

ALTER TABLE Samples
  DROP INDEX idx_samples_mut_pos,
  DROP COLUMN Mutation_Detail,
  DROP COLUMN Position;
