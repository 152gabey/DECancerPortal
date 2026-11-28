-- ============================================================
-- Test queries for decancer_portal
-- Sections 1-12 only read data. Section 13 inserts test rows inside a
-- transaction and rolls them back. Section 14 contains statements that SHOULD fail.
-- The "Expected" comments are the results for the seed data.
-- ============================================================
USE decancer_portal;

-- ------------------------------------------------------------
-- 1. Row counts (run this first)
-- ------------------------------------------------------------
SELECT 'Sex_Types' AS tbl, COUNT(*) AS n FROM Sex_Types
UNION ALL SELECT 'Race_Types', COUNT(*) FROM Race_Types
UNION ALL SELECT 'Cancer_Types', COUNT(*) FROM Cancer_Types
UNION ALL SELECT 'Biomarkers', COUNT(*) FROM Biomarkers
UNION ALL SELECT 'Patients', COUNT(*) FROM Patients
UNION ALL SELECT 'Users', COUNT(*) FROM Users
UNION ALL SELECT 'Samples', COUNT(*) FROM Samples
UNION ALL SELECT 'Diagnosis', COUNT(*) FROM Diagnosis
UNION ALL SELECT 'Biomarker_Measurements', COUNT(*) FROM Biomarker_Measurements
UNION ALL SELECT 'Predictions', COUNT(*) FROM Predictions
UNION ALL SELECT 'Prediction_Scores', COUNT(*) FROM Prediction_Scores
UNION ALL SELECT 'Prediction_Feedback', COUNT(*) FROM Prediction_Feedback;
-- Expected (12 rows):
--   ('Sex_Types', 2)
--   ('Race_Types', 8)
--   ('Cancer_Types', 9)
--   ('Biomarkers', 39)
--   ('Patients', 1803)
--   ('Users', 4)
--   ('Samples', 1803)
--   ('Diagnosis', 1803)
--   ('Biomarker_Measurements', 70317)
--   ('Predictions', 0)
--   ('Prediction_Scores', 0)
--   ('Prediction_Feedback', 0)

-- ------------------------------------------------------------
-- 2a. Integrity: every sample has exactly 39 biomarker values (should return no rows)
-- ------------------------------------------------------------
SELECT s.Sample_Code, COUNT(m.Biomarker_ID) AS n_values
FROM Samples s
LEFT JOIN Biomarker_Measurements m ON m.Sample_ID = s.Sample_ID
GROUP BY s.Sample_ID, s.Sample_Code
HAVING COUNT(m.Biomarker_ID) <> 39;
-- Expected: no rows

-- ------------------------------------------------------------
-- 2b. Integrity: every patient has a sample (should return no rows)
-- ------------------------------------------------------------
SELECT p.Patient_ID, p.FirstName, p.LastName
FROM Patients p
LEFT JOIN Samples s ON s.Patient_ID = p.Patient_ID
WHERE s.Sample_ID IS NULL;
-- Expected: no rows

-- ------------------------------------------------------------
-- 2c. Integrity: cancer-free patients have no stage or treatment (should be 0)
-- ------------------------------------------------------------
SELECT COUNT(*) AS cancer_free_with_stage_or_treatment
FROM Diagnosis d
JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
WHERE ct.is_cancer_free = 1
  AND (d.Cancer_Stage IS NOT NULL OR d.Treatment IS NOT NULL);
-- Expected (1 row):
--   (0)

-- ------------------------------------------------------------
-- 2d. Integrity: cancer patients all have stage and treatment (should be 0)
-- ------------------------------------------------------------
SELECT COUNT(*) AS cancer_patients_missing_stage_or_treatment
FROM Diagnosis d
JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
WHERE ct.is_cancer_free = 0
  AND (d.Cancer_Stage IS NULL OR d.Treatment IS NULL);
-- Expected (1 row):
--   (0)

