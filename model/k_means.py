import pandas as pd
import mysql.connector
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import silhouette_score, davies_bouldin_score  # เพิ่ม DBI
from sklearn.decomposition import PCA 
import matplotlib.pyplot as plt
import seaborn as sns
from scipy.stats import f_oneway

# Connect MySQL
conn = mysql.connector.connect(
    host="127.0.0.1",
    port=3306,
    user="root",
    password="",
    database="projecta",
    use_pure=True
)

# อ่านข้อมูลจาก dataset_ml
sql = """
select
    station_id,
    year,
    month,
    equipment_id,
    sum(amount) AS amount,
    avg(sst) AS sst,
    avg(chlorophyll_a) AS chlorophyll_a,
    avg(rainfall) AS rainfall,
    avg(wind_speed) AS wind_speed
from dataset_ml
group by
    station_id,
    year,
    month,
    equipment_id
order by
    year,
    month,
    station_id,
    equipment_id
"""

df = pd.read_sql(sql, conn)

# ทำความสะอาดข้อมูล
df = df.dropna(subset=["amount", "sst", "chlorophyll_a", "rainfall", "wind_speed"])
df = df[df["amount"] > 0].copy()

print("จำนวนข้อมูลที่ใช้ K-Means:", len(df))

# รวมข้อมูลระดับสถานี + ปี + เดือน
df_cluster = (
    df.groupby(["station_id", "year", "month"])
    .agg({
        "amount": "sum",
        "sst": "mean",
        "chlorophyll_a": "mean",
        "rainfall": "mean",
        "wind_speed": "mean"
    })
    .reset_index()
)

print("จำนวนข้อมูลสำหรับ Clustering:", len(df_cluster))

# Feature สำหรับ K-Means
features = [
             "sst",
               "chlorophyll_a", 
               "rainfall"
            ]
X = df_cluster[features].copy()

# Standardize
scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)

# ทดลอง K = 2 ถึง 8
best_k = None
best_score = -1

wcss = []
silhouette_scores = []
dbi_scores = []  # เก็บค่า DBI

print("\n=== ทดสอบจำนวน Cluster ===")
for k in range(2, 9):
    kmeans_test = KMeans(n_clusters=k, random_state=42, n_init=20)
    labels = kmeans_test.fit_predict(X_scaled)

    # Metrics
    wcss.append(kmeans_test.inertia_)
    score = silhouette_score(X_scaled, labels)
    dbi = davies_bouldin_score(X_scaled, labels)  # คำนวณ DBI
    
    silhouette_scores.append(score)
    dbi_scores.append(dbi)

    print(
        f"K = {k} | "
        f"WCSS = {kmeans_test.inertia_:.2f} | "
        f"Silhouette = {score:.4f} | "
        f"DBI = {dbi:.4f}"
    )

    if score > best_score:
        best_score = score
        best_k = k

print("\n================================")
print("Best K =", best_k)
print("Best Silhouette Score =", round(best_score, 4))
print("================================")

# --- พล็อตกราฟประเมินโมเดล 3 แบบ ---
fig, axs = plt.subplots(1, 3, figsize=(18, 5))

# 1. Elbow Method
axs[0].plot(range(2, 9), wcss, marker='o', color='b')
axs[0].set_title("Elbow Method (Look for Knee)")
axs[0].set_xlabel("Number of Clusters (K)")
axs[0].set_ylabel("WCSS (Inertia)")
axs[0].grid(True)

# 2. Silhouette Score
axs[1].plot(range(2, 9), silhouette_scores, marker='o', color='g')
axs[1].set_title("Silhouette Score (Higher is Better)")
axs[1].set_xlabel("Number of Clusters (K)")
axs[1].set_ylabel("Score")
axs[1].grid(True)

# 3. Davies-Bouldin Index
axs[2].plot(range(2, 9), dbi_scores, marker='o', color='r')
axs[2].set_title("Davies-Bouldin Index (Lower is Better)")
axs[2].set_xlabel("Number of Clusters (K)")
axs[2].set_ylabel("Index Value")
axs[2].grid(True)

plt.tight_layout()
plt.show()

# สร้าง K-Means ตัวสุดท้ายตาม Best K
kmeans = KMeans(n_clusters=best_k, random_state=42, n_init=20)
df_cluster["cluster"] = kmeans.fit_predict(X_scaled)

# จัดเรียง Cluster ใหม่ตามปริมาณจับปลา (Amount)
cluster_amount = df_cluster.groupby("cluster")["amount"].mean().sort_values()
mapping = {old_cluster: new_cluster for new_cluster, old_cluster in enumerate(cluster_amount.index)}
df_cluster["cluster"] = df_cluster["cluster"].map(mapping)

# เพิ่มส่วนย่อยมิติข้อมูลด้วย PCA และพล็อตกราฟ Scatter Plot
pca = PCA(n_components=2)
pca_components = pca.fit_transform(X_scaled)
df_cluster['PC1'] = pca_components[:, 0]
df_cluster['PC2'] = pca_components[:, 1]

# คำนวณเปอร์เซ็นต์การอธิบายข้อมูลของ PCA
variance_ratio = pca.explained_variance_ratio_ * 100

plt.figure(figsize=(8, 6))
sns.scatterplot(
    x='PC1', y='PC2', 
    hue='cluster', 
    palette='viridis',  # ไล่เฉดสีตามระดับความเข้มข้น (0=น้อย, 1=กลาง, 2=ชุกชุม)
    data=df_cluster, 
    s=70, alpha=0.8, edgecolor='w'
)
plt.title(f"PCA Scatter Plot of Fish Catch Clusters (K={best_k})")
plt.xlabel(f"Principal Component 1 ({variance_ratio[0]:.2f}%)")
plt.ylabel(f"Principal Component 2 ({variance_ratio[1]:.2f}%)")
plt.legend(title='Cluster (Sorted by Amount)', loc='best')
plt.grid(True, linestyle='--', alpha=0.5)
plt.show()

# แสดงผลการจัดกลุ่ม
print("\n=== ผลการจัดกลุ่ม ===")
print(
    df_cluster[["station_id", "year", "month", "amount", "sst", "chlorophyll_a", "rainfall", "wind_speed", "cluster"]]
    .head(20)
    .to_string(index=False)
)

# สรุปแต่ละ Cluster
print("\n=== Cluster Summary (Mean Baseline) ===")
summary = df_cluster.groupby("cluster").agg({
    "amount": "mean",
    "sst": "mean",
    "chlorophyll_a": "mean",
    "rainfall": "mean",
    "wind_speed": "mean"
})
print(summary)

# One-Way ANOVA
print("\n========== ANOVA Test ==========")
for var in features:
    groups = [group[var].values for _, group in df_cluster.groupby("cluster")]
    F, p = f_oneway(*groups)
    print(f"{var:15s} F = {F:10.3f}  p-value = {p:.5f}")

# Update Cluster กลับไป dataset_ml
cursor = conn.cursor()
for _, row in df_cluster.iterrows():
    cursor.execute(
        """
        update dataset_ml
        set cluster = %s
        where station_id = %s
        and year = %s
        and month = %s
        """,
        (int(row["cluster"]), int(row["station_id"]), int(row["year"]), int(row["month"]))
    )

conn.commit()
print("\nบันทึก Cluster กลับลง database เรียบร้อย")
print("Finish")

cursor.close()
conn.close()
