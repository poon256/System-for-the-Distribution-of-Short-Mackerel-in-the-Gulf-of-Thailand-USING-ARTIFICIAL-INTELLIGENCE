import requests
import pandas as pd
import mysql.connector

# พิกัดจังหวัด
lat = 13.45
lon = 100.70

#ระบุจังหวัดนะจร๊ะ
station_id = 5


# ช่วงข้อมูล
UrlW = (
    "https://archive-api.open-meteo.com/v1/archive"
    f"?latitude={lat}"
    f"&longitude={lon}"
    "&start_date=2026-01-01"
    "&end_date=2026-08-31"
    "&daily="
    "temperature_2m_mean,"
    "wind_direction_10m_dominant"
    "&timezone=Asia/Bangkok"
)


# โหลดข้อมูล
print("=" * 60)
print("WEATHER IMPORT")
print("=" * 60)

print("station_id :", station_id)
print("latitude   :", lat)
print("longitude  :", lon)

print("\nกำลังโหลดข้อมูล...")

response = requests.get(
    UrlW,
    timeout=60
)

response.raise_for_status()

data = response.json()


# สร้าง DataFrame
df = pd.DataFrame({
    "time": data["daily"]["time"],

    "air_temperature": data["daily"]["temperature_2m_mean"],

    "wind_direction": data["daily"]["wind_direction_10m_dominant"]
})


print("\nข้อมูลรายวัน")

print(df.head())

print("\nColumns")

print(df.columns.tolist())


# แปลงเวลา
df["time"] = pd.to_datetime(
    df["time"],
    errors="coerce"
)

df = df.dropna(
    subset=["time"]
)


# ปี / เดือน
df["year"] = df["time"].dt.year

df["month"] = df["time"].dt.month


# ฤดูกาล
def get_season(month):

    if month in [2, 3, 4, 5]:

        return "summer"

    elif month in [6, 7, 8, 9, 10]:

        return "rainy"

    else:

        return "winter"


df["season"] = df["month"].apply(
    get_season
)


# รวมข้อมูลรายเดือน
monthly = (

    df.groupby(
        ["year", "month"],
        as_index=False
    )

    .agg({

        "air_temperature": "mean",

        "wind_direction": "mean",

        "season": "first"

    })

)


# ปัดทศนิยม
monthly["air_temperature"] = monthly[
    "air_temperature"
].round(4)

monthly["wind_direction"] = monthly[
    "wind_direction"
].round(4)


# แสดงข้อมูลรายเดือน
print("\n" + "=" * 60)

print("ข้อมูลอากาศรายเดือน")

print("=" * 60)

print(
    monthly.to_string(
        index=False
    )
)


# เชื่อม MySQL
print("\n" + "=" * 60)

print("กำลังเชื่อมต่อ MySQL")

print("=" * 60)


conn = mysql.connector.connect(

    host="127.0.0.1",

    port=3306,

    user="root",

    password="",

    database="projecta",

    use_pure=True

)


print(
    "เชื่อมต่อ MySQL สำเร็จ"
)


cursor = conn.cursor()


# =========================================================
# UPDATE
# =========================================================
sql = """
UPDATE weather_data
SET air_temperature = %s,
    wind_direction = %s,
    season = %s
WHERE station_id = %s
AND year = %s
AND month = %s

"""

update_count = 0

print("\n" + "=" * 60)
print("กำลัง UPDATE weather_data")
print("=" * 60)

try:

    for _, row in monthly.iterrows():

        air_temperature = float(
            row["air_temperature"]
        )

        wind_direction = float(
            row["wind_direction"]
        )

        season = row["season"]

        # Open-Meteo = ค.ศ.
        # Database = พ.ศ.
        year = int( row["year"]) + 543

        month = int( row["month"])

        values = (

            air_temperature,
            wind_direction,
            season,

            station_id,
            year,
            month

        )

        cursor.execute(
            sql,
            values
        )

        print(
            f"station={station_id} "
            f"year={year} "
            f"month={month:02d} "
            f"air_temperature={air_temperature} "
            f"wind_direction={wind_direction} "
            f"rowcount={cursor.rowcount}"
        )

        if cursor.rowcount > 0:
            update_count += cursor.rowcount

    conn.commit()

except Exception as e:

    conn.rollback()

    print("\n❌ UPDATE ไม่สำเร็จ")
    print(e)

    cursor.close()
    conn.close()

    exit()