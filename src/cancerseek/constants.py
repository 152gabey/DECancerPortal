from dataclasses import dataclass
from enum import Enum

from src._types import Preprocessors

# CancerSEEK has substantially more samples than the nanoparticle pipeline,
# but the grids remain small enough to evaluate repeatedly across cancer and
# classification combinations.
mlp_params = (
    ("hidden_layer_sizes", [(25,), (50,), (100,)]),
    ("alpha", [1e-4, 1e-3, 1e-2]),
    ("learning_rate_init", [1e-3, 1e-2]),
    ("early_stopping", [True]),
    ("max_iter", [1000]),
    ("random_state", [42]),
)

lr_params = (
    ("C", [0.01, 0.1, 1.0, 10.0, 100.0]),
    ("class_weight", [None, "balanced"]),
    ("max_iter", [2000]),
    ("solver", ["lbfgs"]),
    ("penalty", ["l2"]),
    ("random_state", [42]),
)

svm_params = (
    ("kernel", ["linear", "rbf"]),
    ("C", [0.1, 1.0, 10.0, 100.0]),
    ("gamma", ["scale", 0.01, 0.1]),
    ("class_weight", [None, "balanced"]),
    ("probability", [True]),
    ("random_state", [42]),
)

rf_params = (
    ("n_estimators", [300]),
    ("max_depth", [None]),
    ("max_features", ["sqrt"]),
    ("min_samples_leaf", [1]),
    ("class_weight", ["balanced"]),
    ("random_state", [42]),
)

# TODO
rfe_proteins = [
    'P22891', 
    'Q9BXJ4', 
    'P05109', 
    'P55290', 
    'P05062', 
    'Q6UXB8', 
    'P06702', 
    'Q9NQ79', 
    'P00738', 
    'Q9UBQ6', 
    'P02741', 
    'P13591'
]

class ClassifierType(Enum):
    """The type of classifier."""

    CLASS_2 = 1
    CLASS_3 = 2


class CrossValidation(Enum):
    """The type of cross-validation."""

    K_FOLD = "kf_cv"
    MONTE_CARLO = "mc_cv"


class PipelineMode(Enum):
    """The pipeline mode to use."""

    TESTING = "test"
    VALIDATING = "validation"


class ValidationMode(Enum):
    """The type of validation mode to use.

    Whether to run the validation pipeline with recursive
    feature elimination, optimize hyperparameters or
    without any specific use.
    """

    RECURSIVE_FEATURE_ELIMINATION = "feature_elimination"
    NO_RFE = "auto"
    HYPERPARAMETER_OPTIMISATION = "hyper_optimisation"


class CancerType(Enum):
    """The type of cancer."""

    BREAST = "Breast"
    COLORECTUM = "Colorectum"
    ESOPHAGUS = "Esophagus"
    LIVER = "Liver"
    LUNG = "Lung"
    OVARY = "Ovary"
    PANCANCER = "pancancer"
    PANCREAS = "Pancreas"
    STOMACH = "Stomach"


class Classifier(Enum):
    """The type of classifier to use for the respective hyperparameters."""

    RF = rf_params
    MLP = mlp_params
    LR = lr_params
    SVM = svm_params


class ClassificationMode(Enum):
    """The classification mode to use."""

    CANCER_HEALTHY = "cancer-healthy"
    CANCER_OTHERCANCERS = "cancer-otherCancers"
    CANCER_REST = "cancer-rest"
    THREE_CLASSES = "three-classes"


class KDEMethod(Enum):
    """The Kernel Density Method to use."""

    NONE = 0
    IMBALANCED_HEALTHY = (5, (4, 1))
    BALANCED = (5, (1, 1))
    IMBALANCED_EARLY = (5, (1, 4))


class ModelMode(Enum):
    """The model mode to use.

    The full model includes all proteins, DNA, sex, age and ethnicity.
    """

    FULL_MODEL = "fullModel"
    PROTEINS_ONLY = "proteinsOnly"


class FilterMode(Enum):
    """The filter mode to use.

    Filtering the proteins by what is chosen as the best
    during recursive feature elimination, selected via
    t-test, or to use all proteins.
    """

    FILTER_BY_RFE = "bestRfe"
    POST_T_TEST = "postTTest"
    EVERY_PROTEIN = "all"


class FigureFlag(Enum):
    """The type of figures to generate."""

    TEST = "test"
    VALIDATION = "validation"


VALIDATION_METHOD = CrossValidation.MONTE_CARLO
TEST_SPLIT = 0.2
SEED = 42


@dataclass
class ConstantOptions:
    """The options for constant data."""

    results_path: str
    cache_path: str
    refresh_cache: bool
    pipeline_flag: PipelineMode
    validation_flag: ValidationMode
    best_params: bool = False


@dataclass
class ComboParams:
    """The options for each combination of parameters."""

    collection: str
    classification_mode: ClassificationMode
    kde_method: KDEMethod
    model_mode: ModelMode
    filter_mode: FilterMode
    preprocessor: Preprocessors = "no_preprocessing"
