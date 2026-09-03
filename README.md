# Mackerel Catch Analysis and Prediction System

ระบบวิเคราะห์และคาดการณ์จำนวนปลาทูในอ่าวไทยตอนบน
ด้วยเทคนิค Machine Learning

เป็นโปรเจคของมหาลัยที่ผมได้ทำ

## Features

- วิเคราะห์ข้อมูลการจับปลาทูในอ่าวไทยตอนบน
- วิเคราะห์ปัจจัยทางทะเลและสภาพอากาศ
- วิเคราะห์ Sea Surface Temperature (SST)
- วิเคราะห์ Chlorophyll-a
- วิเคราะห์ปริมาณน้ำฝน (Rainfall)
- รวมข้อมูลเพื่อสร้าง Dataset สำหรับ Machine Learning
- คาดการณ์ปริมาณการจับปลาทู
- จำแนกระดับปริมาณปลาทูด้วย Random Forest
- จัดกลุ่มสภาพแวดล้อมด้วย K-Means Clustering
- แสดงผลการวิเคราะห์ผ่าน Web Dashboard

## Machine Learning

### Linear Regression
ใช้สำหรับคาดการณ์ปริมาณการจับปลาทู

### Random Forest
ใช้สำหรับจำแนกระดับปริมาณปลาทู เช่น

- Low
- Medium
- High

### K-Means Clustering
ใช้สำหรับจัดกลุ่มพื้นที่/ช่วงเวลาตามลักษณะสภาพแวดล้อม

Features ที่ใช้ในการ Clustering:

- Sea Surface Temperature (SST)
- Chlorophyll-a
- Rainfall

ใช้ StandardScaler สำหรับปรับมาตราส่วนข้อมูลก่อนทำ Clustering

ประเมินคุณภาพของ Clustering ด้วย:

- Silhouette Score
- Davies-Bouldin Index (DBI)
- WCSS / Elbow Method
- PCA

## Data

ข้อมูลประกอบด้วย:

- ปี
- เดือน
- จังหวัด / สถานี
- อุปกรณ์ประมง
- ปริมาณปลาทู
- Sea Surface Temperature (SST)
- Chlorophyll-a
- Rainfall
- Wind Speed

> หมายเหตุ: Wind Speed ถูกจัดเก็บไว้ใน Dataset เพื่อใช้ในการวิเคราะห์ประกอบ
> แต่ไม่ได้ใช้เป็น Feature หลักในการสร้าง K-Means Cluster

## Data Sources

- NOAA ERDDAP
- Open-Meteo
- ข้อมูลสถิติการจับปลาทู

## Technologies

- Python
- Pandas
- Scikit-learn
- SciPy
- MySQL
- MySQL Connector/Python
- PHP
- JavaScript
- HTML
- CSS
- Bootstrap
- NOAA ERDDAP
- Open-Meteo

## System Architecture

Data Sources
↓
Data Preprocessing
↓
MySQL Database
↓
Dataset ML
↓
Machine Learning
↓
Web Dashboard

## Project Structure

projectA/
│
├── model/
│   ├── linear_regression.py
│   ├── random_forest.py
│   └── k_means.py
│
├── preprocessing/
│   └── ...
│
├── process/
│   └── ...
│
├── database/
│   └── database.sql
│
├── dashboard/
│   └── ...
│
├── data/
│   └── README.md
│
├── docs/
│   └── ...
│
├── requirements.txt
└── README.md

## Clustering Result

จากการทดลองจำนวน Cluster ตั้งแต่ K = 2–8
พบว่า K = 2 ให้ค่า Silhouette Score สูงที่สุด

- Silhouette Score ≈ 0.47
- Davies-Bouldin Index ≈ 0.85
- PCA 2 Components อธิบายความแปรปรวนได้ประมาณ 80%

ผลการจัดกลุ่มสามารถนำไปใช้ในการวิเคราะห์ความแตกต่าง
ของสภาพแวดล้อมในแต่ละพื้นที่และช่วงเวลา
และนำปริมาณการจับปลาทูมาใช้ในการตีความระดับของ Cluster

## Project Objective

ระบบนี้มีวัตถุประสงค์เพื่อรวบรวมข้อมูลการจับปลาทู
ร่วมกับข้อมูลสภาพแวดล้อมทางทะเลและสภาพอากาศ
เพื่อนำมาวิเคราะห์และสร้างแบบจำลอง Machine Learning

ผลลัพธ์ที่ได้สามารถช่วยวิเคราะห์:

- ปริมาณการจับปลาทู
- ระดับการจับปลาทู
- รูปแบบของสภาพแวดล้อม
- การกระจายตัวของปลาทูในแต่ละพื้นที่และช่วงเวลา
