<?php // phpcs:ignore Standard.Category.SniffName.ErrorCode

namespace WPDataAccess\CSV_Files {

	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Lists;
	use WPDataAccess\Plugin_Table_Models\WPDA_CSV_Uploads_Model;
	use WPDataAccess\WPDA;

	/**
	 * CSV mapping class
	 */
	class WPDA_CSV_Mapping {


		/**
		 * Delimiters
		 */
		const DELIMITER = array(
			'comma'     => ',',
			'semicolon' => ';',
			'tab'       => "\t",
			'pipe'      => '|',
			'colon'     => ':',
		);
		/**
		 * Mapping
		 *
		 * @var mixed|null
		 */
		protected $mapping = null;

		/**
		 * Actual delimiter
		 *
		 * @var mixed|string
		 */
		protected $delimiter = ',';
		/**
		 * Data format
		 *
		 * @var mixed|string
		 */
		protected $date_format = '%Y-%m-%d';
		/**
		 * Indicates if header columns are available
		 *
		 * @var bool
		 */
		protected $has_header_columns = true;

		/**
		 * CSV ID
		 *
		 * @var string|null
		 */
		protected $csv_id = null;
		/**
		 * CSV name
		 *
		 * @var string
		 */
		protected $csv_name = '';
		/**
		 * CSV upload
		 *
		 * @var null
		 */
		protected $csv_upload = null;

		/**
		 * Nonce
		 *
		 * @var null
		 */
		protected $wpnonce = null;
		/**
		 * Preview nonce
		 *
		 * @var null
		 */
		protected $wpnonce_preview = null;

		/**
		 * Database schema name
		 *
		 * @var array
		 */
		protected $db_schema_name = array();
		/**
		 * Schema name mapping
		 *
		 * @var mixed|null
		 */
		protected $schema_name_mapping = null;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->csv_id =
				isset( $_REQUEST['csv_id'] ) ?
					sanitize_text_field( wp_unslash( $_REQUEST['csv_id'] ) ) : ''; // input var okay.

