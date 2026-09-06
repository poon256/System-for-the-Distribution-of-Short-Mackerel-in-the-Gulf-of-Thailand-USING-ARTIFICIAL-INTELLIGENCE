import pandas as pd
import mysql.connector

# =========================================================
# ตั้งค่า
# =========================================================

# พิกัดจังหวัด
LAT = 13.00
LON = 100.18

#ระบุจังหวัดนะจร๊ะ
STATION_ID = 1

# =========================================================
# ข้อมูล NOAA
# ค.ศ. 2019 - 2024
# = พ.ศ. 2562 - 2567
# =========================================================

START_DATE = "2019-01-01"
END_DATE = "2025-01-01"


# =========================================================
# พื้นที่ค้นหารอบพิกัด
# =========================================================

LAT_MIN = LAT - 0.50
LAT_MAX = LAT + 0.50

LON_MIN = LON - 0.50
LON_MAX = LON + 0.50


# =========================================================
# NOAA ERDDAP
# =========================================================

url_salinity = (
    "https://oceanwatch.pifsc.noaa.gov/erddap/griddap/"
    "smos_3day.csv?"
    f"sss[({START_DATE}T00:00:00Z):1:"
    f"({END_DATE}T00:00:00Z)]"
    "[0]"
    f"[({LAT_MIN}):1:({LAT_MAX})]"
    f"[({LON_MIN}):1:({LON_MAX})]"
)


print("=" * 70)
print("SALINITY IMPORT")
print("=" * 70)

print(f"station_id : {STATION_ID}")
print(f"latitude   : {LAT}")
print(f"longitude  : {LON}")

print(
    f"ช่วง NOAA : {START_DATE} ถึง {END_DATE}"
)

print(
    f"พื้นที่ค้นหา: "
    f"LAT {LAT_MIN} ถึง {LAT_MAX}, "
    f"LON {LON_MIN} ถึง {LON_MAX}"
)

print("\nURL NOAA:")
print(url_salinity)


# =========================================================
# โหลดข้อมูล
# =========================================================

print("\nกำลังโหลดข้อมูล...")

try:

    df = pd.read_csv(url_salinity)

except Exception as e:

    print("\n❌ โหลดข้อมูล NOAA ไม่สำเร็จ")
    print(e)
    exit()


print("\nโหลดข้อมูลสำเร็จ")

print(
    "จำนวนข้อมูลทั้งหมด:",
    len(df)
)


print("\nColumns:")

print(
    df.columns.tolist()
)


print("\nตัวอย่างข้อมูล:")

print(
    df.head()
)


# =========================================================
# ตรวจสอบ column
# =========================================================

if "sss" not in df.columns:

    print("\n❌ ไม่พบ column sss")
    exit()


if "time" not in df.columns:

    print("\n❌ ไม่พบ column time")
    exit()


# =========================================================
# แปลง Salinity
# =========================================================

df["sss"] = pd.to_numeric(
    df["sss"],
    errors="coerce"
)


print("\n" + "=" * 70)
print("ตรวจสอบ Salinity")
print("=" * 70)

print(
    "ข้อมูลทั้งหมด:",
    len(df)
)

print(
    "มีค่า Salinity:",
    df["sss"].notna().sum()
)

print(
    "เป็น NaN:",
    df["sss"].isna().sum()
)


# =========================================================
# ลบ NaN
# =========================================================

df = df.dropna(
    subset=["sss"]
).copy()


if df.empty:

    print("\n❌ ไม่มีค่า Salinity ที่ใช้งานได้")
    exit()


# =========================================================
# แปลงเวลา
# =========================================================

df["time"] = pd.to_datetime(
    df["time"],
    errors="coerce"
)


df = df.dropna(
    subset=["time"]
)


# =========================================================
# จำกัดช่วงเวลา
# =========================================================

df = df[
    (df["time"] >= START_DATE)
    &
    (df["time"] < END_DATE)
].copy()


if df.empty:

    print(
        "\n❌ ไม่มีข้อมูลในช่วงเวลาที่กำหนด"
    )

    exit()


# =========================================================
# เพิ่มปี / เดือน
# =========================================================

