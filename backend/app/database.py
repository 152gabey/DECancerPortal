"""MySQL Connector/Python connection helpers."""

from collections.abc import Generator

import mysql.connector
from mysql.connector import MySQLConnection

from .config import settings


def create_connection() -> MySQLConnection:
    """Create a new MySQL connection using the configured environment."""

    return mysql.connector.connect(
        host=settings.database_host,
        port=settings.database_port,
        database=settings.database_name,
        user=settings.database_user,
        password=settings.database_password,
    )


def get_connection() -> Generator[MySQLConnection, None, None]:
    """Yield a connection and always close it after the request."""

    connection = create_connection()
    try:
        yield connection
    finally:
        connection.close()
