import requests
import pandas as pd
import mysql.connector

#พี่กัดจังหวัด 
lat = 13.48
lon = 100.60
#ระบุจังหวัดนะจร๊ะ
station_id = 5

UrlW = (
    "https://archive-api.open-meteo.com/v1/archive"
    f"?latitude={lat}"
    f"&longitude={lon}"
    "&start_date=2021-01-01"
    "&end_date=2021-12-31"
    "&daily=precipitation_sum,wind_speed_10m_max"
    "&timezone=Asia/Bangkok"
)

print("กำลังโหลดข้อมูล...")

data = requests.get(UrlW).json()

# สร้าง DataFrame
df = pd.DataFrame({
    "time": data["daily"]["time"],
    "rainfall": data["daily"]["precipitation_sum"],
    "wind_speed": data["daily"]["wind_speed_10m_max"]
})

print(df.head())
print(df.columns)

# แปลงเวลา
df["time"] = pd.to_datetime(df["time"])

# เพิ่ม year และ month
df["year"] = df["time"].dt.year
df["month"] = df["time"].dt.month

# กำหนดฤดูกาล
def get_season(month):
    if month in [2, 3, 4, 5]:
        return "summer"
    elif month in [6, 7, 8, 9, 10]:
        return "rainy"
    else:
        return "winter"

df["season"] = df["month"].apply(get_season)

# รวมข้อมูลรายเดือน
monthly = (
    df.groupby(["year", "month"])
      .agg({
          "rainfall": "sum",
          "wind_speed": "mean",
          "season": "first"
      })
      .reset_index()
)

print("\nข้อมูลรายเดือน")
print(monthly)

# เชื่อมSql
conn = mysql.connector.connect(
    host="127.0.0.1",
    port=3306,
    user="root",
    password="",
    database="projecta",
    use_pure=True
    )

cursor = conn.cursor()

# เซฟข้อมูล/เพิ่ม
sql = """
INSERT INTO weather_data
(
    station_id,
    rainfall,
    wind_speed,
    season,
    year,
    month,
    status
)
VALUES
(
%s,%s,%s,%s,%s,%s,%s
)
"""

for _, row in monthly.iterrows():

    values = (
        station_id,
        float(row["rainfall"]),
        float(row["wind_speed"]),
        row["season"],
        int(row["year"]),
        int(row["month"]),
        1
    )

    print(values)
    cursor.execute(sql, values)
    print("rowcount =", cursor.rowcount)

conn.commit()
print(f"\nบันทึกข้อมูลสำเร็จ {len(monthly)} เดือน")
cursor.close()
conn.close()