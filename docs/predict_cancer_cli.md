# Cancer Prediction CLI Tool

## Overview

The `predict_cancer.py` command-line tool generates cancer predictions for biomarker samples stored in the database. It loads pre-trained machine learning models for multiple cancer types, extracts features from the database, generates predictions, and stores results for downstream analysis and reporting.

## Motivation

Previously, the cancer prediction functionality was embedded within the Flask backend application (`backend/app/model.py`), making it difficult to:
- Run predictions independently of the web server
- Integrate predictions into batch processing pipelines
- Test prediction logic in isolation
- Schedule automated prediction runs

This tool **decouples prediction from the backend**, enabling:
- **Standalone execution**: Run predictions via command line or cron jobs
- **Batch processing**: Generate predictions for multiple samples efficiently
- **Better testing**: Unit test prediction logic without server infrastructure
- **Easier debugging**: Detailed logging and error handling for diagnostics
- **Integration flexibility**: Use in workflows, CI/CD pipelines, or scheduled tasks

## Approach

### 1. **Separation of Concerns**
- **Model loading**: Isolated `get_model_info()` to load metadata and model paths
- **Data retrieval**: Dedicated `get_sample_inputs()` to fetch features from the database
- **Prediction generation**: Core `save_test_data()` logic remains unchanged but improved
- **CLI interface**: New `main()` function handles argument parsing and user interaction

### 2. **Robustness & Error Handling**
- Each cancer type is processed independently to prevent one failure from blocking others
- Database commits are wrapped in try-except blocks with rollback on error
- Detailed error messages identify which samples/cancer types failed
- Graceful degradation: Continue processing if individual predictions fail

### 3. **User-Friendly Interface**
- Command-line argument parsing with `argparse` for consistency
- `--verbose` flag for detailed progress output
- Descriptive help messages and usage examples
- Exit codes for integration with scripts and CI/CD pipelines

### 4. **Type Safety & Documentation**
- Type hints on all functions for IDE support and static analysis
- Comprehensive docstrings explaining inputs, outputs, and behavior
- Clear variable names and logical flow for maintainability

### 5. **Configuration Management**
- Reuses existing `backend.app.config` module for model paths and settings
- Respects `MODEL_PATH`, `MYSQL_*` environment variables
- Configurable cancer types and model modes through config dataclasses

## Usage

### Basic Usage

Generate predictions for samples using user ID 1:

```bash
python predict_cancer.py 1
```

### With Progress Output

Display detailed progress information:

```bash
python predict_cancer.py 1 --verbose
```

or

```bash
python predict_cancer.py 1 -v
```

### Integration with Cron

Schedule automated predictions daily at 2 AM:

```bash
0 2 * * * cd /path/to/DECancerPortal && python predict_cancer.py 1 >> predictions.log 2>&1
```

### Exit Codes

- **0**: Success (all or most predictions saved)
- **1**: Failure (critical error preventing execution)

## Requirements

The tool requires:

1. **Python 3.10+** (as per project configuration)
2. **Database Connection**: Valid MySQL connection configured via environment variables
   - `MYSQL_HOST` (default: 127.0.0.1)
   - `MYSQL_PORT` (default: 3306)
   - `MYSQL_DATABASE` (default: decancer)
   - `MYSQL_USER` (default: decancer)
   - `MYSQL_PASSWORD` (default: decancer)

3. **Model Files**: Pre-trained models in configured `MODEL_PATH`
   ```
   MODEL_PATH/
   ├── breast/cancer-healthy/fullModel_everyProtein/
   │   ├── RandomForestModel.pkl
   │   └── RandomForestTest.pkl
   ├── lung/cancer-healthy/fullModel_everyProtein/
   │   ├── RandomForestModel.pkl
   │   └── RandomForestTest.pkl
   └── ... (other cancer types)
   ```

4. **Sample Data**: Samples must exist in the database with:
   - `Samples` table with `Sample_ID`, `Sample_Code`, `Patient_ID`
   - `Patients` table with `Patient_ID`, `Race_Type_ID`, `Sex_Type_ID`
   - `Biomarker_Measurements` table with features
   - `Biomarkers` table with `Biomarker_ID`, `Name`, and `Unit`

## Output

