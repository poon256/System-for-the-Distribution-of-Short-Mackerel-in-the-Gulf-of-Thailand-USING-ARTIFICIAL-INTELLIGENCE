# -*- coding: utf-8 -*-
"""
Regression_Linear.py

Train and validate a Linear Regression-family model from the projectA MySQL database.
The script uses walk-forward validation, realistic annual-total calibration,
and optional month-level guardrails selected only when they reduce validation error.
It is called by frontend/regression.php and returns one JSON object.

Usage from PHP:
    python Regression_Linear.py <province_base64> <year> <month> --b64

Required Python packages:
    pip install pandas numpy scikit-learn matplotlib mysql-connector-python scipy

PyMySQL can be used instead of mysql-connector-python:
    pip install pymysql
"""

from __future__ import annotations

import base64
import json
import math
import os
import re
import sys
import warnings
from dataclasses import dataclass
from typing import Any, Dict, Iterable, List, Optional, Sequence, Tuple

warnings.filterwarnings("ignore")

try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

import numpy as np
import pandas as pd

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

from sklearn.impute import SimpleImputer
from sklearn.linear_model import LinearRegression, Ridge
from sklearn.cross_decomposition import PLSRegression
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

try:
    from scipy import stats as scipy_stats
except Exception:
    scipy_stats = None


BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_DIR = os.path.dirname(BASE_DIR)
OUTPUT_DIR = os.path.join(BASE_DIR, "output")
PHP_DB_CONFIG = os.path.join(PROJECT_DIR, "config", "class.connect.php")

MIN_ALLOWED_YEAR = 2562
MAX_ALLOWED_YEAR = 2570

FEATURE_LABELS = {
    "sst": "SST",
    "chlor_log": "ln(1 + Chlorophyll-a)",
    "month_sin": "sin(2π × Month / 12)",
    "month_cos": "cos(2π × Month / 12)",
    "year_index": "Year index",
    "lag12": "Previous-year catch",
    "month_avg_prior": "Previous monthly mean catch",
    "month_median_prior": "Previous monthly median catch",
    "sst_lag1": "SST lag 1 month",
    "sst_lag2": "SST lag 2 months",
    "sst_lag3": "SST lag 3 months",
    "chlor_log_lag1": "Chlorophyll-a lag 1 month",
    "chlor_log_lag2": "Chlorophyll-a lag 2 months",
    "chlor_log_lag3": "Chlorophyll-a lag 3 months",
    "sst_sq": "SST²",
    "chlor_log_sq": "ln(1 + Chlorophyll-a)²",
    "sst_chlor": "SST × ln(1 + Chlorophyll-a)",
    "sst_delta1": "SST monthly change",
    "chlor_delta1": "Chlorophyll-a monthly change",
    "sst_anomaly": "SST seasonal anomaly",
    "chlor_anomaly": "Chlorophyll-a seasonal anomaly",
    "sst_distance_30_5": "Distance from SST 30.5°C",
    "sst_distance_sq": "Squared distance from SST 30.5°C",
    "sst_in_gulf_range": "SST in 29.5–31.5°C range",
}

for _month_number in range(2, 13):
    FEATURE_LABELS[f"month_{_month_number}"] = f"Month={_month_number}"

BASE_FEATURES = [
    "sst",
    "chlor_log",
    "month_sin",
    "month_cos",
    "year_index",
]

LEGACY_HISTORY_FEATURES = BASE_FEATURES + [
    "lag12",
    "month_avg_prior",
]

HISTORY_FEATURES = LEGACY_HISTORY_FEATURES + [
    "month_median_prior",
]

MONTH_DUMMY_FEATURES = [f"month_{month}" for month in range(2, 13)]
DUMMY_HISTORY_FEATURES = [
    "sst",
    "chlor_log",
    "year_index",
] + MONTH_DUMMY_FEATURES + [
    "lag12",
    "month_avg_prior",
    "month_median_prior",
]

ENVIRONMENT_LAG_FEATURES = HISTORY_FEATURES + [
    "sst_lag1",
    "sst_lag2",
    "sst_lag3",
    "chlor_log_lag1",
    "chlor_log_lag2",
    "chlor_log_lag3",
]

ENVIRONMENT_RELATIONSHIP_FEATURES = HISTORY_FEATURES + [
    "sst_sq",
    "chlor_log_sq",
    "sst_chlor",
    "sst_delta1",
    "chlor_delta1",
    "sst_anomaly",
    "chlor_anomaly",
]

ENVIRONMENT_COMBINED_FEATURES = HISTORY_FEATURES + [
    "sst_lag1",
    "sst_lag2",
    "sst_lag3",
    "chlor_log_lag1",
    "chlor_log_lag2",
    "chlor_log_lag3",
    "sst_sq",
    "chlor_log_sq",
    "sst_chlor",
    "sst_delta1",
    "chlor_delta1",
    "sst_anomaly",
    "chlor_anomaly",
]

ECOLOGY_FEATURES = HISTORY_FEATURES + [
    "sst_lag2",
    "sst_lag3",
    "chlor_log_lag1",
    "sst_distance_30_5",
    "sst_distance_sq",
    "sst_in_gulf_range",
    "sst_chlor",
]

PLS_RELATIONSHIP_FEATURES = ENVIRONMENT_COMBINED_FEATURES + MONTH_DUMMY_FEATURES


PROVINCE_ALIASES = {
    "เพชรบุรี": {"เพชรบุรี", "เพรชบุรี"},
    "เพรชบุรี": {"เพชรบุรี", "เพรชบุรี"},
}


@dataclass
class ModelBundle:
    name: str
    features: List[str]
    target_transform: str
    imputer: SimpleImputer
    scaler: Optional[StandardScaler]
    model: Any
    model_type: str
    alpha: float
    blend_weight: float
    n_components: int = 0


def send_json(data: Dict[str, Any]) -> None:
    print(json.dumps(data, ensure_ascii=False, allow_nan=False))


def finite_or_none(value: Any) -> Optional[float]:
    try:
        value = float(value)
        return value if math.isfinite(value) else None
    except Exception:
        return None


def decode_province(value: str, is_base64: bool = False) -> str:
    if is_base64:
        try:
            return base64.b64decode(value).decode("utf-8").strip()
        except Exception:
            pass
    return value.strip()


def read_args() -> Tuple[str, int, int]:
    if len(sys.argv) < 4:
        raise ValueError("Missing arguments: province, year, month")

    is_base64 = len(sys.argv) >= 5 and sys.argv[4] == "--b64"
    province = decode_province(sys.argv[1], is_base64)

    try:
        year = int(sys.argv[2])
        month = int(sys.argv[3])
    except ValueError as exc:
        raise ValueError("Year and month must be numbers") from exc

    if not province:
        raise ValueError("Province is empty")
    if year < MIN_ALLOWED_YEAR or year > MAX_ALLOWED_YEAR:
        raise ValueError(f"Year must be {MIN_ALLOWED_YEAR}-{MAX_ALLOWED_YEAR}")
    if month < 1 or month > 12:
        raise ValueError("Month must be 1-12")

    return province, year, month


def read_php_database_config() -> Dict[str, Any]:
    config: Dict[str, Any] = {
        "host": os.getenv("PROJECTA_DB_HOST", "127.0.0.1"),
        "database": os.getenv("PROJECTA_DB_NAME", "projecta"),
        "user": os.getenv("PROJECTA_DB_USER", "root"),
        "password": os.getenv("PROJECTA_DB_PASSWORD", ""),
        "port": int(os.getenv("PROJECTA_DB_PORT", "3306")),
    }

    if not os.path.exists(PHP_DB_CONFIG):
        return config

    try:
        content = open(PHP_DB_CONFIG, "r", encoding="utf-8", errors="ignore").read()
        patterns = {
            "host": r"\$host\s*=\s*['\"]([^'\"]*)['\"]",
            "database": r"\$dbname\s*=\s*['\"]([^'\"]*)['\"]",
            "user": r"\$user\s*=\s*['\"]([^'\"]*)['\"]",
            "password": r"\$pass\s*=\s*['\"]([^'\"]*)['\"]",
        }
        for key, pattern in patterns.items():
            match = re.search(pattern, content)
            if match:
                config[key] = match.group(1)
    except Exception:
        pass

    return config


def connect_database(config: Dict[str, Any]):
    mysql_error: Optional[Exception] = None

    try:
        import mysql.connector  # type: ignore

        connection = mysql.connector.connect(
            host=config["host"],
            database=config["database"],
            user=config["user"],
            password=config["password"],
            port=config["port"],
            charset="utf8mb4",
            use_unicode=True,
        )
        return connection, "mysql-connector-python"
    except ImportError as exc:
        mysql_error = exc
    except Exception as exc:
        raise RuntimeError(f"Cannot connect to MySQL: {exc}") from exc

    try:
        import pymysql  # type: ignore

        connection = pymysql.connect(
            host=config["host"],
            db=config["database"],
            user=config["user"],
            password=config["password"],
            port=config["port"],
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )
        return connection, "PyMySQL"
    except ImportError as exc:
        raise RuntimeError(
            "ไม่พบ MySQL driver สำหรับ Python กรุณาติดตั้งด้วยคำสั่ง "
            "pip install mysql-connector-python"
        ) from (mysql_error or exc)
    except Exception as exc:
        raise RuntimeError(f"Cannot connect to MySQL: {exc}") from exc


