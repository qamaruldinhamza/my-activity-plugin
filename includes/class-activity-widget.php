<?php
namespace Qamaruldinhamza\MyActivityPlugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ActivityWidget {

    /**
     * Table name for user activity.
     *
     * @var string
     */
    private static $table_name;

    /**
     * Constructor: Hook into dashboard widget setup, AJAX, and realtime tracking.
     */
    public function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'user_activity';

        // Dashboard and AJAX hooks.
        add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_fetch_user_activity_data', [ $this, 'fetch_user_activity_data' ] );

        // Realtime tracking hooks.
        add_action( 'wp_login', [ __CLASS__, 'track_login_activity' ], 10, 2 );
        add_action( 'save_post', [ __CLASS__, 'track_post_activity' ], 10, 3 );
        add_action( 'comment_post', [ __CLASS__, 'track_comment_activity' ], 10, 2 );
    }

    /**
     * Create custom table for user activity.
     * This method will be called on plugin activation.
     */
    public static function create_activity_table() {
        global $wpdb;

        self::$table_name = $wpdb->prefix . 'user_activity';
        $charset_collate   = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . self::$table_name . " (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            activity_date DATE NOT NULL,
            activity_type ENUM('login', 'post', 'comment') NOT NULL,
            activity_count INT NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Seed the table with randomized data for the last 30 days.
        self::seed_activity_data();
    }

    /**
     * Seed the custom table with randomized activity data.
     */
    private static function seed_activity_data() {
        global $wpdb;

        // Clear existing data to avoid duplicates.
        $wpdb->query( "TRUNCATE TABLE " . self::$table_name );

        $users          = get_users();
        $activity_types = [ 'login', 'post', 'comment' ];
        $start_date     = date( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date       = date( 'Y-m-d' );

        foreach ( $users as $user ) {
            $current_date = $start_date;
            while ( $current_date <= $end_date ) {
                foreach ( $activity_types as $activity ) {
                    // Randomized activity count between 0 and 5.
                    $activity_count = rand( 0, 5 );
                    $wpdb->insert(
                        self::$table_name,
                        [
                            'user_id'       => $user->ID,
                            'activity_date' => $current_date,
                            'activity_type' => $activity,
                            'activity_count'=> $activity_count
                        ],
                        [
                            '%d',
                            '%s',
                            '%s',
                            '%d'
                        ]
                    );
                }
                $current_date = date( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
            }
        }
    }

    /**
     * Register the dashboard widget.
     */
    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'user_activity_overview',
            __( 'User Activity Overview', 'my-activity-plugin' ),
            [ $this, 'render_dashboard_widget' ]
        );
    }

    /**
     * Render the dashboard widget content.
     */
    public function render_dashboard_widget() {
        ?>
        <div id="activity-widget-container">
            <!-- Date Range Selector -->
            <div id="activity-date-range">
                <label for="activity-start-date"><?php _e( 'Start Date:', 'my-activity-plugin' ); ?></label>
                <input type="date" id="activity-start-date" name="activity_start_date">
                <label for="activity-end-date"><?php _e( 'End Date:', 'my-activity-plugin' ); ?></label>
                <input type="date" id="activity-end-date" name="activity_end_date">
                <button id="filter-activity-data" class="button-primary"><?php _e( 'Filter', 'my-activity-plugin' ); ?></button>
            </div>
            <!-- Chart Containers -->
            <div style="position: relative; height:300px;">
                <canvas id="loginLineChart"></canvas>
            </div>
            <div style="position: relative; height:300px; margin-top:20px;">
                <canvas id="postCommentBarChart"></canvas>
            </div>
            <!-- Optional CSV Export Button -->
            <button id="export-csv" class="button-secondary" style="margin-top:20px;"><?php _e( 'Export CSV', 'my-activity-plugin' ); ?></button>
        </div>
        <?php
    }

    /**
     * Enqueue necessary scripts and styles on the dashboard.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_scripts( $hook ) {
        // Load only on the dashboard page.
        if ( 'index.php' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'my-activity-style', MY_ACTIVITY_PLUGIN_URL . 'assets/css/style.css', [], '1.0.0' );
        // Enqueue Chart.js from CDN.
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true );
        wp_enqueue_script( 'my-activity-charts', MY_ACTIVITY_PLUGIN_URL . 'assets/js/charts.js', [ 'jquery', 'chart-js' ], '1.0.0', true );
        wp_localize_script( 'my-activity-charts', 'myActivityAjax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'my_activity_nonce' )
        ] );
    }

    /**
     * AJAX callback to fetch user activity data based on a given date range.
     */
    public function fetch_user_activity_data() {
        // Security check.
        check_ajax_referer( 'my_activity_nonce', 'nonce' );

        global $wpdb;

        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';

        if ( ! $start_date || ! $end_date ) {
            wp_send_json_error( 'Invalid date range.' );
        }

        $table = self::$table_name;

        // Fetch login data for the line chart within the selected range.
        $login_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT activity_date, SUM(activity_count) as total
                FROM $table
                WHERE activity_type = 'login'
                AND activity_date BETWEEN %s AND %s
                GROUP BY activity_date
                ORDER BY activity_date ASC",
                $start_date,
                $end_date
            )
        );

        // For the bar chart, compare post and comment counts for the current month.
        $current_month_start = date( 'Y-m-01' );
        $current_month_end   = date( 'Y-m-t' );
        $bar_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, activity_type, SUM(activity_count) as total
                FROM $table
                WHERE activity_type IN ('post', 'comment')
                AND activity_date BETWEEN %s AND %s
                GROUP BY user_id, activity_type",
                $current_month_start,
                $current_month_end
            )
        );

        wp_send_json_success( [
            'login_data' => $login_results,
            'bar_data'   => $bar_results
        ] );
    }

    /**
     * Track realtime activity for a given user and activity type.
     *
     * @param int    $user_id       The ID of the user.
     * @param string $activity_type The type of activity ('login', 'post', 'comment').
     */
    public static function track_activity( $user_id, $activity_type ) {
        global $wpdb;
        // Use the current time based on WordPress settings.
        $today = current_time( 'Y-m-d' );
        $table = self::$table_name;

        // Check if an entry already exists for this user, date, and activity type.
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, activity_count FROM $table WHERE user_id = %d AND activity_date = %s AND activity_type = %s",
            $user_id,
            $today,
            $activity_type
        ) );

        if ( $record ) {
            // Update existing record.
            $wpdb->update(
                $table,
                [ 'activity_count' => $record->activity_count + 1 ],
                [ 'id' => $record->id ],
                [ '%d' ],
                [ '%d' ]
            );
        } else {
            // Insert a new record.
            $wpdb->insert(
                $table,
                [
                    'user_id'       => $user_id,
                    'activity_date' => $today,
                    'activity_type' => $activity_type,
                    'activity_count'=> 1
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%d'
                ]
            );
        }
    }

    /**
     * Hook callback for tracking login activity.
     *
     * @param string $user_login Username.
     * @param WP_User $user      WP_User object.
     */
    public static function track_login_activity( $user_login, $user ) {
        self::track_activity( $user->ID, 'login' );
    }

    /**
     * Hook callback for tracking post creation.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public static function track_post_activity( $post_id, $post, $update ) {
        // Avoid tracking revisions or updates.
        if ( wp_is_post_revision( $post_id ) || $update ) {
            return;
        }
        if ( 'post' !== $post->post_type ) {
            return;
        }
        self::track_activity( $post->post_author, 'post' );
    }

    /**
     * Hook callback for tracking comment submissions.
     *
     * @param int $comment_id         Comment ID.
     * @param int|string $comment_approved Approval status.
     */
    public static function track_comment_activity( $comment_id, $comment_approved ) {
        // Only track if comment is approved.
        if ( $comment_approved != 1 ) {
            return;
        }
        $comment = get_comment( $comment_id );
        // Only track if the comment is associated with a registered user.
        if ( ! $comment || ! $comment->user_id ) {
            return;
        }
        self::track_activity( $comment->user_id, 'comment' );
    }
}