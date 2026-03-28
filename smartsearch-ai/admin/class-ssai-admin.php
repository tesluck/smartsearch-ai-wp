<?php
/**
 * SmartSearch AI Admin Class
 *
 * Handles the plugin's admin settings and interface.
 *
 * @package SmartSearch_AI
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SSAI_Admin class for managing plugin settings and admin interface.
 *
 * @since 1.0.0
 */
class SSAI_Admin {

	/**
	 * Initialize the admin class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'plugin_action_links_' . SSAI_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
		add_action( 'wp_ajax_ssai_test_openai', array( $this, 'handle_test_openai' ) );
		add_action( 'wp_ajax_ssai_export_dictionary', array( $this, 'handle_export_dictionary' ) );
		add_action( 'wp_ajax_ssai_import_dictionary', array( $this, 'handle_import_dictionary' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer_credit' ) );
	}

	/**
	 * Register the admin page under Settings.
	 *
	 * @since 1.0.0
	 */
	public function register_admin_page() {
		add_options_page(
			__( 'SmartSearch AI', 'smartsearch-ai' ),
			__( 'SmartSearch AI', 'smartsearch-ai' ),
			'manage_options',
			'smartsearch-ai',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings and sanitization callbacks.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// General Settings
		register_setting(
			'ssai_general',
			'ssai_placeholder_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Search...', 'smartsearch-ai' ),
			)
		);

