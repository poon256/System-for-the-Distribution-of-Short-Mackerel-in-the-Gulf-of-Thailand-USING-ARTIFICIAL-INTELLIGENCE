import pandas as pd
import mysql.connector
import numpy as np

# พิกัดจังหวัด
lat = 13.00
lon = 100.18
#ระบุจังหวัดนะจร๊ะ
station_id = 1

# NOAA Sea Surface Currents
urlC = (
    "https://coastwatch.noaa.gov/erddap/griddap/"
    "noaacwBLENDEDNRTcurrentsDaily.csv?"
    f"u_current[(2019-01-01T00:00:00Z):1:(2019-12-31T00:00:00Z)]"
    f"[({lat})][({lon})],"
    f"v_current[(2019-01-01T00:00:00Z):1:(2019-12-31T00:00:00Z)]"
    f"[({lat})][({lon})]"
)

print("กำลังโหลดข้อมูลกระแสน้ำ...")
df = pd.read_csv(urlC)

print(df.head())
print(df.columns)


# --------------------------------
# แปลงข้อมูลเป็นตัวเลข
# --------------------------------

df['u_current'] = pd.to_numeric(
    df['u_current'],
    errors='coerce'
)

df['v_current'] = pd.to_numeric(
    df['v_current'],
    errors='coerce'
)

# ลบข้อมูลที่ไม่มีค่า
df = df.dropna(
    subset=['u_current', 'v_current']
)


# --------------------------------
# แปลงเวลา
# --------------------------------

df['time'] = pd.to_datetime(df['time'])

# กัน NOAA ส่งข้อมูลเกินปี
df = df[
    (df['time'] >= '2019-01-01') &
    (df['time'] < '2020-01-01')
]


# --------------------------------
# เพิ่ม year / month
# --------------------------------

df['year'] = df['time'].dt.year
df['month'] = df['time'].dt.month


# --------------------------------
# คำนวณความเร็วกระแสน้ำ
#
# speed = sqrt(u² + v²)
# --------------------------------

df['current_speed'] = np.sqrt(
    df['u_current'] ** 2 +
    df['v_current'] ** 2
)


# --------------------------------
# ค่าเฉลี่ยกระแสน้ำรายเดือน
# --------------------------------

monthly = (
    df.groupby(['year', 'month'])
      .agg({
          'u_current': 'mean',
          'v_current': 'mean',
          'current_speed': 'mean'
      })
      .reset_index()
)

print("\nค่าเฉลี่ยกระแสน้ำรายเดือน")
print(monthly)


# --------------------------------
# เชื่อม MySQL
# --------------------------------

conn = mysql.connector.connect(
    host="127.0.0.1",
    port=3306,
    user="root",
    password="",
    database="projecta",
    use_pure=True
)

cursor = conn.cursor()


# --------------------------------
# UPDATE ลง marine_environment
# --------------------------------

sql = """
UPDATE marine_environment
SET
    current_u = %s,
    current_v = %s,
    current_speed = %s
WHERE station_id = %s
AND year = %s
AND month = %s
"""


for _, row in monthly.iterrows():

    values = (
        float(row['u_current']),
        float(row['v_current']),
        float(row['current_speed']),
        station_id,
        int(row['year']),
        int(row['month'])
    )

    print(values)

    cursor.execute(sql, values)

    print("rowcount =", cursor.rowcount)


conn.commit()

print(
    f"\nบันทึกข้อมูลกระแสน้ำสำเร็จ "
    f"{len(monthly)} เดือน"
)

cursor.close()
conn.close()