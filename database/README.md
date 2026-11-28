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
- `Samples.Sample_ID` is the generated identifier used for predictions; `Samples.Sample_Code` is an optional, unique external code
- `Diagnosis.Patient_ID` → `Patients.Patient_ID` (required)
- `Diagnosis.Cancer_ID` → `Cancer_Types.Cancer_ID` (required)
- `Diagnosis` (`Patient_ID`, `Sample_ID`) → `Samples` (`Patient_ID`, `Sample_ID`) (optional sample; the sample must belong to the diagnosis patient)
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

### 03_import_predictions.sql
**Purpose:** Loads precomputed demo predictions and cancer-specific scores so the portal has prediction results to display without running the model.

**Run:** Optional, after `01_data_import_v1.sql`. This script is not required to create the schema or make new predictions through FastAPI.

**Warning:** The script deletes all existing rows from `Prediction_Scores` and `Predictions` before importing its demo data. Deleting predictions also cascades to their `Prediction_Feedback` records. Run it only on a demo/test database if you are okay losing those records.

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

### migrations/08_remove_user_activity_fields.sql
Drops the unused account activity fields. Run once on an existing database that still has them.

### migrations/09_make_sample_code_optional.sql
Allows `Samples.Sample_Code` to be `NULL` when no external sample code is available. The generated `Sample_ID` remains the identifier used to run predictions; existing sample codes and the unique constraint are preserved. Run once on an existing database after backing it up.

### migrations/10_enforce_diagnosis_sample_patient_match.sql
Ensures a diagnosis can reference only a sample belonging to the same patient. Before running the migration, execute its preflight `SELECT` and confirm it returns no rows; resolve any existing mismatches first. The migration replaces the single-column sample foreign key with a composite foreign key and restricts deletion of samples referenced by diagnoses.

## Setup Instructions

### Using SQLTools (Recommended in VS Code)

1. Install SQLTools and SQLTools MySQL/MariaDB driver
2. Add a MySQL connection in SQLTools (localhost, port 3306)
3. Execute files in order:
   - `00_init_database_v1.sql` → Creates schema
   - `01_data_import_v1.sql` → Loads test data
   - `02_test_data_v1.sql` → Runs test queries
   - `03_import_predictions.sql` → Optionally loads precomputed demo predictions; see the warning above

### Using Command Line

```bash
# Initialize schema
mysql -h 127.0.0.1 -u root -p < 00_init_database_v1.sql

# Import data
mysql -h 127.0.0.1 -u root -p decancer_portal < 01_data_import_v1.sql

# Run test queries
mysql -h 127.0.0.1 -u root -p decancer_portal < 02_test_data_v1.sql
```

To load the optional demo predictions after the sample data:

```bash
mysql -h 127.0.0.1 -u root -p decancer_portal < 03_import_predictions.sql
```

This replaces existing predictions and cascades deletion of their prediction
scores and feedback. Do not run it if those records need to be preserved.

### For Existing Databases

If you have an existing database with the old `Sex` and `Race` columns, run the migration script:

```bash
mysql -h 127.0.0.1 -u root -p decancer_portal < migrations/01_add_race_sex_types_v1.sql
```

For a database that has already been updated through migration 09, run the preflight query at the top of migration 10 separately and confirm it returns no rows. Then apply the migration:

```bash
mysql -h 127.0.0.1 -u root -p decancer_portal < migrations/10_enforce_diagnosis_sample_patient_match.sql
```

Deleting a sample linked to a diagnosis is now blocked. Remove or update the diagnosis's sample reference before deleting that sample.

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

