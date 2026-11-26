"""Application configuration loaded from environment variables."""

from dataclasses import dataclass
import os

from dotenv import load_dotenv
from sklearn.ensemble import RandomForestClassifier

from src._types import ModelEstimator
import src.cancerseek.constants as cs
from src.models.random_forest import RandomForest
from src.parameters import Parameter

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

@dataclass(frozen=True)
class ModelSettings:
    model_path: str = os.getenv("MODEL_PATH", "Results/test")
    cancer_types: list[cs.CancerType] = [
        cs.CancerType.BREAST,
        cs.CancerType.COLORECTUM,
        cs.CancerType.ESOPHAGUS,
        cs.CancerType.LIVER,
        cs.CancerType.LUNG,
        cs.CancerType.OVARY,
        cs.CancerType.PANCREAS,
        cs.CancerType.STOMACH,
        cs.CancerType.PANCANCER,
    ]
    classification_mode: cs.ClassificationMode = cs.ClassificationMode.CANCER_HEALTHY
    model_mode: cs.ModelMode = cs.ModelMode.FULL_MODEL
    filter_mode: cs.FilterMode = cs.FilterMode.EVERY_PROTEIN
    kde_method: cs.KDEMethod = cs.KDEMethod.NONE
    model: type[ModelEstimator] = RandomForest
    params: list[Parameter] = [Parameter(RandomForestClassifier, *el) for el in cs.Classifier.RF.value]
    model_version: str = "v1.0"

model_settings = ModelSettings()