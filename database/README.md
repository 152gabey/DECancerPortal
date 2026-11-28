# Database Directory Guide

This directory contains MySQL initialization and data import scripts for the DECancer Portal.

## Files

### 00_init_database_v1.sql
**Purpose:** Creates the database schema and table structure

**What it does:**
- Creates the `decancer_portal` database (UTF-8 unicode support)
- Defines 12 tables with appropriate data types and constraints:
  - `Sex_Types` - Lookup table for sex values (Female, Male)
  - `Race_Types` - Lookup table for race values (8 categories from CSV data)
  - `Patients` - Patient demographic information (now uses foreign keys for sex and race)
  - `Cancer_Types` - Supported cancer types
  - `Users` - Staff accounts (researcher, oncologist, admin roles; no patient login)
  - `Samples` - Biosamples from patients
  - `Diagnosis` - Cancer diagnoses and staging
  - `Biomarkers` - Blood biomarker definitions
  - `Biomarker_Measurements` - Measured biomarker values per sample
  - `Predictions` - Model prediction results
  - `Prediction_Scores` - Cancer-specific prediction scores
  - `Prediction_Feedback` - User feedback on predictions

**Key Changes (v1 Update):**
- Added `Sex_Types` and `Race_Types` lookup tables for data normalization
- Patients table now uses `Sex_Type_ID` and `Race_Type_ID` foreign keys instead of direct columns
- Pre-populated sex and race lookup tables with values from the CSV data:
  - **Sex:** Female, Male
  - **Race:** Asian, Black, Black/Hispanic, Caucasian, Caucasian/Hispanic, Hispanic, Other, Unknown

**Foreign Key Relationships:**
- `Patients.Sex_Type_ID` → `Sex_Types.Sex_Type_ID` (required)
- `Patients.Race_Type_ID` → `Race_Types.Race_Type_ID` (required)
- Patient clinical records remain separate from staff login accounts
- `Samples.Patient_ID` → `Patients.Patient_ID` (required)
- `Diagnosis.Patient_ID` → `Patients.Patient_ID` (required)
- `Diagnosis.Cancer_ID` → `Cancer_Types.Cancer_ID` (required)
- `Biomarker_Measurements.Sample_ID` → `Samples.Sample_ID` (required)

**Run first:** Yes, before any data import

### 01_data_import_v1.sql
**Purpose:** Populates the database with test data

**Data included:**
- Cancer types (Normal, Breast, Colorectum, Esophagus, Liver, Lung, Ovary, Pancreas, Stomach)
- Biomarkers (39 blood biomarkers from the CancerSEEK panel)
- ~500 synthetic patients with realistic demographics
- ~1800 biosamples with mutation data
- Diagnoses linked to patients, samples, and cancer types
- Biomarker measurements (39 values per sample)

**Run second:** After `00_init_database_v1.sql`

### 02_test_data_v1.sql
**Purpose:** Provides example queries and test operations

**What it does:**
- Sample SELECT queries demonstrating data relationships
- Useful for testing the database connection
- Reference queries for common operations

**Run:** Optional, after `01_data_import_v1.sql` for testing

### migrations/01_add_race_sex_types_v1.sql
**Purpose:** Migration script for existing databases

**What it does:**
- Creates `Sex_Types` and `Race_Types` lookup tables
- Migrates existing Patients table data to use foreign keys
- Maintains backward compatibility with existing data
- Adds foreign key constraints after data migration

**Run:** Only if you have an existing database that needs to be updated. Skip if using `00_init_database_v1.sql` for a fresh installation.

The fresh-install import maps the source's zero-based sex and race codes to the
one-based IDs in `Sex_Types` and `Race_Types`, and inserts patients using
`Sex_Type_ID` and `Race_Type_ID`.

**Migration Steps:**
1. Run this script on an existing database
2. Verify data integrity with test queries
3. Optionally drop old Sex and Race columns after confirming migration success

### migrations/02_split_sample_mutation_details.sql
**Purpose:** Adds `Protein_Change`, `Coding_Change`, and `Genomic_Change` to `Samples` and backfills them from the old `Mutation_Detail` values. It preserves the legacy columns during this first migration phase so the copied values can be reviewed before they are dropped in a follow-up migration.

**Run:** Once, on a database that still has the previous `Samples` schema. Back up the database first, then verify the final review query returns no rows.

After that verification, run `migrations/03_drop_legacy_mutation_columns.sql` to remove `Mutation_Detail`, `Position`, and their old index.

### migrations/04_drop_model_feature_name.sql
Removes the generated `Biomarkers.Model_Feature_Name` column after the CLI and SQL examples have been updated. Run once on a database that still has the column.

### migrations/05_simplify_predictions.sql
Removes the undefined `Predictions.Missing_Cancer_Score` and `Predictions.User_ID` fields, including the user foreign key. Run once on a database that still has both columns, after backing it up.

### migrations/06_remove_seed_admin_and_patient_accounts.sql
Shows sample-uploader and feedback reference counts, then removes the seeded `admin` and `patient1` accounts. Review the counts first; preserve or reassign any referenced records rather than deleting account history.

### migrations/07_remove_patient_user_link.sql
Drops `Users.Patient_ID` and removes `patient` from the `Users.Role` enum. Run only after migration 06 succeeds and no patient-role accounts remain. Patient clinical records are retained.

## Setup Instructions

### Using SQLTools (Recommended in VS Code)

1. Install SQLTools and SQLTools MySQL/MariaDB driver
2. Add a MySQL connection in SQLTools (localhost, port 3306)
3. Execute files in order:
   - `00_init_database_v1.sql` → Creates schema
   - `01_data_import_v1.sql` → Loads test data
   - `02_test_data_v1.sql` → Runs test queries

### Using Command Line

```bash
# Initialize schema
mysql -h 127.0.0.1 -u root -p < 00_init_database_v1.sql

# Import data
mysql -h 127.0.0.1 -u root -p decancer_portal < 01_data_import_v1.sql

# Run test queries
mysql -h 127.0.0.1 -u root -p decancer_portal < 02_test_data_v1.sql
```

### For Existing Databases

If you have an existing database with the old `Sex` and `Race` columns, run the migration script:

```bash
mysql -h 127.0.0.1 -u root -p decancer_portal < migrations/01_add_race_sex_types_v1.sql
```

## Data Characteristics

- **Synthetic Data:** All patient names, photos, dates of birth, and countries are synthetically generated
- **CancerSEEK Biomarkers:** Blood biomarker panel from Johns Hopkins CancerSEEK study
- **Sample Size:** ~500 patients with ~1800 biosamples total
- **Coverage:** 8 cancer types plus healthy controls
- **Mutations:** Representative mutation data for each sample
- **Categorical Values:** Normalized sex and race values using lookup tables

## Schema Design Notes

- Uses InnoDB for transaction support and foreign key enforcement
- UTF-8mb4 character set for international support
- Timestamps with CURRENT_TIMESTAMP for audit trails
- Lookup tables for categorical data (Sex, Race) for data normalization
- Cascade/set null options on foreign keys for data consistency
- RESTRICT on lookup table foreign keys prevents accidental deletion of reference data

## Python Integration

The backend uses static OrdinalEncoders for consistent categorical encoding:
- See `src/static_encoders.py` for the encoder definitions
- Sex and race are encoded using fixed categories from the CSV data
- Encoders handle unknown values by mapping them to -1 (indicating "Other")
- This ensures consistent encoding across model training and inference pipelines

