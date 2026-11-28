"""Static encoders for categorical features (race and sex).

This module provides fixed OrdinalEncoder instances for race and sex columns,
ensuring consistent encoding across the application based on categories from
the CSV data files.

The categories are:
- Sex: Female, Male
- Race: Asian, Black, Black/Hispanic, Caucasian, Caucasian/Hispanic, Hispanic, Other, Unknown
"""

import numpy as np
from sklearn.preprocessing import OrdinalEncoder

# Define static categories for sex and race
SEX_CATEGORIES = np.array([["Female"], ["Male"]])
RACE_CATEGORIES = np.array([
    ["Asian"],
    ["Black"],
    ["Black/Hispanic"],
    ["Caucasian"],
    ["Caucasian/Hispanic"],
    ["Hispanic"],
    ["Other"],
    ["Unknown"],
])

# Create OrdinalEncoder instances with fixed categories
sex_encoder = OrdinalEncoder(
    categories=[["Female", "Male"]],
    handle_unknown="use_encoded_value",
    unknown_value=-1
)
sex_encoder.fit(SEX_CATEGORIES)

race_encoder = OrdinalEncoder(
    categories=[[
        "Asian",
        "Black",
        "Black/Hispanic",
        "Caucasian",
        "Caucasian/Hispanic",
        "Hispanic",
        "Other",
        "Unknown",
    ]],
    handle_unknown="use_encoded_value",
    unknown_value=-1
)
race_encoder.fit(RACE_CATEGORIES)


def get_sex_encoder():
    """Return the static sex OrdinalEncoder.

    Returns
    -------
    OrdinalEncoder
        Pre-fitted encoder for sex values.
    """
    return sex_encoder


def get_race_encoder():
    """Return the static race OrdinalEncoder.

    Returns
    -------
    OrdinalEncoder
        Pre-fitted encoder for race values.
    """
    return race_encoder


def encode_sex(values: np.ndarray | list) -> np.ndarray:
    """Encode sex values using the static encoder.

    Parameters
    ----------
    values : np.ndarray or list
        Array of sex values to encode.

    Returns
    -------
    np.ndarray
        Ordinally encoded sex values.
    """
    if not isinstance(values, np.ndarray):
        values = np.array(values).reshape(-1, 1)
    elif values.ndim == 1:
        values = values.reshape(-1, 1)
    return sex_encoder.transform(values)


def encode_race(values: np.ndarray | list) -> np.ndarray:
    """Encode race values using the static encoder.

    Parameters
    ----------
    values : np.ndarray or list
        Array of race values to encode.

    Returns
    -------
    np.ndarray
        Ordinally encoded race values.
    """
    if not isinstance(values, np.ndarray):
        values = np.array(values).reshape(-1, 1)
    elif values.ndim == 1:
        values = values.reshape(-1, 1)
    return race_encoder.transform(values)


def decode_sex(encoded_values: np.ndarray) -> np.ndarray:
    """Decode ordinally encoded sex values.

    Parameters
    ----------
    encoded_values : np.ndarray
        Array of ordinally encoded sex values.

    Returns
    -------
    np.ndarray
        Decoded sex values.
    """
    return sex_encoder.inverse_transform(encoded_values)


def decode_race(encoded_values: np.ndarray) -> np.ndarray:
    """Decode ordinally encoded race values.

    Parameters
    ----------
    encoded_values : np.ndarray
        Array of ordinally encoded race values.

    Returns
    -------
    np.ndarray
        Decoded race values.
    """
    return race_encoder.inverse_transform(encoded_values)
