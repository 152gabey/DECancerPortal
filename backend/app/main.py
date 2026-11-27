"""FastAPI application entry point."""

from pathlib import Path
import logging

from fastapi import FastAPI
from fastapi import HTTPException
from fastapi.staticfiles import StaticFiles
from mysql.connector import Error as MySQLError

from .run_prediction import run_prediction
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
        raise HTTPException(status_code=404, detail=str(error)) from error
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error
    except MySQLError as error:
        logger.exception("Database error while predicting sample_id=%s", sample_id)
        raise HTTPException(status_code=503, detail="Database operation failed") from error
    except FileNotFoundError as error:
        raise HTTPException(status_code=503, detail=str(error)) from error
    except Exception as error:
        raise HTTPException(status_code=500, detail=f"Prediction failed: {error}") from error

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