def query_dataframe(connection, sql: str, params: Sequence[Any] = ()) -> pd.DataFrame:
    cursor = connection.cursor()
    try:
        cursor.execute(sql, tuple(params))
        rows = cursor.fetchall()
        columns = [column[0] for column in cursor.description]
        return pd.DataFrame(list(rows), columns=columns)
    finally:
        cursor.close()


def normalize_name(value: str) -> str:
    return re.sub(r"\s+", "", str(value or "").strip())


def resolve_station(connection, requested_province: str) -> Tuple[int, str]:
    station_df = query_dataframe(
        connection,
        "SELECT id, station_name FROM station WHERE status = 1 ORDER BY id ASC",
    )
    if station_df.empty:
        raise ValueError("ไม่พบข้อมูลในตาราง station")

    requested_normalized = normalize_name(requested_province)
    accepted_names = PROVINCE_ALIASES.get(requested_province, {requested_province})
    accepted_normalized = {normalize_name(name) for name in accepted_names}
    accepted_normalized.add(requested_normalized)

    for row in station_df.itertuples(index=False):
        if normalize_name(row.station_name) in accepted_normalized:
            return int(row.id), str(row.station_name).strip()

    available = ", ".join(station_df["station_name"].astype(str).tolist())
    raise ValueError(f"ไม่พบจังหวัดดังกล่าว: {requested_province} (มีข้อมูล: {available})")


def impute_environment_from_same_month(env_df: pd.DataFrame) -> Tuple[pd.DataFrame, Dict[str, Any]]:
    """Replace blank, non-finite, or zero environment values.

    The primary replacement is the median of the same calendar month from
    other available years for the same station. Median is used instead of mean
    because chlorophyll-a may contain large spikes. If an entire calendar month
    has no valid value, the station-wide median is used as a safe fallback.
    """
    frame = env_df.copy()
    summary: Dict[str, Any] = {
        "method": "Same-station, same-calendar-month median from other years; station median fallback",
    }

    for column in ["sst", "chlorophyll_a"]:
        frame[column] = pd.to_numeric(frame[column], errors="coerce")
        frame[f"{column}_raw"] = frame[column]
        numeric = frame[column].to_numpy(dtype=float)
        invalid_mask = frame[column].isna() | ~np.isfinite(numeric) | (frame[column] <= 0)
        invalid_count = int(invalid_mask.sum())

        frame.loc[invalid_mask, column] = np.nan
        month_medians = frame.groupby("month", observed=False)[column].median()
        station_median = finite_or_none(frame[column].median())
        if station_median is None or station_median <= 0:
            raise ValueError(f"ไม่มีค่าที่ใช้เติมข้อมูล {column}")

        replacements = frame.loc[invalid_mask, "month"].map(month_medians)
        same_month_count = int(replacements.notna().sum())
        fallback_count = int(replacements.isna().sum())
        replacements = replacements.fillna(station_median)
        frame.loc[invalid_mask, column] = replacements.to_numpy(dtype=float)

        if frame[column].isna().any() or (frame[column] <= 0).any():
            raise ValueError(f"ไม่สามารถเติมข้อมูลที่ขาดหายของ {column} ได้ครบ")

        flag_column = f"{column}_imputed"
        frame[flag_column] = invalid_mask.astype(int)
        summary[f"{column}_imputed_months"] = invalid_count
        summary[f"{column}_same_month_fills"] = same_month_count
        summary[f"{column}_fallback_fills"] = fallback_count

    return frame, summary


def load_database_data(connection, station_id: int) -> Tuple[pd.DataFrame, Dict[str, Any]]:
    catch_sql = """
        SELECT
            year,
            month,
            SUM(amount) AS catch
        FROM catch_mackereldata
        WHERE station_id = %s
          AND status = 1
        GROUP BY year, month
        ORDER BY year, month
    """

    environment_sql = """
        SELECT
            year,
            month,
            AVG(sst) AS sst,
            AVG(chlorophyll_a) AS chlorophyll_a
        FROM marine_environment
        WHERE station_id = %s
          AND status = 1
        GROUP BY year, month
        ORDER BY year, month
    """

    catch_df = query_dataframe(connection, catch_sql, [station_id])
    env_df = query_dataframe(connection, environment_sql, [station_id])

    if catch_df.empty:
        raise ValueError("จังหวัดนี้ไม่มีข้อมูลใน catch_mackereldata")
    if env_df.empty:
        raise ValueError("จังหวัดนี้ไม่มีข้อมูลใน marine_environment")

    for column in ["year", "month"]:
        catch_df[column] = pd.to_numeric(catch_df[column], errors="coerce")
        env_df[column] = pd.to_numeric(env_df[column], errors="coerce")

    catch_df["catch"] = pd.to_numeric(catch_df["catch"], errors="coerce")
    env_df["sst"] = pd.to_numeric(env_df["sst"], errors="coerce")
    env_df["chlorophyll_a"] = pd.to_numeric(env_df["chlorophyll_a"], errors="coerce")

    catch_df = catch_df.dropna(subset=["year", "month", "catch"])
    env_df = env_df.dropna(subset=["year", "month"])

    catch_df[["year", "month"]] = catch_df[["year", "month"]].astype(int)
    env_df[["year", "month"]] = env_df[["year", "month"]].astype(int)

    env_df, environment_imputation = impute_environment_from_same_month(env_df)

    # Environment is the complete monthly calendar. A missing catch record is
    # interpreted as zero catch for that station/month because the catch table
    # stores only rows where an equipment catch was recorded.
    data = env_df.merge(catch_df, on=["year", "month"], how="left")
    zero_filled = int(data["catch"].isna().sum())
    data["catch"] = data["catch"].fillna(0.0).clip(lower=0.0)

    data = data[
        data["month"].between(1, 12)
        & data["year"].between(MIN_ALLOWED_YEAR, MAX_ALLOWED_YEAR)
    ].copy()
    data = data.sort_values(["year", "month"]).drop_duplicates(["year", "month"])
    data.reset_index(drop=True, inplace=True)

    if len(data) < 24:
        raise ValueError("ข้อมูลที่จับคู่กันมีน้อยกว่า 24 เดือน ไม่เพียงพอสำหรับเทรนและทดสอบ")

    source_counts = {
        "catch_months": int(len(catch_df)),
        "environment_months": int(len(env_df)),
        "merged_months": int(len(data)),
        "zero_filled_months": zero_filled,
        "sst_imputed_months": int(environment_imputation.get("sst_imputed_months", 0)),
        "sst_same_month_fills": int(environment_imputation.get("sst_same_month_fills", 0)),
        "chlorophyll_a_imputed_months": int(environment_imputation.get("chlorophyll_a_imputed_months", 0)),
        "chlorophyll_a_same_month_fills": int(environment_imputation.get("chlorophyll_a_same_month_fills", 0)),
        "environment_imputation_method": str(environment_imputation.get("method", "")),
    }
    return data, source_counts


def refresh_environment_features(frame: pd.DataFrame) -> pd.DataFrame:
    """Create nonlinear and lagged environment terms without using future catch."""
    result = frame.sort_values(["year", "month"]).copy()
    result["chlor_log"] = np.log1p(result["chlorophyll_a"].clip(lower=0.0))

    for lag in [1, 2, 3]:
        result[f"sst_lag{lag}"] = result["sst"].shift(lag)
        result[f"chlor_log_lag{lag}"] = result["chlor_log"].shift(lag)

    result["sst_delta1"] = result["sst"] - result["sst"].shift(1)
    result["chlor_delta1"] = result["chlor_log"] - result["chlor_log"].shift(1)
    result["sst_sq"] = result["sst"] ** 2
    result["chlor_log_sq"] = result["chlor_log"] ** 2
    result["sst_chlor"] = result["sst"] * result["chlor_log"]

    result["sst_month_prior_mean"] = result.groupby("month")["sst"].transform(
        lambda values: values.shift(1).expanding(min_periods=1).mean()
    )
    result["chlor_month_prior_mean"] = result.groupby("month")["chlor_log"].transform(
        lambda values: values.shift(1).expanding(min_periods=1).mean()
    )
    result["sst_anomaly"] = result["sst"] - result["sst_month_prior_mean"]
    result["chlor_anomaly"] = result["chlor_log"] - result["chlor_month_prior_mean"]

    # Gulf of Thailand mackerel research reported significant fishing-ground
    # association with SST around 29.5-31.5 C. These terms allow the linear
    # family to represent a preferred temperature band rather than only a
    # straight monotonic relationship.
    result["sst_distance_30_5"] = np.abs(result["sst"] - 30.5)
    result["sst_distance_sq"] = (result["sst"] - 30.5) ** 2
    result["sst_in_gulf_range"] = result["sst"].between(29.5, 31.5).astype(float)
    return result


