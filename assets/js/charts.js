(function($) {
    var loginChart, barChart;

    /**
     * Initialize the Chart.js charts with basic configuration.
     */
    function initCharts() {
        var ctxLogin = document.getElementById('loginLineChart').getContext('2d');
        var ctxBar = document.getElementById('postCommentBarChart').getContext('2d');

        loginChart = new Chart(ctxLogin, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Logins',
                    data: [],
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        mode: 'index'
                    }
                }
            }
        });

        barChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Posts',
                        data: [],
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Comments',
                        data: [],
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index'
                    }
                }
            }
        });
    }

    /**
     * Fetch chart data via AJAX.
     */
    function fetchActivityData() {
        var startDate = $('#activity-start-date').val();
        var endDate = $('#activity-end-date').val();

        // Set default date range to last 30 days if none provided.
        if (!startDate || !endDate) {
            var today = new Date();
            endDate = today.toISOString().split('T')[0];
            var past = new Date();
            past.setDate(today.getDate() - 30);
            startDate = past.toISOString().split('T')[0];
            $('#activity-start-date').val(startDate);
            $('#activity-end-date').val(endDate);
        }

        // Optional: Show a loading indicator here.

        $.ajax({
            url: myActivityAjax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'fetch_user_activity_data',
                nonce: myActivityAjax.nonce,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                if (response.success) {
                    updateLoginChart(response.data.login_data);
                    updateBarChart(response.data.bar_data);
                } else {
                    alert('Error fetching data: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error(error);
                alert('AJAX error occurred.');
            }
        });
    }

    /**
     * Update the login line chart with new data.
     * @param {Array} data - Array of objects with activity_date and total.
     */
    function updateLoginChart(data) {
        var labels = [];
        var totals = [];

        data.forEach(function(item) {
            labels.push(item.activity_date);
            totals.push(item.total);
        });

        loginChart.data.labels = labels;
        loginChart.data.datasets[0].data = totals;
        loginChart.update();
    }

    /**
     * Update the post/comment bar chart.
     * @param {Array} data - Array of objects with user_id, activity_type, and total.
     */
    function updateBarChart(data) {
        var userIds = [];
        var postsData = {};
        var commentsData = {};

        // Group data by user_id.
        data.forEach(function(item) {
            if (userIds.indexOf(item.user_id) === -1) {
                userIds.push(item.user_id);
            }
            if (item.activity_type === 'post') {
                postsData[item.user_id] = item.total;
            } else if (item.activity_type === 'comment') {
                commentsData[item.user_id] = item.total;
            }
        });

        // Create arrays for posts and comments, defaulting missing values to 0.
        var postsArr = [];
        var commentsArr = [];
        userIds.forEach(function(userId) {
            postsArr.push(postsData[userId] || 0);
            commentsArr.push(commentsData[userId] || 0);
        });

        barChart.data.labels = userIds;
        barChart.data.datasets[0].data = postsArr;
        barChart.data.datasets[1].data = commentsArr;
        barChart.update();
    }

    /**
     * Optional: Export login chart data as CSV.
     */
    function exportCSV() {
        var csvContent = "data:text/csv;charset=utf-8,Date,Logins\r\n";
        loginChart.data.labels.forEach(function(label, index) {
            csvContent += label + "," + loginChart.data.datasets[0].data[index] + "\r\n";
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "login_data.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Document ready
    $(document).ready(function() {
        initCharts();
        fetchActivityData();

        // Bind filter button click to fetch new data.
        $('#filter-activity-data').on('click', function(e) {
            e.preventDefault();
            fetchActivityData();
        });

        // Optional CSV export button (if added to your widget HTML).
        $('#export-csv').on('click', function(e) {
            e.preventDefault();
            exportCSV();
        });
    });
})(jQuery);