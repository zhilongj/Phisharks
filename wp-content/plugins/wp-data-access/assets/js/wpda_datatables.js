/**
 * Javascript code needed to build tables in WordPress with jQuery DataTables.
 *
 * @author  Peter Schulz
 * @since   1.0.0
 */

if (typeof Object.assign != 'function') {
	// IE
	Object.assign = function(target, varArgs) { // .length of function is 2
		'use strict';
		var to = Object(target);
		for (var index = 1; index < arguments.length; index++) {
			var nextSource = arguments[index];
			if (nextSource != null) { // Skip over if undefined or null
				for (var nextKey in nextSource) {
					// Avoid bugs when hasOwnProperty is shadowed
					if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
						to[nextKey] = nextSource[nextKey];
					}
				}
			}
		}
		return to;
	};
}

function wpda_datatables_ajax_call(
	columnsvar, database, table_name, columns,
	responsive, responsive_popup_title, responsive_type, responsive_icon,
	language, sql_orderby,
	table_options_searching, table_options_ordering, table_options_paging, table_options_advanced,
	pub_id, pub_show_advanced_settings, modal_hyperlinks,
	filter_field_name, filter_field_value,
	nl2br, buttons, read_more, calc_estimate, geo_search, geo_search_options,
	wpnonce, pub_id_seq
) {
	/*
	* display possible values:
	* childrow = user toggled
	* childrowimmediate = show
	* modal = show details in modal window
	*/

	/*
	* type possible values:
	* column = no control element
	* inline = show control element
	*/

	var show_more_text = 'SHOW MORE';
	var end_of_list_text = 'END OF LIST';
	if (
		table_options_advanced !== null &&
		table_options_advanced.show_more_text !== undefined
	) {
		show_more_text = table_options_advanced.show_more_text
	}
	if (
		table_options_advanced !== null &&
		table_options_advanced.end_of_list_text !== undefined
	) {
		end_of_list_text = table_options_advanced.end_of_list_text
	}

	var responsive_control_type = "inline";
	if (responsive_icon !== "yes") {
		responsive_control_type = "column";
	}

	var childrow = {
		details: {
			display: jQuery.fn.dataTable.Responsive.display.childRow,
			renderer: function (api, rowIdx, columns) {
				var data = jQuery.map(
					columns, function (col, i) {
						if (!col.hidden ) {
							return '';
						}
						if (columnsvar[i].className==='wpda_select') {
							return '';
						}
						if (pub_show_advanced_settings==='Never' && modal_hyperlinks.includes(i)) {
							return '';
						}
						if (pub_show_advanced_settings==='If not listed' && modal_hyperlinks.includes(i) && !col.hidden) {
							return '';
						}
						return '<tr class="' + columnsvar[i].className + '">' +
							'<td class="wpda-child-label">' + columnsvar[i].label + '</td>' +
							'<td class="wpda-child-value">' + col.data + '</td>' +
							'</tr>';
					}
				).join( '' );
				var datatable = '<table class="wpda-child-table display dataTable">' + data + '</table>';
				var table     = '<tr><td>' + datatable + '</td></tr>';

				return jQuery( '<table class="wpda-child-expanded"/>' ).append( table );
			},
			type: responsive_control_type
		}
	};

	var childrowimmediate = {
		details: {
			display: jQuery.fn.dataTable.Responsive.display.childRowImmediate,
			renderer: function (api, rowIdx, columns) {
				var data = jQuery.map(
					columns, function (col, i) {
						if (!col.hidden ) {
							return '';
						}
						if (columnsvar[i].className==='wpda_select') {
							return '';
						}
						if (pub_show_advanced_settings==='Never' && modal_hyperlinks.includes(i)) {
							return '';
						}
						if (pub_show_advanced_settings==='If not listed' && modal_hyperlinks.includes(i) && !col.hidden) {
							return '';
						}
						return '<tr class="' + columnsvar[i].className + '">' +
							'<td class="wpda-child-label">' + columnsvar[i].label + '</td>' +
							'<td class="wpda-child-value">' + col.data + '</td>' +
							'</tr>';
					}
				).join( '' );
				var datatable = '<table class="wpda-child-table display dataTable">' + data + '</table>';
				var table     = '<tr><td>' + datatable + '</td></tr>';

				return jQuery( '<table class="wpda-child-expanded"/>' ).append( table );
			},
			type: responsive_control_type
		}
	};

	var modal = {
		details: {
			display: jQuery.fn.dataTable.Responsive.display.modal(
				{
					header: function (row) {
						if (table_options_advanced.css_classes) {
							return "<span class='" + table_options_advanced.css_classes + "'>" + responsive_popup_title + "</span>";
						} else {
							return responsive_popup_title;
						}
					},
					modal: {
						modal: true,
						draggable: false,
						resizable: false,
						width: "fit-content",
						position: {
							my: "left top",
							at: "left bottom"
						}
					}
				}
			),
			renderer: function (api, rowIdx, columns) {
				let data = jQuery.map(
					columns, function (col, i) {
						if (columnsvar[i].className==='wpda_select') {
							return '';
						}
						if (pub_show_advanced_settings==='Never' && modal_hyperlinks.includes(i)) {
							return '';
						}
						if (pub_show_advanced_settings==='If not listed' && modal_hyperlinks.includes(i) && !col.hidden) {
							return '';
						}
						let html =
							`<tr class="${columnsvar[i].className}">
								<td class="wpda-child-label">${columnsvar[i].label}</td>
								<td class="wpda-child-value">${col.data}</td>
							</tr>`;
						return html;
					}
				).join( '' );
				if (table_options_advanced.primary_css_classes!==undefined) {
					cssClasses = table_options_advanced.primary_css_classes;
				} else {
					cssClasses = "";
					if (window.top['wpda_publication_primary_css_classes']!==undefined) {
						// CSS added from style manager.
						cssClasses = window.top['wpda_publication_primary_css_classes'];
					}
				}
				let modalClasses = `wpda-child-table display dataTable ${cssClasses}`;
				let modalButtonClasses = "button dtr-modal-close";
				if (jQueryDataTablesDefaultOptions.wpda_styling==="jqueryui") {
					modalClasses = "ui-widget-content";
					modalButtonClasses = "ui-button fg-button uit-state-default wpda-close-button";
				}
				if (
					table_options_advanced.primary_add_modal_header===true ||
					window.top['wpda_publication_primary_css_classes']!==undefined
				) {
					data =
						`<thead><tr><td>&nbsp;</td><td>&nbsp;</td></tr></tr></thead>
							${data}
						<tfoot><tr><td>&nbsp;</td><td>&nbsp;</td></tr></tr></tfoot>`;
				}
				let datatable = `<table class="${modalClasses}">${data}</table>`;
				let label = "Close";
				if (api.settings()[0]?.oLanguage?.editor?.close!==undefined) {
					label = api.settings()[0]?.oLanguage.editor.close;
				}
				var footer =
					`<tr><td><div class="wpda-modal-footer">
						<input type="button" value="${label}" class="${modalButtonClasses}" onclick="jQuery('.dtr-modal').remove()"/>
					</div></td></tr>`;
				var table =
					`<tr><td>${datatable}</td></tr>${footer}`;

				return jQuery( '<table class="wpda-child-modal ' + cssClasses + '"/>' ).append( table );
			},
			type: responsive_control_type
		}
	};

	var responsive_value = false;
	if (responsive === 'yes') {
		switch (responsive_type) {
			case "modal":
				responsive_value = modal;
				break;
			case "expanded":
				responsive_value = childrowimmediate;
				break;
			default:
				/* collapsed */
				responsive_value = childrow;
		}
	}

	orderby = [];
	if ( sql_orderby != '') {
		sql_orderby.split("|").forEach(function (item) {
			orderby_array = item.split(",");
			orderby.push(orderby_array);
		});
	}

	var jQueryDataTablesUserOptions = {
		searching: table_options_searching,
		ordering: table_options_ordering,
		paging: table_options_paging
	};
	if (!table_options_paging) {
		jQueryDataTablesUserOptions.serverSide = false;
	}

	// Allow user to use more button to load additional rows
	var more_rows = [];
	var more_start = 0;
	var more_limit = 10;
	var more_new = true;

	// Prevent counting on each request to improve performance
	var wpda_paginationButtonPressed = false;

	if (table_options_advanced!==null && table_options_advanced.pageLength!==undefined && !isNaN(table_options_advanced.pageLength)) {
		more_limit = table_options_advanced.pageLength;
	}

	if (
		table_options_advanced!==null &&
		(
			table_options_advanced.wpda_use_estimates_only===true ||
			table_options_advanced.wpda_use_estimates_only==="true"
		)
	) {
		table_options_advanced.pagingType = "simple_numbers";
		jQuery.fn.DataTable.ext.pager.numbers_length = 7;
	}

	var jQueryDataTablesDefaultOptions = {
		responsive: responsive_value,
		processing: true,
		serverSide: true,
		stateSave: false,
		bAutoWidth: false,
		columnDefs: columnsvar,
		order: orderby,
		buttons: buttons,
		searchBuilder: {
			enterSearch: true,
			depthLimit: 2
		},
		ajax: {
			method: "POST",
			url: wpda_publication_vars.wpda_ajaxurl,
			data: function(data, event) {
				data.action ="wpda_datatables";
				data.wpnonce = wpnonce;
				data.pubid = pub_id;
				if (pub_id=='0') {
					// For backward compatibility
					data.wpdasrc = database;
					data.wpdatabs = table_name;
					data.wpdacols = columns;
				}
				data.filter_field_name = filter_field_name;
				data.filter_field_value = filter_field_value;
				data.nl2br = nl2br;
				if (table_options_advanced!==null && table_options_advanced.wpda_use_estimates_only!==undefined) {
					data.wpda_use_estimates_only = table_options_advanced.wpda_use_estimates_only;
				}
				if (read_more==="true") {
					if (more_new) {
						more_start = 0;
					} else {
						more_start += more_limit;
					}
					data.more_start = more_start;
					data.more_limit = more_limit;
				}
				jQuery.each(window.location.search.replace('?','').split('&'), function(index, val) {
					var urlparam = val.split('=');
					if (urlparam.length===2) {
						if (urlparam[0].substring(0, 19) === 'wpda_search_column_') {
							data[urlparam[0]] = decodeURIComponent(urlparam[1]);
						}
					}
				});
				var wpda_post_vars_name = table_name + "_" + pub_id + "_args";
				if (window[wpda_post_vars_name] && Array.isArray(window[wpda_post_vars_name])) {
					for (let i=0; i<window[wpda_post_vars_name].length; i++) {
						for (var key in window[wpda_post_vars_name][i]) {
							if (key.substring(0, 19) === 'wpda_search_column_') {
								data[key] = window[wpda_post_vars_name][i][key];
							}
						}
					}
				}
				// Add GET Params
				var wpda_url_get_params = table_name + "_" + pub_id + "_gets";
				if (window[wpda_url_get_params] && Array.isArray(window[wpda_url_get_params])) {
					for (let i=0; i<window[wpda_url_get_params].length; i++) {
						for (var key in window[wpda_url_get_params][i]) {
							if (!data["get"]) {
								data["get"] = {};
							}
							data["get"][key] = window[wpda_url_get_params][i][key];
						}
					}
				}
				// Add POST Params
				var wpda_url_post_params = table_name + "_" + pub_id + "_posts";
				if (window[wpda_url_post_params] && Array.isArray(window[wpda_url_post_params])) {
					for (let i=0; i<window[wpda_url_post_params].length; i++) {
						for (var key in window[wpda_url_post_params][i]) {
							if (!data["post"]) {
								data["post"] = {};
							}
							data["post"][key] = window[wpda_url_post_params][i][key];
						}
					}
				}
				var function_name = 'wpda_' + table_name + '_advanced_' + pub_id;
				if (typeof window[function_name] === "function") {
					var return_value = window[function_name]();
					if (Array.isArray(return_value)) {
						for (var key in return_value) {
							data[key] = return_value[key];
						}
					}
				}
				if (
					window['detailQuery']!==undefined &&
					Array.isArray(window['detailQuery'])
				) {
					for (let i=0; i<window['detailQuery'].length; i++) {
						if (
							window['detailQuery'][i].key!==undefined &&
							window['detailQuery'][i].val!==undefined
						) {
							data[window['detailQuery'][i].key] = window['detailQuery'][i].val;
						}
					}
					delete window['detailQuery'];
				}
				if (geo_search!=='') {
					if (
						table_options_advanced.wpda_geo &&
						table_options_advanced.wpda_geo.map_location==="user"
					) {
						map_location = "user";
						if ( wpda_user_coords !== null ) {
							initial_lat = wpda_user_coords.latitude;
							initial_lng = wpda_user_coords.longitude;
						} else {
							initial_lat = undefined;
							initial_lng = undefined;
						}
					} else {
						map_location = "fixed";
						initial_lat = geo_search_options.initial_lat;
						initial_lng = geo_search_options.initial_lng;
					}
					radius = geo_search_options.radius;
					if (jQuery("#" + table_name + pub_id + "_georange").val()) {
						radius = jQuery("#" + table_name + pub_id + "_georange").val();
					}
					unit = geo_search_options.unit;
					if (jQuery("#" + table_name + pub_id + "_geounits").val()) {
						unit = jQuery("#" + table_name + pub_id + "_geounits").val();
					}
					geosearch = {
						map_location: map_location,
						initial_lat: initial_lat,
						initial_lng: initial_lng,
						radius: radius,
						unit: unit
					}
					data.geosearch = geosearch;
				}

				// Prevent counting on each request to improve performance
				let table = jQuery("#" + table_name + pub_id + pub_id_seq).DataTable();
				if (table.ajax.json()) {
					data.records_total = table.ajax.json().recordsTotal;
					if (wpda_paginationButtonPressed) {
						data.records_filtered = table.ajax.json().recordsFiltered;
					}
				}
				wpda_paginationButtonPressed = false;

				// console.log(data);
			},
			dataSrc: function(data) {
				if (read_more==="true") {
					if (data.data.length<more_limit) {
						jQuery("#" + table_name + pub_id + "_more_button").prop('disabled', true).html(end_of_list_text);
					} else {
						jQuery("#" + table_name + pub_id + "_more_button").prop('disabled', false).html(show_more_text);
					}
					if (more_new) {
						more_rows = data.data;
					} else {
						more_rows = more_rows.concat(data.data);
					}
					more_new = true;

					return more_rows;
				}

				return data.data;
			}
		},
		infoCallback: function( settings, start, end, max, total, pre ) {
			if (read_more==="true") {
				if (jQueryDataTablesDefaultOptions.wpda_search_more_info) {
					return jQueryDataTablesDefaultOptions
							.wpda_search_more_info
							.replaceAll("_START_", start)
							.replaceAll("_END_", end)
							.replaceAll("_MAX_", max)
							.replaceAll("_TOTAL_", total);
				} else {
					return end + " rows selected (from " + total + " entries)";
				}
			}

			return (calc_estimate === 'true' ? '~' : '') +  pre;
		},
		initComplete: function(settings, json) {
			if (jQueryDataTablesDefaultOptions.wpda_styling==="jqueryui") {
				jQuery("#" + table_name + pub_id + pub_id_seq + "_wrapper .dt-button").removeClass("dt-button").addClass("fg-button ui-button ui-state-default");
				jQuery("#" + table_name + pub_id + pub_id_seq + "_wrapper .wpda_search_textbox").css("background-color", "transparent").css("color", "inherit");
			}
			if (jQueryDataTablesDefaultOptions.wpda_geo) {
				if ( jQueryDataTablesDefaultOptions.wpda_geo.map_location==="user" ) {
					wpda_get_user_location(table_name, pub_id);
				}
				if (
					jQueryDataTablesDefaultOptions.wpda_geo.geo_filter &&
					'after' === jQueryDataTablesDefaultOptions.wpda_geo.geo_filter
				) {
					jQuery("#" + table_name + pub_id + "_filter").append(geo_search);
				} else {
					jQuery("#" + table_name + pub_id + "_filter").prepend(geo_search);
				}
				jQuery("#" + table_name + pub_id + "_wrapper").append(jQuery("#" + table_name + pub_id + "_geocontainer"));
				jQuery("#" + table_name + pub_id + "_georange").on("change", function() {
					update_table(table_name, pub_id);
				});
				jQuery("#" + table_name + pub_id + "_geounits").on("change", function() {
					update_table(table_name, pub_id);
				});
				jQuery("#" + table_name + pub_id + "_geobutton").on("click", function() {
					if (
						jQueryDataTablesDefaultOptions.wpda_geo.map_location==="user" &&
						wpda_user_coords === null
					) {
						alert("Missing user location information");
					} else {
						if (jQuery("#" + table_name + pub_id + "_geocontainer").is(":visible")) {
							jQuery("#" + table_name + pub_id + "_geocontainer").hide();
						} else {
							jQuery("#" + table_name + pub_id + "_geocontainer").css('display', 'inline-block')
						}

						wpdaproGeolocationRefreshMap();
					}
				});
				if (
					jQueryDataTablesDefaultOptions.wpda_geo.map_init &&
					'open' === jQueryDataTablesDefaultOptions.wpda_geo.map_init
				) {
					setTimeout(function(){
						if (jQuery("#" + table_name + pub_id + "_geocontainer").is(":visible")) {
							jQuery("#" + table_name + pub_id + "_geocontainer").hide();
						} else {
							jQuery("#" + table_name + pub_id + "_geocontainer").css('display', 'inline-block')
						}

						wpdaproGeolocationRefreshMap();
					}, 100);
				}
			}

			if (responsive === 'yes') {
				try {
					hiddenColumns = this.api().columns().responsiveHidden();
					for (i=0; i<hiddenColumns.length; i++) {
						if (!hiddenColumns[i]) {
							hide_header_and_footer_of_hidden_column(table_name, pub_id, pub_id_seq, i);
						}
					}
				} catch(err) {
					console.log(err);
				}
			}

			if (
				jQueryDataTablesDefaultOptions.wpda_search_force_enter!==false &&
				jQueryDataTablesDefaultOptions.wpda_search_force_enter!=="false"
			) {
				// Force press enter for global search and search builder
				jQueryDataTablesDefaultOptions.search = {
					return: true
				}
				// Force press enter for column search
				enterKeySearch(settings, jQueryDataTablesDefaultOptions);
			} else {
				// search on each key press
				noEnterKeySearch(settings, jQueryDataTablesDefaultOptions);
			}

			if (jQueryDataTablesDefaultOptions.userInitComplete) {
				jQueryDataTablesDefaultOptions.userInitComplete(settings, json);
			}

			if (jQuery.fn.tooltip) {
				// Add ui tooltip to jdt buttons
				jQuery(".wpda_tooltip").tooltip();
			}

			// Prevent counting on each request to improve performance
			jQuery("#" + table_name + pub_id + pub_id_seq + "_wrapper .dataTables_paginate, #" + table_name + pub_id + "_wrapper .dataTables_length").on("mousedown", "a, select", function () {
				wpda_paginationButtonPressed = true;
			});
			jQuery("#" + table_name + pub_id + pub_id_seq + "_wrapper .dataTables_paginate").on("click", "a", function () {
				wpda_paginationButtonPressed = false;
			});
			jQuery("#" + table_name + pub_id + pub_id_seq + "_wrapper .dataTables_length").on("change", "select", function () {
				wpda_paginationButtonPressed = false;
			});

			if (jQueryDataTablesOptions.wpda_seo_links!==undefined) {
				// Add SEO links
				wpdaTableSeo(settings, json, jQueryDataTablesOptions.wpda_seo_links);
			}
		},
		drawCallback: function(settings) {
			// console.log(settings);

			table = jQuery("#" + table_name + pub_id + pub_id_seq).DataTable();
			paging_start = table.page.info().start;
			paging_length = table.page.info().length;

			if (jQueryDataTablesDefaultOptions.wpda_geo) {
				showmap = true;
				if (
					jQueryDataTablesDefaultOptions.wpda_geo.show_map !== undefined &&
					jQueryDataTablesDefaultOptions.wpda_geo.show_map === false
				) {
					showmap = false;
				}

				if (showmap) {
					get_map_data = true;
					if (
						jQueryDataTablesDefaultOptions.wpda_geo &&
						jQueryDataTablesDefaultOptions.wpda_geo.map_location==="user" &&
						wpda_user_coords === null
					) {
						get_map_data = false;
					}

					if (get_map_data) {
						radius = geo_search_options.radius;
						if (jQuery("#" + table_name + pub_id + "_georange").val()) {
							radius = jQuery("#" + table_name + pub_id + "_georange").val();
						}
						unit = geo_search_options.unit;
						if (jQuery("#" + table_name + pub_id + "_geounits").val()) {
							unit = jQuery("#" + table_name + pub_id + "_geounits").val();
						}

						if (typeof deleteAllMarkers==="function") {
							deleteAllMarkers();
						}

						if (typeof popupClose==="function") {
							popupClose();
						}

						args = {};
						args.start = paging_start;
						args.step = paging_length;
						args.radius = radius;
						args.unit = unit;
						args.msg = settings.json.msg;
						if (
							jQueryDataTablesDefaultOptions.wpda_geo &&
							jQueryDataTablesDefaultOptions.wpda_geo.map_location==="user" &&
							wpda_user_coords !== null
						) {
							args.user_latitude = wpda_user_coords.latitude;
							args.user_longitude = wpda_user_coords.longitude;
						}
						// Global search
						args.search = table.search(this.value);
						filter_args = {}
						// Column search
						var searchBoxes = jQuery("#" + table_name + pub_id + pub_id_seq + "_wrapper .wpda_search_boxes");
						for (var i=0; i<searchBoxes.length; i++) {
							if (jQuery(searchBoxes[i]).val()!=="") {
								filter_args[jQuery(searchBoxes[i]).data("id")] = jQuery(searchBoxes[i]).val();
							}
						}
						// Add Search Panes
						let sp = jQuery("div.dtsp-searchPane");
						let searchPanes = [];
						for (let i=0; i<sp.length; i++) {
							let columnSearch = jQuery(sp[i]).find("table").DataTable().rows({selected: true}).data().toArray();
							for (let j=0; j<columnSearch.length; j++) {
								let columnIndex = jQueryDataTablesDefaultOptions.searchPanes.columns[i];
								let columnName = jQueryDataTablesDefaultOptions.columnDefs[columnIndex].name;
								if (columnSearch[j].filter) {
									searchPanes.push({ "key": columnName, "val": columnSearch[j].filter });
								}
							}
						}
						// Add Search Builder
						let searchBuilder = [];
						if (jQuery(".dtsb-criteria").length>0) {
							let sb = table.searchBuilder.getDetails();
							function updateColumnNames(obj) {
								for (const property in obj) {
									if (obj.criteria!==undefined && Array.isArray(obj.criteria)) {
										for (let i = 0; i < obj.criteria.length; i++) {
											let columnName = jQuery("div.dtsb-searchBuilder select.dtsb-data option:selected:contains(" + obj.criteria[i].data + ")").val();
											if (columnName!==undefined) {
												columnName = jQueryDataTablesDefaultOptions.columnDefs[columnName].name;
												if (columnName!==undefined) {
													obj.criteria[i].origData = columnName;
												}
											}
										}
									}
								}
								return obj;
							}
							searchBuilder = updateColumnNames(sb);
						}

						args.msg = {
							filter_args: filter_args,
							searchPanes: searchPanes,
							searchBuilder: searchBuilder
						};
						// console.log(args);

						if (typeof wpdaproGetGeoData==="function") {
							wpdaproGetGeoData(args);
						}

						if (unit==='mile') {
							jQuery(".wpda_geo_unit").html(" (mi)");
						} else {
							jQuery(".wpda_geo_unit").html(" (km)");
						}

						if (
							jQueryDataTablesDefaultOptions.wpda_geo.geo_marker_column === 0 ||
							(
								jQueryDataTablesDefaultOptions.wpda_geo.geo_marker_column &&
								!isNaN(jQueryDataTablesDefaultOptions.wpda_geo.geo_marker_column)
							)
						) {
							var labelIndex = 0;
							for (var i=0; i<table.rows().data().length; i++) {
								node = jQuery(this.api().cell(i, jQueryDataTablesDefaultOptions.wpda_geo.geo_marker_column).node());
								marker = '<div class="wpda_geo_map_marker_link"><a href="javascript:void(0)" onclick="popupMarker(\'' + String.fromCharCode(65+labelIndex) + '\')">' + String.fromCharCode(65+labelIndex) + '</a></div>';
								node.append(marker);
								labelIndex++;
							}
						}
					}
				}
			}

			if (buttons.length > 0) {
				jQuery("#" + table_name + pub_id + pub_id_seq).find("td").on("click", function(e) {
					if (jQuery(this).hasClass("dtr-control")) {
						table = jQuery("#" + table_name + pub_id + pub_id_seq).DataTable();
						if (!table.responsive.hasHidden()) {
							// Overwrite default icon behaviour
							selectedRows = table.row({selected : true});
							if (selectedRows[0].includes(this._DT_CellIndex.row)) {
								table.row(':eq(' + this._DT_CellIndex.row + ')', { page: 'current' }).deselect();
							} else {
								table.row(':eq(' + this._DT_CellIndex.row + ')', { page: 'current' }).select();
							}
						}
					}
				});
			}

			if (
				jQueryDataTablesDefaultOptions.wpda_use_estimates_only===true ||
				jQueryDataTablesDefaultOptions.wpda_use_estimates_only==="true"
			) {
				if (jQuery("#" + table_name + pub_id + pub_id_seq + "_paginate span a").length>0) {
					// default, compact, jQueryUI
					jQuery("#" + table_name + pub_id + pub_id_seq + "_paginate span a:last-child").hide();
				} else if (jQuery("#" + table_name + pub_id + pub_id_seq + "_paginate ul").length>0) {
					// foundation, bootstrap3, bootstrap4
					jQuery("#" + table_name + pub_id + pub_id_seq + "_paginate ul li:nth-last-child(2)").hide();
				} else {
					// semantic
					jQuery("#" + table_name + pub_id + pub_id_seq + "_paginate a:nth-last-child(2)").hide();
				}
			}

			if (jQueryDataTablesDefaultOptions.userDrawCallback) {
				jQueryDataTablesDefaultOptions.userDrawCallback(settings);
			}

			var fncSetModalWidth = table_name + pub_id + pub_id_seq + "SetModalWidth";
			if (typeof window[fncSetModalWidth] === "function") {
				window[fncSetModalWidth]();
			}
		}
	};

	if ( typeof Object.assign != 'function' ) {
		var jQueryDataTablesOptions = jQueryDataTablesDefaultOptions;
	} else {
		var jQueryDataTablesOptions = Object.assign(jQueryDataTablesDefaultOptions, jQueryDataTablesUserOptions);
	}

	convert_string_to_function(table_options_advanced);
	jQueryDataTablesOptions = Object.assign(jQueryDataTablesOptions, table_options_advanced);

	if (jQueryDataTablesOptions.language==undefined) {
		jQueryDataTablesOptions.language = {};
	}
	jQueryDataTablesOptions.language.url = "https://cdn.datatables.net/plug-ins/1.13.1/i18n/" + language + ".json";

	// console.log(jQueryDataTablesOptions);

	jQuery("#" + table_name + pub_id + pub_id_seq).addClass('wpda-datatable');
	jQuery("#" + table_name + pub_id + pub_id_seq).DataTable(jQueryDataTablesOptions);
	jQuery("#" + table_name + pub_id + pub_id_seq).on("click", "tr", function () {
		// Add actions to open modal
		jQuery(".dtr-modal-display").draggable();
		jQuery(".dtr-modal-display").resizable();
		jQuery("body div.dtr-modal div.dtr-modal-display").css("top", function() {
			var displayHeight = ( jQuery(window).height() - jQuery("body div.dtr-modal div.dtr-modal-display").height() ) / 2;
			if (displayHeight<0) {
				displayHeight = 0; // Just to be sure
			}
			return displayHeight + "px";
		});
	});

	if (jQuery("#" + table_name + pub_id + "_more_button").length>0) {
		// Add load more rows action
		jQuery("#" + table_name + pub_id + "_more_button").on("click", function() {
			more_new = false;
			jQuery("#" + table_name + pub_id + pub_id_seq).DataTable().draw("page");
			let scrolledHeight = jQuery("#" + table_name + pub_id + " tr").height() * 10;
			window.scrollBy(0,scrolledHeight);
		});
	}

	if (window.top['wpda_publication_status']!==undefined) {
		window.top['wpda_publication_status']['completed'] = true;
	}
}