			if ( null === $this->csv_id ) {
				wp_die( __( 'ERROR: Missing argument', 'wp-data-access' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}

			$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // input var okay.
			if ( ! wp_verify_nonce( $wp_nonce, "wpda-mapping-{$this->csv_id}" ) ) {
				wp_die( __( 'ERROR: Not authorized', 'wp-data-access' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}

			$this->csv_upload = WPDA_CSV_Uploads_Model::query( $this->csv_id );
			if ( isset( $this->csv_upload[0]->csv_name ) ) {
				$this->csv_name = $this->csv_upload[0]->csv_name;
			}
			if ( isset( $this->csv_upload[0]->csv_mapping ) ) {
				$this->mapping = json_decode( $this->csv_upload[0]->csv_mapping, true );
				if ( isset( $this->mapping['settings']['delimiter'] ) ) {
					$this->delimiter = $this->mapping['settings']['delimiter'];
					if ( '\\t' === $this->delimiter ) {
						$this->delimiter = "\t";
					}
				}
				if ( isset( $this->mapping['settings']['date_format'] ) ) {
					$this->date_format = $this->mapping['settings']['date_format'];
				}
				if (
					isset( $this->mapping['settings']['has_header_columns'] ) &&
					'false' === $this->mapping['settings']['has_header_columns']
				) {
					$this->has_header_columns = false;
				}
			}

			$this->db_schema_name = WPDA_Dictionary_Lists::get_db_schemas();

			if ( isset( $this->mapping['database']['wpdaschema_name'] ) ) {
				$this->schema_name_mapping = $this->mapping['database']['wpdaschema_name'];
			} else {
				$this->schema_name_mapping = WPDA::get_user_default_scheme();
			}

			$this->wpnonce         = wp_create_nonce( "wpda-csv-mapping-{$this->csv_id}" );
			$this->wpnonce_preview = wp_create_nonce( "wpda-csv-preview-mapping-{$this->csv_id}" );
		}

		/**
		 * Show mapping page
		 *
		 * @return void
		 */
		public function show() {
			$upload_dir = WPDA::get_plugin_upload_dir();
			if ( ! isset( $this->csv_upload[0]->csv_real_file_name ) ) {
				wp_die( __( 'ERROR: CSV file not found', 'wp-data-access' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			$file_name = $upload_dir . $this->csv_upload[0]->csv_real_file_name;

			$table_list = WPDA_Dictionary_Lists::get_tables( false, $this->schema_name_mapping );

			$wp_nonce         = esc_attr( wp_create_nonce( "wpda-import-csv-{$this->csv_id}" ) );
			$wp_nonce_mapping = esc_attr( wp_create_nonce( "wpda-mapping-{$this->csv_id}" ) );
			?>
			<style type="text/css">
				#csv_table_columns, .csv_column {
					display: inline-block;
					min-width: 200px;
					margin: 0;
				}
				#csv_table_columns {
					min-height: 100px;
				}
				.csv_column {
					height: 28px;
				}
				#csv_table_columns li, .csv_column li {
					padding: 0 8px;
					line-height: 2;
					min-height: 28px;
					box-shadow: 0 0 0 transparent;
					border-radius: 4px;
					border: 1px solid #7e8993;
					background-color: #fff;
					color: #32373c;
					margin-bottom: 2px;
				}
				#wpda_csv_mapping th {
					text-align: right;
				}
				#wpda_csv_mapping th, #wpda_csv_mapping td {
					padding: 2px;
				}
				.wpda_highlight {
					background-color: yellow !important;
				}
				.wpda_receive {
					background-color: lightblue !important;
				}
				.wpda_csv_column_init {
					border: none !important;
				}
			</style>
			<script type="text/javascript">
				var mapping = [];
				<?php
				if ( null !== $this->mapping ) {
					$mapping_to_js = wp_json_encode( $this->mapping );
					echo 'var mapping = ' . $mapping_to_js . ";\n"; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>

				function wpda_get_tables() {
					var schema_name = jQuery('#csv_schema_name').val();

					var url = location.pathname + '?action=wpda_get_tables&hideviews=TRUE';
					var data = {
						wpdaschema_name: schema_name,
						wpda_wpnonce: '<?php echo esc_attr( wp_create_nonce( 'wpda-getdata-access-' . WPDA::get_current_user_login() ) ); ?>'
					};
					jQuery.post(
						url,
						data,
						function (data) {
							jQuery('#csv_table_name').find('option').remove();
							var jsonData = JSON.parse(data);
							for (i = 0; i < jsonData.length; i++) {
								jQuery('#csv_table_name').append(
									jQuery("<option></option>")
									.attr("value", jsonData[i]['table_name'])
									.text(jsonData[i]['table_name'])
								);
							}
							jQuery('#csv_table_name').trigger("change");
						}
					);
				}

				function wpda_get_columns(init=false) {
					var schema_name = jQuery('#csv_schema_name').val();
					var table_name = jQuery('#csv_table_name').val();

					var url = location.pathname + '?action=wpda_get_columns';
					var data = {
						wpdaschema_name: schema_name,
						table_name: table_name,
						wpda_wpnonce: '<?php echo esc_attr( wp_create_nonce( 'wpda-getdata-access-' . WPDA::get_current_user_login() ) ); ?>'
					};
					jQuery.post(
						url,
						data,
						function (data) {
							var jsonData = JSON.parse(data);
							jQuery('#csv_table_columns').empty();
							if (!init) {
								jQuery('.csv_column').empty().append(
									'<li class="wpda_csv_column_init"><?php echo __( 'Drag column here...', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>'
								);
							}
							for (i = 0; i < jsonData.length; i++) {
								add_column = true;
								if (init) {
									for (var column in mapping.columns) {
										if (mapping.columns[column]===jsonData[i]['column_name']) {
											add_column = false;
										}
									}
								}
								if (add_column) {
									jQuery('#csv_table_columns').append(
										jQuery("<li></li>")
										.attr("value", jsonData[i]['column_name'])
										.text(jsonData[i]['column_name'])
									);
								}
							}
						}
					);
				}

				function save_mapping(refresh=false, exclude_mapping=false) {
					var dbs_schema_name = jQuery('#csv_schema_name').val();
					var dbs_table_name = jQuery('#csv_table_name').val();

					let delimiter = jQuery('#csv_delimiter').val();
					if (delimiter==="\t") {
						delimiter = "\\t";
					}
					var settings = {
						delimiter: delimiter,
						date_format: jQuery('#csv_date_format').val(),
						has_header_columns: jQuery('#csv_has_header_columns').is(':checked')
					};
					var database = {
						wpdaschema_name: dbs_schema_name,
						table_name: dbs_table_name
					};
					var columns = {};

					i = 0;
					jQuery('ul.csv_column_mapped').each(function() {
						csv_column_name = jQuery(this).attr('data-csv-column-name');
						if (csv_column_name!==undefined) {
							dbs_column_name = jQuery(this).find('li').first().text().trim();
							if (dbs_column_name!=='' &&
								dbs_column_name!=='<?php echo __( 'Drag column here...', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>'
							) {
								columns[i] = dbs_column_name;
							}
						}
						i++;
					});

					if (exclude_mapping) {
						var new_mapping = {
							settings: settings,
							database: mapping.database,
							columns: mapping.columns
						};
					} else {
						var new_mapping = {
							settings: settings,
							database: database,
							columns: columns
						};
					}

					jQuery.ajax({
						method: 'POST',
						url: location.pathname + "?action=wpda_save_csv_mapping",
						data: {
							wpnonce: '<?php echo esc_attr( $this->wpnonce ); ?>',
							csv_id: '<?php echo esc_attr( $this->csv_id ); ?>',
							csv_mapping: new_mapping
						}
					}).done(
						function(msg) {
							if (msg==='UPD-0' || msg==='UPD-1') {
								alert('Mapping successfully updated');
								if (refresh) {
									jQuery('#change_delimiter').submit();
								}
							} else {
								alert('ERROR: ' + msg);
							}
						}
					);
				}

				function change_delimiter() {
					save_mapping(true, true);
				}

				function preview(page_number, page_length) {
					if (page_number<1) {
						page_number = 1;
					}
					jQuery.ajax({
						type: 'POST',
						url: location.pathname + '?action=wpda_csv_preview_mapping',
						data: {
							csv_id: '<?php echo esc_attr( $this->csv_id ); ?>',
							wpnonce: '<?php echo esc_attr( $this->wpnonce_preview ); ?>',
							page_number: page_number,
							page_length: page_length
						}
					}).done(
							function(msg) {
								jQuery('#wpda_csv_preview_table').empty().append(msg);
							}
						);
				}

				function toggle_preview() {
					jQuery('#wpda_csv_preview').toggle();
					if ( jQuery('#wpda_csv_preview').is(":visible") ) {
						jQuery('#wpda_preview_visible').hide();
						jQuery('#wpda_preview_hidden').show();
					} else {
						jQuery('#wpda_preview_visible').show();
						jQuery('#wpda_preview_hidden').hide();
					}
				}

				var wpda_moving_item = '';

				jQuery(function() {
					wpda_get_columns(true);

					jQuery('#csv_table_columns').sortable({
						connectWith: 'ul',
						start: function( event, ui ) {
							jQuery(ui.item).addClass("wpda_highlight");
						},
						stop: function( event, ui ) {
							jQuery(ui.item).removeClass("wpda_highlight");
						},
						receive: function( event, ui ) {
							if (wpda_moving_item.childElementCount===0) {
								jQuery(wpda_moving_item).empty().append(
									'<li class="wpda_csv_column_init"><?php echo __( 'Drag column here...', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>'
								);
							}
						}
					});

					jQuery('.csv_column').sortable({
						connectWith: 'ul',
						start: function( event, ui ) {
							wpda_moving_item = event.target;
						},
						stop: function( event, ui ) {
							wpda_moving_item = '';
						},
						over: function( event, ui ) {
							jQuery(event.target).closest('.csv_column_selected').find('.csv_column_selected_cell').addClass("wpda_receive");
						},
						out: function( event, ui ) {
							jQuery(event.target).closest('.csv_column_selected').find('.csv_column_selected_cell').removeClass("wpda_receive");
						},
						receive: function( event, ui ) {
							jQuery(event.target).find('.wpda_csv_column_init').remove();
							if (jQuery(event.target)[0].children.length===2) {
								if (wpda_moving_item!=='') {
									jQuery(wpda_moving_item).append(ui.item[0]);
								} else {
									if (jQuery(event.target)[0].firstChild===ui.item[0]) {
										jQuery('#csv_table_columns').append(jQuery(event.target)[0].lastChild);
									} else {
										jQuery('#csv_table_columns').append(jQuery(event.target)[0].firstChild);
									}
								}
							}
							if (wpda_moving_item.childElementCount===0) {
								jQuery(wpda_moving_item).append(
									'<li class="wpda_csv_column_init"><?php echo __( 'Drag column here...', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>'
								);
							}
						}
					});

					preview(1, 5);
				});
			</script>
			<form method="post" action="?page=wpda&page_action=wpda_import_csv">
				<br/>
				<fieldset class="wpda_fieldset">
					<legend>
						<?php echo __( 'Settings', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</legend>
					<div id="wpda_csv_settings">
						<label for="csv_delimiter" style="line-height: 2; vertical-align: text-top;">
							<span>Field separator</span>
							<select id="csv_delimiter" name="csv_table_name" style="margin-left: 0; vertical-align: top;" onchange="wpda_get_columns()">
								<?php
								foreach ( self::DELIMITER as $key => $val ) {
									$selected = $this->delimiter === $val ? ' selected' : '';
									echo '<option value="' . esc_attr( $val ) . '" ' . esc_attr( $selected ) . '>' . esc_attr( $key ) . '</option>';
								}
								?>
							</select>
						</label>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<label for="csv_has_header_columns" style="line-height: 2; vertical-align: text-top;">
							<span>Date format</span>
							<select id="csv_date_format" name="csv_date_format">
								<option value="%Y-%m-%d" <?php echo '%Y-%m-%d' === $this->date_format ? 'selected' : ''; ?>>yyyy-mm-dd</option>
								<option value="%Y/%m/%d" <?php echo '%Y/%m/%d' === $this->date_format ? 'selected' : ''; ?>>yyyy/mm/dd</option>
								<option value="%d-%m-%Y" <?php echo '%d-%m-%Y' === $this->date_format ? 'selected' : ''; ?>>dd-mm-yyyy</option>
								<option value="%d/%m/%Y" <?php echo '%d/%m/%Y' === $this->date_format ? 'selected' : ''; ?>>dd/mm/yyyy</option>
								<option value="%m-%d-%Y" <?php echo '%m-%d-%Y' === $this->date_format ? 'selected' : ''; ?>>mm-dd-yyyy</option>
								<option value="%m/%d/%Y" <?php echo '%m/%d/%Y' === $this->date_format ? 'selected' : ''; ?>>mm/dd/yyyy</option>
							</select>
						</label>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<label for="csv_has_header_columns" style="line-height: 2; vertical-align: text-top; padding-right:15px;">
							<input id="csv_has_header_columns"
									name="csv_has_header_columns"
									type="checkbox"
									<?php echo $this->has_header_columns ? 'checked' : ''; ?>
							/>
							<span>Has header columns</span>
						</label>

						<button type="button" class="button" onclick="change_delimiter()">
							<i class="fas fa-check wpda_icon_on_button"></i>
							<?php echo __( 'Apply settings' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</button>
					</div>
				</fieldset>
				<br/>
				<fieldset class="wpda_fieldset">
					<legend>
						<?php echo __( 'Destination database and table', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</legend>
				<div>
					<select id="csv_schema_name" name="csv_schema_name" style="margin-left: 0; vertical-align: top;" onchange="wpda_get_tables()">
						<?php
						foreach ( $this->db_schema_name as $db_schema_name ) {
							$selected = $this->schema_name_mapping === $db_schema_name['schema_name'] ? 'selected' : '';
							echo '<option value="' . esc_attr( $db_schema_name['schema_name'] ) . '" ' . esc_attr( $selected ) . '>' .
									esc_attr( $db_schema_name['schema_name'] ) .
								'</option>';
						}
						?>
					</select>
					<br/>
					<select id="csv_table_name" name="csv_table_name" style="margin-left: 0; vertical-align: top;" onchange="wpda_get_columns()">
						<?php
						foreach ( $table_list as $table ) {
							$selected = '';
							if ( isset( $this->mapping['database']['table_name'] ) ) {
								$selected = $this->mapping['database']['table_name'] === $table['table_name'] ? ' selected' : '';
							}
							echo '<option value="' . esc_attr( $table['table_name'] ) . '" ' . esc_attr( $selected ) . '>' .
									esc_attr( $table['table_name'] ) .
								'</option>';
						}
						?>
					</select>
				</div>
				</fieldset>
				<?php
				$csv_columns = array();
				@ini_set( 'auto_detect_line_endings', true ); // phpcs:ignore
				if ( false !== ( $fp = fopen( $file_name, 'r' ) ) ) { // phpcs:ignore
					$index = 0;
					while ( false !== ( $data = fgetcsv( $fp, 0, $this->delimiter ) ) ) { // phpcs:ignore
						for ( $column = 0; $column < count( $data ); $column ++ ) { // phpcs:ignore - 8.1 proof
							if ( $this->has_header_columns ) {
								array_push( $csv_columns, $data[ $column ] );//phpcs:ignore - 8.1 proof
							} else {
								array_push( $csv_columns, "column_{$index}" );//phpcs:ignore - 8.1 proof
								$index++;
							}
						}
						break;
					}
					fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
				}
				?>
				<br/>
				<fieldset class="wpda_fieldset">
					<legend>
						<?php echo __( 'Column mapping', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<a href="javascript:void(0)">
							<span class="dashicons dashicons-editor-help wpda_tooltip" title="Drag columns from Table to Mapped" style="cursor:pointer;vertical-align:bottom;text-decoration:none;"></span>
						</a>
					</legend>
					<div>
						<table id="wpda_csv_mapping" cellpadding="0" cellspacing="0">
							<tr>
								<th></th>
								<th>&nbsp;</th>
								<th style="text-align:left;">Mapped</th>
								<th>&nbsp;</th>
								<th style="text-align:left;">Table</th>
							</tr>
							<?php
							$first_row = true;
							foreach ( $csv_columns as $key => $csv_column_ ) {
								$csv_column = trim( $csv_column_ );
								?>
								<tr class="csv_column_selected" style="height: 30px !important;">
									<th class="csv_column_selected_cell">
										<?php echo esc_attr( $csv_column ); ?>
									</th>
									<td></td>
									<td class="csv_column_selected_cell">
										<ul class="csv_column csv_column_mapped"
											data-csv-column-name="<?php echo esc_attr( $csv_column ); ?>"
										>
										<?php
										if ( isset( $this->mapping['columns'][ $key ] ) ) {
											?>
												<li value="<?php echo esc_attr( $this->mapping['columns'][ $key ] ); ?>">
												<?php echo esc_attr( $this->mapping['columns'][ $key ] ); ?>
												</li>
												<?php
										} else {
											?>
												<li class="wpda_csv_column_init">
												<?php echo __( 'Drag column here...', 'wp-data-access' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
												</li>
												<?php
										}
										?>
											</ul>
									</td>
									<td></td>
									<?php
									if ( $first_row ) {
										?>
										<td rowspan="<?php echo esc_attr( count( $csv_columns ) + 1 ); //phpcs:ignore - 8.1 proof ?>" style="vertical-align: top;">
											<ul id="csv_table_columns">
											</ul>
										</td>
										<?php
									}
									?>
								</tr>
								<?php
								$first_row = false;
							}
							?>
							<tr>
								<td></td>
								<td></td>
								<td></td>
								<td></td>
							</tr>
						</table>
					</div>
				</fieldset>
			</form>
			<form id="change_delimiter" method="post"
					action="?page=wpda&page_action=wpda_import_csv" style="display: none;">
				<input type="hidden" name="csv_id" value="<?php echo esc_attr( $this->csv_id ); ?>"/>
				<input type="hidden" name="action" value="mapping"/>
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $wp_nonce_mapping ); ?>" />
			</form>
			<form id="import_form" action="?page=wpda&page_action=wpda_import_csv"
					method="post" style="display: none;">
				<input type="hidden" name="action" value="import_start" />
				<input type="hidden" name="csv_id" value="<?php echo esc_attr( $this->csv_id ); ?>" />
				<input type="hidden" name="csv_name" value="<?php echo esc_attr( $this->csv_name ); ?>" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $wp_nonce ); ?>" />
			</form>
			<br/>
			<a href="javascript:void(0)" onclick="save_mapping()" class="button">
				<span class="dashicons dashicons-yes-alt" style="padding-top: 4px; padding-right: 4px;"></span>
				Save mapping
			</a>
			<a href="javascript:void(0)" onclick="jQuery('#import_form').submit()" class="button">
				<span class="dashicons dashicons-upload" style="padding-top: 4px; padding-right: 4px;"></span>
				Import CSV file
			</a>
			<a id="wpda_preview_visible" href="javascript:void(0)" onclick="toggle_preview()" class="button">
				<span class="dashicons dashicons-visibility" style="padding-top: 4px; padding-right: 4px;"></span>
				Preview CSV file
			</a>
			<a id="wpda_preview_hidden" href="javascript:void(0)" onclick="toggle_preview()" class="button" style="display: none;">
				<span class="dashicons dashicons-hidden" style="padding-top: 4px; padding-right: 4px;"></span>
				Hide CSV file
			</a>
			<?php
			$this->preview();
		}

		/**
		 * Preview container
		 *
		 * @return void
		 */
		protected function preview() {
			?>
			<div id="wpda_csv_preview" style="display: none;">
			<br/>
			<div id="wpda_csv_preview_table"></div>
			<?php
		}

	}

}