def impute_environment_for_training(
    train_df: pd.DataFrame,
    test_df: pd.DataFrame,
) -> Tuple[pd.DataFrame, pd.DataFrame]:
    """Fit environment replacements on training rows only and rebuild lags."""
    train = train_df.copy()
    test = test_df.copy()

    for column in ["sst", "chlorophyll_a"]:
        raw_column = f"{column}_raw"
        train_values = pd.to_numeric(
            train[raw_column] if raw_column in train.columns else train[column],
            errors="coerce",
        )
        test_values = pd.to_numeric(
            test[raw_column] if raw_column in test.columns else test[column],
            errors="coerce",
        )

        train_values = train_values.mask(~np.isfinite(train_values) | (train_values <= 0))
        test_values = test_values.mask(~np.isfinite(test_values) | (test_values <= 0))

        month_medians = pd.DataFrame({"month": train["month"], "value": train_values}).groupby("month")["value"].median()
        global_median = finite_or_none(train_values.median())
        if global_median is None or global_median <= 0:
            raise ValueError(f"ไม่มีค่าฝึกที่ใช้เติมข้อมูล {column}")

        train[column] = train_values.fillna(train["month"].map(month_medians)).fillna(global_median)
        test[column] = test_values.fillna(test["month"].map(month_medians)).fillna(global_median)

    train["__split"] = "train"
    test["__split"] = "test"
    combined = refresh_environment_features(pd.concat([train, test], ignore_index=True))
    train_result = combined[combined["__split"] == "train"].drop(columns="__split").copy()
    test_result = combined[combined["__split"] == "test"].drop(columns="__split").copy()
    return train_result, test_result


def add_features(data: pd.DataFrame) -> pd.DataFrame:
    frame = data.sort_values(["year", "month"]).copy()
    min_year = int(frame["year"].min())

    frame["month_sin"] = np.sin(2.0 * np.pi * frame["month"] / 12.0)
    frame["month_cos"] = np.cos(2.0 * np.pi * frame["month"] / 12.0)
    frame["year_index"] = frame["year"] - min_year
    for month_number in range(2, 13):
        frame[f"month_{month_number}"] = (frame["month"] == month_number).astype(float)

    frame["lag12"] = frame["catch"].shift(12)
    frame["month_avg_prior"] = frame.groupby("month")["catch"].transform(
        lambda values: values.shift(1).expanding(min_periods=1).mean()
    )
    frame["month_median_prior"] = frame.groupby("month")["catch"].transform(
        lambda values: values.shift(1).expanding(min_periods=1).median()
    )
    return refresh_environment_features(frame)


def fit_model(
    train_df: pd.DataFrame,
    name: str,
    features: List[str],
    target_transform: str,
    model_type: str = "ols",
    alpha: float = 0.0,
    blend_weight: float = 1.0,
    n_components: int = 0,
) -> ModelBundle:
    normalized_model_type = str(model_type).lower()
    minimum_rows = max(12, len(features) + 3)
    if normalized_model_type == "pls":
        minimum_rows = max(24, int(n_components) + 5)
    if len(train_df) < minimum_rows:
        raise ValueError("Training data is insufficient for the selected feature set")

    imputer = SimpleImputer(strategy="median")
    x_train = imputer.fit_transform(train_df[features])
    y_train = train_df["catch"].to_numpy(dtype=float)

    if target_transform == "log1p":
        y_model = np.log1p(np.maximum(y_train, 0.0))
    else:
        y_model = y_train

    scaler: Optional[StandardScaler] = None
    resolved_components = 0

    if normalized_model_type == "ridge":
        scaler = StandardScaler()
        x_model = scaler.fit_transform(x_train)
        model: Any = Ridge(alpha=max(float(alpha), 1e-9))
        model.fit(x_model, y_model)
    elif normalized_model_type == "pls":
        scaler = StandardScaler()
        x_model = scaler.fit_transform(x_train)
        resolved_components = max(
            1,
            min(int(n_components or 1), x_model.shape[1], max(x_model.shape[0] - 1, 1)),
        )
        model = PLSRegression(
            n_components=resolved_components,
            scale=False,
            max_iter=1000,
        )
        model.fit(x_model, y_model.reshape(-1, 1))
        alpha = 0.0
    else:
        x_model = x_train
        model = LinearRegression()
        normalized_model_type = "ols"
        alpha = 0.0
        model.fit(x_model, y_model)

    return ModelBundle(
        name=name,
        features=list(features),
        target_transform=target_transform,
        imputer=imputer,
        scaler=scaler,
        model=model,
        model_type=normalized_model_type,
        alpha=float(alpha),
        blend_weight=float(np.clip(blend_weight, 0.0, 1.0)),
        n_components=int(resolved_components),
    )


def inverse_target(raw_prediction: np.ndarray, target_transform: str, train_y: np.ndarray) -> np.ndarray:
    raw_prediction = np.asarray(raw_prediction, dtype=float)
    if target_transform == "log1p":
        safe_upper = math.log1p(max(float(np.nanmax(train_y)) * 4.0, 1.0))
        raw_prediction = np.clip(raw_prediction, -0.25, safe_upper)
        return np.expm1(raw_prediction)
    return raw_prediction


def robust_prediction_bounds(train_df: pd.DataFrame, months: Iterable[int]) -> Tuple[np.ndarray, np.ndarray]:
    overall = train_df["catch"].to_numpy(dtype=float)
    overall_q95 = float(np.quantile(overall, 0.95)) if len(overall) else 0.0
    lower_values: List[float] = []
    upper_values: List[float] = []

    for month in months:
        month_values = train_df.loc[train_df["month"] == int(month), "catch"].to_numpy(dtype=float)
        if len(month_values) == 0:
            month_values = overall

        month_max = float(np.max(month_values)) if len(month_values) else 0.0
        month_mean = float(np.mean(month_values)) if len(month_values) else 0.0
        month_std = float(np.std(month_values, ddof=1)) if len(month_values) > 1 else month_mean

        lower_values.append(0.0)
        upper_values.append(max(month_max * 1.60, month_mean + 3.0 * month_std, overall_q95 * 1.25, 1.0))

    return np.asarray(lower_values), np.asarray(upper_values)


def model_feature_parameters(bundle: ModelBundle) -> Tuple[float, np.ndarray]:
    coefficients = np.asarray(bundle.model.coef_, dtype=float).reshape(-1)
    intercept_values = np.asarray(bundle.model.intercept_, dtype=float).reshape(-1)
    intercept = float(intercept_values[0]) if len(intercept_values) else 0.0
    if bundle.scaler is None:
        return intercept, coefficients

    scale = np.asarray(bundle.scaler.scale_, dtype=float)
    mean = np.asarray(bundle.scaler.mean_, dtype=float)
    safe_scale = np.where(np.abs(scale) < 1e-12, 1.0, scale)
    original_coefficients = coefficients / safe_scale
    original_intercept = intercept - float(np.sum(coefficients * mean / safe_scale))
    return original_intercept, original_coefficients


def seasonal_baseline(test_df: pd.DataFrame, train_df: pd.DataFrame) -> np.ndarray:
    baseline = pd.to_numeric(test_df.get("lag12"), errors="coerce")
    if baseline is None:
        baseline = pd.Series(np.nan, index=test_df.index, dtype=float)

    for column in ["month_median_prior", "month_avg_prior"]:
        if column in test_df.columns:
            baseline = baseline.fillna(pd.to_numeric(test_df[column], errors="coerce"))

    month_medians = train_df.groupby("month")["catch"].median()
    baseline = baseline.fillna(test_df["month"].map(month_medians))
    baseline = baseline.fillna(float(train_df["catch"].median()))
    return np.maximum(baseline.to_numpy(dtype=float), 0.0)


def predict_bundle(bundle: ModelBundle, test_df: pd.DataFrame, train_df: pd.DataFrame) -> np.ndarray:
    x_test = bundle.imputer.transform(test_df[bundle.features])
    x_model = bundle.scaler.transform(x_test) if bundle.scaler is not None else x_test
    raw = np.asarray(bundle.model.predict(x_model), dtype=float).reshape(-1)
    prediction = inverse_target(raw, bundle.target_transform, train_df["catch"].to_numpy(dtype=float))

    if bundle.blend_weight < 1.0:
        baseline = seasonal_baseline(test_df, train_df)
        prediction = bundle.blend_weight * prediction + (1.0 - bundle.blend_weight) * baseline

    lower, upper = robust_prediction_bounds(train_df, test_df["month"].astype(int).tolist())
    return np.clip(prediction, lower, upper)