function convert_string_to_function(obj) {
	for (var prop in obj) {
		if (typeof obj[prop]=='string') {
			if (obj[prop].substr(0,8)=='function') {
				fnc = obj[prop];
				delete obj[prop];
				var f = new Function("return " + fnc);
				if (prop==="initComplete") {
					// Plugin users cannot overwrite initComplete
					prop = "userInitComplete";
				}
				if (prop==="drawCallback") {
					// Plugin users cannot overwrite initComplete
					prop = "userDrawCallback";
				}
				obj[prop] = f();
			}
		} else {
			convert_string_to_function(obj[prop]);
		}
	}
}

function hide_header_and_footer_of_hidden_column(table_name, pub_id, pub_id_seq, i) {
	// Hide labels of dynamic hyperlinks and double header rows
	var tr_head0 = jQuery("#" + table_name + pub_id + pub_id_seq).find('thead tr').eq(0);
	tr_head0.find('th').eq(i).hide();
	tr_head0.find('td').eq(i).hide();

	var tr_head1 = jQuery("#" + table_name + pub_id + pub_id_seq).find('thead tr').eq(1);
	tr_head1.find('th').eq(i).hide();
	tr_head1.find('td').eq(i).hide();

	var tr_foot  = jQuery("#" + table_name + pub_id + pub_id_seq).find('tfoot tr').eq(0);
	tr_foot.find('th').eq(i).hide().find('td').eq(i).hide();
}

