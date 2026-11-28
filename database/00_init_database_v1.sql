-- ============================================================
-- Cancer Type Prediction System - database schema (MySQL 8.0.16+)
-- 12 tables, InnoDB, utf8mb4
-- All patient names, photos, dates of birth and countries are synthetic.
-- ============================================================

DROP DATABASE IF EXISTS decancer_portal;
CREATE DATABASE decancer_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE decancer_portal;

-- ------------------------------------------------------------
-- 1a. Sex_Types (lookup table)
-- ------------------------------------------------------------
CREATE TABLE Sex_Types (
  Sex_Type_ID INT AUTO_INCREMENT PRIMARY KEY,
  Sex_Name    VARCHAR(50) NOT NULL,
  CONSTRAINT uq_sex_name UNIQUE (Sex_Name)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 1b. Race_Types (lookup table)
-- ------------------------------------------------------------
CREATE TABLE Race_Types (
  Race_Type_ID INT AUTO_INCREMENT PRIMARY KEY,
  Race_Name    VARCHAR(100) NOT NULL,
  CONSTRAINT uq_race_name UNIQUE (Race_Name)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 1. Patients
-- ------------------------------------------------------------
CREATE TABLE Patients (
  Patient_ID     INT AUTO_INCREMENT PRIMARY KEY,
  FirstName      VARCHAR(50)  NOT NULL,
  LastName       VARCHAR(50)  NOT NULL,
  Date_of_Birth  DATE         NOT NULL,
  Sex_Type_ID    INT          NOT NULL,
  Race_Type_ID   INT          NOT NULL,
  Country        VARCHAR(60)  NULL,
  Photo          VARCHAR(255) NULL,            -- file path, not the image itself
  Created_Date   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_patients_sex FOREIGN KEY (Sex_Type_ID)
    REFERENCES Sex_Types(Sex_Type_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_patients_race FOREIGN KEY (Race_Type_ID)
    REFERENCES Race_Types(Race_Type_ID) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. Cancer_Types
-- ------------------------------------------------------------
CREATE TABLE Cancer_Types (
  Cancer_ID      INT AUTO_INCREMENT PRIMARY KEY,
  Cancer_Code    VARCHAR(20) NOT NULL,        -- equals the model output key, e.g. 'Breast'
  Cancer_Name    VARCHAR(60) NOT NULL,
  is_cancer_free TINYINT(1)  NOT NULL DEFAULT 0,
  Description    TEXT        NULL,
  CONSTRAINT uq_cancer_code UNIQUE (Cancer_Code),
  CONSTRAINT chk_cancer_free CHECK (is_cancer_free IN (0,1))
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. Users
-- ------------------------------------------------------------
CREATE TABLE Users (
  User_ID       INT AUTO_INCREMENT PRIMARY KEY,
  Username      VARCHAR(50)  NOT NULL,
  Email         VARCHAR(100) NOT NULL,
  Password_Hash VARCHAR(255) NOT NULL,        -- PHP password_hash() output
  FirstName     VARCHAR(50)  NOT NULL,
  LastName      VARCHAR(50)  NOT NULL,
  Role          ENUM('oncologist','researcher','admin') NOT NULL,
  Speciality    VARCHAR(100) NULL,
  Created_Date  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_users_username   UNIQUE (Username),
  CONSTRAINT uq_users_email      UNIQUE (Email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. Samples
-- ------------------------------------------------------------
CREATE TABLE Samples (
  Sample_ID               INT AUTO_INCREMENT PRIMARY KEY,
  Sample_Code             VARCHAR(30) NULL,       -- Optional external identifier
  Patient_ID              INT         NOT NULL,
  Uploaded_By             INT         NOT NULL,
  Collection_Date         DATE        NULL,
  Sample_Type             VARCHAR(50) NULL,
  Age_At_Collection       INT         NOT NULL,   -- model input: Age
  Omega_Score             DOUBLE      NOT NULL,   -- model input: Ω score
  Mutation_Gene           VARCHAR(30) NULL,
  Mutation_Type           VARCHAR(50) NULL,
  Protein_Change          VARCHAR(50) NULL,
  Coding_Change           VARCHAR(50) NULL,
  Genomic_Change          VARCHAR(100) NULL,
  Chromosome              VARCHAR(5)  NULL,
  Mutant_Allele_Frequency DOUBLE      NULL,       -- percent (0-100), as in CancerSEEK
  CONSTRAINT uq_samples_code     UNIQUE (Sample_Code),
  CONSTRAINT uq_samples_patient_sample UNIQUE (Patient_ID, Sample_ID),
  CONSTRAINT fk_samples_patient  FOREIGN KEY (Patient_ID)
    REFERENCES Patients(Patient_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_samples_user     FOREIGN KEY (Uploaded_By)
    REFERENCES Users(User_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_samples_age     CHECK (Age_At_Collection BETWEEN 0 AND 120),
  CONSTRAINT chk_samples_maf     CHECK (Mutant_Allele_Frequency IS NULL
                                        OR Mutant_Allele_Frequency BETWEEN 0 AND 100),
  INDEX idx_samples_mut_gene (Mutation_Gene)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. Diagnosis
-- ------------------------------------------------------------
CREATE TABLE Diagnosis (
  Diagnosis_ID     INT AUTO_INCREMENT PRIMARY KEY,
  Patient_ID       INT          NOT NULL,
  Cancer_ID        INT          NOT NULL,
  Sample_ID        INT          NULL,
  Diagnosis_Date   DATE         NULL,
  Cancer_Stage     VARCHAR(20)  NULL,          -- AJCC stage; NULL for Normal
  Histopathology   VARCHAR(255) NULL,
  Treatment        VARCHAR(255) NULL,
  Diagnosis_Status ENUM('confirmed','suspected','ruled_out') NOT NULL DEFAULT 'confirmed',
  CONSTRAINT fk_diag_patient FOREIGN KEY (Patient_ID)
    REFERENCES Patients(Patient_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_diag_cancer  FOREIGN KEY (Cancer_ID)
    REFERENCES Cancer_Types(Cancer_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_diag_sample_patient FOREIGN KEY (Patient_ID, Sample_ID)
    REFERENCES Samples(Patient_ID, Sample_ID) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. Biomarkers (39 proteins, seeded in model feature order)
-- ------------------------------------------------------------
CREATE TABLE Biomarkers (
  Biomarker_ID       INT AUTO_INCREMENT PRIMARY KEY,
  Name               VARCHAR(60) NOT NULL,
  Unit               VARCHAR(10) NOT NULL,
  Category           VARCHAR(20) NOT NULL DEFAULT 'protein',
  CONSTRAINT uq_biomarkers_name UNIQUE (Name)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. Biomarker_Measurements (link table, 39 rows per sample)
-- ------------------------------------------------------------
CREATE TABLE Biomarker_Measurements (
  Sample_ID    INT    NOT NULL,
  Biomarker_ID INT    NOT NULL,
  Value        DOUBLE NOT NULL,
  PRIMARY KEY (Sample_ID, Biomarker_ID),
  CONSTRAINT fk_bm_sample    FOREIGN KEY (Sample_ID)
    REFERENCES Samples(Sample_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bm_biomarker FOREIGN KEY (Biomarker_ID)
    REFERENCES Biomarkers(Biomarker_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_bm_value    CHECK (Value >= 0),
  INDEX idx_bm_biomarker (Biomarker_ID)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. Predictions
-- ------------------------------------------------------------
CREATE TABLE Predictions (
  Prediction_ID             INT AUTO_INCREMENT PRIMARY KEY,
  Sample_ID                 INT         NOT NULL,
  Date                      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- timestamp
  Pancancer_Probability     DOUBLE      NOT NULL,
  Prediction_Result         ENUM('cancer','cancer-free') NOT NULL,
  Highest_Protein_Influence DOUBLE      NULL,
  Highest_Biomarker_ID      INT         NULL,
  Model_Version             VARCHAR(30) NOT NULL,
  CONSTRAINT fk_pred_sample    FOREIGN KEY (Sample_ID)
    REFERENCES Samples(Sample_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pred_biomarker FOREIGN KEY (Highest_Biomarker_ID)
    REFERENCES Biomarkers(Biomarker_ID) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_pred_prob     CHECK (Pancancer_Probability BETWEEN 0 AND 1)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. Prediction_Scores (link table, 8 rows per prediction)
-- ------------------------------------------------------------
CREATE TABLE Prediction_Scores (
  Prediction_ID INT    NOT NULL,
  Cancer_ID     INT    NOT NULL,
  Probability   DOUBLE NOT NULL,
  PRIMARY KEY (Prediction_ID, Cancer_ID),
  CONSTRAINT fk_ps_prediction FOREIGN KEY (Prediction_ID)
    REFERENCES Predictions(Prediction_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ps_cancer FOREIGN KEY (Cancer_ID)
    REFERENCES Cancer_Types(Cancer_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_ps_prob CHECK (Probability BETWEEN 0 AND 1)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. Prediction_Feedback
-- ------------------------------------------------------------
CREATE TABLE Prediction_Feedback (
  Feedback_ID         INT AUTO_INCREMENT PRIMARY KEY,
  Prediction_ID       INT  NOT NULL,
  User_ID             INT  NOT NULL,
  Feedback_Type       ENUM('correct','incorrect','unsure') NOT NULL,
  Suggested_Cancer_ID INT  NULL,
  Feedback_Comment    TEXT NULL,
  Created_Date        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_feedback_pred_user UNIQUE (Prediction_ID, User_ID),
  CONSTRAINT fk_fb_prediction FOREIGN KEY (Prediction_ID)
    REFERENCES Predictions(Prediction_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fb_user       FOREIGN KEY (User_ID)
    REFERENCES Users(User_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_fb_cancer     FOREIGN KEY (Suggested_Cancer_ID)
    REFERENCES Cancer_Types(Cancer_ID) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
