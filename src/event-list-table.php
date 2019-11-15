<?php
/**
 * List table for cron events.
 *
 * @package WP Crontrol
 */

namespace Crontrol;

use stdClass;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Cron event list table class.
 */
class Event_List_Table extends \WP_List_Table {

	/**
	 * Array of cron event hooks that are persistently added by WordPress core.
	 *
	 * @var string[] Array of hook names.
	 */
	protected static $persistent_core_hooks;

	/**
	 * Whether the current user has the capability to edit files.
	 *
	 * @var bool Whether the user can edit files.
	 */
	protected static $can_edit_files;

	/**
	 * Array of the count of each hook.
	 *
	 * @var int[] Array of count of each hooked, keyed by hook name.
	 */
	protected static $count_by_hook;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'crontrol-event',
			'plural'   => 'crontrol-events',
			'ajax'     => false,
			'screen'   => 'crontrol-events',
		) );
	}

	/**
	 * Prepares the list table items and arguments.
	 */
	public function prepare_items() {
		self::$persistent_core_hooks = get_persistent_core_hooks();
		self::$can_edit_files        = current_user_can( 'edit_files' );
		self::$count_by_hook         = Event\count_by_hook();

		$events = Event\get();

		if ( ! empty( $_GET['s'] ) ) {
			$s = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			$events = array_filter( $events, function( $event ) use ( $s ) {
				return ( false !== strpos( $event->hook, $s ) );
			} );
		}

		$count    = count( $events );
		$per_page = 50;
		$offset   = ( $this->get_pagenum() - 1 ) * $per_page;

		$this->items = array_slice( $events, $offset, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $count,
			'per_page'    => $per_page,
			'total_pages' => ceil( $count / $per_page ),
		) );
	}

	/**
	 * Returns an array of column names for the table.
	 *
	 * @return string[] Array of column names keyed by their ID.
	 */
	public function get_columns() {
		return array(
			'cb'                  => '<input type="checkbox" />',
			'crontrol_hook'       => __( 'Hook', 'wp-crontrol' ),
			'crontrol_next'       => sprintf(
				/* translators: %s: UTC offset */
				__( 'Next Run (%s)', 'wp-crontrol' ),
				get_utc_offset()
			),
			'crontrol_args'       => __( 'Arguments', 'wp-crontrol' ),
			'crontrol_actions'    => __( 'Actions', 'wp-crontrol' ),
			'crontrol_recurrence' => __( 'Recurrence', 'wp-crontrol' ),
		);
	}

	/**
	 * Returns an array of CSS class names for the table.
	 *
	 * @return string[] Array of class names.
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'striped', $this->_args['plural'] );
	}

	/**
	 * Get an associative array ( option_name => option_title ) with the list
	 * of bulk actions available on this table.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete_crons' => esc_html__( 'Delete', 'wp-crontrol' ),
		);
	}

	/**
	 * Generates and displays row action links for the table.
	 *
	 * @param stdClass $event       The cron event for the current row.
	 * @param string   $column_name Current column name.
	 * @param string   $primary     Primary column name.
	 * @return string The row actions HTML.
	 */
	protected function handle_row_actions( $event, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$links = array();

		if ( ( 'crontrol_cron_job' !== $event->hook ) || self::$can_edit_files ) {
			$link = array(
				'page'         => 'crontrol_admin_manage_page',
				'action'       => 'edit-cron',
				'id'           => rawurlencode( $event->hook ),
				'sig'          => rawurlencode( $event->sig ),
				'next_run_utc' => rawurlencode( $event->time ),
			);
			$link = add_query_arg( $link, admin_url( 'tools.php' ) );

			$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'Edit', 'wp-crontrol' ) . '</a>';
		}

		$link = array(
			'page'         => 'crontrol_admin_manage_page',
			'action'       => 'run-cron',
			'id'           => rawurlencode( $event->hook ),
			'sig'          => rawurlencode( $event->sig ),
			'next_run_utc' => rawurlencode( $event->time ),
		);
		$link = add_query_arg( $link, admin_url( 'tools.php' ) );
		$link = wp_nonce_url( $link, "run-cron_{$event->hook}_{$event->sig}" );

		$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'Run Now', 'wp-crontrol' ) . '</a>';

		$link = add_query_arg( array(
			'post_type'    => Log::$post_type,
			Log::$taxonomy => rawurlencode( sanitize_title( $event->hook ) ),
		), admin_url( 'edit.php' ) );

		$links[] = "<a href='" . esc_url( $link ) . "'>" . esc_html__( 'View Logs', 'wp-crontrol' ) . '</a>';

		if ( ! in_array( $event->hook, self::$persistent_core_hooks, true ) && ( ( 'crontrol_cron_job' !== $event->hook ) || self::$can_edit_files ) ) {
			$link = array(
				'page'         => 'crontrol_admin_manage_page',
				'action'       => 'delete-cron',
				'id'           => rawurlencode( $event->hook ),
				'sig'          => rawurlencode( $event->sig ),
				'next_run_utc' => rawurlencode( $event->time ),
			);
			$link = add_query_arg( $link, admin_url( 'tools.php' ) );
			$link = wp_nonce_url( $link, "delete-cron_{$event->hook}_{$event->sig}_{$event->time}" );

			$links[] = "<span class='delete'><a href='" . esc_url( $link ) . "'>" . esc_html__( 'Delete', 'wp-crontrol' ) . '</a></span>';
		}

		if ( function_exists( 'wp_unschedule_hook' ) && ! in_array( $event->hook, self::$persistent_core_hooks, true ) && ( 'crontrol_cron_job' !== $event->hook ) ) {
			if ( self::$count_by_hook[ $event->hook ] > 1 ) {
				$link = array(
					'page'   => 'crontrol_admin_manage_page',
					'action' => 'delete-hook',
					'id'     => rawurlencode( $event->hook ),
				);
				$link = add_query_arg( $link, admin_url( 'tools.php' ) );
				$link = wp_nonce_url( $link, "delete-hook_{$event->hook}" );

				$text = sprintf(
					/* translators: %s: Number of events with a given name */
					_n( 'Delete All %s', 'Delete All %s', self::$count_by_hook[ $event->hook ], 'wp-crontrol' ),
					number_format_i18n( self::$count_by_hook[ $event->hook ] )
				);
				$links[] = "<span class='delete'><a href='" . esc_url( $link ) . "'>" . esc_html( $text ) . '</a></span>';
			}
		}

		return $this->row_actions( $links );
	}

	/**
	 * Outputs the checkbox cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 */
	protected function column_cb( $event ) {
		if ( ! in_array( $event->hook, self::$persistent_core_hooks, true ) && ( ( 'crontrol_cron_job' !== $event->hook ) || self::$can_edit_files ) ) {
			?>
			<label class="screen-reader-text" for="">
				<?php printf( esc_html__( 'Select this row', 'wp-crontrol' ) ); ?>
			</label>
			<?php
				printf(
					'<input type="checkbox" name="delete[%1$s][%2$s]" value="%3$s">',
					esc_attr( $event->time ),
					esc_attr( rawurlencode( $event->hook ) ),
					esc_attr( $event->sig )
				);
			?>
			<?php
		}
	}

	/**
	 * Returns the output for the hook name cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_crontrol_hook( $event ) {
		if ( 'crontrol_cron_job' === $event->hook ) {
			if ( ! empty( $event->args['name'] ) ) {
				/* translators: 1: The name of the PHP cron event. */
				return '<em>' . esc_html( sprintf( __( 'PHP Cron (%s)', 'wp-crontrol' ), $event->args['name'] ) ) . '</em>';
			} else {
				return '<em>' . esc_html__( 'PHP Cron', 'wp-crontrol' ) . '</em>';
			}
		}

		$return = esc_html( $event->hook );

		if ( in_array( $event->hook, get_all_core_hooks(), true ) ) {
			$return .= sprintf(
				'<br><em>(%s)</em>',
				esc_html__( 'WordPress core hook', 'wp-crontrol' )
			);
		}

		return $return;
	}

	/**
	 * Returns the output for the arguments cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_crontrol_args( $event ) {
		if ( ! empty( $event->args ) ) {
			$args = json_output( $event->args );
		}

		if ( 'crontrol_cron_job' === $event->hook ) {
			$return = '<em>' . esc_html__( 'PHP Code', 'wp-crontrol' ) . '</em>';

			if ( ! empty( $event->args['syntax_error_message'] ) ) {
				$return .= '<br><span style="color:#c00"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
				$return .= sprintf(
					/* translators: 1: Line number, 2: Error message text */
					esc_html__( 'Line %1$s: %2$s', 'wp-crontrol' ),
					esc_html( number_format_i18n( $event->args['syntax_error_line'] ) ),
					esc_html( $event->args['syntax_error_message'] )
				);
				$return .= '</span>';
			}

			if ( ! empty( $event->args['code'] ) ) {
				$lines = explode( "\n", trim( $event->args['code'] ) );
				$code  = reset( $lines );
				$code  = substr( $code, 0, 50 );

				$return .= '<br>';
				$return .= sprintf(
					'<code>%s</code>&hellip;',
					esc_html( $code )
				);
			}

			return $return;
		} else {
			if ( empty( $event->args ) ) {
				return sprintf(
					'<em>%s</em>',
					esc_html__( 'None', 'wp-crontrol' )
				);
			} else {
				return sprintf(
					'<pre>%s</pre>',
					esc_html( $args )
				);
			}
		}
	}

	/**
	 * Returns the output for the actions cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_crontrol_actions( $event ) {
		if ( 'crontrol_cron_job' === $event->hook ) {
			return '<em>' . esc_html__( 'WP Crontrol', 'wp-crontrol' ) . '</em>';
		} else {
			$callbacks = array();

			foreach ( get_hook_callbacks( $event->hook ) as $callback ) {
				$callbacks[] = output_callback( $callback );
			}

			return implode( '<br>', $callbacks ); // WPCS:: XSS ok.
		}
	}

	/**
	 * Returns the output for the next run cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_crontrol_next( $event ) {
		$date_utc   = gmdate( 'Y-m-d\TH:i:s+00:00', $event->time );
		$date_local = get_date_from_gmt( date( 'Y-m-d H:i:s', $event->time ), 'Y-m-d H:i:s' );

		$time = sprintf(
			'<time datetime="%1$s">%2$s</time>',
			esc_attr( $date_utc ),
			esc_html( $date_local )
		);

		return sprintf(
			'%s<br>%s',
			$time,
			esc_html( time_since( time(), $event->time ) )
		);
	}

	/**
	 * Returns the output for the recurrence cell of a table row.
	 *
	 * @param stdClass $event The cron event for the current row.
	 * @return string The cell output.
	 */
	protected function column_crontrol_recurrence( $event ) {
		if ( $event->schedule ) {
			$schedule_name = Event\get_schedule_name( $event );
			if ( is_wp_error( $schedule_name ) ) {
				return sprintf(
					'<span class="dashicons dashicons-warning" style="color:#c00" aria-hidden="true"></span> %s',
					esc_html( $schedule_name->get_error_message() )
				);
			} else {
				return esc_html( $schedule_name );
			}
		} else {
			return esc_html__( 'Non-repeating', 'wp-crontrol' );
		}
	}

	/**
	 * Outputs a message when there are no items to show in the table.
	 */
	public function no_items() {
		esc_html_e( 'There are currently no scheduled cron events.', 'wp-crontrol' );
	}

}
