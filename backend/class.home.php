<?php

class home
{
    function def()
    {
        $conn = new connect();

        ?>

        <div class='container'>

            <div class='row'>

                <!-- Chlorophyll -->
                <div class='col-md-3'>

                    <div class='card p-3 shadow'>

                        <h4>Chlorophyll-a</h4>

                        <?php
                        $sql = "
                            SELECT chlorophyll_a
                            FROM marine_environment
                            ORDER BY id DESC
                            LIMIT 1
                        ";

                        $query = $conn->query($sql);
                        $row = $conn->fetch($query);

                        ?>

                        <h2>
                            <?php echo $row['chlorophyll']; ?>
                        </h2>

                    </div>

                </div>

            </div>

            <br>

            <!-- Graph -->
            <div class='row'>

                <div class='col-md-12'>

                    <div class='card p-3 shadow'>

                        <h4>Fish Catch Graph</h4>

                        <canvas id='fishChart'></canvas>

                    </div>

                </div>

            </div>

        </div>

        <?php

        $sql = "
            SELECT 
                month,
                SUM(amount) as total
            FROM catch_mackereldata
            GROUP BY month
            ORDER BY month ASC
        ";

        $query = $conn->query($sql);

        $month = [];
        $amount = [];

        while($row = $conn->fetch($query))
        {
            $month[] = $row['month'];
            $amount[] = $row['total'];
        }

        ?>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <script>

        const ctx = document.getElementById('fishChart');

        new Chart(ctx, {

            type: 'line',

            data: {

                labels: <?php echo json_encode($month); ?>,

                datasets: [{

                    label: 'Fish Amount',

                    data: <?php echo json_encode($amount); ?>,

                    borderWidth: 3,
                    tension: 0.3

                }]
            }

        });

        </script>

        <?php
    }
}
?>