# DEcancer Pipelines

## Repository layout

The original analysis pipelines remain at the repository root and in `src/`.
The portal stack is organised as follows:

```text
backend/
  app/
    config.py       # environment-based settings
    database.py     # MySQL Connector/Python connection helpers
    main.py         # FastAPI application and health endpoints
frontend/
  index.html
  styles.css
  app.js
database/
  init.sql          # starter MySQL schema
docker-compose.yml # local MySQL service
```

This separation keeps reproducible research scripts independent from the
portal service while providing a place to add API routes and client features.

## Portal stack quick start

1. Copy `.env.example` to `.env` and change credentials for a non-local
   deployment.
2. Start MySQL:

   ```sh
   docker compose up -d mysql
   ```

3. Create a portal virtual environment and install the API dependencies:

   ```sh
   python -m venv .venv
   . .venv/bin/activate
   python -m pip install -r backend/requirements.txt
   ```

4. Start the FastAPI development server:

   ```sh
   uvicorn backend.app.main:app --reload
   ```

   The API is available at `http://127.0.0.1:8000`. Check
   `http://127.0.0.1:8000/api/health` for liveness and
   `http://127.0.0.1:8000/api/health/database` for MySQL connectivity.

5. Serve `frontend/` with any static web server during development, for
   example `python -m http.server 8080 --directory frontend`. Configure that
   server to proxy `/api` to the FastAPI service, or open the client from the
   same origin when the API serves the frontend.

# Installation
Create a virtual environment and install the required dependencies.

```
python -m venv venv
venv/bin/activate
python -m pip install --upgrade pip
pip install -r requirements.txt
```

# Usage
## Nanoparticles
Processed data is provided in `perseus_imputation/` for each of the 5 SPIONS and diluted plasma (DP). Note here that hyperparameters need to be specified for each algorithm which can be added to `src/parameters.py` when performing hyperparameter optimisation. Also not included is the steps taken for feature selection. Implementation fits between running the pipeline initially and before hyperparameter tuning found in `main.py`.

Hyperparameters are in the form of a tuple:
```py
params = (("name", (value1, value2, value3)),)
```

Run the pipeline with `python main.py`

## CancerSEEK
Data is found in `cancerseek_data/`. Similar to Nanoparticles, hyperparameters must be specified for hyperparameter optimisation found in `src/cancerseek/constants.py`. Additionally, the recursive feature elimination step has not been included for feature selection. Implementation can be added in `src/cancerseek/pipeline.py`

This pipeline is a 3-step process outlined in `main_cancerseek.py` with each section requiring different options. Run the pipeline with `python main_cancerseek.py`