		register_setting(
			'ssai_general',
			'ssai_no_results_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'No results found', 'smartsearch-ai' ),
			)
		);

		register_setting(
			'ssai_general',
			'ssai_min_chars',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 2,
			)
		);

		register_setting(
			'ssai_general',
			'ssai_max_suggestions',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 8,
			)
		);

		register_setting(
			'ssai_general',
			'ssai_post_types',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_array' ),
				'default'           => array( 'post', 'page' ),
			)
		);

		register_setting(
			'ssai_general',
			'ssai_taxonomy',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'category',
			)
		);

		register_setting(
			'ssai_general',
			'ssai_location_taxonomy',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Dictionary Settings
		register_setting(
			'ssai_dictionary',
			'ssai_active_dictionary',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'default',
			)
		);

		// AI Fallback Settings
		register_setting(
			'ssai_ai_fallback',
			'ssai_ai_fallback',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);

		register_setting(
			'ssai_ai_fallback',
			'ssai_openai_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'ssai_ai_fallback',
			'ssai_openai_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'gpt-4o-mini',
			)
		);
	}

	/**
	 * Add settings link to plugin action links.
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified plugin action links.
	 * @since 1.0.0
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=smartsearch-ai' ) ),
			esc_html__( 'Settings', 'smartsearch-ai' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render the settings page with tabs.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smartsearch-ai' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$tabs        = array(
			'general'     => __( 'General', 'smartsearch-ai' ),
			'dictionary'  => __( 'Dictionary', 'smartsearch-ai' ),
			'ai_fallback' => __( 'AI Fallback', 'smartsearch-ai' ),
			'usage'       => __( 'Usage', 'smartsearch-ai' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SmartSearch AI Settings', 'smartsearch-ai' ); ?></h1>

			<nav class="nav-tab-wrapper wp-clearfix" role="tablist">
				<?php
				foreach ( $tabs as $tab_key => $tab_label ) {
					$tab_url  = esc_url(
						add_query_arg(
							array(
								'page' => 'smartsearch-ai',
								'tab'  => $tab_key,
							),
							admin_url( 'options-general.php' )
						)
					);
					$class    = ( $active_tab === $tab_key ) ? ' nav-tab-active' : '';
					$aria_sel = ( $active_tab === $tab_key ) ? ' aria-selected="true"' : '';
					?>
					<a href="<?php echo $tab_url; ?>" class="nav-tab<?php echo esc_attr( $class ); ?>" role="tab"<?php echo $aria_sel; ?>>
						<?php echo esc_html( $tab_label ); ?>
					</a>
					<?php
				}
				?>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'general':
						$this->render_general_tab();
						break;
					case 'dictionary':
						$this->render_dictionary_tab();
						break;
					case 'ai_fallback':
						$this->render_ai_fallback_tab();
						break;
					case 'usage':
						$this->render_usage_tab();
						break;
					default:
						$this->render_general_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the General settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_general_tab() {
		?>
		<form method="post" action="options.php" class="ssai-settings-form">
			<?php
			settings_fields( 'ssai_general' );
			do_settings_sections( 'ssai_general' );
			?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ssai_placeholder_text">
							<?php echo esc_html__( 'Search Placeholder Text', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="ssai_placeholder_text"
							name="ssai_placeholder_text"
							value="<?php echo esc_attr( get_option( 'ssai_placeholder_text' ) ); ?>"
							class="regular-text"
						/>
						<p class="description">
							<?php echo esc_html__( 'Placeholder text shown in the search input.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ssai_no_results_text">
							<?php echo esc_html__( 'No Results Text', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="ssai_no_results_text"
							name="ssai_no_results_text"
							value="<?php echo esc_attr( get_option( 'ssai_no_results_text' ) ); ?>"
							class="regular-text"
						/>
						<p class="description">
							<?php echo esc_html__( 'Message displayed when no results are found.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ssai_min_chars">
							<?php echo esc_html__( 'Minimum Characters', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="ssai_min_chars"
							name="ssai_min_chars"
							value="<?php echo esc_attr( get_option( 'ssai_min_chars' ) ); ?>"
							min="1"
							max="10"
							class="small-text"
						/>
						<p class="description">
							<?php echo esc_html__( 'Minimum characters before search starts (1-10).', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ssai_max_suggestions">
							<?php echo esc_html__( 'Maximum Suggestions', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="ssai_max_suggestions"
							name="ssai_max_suggestions"
							value="<?php echo esc_attr( get_option( 'ssai_max_suggestions' ) ); ?>"
							min="1"
							max="50"
							class="small-text"
						/>
						<p class="description">
							<?php echo esc_html__( 'Maximum number of search suggestions to display.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php echo esc_html__( 'Post Types', 'smartsearch-ai' ); ?>
					</th>
					<td>
						<?php
						$post_types = get_post_types( array( 'public' => true ), 'objects' );
						$selected   = get_option( 'ssai_post_types', array( 'post', 'page' ) );
						foreach ( $post_types as $post_type ) {
							$checked = in_array( $post_type->name, $selected, true ) ? 'checked' : '';
							?>
							<label>
								<input
									type="checkbox"
									name="ssai_post_types[]"
									value="<?php echo esc_attr( $post_type->name ); ?>"
									<?php echo $checked; ?>
								/>
								<?php echo esc_html( $post_type->label ); ?>
							</label><br />
							<?php
						}
						?>
						<p class="description">
							<?php echo esc_html__( 'Select which post types to include in search.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ssai_taxonomy">
							<?php echo esc_html__( 'Taxonomy', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<select id="ssai_taxonomy" name="ssai_taxonomy" class="regular-text">
							<?php
							$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
							$selected   = get_option( 'ssai_taxonomy' );
							foreach ( $taxonomies as $taxonomy ) {
								?>
								<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $selected, $taxonomy->name ); ?>>
									<?php echo esc_html( $taxonomy->label ); ?>
								</option>
								<?php
							}
							?>
						</select>
						<p class="description">
							<?php echo esc_html__( 'Primary taxonomy for search results.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ssai_location_taxonomy">
							<?php echo esc_html__( 'Location Taxonomy', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<select id="ssai_location_taxonomy" name="ssai_location_taxonomy" class="regular-text">
							<option value=""><?php echo esc_html__( 'None', 'smartsearch-ai' ); ?></option>
							<?php
							$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
							$selected   = get_option( 'ssai_location_taxonomy' );
							foreach ( $taxonomies as $taxonomy ) {
								?>
								<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $selected, $taxonomy->name ); ?>>
									<?php echo esc_html( $taxonomy->label ); ?>
								</option>
								<?php
							}
							?>
						</select>
						<p class="description">
							<?php echo esc_html__( 'Optional taxonomy for location-based filtering.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the Dictionary settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_dictionary_tab() {
		?>
		<div class="ssai-dictionary-settings">
			<h2><?php echo esc_html__( 'Search Dictionary Management', 'smartsearch-ai' ); ?></h2>

			<form method="post" action="options.php" class="ssai-settings-form">
				<?php
				settings_fields( 'ssai_dictionary' );
				do_settings_sections( 'ssai_dictionary' );
				?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ssai_active_dictionary">
								<?php echo esc_html__( 'Active Dictionary', 'smartsearch-ai' ); ?>
							</label>
						</th>
						<td>
							<select id="ssai_active_dictionary" name="ssai_active_dictionary" class="regular-text">
								<option value="default" <?php selected( get_option( 'ssai_active_dictionary' ), 'default' ); ?>>
									<?php echo esc_html__( 'Default', 'smartsearch-ai' ); ?>
								</option>
								<?php
								$dictionaries = apply_filters( 'ssai_available_dictionaries', array() );
								foreach ( $dictionaries as $dict_id => $dict_name ) {
									?>
									<option value="<?php echo esc_attr( $dict_id ); ?>" <?php selected( get_option( 'ssai_active_dictionary' ), $dict_id ); ?>>
										<?php echo esc_html( $dict_name ); ?>
									</option>
									<?php
								}
								?>
							</select>
							<p class="description">
								<?php echo esc_html__( 'Choose which search dictionary to use.', 'smartsearch-ai' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<div class="ssai-dictionary-list">
				<h3><?php echo esc_html__( 'Dictionary Services', 'smartsearch-ai' ); ?></h3>
				<?php $this->render_dictionary_list(); ?>
			</div>

			<div class="ssai-dictionary-actions">
				<h3><?php echo esc_html__( 'Import / Export', 'smartsearch-ai' ); ?></h3>
				<p>
					<button type="button" class="button button-secondary" id="ssai-export-dict-btn">
						<?php echo esc_html__( 'Export Dictionary', 'smartsearch-ai' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="ssai-import-dict-btn">
						<?php echo esc_html__( 'Import Dictionary', 'smartsearch-ai' ); ?>
					</button>
					<input type="file" id="ssai-import-dict-file" style="display:none;" accept=".json" />
				</p>
			</div>

			<script>
				jQuery(function($) {
					const nonce = '<?php echo esc_js( wp_create_nonce( 'ssai_dictionary_action' ) ); ?>';

					$('#ssai-export-dict-btn').on('click', function() {
						const $btn = $(this);
						$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Exporting...', 'smartsearch-ai' ) ); ?>');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'ssai_export_dictionary',
								nonce: nonce,
							},
							success: function(response) {
								if (response.success && response.data.file) {
									const a = document.createElement('a');
									a.href = response.data.file;
									a.download = 'smartsearch-ai-dictionary.json';
									document.body.appendChild(a);
									a.click();
									document.body.removeChild(a);
								}
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Export Dictionary', 'smartsearch-ai' ) ); ?>');
							},
							error: function() {
								alert('<?php echo esc_js( __( 'Export failed.', 'smartsearch-ai' ) ); ?>');
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Export Dictionary', 'smartsearch-ai' ) ); ?>');
							}
						});
					});

					$('#ssai-import-dict-btn').on('click', function() {
						$('#ssai-import-dict-file').click();
					});

					$('#ssai-import-dict-file').on('change', function(e) {
						const file = e.target.files[0];
						if (!file) return;

						const formData = new FormData();
						formData.append('action', 'ssai_import_dictionary');
						formData.append('nonce', nonce);
						formData.append('file', file);

						const $btn = $('#ssai-import-dict-btn');
						$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Importing...', 'smartsearch-ai' ) ); ?>');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: formData,
							processData: false,
							contentType: false,
							success: function(response) {
								if (response.success) {
									alert('<?php echo esc_js( __( 'Dictionary imported successfully.', 'smartsearch-ai' ) ); ?>');
									location.reload();
								} else {
									alert(response.data || '<?php echo esc_js( __( 'Import failed.', 'smartsearch-ai' ) ); ?>');
								}
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Import Dictionary', 'smartsearch-ai' ) ); ?>');
							},
							error: function() {
								alert('<?php echo esc_js( __( 'Import failed.', 'smartsearch-ai' ) ); ?>');
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Import Dictionary', 'smartsearch-ai' ) ); ?>');
							}
						});

						// Reset file input
						$(this).val('');
					});
				});
			</script>
		</div>
		<?php
	}

	/**
	 * Render the dictionary list.
	 *
	 * @since 1.0.0
	 */
	private function render_dictionary_list() {
		$dictionary = new SSAI_Dictionary();
		$services   = $dictionary->get_services();

		if ( empty( $services ) ) {
			echo '<p>' . esc_html__( 'No services found in the current dictionary.', 'smartsearch-ai' ) . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Service Name', 'smartsearch-ai' ); ?></th>
					<th><?php echo esc_html__( 'Category', 'smartsearch-ai' ); ?></th>
					<th><?php echo esc_html__( 'Total Items', 'smartsearch-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $services as $service ) {
					?>
					<tr>
						<td><?php echo esc_html( $service['name'] ); ?></td>
						<td><?php echo esc_html( $service['category'] ?? 'N/A' ); ?></td>
						<td><?php echo absint( $service['count'] ?? 0 ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the AI Fallback settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_ai_fallback_tab() {
		$is_pro = SmartSearch_AI::is_pro();

		if ( ! $is_pro ) {
			$this->render_upgrade_banner();
		}

		?>
		<form method="post" action="options.php" class="ssai-settings-form">
			<?php
			settings_fields( 'ssai_ai_fallback' );
			do_settings_sections( 'ssai_ai_fallback' );
			?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ssai_ai_fallback">
							<?php echo esc_html__( 'Enable AI Fallback', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<input
							type="checkbox"
							id="ssai_ai_fallback"
							name="ssai_ai_fallback"
							value="1"
							<?php checked( get_option( 'ssai_ai_fallback' ), 1 ); ?>
							<?php disabled( ! $is_pro ); ?>
						/>
						<p class="description">
							<?php echo esc_html__( 'Use OpenAI when search dictionary returns no results.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ssai_openai_api_key">
							<?php echo esc_html__( 'OpenAI API Key', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="ssai_openai_api_key"
							name="ssai_openai_api_key"
							value="<?php echo esc_attr( get_option( 'ssai_openai_api_key' ) ); ?>"
							class="regular-text"
							<?php disabled( ! $is_pro ); ?>
						/>
						<p class="description">
							<?php echo esc_html__( 'Your OpenAI API key for fallback searches. Keep this secure.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ssai_openai_model">
							<?php echo esc_html__( 'OpenAI Model', 'smartsearch-ai' ); ?>
						</label>
					</th>
					<td>
						<select id="ssai_openai_model" name="ssai_openai_model" <?php disabled( ! $is_pro ); ?>>
							<option value="gpt-4o-mini" <?php selected( get_option( 'ssai_openai_model' ), 'gpt-4o-mini' ); ?>>
								GPT-4o Mini (faster, cheaper)
							</option>
							<option value="gpt-4o" <?php selected( get_option( 'ssai_openai_model' ), 'gpt-4o' ); ?>>
								GPT-4o (more capable)
							</option>
						</select>
						<p class="description">
							<?php echo esc_html__( 'Choose which OpenAI model to use for fallback searches.', 'smartsearch-ai' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php echo esc_html__( 'Test Connection', 'smartsearch-ai' ); ?>
					</th>
					<td>
						<button type="button" class="button button-secondary" id="ssai-test-openai-btn" <?php disabled( ! $is_pro ); ?>>
							<?php echo esc_html__( 'Test OpenAI Connection', 'smartsearch-ai' ); ?>
						</button>
						<span id="ssai-test-result" style="margin-left: 10px; display:none;"></span>
					</td>
				</tr>
			</table>

			<?php if ( $is_pro ) : ?>
				<?php submit_button(); ?>
			<?php endif; ?>
		</form>

		<script>
			jQuery(function($) {
				const nonce = '<?php echo esc_js( wp_create_nonce( 'ssai_test_openai' ) ); ?>';
				const $btn = $('#ssai-test-openai-btn');
				const $result = $('#ssai-test-result');

				$btn.on('click', function() {
					$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'smartsearch-ai' ) ); ?>');
					$result.hide();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'ssai_test_openai',
							nonce: nonce,
						},
						success: function(response) {
							if (response.success) {
								$result.html('<span style="color:green;">✓ <?php echo esc_js( __( 'Connection successful!', 'smartsearch-ai' ) ); ?></span>');
							} else {
								$result.html('<span style="color:red;">✗ ' + response.data + '</span>');
							}
							$result.show();
						},
						error: function() {
							$result.html('<span style="color:red;">✗ <?php echo esc_js( __( 'Connection failed.', 'smartsearch-ai' ) ); ?></span>');
							$result.show();
						},
						complete: function() {
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test OpenAI Connection', 'smartsearch-ai' ) ); ?>');
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Render the upgrade banner for non-pro users.
	 *
	 * @since 1.0.0
	 */
	private function render_upgrade_banner() {
		?>
		<div class="notice notice-info" style="padding: 20px; margin: 20px 0; border-left-color: #0073aa;">
			<p>
				<strong><?php echo esc_html__( 'AI Fallback is a Pro Feature', 'smartsearch-ai' ); ?></strong>
			</p>
			<p>
				<?php echo esc_html__( 'Enhance your search experience by enabling AI-powered fallback suggestions when your search dictionary doesn\'t return results. This Pro feature uses OpenAI\'s powerful models to provide intelligent, contextual search suggestions.', 'smartsearch-ai' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( SmartSearch_AI::pro_url( 'ai-tab' ) ); ?>" class="button button-primary">
					<?php echo esc_html__( 'Upgrade to Pro', 'smartsearch-ai' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the Usage reference tab.
	 *
	 * @since 1.0.0
	 */
	private function render_usage_tab() {
		?>
		<div class="ssai-usage-reference">
			<h2><?php echo esc_html__( 'Usage Documentation', 'smartsearch-ai' ); ?></h2>

			<div style="max-width: 900px;">
				<h3><?php echo esc_html__( 'Shortcode Usage', 'smartsearch-ai' ); ?></h3>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Shortcode', 'smartsearch-ai' ); ?></th>
							<th><?php echo esc_html__( 'Parameters', 'smartsearch-ai' ); ?></th>
							<th><?php echo esc_html__( 'Example', 'smartsearch-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>[smartsearch_ai]</code></td>
							<td>
								<code>placeholder</code>, <code>post_types</code>, <code>taxonomy</code>,
								<code>max_suggestions</code>
							</td>
							<td>
								<code class="block">[smartsearch_ai<br />
								&nbsp;&nbsp;placeholder="Search posts..."<br />
								&nbsp;&nbsp;post_types="post,page"<br />
								&nbsp;&nbsp;max_suggestions="10"<br />
								]</code>
							</td>
						</tr>
					</tbody>
				</table>

				<h3 style="margin-top: 30px;"><?php echo esc_html__( 'PHP Template Usage', 'smartsearch-ai' ); ?></h3>
				<pre><code class="language-php"><?php echo esc_html( '<?php echo do_shortcode( "[smartsearch_ai placeholder=\"Search...\"]" ); ?>' ); ?></code></pre>

				<h3 style="margin-top: 30px;"><?php echo esc_html__( 'Available Hooks & Filters', 'smartsearch-ai' ); ?></h3>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Hook Type', 'smartsearch-ai' ); ?></th>
							<th><?php echo esc_html__( 'Hook Name', 'smartsearch-ai' ); ?></th>
							<th><?php echo esc_html__( 'Description', 'smartsearch-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Filter</strong></td>
							<td><code>ssai_search_results</code></td>
							<td><?php echo esc_html__( 'Filter search results before returning', 'smartsearch-ai' ); ?></td>
						</tr>
						<tr>
							<td><strong>Filter</strong></td>
							<td><code>ssai_search_query</code></td>
							<td><?php echo esc_html__( 'Filter the search query before processing', 'smartsearch-ai' ); ?></td>
						</tr>
						<tr>
							<td><strong>Action</strong></td>
							<td><code>ssai_before_search</code></td>
							<td><?php echo esc_html__( 'Hook before search is performed', 'smartsearch-ai' ); ?></td>
						</tr>
						<tr>
							<td><strong>Action</strong></td>
							<td><code>ssai_after_search</code></td>
							<td><?php echo esc_html__( 'Hook after search is performed', 'smartsearch-ai' ); ?></td>
						</tr>
						<tr>
							<td><strong>Filter</strong></td>
							<td><code>ssai_available_dictionaries</code></td>
							<td><?php echo esc_html__( 'Filter available search dictionaries', 'smartsearch-ai' ); ?></td>
						</tr>
						<tr>
							<td><strong>Filter</strong></td>
							<td><code>ssai_openai_request_args</code></td>
							<td><?php echo esc_html__( 'Filter OpenAI API request arguments', 'smartsearch-ai' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h3 style="margin-top: 30px;"><?php echo esc_html__( 'Code Examples', 'smartsearch-ai' ); ?></h3>
				<pre><code class="language-php"><?php echo esc_html(
					"// Filter search results\n" .
					"add_filter( 'ssai_search_results', function( \$results ) {\n" .
					"    // Your custom filtering logic\n" .
					"    return \$results;\n" .
					"} );\n\n" .
					"// Filter search query\n" .
					"add_filter( 'ssai_search_query', function( \$query ) {\n" .
					"    \$query = sanitize_text_field( \$query );\n" .
					"    return strtolower( \$query );\n" .
					"} );"
				); ?></code></pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX test OpenAI connection.
	 *
	 * @since 1.0.0
	 */
	public function handle_test_openai() {
		check_ajax_referer( 'ssai_test_openai' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'smartsearch-ai' ) );
		}

		if ( ! SmartSearch_AI::is_pro() ) {
			wp_send_json_error( __( 'This feature is only available in the Pro version.', 'smartsearch-ai' ) );
		}

		$api_key = get_option( 'ssai_openai_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'OpenAI API key is not configured.', 'smartsearch-ai' ) );
		}

		$openai = new SSAI_OpenAI( $api_key );
		$result = $openai->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( __( 'Connection successful!', 'smartsearch-ai' ) );
		} else {
			wp_send_json_error( $result['error'] ?? __( 'Connection failed.', 'smartsearch-ai' ) );
		}
	}

	/**
	 * Handle AJAX export dictionary.
	 *
	 * @since 1.0.0
	 */
	public function handle_export_dictionary() {
		check_ajax_referer( 'ssai_dictionary_action' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'smartsearch-ai' ) );
		}

		$dictionary = new SSAI_Dictionary();
		$data       = $dictionary->export();

		if ( empty( $data ) ) {
			wp_send_json_error( __( 'No data to export.', 'smartsearch-ai' ) );
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/smartsearch-ai-exports/';

		if ( ! is_dir( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$filename = 'smartsearch-ai-dictionary-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
		$filepath = $export_dir . $filename;

		$result = file_put_contents( $filepath, wp_json_encode( $data ) );

		if ( false === $result ) {
			wp_send_json_error( __( 'Failed to write export file.', 'smartsearch-ai' ) );
		}

		$file_url = $upload_dir['baseurl'] . '/smartsearch-ai-exports/' . $filename;
		wp_send_json_success( array( 'file' => $file_url ) );
	}

	/**
	 * Handle AJAX import dictionary.
	 *
	 * @since 1.0.0
	 */
	public function handle_import_dictionary() {
		check_ajax_referer( 'ssai_dictionary_action' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'smartsearch-ai' ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'smartsearch-ai' ) );
		}

		$file = $_FILES['file'];

		// Validate file type.
		if ( 'application/json' !== $file['type'] && ! str_ends_with( $file['name'], '.json' ) ) {
			wp_send_json_error( __( 'Invalid file type. Please upload a JSON file.', 'smartsearch-ai' ) );
		}

		// Read file contents.
		$contents = file_get_contents( $file['tmp_name'] );
		$data     = json_decode( $contents, true );

		if ( null === $data || ! is_array( $data ) ) {
			wp_send_json_error( __( 'Invalid JSON file.', 'smartsearch-ai' ) );
		}

		// Import the data.
		$dictionary = new SSAI_Dictionary();
		$result     = $dictionary->import( $data );

		if ( $result ) {
			// Clear search index cache.
			delete_transient( 'ssai_search_index' );
			wp_send_json_success( __( 'Dictionary imported successfully.', 'smartsearch-ai' ) );
		} else {
			wp_send_json_error( __( 'Failed to import dictionary.', 'smartsearch-ai' ) );
		}
	}

	/**
	 * Render admin footer credit.
	 *
	 * @since 1.0.0
	 */
	public function admin_footer_credit() {
		$screen = get_current_screen();

		if ( 'settings_page_smartsearch-ai' !== $screen->id ) {
			return;
		}

		$pro_url = SmartSearch_AI::pro_url();
		?>
		<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc; color: #666; font-size: 12px;">
			<p>
				<?php esc_html_e( 'Thank you for using SmartSearch AI.', 'smartsearch-ai' ); ?> |
				<a href="https://ko-fi.com/smartsearchai" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Buy us a coffee', 'smartsearch-ai' ); ?>
				</a> |
				<a href="https://wordpress.org/plugins/smartsearch-ai/#reviews" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Rate us', 'smartsearch-ai' ); ?>
				</a> |
				<a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to Pro', 'smartsearch-ai' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Sanitize array option.
	 *
	 * @param array $array Array to sanitize.
	 * @return array Sanitized array.
	 * @since 1.0.0
	 */
	public function sanitize_array( $array ) {
		if ( ! is_array( $array ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $array );
	}

	/**
	 * Sanitize checkbox option.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return boolean Sanitized value.
	 * @since 1.0.0
	 */
	public function sanitize_checkbox( $value ) {
		return ! empty( $value ) ? 1 : 0;
	}
}