def calculate_metrics(y_true: Sequence[float], y_pred: Sequence[float], feature_count: int) -> Dict[str, Any]:
    actual = np.asarray(y_true, dtype=float)
    predicted = np.asarray(y_pred, dtype=float)
    valid = np.isfinite(actual) & np.isfinite(predicted)
    actual = actual[valid]
    predicted = predicted[valid]

    if len(actual) == 0:
        return {}

    errors = actual - predicted
    absolute_errors = np.abs(errors)
    squared_errors = errors ** 2

    mae = float(mean_absolute_error(actual, predicted))
    mse = float(mean_squared_error(actual, predicted))
    rmse = float(math.sqrt(mse))
    r2 = float(r2_score(actual, predicted)) if len(actual) > 1 else float("nan")

    non_zero = np.abs(actual) > 1e-12
    mape = float(np.mean(np.abs(errors[non_zero] / actual[non_zero])) * 100.0) if non_zero.any() else float("nan")
    smape = float(np.mean(2.0 * absolute_errors / (np.abs(actual) + np.abs(predicted) + 1e-12)) * 100.0)
    wmape = float(np.sum(absolute_errors) / max(np.sum(np.abs(actual)), 1e-12) * 100.0)
    mean_actual = float(np.mean(actual))
    nrmse = float(rmse / max(abs(mean_actual), 1e-12) * 100.0)
    bias = float(np.mean(predicted - actual))

    n = len(actual)
    p = int(feature_count)
    adjusted_r2 = float("nan")
    if n > p + 1 and math.isfinite(r2):
        adjusted_r2 = 1.0 - (1.0 - r2) * (n - 1.0) / (n - p - 1.0)

    return {
        "rows": int(n),
        "mae": round(mae, 4),
        "mse": round(mse, 4),
        "rmse": round(rmse, 4),
        "mape": finite_or_none(round(mape, 4)),
        "smape": round(smape, 4),
        "wmape": round(wmape, 4),
        "r2": finite_or_none(round(r2, 6)),
        "adjusted_r2": finite_or_none(round(adjusted_r2, 6)),
        "nrmse_percent": round(nrmse, 4),
        "mean_bias_error": round(bias, 4),
        "mean_actual": round(mean_actual, 4),
        "median_absolute_error": round(float(np.median(absolute_errors)), 4),
        "p90_absolute_error": round(float(np.quantile(absolute_errors, 0.90)), 4),
        "max_absolute_error": round(float(np.max(absolute_errors)), 4),
    }


ANNUAL_CALIBRATION_LABELS = {
    "none": "ไม่ปรับยอดรวมรายปี",
    "last": "อิงยอดรวมของปีก่อน",
    "mean2": "อิงค่าเฉลี่ยยอดรวม 2 ปีย้อนหลัง",
    "median": "อิงค่ามัธยฐานยอดรวมของปีที่ผ่านมา",
    "trend": "อิงแนวโน้มเชิงเส้นของยอดรวมรายปี",
    "growth": "อิงอัตราเติบโตมัธยฐานรายปี",
    "dampedtrend": "อิงแนวโน้มรายปีแบบลดความแรง",
}

GUARDRAIL_LABELS = {
    "none": "ไม่ใช้กรอบเสริม",
    "iqr1": "กรอบรายเดือน IQR 1 เท่า",
    "iqr1.5": "กรอบรายเดือน IQR 1.5 เท่า",
    "adaptive0.5": "ดึงค่าผิดปกติกลับ 50%",
    "adaptive0.75": "ดึงค่าผิดปกติกลับ 75%",
    "soft0.25": "ลดความรุนแรงของค่าที่เกินกรอบ",
}


def forecast_annual_total(train_df: pd.DataFrame, target_year: int, method: str) -> Optional[float]:
    totals = train_df.groupby("year", observed=False)["catch"].sum().sort_index()
    values = totals.to_numpy(dtype=float)
    years = totals.index.to_numpy(dtype=float)
    if len(values) == 0 or method == "none":
        return None
    if method == "last":
        return max(float(values[-1]), 0.0)
    if method == "mean2":
        return max(float(np.mean(values[-2:])), 0.0)
    if method == "median":
        return max(float(np.median(values)), 0.0)
    if len(values) < 2:
        return max(float(values[-1]), 0.0)
    slope, intercept = np.polyfit(years, values, 1)
    if method == "trend":
        return max(float(slope * float(target_year) + intercept), 0.0)
    if method == "dampedtrend":
        return max(float(values[-1] + 0.5 * slope), 0.0)
    if method == "growth":
        growth = values[1:] / np.maximum(values[:-1], 1.0)
        robust_growth = float(np.median(np.clip(growth, 0.50, 1.80)))
        return max(float(values[-1] * robust_growth), 0.0)
    return None


def apply_annual_calibration(
    predictions: Sequence[float],
    train_df: pd.DataFrame,
    target_year: int,
    method: str,
    weight: float,
) -> np.ndarray:
    prediction = np.maximum(np.asarray(predictions, dtype=float), 0.0)
    annual_target = forecast_annual_total(train_df, target_year, method)
    if annual_target is None or method == "none" or weight <= 0.0:
        return prediction
    predicted_total = float(np.sum(prediction))
    if predicted_total <= 1e-12:
        return prediction
    scale = float(np.clip(annual_target / predicted_total, 0.35, 2.50))
    effective_scale = (1.0 - float(weight)) + float(weight) * scale
    return np.maximum(prediction * effective_scale, 0.0)


def apply_month_guardrail(
    predictions: Sequence[float],
    train_df: pd.DataFrame,
    test_df: pd.DataFrame,
    guardrail: str,
) -> np.ndarray:
    prediction = np.maximum(np.asarray(predictions, dtype=float), 0.0).copy()
    if guardrail == "none":
        return prediction
    baseline = seasonal_baseline(test_df, train_df)
    overall = train_df["catch"].to_numpy(dtype=float)
    for index, month in enumerate(test_df["month"].astype(int).tolist()):
        values = train_df.loc[train_df["month"] == month, "catch"].to_numpy(dtype=float)
        if len(values) < 2:
            values = overall
        if len(values) == 0:
            continue
        q10, q25, q75, q90 = np.quantile(values, [0.10, 0.25, 0.75, 0.90])
        median = float(np.median(values))
        iqr = float(q75 - q25)
        if guardrail == "iqr1":
            prediction[index] = np.clip(prediction[index], max(0.0, q25 - iqr), max(1.0, q75 + iqr))
        elif guardrail == "iqr1.5":
            prediction[index] = np.clip(prediction[index], max(0.0, q25 - 1.5 * iqr), max(1.0, q75 + 1.5 * iqr))
        elif guardrail in {"adaptive0.5", "adaptive0.75"}:
            scale = max(iqr, float(q90 - q10) / 2.0, abs(median) * 0.20, 1.0)
            if abs(prediction[index] - median) / scale > 2.0:
                pull = 0.50 if guardrail == "adaptive0.5" else 0.75
                prediction[index] = (1.0 - pull) * prediction[index] + pull * baseline[index]
        elif guardrail == "soft0.25":
            lower = max(0.0, q25 - 1.5 * iqr)
            upper = max(1.0, q75 + 1.5 * iqr)
            if prediction[index] > upper:
                prediction[index] = upper + 0.25 * (prediction[index] - upper)
            elif prediction[index] < lower:
                prediction[index] = lower + 0.25 * (prediction[index] - lower)
    return np.maximum(prediction, 0.0)


def apply_selected_calibration(
    predictions: Sequence[float],
    train_df: pd.DataFrame,
    test_df: pd.DataFrame,
    target_year: int,
    chosen: Dict[str, Any],
) -> np.ndarray:
    calibrated = apply_annual_calibration(
        predictions,
        train_df,
        target_year,
        str(chosen.get("annual_calibration", "none")),
        float(chosen.get("annual_calibration_weight", 0.0)),
    )
    return apply_month_guardrail(
        calibrated,
        train_df,
        test_df,
        str(chosen.get("guardrail", "none")),
    )


