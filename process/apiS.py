import pandas as pd
import mysql.connector

#พี่กัดจังหวัด 
lat = 13.48
lon = 100.60
#ระบุจังหวัดนะจร๊ะ
station_id = 5

urlS = (
    "https://coastwatch.pfeg.noaa.gov/erddap/griddap/"
    "ncdcOisst21Agg_LonPM180.csv?"
    f"sst[(2021-01-01T00:00:00Z):1:(2021-12-31T00:00:00Z)]"
    f"[(0.0)][({lat})][({lon})]"
)
print("กำลังโหลดข้อมูล...")
df = pd.read_csv(urlS)
print(df.head())
print(df.columns)


# ลบแถวที่ไม่ใช่ข้อมูล
df['sst'] = pd.to_numeric(df['sst'], errors='coerce')
df = df.dropna(subset=['sst'])

# แปลงเวลา
df['time'] = pd.to_datetime(df['time'])
# กันNoaaบันทึกเกิน
df = df[
    (df['time'] >= '2021-01-01') &
    (df['time'] < '2022-01-01')
]
# เพิ่ม year และ month
df['year'] = df['time'].dt.year
df['month'] = df['time'].dt.month

# คำนวณค่าเฉลี่ย SST รายเดือน
monthly = (
    df.groupby(['year', 'month'])['sst']
      .mean()
      .reset_index()
)

print("\nค่าเฉลี่ยรายเดือน")
print(monthly.head())


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

# เซฟข้อมูล
sql = """
INSERT INTO marine_environment
(
    station_id,
    sst,
    chlorophyll_a,
    year,
    month,
    status
)
VALUES
(
%s,%s,%s,%s,%s,%s
)
"""

for _, row in monthly.iterrows():

    values = (
        station_id,
        float(row['sst']),
        0,
        int(row['year']),
        int(row['month']),
        1
    )
    
    print(values) 
    cursor.execute(sql, values)
    print("rowcount =", cursor.rowcount)

conn.commit()
print(f"\nบันทึกข้อมูลสำเร็จ {len(monthly)} เดือน")
cursor.close()
conn.close()