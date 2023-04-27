<?php // phpcs:ignore Standard.Category.SniffName.ErrorCode
/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataAccess\Backup
 */

namespace WPDataAccess\Backup {

	use Dropbox\Dropbox;
	use Dropbox\Dropbox\Auth;
	use WPDataAccess\Dashboard\WPDA_Dashboard;
	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Lists;
	use WPDataAccess\Settings\WPDA_Settings_DataBackup;
	use WPDataAccess\Utilities\WPDA_Export_Sql;
	use WPDataAccess\Utilities\WPDA_Remote_Call;
	use WPDataAccess\WPDA;
	use WPDataAccess\Utilities\WPDA_Message_Box;
	use WPDataAccess\Utilities\WPDA_Repository;

	/**
	 * Class WPDA_Data_Export
	 *
	 * This class offers support to manage unattended data exports. Data exports can be run once only or scheduled.
	 * Every data backup has a unique name to identify it. The data backup name is used in combination with the date
	 * and time to create a unique file name.
	 *
	 * @author  Peter Schulz
	 * @since   1.0.0
	 */
	class WPDA_Data_Export {

		const PREFIX_RUNONCE        = 'wpda-run-once-';
		const SHOW_JOBS_OPTION_NAME = 'wpda_data_backup_show_jobs';

		/**
		 * Holds the scheduled cron jobs
		 *
		 * @var array
		 */
		protected $schedules;

		/**
		 * WPDA_Data_Export constructor
		 *
		 * Gets and stores the scheduled cron jobs.
		 */
		public function __construct() {
			$this->schedules = wp_get_schedules();
		}

