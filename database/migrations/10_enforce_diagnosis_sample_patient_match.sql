-- Before applying this migration, verify the query below returns no rows.
-- Resolve any mismatches before adding the composite foreign key.
SELECT d.Diagnosis_ID,
       d.Patient_ID AS Diagnosis_Patient_ID,
       d.Sample_ID,
       s.Patient_ID AS Sample_Patient_ID
FROM Diagnosis d
JOIN Samples s ON s.Sample_ID = d.Sample_ID
WHERE d.Patient_ID <> s.Patient_ID;

-- The composite unique key is required as the referenced key for the FK.
ALTER TABLE Samples
  ADD CONSTRAINT uq_samples_patient_sample UNIQUE (Patient_ID, Sample_ID);

-- The composite FK prevents a diagnosis from referring to another patient's sample.
-- RESTRICT preserves the diagnosis record and prevents deletion of a linked sample.
ALTER TABLE Diagnosis
  DROP FOREIGN KEY fk_diag_sample,
  ADD CONSTRAINT fk_diag_sample_patient
    FOREIGN KEY (Patient_ID, Sample_ID)
    REFERENCES Samples (Patient_ID, Sample_ID)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;
