-- Migration script to update existing database schema
-- Adds Sex_Types and Race_Types lookup tables and converts Patients table
-- to use foreign keys instead of direct columns

-- Create Sex_Types lookup table
CREATE TABLE IF NOT EXISTS Sex_Types (
  Sex_Type_ID INT AUTO_INCREMENT PRIMARY KEY,
  Sex_Name    VARCHAR(50) NOT NULL,
  CONSTRAINT uq_sex_name UNIQUE (Sex_Name)
) ENGINE=InnoDB;

-- Pre-populate with known values
INSERT IGNORE INTO Sex_Types (Sex_Name) VALUES ('Female'), ('Male'), ('Other');

-- Create Race_Types lookup table
CREATE TABLE IF NOT EXISTS Race_Types (
  Race_Type_ID INT AUTO_INCREMENT PRIMARY KEY,
  Race_Name    VARCHAR(100) NOT NULL,
  CONSTRAINT uq_race_name UNIQUE (Race_Name)
) ENGINE=InnoDB;

-- Pre-populate with known values from CSV
INSERT IGNORE INTO Race_Types (Race_Name) VALUES 
  ('Asian'),
  ('Black'),
  ('Black/Hispanic'),
  ('Caucasian'),
  ('Caucasian/Hispanic'),
  ('Hispanic'),
  ('Other'),
  ('Unknown');

-- Alter Patients table to add foreign key columns
ALTER TABLE Patients 
ADD COLUMN Sex_Type_ID INT NULL AFTER Sex,
ADD COLUMN Race_Type_ID INT NULL AFTER Race;

-- Migrate existing data
UPDATE Patients p
SET p.Sex_Type_ID = (
  SELECT Sex_Type_ID FROM Sex_Types 
  WHERE Sex_Name = p.Sex
)
WHERE p.Sex_Type_ID IS NULL AND p.Sex IS NOT NULL;

UPDATE Patients p
SET p.Race_Type_ID = (
  SELECT Race_Type_ID FROM Race_Types 
  WHERE Race_Name = p.Race
)
WHERE p.Race_Type_ID IS NULL AND p.Race IS NOT NULL;

-- Set values to 'Unknown' for any remaining NULL Race_Type_ID
UPDATE Patients p
SET p.Race_Type_ID = (
  SELECT Race_Type_ID FROM Race_Types 
  WHERE Race_Name = 'Unknown'
)
WHERE p.Race_Type_ID IS NULL;

-- Make foreign key columns NOT NULL
ALTER TABLE Patients 
MODIFY COLUMN Sex_Type_ID INT NOT NULL,
MODIFY COLUMN Race_Type_ID INT NOT NULL;

-- Add foreign key constraints
ALTER TABLE Patients
ADD CONSTRAINT fk_patients_sex FOREIGN KEY (Sex_Type_ID)
  REFERENCES Sex_Types(Sex_Type_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT fk_patients_race FOREIGN KEY (Race_Type_ID)
  REFERENCES Race_Types(Race_Type_ID) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Optional: Drop old columns after migration is confirmed
-- ALTER TABLE Patients DROP COLUMN Sex;
-- ALTER TABLE Patients DROP COLUMN Race;