df["year"] = df["time"].dt.year

df["month"] = df["time"].dt.month


# =========================================================
# ค่าเฉลี่ย Salinity รายเดือน
# =========================================================

monthly = (

    df.groupby(
        ["year", "month"],
        as_index=False
    )["sss"]

    .mean()

)


monthly["sss"] = monthly[
    "sss"
].round(4)


# =========================================================
# แปลงปี ค.ศ. -> พ.ศ.
# =========================================================

monthly["year_be"] = (
    monthly["year"] + 543
)


# =========================================================
# แสดงผล
# =========================================================

print("\n" + "=" * 70)
print("ค่าเฉลี่ย Salinity รายเดือน")
print("=" * 70)

print(
    monthly[
        ["year", "year_be", "month", "sss"]
    ].to_string(index=False)
)


# =========================================================
# ตรวจจำนวนเดือน
# =========================================================

print("\n" + "=" * 70)
print("สรุปข้อมูล")
print("=" * 70)

print(
    "จำนวนเดือนที่มีข้อมูล:",
    len(monthly)
)

print(
    "จำนวนเดือนที่ควรมี:",
    72
)

print(
    "เดือนที่ขาด:",
    72 - len(monthly)
)


# =========================================================
# MySQL
# =========================================================

print("\n" + "=" * 70)
print("กำลังเชื่อมต่อ MySQL")
print("=" * 70)


try:

    conn = mysql.connector.connect(

        host="127.0.0.1",

        port=3306,

        user="root",

        password="",

        database="projecta",

        use_pure=True

    )

except Exception as e:

    print(
        "\n❌ เชื่อมต่อ MySQL ไม่สำเร็จ"
    )

    print(e)

    exit()


print(
    "เชื่อมต่อ MySQL สำเร็จ"
)


cursor = conn.cursor()


# =========================================================
# UPDATE
# =========================================================

sql = """

UPDATE marine_environment

SET salinity = %s

WHERE station_id = %s

AND year = %s

AND month = %s

"""


update_count = 0

not_found_count = 0


print("\n" + "=" * 70)
print("กำลัง UPDATE Salinity")
print("=" * 70)


try:

    for _, row in monthly.iterrows():

        # -------------------------------------------------
        # Salinity
        # -------------------------------------------------

        salinity = float(
            row["sss"]
        )


        # -------------------------------------------------
        # ปีจาก NOAA ค.ศ.
        # แปลงเป็น พ.ศ.
        # -------------------------------------------------

        year_be = int(
            row["year"]
        ) + 543


        month = int(
            row["month"]
        )


        values = (

            salinity,

            STATION_ID,

            year_be,

            month

        )


        print(
            f"station={STATION_ID} "
            f"year={year_be} "
            f"month={month:02d} "
            f"salinity={salinity}"
        )


        cursor.execute(
            sql,
            values
        )


        print(
            "  rowcount =",
            cursor.rowcount
        )


        if cursor.rowcount > 0:

            update_count += cursor.rowcount

        else:

            not_found_count += 1


    # =====================================================
    # COMMIT
    # =====================================================

    conn.commit()


except Exception as e:

    conn.rollback()

    print(
        "\n❌ UPDATE ไม่สำเร็จ"
    )

    print(e)

    cursor.close()

    conn.close()

    exit()


# =========================================================
# ปิด MySQL
# =========================================================

cursor.close()

conn.close()


# =========================================================
# สรุป
# =========================================================

print("\n" + "=" * 70)
print("SALINITY IMPORT เสร็จสิ้น")
print("=" * 70)

print(
    f"ข้อมูลจาก NOAA ที่มีค่า: {len(monthly)} เดือน"
)

print(
    f"UPDATE สำเร็จ: {update_count} แถว"
)

print(
    f"ไม่พบข้อมูลใน marine_environment: {not_found_count} เดือน"
)


if len(monthly) < 72:

    print("\n⚠️ NOAA ไม่มีข้อมูลครบ 72 เดือน")

    print(
        "ไม่ได้เติมค่าที่ไม่มีข้อมูล เพื่อไม่สร้างข้อมูลปลอม"
    )

print("\nจบการทำงาน")