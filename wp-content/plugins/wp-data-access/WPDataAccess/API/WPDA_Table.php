<?php

namespace WPDataAccess\API {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;
	use WPDataAccess\WPDA;

	class WPDA_Table {

		/**
		 * Perform query and return result as JSON response.
		 *
		 * @param string   $schema_name Schema name (database).
		 * @param string   $table_name Table Name.
		 * @param int      $start Page number (zero based).
		 * @param int      $length Rows per page.
		 * @param string   $order Sorting columns.
		 * @param string   $search Filter.
		 * @param int      $draw Internal use.
		 * @param int|null $records_total Total record count from first query.
		 * @param int|null $records_filtered Record filtered from previous query.
		 * @return \WP_Error|\WP_REST_Response
		 */
		public static function dtselect(
			$schema_name,
			$table_name,
			$start,
			$length,
			$order,
			$search,
			$draw,
			$records_total,
			$records_filtered
		) {
			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				// Error connecting.
				return self::dterror( "Error connecting to database {$schema_name}", $draw );
			} else {
				// Connected, perform queries.
				$suppress = $wpdadb->suppress_errors( true );
				$draw++;

				$where = '';
				if ( is_array( $search ) && isset( $search['value'] ) ) {
					// Add search filter.
					$wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );
					$where             = WPDA::construct_where_clause(
						$schema_name,
						$table_name,
						$wpda_list_columns->get_searchable_table_columns(),
						$search['value']
					);

					if ( '' !== $where ) {
						$where = " where {$where} ";
					}
				}

				$sqlorder = '';
				if ( is_array( $order ) ) {
					foreach ( $order as $orderby ) {
						if ( isset( $orderby['column'], $orderby['dir'] ) ) {
							$column = intval( $orderby['column'] ) + 1;
							$dir    = $orderby['dir'];

							if ( '' === $sqlorder ) {
								$sqlorder = 'order by ';
							} else {
								$sqlorder .= ',';
							}

							$sqlorder .= sanitize_sql_orderby( "{$column} ${dir}" );
						}
					}
				}

				// Query.
				$query   = $wpdadb->prepare(
					"
						select * 
						from `%1s` 
						{$where} 
						{$sqlorder} 
						limit {$length} offset {$start}
					",
					array(
						$table_name,
					)
				);
				$dataset = $wpdadb->get_results(
					$query,
					'ARRAY_N'
				);

				if ( $wpdadb->last_error ) {
					// Handle SQL errors.
					return self::dterror( $wpdadb->last_error, $draw );
				}

				// Count total rows.
				if ( $records_total !== null && is_numeric( $records_total ) ) {
					$count_total = $records_total;
				} else {
					$get_count_total = $wpdadb->get_results(
						$wpdadb->prepare(
							'
                        select count(1)
                        from `%1s`
						',
							array(
								$table_name,
							)
						),
						'ARRAY_N'
					);

					if ( $wpdadb->last_error ) {
						// Handle SQL errors.
						return self::dterror( $wpdadb->last_error, $draw );
					}

					$count_total = $get_count_total[0][0];
				}

				// Count rows filtered.
				if ( $where !== '' ) {
					if ( $records_filtered !== null && is_numeric( $records_filtered ) ) {
						$count_filtered = $records_filtered;
					} else {
						$get_count_filtered = $wpdadb->get_results(
							$wpdadb->prepare(
								"
							select count(1)
							from `%1s`
							{$where}
							",
								array(
									$table_name,
								)
							),
							'ARRAY_N'
						);

						if ( $wpdadb->last_error ) {
							// Handle SQL errors.
							return self::dterror( $wpdadb->last_error, $draw );
						}

						$count_filtered = $get_count_filtered[0][0];
					}
				} else {
					$count_filtered = $count_total;
				}

				$wpdadb->suppress_errors( $suppress );

				$response = array(
					'data'            => $dataset,
					'draw'            => $draw,
					'error'           => '',
					'recordsTotal'    => (int) $count_total,
					'recordsFiltered' => (int) $count_filtered,
					'search'          => $search
				);

				if ( 'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_DEBUG ) ) {
					$response['debug'] = array(
						'query' => str_replace(
							array( "\n", "\t" ),
							array( "", "" ),
							$query
						)
					);
				}