The tool stores predictions in two database tables:

### Predictions Table
Stores overall cancer prediction for each sample:

| Column | Type | Purpose |
|--------|------|---------|
| Sample_ID | INT | Reference to the sample |
| User_ID | INT | User who generated the prediction |
| Date | TIMESTAMP | When prediction was generated |
| Pancancer_Probability | FLOAT | Probability score from pancancer model |
| Prediction_Result | VARCHAR | "cancer" or "cancer-free" |
| Highest_Protein_Influence | FLOAT | Strongest feature importance value |
| Highest_Biomarker_ID | INT | Biomarker with highest influence |
| Model_Version | VARCHAR | Version identifier (e.g., "v1.0") |

### Prediction_Scores Table
Stores per-cancer-type probability scores:

| Column | Type | Purpose |
|--------|------|---------|
| Prediction_ID | INT | Reference to parent prediction |
| Cancer_ID | INT | Cancer type (1=Breast, 2=Colorectum, etc., 10=Pancancer) |
| Probability | FLOAT | Model probability for this cancer type |

## Cancer Type Mapping

The tool uses the following cancer type IDs (from `src.cancerseek.constants.CancerType`):

| ID | Cancer Type |
|----|-------------|
| 1 | Breast |
| 2 | Colorectum |
| 3 | Esophagus |
| 4 | Liver |
| 5 | Lung |
| 6 | Ovary |
| 7 | Pancreas |
| 8 | Stomach |
| 10 | Pancancer |

## Example Workflow

```bash
# 1. Set up environment variables
export MYSQL_HOST=localhost
export MYSQL_DATABASE=decancer
export MODEL_PATH=Results/test

# 2. Run predictions for user 42
python predict_cancer.py 42 --verbose

# 3. Output
# Loading models for 9 cancer types...
#   Processing BREAST...
#   Processing COLORECTUM...
#   ...
# Saving predictions to database...
#   ✓ Saved prediction for sample BR001
#   ✓ Saved prediction for sample BR002
#   ...
# ✓ Successfully saved 150 predictions
```

## Troubleshooting

### "Model file not found"
Check that `MODEL_PATH` environment variable is correctly set and model files exist:
```bash
ls -la $MODEL_PATH/*/cancer-healthy/fullModel_everyProtein/
```

### "Sample not found in database"
Verify the sample exists in the database:
```bash
mysql -h $MYSQL_HOST -u $MYSQL_USER -p $MYSQL_DATABASE \
  -e "SELECT * FROM Samples WHERE Sample_Code='YOUR_SAMPLE_CODE';"
```

### "Database connection failed"
Check MySQL connection details:
```bash
mysql -h $MYSQL_HOST -u $MYSQL_USER -p $MYSQL_DATABASE -e "SELECT 1;"
```

### Database commit failures
Check for:
- Foreign key constraint violations
- Duplicate prediction records (may need to delete old predictions first)
- Database permissions for INSERT operations

## Security Considerations

⚠️ **SQL Injection Risk**: The current implementation uses string interpolation for SQL queries. For production deployment, migrate to parameterized queries:

```python
# BEFORE (vulnerable)
cur.execute(f"SELECT * FROM Samples WHERE Sample_Code='{sample_code}'")

# AFTER (safe)
cur.execute("SELECT * FROM Samples WHERE Sample_Code=%s", (sample_code,))
```

## Future Improvements

1. **Parameterized Queries**: Replace f-string SQL with parameter binding
2. **Batch Operations**: Use `executemany()` for more efficient database writes
3. **Async Support**: Enable concurrent prediction generation for large batches
4. **Caching**: Cache model objects between runs to reduce startup time
5. **Logging**: Integrate with application logging framework (e.g., `logging` module)
6. **Data Validation**: Add input validation for sample data before prediction
7. **Performance Metrics**: Track and report prediction speed and accuracy

## Related Files

- **Backend Model Functions**: [`backend/app/model.py`](../backend/app/model.py)
- **Configuration**: [`backend/app/config.py`](../backend/app/config.py)
- **Database Schema**: [`database/00_init_database_v1.sql`](../database/00_init_database_v1.sql)
- **Model Pipeline**: [`docs/model_pipeline.md`](./model_pipeline.md)
