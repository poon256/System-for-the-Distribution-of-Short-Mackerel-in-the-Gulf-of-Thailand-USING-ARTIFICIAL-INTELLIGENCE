import pandas as pd
import mysql.connector

#พี่กัดจังหวัด 
lat = 13.48
lon = 100.60
#ระบุจังหวัดนะจร๊ะ
station_id = 5

urlC = (
    "https://oceanwatch.pifsc.noaa.gov/erddap/griddap/"
    "noaa_snpp_chla_monthly.csv?"
    f"chlor_a[(2021-01-01T00:00:00Z):1:(2021-12-31T00:00:00Z)]"
    f"[({lat})][({lon})]"
)

print("กำลังโหลดข้อมูล...")
df = pd.read_csv(urlC)
print(df.head())
print(df.columns)


# ลบแถวที่ไม่ใช่ข้อมูล
df['chlor_a'] = pd.to_numeric(df['chlor_a'], errors='coerce')
df = df.dropna(subset=['chlor_a'])

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

# คำนวณค่าเฉลี่ย chlor_a รายเดือน
monthly = (
    df.groupby(['year', 'month'])['chlor_a']
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
UPDATE marine_environment
SET chlorophyll_a = %s
WHERE station_id = %s
AND year = %s
AND month = %s
"""

for _, row in monthly.iterrows():

    values = (
        float(row['chlor_a']), 
        station_id,          
        int(row['year']),     
        int(row['month'])  
    )

    print(values) 
    cursor.execute(sql, values)
    print("rowcount =", cursor.rowcount)

conn.commit()
print(f"\nบันทึกข้อมูลสำเร็จ {len(monthly)} เดือน")
cursor.close()
conn.close()