		/**
		 * Data entry form to add or update a data backup
		 *
		 * @param string $action Add for new export; Update to edit existing export.
		 */
		public function create_export( $action ) {
			$wpda_db_options_activated = get_option( 'wpda_db_options_activated' );
			if ( ! is_array( $wpda_db_options_activated ) || 0 === count( $wpda_db_options_activated ) ) {//phpcs:ignore - 8.1 proof
				echo '<br/>';
				echo __( 'You need to define and activate at least one storage device in Data Backup Settings to use this feature.', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<br/>';
				echo '<a href="?page=wpdataaccess&tab=databackup">&raquo; ';
				echo __( 'Define and/or activate a storage device', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</a>';
				wp_die();
			}

			if ( isset( $_REQUEST['wpdaschema_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$schema_name = '';
			}

			$device_arg = '';
			if ( 'update' === $action ) {
				if ( isset( $_REQUEST['schedule'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$schedule = sanitize_text_field( wp_unslash( $_REQUEST['schedule'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
					if ( 'wpda_data_backup' !== $schedule ) {
						wp_die( __( 'ERROR: Wrong arguments', 'wp-data-access' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
				} else {
					wp_die( __( 'ERROR: Wrong arguments', 'wp-data-access' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				if ( isset( $_REQUEST['schedule_args'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$backupid = sanitize_text_field( wp_unslash( $_REQUEST['schedule_args'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				} else {
					wp_die( __( 'ERROR: Wrong arguments', 'wp-data-access' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				$data_backups      = get_option( 'wpda_data_backup_option' );
				$data_backup_found = false;
				foreach ( $data_backups as $data_backup ) {
					if ( $data_backup['id'] === $backupid ) {
						$data_backup_tables = $data_backup['tables'];
						$keep               = $data_backup['keep'];
						$schema_name        = isset( $data_backup['schema_name'] ) ? $data_backup['schema_name'] : '';
						$data_backup_found  = true;
					}
				}
				if ( ! $data_backup_found ) {
					wp_die( __( 'ERROR: Wrong arguments', 'wp-data-access' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				if ( isset( $_REQUEST['interval'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$interval = sanitize_text_field( wp_unslash( $_REQUEST['interval'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
				if ( isset( $_REQUEST['device'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$device_arg = sanitize_text_field( wp_unslash( $_REQUEST['device'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
			} else {
				$backupid = '';
			}

			$table_list = WPDA_Dictionary_Lists::get_tables( false, $schema_name );
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline">
					<?php echo __( 'Data Backup' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</h1>
				<div id="wpda_export_import">
					<div class="wpda_export_import">
						<form id="wpda_export_import_form"
								action="?page=wpda&page_action=wpda_backup"
								method="post"
								onsubmit="return pre_submit()">
							<table>
								<tr>
									<td style="font-weight:bold;padding-left:10px;"><?php echo __( 'Database Tables' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
									<td></td>
									<td style="font-weight:bold;padding-left:10px"><?php echo __( 'Tables To Be Exported' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
								</tr>
								<tr>
									<td>
										<select id="wpda_table_name_db" name="wpda_table_name_db" multiple size="20"
												style="width:300px;">
											<?php
											foreach ( $table_list as $key => $value ) {
												echo '<option value="' . esc_attr( $value['table_name'] ) . '">' . esc_attr( $value['table_name'] ) . '</option>';
											}
											?>
										</select>
									</td>
									<td>
										<a href="javascript:void(0)" class="button" onclick="move_all_to_export()">
											>>> </a>
										<br/>
										<a href="javascript:void(0)" class="button" onclick="move_all_to_db()"> <<< </a>
									</td>
									<td>
										<select id="wpda_table_name_export" name="wpda_table_name_export[]" multiple
												size="20" style="width:300px;">
										</select>
									</td>
								</tr>
								<tr>
									<td colspan="3" style="text-align:center;">
										<table align="center">
											<tr>
												<td style="text-align:right;"><?php echo __( 'Backup Id' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
												<td style="text-align:left;">
													<input
															type="text"
															id="backupid"
															name="backupid"
															value="<?php echo esc_attr( $backupid ); ?>"
															maxlength="50"
														<?php
														if ( 'update' === $action ) {
															echo 'readonly';
														}
														?>
													/>
												</td>
											</tr>
											<tr>
												<td style="text-align:right;"><?php echo __( 'Backup Interval' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
												<td style="text-align:left;">
													<select id="interval" name="interval">
														<?php if ( 'add' === $action ) { ?>
															<option value="runonce"><?php echo __( 'Run once (no interval)' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></option>
														<?php } ?>
														<?php
														foreach ( $this->schedules as $key => $schedule ) {
															echo '<option value="' . esc_attr( $key ) . '">' .
																	esc_attr( $schedule['display'] ) . ' (' . esc_attr( $schedule['interval'] ) . ' sec)' .
																'</option>';
														}
														?>
													</select>
												</td>
											</tr>
											<tr>
												<td style="text-align:right;"><?php echo __( 'Backup Location' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
												<td style="text-align:left;">
													<select id="device" name="device">
														<?php
														foreach ( $wpda_db_options_activated as $key => $value ) {
															$device = $key;
															if ( 'local_path' === $key ) {
																$device = 'local > ' . WPDA::get_option( WPDA::OPTION_DB_LOCAL_PATH );
															} elseif ( 'dropbox' === $key ) {
																$dropbox_path = WPDA::get_option( WPDA::OPTION_DB_DROPBOX_PATH );
																if ( '' === $dropbox_path ) {
																	$dropbox_path = '/wp-data-access-backups/'; // Use last version.
																}
																$device = 'dropbox > ' . $dropbox_path;
															}
															echo '<option value="' . esc_attr( $key ) . '"' . ( $key === $device_arg ? 'selected' : '' ) . '>' .
																	esc_attr( $device ) .
																'</option>';
														}
														?>
													</select>
												</td>
											</tr>
											<tr>
												<td style="text-align:right;"><?php echo __( 'Backup Files Kept' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
												<td style="text-align:left;">
													<select id="keep" name="keep">
														<option value="1">1</option>
														<option value="2">2</option>
														<option value="3">3</option>
														<option value="4">4</option>
														<option value="5">5</option>
														<option value="6">6</option>
														<option value="7">7</option>
														<option value="8">8</option>
														<option value="9">9</option>
														<option value="10">10</option>
														<option value="ALL">ALL</option>
													</select>
												</td>
											</tr>
											<tr>
												<td></td>
												<td style="text-align:left;">
													<button type="submit" class="button button-primary">
														<i class="fas fa-check wpda_icon_on_button"></i>
														<?php echo __( 'Start' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
													</button>
													<button type="button" class="button button-secondary"
															onclick="window.location.href=window.location.href">
														<i class="fas fa-times-circle wpda_icon_on_button"></i>
														<?php echo __( 'Cancel' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
													</button>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
							<input type="hidden" name="wpdaschema_name" value="<?php echo esc_attr( $schema_name ); ?>"/>
							<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>"/>
						</form>
					</div>
				</div>
			</div>
			<script type='text/javascript'>
				var tables_selected = [];
				<?php
				if ( 'update' === $action ) {
					foreach ( $data_backup_tables as $data_backup_table ) {
						?>
						tables_selected.push('<?php echo esc_attr( $data_backup_table ); ?>');
						<?php
					}
					?>
				jQuery(function () {
					jQuery("#keep option[value='<?php echo esc_attr( $keep ); ?>']").prop('selected', true);
					jQuery("#interval option[value='<?php echo esc_attr( $interval ); ?>']").prop('selected', true);
					for (var i = 0; i < tables_selected.length; i++) {
						jQuery("#wpda_table_name_db option[value='" + tables_selected[i] + "']").remove();
						jQuery('#wpda_table_name_export').append(jQuery('<option>', {
							value: tables_selected[i],
							text: tables_selected[i]
						}));
					}
				});
					<?php
				} else {
					?>
					jQuery(function () {
						jQuery("#keep option[value='3']").prop('selected', true);
						jQuery( '.wpda_tooltip' ).tooltip();
					});
					<?php
				}
				?>
				function pre_submit() {
					if (0 === jQuery("#wpda_table_name_export > option").length) {
						alert('<?php echo __( 'No tables to be exported' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>');
						return false;
					}
					if ('' === jQuery("#backupid").val().trim()) {
						alert('<?php echo __( 'You must specify a backupid' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>');
						return false;
					}
					jQuery("#wpda_table_name_export > option").each(function () {
						jQuery(this).attr("selected", "true");
					});
					return true;
				}

				function move_all_to_export() {
					jQuery("#wpda_table_name_db > option").each(function () {
						jQuery('#wpda_table_name_export').append(jQuery('<option>', {
							value: this.text,
							text: this.text
						}));
					});
					jQuery('#wpda_table_name_db > option').remove();
				}

				function move_all_to_db() {
					jQuery("#wpda_table_name_export > option").each(function () {
						jQuery('#wpda_table_name_db').append(jQuery('<option>', {
							value: this.text,
							text: this.text
						}));
					});
					jQuery('#wpda_table_name_export > option').remove();
				}

				jQuery(function () {
					jQuery('#wpda_table_name_db').on('click', function (event) {
						if ('' !== event.target.text && undefined != event.target.text) {
							jQuery('#wpda_table_name_export').append(jQuery('<option>', {
								value: event.target.text,
								text: event.target.text
							}));
							jQuery("#wpda_table_name_db option[value='" + event.target.text + "']").remove();
						}
					});
					jQuery('#wpda_table_name_export').on('click', function (event) {
						if ('' !== event.target.text && undefined != event.target.text) {
							jQuery('#wpda_table_name_db').append(jQuery('<option>', {
								value: event.target.text,
								text: event.target.text
							}));
							jQuery("#wpda_table_name_export option[value='" + event.target.text + "']").remove();
						}
					});
				});
			</script>
			<?php
		}

		/**
		 * Show available cron jobs
		 *
		 * Shows data backups only be default. Other cron jobs can be displayed as well.
		 */
		public function show_wp_cron() {
			$wpda_repository = new WPDA_Repository();
			$wpda_repository->inform_user();

			$no_data_backup_events = 0;
			$data_backups          = get_option( 'wpda_data_backup_option' );
			$data_backups_keep     = array();
			$data_backups_device   = array();
			if ( null !== $data_backups && false !== $data_backups ) {
				foreach ( $data_backups as $data_backup ) {
					$data_backups_keep[ $data_backup['id'] ]   = $data_backup['keep'];
					$data_backups_device[ $data_backup['id'] ] = $data_backup['device'];
				}
			}

			$crons              = _get_cron_array();
			$data_backups_found = false;
			foreach ( $crons as $key => $cron ) {
				foreach ( $cron as $key => $value ) {
					if ( 'wpda_data_backup' === $key ) {
						$data_backups_found = true;
						continue;
					}
				}
				if ( $data_backups_found ) {
					continue;
				}
			}

			if ( isset( $_REQUEST['show_jobs'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$show_jobs = sanitize_text_field( wp_unslash( $_REQUEST['show_jobs'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				update_option( self::SHOW_JOBS_OPTION_NAME, $show_jobs );
			} else {
				$show_jobs = get_option( self::SHOW_JOBS_OPTION_NAME, 'wpda' );
			}

			if ( isset( $_REQUEST['wpdaschema_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$schema_name = '';
			}

			global $wpdb;

			echo '<div class="wrap">';
			echo '<h1 class="wp-heading-inline">';
			echo __( 'Data Backup' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</h1>';

			if ( $data_backups_found || 'all' === $show_jobs ) {
				echo '<table cellpadding="3" cellspacing="3" border="0" style="border-collapse:collapse;">';
				echo '<tr>';
				echo '<th></th>';
				echo '<th></th>';
				echo '<th style="text-align:left;">' . __( 'Hook Name' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<th style="text-align:left;">' . __( 'Arguments' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<th style="text-align:left;">' . __( 'Interval' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<th style="text-align:left;">' . __( 'Next Execution' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<th style="text-align:left;">' . __( 'Backup Location' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<th style="text-align:left;">' . __( 'Files Kept' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<th colspan="2" style="text-align:left;">' . __( 'Status' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</tr>';
				foreach ( $crons as $key => $cron ) {
					foreach ( $cron as $key => $value ) {
						if ( 'wpda_data_backup' === $key ) {
							// Filter run once jobs.
							$is_runonce = false;
							foreach ( $value as $value_key => $value_value ) {
								if ( isset( $value_value['args'][0] ) && substr( $value_value['args'][0], 0, 14 ) === 'wpda-run-once-' ) {
									$is_runonce = true;
									continue;
								}
							}
							if ( $is_runonce ) {
								continue;
							}
							$style = 'style="background-color:#ffffff;"';
							$no_data_backup_events ++;
						} else {
							$style = '';
							if ( 'all' !== $show_jobs ) {
								// Hide other cron jobs.
								continue;
							}
						}
						echo '<tr ' . esc_attr( $style ) . '>';
						if ( 'wpda_data_backup' === $key ) {
							echo '<td>';
							echo '<form method="post" action="?page=wpda&page_action=wpda_backup">';
							echo '<a href="javascript:void(0)" onclick="if (confirm(\'Are you sure you want to delete this data backup job?\')) { jQuery(this).closest(\'form\').submit(); }" class="dashicons dashicons-trash"></a>';
							echo '<input type="hidden" name="action" value="remove" />';
							echo '<input type="hidden" name="schedule" value="' . esc_attr( $key ) . '" />';
							foreach ( $value as $value_key => $value_value ) {
								$schedule_args = isset( $value_value['args'][0] ) ? $value_value['args'][0] : '';
								echo '<input type="hidden" name="schedule_args" value="' . esc_attr( $schedule_args ) . '" />';
							}
							echo '</form>';
							echo '</td>';
							echo '<td>';
							echo '<form method="post" action="?page=wpda&page_action=wpda_backup">';
							echo '<a href="javascript:void(0)" onclick="jQuery(this).closest(\'form\').submit()" class="dashicons dashicons-edit"></a>';
							echo '<input type="hidden" name="action" value="edit" />';
							echo '<input type="hidden" name="schedule" value="' . esc_attr( $key ) . '" />';
							foreach ( $this->schedules as $schedule_key => $schedule ) {
								if ( $schedule['display'] === $this->schedules[ $value_value['schedule'] ]['display'] ) {
									$interval = $schedule_key;
								}
							}
							foreach ( $value as $value_key => $value_value ) {
								echo '<input type="hidden" name="schedule_args" value="' . esc_attr( $value_value['args'][0] ) . '" />';
								echo '<input type="hidden" name="interval" value="' . esc_attr( $interval ) . '" />';
							}
							if ( isset( $data_backups_keep[ $value_value['args'][0] ] ) ) {
								echo '<input type="hidden" name="device" value="' . esc_attr( $data_backups_device[ $value_value['args'][0] ] ) . '" />';
							}
							echo '</form>';
							echo '</td>';
						} else {
							echo '<td>';
							echo '</td>';
							echo '<td>';
							echo '</td>';
						}
						echo '<td>';
						echo esc_attr( $key );
						echo '</td>';
						foreach ( $value as $value_key => $value_value ) {
							echo '<td>';
							if ( is_array ($value_value['args']) &&
							     0 < count( $value_value['args'] ) ) {//phpcs:ignore - 8.1 proof
								foreach ( $value_value['args'] as $arg ) {
									if ( reset( $value_value['args'] ) !== $arg ) {//phpcs:ignore - 8.1 proof
										echo ',';
									}
									echo esc_attr( $arg );
								}
							} else {
								echo '-';
							}
							echo '</td>';
							echo '<td>';
							if ( isset( $this->schedules[ $value_value['schedule'] ]['display'] ) ) {
								echo esc_attr( $this->schedules[ $value_value['schedule'] ]['display'] );
								if ( isset( $value_value['interval'] ) ) {
									echo ' (' . esc_attr( $value_value['interval'] ) . ' sec)';
								}
							}
							echo '</td>';
							echo '<td>';
							$next = wp_next_scheduled( $key, $value_value['args'] );
							echo esc_attr( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ) ) );
							echo '</td>';
							echo '<td>';
							if ( 'wpda_data_backup' === $key ) {
								if ( isset( $data_backups_device[ $value_value['args'][0] ] ) ) {
									echo esc_attr( $data_backups_device[ $value_value['args'][0] ] );
								}
							}
							echo '</td>';
							echo '<td>';
							if ( 'wpda_data_backup' === $key ) {
								if ( isset( $data_backups_keep[ $value_value['args'][0] ] ) ) {
									echo esc_attr( $data_backups_keep[ $value_value['args'][0] ] );
								}
							}
							echo '</td>';
							echo '<td>';
							if ( 'wpda_data_backup' === $key ) {
								echo '<form method="post" action="?page=wpda">';
								echo '<input type="hidden" name="table_name" value="' . esc_attr( $wpdb->prefix ) . 'wpda_logging" />';
								echo '<input type="hidden" name="wpda_s" value="' . esc_attr( $value_value['args'][0] ) . '" />';
								echo '<a href="javascript:void(0)" onclick="jQuery(this).closest(\'form\').submit()" class="dashicons dashicons-info"></a>';
								echo '</form>';
							}
							echo '</td>';
							echo '<td>';
							if ( 'wpda_data_backup' === $key ) {
								$resultset = $wpdb->get_results(
									$wpdb->prepare(
										"
										SELECT `log_time`, `log_type`, `log_msg`
										  FROM `{$wpdb->prefix}wpda_logging`
										 WHERE `log_id` = %s
										 ORDER BY `log_time` desc limit 1",
										array(
											$value_value['args'][0],
										)
									),
									'ARRAY_A'
								); // db call ok; no-cache ok.
								if ( 1 === $wpdb->num_rows ) {
									echo esc_attr( $resultset[0]['log_type'] ) . ': ' .
										esc_attr( $resultset[0]['log_msg'] ) .
										' (' . esc_attr( $resultset[0]['log_time'] ) . ')';
								} else {
									echo __( 'No logging information found' ); // phpcs:ignore WordPress.Security.EscapeOutput
								}
							}
							echo '</td>';
						}
						echo '</tr>';
					}
				}
				echo '</table>';
				echo '<table style="margin-top:10px">';
				echo '<tr>';
				echo '<td><strong>' . esc_attr( $no_data_backup_events ) . ' data backup job' .
					( 1 < $no_data_backup_events ? 's' : '' ) . ' scheduled</strong></td>';
			} else {
				echo '<table>';
				echo '<tr>';
				echo '<td><strong>' . __( 'No data backup jobs found' ) . '</strong></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<td>';
			echo '<form method="post" action="?page=wpda&page_action=wpda_backup" style="display: inline-block; vertical-align: unset;">';
			echo '<select name="show_jobs" onchange="jQuery(this).closest(\'form\').submit()" >';
			echo '<option value="wpda"' . ( 'all' !== $show_jobs ? 'selected' : '' ) . '>' . __( 'Show plugin jobs only' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<option value="all"' . ( 'all' === $show_jobs ? 'selected' : '' ) . '>' . __( 'Show all WordPress jobs' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</select>';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
			echo '</table>';
			echo '</div>';
			?>
			<script type="text/javascript">
				jQuery(function () {
					jQuery( '.wpda_tooltip' ).tooltip();
				});
				// Add toolbar events
				jQuery("#wpda_toolbar_icon_add_backup").on("click", function() {
					jQuery("#wpda_new_backup_wpdaschema_name").val("<?php echo esc_attr( $schema_name ); ?>");
					jQuery("#wpda_new_backup").submit();
				});
			</script>
			<?php
		}

		/**
		 * Prepares an unattended data backup (export)
		 *
		 * @param string $backupid Unique ID that identifies a data backup.
		 */
		public function wpda_data_backup( $backupid ) {
			$is_runonce_data_backup = substr( $backupid, 0, strlen( self::PREFIX_RUNONCE ) ) === self::PREFIX_RUNONCE;
			if ( $is_runonce_data_backup ) {
				// Run once data backup.
				$data_backups = get_option( 'wpda_data_backup_option_runonce' );
				// Directly remove data backup to prevent multiple backups.
				$data_backups_new = array();
				foreach ( $data_backups as $data_backup ) {
					if ( $data_backup['id'] !== $backupid ) {
						array_push( $data_backups_new, $data_backup );//phpcs:ignore - 8.1 proof
					}
				}
				update_option( 'wpda_data_backup_option_runonce', $data_backups_new );
			} else {
				// Data backup job.
				$data_backups = get_option( 'wpda_data_backup_option' );
			}

			foreach ( $data_backups as $data_backup ) {
				if ( $data_backup['id'] === $backupid ) {
					if ( $is_runonce_data_backup ) {
						$user_backupid = substr( $backupid, strlen( self::PREFIX_RUNONCE ) );
					} else {
						$user_backupid = $backupid;
					}

					$keep   = $data_backup['keep'];
					$device = $data_backup['device'];
					$schema = $data_backup['schema_name'];
					$tables = $data_backup['tables'];

					$this->wpda_data_backup_run( $user_backupid, $keep, $device, $tables, $schema );
				}
			}
		}

		/**
		 * Performs an unattended data backup (export)
		 *
		 * @param string  $backupid Unique ID that identifies a data backup.
		 * @param integer $keep Number of backup files to be kept.
		 * @param string  $device Location where export file is stored.
		 * @param array   $tables Tables to be exported.
		 * @param array   $schema Database schema name or remote database connection string.
		 */
		protected function wpda_data_backup_run( $backupid, $keep, $device, $tables, $schema ) {
			try {
				$prefix   = "wpda-data-backup-$backupid-";
				$filename = $prefix . gmdate( 'YmdHis' ) . '.sql';

				if ( 'local_path' === $device ) {
					$local_path       = WPDA::get_option( WPDA::OPTION_DB_LOCAL_PATH );
					$client_file_name = $local_path . $filename;
					$file             = fopen( $client_file_name, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
					$wpda_export      = new WPDA_Export_Sql();
					$wpda_export->set_output_stream( $file );
					$wpda_export->export_with_arguments(
						'on',
						'on',
						'on',
						$schema,
						$tables,
						'table'
					);
					fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
					$keep_counting = 0;
					$files_sorted  = array();
					foreach ( glob( $local_path . "wpda-data-backup-$backupid-*.sql" ) as $filename ) {
						array_push( $files_sorted, $filename );//phpcs:ignore - 8.1 proof
					}
					rsort( $files_sorted );//phpcs:ignore - 8.1 proof
					foreach ( $files_sorted as $file ) {
						$keep_counting ++;
						if ( $keep_counting > (int) $keep ) {
							unlink( $file );
						}
					}
				} elseif ( 'dropbox' === $device ) {
					// To use a stream for Dropbox we first need to write the export to a temporary file.
					$temporary_file = tmpfile();
					$wpda_export    = new WPDA_Export_Sql();
					$wpda_export->set_output_stream( $temporary_file );
					$wpda_export->export_with_arguments(
						'on',
						'on',
						'on',
						$schema,
						$tables,
						'table'
					);

					$client_access_token = WPDA_Settings_DataBackup::dropbox_get_token();
					if ( false === $client_access_token ) {
						// Use old token strategy (for older activations).
						$client_access_token = get_option( 'wpda_db_dropbox_access_token' );
						if ( false === $client_access_token ) {
							// No token = no dropbox backup.
							WPDA::log( $backupid, 'ERROR', "Data backup '$backupid'' failed (missing access token)" );
							return;
						}
					}

					$client_access_path  = WPDA::get_option( WPDA::OPTION_DB_DROPBOX_PATH );
					if ( '' === $client_access_path ) {
						$client_access_path = '/';
					}
					$client_file_name    = $client_access_path . $filename;
					$post_max_size       = WPDA_Remote_Call::max_size();

					if (
						$post_max_size < filesize( stream_get_meta_data( $temporary_file )['uri'] ) &&
						! extension_loaded('zip')
					) {
						// File is too big to send in one chunk and zip archive is not installed.
						WPDA::log( $backupid, 'ERROR', "Data backup '$backupid'' failed (ZipArchive not installed)" );
						return;
					}

					if ( extension_loaded('zip') ) {
						$zip     = new \ZipArchive();
						$zipfile = sys_get_temp_dir() . '/' . $filename . '.zip';
						if ( ! $zip->open( $zipfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
							WPDA::log( $backupid, 'ERROR', "Data backup '$backupid'' failed (could not create ZIP file)" );
							return;
						}
						$zip->addFile( stream_get_meta_data( $temporary_file )['uri'], $filename );
						$zip->close();

						$zpf = fopen( $zipfile, 'r' );
						WPDA_Remote_Call::post(
							'https://content.dropboxapi.com/2/files/upload',
							fread( $zpf, filesize( $zipfile ) ),
							false,
							array(
								'Authorization'   => "Bearer $client_access_token",
								'Content-Type'    => 'application/octet-stream',
								'Dropbox-API-Arg' => '{"path":"' . $client_file_name . '.zip","mode":"add","autorename":false,"mute":false,"strict_conflict":false}',
							)
						);
					} else {
						fseek( $temporary_file, 0 );
						WPDA_Remote_Call::post(
							'https://content.dropboxapi.com/2/files/upload',
							fread( $temporary_file, filesize( stream_get_meta_data( $temporary_file )['uri'] ) ),
							false,
							array(
								'Authorization'   => "Bearer $client_access_token",
								'Content-Type'    => 'application/octet-stream',
								'Dropbox-API-Arg' => '{"path":"' . $client_file_name . '","mode":"add","autorename":false,"mute":false,"strict_conflict":false}',
							)
						);
					}

					// Search for outdated files.
					$ext      = extension_loaded('zip') ? '.zip' : '';
					$response = WPDA_Remote_Call::post(
						'https://api.dropboxapi.com/2/files/search_v2',
						json_encode(
							array(
								'options' => array(
									'file_status' 	=> 'active',
									'filename_only' => false,
									'max_results' 	=> 999,
									'path' 			=> substr( $client_access_path, 0, strlen( $client_access_path ) - 1 )
								),
								'query' => $prefix . '*' . $ext,
							)
						),
						false,
						array(
							'Authorization' => "Bearer $client_access_token",
							'Content-Type'  => 'application/json',
						)
					);

					if ( 'ALL' !== $keep && isset( $response['body'] ) ) {
						$body_content = json_decode( $response['body'], true );
						if ( isset( $body_content['matches'] ) ) {
							$keep_counting = 0;
							$files_sorted  = array();
							foreach ( $body_content['matches'] as $match ) {
								array_push( $files_sorted, $match['metadata']['metadata']['name'] );//phpcs:ignore - 8.1 proof
							}
							rsort( $files_sorted );//phpcs:ignore - 8.1 proof
							foreach ( $files_sorted as $file ) {
								$keep_counting++;
								if ( $keep_counting > (int) $keep ) {
									// Remove outdated files.
									WPDA_Remote_Call::post(
										'https://api.dropboxapi.com/2/files/delete_v2',
										json_encode(
											array(
												'path' => $client_access_path . $file,
											)
										),
										false,
										array(
											'Authorization' => "Bearer $client_access_token",
											'Content-Type'  => 'application/json',
										)
									);
								}
							}
						}
					}
				}
			} catch ( Exception $e ) {
				WPDA::log( $backupid, 'ERROR', "Data backup '$backupid' failed: " . $e->getMessage() );
			}

			WPDA::log( $backupid, 'INFO', "Data backup '$backupid' finished" );
		}

		/**
		 * Create a cron job for data export
		 *
		 * Backup ID = $_REQUEST['backupid']
		 */
		public function wpda_add_cron_job() {
			if ( ! isset( $_REQUEST['backupid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->show_wp_cron();
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "backupid" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();

				return;
			}
			$backupid = sanitize_text_field( wp_unslash( $_REQUEST['backupid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['interval'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->show_wp_cron();
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "interval" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();

				return;
			}
			$interval = sanitize_text_field( wp_unslash( $_REQUEST['interval'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['keep'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->show_wp_cron();
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "keep" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();

				return;
			}
			$keep = sanitize_text_field( wp_unslash( $_REQUEST['keep'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['device'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->show_wp_cron();
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "device" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();

				return;
			}
			$device = sanitize_text_field( wp_unslash( $_REQUEST['device'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['wpda_table_name_export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->show_wp_cron();
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'No tables defined to backup', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();

				return;
			} else {
				$request_tables = WPDA::sanitize_text_field_array( $_REQUEST['wpda_table_name_export'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification
			}
			if ( isset( $_REQUEST['wpdaschema_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$schema_name = '';
			}
			if ( 'runonce' === $interval ) {
				// Run data backup once. Do not create job.
				$data_backups = get_option( 'wpda_data_backup_option_runonce' );
				if ( ! $data_backups ) {
					$data_backups = array();
				}
				$data_backup = array(
					'id'          => self::PREFIX_RUNONCE . $backupid,
					'device'      => $device,
					'keep'        => $keep,
					'schema_name' => $schema_name,
					'tables'      => $request_tables,
				);
				array_push( $data_backups, $data_backup );//phpcs:ignore - 8.1 proof
				if ( ! update_option( 'wpda_data_backup_option_runonce', $data_backups ) ) {
					$msg = new WPDA_Message_Box(
						array(
							'message_text'           => __( 'Could not create data backup', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();
				} else {
					if ( ! wp_schedule_single_event(
						current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
						'wpda_data_backup',
						array( self::PREFIX_RUNONCE . $backupid )
					)
					) {
						$msg = new WPDA_Message_Box(
							array(
								'message_text'           => __( 'Backup failed', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
								'message_type'           => 'error',
								'message_is_dismissible' => false,
							)
						);
						$msg->box();
					}
				}
				?>
				<div class="wrap">
					<h1 class="wp-heading-inline">
						<?php echo __( 'Data Backup' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</h1>
					<p><?php echo __( 'Data backup started. Please check backup location.' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
					<p>
						<a href="?page=wpda&page_action=wpda_backup" class="button">
							<i class="fas fa-angle-left wpda_icon_on_button"></i>
							<?php echo __( 'List' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</a>
					</p>
				</div>
				<?php
			} else {
				// Create job for data backup.
				$data_backups = get_option( 'wpda_data_backup_option' );
				if ( ! $data_backups ) {
					$data_backups = array();
				} else {
					foreach ( $data_backups as $data_backup ) {
						if ( $data_backup['id'] === $backupid ) {
							$this->show_wp_cron();
							$msg = new WPDA_Message_Box(
								array(
									'message_text' => __( 'Backup id already exists', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
									'message_type' => 'error',
									'message_is_dismissible' => false,
								)
							);
							$msg->box();

							return;
						}
					}
				}
				$data_backup = array(
					'id'          => $backupid,
					'device'      => $device,
					'keep'        => $keep,
					'schema_name' => $schema_name,
					'tables'      => $request_tables,
				);
				array_push( $data_backups, $data_backup );//phpcs:ignore - 8.1 proof
				if ( ! update_option( 'wpda_data_backup_option', $data_backups ) ) {
					$this->show_wp_cron();
					$msg = new WPDA_Message_Box(
						array(
							'message_text'           => __( 'Could not save data backup options', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						)
					);
					$msg->box();
				} else {
					global $wpdb;
					$wpdb->flush();
					wp_cache_flush();
					if ( ! wp_next_scheduled( 'wpda_data_backup', array( $backupid ) ) ) {
						wp_schedule_event( current_time( 'timestamp' ), $interval, 'wpda_data_backup', array( $backupid ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					}
				}
				$this->show_wp_cron();
			}
		}

		/**
		 * Remove a data backup from cron
		 *
		 * Backup ID = $_REQUEST['schedule_args']
		 */
		public function wpda_remove_cron_job() {
			if ( isset( $_REQUEST['schedule_args'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$backupid = sanitize_text_field( wp_unslash( $_REQUEST['schedule_args'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				if ( isset( $_REQUEST['schedule'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$schedule  = sanitize_text_field( wp_unslash( $_REQUEST['schedule'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
					if ( '' !== $backupid ) {
						$timestamp  = wp_next_scheduled( $schedule, array( $backupid ) );
						$unschedule = wp_unschedule_event( $timestamp, $schedule, array( $backupid ) );
					} else {
						$timestamp = wp_next_scheduled( $schedule );
						$unschedule = wp_unschedule_event( $timestamp, $schedule );
					}

					if ( false === $unschedule ) {
						$msg = new WPDA_Message_Box(
							array(
								'message_text'           => __( 'Could not delete data backup schedule', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
								'message_type'           => 'error',
								'message_is_dismissible' => false,
							)
						);
						$msg->box();
					} else {
						$msg = new WPDA_Message_Box(
							array(
								'message_text'           => __( 'Deleted data backup schedule', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
							)
						);
						$msg->box();
						// Remove job from queue.
						$data_backups     = get_option( 'wpda_data_backup_option' );
						$data_backups_new = array();
						foreach ( $data_backups as $data_backup ) {
							if ( $data_backup['id'] !== $backupid ) {
								array_push( $data_backups_new, $data_backup );//phpcs:ignore - 8.1 proof
							}
						}
						update_option( 'wpda_data_backup_option', $data_backups_new );
					}
				}
			}
			$this->show_wp_cron();
		}

		/**
		 * Update a data backup
		 *
		 * Backup ID = $_REQUEST['backupid']
		 */
		public function wpda_update_cron_job() {
			if ( isset( $_REQUEST['backupid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$backupid = sanitize_text_field( wp_unslash( $_REQUEST['backupid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "backupid" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			}
			if ( isset( $_REQUEST['wpda_table_name_export'] ) && is_array( $_REQUEST['wpda_table_name_export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$wpda_table_name_export = WPDA::sanitize_text_field_array( $_REQUEST['wpda_table_name_export'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput ,WordPress.Security.NonceVerification
			} else {
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'No tables defined to backup', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			}
			if ( isset( $_REQUEST['interval'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$interval = sanitize_text_field( wp_unslash( $_REQUEST['interval'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "interval" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			}
			if ( isset( $_REQUEST['keep'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$keep = sanitize_text_field( wp_unslash( $_REQUEST['keep'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "keep" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			}
			if ( isset( $_REQUEST['device'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$device = sanitize_text_field( wp_unslash( $_REQUEST['device'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Mandatory item "device" not found', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			}
			if ( isset( $_REQUEST['wpdaschema_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				$schema_name = '';
			}
			// Reschedule current job.
			$timestamp = wp_next_scheduled( 'wpda_data_backup', array( $backupid ) );
			if ( false === wp_unschedule_event( $timestamp, 'wpda_data_backup', array( $backupid ) ) ) {
				$msg = new WPDA_Message_Box(
					array(
						'message_text'           => __( 'Could not delete data backup schedule', 'wp-data-access' ), // phpcs:ignore WordPress.Security.EscapeOutput
						'message_type'           => 'error',
						'message_is_dismissible' => false,
					)
				);
				$msg->box();
			} else {
				// Update job settings.
				$data_backups     = get_option( 'wpda_data_backup_option' );
				$data_backups_new = array();
				foreach ( $data_backups as $data_backup ) {
					if ( $data_backup['id'] !== $backupid ) {
						array_push( $data_backups_new, $data_backup );//phpcs:ignore - 8.1 proof
					} else {
						$data_backup_updated = array(
							'id'          => $backupid,
							'keep'        => $keep,
							'device'      => $device,
							'schema_name' => $schema_name,
							'tables'      => $wpda_table_name_export,
						);
						array_push( $data_backups_new, $data_backup_updated );//phpcs:ignore - 8.1 proof
					}
				}
				update_option( 'wpda_data_backup_option', $data_backups_new );
				// Reschedule job with new arguments.
				wp_schedule_event( current_time( 'timestamp' ), $interval, 'wpda_data_backup', array( $backupid ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			}
			$this->show_wp_cron();
		}

	}

}
