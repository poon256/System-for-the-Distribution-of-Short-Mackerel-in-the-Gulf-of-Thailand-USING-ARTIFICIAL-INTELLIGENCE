import pandas as pd
import numpy as np
from sqlalchemy import create_engine, text

# ── เชื่อม MySQL server เดียวกัน ──
DB_HOST = "localhost"       # หรือ IP server
DB_USER = "root"
DB_PASS = ""
DB_NAME = "projecta"          # ชื่อ database จริง

engine = create_engine(
    f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}/{DB_NAME}",
    echo=False
)

# ── JOIN 3 ตารางตาม station + year + month ──
query = """
SELECT
    c.id            AS catch_id,
    c.station_id,
    c.year,
    c.month,
    c.equipment_id,
    c.amount        AS catch_amount,
    c.unit,
    m.sst,
    m.chlorophyll_a,
    w.rainfall,
    w.wind_speed,
    w.season
FROM catch_mackereldata c
LEFT JOIN marine_environment m
    ON c.station_id = m.station_id
    AND c.year = m.year
    AND c.month = m.month
LEFT JOIN weather_data w
    ON c.station_id = w.station_id
    AND c.year = w.year
    AND c.month = w.month
WHERE c.status = 1
  AND m.status = 1
  AND w.status = 1
ORDER BY c.year, c.month
"""

df = pd.read_sql(query, engine)
print(f"โหลดข้อมูลสำเร็จ: {len(df)} แถว")
print(df.head())

# Lag features (เดือนก่อนหน้า — เรียงตาม year/month)
df = df.sort_values(['station_id','year','month']).reset_index(drop=True)
df['catch_lag1'] = df.groupby('station_id')['catch_amount'].shift(1)
df['sst_lag1']   = df.groupby('station_id')['sst'].shift(1)
df['sst_ma3']    = df.groupby('station_id')['sst'].transform(
                       lambda x: x.rolling(3, min_periods=1).mean())
df['chl_ma3']    = df.groupby('station_id')['chlorophyll_a'].transform(
                       lambda x: x.rolling(3, min_periods=1).mean())

df = df.dropna(subset=['sst','chlorophyll_a','rainfall',
                        'wind_speed','catch_lag1'])
print(f"หลัง dropna: {len(df)} แถว")