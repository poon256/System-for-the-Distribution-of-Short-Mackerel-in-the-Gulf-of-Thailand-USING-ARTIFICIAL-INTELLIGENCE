# -*- coding: utf-8 -*-

import os
import sys
import json
import base64
import warnings

warnings.filterwarnings("ignore")

try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

import pandas as pd

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

from sklearn.ensemble import RandomForestClassifier

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

FISH_CSV = os.path.join(
    BASE_DIR,
    "mackerel_upper_gulf.csv"
)

ENV_CSV = os.path.join(
    BASE_DIR,
    "marine_environment.csv"
)

PROJECT_DIR = os.path.dirname(BASE_DIR)

OUTPUT_DIR = os.path.join(
    BASE_DIR,
    "output"
)

def send_json(data):
    """
    ส่ง JSON กลับไปยัง PHP
    """
    print(
        json.dumps(
            data,
            ensure_ascii=False
        )
    )

def decode_province(value, is_base64=False):

    if is_base64:

        try:

            return base64.b64decode(
                value
            ).decode(
                "utf-8"
            ).strip()

        except Exception:

            return value.strip()

    return value.strip()

def read_args():

    if len(sys.argv) < 4:

        send_json({

            "status": "error",

            "message": "Missing arguments: province, year, month"

        })

        sys.exit(0)

    is_base64 = (
        len(sys.argv) >= 5
        and sys.argv[4] == "--b64"
    )

    province = decode_province(
        sys.argv[1],
        is_base64
    )

    try:

        year = int(sys.argv[2])

        month = int(sys.argv[3])

    except Exception:

        send_json({

            "status": "error",

            "message": "Year and month must be numbers"

        })

        sys.exit(0)

    if province == "":

        send_json({

            "status": "error",

            "message": "Province is empty"

        })

        sys.exit(0)

    if month < 1 or month > 12:

        send_json({

            "status": "error",

            "message": "Month must be 1-12"

        })

        sys.exit(0)

    return province, year, month

def load_csv():

    if not os.path.exists(FISH_CSV):

        send_json({

            "status": "error",

            "message": f"ไม่พบไฟล์ {FISH_CSV}"

        })

        sys.exit(0)

    if not os.path.exists(ENV_CSV):

        send_json({

            "status": "error",

            "message": f"ไม่พบไฟล์ {ENV_CSV}"

        })

        sys.exit(0)

    fish = pd.read_csv(
        FISH_CSV,
        encoding="utf-8-sig"
    )

    env = pd.read_csv(
        ENV_CSV,
        encoding="utf-8-sig"
    )

    return fish, env

def convert_month_columns(fish, env):

    month_map = {

        "มกราคม": 1,
        "กุมภาพันธ์": 2,
        "มีนาคม": 3,
        "เมษายน": 4,
        "พฤษภาคม": 5,
        "มิถุนายน": 6,
        "กรกฎาคม": 7,
        "สิงหาคม": 8,
        "กันยายน": 9,
        "ตุลาคม": 10,
        "พฤศจิกายน": 11,
        "ธันวาคม": 12,

    }

    for data in [fish, env]:

        data["จังหวัด"] = data["จังหวัด"].astype(str).str.strip()

        data["เดือน"] = data["เดือน"].astype(str).str.strip()

        data["เดือน"] = data["เดือน"].map(month_map).fillna(
            pd.to_numeric(
                data["เดือน"],
                errors="coerce"
            )
        )

        data["เดือน"] = pd.to_numeric(
            data["เดือน"],
            errors="coerce"
        )

        data["ปี"] = pd.to_numeric(
            data["ปี"],
            errors="coerce"
        )

    fish = fish.dropna(
        subset=[
            "จังหวัด",
            "ปี",
            "เดือน"
        ]
    )

    env = env.dropna(
        subset=[
            "จังหวัด",
            "ปี",
            "เดือน"
        ]
    )

    fish["ปี"] = fish["ปี"].astype(int)
    fish["เดือน"] = fish["เดือน"].astype(int)

    env["ปี"] = env["ปี"].astype(int)
    env["เดือน"] = env["เดือน"].astype(int)

    return fish, env

def prepare_data(
    fish,
    env,
    province
):

    fish_p = fish[
        fish["จังหวัด"] == province
    ].copy()

    env_p = env[
        env["จังหวัด"] == province
    ].copy()

    if fish_p.empty or env_p.empty:

        send_json({

            "status": "error",

            "message": f"ไม่พบจังหวัดดังกล่าว : {province}"

        })

        sys.exit(0)

    fish_monthly = (

        fish_p

        .groupby([
            "ปี",
            "เดือน"
        ])["ปริมาณ (ตัน)"]

        .sum()

        .reset_index()

        .rename(
            columns={
                "ปริมาณ (ตัน)": "catch"
            }
        )

    )

    df = pd.merge(

        env_p,

        fish_monthly,

        on=[
            "ปี",
            "เดือน"
        ],

        how="inner"

    )

    df = df.dropna(

        subset=[

            "อุณหภูมิผิวทะเล",

            "คลอโรฟิลล์-เอ",

            "ปี",

            "เดือน",

            "catch"

        ]

    )

    if df.empty:

        send_json({

            "status": "error",

            "message": "Merge ข้อมูลไม่สำเร็จ"

        })

        sys.exit(0)

    return (

        fish_monthly,

        env_p,

        df

    )