def select_prediction_calibration(data: pd.DataFrame, chosen: Dict[str, Any]) -> Tuple[Dict[str, Any], List[Dict[str, Any]]]:
    fold_predictions: List[Tuple[int, pd.DataFrame, pd.DataFrame, np.ndarray]] = []
    for validation_year in chosen.get("validation_years", []):
        train_df = data[data["year"] < int(validation_year)].copy()
        test_df = data[data["year"] == int(validation_year)].copy()
        if train_df.empty or test_df.empty:
            continue
        train_df, test_df = impute_environment_for_training(train_df, test_df)
        bundle = fit_model(
            train_df,
            str(chosen["name"]),
            list(chosen["features"]),
            str(chosen["target_transform"]),
            str(chosen.get("model_type", "ols")),
            float(chosen.get("alpha", 0.0)),
            float(chosen.get("blend_weight", 1.0)),
            int(chosen.get("n_components", 0)),
        )
        base_prediction = predict_bundle(bundle, test_df, train_df)
        fold_predictions.append((int(validation_year), train_df, test_df, base_prediction))

    if not fold_predictions:
        selected = dict(chosen)
        selected.update({
            "annual_calibration": "none",
            "annual_calibration_weight": 0.0,
            "guardrail": "none",
        })
        return selected, []

    annual_options = [("none", 0.0)]
    for method in ["last", "mean2", "median", "trend", "growth", "dampedtrend"]:
        for weight in [0.50, 0.75, 1.00]:
            annual_options.append((method, weight))
    guardrail_options = ["none", "iqr1", "iqr1.5", "adaptive0.5", "adaptive0.75", "soft0.25"]
    calibration_results: List[Dict[str, Any]] = []
    effective_features = int(chosen.get("n_components", 0)) if chosen.get("model_type") == "pls" else len(chosen["features"])

    for annual_method, annual_weight in annual_options:
        for guardrail in guardrail_options:
            actual_all: List[float] = []
            prediction_all: List[float] = []
            fold_metrics: List[Dict[str, Any]] = []
            for validation_year, train_df, test_df, base_prediction in fold_predictions:
                calibrated = apply_annual_calibration(
                    base_prediction, train_df, validation_year, annual_method, annual_weight
                )
                calibrated = apply_month_guardrail(calibrated, train_df, test_df, guardrail)
                actual = test_df["catch"].to_numpy(dtype=float)
                metric = calculate_metrics(actual, calibrated, effective_features)
                metric["year"] = int(validation_year)
                fold_metrics.append(metric)
                actual_all.extend(actual.tolist())
                prediction_all.extend(calibrated.tolist())
            metrics = calculate_metrics(actual_all, prediction_all, effective_features)
            calibration_results.append({
                "annual_calibration": annual_method,
                "annual_calibration_weight": float(annual_weight),
                "guardrail": guardrail,
                "metrics": metrics,
                "folds": fold_metrics,
            })

    calibration_results.sort(
        key=lambda item: (
            float(item["metrics"].get("rmse", float("inf"))),
            float(item["metrics"].get("mae", float("inf"))),
            float(item["metrics"].get("p90_absolute_error", float("inf"))),
            0 if item["annual_calibration"] == "none" else 1,
            0 if item["guardrail"] == "none" else 1,
        )
    )

    # Do not accept a lower-RMSE calibration if it creates unusually large
    # month-level misses. The uncalibrated option is always present, so this
    # constraint can safely fall back to the original prediction behaviour.
    base_metrics = chosen.get("metrics", {})
    base_p90 = float(base_metrics.get("p90_absolute_error", float("inf")))
    base_max = float(base_metrics.get("max_absolute_error", float("inf")))
    acceptable = [
        item for item in calibration_results
        if float(item["metrics"].get("p90_absolute_error", float("inf"))) <= base_p90 * 1.05
        and float(item["metrics"].get("max_absolute_error", float("inf"))) <= base_max * 1.05
    ]
    best = acceptable[0] if acceptable else calibration_results[0]
    selected = dict(chosen)
    selected.update({
        "annual_calibration": best["annual_calibration"],
        "annual_calibration_weight": best["annual_calibration_weight"],
        "guardrail": best["guardrail"],
        "metrics": best["metrics"],
        "folds": best["folds"],
    })
    return selected, calibration_results


def _correlation_pair(x: pd.Series, y: pd.Series) -> Tuple[Optional[float], Optional[float], int]:
    frame = pd.DataFrame({"x": pd.to_numeric(x, errors="coerce"), "y": pd.to_numeric(y, errors="coerce")})
    frame = frame.replace([np.inf, -np.inf], np.nan).dropna()
    if len(frame) < 4 or frame["x"].nunique() < 2 or frame["y"].nunique() < 2:
        return None, None, int(len(frame))

    pearson = float(frame["x"].corr(frame["y"], method="pearson"))
    spearman = float(frame["x"].corr(frame["y"], method="spearman"))
    return finite_or_none(pearson), finite_or_none(spearman), int(len(frame))


def analyze_environment_relationship(data: pd.DataFrame) -> Dict[str, Any]:
    """Compare same-month and lagged environment relationships with catch.

    Correlation is descriptive, not proof of causation. Lag N means the
    environment observed N months earlier is compared with current catch.
    """
    relationship_rows: List[Dict[str, Any]] = []
    variable_columns = {
        "SST": "sst",
        "Chlorophyll-a": "chlor_log",
    }

    for variable_name, base_column in variable_columns.items():
        for lag in range(0, 4):
            column = base_column if lag == 0 else f"{base_column}_lag{lag}"
            pearson, spearman, row_count = _correlation_pair(data[column], data["catch"])
            direction = "none"
            if spearman is not None:
                direction = "positive" if spearman > 0 else "negative" if spearman < 0 else "none"
            relationship_rows.append({
                "variable": variable_name,
                "lag_months": int(lag),
                "pearson": finite_or_none(round(pearson, 6)) if pearson is not None else None,
                "spearman": finite_or_none(round(spearman, 6)) if spearman is not None else None,
                "direction": direction,
                "rows": row_count,
            })

    best_relationships: Dict[str, Any] = {}
    for variable_name in variable_columns:
        options = [
            row for row in relationship_rows
            if row["variable"] == variable_name and row["spearman"] is not None
        ]
        best = max(options, key=lambda row: abs(float(row["spearman"]))) if options else None
        best_relationships[variable_name] = best

    valid = data[["catch", "sst", "chlorophyll_a"]].replace([np.inf, -np.inf], np.nan).dropna()
    positive = valid[valid["catch"] > 0]
    reference = positive if not positive.empty else valid
    high_threshold = float(reference["catch"].quantile(0.75)) if not reference.empty else 0.0
    high_catch = reference[reference["catch"] >= high_threshold] if not reference.empty else reference

    high_catch_summary = {
        "threshold_ton": round(high_threshold, 4),
        "months": int(len(high_catch)),
        "mean_sst": finite_or_none(round(float(high_catch["sst"].mean()), 4)) if not high_catch.empty else None,
        "median_sst": finite_or_none(round(float(high_catch["sst"].median()), 4)) if not high_catch.empty else None,
        "mean_chlorophyll_a": finite_or_none(round(float(high_catch["chlorophyll_a"].mean()), 4)) if not high_catch.empty else None,
        "median_chlorophyll_a": finite_or_none(round(float(high_catch["chlorophyll_a"].median()), 4)) if not high_catch.empty else None,
        "sst_in_29_5_31_5_percent": (
            round(float(high_catch["sst"].between(29.5, 31.5).mean() * 100.0), 2)
            if not high_catch.empty else None
        ),
    }

    return {
        "method": "Pearson and Spearman correlations at lags 0-3 months",
        "note": "Correlation is descriptive and does not prove that SST or Chlorophyll-a caused catch changes.",
        "rows": relationship_rows,
        "best": best_relationships,
        "high_catch_environment": high_catch_summary,
        "research_sst_reference": "29.5-31.5 C Gulf of Thailand mackerel fishing-ground range",
    }

def candidate_definitions() -> List[Tuple[str, List[str], str, str, float, float, int]]:
    candidates: List[Tuple[str, List[str], str, str, float, float, int]] = [
        ("Basic OLS Linear Regression", BASE_FEATURES, "none", "ols", 0.0, 1.0, 0),
        ("Basic OLS Log-Target", BASE_FEATURES, "log1p", "ols", 0.0, 1.0, 0),
        ("Seasonal-History OLS", HISTORY_FEATURES, "none", "ols", 0.0, 1.0, 0),
        ("Seasonal-History OLS + 50% Baseline", HISTORY_FEATURES, "none", "ols", 0.0, 0.50, 0),
        ("Seasonal-History OLS Log-Target + 50% Baseline", HISTORY_FEATURES, "log1p", "ols", 0.0, 0.50, 0),
        ("Environment Relationship OLS + 50% Baseline", ENVIRONMENT_RELATIONSHIP_FEATURES, "none", "ols", 0.0, 0.50, 0),
        ("Ecology Relationship OLS + 50% Baseline", ECOLOGY_FEATURES, "none", "ols", 0.0, 0.50, 0),
    ]

    for alpha in [0.1, 1.0, 10.0, 100.0, 1000.0]:
        for blend_weight in [1.0, 0.75, 0.50]:
            candidates.append((
                f"Seasonal-History Ridge alpha={alpha:g}, weight={blend_weight:.2f}",
                HISTORY_FEATURES,
                "none",
                "ridge",
                alpha,
                blend_weight,
                0,
            ))

    for alpha in [0.1, 1.0, 10.0]:
        for blend_weight in [0.75, 0.50]:
            candidates.append((
                f"Seasonal-History Ridge Log-Target alpha={alpha:g}, weight={blend_weight:.2f}",
                HISTORY_FEATURES,
                "log1p",
                "ridge",
                alpha,
                blend_weight,
                0,
            ))

    for alpha in [10.0, 100.0, 1000.0]:
        for blend_weight in [1.0, 0.90, 0.75]:
            candidates.append((
                f"Environment-Lag Ridge alpha={alpha:g}, weight={blend_weight:.2f}",
                ENVIRONMENT_LAG_FEATURES,
                "none",
                "ridge",
                alpha,
                blend_weight,
                0,
            ))

    for alpha in [1.0, 10.0, 100.0]:
        for blend_weight in [1.0, 0.90, 0.50]:
            candidates.append((
                f"Environment-Relationship Ridge alpha={alpha:g}, weight={blend_weight:.2f}",
                ENVIRONMENT_RELATIONSHIP_FEATURES,
                "none",
                "ridge",
                alpha,
                blend_weight,
                0,
            ))
            candidates.append((
                f"Environment-Combined Ridge alpha={alpha:g}, weight={blend_weight:.2f}",
                ENVIRONMENT_COMBINED_FEATURES,
                "none",
                "ridge",
                alpha,
                blend_weight,
                0,
            ))

    for component_count in [1, 2, 3]:
        for blend_weight in [1.0, 0.75, 0.50]:
            candidates.append((
                f"PLS Environment Relationship components={component_count}, weight={blend_weight:.2f}",
                PLS_RELATIONSHIP_FEATURES,
                "none",
                "pls",
                0.0,
                blend_weight,
                component_count,
            ))

    return candidates


