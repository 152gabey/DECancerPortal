-- Phase 1: add and backfill the split HGVS notation fields.
-- Run once against a database created with the previous Samples schema.
-- Back up the database first. Review the final SELECT before dropping the old
-- columns in a later migration.
USE decancer_portal;

ALTER TABLE Samples
  ADD COLUMN Protein_Change VARCHAR(50) NULL AFTER Mutation_Type,
  ADD COLUMN Coding_Change VARCHAR(50) NULL AFTER Protein_Change,
  ADD COLUMN Genomic_Change VARCHAR(100) NULL AFTER Coding_Change;

-- The imported p./c. records have the form "p.…, c.…".
UPDATE Samples
SET Protein_Change = TRIM(SUBSTRING_INDEX(Mutation_Detail, ', ', 1)),
    Coding_Change = TRIM(SUBSTRING_INDEX(Mutation_Detail, ', ', -1))
WHERE Mutation_Detail LIKE 'p.%, c.%';

-- The imported splice-site records have the form "g.… (Splice Site)".
-- Mutation_Type already stores "Splice site", so keep only the HGVS g. value.
UPDATE Samples
SET Genomic_Change = TRIM(SUBSTRING_INDEX(Mutation_Detail, ' (', 1))
WHERE Mutation_Detail LIKE 'g.%';

-- Review this result before proceeding if the database contains data beyond
-- the supplied import. Every non-NULL detail should have been copied above.
SELECT Sample_ID, Mutation_Detail
FROM Samples
WHERE Mutation_Detail IS NOT NULL
  AND Protein_Change IS NULL
  AND Coding_Change IS NULL
  AND Genomic_Change IS NULL;

