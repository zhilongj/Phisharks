<?php

namespace WPDataAccess\API {

	use WPDataAccess\Plugin_Table_Models\WPDA_Media_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Plugin_Table_Models\WPDA_User_Menus_Model;
	use WPDataAccess\WPDA;

	class WPDA_Settings {

		/**
		 * Save table settings.
		 *
		 * @param $schema_name
		 * @param $table_name
		 * @param $settings
		 * @return \WP_Error|\WP_REST_Response
		 */
		public static function save_table_settings( $schema_name, $table_name, $settings ) {
			if (
				isset( $settings->table_settings->hyperlink_definition ) &&
				isset( $settings->unused )
			) {
				$sql_dml = $settings->unused;

				unset( $settings->request_type );
				unset( $settings->unused );

				global $wpdb;
				if ( 'UPDATE' === $sql_dml ) {
					$settings_db = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
					if ( isset( $settings_db[0]['wpda_table_settings'] ) ) {
						$settings_from_db = json_decode( $settings_db[0]['wpda_table_settings'] );
						foreach ( $settings_from_db as $key => $value ) {
							if ( ! isset( $settings->$key ) ) {
								$settings->$key = $settings_from_db->$key;
							}
						}
					}

					if (
						1 === WPDA_Table_Settings_Model::update( $table_name, json_encode( $settings ), $schema_name ) ||
						(
							'' === $wpdb->last_error &&
							0 === WPDA_Table_Settings_Model::update( $table_name, json_encode( $settings ), $schema_name )
						)
					) {
						return WPDA_API::WPDA_Rest_Response( sprintf( __( 'Saved settings for table `%s`', 'wp-data-access' ), $table_name ) );
					} else {
						return new \WP_Error(
							sprintf( __( 'Cannot save settings for table `%s` [%s]', 'wp-data-access' ), $table_name, $wpdb->last_error ),
							array( 'status' => 420 )
						);
					}
				} else {
					if ( WPDA_Table_Settings_Model::insert( $table_name, json_encode( $settings ), $schema_name ) ) {
						return WPDA_API::WPDA_Rest_Response( sprintf( __( 'Saved settings for table `%s`', 'wp-data-access' ), $table_name ) );
					} else {
						return new \WP_Error(
							sprintf( __( 'Cannot save settings for table `%s` [%s]', 'wp-data-access' ), $table_name, $wpdb->last_error ),
							array( 'status' => 420 )
						);
					}
				}
			} else {
				// Nothing really to save, just to satisfy the user experience.
				return WPDA_API::WPDA_Rest_Response( sprintf( __( 'Saved settings for table `%s`', 'wp-data-access' ), $table_name ) );
			}
		}