function post_publication_widget(table_name, pub_id) {
	// responsive data table widgets do not rerender correctly with search box in header
	jQuery("#" + table_name + pub_id + ' thead tr:last-child th').each(function(i) {
		if (jQuery(this).is(":visible")) {
			jQuery("#" + table_name + pub_id + ' thead tr:first-child td:nth-child(' + i + ')').show();
		} else {
			jQuery("#" + table_name + pub_id + ' thead tr:first-child td:nth-child(' + i + ')').hide();
		}
	});
}

function update_table(table_name, pub_id) {
	jQuery("#" + table_name + pub_id).DataTable().draw();
}

var wpda_user_coords = null;
function wpda_get_user_location(table_name, pub_id) {
	if (!navigator.geolocation) {
		alert("Geolocation is not supported by your browser");
	} else {
		navigator.geolocation.getCurrentPosition(
			function(pos) {
				if (pos.coords===undefined) {
					wpda_user_coords = null;
					alert("ERROR - Could not determine user location");
				} else {
					wpda_user_coords = pos.coords;
					update_table(table_name, pub_id);
				}
			},
			function(err) {
				wpda_user_coords = null;
				alert("ERROR - " + err);
			},
			{
				enableHighAccuracy: true,
				timeout: 5000,
				maximumAge: 0
			}
		);
	}
}

