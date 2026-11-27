import os
import pickle
from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path
import mysql.connector
from dotenv import load_dotenv
import src.cancerseek.constants as cs
load_dotenv()

@dataclass(frozen=True)
class DatabaseSettings:
    """MySQL connection settings shared by prediction workflows."""
    host: str = os.getenv("MYSQL_HOST", "127.0.0.1")
    port: int = int(os.getenv("MYSQL_PORT", "3306"))
    database: str = os.getenv("MYSQL_DATABASE", "decancer_portal")
    user: str = os.getenv("MYSQL_USER", "decancer")
    password: str = os.getenv("MYSQL_PASSWORD", "decancer")

@dataclass(frozen=True)
class PredictionSettings:
    """Settings for the trained model artifacts."""
    model_path: str = os.getenv("MODEL_PATH", "Results/test")
    model_name: str = "RandomForest"
    model_version: str = "v1.0"
    cancer_types: list = None
    def __post_init__(self):
        if self.cancer_types is None:
            object.__setattr__(self, "cancer_types", [
                cs.CancerType.BREAST,
                cs.CancerType.COLORECTUM,
                cs.CancerType.ESOPHAGUS,
                cs.CancerType.LIVER,
                cs.CancerType.LUNG,
                cs.CancerType.OVARY,
                cs.CancerType.PANCREAS,
                cs.CancerType.STOMACH,
                cs.CancerType.PANCANCER,
            ])

db_settings = DatabaseSettings()
settings = PredictionSettings()
CANCER_TYPE_TO_ID = {
    cs.CancerType.BREAST: 2,
    cs.CancerType.COLORECTUM: 3,
    cs.CancerType.ESOPHAGUS: 4,
    cs.CancerType.LIVER: 5,
    cs.CancerType.LUNG: 6,
    cs.CancerType.OVARY: 7,
    cs.CancerType.PANCREAS: 8,
    cs.CancerType.STOMACH: 9,
    cs.CancerType.PANCANCER: 10,
}

def create_connection():
    """Open a MySQL connection using environment-based settings."""
    return mysql.connector.connect(
        host=db_settings.host,
        port=db_settings.port,
        database=db_settings.database,
        user=db_settings.user,
        password=db_settings.password,
    )

def model_directory(cancer_name: str) -> Path:
    """Return the configured result folder for one cancer type."""
    model_root = Path(settings.model_path)
    if not model_root.is_absolute():
        # Relative model paths are based at the repository root, not backend/app.
        model_root = Path(__file__).resolve().parents[2] / model_root
    return model_root / cancer_name / "cancer-healthy" / "fullModel_all"

@lru_cache(maxsize=9)
def load_model(cancer_type: cs.CancerType):
    """Load and cache one trusted trained model pipeline."""
    cancer_name = "pancancer" if cancer_type == cs.CancerType.PANCANCER else cancer_type.value
    model_path = model_directory(cancer_name) / f"{settings.model_name}Model.pkl"
    if not model_path.is_file():
        raise FileNotFoundError(f"Model file not found: {model_path}")
    with model_path.open("rb") as handle:
        return pickle.load(handle)

def get_sample_inputs(connection, sample_id: int) -> list[float]:
    """Fetch a sample's 43 model features in training order."""
    cursor = connection.cursor()
    try:
        cursor.execute(
            """SELECT b.Biomarker_ID, m.Value
               FROM Biomarker_Measurements m
               JOIN Samples s ON s.Sample_ID = m.Sample_ID
               JOIN Biomarkers b ON b.Biomarker_ID = m.Biomarker_ID
               WHERE s.Sample_ID = %s
               ORDER BY b.Biomarker_ID""",
            (sample_id,),
        )
        proteins = cursor.fetchall()
        cursor.execute(
            """SELECT rt.Race_Name, st.Sex_Name, s.Omega_Score, s.Age_At_Collection
               FROM Samples s
               JOIN Patients p ON p.Patient_ID = s.Patient_ID
               JOIN Race_Types rt ON rt.Race_Type_ID = p.Race_Type_ID
               JOIN Sex_Types st ON st.Sex_Type_ID = p.Sex_Type_ID
               WHERE s.Sample_ID = %s""",
            (sample_id,),
        )
        demographics = cursor.fetchone()
        if not demographics or len(proteins) != 39:
            return []
        if [int(row[0]) for row in proteins] != list(range(1, 40)):
            return []
        race_order = [
            "Asian", "Black", "Black/Hispanic", "Caucasian",
            "Caucasian/Hispanic", "Hispanic", "Other", "Unknown",
        ]
        sex_order = ["Female", "Male"]
        try:
            race_code = race_order.index(demographics[0])
            sex_code = sex_order.index(demographics[1])
        except ValueError:
            return []
        # Match Data.data_preprocess(): proteins, omega, age, race, sex.
        return [float(row[1]) for row in proteins] + [
            float(demographics[2]),
            float(demographics[3]),
            float(race_code),
            float(sex_code),
        ]
    finally:
        cursor.close()

