<?php
session_start();
?>

<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Model Performance</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link rel="stylesheet" href="../css/menu.css">
<link rel="stylesheet" href="../css/data.css">

</head>

<body>

<?php include 'menu.php'; ?>

<div class="container page-container">

    <div class="page-header mb-4">

        <h2>
            Model Performance
        </h2>

        <p class="text-muted">

            AI Model Evaluation

        </p>

    </div>

    <!-- Regression -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-primary text-white">

            Linear Regression

        </div>

        <div class="card-body">

            <table class="table">

                <tr>
                    <th>R² Score</th>
                    <td>0.89</td>
                </tr>

                <tr>
                    <th>MAE</th>
                    <td>0.21</td>
                </tr>

                <tr>
                    <th>RMSE</th>
                    <td>0.35</td>
                </tr>

            </table>

        </div>

    </div>

    <!-- Classification -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-success text-white">

            Random Forest

        </div>

        <div class="card-body">

            <table class="table">

                <tr>
                    <th>Accuracy</th>
                    <td>92%</td>
                </tr>

                <tr>
                    <th>Precision</th>
                    <td>90%</td>
                </tr>

                <tr>
                    <th>Recall</th>
                    <td>89%</td>
                </tr>

                <tr>
                    <th>F1 Score</th>
                    <td>91%</td>
                </tr>

            </table>

        </div>

    </div>

    <!-- Clustering -->

    <div class="card shadow-sm border-0">

        <div class="card-header bg-info text-white">

            K-Means

        </div>

        <div class="card-body">

            <table class="table">

                <tr>
                    <th>Clusters</th>
                    <td>3</td>
                </tr>

                <tr>
                    <th>Silhouette Score</th>
                    <td>0.71</td>
                </tr>

            </table>

        </div>

    </div>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>