		/**
		 * Save column settings.
		 *
		 * @param $schema_name
		 * @param $table_name
		 * @param $settings
		 * @return \WP_Error|\WP_REST_Response
		 */
		public static function save_column_settings( $schema_name, $table_name, $settings ) {
			$sql_dml	   = null;
			$dml_succeeded = 0;
			$dml_failed    = 0;

			if ( isset( $settings->column_media ) ) {
				// Process media columns.
				$settings_column_media = $settings->column_media;
				foreach ( $settings_column_media as $column => $value ) {
					if ( isset( $value->value ) && isset( $value->dml ) ) {
						if ( 'INSERT' === $value->dml ) {
							if ( WPDA_Media_Model::insert( $table_name, $column, $value->value, 'Yes', $schema_name ) ) {
								$dml_succeeded ++;
							} else {
								$dml_failed ++;
							}
						} elseif ( 'UPDATE' === $value->dml ) {
							if ( '' === $value->value ) {
								if ( 1 === WPDA_Media_Model::delete( $table_name, $column, $schema_name ) ) {
									$dml_succeeded ++;
								} else {
									$dml_failed ++;
								}
							} else {
								if ( 1 === WPDA_Media_Model::update( $table_name, $column, $value->value, $schema_name ) ) {
									$dml_succeeded ++;
								} else {
									$dml_failed ++;
								}
							}
						}
					}
				}
				unset( $settings->column_media );
			}

			if ( isset( $settings->unused ) ) {
				$settings_unused = $settings->unused;
				if ( isset( $settings_unused->sql_dml ) ) {
					$sql_dml = $settings_unused->sql_dml;
				}
				unset( $settings->unused );
			}

			if ( null === $sql_dml ) {
				return new \WP_Error(
					sprintf( __( 'Cannot save settings for table `%s` [please contact the plugin development team]', 'wp-data-access' ), $table_name ),
					array( 'status' => 420 )
				);
			} else {
				// Save settings.
				if ( isset( $settings->request_type ) ) {
					unset( $settings->request_type );
				}

				if ( 'UPDATE' === $sql_dml ) {
					$settings_db = WPDA_Table_Settings_Model::query( $table_name, $schema_name );
					if ( isset( $settings_db[0]['wpda_table_settings'] ) ) {
						$settings_from_db = json_decode( $settings_db[0]['wpda_table_settings'] );
						foreach ( $settings_from_db as $key => $value ) {
							if ( ! isset( $settings->$key ) ) {
								$settings->$key = $settings_from_db->$key;
							}
						}
					}
					if ( 1 === WPDA_Table_Settings_Model::update( $table_name, json_encode( $settings ), $schema_name ) ) {
						$dml_succeeded ++;
					}
				} else {
					if ( WPDA_Table_Settings_Model::insert( $table_name, json_encode( $settings ), $schema_name ) ) {
						$dml_succeeded ++;
					} else {
						$dml_failed ++;
					}
				}
			}

			if ( $dml_succeeded >= 0 && $dml_failed === 0 ) {
				return WPDA_API::WPDA_Rest_Response( sprintf( __( 'Saved settings for table `%s`', 'wp-data-access' ), $table_name ) );
			} else {
				global $wpdb;
				$msg = '' !== $wpdb->last_error ? " [{$wpdb->last_error}]" : '';

				return new \WP_Error(
					sprintf( __( 'Cannot save settings for table `%s`%s', 'wp-data-access' ), $table_name, $msg ),
					array( 'status' => 420 )
				);
			}
		}

		/**
		 * Save dashboard menu changes.
		 *
		 * @param $schema_name
		 * @param $table_name
		 * @param $settings
		 * @return \WP_Error|\WP_REST_Response
		 */
		public static function save_dashboard_menus( $schema_name, $table_name, $settings ) {
			$dml_succeeded = 0;
			$dml_failed    = 0;
			$new_menus     = array();

			if ( isset( $settings->menu ) && is_array( $settings->menu ) ) {
				// Process menu items.
				foreach ( $settings->menu as $menu ) {
					if ( isset( $menu->menu_name ) && isset( $menu->menu_slug ) ) {
						if ( isset( $menu->menu_role ) ) {
							if ( is_array( $menu->menu_role ) ) {
								$menu_role = implode( ',', $menu->menu_role );
							} else {
								$menu_role = $menu->menu_role;
							}
						} else {
							$menu_role = '';
						}
						if ( isset( $menu->menu_id ) && '' === $menu->menu_id ) {
							// Add new menu
							if ( WPDA_User_Menus_Model::insert(
								$table_name,
								$menu->menu_name,
								$menu->menu_slug,
								$menu_role,
								$schema_name
							) ) {
								$dml_succeeded ++;

								global $wpdb;
								$new_menus[] = array(
									'menu_name' => $menu->menu_name,
									'menu_slug' => $menu->menu_slug,
									'menu_id'   => $wpdb->insert_id,
								);
							} else {
								$dml_failed ++;
							}
						} else {
							if ( isset( $menu->menu_id ) ) {
								// Update existing menu.
								$update_result = WPDA_User_Menus_Model::update(
									$menu->menu_id,
									$table_name,
									$menu->menu_name,
									$menu->menu_slug,
									$menu_role,
									$schema_name
								);
								if ( 1 === $update_result ) {
									$dml_succeeded ++;
								}
							}
						}
					}
				}
				unset( $settings->menu );
			}

			if ( isset( $settings->delete ) && is_array( $settings->delete ) ) {
				// Process menu items to be deleted.
				foreach ( $settings->delete as $menu ) {
					if ( 1 === WPDA_User_Menus_Model::delete( $menu ) ) {
						$dml_succeeded++;
					} else {
						$dml_failed++;
					}
				}
				unset( $settings->delete );
			}

			if ( $dml_succeeded >= 0 && $dml_failed === 0 ) {
				return WPDA_API::WPDA_Rest_Response(
					sprintf( __( 'Saved dashboard menus for table `%s`', 'wp-data-access' ), $table_name ),
					$new_menus
				);
			} else {
				global $wpdb;
				$msg = '' !== $wpdb->last_error ? " [{$wpdb->last_error}]" : '';

				return new \WP_Error(
					sprintf( __( 'Cannot save dashboard menus for table `%s`%s', 'wp-data-access' ), $table_name, $msg ),
					array( 'status' => 420 )
				);
			}
		}