/**
 * Author: Charles Godwin
 * @since  4.2.6
 */
function enterKeySearch(oSettings, options) {
	sTableID = "#" + oSettings.sTableId;

	jQuery(sTableID + "_filter input").off();
	jQuery(sTableID + "_filter input").on("keyup", { sTableID: sTableID }, function (e) {
		if (e.keyCode == 13) { /* Enter key */
			jQuery(e.data.sTableID).DataTable().search(this.value).draw();
		}
	});

	jQuery(sTableID + "_wrapper .wpda_search_textbox").off();
	jQuery(sTableID + "_wrapper .wpda_search_textbox").on("keyup", { sTableID: sTableID }, function (e) {
		if (e.keyCode == 13) { /* Enter key */
			if (options.serverSide) {
				jQuery(e.data.sTableID).DataTable().draw();
			} else {
				let idx = jQuery(e.data.sTableID).DataTable().column(jQuery(this).data("id")+":name").index();
				jQuery(e.data.sTableID).DataTable().column(idx).search(this.value).draw();
			}
		}
	});

	searchListbox(sTableID, options);
}

function noEnterKeySearch(oSettings, options) {
	sTableID = "#" + oSettings.sTableId;

	jQuery(sTableID + "_wrapper .wpda_search_textbox").off();
	jQuery(sTableID + "_wrapper .wpda_search_textbox").on("keyup", {sTableID: sTableID}, function (e) {
		if (options.serverSide) {
			jQuery(e.data.sTableID).DataTable().draw();
		} else {
			let idx = jQuery(e.data.sTableID).DataTable().column(jQuery(this).data("id") + ":name").index();
			jQuery(e.data.sTableID).DataTable().column(idx).search(this.value).draw();
		}
	});

	searchListbox(sTableID, options);
}

function searchListbox(sTableID, options) {
	jQuery(sTableID + "_wrapper .wpda_search_listbox").off();
	jQuery(sTableID + "_wrapper .wpda_search_listbox").on("change", { sTableID: sTableID }, function (e) {
		if (options.serverSide) {
			jQuery(e.data.sTableID).DataTable().draw();
		} else {
			let idx = jQuery(e.data.sTableID).DataTable().column(jQuery(this).data("id")+":name").index();
			jQuery(e.data.sTableID).DataTable().column(idx).search('\\b' + this.value + '\\b', true, false).draw();
		}
	});
}