-- Remove the generated display label from Biomarkers.
-- Run once against a database that still has this generated column.
-- Display labels can be formed with CONCAT(Name, ' (', Unit, ')') when needed.
USE decancer_portal;

ALTER TABLE Biomarkers
  DROP COLUMN Model_Feature_Name;