		/**
		 * Save rest api settings.
		 *
		 * @param $schema_name
		 * @param $table_name
		 * @param $settings
		 * @return \WP_Error|\WP_REST_Response
		 */
		public static function save_rest_api_settings( $schema_name, $table_name, $settings ) {
			$rest_api_settings = get_option(  WPDA_API::WPDA_REST_API_TABLE_ACCESS );
			if ( false === $rest_api_settings ) {
				$rest_api_settings = array();
			}

			if ( ! isset( $settings->enabled ) ) {
				return new \WP_Error(
					'error',
					__( 'Bad request1', 'wp-data-access' ),
					array( 'status' => 400 )
				);
			}

			if ( true === $settings->enabled ) {
				// Add new REST API rule.
				if (
					isset(
						$settings->select,
						$settings->select->authorization,
						$settings->select->methods,
						$settings->select->authorized_roles,
						$settings->select->authorized_users
					)
				) {
					unset( $rest_api_settings[ $schema_name ][ $table_name ]['select'] ); // Unset previous authorization.
					$rest_api_settings[ $schema_name ][ $table_name ]['select']['authorization'] = $settings->select->authorization;
					$rest_api_settings[ $schema_name ][ $table_name ]['select']['methods'] = $settings->select->methods;
					$rest_api_settings[ $schema_name ][ $table_name ]['select']['authorized_roles'] = $settings->select->authorized_roles;
					$rest_api_settings[ $schema_name ][ $table_name ]['select']['authorized_users'] = $settings->select->authorized_users;
				} else {
					return new \WP_Error(
						'error',
						__( 'Bad request2', 'wp-data-access' ),
						array( 'status' => 400 )
					);
				}
			} elseif ( false === $settings->enabled ) {
				// Remove existing REST API rule.
				if ( isset( $settings->select ) ) {
					unset( $rest_api_settings[ $schema_name ][ $table_name ]['select'] );
					if (
						isset( $rest_api_settings[ $schema_name ][ $table_name ] ) &&
						0 === count( $rest_api_settings[ $schema_name ][ $table_name ] )//phpcs:ignore - 8.1 proof
					) {
						unset( $rest_api_settings[ $schema_name ][ $table_name ] );
						if (
							isset( $rest_api_settings[ $schema_name ] ) &&
							0 === count( $rest_api_settings[ $schema_name ] )//phpcs:ignore - 8.1 proof
						) {
							unset( $rest_api_settings[ $schema_name ] );
						}
					}
				} else {
					return new \WP_Error(
						'error',
						__( 'Bad request3', 'wp-data-access' ),
						array( 'status' => 400 )
					);
				}
			} else {
				return new \WP_Error(
					'error',
					__( 'Bad request4', 'wp-data-access' ),
					array( 'status' => 400 )
				);
			}

			update_option( WPDA_API::WPDA_REST_API_TABLE_ACCESS, $rest_api_settings );

			return WPDA_API::WPDA_Rest_Response(
				sprintf( __( 'Saved REST API settings for table `%s`', 'wp-data-access' ), $table_name )
			);
		}

	}

}
