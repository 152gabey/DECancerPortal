"""FastAPI application entry point."""

from pathlib import Path
import logging

from fastapi import FastAPI
from fastapi import HTTPException
from fastapi.staticfiles import StaticFiles
from mysql.connector import Error as MySQLError

from .run_prediction import InvalidSampleError, run_prediction
from .config import settings
from .database import create_connection

app = FastAPI(title=settings.app_name, version="0.1.0")
logger = logging.getLogger(__name__)

@app.post("/api/predict/sample/{sample_id}")
def predict_saved_sample(sample_id: int) -> dict:
    """Run one stored sample through the shared prediction module."""
    try:
        return run_prediction(sample_id)
    except LookupError as error:
        raise HTTPException(
            status_code=404,
            detail="No saved sample was found with that Sample_ID. Check the ID and try again.",
        ) from error
    except InvalidSampleError as error:
        raise HTTPException(
            status_code=422,
            detail=(
                "This sample is missing required biomarker data or has unsupported "
                "race/sex values. Check the sample record and try again."
            ),
        ) from error
    except MySQLError as error:
        logger.exception("Database error while predicting sample_id=%s", sample_id)
        raise HTTPException(
            status_code=503,
            detail=(
                "The prediction service could not read or save the sample data. "
                "Please try again later."
            ),
        ) from error
    except FileNotFoundError as error:
        logger.exception("Prediction model file is unavailable")
        raise HTTPException(
            status_code=503,
            detail="Prediction models are unavailable. Please contact the administrator.",
        ) from error
    except Exception as error:
        logger.exception("Unexpected error while predicting sample_id=%s", sample_id)
        raise HTTPException(
            status_code=500,
            detail="The prediction could not be completed. Please try again later.",
        ) from error

@app.get("/api/health")
def health() -> dict[str, str]:
    """Return a liveness response without requiring the database."""

    return {"status": "ok", "service": settings.app_name}


@app.get("/api/health/database")
def database_health() -> dict[str, str]:
    """Verify that the API can connect to MySQL."""

    try:
        connection = create_connection()
        connection.close()
    except MySQLError as error:
        raise HTTPException(status_code=503, detail=f"Database connection failed: {error}") from error

    return {"status": "ok", "database": settings.database_name}


frontend_directory = Path(__file__).resolve().parents[2] / "frontend"
app.mount("/", StaticFiles(directory=frontend_directory, html=True), name="frontend")