-- ------------------------------------------------------------
-- 2e. Integrity: age from date of birth equals stored age (MySQL only, should be 0)
-- ------------------------------------------------------------
SELECT COUNT(*) AS age_mismatches
FROM Samples s
JOIN Patients p ON p.Patient_ID = s.Patient_ID
WHERE TIMESTAMPDIFF(YEAR, p.Date_of_Birth, s.Collection_Date) <> s.Age_At_Collection;
-- Expected: age_mismatches = 0

-- ------------------------------------------------------------
-- 3. Patients per cancer type
-- ------------------------------------------------------------
SELECT ct.Cancer_Name, COUNT(*) AS n_patients
FROM Diagnosis d
JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
GROUP BY ct.Cancer_ID, ct.Cancer_Name
ORDER BY n_patients DESC, ct.Cancer_Name;
-- Expected (9 rows):
--   ('Normal (cancer-free)', 799)
--   ('Colorectum', 388)
--   ('Breast', 208)
--   ('Lung', 104)
--   ('Pancreas', 93)
--   ('Stomach', 68)
--   ('Ovary', 54)
--   ('Esophagus', 45)
--   ('Liver', 44)

-- ------------------------------------------------------------
-- 4. Race and sex categories (must match what the model was trained on)
-- ------------------------------------------------------------
SELECT rt.Race_Name, st.Sex_Name, COUNT(*) AS n
FROM Patients p
JOIN Race_Types rt ON rt.Race_Type_ID = p.Race_Type_ID
JOIN Sex_Types st ON st.Sex_Type_ID = p.Sex_Type_ID
GROUP BY rt.Race_Type_ID, st.Sex_Type_ID, rt.Race_Name, st.Sex_Name
ORDER BY rt.Race_Name, st.Sex_Name;
-- Expected: 16 rows, first 3:
--   ('Asian', 'Female', 159)
--   ('Asian', 'Male', 163)
--   ('Black', 'Female', 62)

-- ------------------------------------------------------------
-- 5. Biomarkers and units in model feature order
-- ------------------------------------------------------------
SELECT Biomarker_ID, Name, Unit
FROM Biomarkers
ORDER BY Biomarker_ID;
-- Expected: 39 rows, first 3:
--   (1, 'AFP', 'pg/ml')
--   (2, 'Angiopoietin-2', 'pg/ml')
--   (3, 'AXL', 'pg/ml')

-- ------------------------------------------------------------
-- 6. Overview of prediction information
-- ------------------------------------------------------------
SELECT
  s.sample_code as sample,
  pa.firstName,
  pa.lastName,
  rt.race_name as race,
  st.sex_name as sex,
  s.age_at_collection as age,
  s.mutation_gene as mutated_DNA,
  c.cancer_name as predicted_cancer,
  ps.probability as likelihood,
  b.name as protein_of_interest,
  p.highest_protein_influence as protein_influence
FROM Predictions p
JOIN Samples s ON p.sample_id = s.sample_id
JOIN Patients pa ON s.patient_id = pa.patient_id
JOIN Prediction_Scores ps ON ps.probability = (
  SELECT MAX(Probability)
  FROM Prediction_Scores
  WHERE Prediction_ID = p.prediction_id
)
JOIN Biomarkers b ON p.highest_biomarker_id = b.biomarker_id
JOIN Race_Types rt ON pa.race_type_id = rt.race_type_id
JOIN Sex_Types st ON pa.sex_type_id = st.sex_type_id
JOIN Cancer_types c ON c.cancer_id = ps.cancer_id
ORDER BY likelihood DESC
;
-- Expected 835 rows, first:
-- ('PANCA 1025 PLS 1', 'Xavier', 'Mans', 'Caucasian', 'Male',  79, 'KRAS', 'Pancreas', 0.8833333333333333, 'CA 15-3', 0.13842009219631207)