def walk_forward_validation(data: pd.DataFrame) -> Tuple[Dict[str, Any], List[Dict[str, Any]]]:
    years = sorted(int(year) for year in data["year"].unique())
    validation_years = [year for year in years if len(data[data["year"] < year]) >= 24]
    if not validation_years:
        validation_years = [years[-1]] if len(years) > 1 else []

    candidate_results: List[Dict[str, Any]] = []

    for name, features, target_transform, model_type, alpha, blend_weight, n_components in candidate_definitions():
        all_actual: List[float] = []
        all_predicted: List[float] = []
        fold_rows: List[Dict[str, Any]] = []

        for validation_year in validation_years:
            train_df = data[data["year"] < validation_year].copy()
            test_df = data[data["year"] == validation_year].copy()
            minimum_rows = max(24, n_components + 5) if model_type == "pls" else max(24, len(features) + 3)
            if len(train_df) < minimum_rows or test_df.empty:
                continue

            try:
                train_df, test_df = impute_environment_for_training(train_df, test_df)
                bundle = fit_model(
                    train_df,
                    name,
                    features,
                    target_transform,
                    model_type,
                    alpha,
                    blend_weight,
                    n_components,
                )
                predictions = predict_bundle(bundle, test_df, train_df)
            except Exception:
                continue

            actual = test_df["catch"].to_numpy(dtype=float)
            effective_feature_count = bundle.n_components if bundle.model_type == "pls" else len(features)
            fold_metric = calculate_metrics(actual, predictions, effective_feature_count)
            fold_metric["year"] = int(validation_year)
            fold_rows.append(fold_metric)

            all_actual.extend(actual.tolist())
            all_predicted.extend(predictions.tolist())

        if not all_actual:
            continue

        effective_feature_count = n_components if model_type == "pls" else len(features)
        metrics = calculate_metrics(all_actual, all_predicted, effective_feature_count)
        candidate_results.append({
            "name": name,
            "features": features,
            "target_transform": target_transform,
            "model_type": model_type,
            "alpha": float(alpha),
            "blend_weight": float(blend_weight),
            "n_components": int(n_components),
            "metrics": metrics,
            "folds": fold_rows,
            "validation_years": [int(row["year"]) for row in fold_rows],
        })

    if not candidate_results:
        raise ValueError("ไม่สามารถสร้างชุดทดสอบแบบ time-series ได้")

    candidate_results.sort(
        key=lambda result: (
            float(result["metrics"].get("rmse", float("inf"))),
            float(result["metrics"].get("mae", float("inf"))),
            result.get("n_components", 0) if result.get("model_type") == "pls" else len(result["features"]),
        )
    )
    return candidate_results[0], candidate_results


def model_diagnostics(bundle: ModelBundle, train_df: pd.DataFrame) -> Dict[str, Any]:
    x = bundle.imputer.transform(train_df[bundle.features]).astype(float)
    y_original = train_df["catch"].to_numpy(dtype=float)
    y = np.log1p(np.maximum(y_original, 0.0)) if bundle.target_transform == "log1p" else y_original
    x_model = bundle.scaler.transform(x) if bundle.scaler is not None else x
    fitted = np.asarray(bundle.model.predict(x_model), dtype=float).reshape(-1)
    residuals = y - fitted

    n = len(y)
    p = len(bundle.features)
    dof = n - p - 1
    sse = float(np.sum(residuals ** 2))
    mse_residual = sse / dof if dof > 0 else float("nan")
    residual_std_error = math.sqrt(mse_residual) if mse_residual >= 0 and math.isfinite(mse_residual) else float("nan")

    design = np.column_stack([np.ones(n), x])
    original_intercept, original_coefficients = model_feature_parameters(bundle)
    beta = np.concatenate([[original_intercept], original_coefficients])
    covariance = np.full((p + 1, p + 1), np.nan)
    if dof > 0 and math.isfinite(mse_residual):
        covariance = mse_residual * np.linalg.pinv(design.T @ design)

    standard_errors = np.sqrt(np.maximum(np.diag(covariance), 0.0))
    with np.errstate(divide="ignore", invalid="ignore"):
        t_values = beta / standard_errors

    if scipy_stats is not None and dof > 0:
        p_values = 2.0 * scipy_stats.t.sf(np.abs(t_values), dof)
    else:
        p_values = np.full_like(t_values, np.nan)

    names = ["Intercept"] + [FEATURE_LABELS.get(feature, feature) for feature in bundle.features]
    coefficients = []
    for index, name in enumerate(names):
        coefficients.append({
            "term": name,
            "coefficient": round(float(beta[index]), 8),
            "std_error": finite_or_none(round(float(standard_errors[index]), 8)),
            "t_stat": finite_or_none(round(float(t_values[index]), 6)),
            "p_value": finite_or_none(round(float(p_values[index]), 8)),
        })

    y_mean = float(np.mean(y))
    ss_total = float(np.sum((y - y_mean) ** 2))
    ss_regression = max(ss_total - sse, 0.0)
    f_statistic = float("nan")
    f_p_value = float("nan")
    if p > 0 and dof > 0 and mse_residual > 0:
        f_statistic = (ss_regression / p) / mse_residual
        if scipy_stats is not None:
            f_p_value = float(scipy_stats.f.sf(f_statistic, p, dof))

    sigma2_mle = max(sse / max(n, 1), 1e-12)
    aic = n * math.log(sigma2_mle) + 2 * (p + 1)
    bic = n * math.log(sigma2_mle) + math.log(max(n, 1)) * (p + 1)

    target_name = "ln(1 + Catch ton)" if bundle.target_transform == "log1p" else "Catch ton"
    equation_parts = [f"{target_name} = {original_intercept:.6f}"]
    for feature, coefficient in zip(bundle.features, original_coefficients):
        sign = "+" if coefficient >= 0 else "-"
        equation_parts.append(f" {sign} {abs(float(coefficient)):.6f} × {FEATURE_LABELS.get(feature, feature)}")

    train_prediction = predict_bundle(bundle, train_df, train_df)
    training_metrics = calculate_metrics(y_original, train_prediction, p)

    return {
        "equation": (
            f"Final prediction = {bundle.blend_weight:.2f} × [" + "".join(equation_parts) +
            f"] + {1.0 - bundle.blend_weight:.2f} × Seasonal baseline"
            if bundle.blend_weight < 1.0
            else "".join(equation_parts)
        ),
        "target_scale": target_name,
        "intercept": round(float(original_intercept), 8),
        "model_type": bundle.model_type,
        "alpha": round(float(bundle.alpha), 6),
        "n_components": int(bundle.n_components),
        "blend_weight": round(float(bundle.blend_weight), 4),
        "coefficient_note": (
            "PLS coefficients are linear latent-component coefficients; classical p-values are approximate."
            if bundle.model_type == "pls"
            else (
                "Ridge coefficients are regularized; classical p-values are approximate."
                if bundle.model_type == "ridge"
                else "OLS coefficients and classical diagnostic statistics."
            )
        ),
        "coefficients": coefficients,
        "training_metrics": training_metrics,
        "residual_standard_error": finite_or_none(round(residual_std_error, 6)),
        "f_statistic": finite_or_none(round(f_statistic, 6)),
        "f_p_value": finite_or_none(round(f_p_value, 8)),
        "aic": round(float(aic), 6),
        "bic": round(float(bic), 6),
        "degrees_of_freedom": int(max(dof, 0)),
    }


def historical_environment_for_year(data: pd.DataFrame, year: int) -> pd.DataFrame:
    result = data.loc[data["year"] == year, ["year", "month", "sst", "chlorophyll_a", "catch"]].copy()
    return result.sort_values("month").reset_index(drop=True)


