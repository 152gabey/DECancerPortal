from typing import Any
from typing import Sequence

from ._types import BaseEstimator


class Parameter:
    """Parameters used for hyperparameter optimisation."""

    def __init__(
        self,
        model: type[BaseEstimator],
        name: str = "default",
        values: Sequence[Any] = [None],
    ) -> None:

        self.model = model
        self.name = name
        self.values = values

        if self.name != "default":
            self._validate_params()

    def _validate_params(self) -> None:
        all_params = self.model().get_params()

        if self.name not in all_params:
            raise ValueError(f'"{self.name}" is not a valid parameter of the {self.model().__class__.__name__}.')