-- ------------------------------------------------------------
-- 7. Model input: the 39 protein values of one sample, in model order
-- ------------------------------------------------------------
SELECT m.Value
FROM Biomarker_Measurements m
JOIN Samples s ON s.Sample_ID = m.Sample_ID
WHERE s.Sample_Code = 'CRC 456 PLS 1'
ORDER BY m.Biomarker_ID;
-- Expected: 39 rows, first 3:
--   (715.308)
--   (20936.35)
--   (2772.96)

-- ------------------------------------------------------------
-- 8. Model input: Race, Sex, Omega score and Age of the same sample
-- ------------------------------------------------------------
SELECT p.Race_Type_ID AS Race, p.Sex_Type_ID AS Sex, s.Omega_Score AS omega_score, s.Age_At_Collection AS age
FROM Samples s
JOIN Patients p ON p.Patient_ID = s.Patient_ID
WHERE s.Sample_Code = 'CRC 456 PLS 1';
-- Expected (1 row):
--   (3.0, 0.0, 2.45, 59)

-- ------------------------------------------------------------
-- 9. Patient detail page (oncologist view)
-- ------------------------------------------------------------
SELECT p.Patient_ID, p.FirstName, p.LastName, p.Date_of_Birth, 
       st.Sex_Name AS Sex, rt.Race_Name AS Race, p.Country,
       ct.Cancer_Name, d.Cancer_Stage, d.Histopathology, d.Treatment, s.Sample_Code
FROM Patients p
JOIN Sex_Types st ON st.Sex_Type_ID = p.Sex_Type_ID
JOIN Race_Types rt ON rt.Race_Type_ID = p.Race_Type_ID
JOIN Diagnosis d ON d.Patient_ID = p.Patient_ID
JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
JOIN Samples s ON s.Sample_ID = d.Sample_ID
WHERE p.Patient_ID = 1;
-- Expected (1 row):
--   (1, 'James', 'Hierro', '1955-08-05', 'Male', 'Caucasian', 'Ireland', 'Colorectum', 'I', 'Adenocarcinoma', 'Surgical resection', 'CRC 455 PLS 1')

-- ------------------------------------------------------------
-- 10a. Filter by gene: patients with a KRAS mutation (first 10 shown)
-- ------------------------------------------------------------
SELECT p.Patient_ID, p.FirstName, p.LastName, s.Mutation_Gene,
       s.Protein_Change, s.Coding_Change, s.Genomic_Change, s.Chromosome
FROM Samples s
JOIN Patients p ON p.Patient_ID = s.Patient_ID
WHERE s.Mutation_Gene = 'KRAS'
ORDER BY p.Patient_ID
LIMIT 10;
-- Expected: 115 matching rows in total (first 10 shown)

-- ------------------------------------------------------------
-- 10b. Patients with the same mutation (TP53 p.R248Q)
-- ------------------------------------------------------------
SELECT p.Patient_ID, p.FirstName, p.LastName, s.Protein_Change, s.Coding_Change
FROM Samples s
JOIN Patients p ON p.Patient_ID = s.Patient_ID
WHERE s.Mutation_Gene = 'TP53' AND s.Protein_Change = 'p.R248Q'
ORDER BY p.Patient_ID
LIMIT 10;
-- Expected: 15 matching rows in total (first 10 shown)

-- ------------------------------------------------------------
-- 10c. Filter by chromosome (chromosome 17)
-- ------------------------------------------------------------
SELECT COUNT(*) AS samples_on_chr17
FROM Samples
WHERE Chromosome = '17';
-- Expected (1 row):
--   (1315)

-- ------------------------------------------------------------
-- 10d. Count records with genomic HGVS notation (splice-site variants)
-- ------------------------------------------------------------
SELECT COUNT(*) AS samples_with_genomic_change
FROM Samples
WHERE Genomic_Change IS NOT NULL;
-- Expected (1 row):
--   (40)