				return new \WP_REST_Response(
					$response,
					200
				);
			}
		}

		private static function dterror( $error, $draw ) {
			return new \WP_REST_Response(
				array(
					'data'            => [],
					'draw'            => $draw,
					'error'           => $error,
					'recordsTotal'    => 0,
					'recordsFiltered' => 0,
				),
				200
			);
		}

		/**
		 * Perform query and return result as JSON response.
		 *
		 * @param string $schema_name Schema name (database).
		 * @param string $table_name Table Name.
		 * @param array $primary Primary (key|value pairs.
		 * @return \WP_Error|\WP_REST_Response
		 */
		public static function get(
			$schema_name,
			$table_name,
			$primary_key
		) {
			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				// Error connecting.
				return new \WP_Error( 'error', "Error connecting to database {$schema_name}", array( 'status' => 420 ) );
			} else {
				// Connected, perform queries.
				$suppress = $wpdadb->suppress_errors( true );

				$wpda_list_columns   = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );
				$primary_key_columns = $wpda_list_columns->get_table_primary_key();

				$where = '';
				foreach ( $primary_key_columns as $primary_key_column ) {
					if ( ! isset( $primary_key[ $primary_key_column ] ) ) {
						return new \WP_Error( 'error', "Invalid arguments", array( 'status' => 420 ) );
					}

					$where = '' === $where ? ' where ' : ` $where and `;
					$where .= $wpdadb->prepare(
						" `%1s` like %s ",
						array(
							WPDA::remove_backticks( $primary_key_column ),
							$primary_key[ $primary_key_column ],
						)
					);
				}

				$dataset = $wpdadb->get_results(
					$wpdadb->prepare("
							select *
							from `%1s`
							{$where}
						",
						array(
							$table_name,
						)
					),
					'ARRAY_A'
				);

				$wpdadb->suppress_errors( $suppress );

				// Send response.
				if ( 0 === $wpdadb->num_rows ) {
					return new \WP_Error( 'error', "No data found", array( 'status' => 420 ) );
				} elseif ( 1 === $wpdadb->num_rows ) {
					return WPDA_API::WPDA_Rest_Response( '', $dataset );
				} else {
					return new \WP_Error( 'error', "Invalid arguments", array( 'status' => 420 ) );
				}
			}
		}

		/**
		 * Perform query and return result as JSON response.
		 *
		 * @param string $schema_name Schema name (database).
		 * @param string $table_name Table Name.
		 * @param string $page Page number.
		 * @param string $rows Rows per page.
		 * @param string $order Sorting columns.
		 * @param string $order_dir Asc (default) or desc.
		 * @param string $search Filter.
		 * @return \WP_Error|\WP_REST_Response
		 */
		public static function select( $schema_name, $table_name, $page, $rows, $order, $order_dir, $search ) {
			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				// Error connecting.
				return new \WP_Error( 'error', "Error connecting to database {$schema_name}", array( 'status' => 420 ) );
			} else {
				// Connected, perform queries.
				$suppress = $wpdadb->suppress_errors( true );

				$where = '';
				if ( null !== $search ) {
					// Add search filter.
					$wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );
					$where             = WPDA::construct_where_clause(
						$schema_name,
						$table_name,
						$wpda_list_columns->get_searchable_table_columns(),
						$search
					);
					if ( '' !== $where ) {
						$where = " where {$where} ";
					}
				}

				$sqlorder = '';
				if ( null !== $order ) {
					// Add order by.
					$_order     = explode( ',', $order );//phpcs:ignore - 8.1 proof
					$_order_dir = explode( ',', (string) $order_dir );//phpcs:ignore - 8.1 proof
					//phpcs:ignore - 8.1 proof
					for ( $i = 0; $i < count( $_order ); $i++ ) { // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall, Squiz.PHP.DisallowSizeFunctionsInLoops
						if ( '' === $sqlorder ) {
							$sqlorder = 'order by ';
						} else {
							$sqlorder .= ',';
						}
						if ( isset( $_order_dir[ $i ] ) ) {
							$sqlorder .= sanitize_sql_orderby( "{$_order[ $i ]} {$_order_dir[ $i ]}" );
						} else {
							$sqlorder .= sanitize_sql_orderby( $_order[ $i ] );
						}
					}
				}

				if ( ! is_numeric( $rows ) ) {
					$rows = 10;
				}
				$offset = ( $page - 1 ) * $rows; // Calculate offset.
				if ( ! is_numeric( $offset ) ) {
					$offset = 0;
				}

				// Query.
				$dataset = $wpdadb->get_results(
					$wpdadb->prepare(
						"
                        select *
                        from `%1s`
                        {$where}
                        {$sqlorder}
                        limit {$rows} offset {$offset}
                    ",
						array(
							$table_name,
						)
					),
					'ARRAY_A'
				);

				if ( $wpdadb->last_error ) {
					// Handle SQL errors.
					return new \WP_Error( 'error', $wpdadb->last_error, array( 'status' => 420 ) );
				}

				// Count rows.
				$countrows = $wpdadb->get_results(
					$wpdadb->prepare(
						'
                        select count(1) as rowcount
                        from `%1s`
                    ',
						array(
							$table_name,
						)
					),
					'ARRAY_A'
				);

				if ( $wpdadb->last_error ) {
					// Handle SQL errors.
					return new \WP_Error( 'error', $wpdadb->last_error, array( 'status' => 420 ) );
				}

				$rowcount  = isset( $countrows[0]['rowcount'] ) ? $countrows[0]['rowcount'] : 0;
				$pagecount = floor( $rowcount / $rows );
				if ( $pagecount != $rowcount / $rows ) { // phpcs:ignore WordPress.PHP.StrictComparisons
					$pagecount++;
				}

				$wpdadb->suppress_errors( $suppress );

				// Send response.
				$response = WPDA_API::WPDA_Rest_Response( '', $dataset );
				$response->header( 'X-WP-Total', $rowcount ); // Total rows for this query.
				$response->header( 'X-WP-TotalPages', $pagecount ); // Pages for this query.
				return $response;
			}
		}

		/**
		 * Get table meta data.
		 *
		 * @param string $schema_name Database schema name.
		 * @param string $table_name Database table name.
		 * @return array\object
		 */
		public static function get_table_meta_data( $schema_name, $table_name ) {
			$wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );

			return [
				'columns'      => $wpda_list_columns->get_table_columns(),
				'table_labels' => $wpda_list_columns->get_table_header_labels(),
				'form_labels'  => $wpda_list_columns->get_table_column_headers(),
				'primary_key'  => $wpda_list_columns->get_table_primary_key(),
			];
		}

		/**
		 * Check if access is grant for requested database/table.
		 *
		 * @param string $dbs Remote or local database connection string.
		 * @param string $tbl Database table name.
		 * @param string $action Possible values: select, insert, update, delete.
		 * @return bool
		 */
		public static function check_table_access( $dbs, $tbl, $request, $action ) {
			$tables = get_option( WPDA_API::WPDA_REST_API_TABLE_ACCESS );
			if ( false === $tables ) {
				// No tables.
				return false;
			}

			if (
				! (
					isset( $tables[ $dbs ][ $tbl ][ $action ]['methods'] ) &&
					is_array( $tables[ $dbs ][ $tbl ][ $action ]['methods'] )
				)
			) {
				// No methods.
				return false;
			} else {
				if ( ! in_array( $request->get_method(), $tables[ $dbs ][ $tbl ][ $action ]['methods'] ) ) {//phpcs:ignore - 8.1 proof
					return false;
				}
			}

			if ( ! isset( $tables[ $dbs ][ $tbl ][ $action ]['authorization'] ) ) {
				// No authorization.
				return false;
			} else {
				if ( 'anonymous' === $tables[ $dbs ][ $tbl ][ $action ]['authorization'] ) {
					// Access granted to all users.
					return true;
				}
			}

			global $wp_rest_auth_cookie;
			if ( true !== $wp_rest_auth_cookie ) {
				// No anonymous access.
				return false;
			} else {
				if ( 'authorized' !== $tables[ $dbs ][ $tbl ][ $action ]['authorization'] ) {
					// Authorization check.
					return false;
				}

				// Authorized access requires a valid nonce.
				if ( ! wp_verify_nonce( $request->get_header('X-WP-Nonce'), 'wp_rest' ) ) {
					return false;
				}

				if (
					! (
						isset( $tables[ $dbs ][ $tbl ][ $action ]['authorized_roles'] ) &&
						is_array( $tables[ $dbs ][ $tbl ][ $action ]['authorized_roles'] )
					)
				) {
					// No Roles.
					return false;
				} else {
					$requesting_user_roles = WPDA::get_current_user_roles();
					if ( false === $requesting_user_roles ) {
						$requesting_user_roles = array();
					}

					if (
						0 < count( $tables[ $dbs ][ $tbl ][ $action ]['authorized_roles'] ) &&//phpcs:ignore - 8.1 proof
						0 < count( array_intersect( $requesting_user_roles, $tables[ $dbs ][ $tbl ][ $action ]['authorized_roles'] ) ) //phpcs:ignore - 8.1 proof
					) {
						return true;
					}
				}

				if (
					! (
						isset( $tables[ $dbs ][ $tbl ][ $action ]['authorized_users'] ) &&
						is_array( $tables[ $dbs ][ $tbl ][ $action ]['authorized_users'] )
					)
				) {
					// No methods.
					return false;
				} else {
					$requesting_user_login = WPDA::get_current_user_login();

					if (
						0 < count( $tables[ $dbs ][ $tbl ][ $action ]['authorized_users'] ) && //phpcs:ignore - 8.1 proof
						in_array( $requesting_user_login, $tables[ $dbs ][ $tbl ][ $action ]['authorized_users'] )//phpcs:ignore - 8.1 proof
					) {
						return true;
					}
				}

				return false;
			}
		}

	}

}
