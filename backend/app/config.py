"""Application configuration loaded from environment variables."""

from dataclasses import dataclass
import os

from dotenv import load_dotenv

load_dotenv()


@dataclass(frozen=True)
class Settings:
    """Runtime settings for the API and MySQL connection."""

    app_name: str = os.getenv("APP_NAME", "DE Cancer Portal API")
    database_host: str = os.getenv("MYSQL_HOST", "127.0.0.1")
    database_port: int = int(os.getenv("MYSQL_PORT", "3306"))
    database_name: str = os.getenv("MYSQL_DATABASE", "decancer")
    database_user: str = os.getenv("MYSQL_USER", "decancer")
    database_password: str = os.getenv("MYSQL_PASSWORD", "decancer")


settings = Settings()