-- ------------------------------------------------------------
-- 10e. Researcher view: number of mutations per gene
-- ------------------------------------------------------------
SELECT Mutation_Gene, COUNT(*) AS n_mutations
FROM Samples
WHERE Mutation_Gene IS NOT NULL
GROUP BY Mutation_Gene
ORDER BY n_mutations DESC, Mutation_Gene;
-- Expected: 16 rows, first 3:
--   ('TP53', 1315)
--   ('KRAS', 115)
--   ('FBXW7', 45)

-- ------------------------------------------------------------
-- 10f. Researcher view: mutations in selected genes, split by cancer type
-- ------------------------------------------------------------
SELECT s.Mutation_Gene, ct.Cancer_Name, COUNT(*) AS n
FROM Samples s
JOIN Diagnosis d ON d.Sample_ID = s.Sample_ID
JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
WHERE s.Mutation_Gene IN ('TP53', 'KRAS', 'PIK3CA')
GROUP BY s.Mutation_Gene, ct.Cancer_ID, ct.Cancer_Name
ORDER BY s.Mutation_Gene, ct.Cancer_Name;
-- Expected: 25 rows

-- ------------------------------------------------------------
-- 10g. Mutation types
-- ------------------------------------------------------------
SELECT Mutation_Type, COUNT(*) AS n
FROM Samples
WHERE Mutation_Type IS NOT NULL
GROUP BY Mutation_Type
ORDER BY n DESC, Mutation_Type;
-- Expected (6 rows):
--   ('Missense', 1410)
--   ('Frameshift', 189)
--   ('Nonsense', 81)
--   ('Splice site', 40)
--   ('Splice region (exon end)', 15)
--   ('Synonymous', 3)

-- ------------------------------------------------------------
-- 11a. Staff accounts and roles (the password hash must start with $2y$)
-- ------------------------------------------------------------
SELECT User_ID, Username, Role, Speciality, LEFT(Password_Hash, 4) AS hash_prefix
FROM Users
ORDER BY User_ID;
-- Expected (2 rows):
--   (2, 'researcher1', 'researcher', 'Cancer genomics', '$2y$')
--   (3, 'oncologist1', 'oncologist', 'Medical oncology', '$2y$')

-- ------------------------------------------------------------
-- 11b. Patient records exist independently from staff user accounts
-- ------------------------------------------------------------
SELECT COUNT(*) AS clinical_patient_records
FROM Patients;

-- ------------------------------------------------------------
-- 12. Build a display label from the biomarker name and unit when needed
-- ------------------------------------------------------------
SELECT Biomarker_ID, Name, Unit, CONCAT(Name, ' (', Unit, ')') AS Display_Label
FROM Biomarkers
WHERE Name = 'CA-125';
-- Expected (1 row):
--   (4, 'CA-125', 'U/ml', 'CA-125 (U/ml)')

-- ------------------------------------------------------------
-- 13. Prediction tables: save a prediction, add feedback, then undo everything
--     (MySQL only; the ROLLBACK at the end removes the test rows)
-- ------------------------------------------------------------
START TRANSACTION;

-- Save a test prediction for sample 1
INSERT INTO Predictions (Sample_ID, Pancancer_Probability, Prediction_Result, Model_Version)
VALUES (1, 0.93, 'cancer', 'DeCancer v1.0');
SET @pid = LAST_INSERT_ID();

-- one score per cancer type (Cancer_ID 2-9 = Breast ... Stomach)
INSERT INTO Prediction_Scores (Prediction_ID, Cancer_ID, Probability) VALUES
  (@pid, 2, 0.02), (@pid, 3, 0.81), (@pid, 4, 0.01), (@pid, 5, 0.01),
  (@pid, 6, 0.05), (@pid, 7, 0.02), (@pid, 8, 0.07), (@pid, 9, 0.01);

-- oncologist1 (User 3) says the prediction was correct
INSERT INTO Prediction_Feedback (Prediction_ID, User_ID, Feedback_Type, Feedback_Comment)
VALUES (@pid, 3, 'correct', 'Matches the diagnosis');

