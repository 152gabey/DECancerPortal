# DECancer Portal and Research Pipelines

## Repository layout

The research pipelines remain at the repository root and in `src/`. The web
portal uses PHP, MySQL, and a FastAPI prediction service:

```text
backend/
  app/
    main.py             # FastAPI prediction and health endpoints
    run_prediction.py   # Saved-sample inference and persistence
frontend/
  *.php                # Authenticated portal pages
  includes/            # Shared PHP helpers and layout
  css/style.css
database/
  00_init_database_v1.sql
  01_data_import_v1.sql
  02_test_data_v1.sql
  03_import_predictions.sql
  migrations/
docs/
```

The research scripts and web portal share the database and trained model
artifacts, while PHP delegates prediction requests to FastAPI.

# Usage

## Portal stack quick start

### 1. Environment configuration

Copy `.env.example` to `.env` and update credentials for your deployment:

```sh
cp .env.example .env
```

Update the username, and password.

### 2. Database setup using SQLTools (Recommended)

This project uses SQLTools for database management within VS Code. Follow these
steps to activate and run the database:

#### Install SQLTools extension

1. Open VS Code and navigate to Extensions (`Ctrl+Shift+X` / `Cmd+Shift+X`)
2. Search for "SQLTools" by Matheus Teixeira
3. Click **Install**
4. Also install the **SQLTools MySQL/MariaDB** driver extension

#### Add database connection

1. Open the SQLTools explorer panel (database icon in the left sidebar)
2. Click **Add Connection** or the `+` icon
3. Select **MySQL** as the database type
4. Configure the connection:
   - **Connection name:** `DECancer Local` (or your preferred name)
   - **Host:** `127.0.0.1` (for local MySQL)
   - **Port:** `3306` (default MySQL port)
   - **Database:** Leave empty initially
   - **Username:** `root` (or your MySQL user)
   - **Password:** Your MySQL password
5. Click **Save Connection**

#### Initialize the database

1. In the SQLTools explorer, expand the connection you just created
2. Right-click the connection name and select **New Query**
3. Open the first initialization file: [database/00_init_database_v1.sql](database/00_init_database_v1.sql)
4. Select all content (`Ctrl+A` / `Cmd+A`) and execute the query:
   - Click the **Execute** button (play icon) or press `Ctrl+Shift+Enter` / `Cmd+Shift+Enter`
   - This creates the `decancer_portal` database and schema
5. Repeat for [database/01_data_import_v1.sql](database/01_data_import_v1.sql) to populate test data
6. Optionally run [database/02_test_data_v1.sql](database/02_test_data_v1.sql) for additional queries
7. Optionally run [database/03_import_predictions.sql](database/03_import_predictions.sql) after the data import to load precomputed demo predictions; it is not needed to run new predictions.
   **Warning:** It replaces existing predictions and cascades deletion of their scores and feedback. Only run it on a demo/test database if you are okay losing those records.

#### Verify the database connection

1. In SQLTools explorer, expand your connection
2. You should see the `decancer_portal` database listed
3. Expand it to view tables such as `Patients`, `Samples`,
   `Biomarker_Measurements`, `Predictions`, and `Prediction_Scores`.

**Note:** The data import scripts have been fixed to respect foreign key dependencies.
Patients must be inserted before Users, Samples, and Diagnosis records. If you're using
an older version of `01_data_import_v1.sql`, update it from this repository.

### Alternative: Command-line database setup

If you prefer the command line, initialize the database directly:

```sh
mysql -h 127.0.0.1 -u root -p < database/00_init_database_v1.sql
mysql -h 127.0.0.1 -u root -p decancer_portal < database/01_data_import_v1.sql
mysql -h 127.0.0.1 -u root -p decancer_portal < database/02_test_data_v1.sql
```

To load demo predictions after the sample data, optionally run:

```sh
mysql -h 127.0.0.1 -u root -p decancer_portal < database/03_import_predictions.sql
```

This replaces existing predictions and cascades deletion of their prediction
scores and feedback. Only run it on a demo/test database if you are okay losing
those records.

### 3. Create a Python virtual environment

```sh
python -m venv .venv
source .venv/bin/activate  # On Windows: .venv\Scripts\activate
pip install --upgrade pip
pip install -r requirements.txt
```

The dependency pins support Python 3.15.2. Use a fresh virtual environment
so older NumPy and SciPy installations are not reused.

### 4. Start the FastAPI development server

```sh
uvicorn backend.app.main:app --reload
```

The API is available at `http://127.0.0.1:8000`. Check:
- `http://127.0.0.1:8000/api/health` for liveness
- `http://127.0.0.1:8000/api/health/database` for MySQL connectivity

### 5. Start the PHP portal

```sh
php -S 127.0.0.1:3000 -t frontend
```

Open <http://127.0.0.1:3000/login.php>. PHP must have the `pdo_mysql` and
`curl` extensions enabled. The portal uses `PREDICTION_API_URL` to contact
FastAPI; it defaults to `http://127.0.0.1:8000`. See
[docs/model_integration.md](docs/model_integration.md) for the complete
PHP-to-FastAPI flow.


### Test accounts

After importing `01_data_import_v1.sql`, these demo accounts are ready to use:

| Username | Role | Password |
|---|---|---|
| `researcher1` | Researcher | `TestPassword123` |
| `oncologist1` | Oncologist | `TestPassword123` |

These credentials are for local demonstration only. Do not reuse them or expose
the seeded database on a public network in a production environment.

### 6. Generate predictions using the CLI tool (Optional)

The standalone prediction tool enables batch processing and automated scheduling:

```sh
# Generate predictions for configured samples with progress output
python predict_cancer.py --verbose

# Schedule daily predictions at 2 AM using cron
# 0 2 * * * cd /path/to/DECancerPortal && python predict_cancer.py >> predictions.log 2>&1
```

See [docs/predict_cancer_cli.md](docs/predict_cancer_cli.md) for detailed documentation,
including configuration, output schema, troubleshooting, and integration examples.

## Nanoparticles

Processed data is provided in `perseus_imputation/` for each of the 5 SPIONS and diluted plasma (DP). Note here that hyperparameters need to be specified for each algorithm which can be added to `src/parameters.py` when performing hyperparameter optimisation. Also not included is the steps taken for feature selection. Implementation fits between running the pipeline initially and before hyperparameter tuning found in `main.py`.

Hyperparameters are in the form of a tuple:

```py
params = (("name", (value1, value2, value3)),)
```

Run the pipeline with `python main.py`

## CancerSEEK

Data is found in `cancerseek_data/`. Hyperparameters must be specified in the form of a tuple:

```py
params = (("name", (value1, value2, value3)),)
```

This pipeline is a 3-step process outlined in `main_cancerseek.py` with each section requiring different options. Run the pipeline with `python main_cancerseek.py`
