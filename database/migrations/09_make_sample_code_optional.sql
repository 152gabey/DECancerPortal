-- Allow samples to be identified by the generated Sample_ID without an external code.
USE decancer_portal;

ALTER TABLE Samples
  MODIFY COLUMN Sample_Code VARCHAR(30) NULL;
