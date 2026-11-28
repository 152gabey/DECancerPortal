-- Migrate an existing legacy database whose Patients table has Sex and Race
-- columns. Run once, after backing up the database. Do not run on a fresh
-- schema created by 00_init_database_v1.sql; that schema already has lookup
-- tables and foreign-key columns.
USE decancer_portal;

-- Keep lookup IDs stable; these IDs are used by the prediction feature mapping.
CREATE TABLE IF NOT EXISTS Sex_Types (
  Sex_Type_ID INT PRIMARY KEY,
  Sex_Name    VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO Sex_Types (Sex_Type_ID, Sex_Name) VALUES
  (1, 'Female'),
  (2, 'Male'),
  (3, 'Other');

CREATE TABLE IF NOT EXISTS Race_Types (
  Race_Type_ID INT PRIMARY KEY,
  Race_Name    VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO Race_Types (Race_Type_ID, Race_Name) VALUES
  (1, 'Asian'),
  (2, 'Black'),
  (3, 'Black/Hispanic'),
  (4, 'Caucasian'),
  (5, 'Caucasian/Hispanic'),
  (6, 'Hispanic'),
  (7, 'Other'),
  (8, 'Unknown');

ALTER TABLE Patients
  ADD COLUMN Sex_Type_ID INT NULL AFTER Sex,
  ADD COLUMN Race_Type_ID INT NULL AFTER Race;

-- Legacy source codes are zero-based: Female=0/Male=1; race codes are
-- Asian=0 through Other=6, with -1 and 7 representing Unknown. Text values
-- from the earlier import format are handled too.
UPDATE Patients p
SET p.Sex_Type_ID = CASE LOWER(TRIM(CAST(p.Sex AS CHAR)))
  WHEN '0' THEN 1
  WHEN 'female' THEN 1
  WHEN 'f' THEN 1
  WHEN '1' THEN 2
  WHEN 'male' THEN 2
  WHEN 'm' THEN 2
  ELSE 3
END
WHERE p.Sex_Type_ID IS NULL;

UPDATE Patients p
SET p.Race_Type_ID = CASE LOWER(TRIM(CAST(p.Race AS CHAR)))
  WHEN '0' THEN 1
  WHEN 'asian' THEN 1
  WHEN '1' THEN 2
  WHEN 'black' THEN 2
  WHEN '2' THEN 3
  WHEN 'black/hispanic' THEN 3
  WHEN '3' THEN 4
  WHEN 'caucasian' THEN 4
  WHEN '4' THEN 5
  WHEN 'caucasian/hispanic' THEN 5
  WHEN '5' THEN 6
  WHEN 'hispanic' THEN 6
  WHEN '6' THEN 7
  WHEN 'other' THEN 7
  WHEN '-1' THEN 8
  WHEN '7' THEN 8
  WHEN 'unknown' THEN 8
  ELSE 8
END
WHERE p.Race_Type_ID IS NULL;

ALTER TABLE Patients
  MODIFY COLUMN Sex_Type_ID INT NOT NULL,
  MODIFY COLUMN Race_Type_ID INT NOT NULL,
  ADD CONSTRAINT fk_patients_sex FOREIGN KEY (Sex_Type_ID)
    REFERENCES Sex_Types(Sex_Type_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_patients_race FOREIGN KEY (Race_Type_ID)
    REFERENCES Race_Types(Race_Type_ID) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Keep the old Sex and Race columns until the application has been verified.