def predict_one_model(cancer_type: cs.CancerType, features: list[float]) -> dict:
    """Return one model result in the batch CLI's output shape."""
    model = load_model(cancer_type)
    probabilities = model.predict_proba([features])[0]
    classes = list(model.classes_)
    try:
        positive_index = classes.index(1)
        negative_index = classes.index(0)
    except ValueError as error:
        raise ValueError(f"{cancer_type.value} model must have classes 0 and 1") from error
    feature_importance = model.named_steps["classifier"].feature_importances_
    protein_importance = feature_importance[:39]
    probability = float(probabilities[positive_index])
    return {
        "cancer_id": CANCER_TYPE_TO_ID[cancer_type],
        "probability": probability,
        "prediction_result": "cancer" if probability > float(probabilities[negative_index]) else "cancer-free",
        "highest_protein_influence": float(max(protein_importance)),
        # The schema's Biomarker_ID is one-based; argmax returns a zero-based index.
        "highest_biomarker_id": int(protein_importance.argmax()) + 1,
    }

def save_prediction_outputs(connection, sample_id: int, user_id: int, outputs: list[dict]) -> int:
    """Save an overall result and per-cancer scores in one transaction."""
    if not outputs:
        raise ValueError("Cannot save an empty set of model outputs")
    best = max(outputs, key=lambda item: item["probability"])
    pancancer_rows = [item for item in outputs if item["cancer_id"] == 10]
    pancancer = pancancer_rows[0] if pancancer_rows else best
    cursor = connection.cursor()
    try:
        cursor.execute(
            """INSERT INTO Predictions (
                   Sample_ID, User_ID, Pancancer_Probability, Prediction_Result,
                   Highest_Protein_Influence, Highest_Biomarker_ID, Model_Version
               ) VALUES (%s, %s, %s, %s, %s, %s, %s)""",
            (
                sample_id,
                user_id,
                pancancer["probability"],
                best["prediction_result"],
                best["highest_protein_influence"],
                best["highest_biomarker_id"],
                settings.model_version,
            ),
        )
        prediction_id = int(cursor.lastrowid)
        for item in outputs:
            if item["cancer_id"] == 10:
                continue
            cursor.execute(
                """INSERT INTO Prediction_Scores (Prediction_ID, Cancer_ID, Probability)
                   VALUES (%s, %s, %s)""",
                (prediction_id, item["cancer_id"], item["probability"]),
            )
        connection.commit()
        return prediction_id
    except Exception:
        connection.rollback()
        raise
    finally:
        cursor.close()

def run_prediction(sample_id: int) -> dict:
    """Run and persist predictions for one database Sample_ID."""
    connection = create_connection()
    cursor = connection.cursor()
    try:
        cursor.execute(
            "SELECT Uploaded_By FROM Samples WHERE Sample_ID = %s",
            (sample_id,),
        )
        sample = cursor.fetchone()
        if not sample:
            raise LookupError(f"Sample_ID {sample_id} was not found")
        features = get_sample_inputs(connection, sample_id)
        user_id = int(sample[0])
    finally:
        cursor.close()
        connection.close()
    if len(features) != 43:
        raise ValueError("Sample must have all 39 biomarkers and model-supported race/sex values")
    outputs = [predict_one_model(cancer_type, features) for cancer_type in settings.cancer_types]
    cancer_outputs = [item for item in outputs if item["cancer_id"] != 10]
    pancancer_outputs = [item for item in outputs if item["cancer_id"] == 10]
    if not cancer_outputs:
        raise ValueError("No cancer-specific models are configured")
    highest_cancer = max(cancer_outputs, key=lambda item: item["probability"])
    best = max(outputs, key=lambda item: item["probability"])
    prediction = {
        "sample_id": sample_id,
        "cancer_scores": {
            cancer_type.value: next(
                item["probability"] for item in cancer_outputs
                if item["cancer_id"] == CANCER_TYPE_TO_ID[cancer_type]
            )
            for cancer_type in settings.cancer_types
            if cancer_type != cs.CancerType.PANCANCER
        },
        "highest_cancer_type": next(
            cancer_type.value for cancer_type in settings.cancer_types
            if cancer_type != cs.CancerType.PANCANCER
            and CANCER_TYPE_TO_ID[cancer_type] == highest_cancer["cancer_id"]
        ),
        "highest_probability": highest_cancer["probability"],
        "highest_result": best["prediction_result"],
        "pancancer_probability": pancancer_outputs[0]["probability"] if pancancer_outputs else best["probability"],
        "model_outputs": outputs,
    }
    connection = create_connection()
    try:
        prediction["prediction_id"] = save_prediction_outputs(connection, sample_id, user_id, outputs)
    finally:
        connection.close()
    return prediction
