<?php
/*
Plugin Name: Page and Post Views
Plugin URI: http://www.allendav.com/
Description: A WordPress plugin that counts page and post views without sacrificing your users' and visitors' privacy.
Version: 1.0.0
Author: allendav
Author URI: http://www.allendav.com
License: GPL2
*/
class Allendav_Page_And_Post_Views {
	private static $instance;
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __clone() {
	}

	private function __wakeup() {
	}

	protected function __construct() {
		add_action( 'wp_footer', array( $this, 'maybe_record_view' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
	}

	function get_post_id() {
		if ( ! is_page() && ! is_single() ) {
			return false;
		}

		$queried_object = get_queried_object();
		if ( ! $queried_object ) {
			return false;
		}

		if ( property_exists( $queried_object, 'ID' ) ) {
			return $queried_object->ID;
		}

		return false;
	}

	function maybe_record_view() {
		global $wpdb;
		$table_name = $wpdb->prefix . "page_and_post_views";

		$post_ID = $this->get_post_id();
		if ( ! $post_ID ) {
			return;
		}

		$event_date = date( 'Y-m-d', current_time( 'timestamp' ) );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `$table_name`
				( `view_date`, `post_id`, `view_count` )
				VALUES
				( %s, %d, %d )
				ON DUPLICATE KEY UPDATE
				`view_count` = `view_count` + 1;",
				$event_date,
				$post_ID,
				1
			)
		);
	}

	function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'allendav_page_and_post_views',
			__( 'Most Viewed Pages and Posts', 'allendav_page_and_post_views' ),
			array( $this, 'dashboard_widget' )
		);
	}

	function list_top_pages_and_posts( $max_age_in_days = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "page_and_post_views";

		$where = '';
		if ( $max_age_in_days ) {
			$since = date( 'Y-m-d', current_time( 'timestamp' ) - DAY_IN_SECONDS * $max_age_in_days );
			$where = "WHERE `view_date` >= '$since'";
		}

		$results = $wpdb->get_results(
			"SELECT `post_id`, SUM( `view_count` ) AS `total_views` FROM `$table_name` $where GROUP BY `post_id` ORDER BY `total_views` DESC LIMIT 5",
			ARRAY_A
		);

		?>
			<table width="100%">
				<tbody>
					<tr>
						<th style="text-align:left"><?php esc_html_e( 'Page/Post', 'allendav_page_and_post_views' ); ?></th>
						<th><?php esc_html_e( 'Views', 'allendav_page_and_post_views' ); ?></th>
					</tr>
					<?php
					foreach( (array) $results as $result ) {
						?>
							<tr>
								<td>
									<a href="<?php echo esc_attr( get_permalink( $result['post_id'] ) ) ?>">
										<?php echo get_the_title( $result['post_id'] ); ?>
									</a>
								</td>
								<td style="text-align:center">
									<?php echo esc_html( $result['total_views'] ); ?>
								</td>
							</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		<?php
	}

	function dashboard_widget() {
		?>
		<div class="activity-block">
			<h3><?php esc_html_e( 'Last 7 Days' ); ?></h3>
			<?php $this->list_top_pages_and_posts( 7 ); ?>
		</div>
		<div class="activity-block">
			<h3><?php esc_html_e( 'All Time' ); ?></h3>
			<?php $this->list_top_pages_and_posts(); ?>
		</div>
		<?php
	}
}

function allendav_page_and_post_views_on_activation() {
	global $wpdb;
	$table_name = $wpdb->prefix . "page_and_post_views";

	// Table already exists? Bail.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
		return;
	}

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		ID BIGINT(20) NOT NULL AUTO_INCREMENT,
		view_date DATE NOT NULL,
		post_id BIGINT(20) NOT NULL,
		view_count MEDIUMINT(9) NOT NULL,
		PRIMARY KEY (ID),
		CONSTRAINT post_and_date UNIQUE ( view_date, post_id )
		) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'allendav_page_and_post_views_on_activation' );

function allendav_page_and_post_views_on_uninstall() {
	global $wpdb;
	$table_name = $wpdb->prefix . "page_and_post_views";
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}
register_uninstall_hook( __FILE__, 'allendav_page_and_post_views_on_uninstall' );

Allendav_Page_And_Post_Views::getInstance();