def forecast_environment_year(environment_history: pd.DataFrame, target_year: int) -> pd.DataFrame:
    rows: List[Dict[str, float]] = []

    for month in range(1, 13):
        month_history = environment_history[environment_history["month"] == month].sort_values("year")
        if month_history.empty:
            raise ValueError(f"ไม่มีข้อมูลสิ่งแวดล้อมสำหรับเดือน {month}")

        row: Dict[str, float] = {"year": int(target_year), "month": month}
        for column in ["sst", "chlorophyll_a"]:
            values = month_history[column].to_numpy(dtype=float)
            years = month_history["year"].to_numpy(dtype=float)

            recent_mean = float(np.mean(values[-min(3, len(values)):]))
            last_value = float(values[-1])

            if len(values) >= 2:
                trend_model = LinearRegression().fit(years.reshape(-1, 1), values)
                trend_value = float(trend_model.predict(np.asarray([[target_year]], dtype=float))[0])
                deltas = np.diff(values)
                cyclic_delta = float(deltas[(target_year - int(years[-1]) - 1) % len(deltas)]) if len(deltas) else 0.0
            else:
                trend_value = last_value
                cyclic_delta = 0.0

            forecast = 0.45 * trend_value + 0.35 * recent_mean + 0.20 * last_value + 0.15 * cyclic_delta

            q05 = float(np.quantile(values, 0.05))
            q95 = float(np.quantile(values, 0.95))
            spread = max(q95 - q05, float(np.std(values)), 0.01)
            lower = q05 - 0.35 * spread
            upper = q95 + 0.35 * spread
            if column == "chlorophyll_a":
                lower = max(lower, 0.0)

            row[column] = float(np.clip(forecast, lower, upper))

        rows.append(row)

    return pd.DataFrame(rows)


def build_future_feature_rows(
    history: pd.DataFrame,
    env_year: pd.DataFrame,
    min_year: int,
    environment_history: Optional[pd.DataFrame] = None,
) -> pd.DataFrame:
    target_year = int(env_year["year"].iloc[0])
    environment_base = (
        environment_history[["year", "month", "sst", "chlorophyll_a"]].copy()
        if environment_history is not None
        else history[["year", "month", "sst", "chlorophyll_a"]].copy()
    )
    environment_combined = pd.concat(
        [environment_base, env_year[["year", "month", "sst", "chlorophyll_a"]]],
        ignore_index=True,
    )
    environment_combined = (
        environment_combined.sort_values(["year", "month"])
        .drop_duplicates(["year", "month"], keep="last")
    )
    target_environment = refresh_environment_features(environment_combined)
    target_environment = target_environment[target_environment["year"] == target_year].copy()

    rows: List[Dict[str, float]] = []
    for env_row in target_environment.sort_values("month").itertuples(index=False):
        month = int(env_row.month)
        previous_year_value = history.loc[
            (history["year"] == target_year - 1) & (history["month"] == month), "catch"
        ]
        lag12 = float(previous_year_value.iloc[-1]) if not previous_year_value.empty else float("nan")

        prior_month_values = history.loc[
            (history["year"] < target_year) & (history["month"] == month), "catch"
        ]
        month_avg_prior = float(prior_month_values.mean()) if not prior_month_values.empty else float("nan")
        month_median_prior = float(prior_month_values.median()) if not prior_month_values.empty else float("nan")

        row = {
            "year": target_year,
            "month": month,
            "sst": float(env_row.sst),
            "chlorophyll_a": float(env_row.chlorophyll_a),
            "chlor_log": float(env_row.chlor_log),
            "month_sin": math.sin(2.0 * math.pi * month / 12.0),
            "month_cos": math.cos(2.0 * math.pi * month / 12.0),
            "year_index": target_year - min_year,
            "lag12": lag12,
            "month_avg_prior": month_avg_prior,
            "month_median_prior": month_median_prior,
            **{f"month_{month_number}": float(month == month_number) for month_number in range(2, 13)},
        }
        for column in [
            "sst_lag1", "sst_lag2", "sst_lag3",
            "chlor_log_lag1", "chlor_log_lag2", "chlor_log_lag3",
            "sst_sq", "chlor_log_sq", "sst_chlor",
            "sst_delta1", "chlor_delta1", "sst_anomaly", "chlor_anomaly",
            "sst_distance_30_5", "sst_distance_sq", "sst_in_gulf_range",
        ]:
            row[column] = float(getattr(env_row, column)) if pd.notna(getattr(env_row, column)) else float("nan")
        rows.append(row)

    return pd.DataFrame(rows)


def predict_selected_year(
    selected_year: int,
    data: pd.DataFrame,
    chosen: Dict[str, Any],
) -> Tuple[pd.DataFrame, ModelBundle, str, pd.DataFrame]:
    min_year = int(data["year"].min())
    max_year = int(data["year"].max())
    features = list(chosen["features"])
    target_transform = str(chosen["target_transform"])
    name = str(chosen["name"])

    if selected_year <= max_year:
        prior = data[data["year"] < selected_year].copy()
        required_rows = (
            max(24, int(chosen.get("n_components", 0)) + 5)
            if str(chosen.get("model_type", "ols")) == "pls"
            else max(24, len(features) + 3)
        )
        if len(prior) >= required_rows:
            train_df = prior
            mode = "Historical out-of-sample prediction"
        else:
            train_df = data[data["year"] != selected_year].copy()
            mode = "Historical leave-one-year-out prediction"

        test_df = data[data["year"] == selected_year].copy()
        if test_df.empty:
            raise ValueError(f"ไม่มีข้อมูลสิ่งแวดล้อมปี {selected_year} ในฐานข้อมูล")

        train_df, test_df = impute_environment_for_training(train_df, test_df)
        bundle = fit_model(
            train_df,
            name,
            features,
            target_transform,
            str(chosen.get("model_type", "ols")),
            float(chosen.get("alpha", 0.0)),
            float(chosen.get("blend_weight", 1.0)),
            int(chosen.get("n_components", 0)),
        )
        predictions = predict_bundle(bundle, test_df, train_df)
        predictions = apply_selected_calibration(
            predictions, train_df, test_df, selected_year, chosen
        )
        result = test_df[["year", "month", "sst", "chlorophyll_a", "catch"]].copy()
        result["predicted_mackerel_ton"] = predictions
        result.rename(columns={"catch": "actual_mackerel_ton"}, inplace=True)
        result["absolute_error_ton"] = np.abs(
            result["actual_mackerel_ton"] - result["predicted_mackerel_ton"]
        )
        return result, bundle, mode, train_df

    train_df = data.copy()
    bundle = fit_model(
        train_df,
        name,
        features,
        target_transform,
        str(chosen.get("model_type", "ols")),
        float(chosen.get("alpha", 0.0)),
        float(chosen.get("blend_weight", 1.0)),
        int(chosen.get("n_components", 0)),
    )
    history = data[["year", "month", "sst", "chlorophyll_a", "catch"]].copy()
    environment_history = history[["year", "month", "sst", "chlorophyll_a"]].copy()
    selected_result: Optional[pd.DataFrame] = None

    for year in range(max_year + 1, selected_year + 1):
        env_year = forecast_environment_year(environment_history, year)
        future_features = build_future_feature_rows(
            history,
            env_year,
            min_year,
            environment_history,
        )
        predictions = predict_bundle(bundle, future_features, train_df)
        predictions = apply_selected_calibration(
            predictions, history, future_features, year, chosen
        )

        # Keep future values within a realistic year-to-year range while retaining
        # variation learned from SST, chlorophyll and seasonal history.
        previous_year = history[history["year"] == year - 1].set_index("month")
        adjusted: List[float] = []
        for row, prediction in zip(future_features.itertuples(index=False), predictions):
            previous = float(previous_year.loc[int(row.month), "catch"]) if int(row.month) in previous_year.index else float(prediction)
            if previous > 0:
                prediction = float(np.clip(prediction, previous * 0.45, previous * 1.75))
            adjusted.append(max(float(prediction), 0.0))

        year_result = env_year.copy()
        year_result["predicted_mackerel_ton"] = np.asarray(adjusted)

        history_add = year_result.rename(columns={"predicted_mackerel_ton": "catch"})[
            ["year", "month", "sst", "chlorophyll_a", "catch"]
        ]
        history = pd.concat([history, history_add], ignore_index=True)
        environment_history = pd.concat(
            [environment_history, env_year[["year", "month", "sst", "chlorophyll_a"]]],
            ignore_index=True,
        )

        if year == selected_year:
            selected_result = year_result

    if selected_result is None:
        raise ValueError("ไม่สามารถสร้างข้อมูลคาดการณ์ปีอนาคตได้")

    return selected_result, bundle, "Future recursive forecast", train_df


def save_forecast_graph(result: pd.DataFrame, selected_year: int) -> None:
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    plt.figure(figsize=(12, 5))
    plt.plot(
        result["month"],
        result["predicted_mackerel_ton"],
        marker="o",
        linewidth=2,
        label="Predicted",
    )
    if "actual_mackerel_ton" in result.columns:
        plt.plot(
            result["month"],
            result["actual_mackerel_ton"],
            marker="o",
            linewidth=2,
            linestyle="--",
            label="Actual",
        )
    plt.xticks(range(1, 13))
    plt.xlabel("Month")
    plt.ylabel("Mackerel catch (ton)")
    plt.title(f"Monthly Mackerel Catch - Year {selected_year}")
    plt.grid(True)
    plt.legend()
    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "regression_fish.png"), dpi=170)
    plt.close()

    plt.figure(figsize=(12, 5))
    plt.plot(result["month"], result["sst"], marker="o", linewidth=2)
    plt.xticks(range(1, 13))
    plt.xlabel("Month")
    plt.ylabel("Sea surface temperature (C)")
    plt.title(f"Monthly Sea Surface Temperature - Year {selected_year}")
    plt.grid(True)
    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "regression_sst.png"), dpi=170)
    plt.close()

    plt.figure(figsize=(12, 5))
    plt.plot(result["month"], result["chlorophyll_a"], marker="o", linewidth=2)
    plt.xticks(range(1, 13))
    plt.xlabel("Month")
    plt.ylabel("Chlorophyll-a (mg/m3)")
    plt.title(f"Monthly Chlorophyll-a - Year {selected_year}")
    plt.grid(True)
    plt.tight_layout()
    plt.savefig(os.path.join(OUTPUT_DIR, "regression_chlor.png"), dpi=170)
    plt.close()