-- Top predicted cancer type for this prediction
SELECT ct.Cancer_Name, ps.Probability
FROM Prediction_Scores ps
JOIN Cancer_Types ct ON ct.Cancer_ID = ps.Cancer_ID
WHERE ps.Prediction_ID = @pid
ORDER BY ps.Probability DESC
LIMIT 1;
-- Expected: ('Colorectum', 0.81)

-- Prediction compared with the real diagnosis
SELECT pr.Pancancer_Probability, pr.Prediction_Result, ct.Cancer_Name AS real_diagnosis, f.Feedback_Type
FROM Predictions pr
JOIN Diagnosis d ON d.Sample_ID = pr.Sample_ID
JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
LEFT JOIN Prediction_Feedback f ON f.Prediction_ID = pr.Prediction_ID
WHERE pr.Prediction_ID = @pid;
-- Expected: (0.93, 'cancer', 'Colorectum', 'correct')

-- Deleting the prediction must also delete its scores and feedback (ON DELETE CASCADE)
DELETE FROM Predictions WHERE Prediction_ID = @pid;
SELECT (SELECT COUNT(*) FROM Prediction_Scores WHERE Prediction_ID = @pid) AS scores_left,
       (SELECT COUNT(*) FROM Prediction_Feedback WHERE Prediction_ID = @pid) AS feedback_left;
-- Expected: scores_left = 0, feedback_left = 0

ROLLBACK;

-- ------------------------------------------------------------
-- 14. Constraint tests: every statement below SHOULD FAIL (run them one at a time).
--     If one succeeds, the constraint is missing. Nothing is saved when they fail.
-- ------------------------------------------------------------

-- a) Duplicate sample code -> ERROR 1062 (Duplicate entry)
INSERT INTO Samples (Sample_Code, Patient_ID, Uploaded_By, Age_At_Collection, Omega_Score)
VALUES ('CRC 455 PLS 1', 1, 2, 60, 1.0);

-- b) Sample for a patient that does not exist -> ERROR 1452 (foreign key)
INSERT INTO Samples (Sample_Code, Patient_ID, Uploaded_By, Age_At_Collection, Omega_Score)
VALUES ('TEST-001', 999999, 2, 50, 1.0);

-- c) Probability above 1 -> ERROR 3819 (check constraint chk_pred_prob)
INSERT INTO Predictions (Sample_ID, Pancancer_Probability, Prediction_Result, Model_Version)
VALUES (1, 1.5, 'cancer', 'test');

-- d) Age outside 0-120 -> ERROR 3819 (check constraint chk_samples_age)
INSERT INTO Samples (Sample_Code, Patient_ID, Uploaded_By, Age_At_Collection, Omega_Score)
VALUES ('TEST-002', 1, 2, 200, 1.0);

-- e) Allele frequency above 100 -> ERROR 3819 (check constraint chk_samples_maf)
INSERT INTO Samples (Sample_Code, Patient_ID, Uploaded_By, Age_At_Collection, Omega_Score, Mutant_Allele_Frequency)
VALUES ('TEST-003', 1, 2, 50, 1.0, 150);

-- f) Invalid sex lookup ID -> foreign-key violation
INSERT INTO Patients (FirstName, LastName, Date_of_Birth, Sex_Type_ID, Race_Type_ID)
VALUES ('Test', 'Person', '1980-01-01', 999, 8);

-- g) Delete a patient who still has samples -> ERROR 1451 (ON DELETE RESTRICT)
DELETE FROM Patients WHERE Patient_ID = 1;

-- h) Duplicate username -> ERROR 1062
INSERT INTO Users (Username, Email, Password_Hash, FirstName, LastName, Role)
VALUES ('researcher1', 'other@decancer.demo', 'x', 'Test', 'User', 'researcher');
