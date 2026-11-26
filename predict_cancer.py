#!/usr/bin/env python3
"""
Standalone CLI tool for cancer prediction using trained models.

This script loads pre-trained cancer detection models and generates predictions
for samples in the database, storing results in the Predictions and Prediction_Scores
tables.
"""

import argparse
import os
import pickle
import sys
from dataclasses import dataclass
from pathlib import Path

import mysql.connector
from dotenv import load_dotenv
import src.cancerseek.constants as cs

load_dotenv()


@dataclass(frozen=True)
class DatabaseSettings:
    """Settings for MySQL database connection."""
    host: str = os.getenv("MYSQL_HOST", "127.0.0.1")
    port: int = int(os.getenv("MYSQL_PORT", "3306"))
    database: str = os.getenv("MYSQL_DATABASE", "decancer")
    user: str = os.getenv("MYSQL_USER", "decancer")
    password: str = os.getenv("MYSQL_PASSWORD", "decancer")


@dataclass(frozen=True)
class PredictionSettings:
    """Settings for model prediction pipeline."""
    model_path: str = os.getenv("MODEL_PATH", "Results/test")
    model_name: str = "RandomForest"
    model_version: str = "v1.0"
    cancer_types: list = None
    
    def __post_init__(self):
        """Initialize default cancer types."""
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


def create_connection():
    """Create a new MySQL connection using environment variables."""
    return mysql.connector.connect(
        host=db_settings.host,
        port=db_settings.port,
        database=db_settings.database,
        user=db_settings.user,
        password=db_settings.password,
    )


def get_model_info(cancer_type: cs.CancerType) -> dict:
    """
    Load model metadata and return paths for a given cancer type.
    
    Args:
        cancer_type: The cancer type to load the model for
        
    Returns:
        dict: Model metadata including the model_path
    """
    dir_path = (
        f"{settings.model_path}/{cancer_type.name.lower()}/"
        f"cancer-healthy/"
        f"fullModel_everyProtein"
    )
    metadata_path = f"{dir_path}/{settings.model_name}Test.pkl"
    model_path = f"{dir_path}/{settings.model_name}Model.pkl"
    
    with open(metadata_path, "rb") as handle:
        metadata = pickle.load(handle)
    
    metadata["model_path"] = model_path
    return metadata


def get_sample_inputs(connection, sample_code: str) -> list[float]:
    """
    Fetch feature inputs for a sample from the database.
    
    Retrieves protein biomarker measurements plus demographic features
    (race, sex, omega score, age) for a given sample.
    
    Args:
        connection: MySQL database connection
        sample_code: The sample identifier
        
    Returns:
        list[float]: Ordered list of features for model prediction
    """
    cur = connection.cursor()

    cur.execute(f"""
    SELECT b.Model_Feature_Name, m.Value
    FROM Biomarker_Measurements m
    JOIN Samples s ON s.Sample_ID = m.Sample_ID
    JOIN Biomarkers b ON b.Biomarker_ID = m.Biomarker_ID
    WHERE s.Sample_Code = '{sample_code}'
    ORDER BY b.Biomarker_ID;
    """)
    proteins = cur.fetchall()

    cur.execute(f"""
    SELECT p.Race_Type_ID AS Race, p.Sex_Type_ID AS Sex, s.Omega_Score AS omega_score, s.Age_At_Collection AS age
    FROM Samples s
    JOIN Patients p ON p.Patient_ID = s.Patient_ID
    WHERE s.Sample_Code = '{sample_code}';
    """)
    categorical = cur.fetchone()

    features = [p[1] for p in proteins] + [c for c in categorical] if categorical else []
    return features


def save_test_data(user_id: int, verbose: bool = False) -> int:
    """
    Generate predictions for all test samples and save to database.
    
    Loads all configured cancer models, generates predictions for each test sample,
    and stores results in the Predictions and Prediction_Scores tables.
    
    Args:
        user_id: The user ID to associate with predictions
        verbose: If True, print progress information
        
    Returns:
        int: Number of predictions successfully saved
    """
    connection = create_connection()
    
    output = {}
    prediction_count = 0
    
    if verbose:
        print(f"Loading models for {len(settings.cancer_types)} cancer types...")
    
    for cancer_type in settings.cancer_types:
        if verbose:
            print(f"  Processing {cancer_type.name}...")
        
        try:
            results = get_model_info(cancer_type)
            model = pickle.load(open(results["model_path"], "rb"))
            
            for [sample_id] in results[0]["results"]["test_ids"]:
                if sample_id not in output:
                    output[sample_id] = []

                features = get_sample_inputs(connection, sample_id)
                prediction = model.predict(features)
                feature_importance = model.feature_importances_

                output[sample_id].append({
                    "cancer_id": cancer_type.value,
                    "probability": float(max(prediction)),
                    "prediction_result": "cancer" if prediction.argmax() == 0 else "cancer-free",
                    "highest_protein_influence": float(max(feature_importance[:39])),
                    "highest_biomarker_id": int(feature_importance[:39].argmax()),
                })
        except Exception as e:
            if verbose:
                print(f"    Error: {e}")
            continue

    if verbose:
        print(f"Saving predictions to database...")
    
    cur = connection.cursor()
    for sample_code in output:
        try:
            cur.execute(f"SELECT Sample_ID FROM Samples WHERE Sample_Code='{sample_code}'")
            sample_id_row = cur.fetchone()
            
            if not sample_id_row:
                if verbose:
                    print(f"  Warning: Sample {sample_code} not found in database")
                continue
            
            sample_id = sample_id_row[0]

            best = max(output[sample_code], key=lambda x: x["probability"])
            pancancer = next(x for x in output[sample_code] if x["cancer_id"] == 10)
            
            cur.execute(f"""
              INSERT INTO Predictions (
                Sample_ID, 
                User_ID, 
                Date, 
                Pancancer_Probability, 
                Prediction_Result, 
                Missing_Cancer_Score, 
                Highest_Protein_Influence, 
                Highest_Biomarker_ID, 
                Model_Version
              ) VALUES (
                {sample_id}, 
                {user_id}, 
                NOW(), 
                {pancancer['probability']}, 
                '{best['prediction_result']}', 
                NULL, 
                {best['highest_protein_influence']}, 
                {best['highest_biomarker_id']}, 
                '{settings.model_version}',
              )
            """)
            prediction_id = cur.lastrowid
            connection.commit()
            prediction_count += 1
            
            for scores in output[sample_code]:
                cur.execute(f"""
                  INSERT INTO Prediction_Scores (Prediction_ID, Cancer_ID, Probability)
                  VALUES ({prediction_id}, {scores['cancer_id']}, {scores['probability']})
                """)
            connection.commit()
            
            if verbose:
                print(f"  ✓ Saved prediction for sample {sample_code}")
        except Exception as e:
            if verbose:
                print(f"  ✗ Error saving predictions for sample {sample_code}: {e}")
            connection.rollback()
            continue
    
    connection.close()
    return prediction_count


def main() -> int:
    """Parse arguments and run predictions."""
    parser = argparse.ArgumentParser(
        description="Generate cancer predictions for database samples using trained models."
    )
    parser.add_argument(
        "user_id",
        type=int,
        help="User ID to associate with predictions"
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_true",
        help="Print progress information"
    )

    args = parser.parse_args()

    try:
        count = save_test_data(args.user_id, verbose=args.verbose)
        if args.verbose:
            print(f"\n✓ Successfully saved {count} predictions")
        return 0
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