def save_validation_outputs(
    validation_rows: pd.DataFrame,
    metrics: Dict[str, Any],
    diagnostics: Dict[str, Any],
    chosen: Dict[str, Any],
) -> None:
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    if not validation_rows.empty:
        validation_rows.to_csv(
            os.path.join(OUTPUT_DIR, "regression_model_evaluation.csv"),
            index=False,
            encoding="utf-8-sig",
        )

    stale_relationship = os.path.join(OUTPUT_DIR, "regression_environment_relationship.csv")
    if os.path.exists(stale_relationship):
        try:
            os.remove(stale_relationship)
        except OSError:
            pass

    with open(os.path.join(OUTPUT_DIR, "regression_model_metrics.json"), "w", encoding="utf-8") as file:
        json.dump(
            {
                "validation_metrics": metrics,
                "diagnostics": diagnostics,
                "annual_calibration": chosen.get("annual_calibration", "none"),
                "annual_calibration_weight": chosen.get("annual_calibration_weight", 0.0),
                "guardrail": chosen.get("guardrail", "none"),
            },
            file,
            ensure_ascii=False,
            indent=2,
            allow_nan=False,
        )


def build_validation_rows(data: pd.DataFrame, chosen: Dict[str, Any]) -> pd.DataFrame:
    rows: List[pd.DataFrame] = []
    for year in chosen.get("validation_years", []):
        train_df = data[data["year"] < int(year)].copy()
        test_df = data[data["year"] == int(year)].copy()
        if train_df.empty or test_df.empty:
            continue
        train_df, test_df = impute_environment_for_training(train_df, test_df)
        bundle = fit_model(
            train_df,
            str(chosen["name"]),
            list(chosen["features"]),
            str(chosen["target_transform"]),
            str(chosen.get("model_type", "ols")),
            float(chosen.get("alpha", 0.0)),
            float(chosen.get("blend_weight", 1.0)),
            int(chosen.get("n_components", 0)),
        )
        prediction = predict_bundle(bundle, test_df, train_df)
        prediction = apply_selected_calibration(
            prediction, train_df, test_df, int(year), chosen
        )
        fold = test_df[["year", "month", "catch"]].copy()
        fold.rename(columns={"catch": "actual_mackerel_ton"}, inplace=True)
        fold["predicted_mackerel_ton"] = prediction
        fold["error_ton"] = fold["predicted_mackerel_ton"] - fold["actual_mackerel_ton"]
        fold["absolute_error_ton"] = np.abs(fold["error_ton"])
        rows.append(fold)
    return pd.concat(rows, ignore_index=True) if rows else pd.DataFrame()


def clean_result_for_output(result: pd.DataFrame, province: str) -> pd.DataFrame:
    output = result.copy()
    output.insert(0, "province", province)
    output.rename(
        columns={
            "year": "year",
            "month": "month",
            "sst": "sst",
            "chlorophyll_a": "chlor_a",
        },
        inplace=True,
    )
    numeric_columns = output.select_dtypes(include=[np.number]).columns
    output[numeric_columns] = output[numeric_columns].round(4)
    return output


def main() -> None:
    requested_province, selected_year, selected_month = read_args()
    db_config = read_php_database_config()
    connection, driver_name = connect_database(db_config)

    try:
        station_id, database_province = resolve_station(connection, requested_province)
        raw_data, source_counts = load_database_data(connection, station_id)
    finally:
        connection.close()

    data = add_features(raw_data)
    base_chosen, candidate_results = walk_forward_validation(data)
    chosen, calibration_results = select_prediction_calibration(data, base_chosen)

    result, prediction_bundle, prediction_mode, diagnostics_train = predict_selected_year(selected_year, data, chosen)
    diagnostics = model_diagnostics(prediction_bundle, diagnostics_train)

    validation_rows = build_validation_rows(data, chosen)
    validation_metrics = dict(chosen["metrics"])
    latest_fold = chosen["folds"][-1] if chosen.get("folds") else {}

    selected_rows = result[result["month"] == selected_month]
    if selected_rows.empty:
        raise ValueError(f"ไม่พบข้อมูลเดือน {selected_month}")
    selected_row = selected_rows.iloc[0]

    output_result = clean_result_for_output(result, requested_province)
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    safe_province = re.sub(r"[\\/:*?\"<>|]", "_", requested_province)
    csv_name = f"{safe_province}_predicted_linear.csv"
    output_result.to_csv(
        os.path.join(OUTPUT_DIR, csv_name),
        index=False,
        encoding="utf-8-sig",
    )

    save_forecast_graph(result, selected_year)
    save_validation_outputs(
        validation_rows,
        validation_metrics,
        diagnostics,
        chosen,
    )

    candidate_summary = [
        {
            "name": candidate["name"],
            "target_transform": candidate["target_transform"],
            "model_type": candidate.get("model_type", "ols"),
            "alpha": candidate.get("alpha", 0.0),
            "n_components": candidate.get("n_components", 0),
            "blend_weight": candidate.get("blend_weight", 1.0),
            "rmse": candidate["metrics"].get("rmse"),
            "mae": candidate["metrics"].get("mae"),
            "r2": candidate["metrics"].get("r2"),
        }
        for candidate in candidate_results[:5]
    ]

    candidate_csv_name = "regression_candidate_comparison.csv"
    pd.DataFrame(candidate_summary).to_csv(
        os.path.join(OUTPUT_DIR, candidate_csv_name),
        index=False,
        encoding="utf-8-sig",
    )

    calibration_summary = [
        {
            "annual_calibration": item["annual_calibration"],
            "annual_calibration_weight": item["annual_calibration_weight"],
            "guardrail": item["guardrail"],
            "rmse": item["metrics"].get("rmse"),
            "mae": item["metrics"].get("mae"),
            "p90_absolute_error": item["metrics"].get("p90_absolute_error"),
        }
        for item in calibration_results[:5]
    ]

    response = {
        "status": "success",
        "province": requested_province,
        "database_province": database_province,
        "station_id": station_id,
        "year": int(selected_year),
        "month": int(selected_month),
        "sst": round(float(selected_row["sst"]), 4),
        "chlor_a": round(float(selected_row["chlorophyll_a"]), 4),
        "ton": round(float(selected_row["predicted_mackerel_ton"]), 4),
        "actual_ton": round(float(selected_row["actual_mackerel_ton"]), 4)
        if "actual_mackerel_ton" in selected_row.index
        else None,
        "prediction_mode": prediction_mode,
        "data_source": "MySQL database: catch_mackereldata + marine_environment",
        "database_driver": driver_name,
        "data_year_start": int(data["year"].min()),
        "data_year_end": int(data["year"].max()),
        "source_counts": source_counts,
        "model": {
            "selected_name": chosen["name"],
            "target_transform": chosen["target_transform"],
            "model_type": chosen.get("model_type", "ols"),
            "alpha": chosen.get("alpha", 0.0),
            "n_components": chosen.get("n_components", 0),
            "blend_weight": chosen.get("blend_weight", 1.0),
            "features": [FEATURE_LABELS.get(feature, feature) for feature in chosen["features"]],
            "validation_method": "Expanding-window walk-forward validation by year",
            "model_selection_rule": "Lowest walk-forward RMSE, then MAE and 90th-percentile absolute error",
            "validation_years": chosen.get("validation_years", []),
            "validation_metrics": validation_metrics,
            "latest_holdout_metrics": latest_fold,
            "candidate_summary": candidate_summary,
            "calibration_summary": calibration_summary,
            "annual_calibration": chosen.get("annual_calibration", "none"),
            "annual_calibration_label": ANNUAL_CALIBRATION_LABELS.get(
                chosen.get("annual_calibration", "none"), chosen.get("annual_calibration", "none")
            ),
            "annual_calibration_weight": chosen.get("annual_calibration_weight", 0.0),
            "guardrail": chosen.get("guardrail", "none"),
            "guardrail_label": GUARDRAIL_LABELS.get(
                chosen.get("guardrail", "none"), chosen.get("guardrail", "none")
            ),
            "diagnostics": diagnostics,
        },
        "csv": "output/" + csv_name,
        "evaluation_csv": "output/regression_model_evaluation.csv",
        "candidate_csv": "output/" + candidate_csv_name,
        "metrics_json": "output/regression_model_metrics.json",
        "fish_graph": "output/regression_fish.png",
        "sst_graph": "output/regression_sst.png",
        "chlor_graph": "output/regression_chlor.png",
    }
    send_json(response)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        send_json({
            "status": "error",
            "message": str(error),
        })