def create_density_class(train_df):
    """
    Convert catch (ton) into 3 density levels
    using Quantile (33%, 66%)
    """

    train_df = train_df.copy()

    train_df["density_level"] = pd.qcut(
    train_df["catch"],
    q=3,
    labels=[
        "LOW",
        "MEDIUM",
        "HIGH"
    ],
    duplicates="drop"
)

    return train_df

def train_random_forest(df, selected_year):

    train_df = df[
        df["ปี"] < selected_year
    ].copy()

    # ถ้าข้อมูลก่อนปีที่เลือกน้อย
    # ให้ใช้ข้อมูลทั้งหมด

    if len(train_df) < 6:

        train_df = df.copy()

    if len(train_df) < 6:

        send_json({

            "status": "error",

            "message": "ข้อมูลสำหรับ Train ไม่เพียงพอ"

        })

        sys.exit(0)

    train_df = create_density_class(train_df)

    X_train = train_df[

        [

            "อุณหภูมิผิวทะเล",

            "คลอโรฟิลล์-เอ",

            "เดือน"

        ]

    ]

    y_train = train_df["density_level"]

    model = RandomForestClassifier(

        n_estimators=200,

        random_state=42

    )

    model.fit(

        X_train,

        y_train

    )

    return model

def predict_density_level(
    model,
    env_p,
    selected_year,
    selected_month
):

    month_env = env_p[
        (env_p["ปี"] == selected_year)
        &
        (env_p["เดือน"] == selected_month)
    ].copy()

    if month_env.empty:

        month_env = (

            env_p

            .groupby("เดือน")[

                [

                    "อุณหภูมิผิวทะเล",

                    "คลอโรฟิลล์-เอ"

                ]

            ]

            .mean()

            .reset_index()

        )

        month_env = month_env[
            month_env["เดือน"] == selected_month
        ]

    if month_env.empty:

        send_json({

            "status": "error",

            "message": "ไม่พบข้อมูลสำหรับเดือนที่เลือก"

        })

        sys.exit(0)

    X_predict = pd.DataFrame(

        [

            {

                "อุณหภูมิผิวทะเล":

                    float(
                        month_env.iloc[0]["อุณหภูมิผิวทะเล"]
                    ),

                "คลอโรฟิลล์-เอ":

                    float(
                        month_env.iloc[0]["คลอโรฟิลล์-เอ"]
                    ),

                "เดือน":

                    int(selected_month)

            }

        ]

    )

    level = model.predict(
        X_predict
    )[0]

    probability_values = model.predict_proba(
        X_predict
    )[0]

    class_names = model.classes_

    probability = {}

    for label, value in zip(
        class_names,
        probability_values
    ):

        probability[label] = round(
            float(value) * 100,
            2
        )

    level_info = {

        "LOW": {

            "description": "Low Density Area",

            "badge_color": "success"

        },

        "MEDIUM": {

            "description": "Medium Density Area",

            "badge_color": "warning"

        },

        "HIGH": {

            "description": "High Density Area",

            "badge_color": "danger"

        }

    }

    return {

        "level": level,

        "description": level_info[level]["description"],

        "badge_color": level_info[level]["badge_color"],

        "probability": probability,

        "sst":

            float(
                month_env.iloc[0]["อุณหภูมิผิวทะเล"]
            ),

        "chlor_a":

            float(
                month_env.iloc[0]["คลอโรฟิลล์-เอ"]
            )

    }
def save_probability_graph(probability):

    os.makedirs(
        OUTPUT_DIR,
        exist_ok=True
    )

    labels = list(
        probability.keys()
    )

    values = list(
        probability.values()
    )

    plt.figure(figsize=(6,4))

    plt.bar(
        labels,
        values
    )

    plt.ylim(0,100)

    plt.ylabel("Probability (%)")

    plt.title(
        "Random Forest Classification Probability"
    )

    plt.tight_layout()

    filename = "classification_probability.png"

    save_path = os.path.join(
        OUTPUT_DIR,
        filename
    )

    plt.savefig(
        save_path,
        dpi=160
    )

    plt.close()

    return "../model/output/" + filename

def main():

    province, selected_year, selected_month = read_args()

    fish, env = load_csv()

    fish, env = convert_month_columns(
        fish,
        env
    )

    fish_monthly, env_p, df = prepare_data(
        fish,
        env,
        province
    )

    model = train_random_forest(
        df,
        selected_year
    )

    result = predict_density_level(
        model,
        env_p,
        selected_year,
        selected_month
    )

    probability_graph = save_probability_graph(
    result["probability"]
)

    send_json({

    "status": "success",

    "province": province,

    "year": int(selected_year),

    "month": int(selected_month),

    "level": result["level"],

    "description": result["description"],

    "badge_color": result["badge_color"],

    "sst": result["sst"],

    "chlor_a": result["chlor_a"],

    "probability": result["probability"],

    "probability_graph": probability_graph

})

if __name__ == "__main__":

    try:

        main()

    except Exception as e:

        send_json({

            "status": "error",

            "message": str(e)

        })


