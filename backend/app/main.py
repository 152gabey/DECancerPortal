"""FastAPI application entry point."""

from pathlib import Path

from fastapi import FastAPI
from fastapi import HTTPException
from fastapi.staticfiles import StaticFiles
from mysql.connector import Error as MySQLError

from .config import settings
from .database import create_connection

app = FastAPI(title=settings.app_name, version="0.1.0")


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
