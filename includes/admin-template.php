<?php
/**
 * Admin page template for Shortcode Exec PHP.
 *
 * This template renders the admin interface with proper security measures.
 * All output is properly escaped to prevent XSS vulnerabilities.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure variables are available.
if ( ! isset( $message, $shortcodes ) ) {
	return;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php
	// Display message if present.
	if ( ! empty( $message ) ) {
		// Message is already escaped in the calling method.
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>

	<div id="scep-admin-panel">
		<form method="post" action="">
			<?php wp_nonce_field( 'scep_admin_action', 'scep_admin_nonce' ); ?>
			<input type="hidden" name="scep_action" value="save_shortcode" />

			<h2><?php esc_html_e( 'Add/Edit Shortcode', 'shortcode-exec-php' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="scep-shortcode-name"><?php esc_html_e( 'Shortcode Name', 'shortcode-exec-php' ); ?></label>
					</th>
					<td>
						<input type="text" id="scep-shortcode-name" name="scep_shortcode_name" class="scep-shortcode-name" placeholder="<?php esc_attr_e( 'Enter shortcode name', 'shortcode-exec-php' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Only letters, numbers, underscores, and hyphens allowed. Must not start with a number.', 'shortcode-exec-php' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scep-description"><?php esc_html_e( 'Description', 'shortcode-exec-php' ); ?></label>
					</th>
					<td>
						<input type="text" id="scep-description" name="scep_description" class="scep-shortcode-description" placeholder="<?php esc_attr_e( 'Optional description', 'shortcode-exec-php' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'shortcode-exec-php' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Shortcode Options', 'shortcode-exec-php' ); ?></legend>
							<label for="scep-enabled">
								<input type="checkbox" id="scep-enabled" name="scep_enabled" value="1" checked="checked" />
								<?php esc_html_e( 'Enabled', 'shortcode-exec-php' ); ?>
							</label>
							<br />
							<label for="scep-buffer">
								<input type="checkbox" id="scep-buffer" name="scep_buffer" value="1" />
								<?php esc_html_e( 'Buffer output', 'shortcode-exec-php' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scep-phpcode"><?php esc_html_e( 'PHP Code', 'shortcode-exec-php' ); ?></label>
					</th>
					<td>
						<textarea id="scep-phpcode" name="scep_phpcode" rows="15" cols="80" class="large-text code" placeholder="<?php esc_attr_e( 'Enter PHP code (without <?php tags)', 'shortcode-exec-php' ); ?>"></textarea>
						<p class="description">
							<strong><?php esc_html_e( 'Security Warning:', 'shortcode-exec-php' ); ?></strong>
							<?php esc_html_e( 'This code will be executed on your server. Only add trusted code from reliable sources.', 'shortcode-exec-php' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Shortcode', 'shortcode-exec-php' ); ?></button>
				<button type="button" id="test-shortcode" class="button"><?php esc_html_e( 'Test Shortcode', 'shortcode-exec-php' ); ?></button>
			</p>
		</form>

		<h2><?php esc_html_e( 'Existing Shortcodes', 'shortcode-exec-php' ); ?></h2>

		<?php if ( empty( $shortcodes ) ) : ?>
			<p><?php esc_html_e( 'No shortcodes have been created yet.', 'shortcode-exec-php' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Name', 'shortcode-exec-php' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'shortcode-exec-php' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'shortcode-exec-php' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'shortcode-exec-php' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $shortcodes as $shortcode ) : ?>
						<tr>
							<td>
								<code><?php echo esc_html( $shortcode['name'] ); ?></code>
							</td>
							<td>
								<?php echo esc_html( wp_trim_words( $shortcode['description'], 10 ) ); ?>
							</td>
							<td>
								<?php if ( $shortcode['enabled'] ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: green;" title="<?php esc_attr_e( 'Enabled', 'shortcode-exec-php' ); ?>"></span>
									<?php esc_html_e( 'Enabled', 'shortcode-exec-php' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-dismiss" style="color: red;" title="<?php esc_attr_e( 'Disabled', 'shortcode-exec-php' ); ?>"></span>
									<?php esc_html_e( 'Disabled', 'shortcode-exec-php' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button button-small edit-shortcode" data-shortcode="<?php echo esc_attr( $shortcode['name'] ); ?>">
									<?php esc_html_e( 'Edit', 'shortcode-exec-php' ); ?>
								</button>
								<button type="button" class="button button-small test-shortcode" data-shortcode="<?php echo esc_attr( $shortcode['name'] ); ?>">
									<?php esc_html_e( 'Test', 'shortcode-exec-php' ); ?>
								</button>
								<button type="button" class="button button-small button-link-delete delete-shortcode" data-shortcode="<?php echo esc_attr( $shortcode['name'] ); ?>">
									<?php esc_html_e( 'Delete', 'shortcode-exec-php' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Plugin Settings', 'shortcode-exec-php' ); ?></h2>

		<form method="post" action="">
			<?php wp_nonce_field( 'scep_admin_action', 'scep_admin_nonce' ); ?>
			<input type="hidden" name="scep_action" value="save_settings" />

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Shortcodes In', 'shortcode-exec-php' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Enable Shortcodes In', 'shortcode-exec-php' ); ?></legend>
							<label for="scep-widget">
								<input type="checkbox" id="scep-widget" name="scep_widget" value="1" <?php checked( get_option( 'scep_widget', false ) ); ?> />
								<?php esc_html_e( 'Widgets', 'shortcode-exec-php' ); ?>
							</label>
							<br />
							<label for="scep-excerpt">
								<input type="checkbox" id="scep-excerpt" name="scep_excerpt" value="1" <?php checked( get_option( 'scep_excerpt', false ) ); ?> />
								<?php esc_html_e( 'Excerpts', 'shortcode-exec-php' ); ?>
							</label>
							<br />
							<label for="scep-comment">
								<input type="checkbox" id="scep-comment" name="scep_comment" value="1" <?php checked( get_option( 'scep_comment', false ) ); ?> />
								<?php esc_html_e( 'Comments', 'shortcode-exec-php' ); ?>
							</label>
							<br />
							<label for="scep-rss">
								<input type="checkbox" id="scep-rss" name="scep_rss" value="1" <?php checked( get_option( 'scep_rss', false ) ); ?> />
								<?php esc_html_e( 'RSS Feeds', 'shortcode-exec-php' ); ?>
								<em>(<?php esc_html_e( 'Not recommended for security', 'shortcode-exec-php' ); ?>)</em>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scep-codewidth"><?php esc_html_e( 'Code Editor Width', 'shortcode-exec-php' ); ?></label>
					</th>
					<td>
						<input type="number" id="scep-codewidth" name="scep_codewidth" value="<?php echo esc_attr( get_option( 'scep_codewidth', 670 ) ); ?>" min="200" max="2000" />
						<p class="description"><?php esc_html_e( 'Width of the code editor in pixels (200-2000).', 'shortcode-exec-php' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="scep-codeheight"><?php esc_html_e( 'Code Editor Height', 'shortcode-exec-php' ); ?></label>
					</th>
					<td>
						<input type="number" id="scep-codeheight" name="scep_codeheight" value="<?php echo esc_attr( get_option( 'scep_codeheight', 350 ) ); ?>" min="100" max="1000" />
						<p class="description"><?php esc_html_e( 'Height of the code editor in pixels (100-1000).', 'shortcode-exec-php' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'TinyMCE Integration', 'shortcode-exec-php' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'TinyMCE Integration', 'shortcode-exec-php' ); ?></legend>
							<label for="scep-tinymce">
								<input type="checkbox" id="scep-tinymce" name="scep_tinymce" value="1" <?php checked( get_option( 'scep_tinymce', false ) ); ?> />
								<?php esc_html_e( 'Enable TinyMCE button', 'shortcode-exec-php' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'shortcode-exec-php' ); ?></button>
			</p>
		</form>
	</div>

	<div id="scep-resources-panel">
		<h3><?php esc_html_e( 'Security Information', 'shortcode-exec-php' ); ?></h3>
		<p><strong><?php esc_html_e( 'Important:', 'shortcode-exec-php' ); ?></strong></p>
		<ul>
			<li><?php esc_html_e( 'Only administrators can create/edit shortcodes', 'shortcode-exec-php' ); ?></li>
			<li><?php esc_html_e( 'All code executions are logged', 'shortcode-exec-php' ); ?></li>
			<li><?php esc_html_e( 'Dangerous functions are blocked', 'shortcode-exec-php' ); ?></li>
			<li><?php esc_html_e( 'Execution time is limited', 'shortcode-exec-php' ); ?></li>
		</ul>

		<h3><?php esc_html_e( 'Usage Examples', 'shortcode-exec-php' ); ?></h3>
		<table id="scep-example-table">
			<tr>
				<td class="scep-example-title"><?php esc_html_e( 'Basic:', 'shortcode-exec-php' ); ?></td>
				<td class="scep-example-explanation"><code>[my_shortcode]</code></td>
			</tr>
			<tr>
				<td class="scep-example-title"><?php esc_html_e( 'With parameters:', 'shortcode-exec-php' ); ?></td>
				<td class="scep-example-explanation"><code>[my_shortcode param="value"]</code></td>
			</tr>
			<tr>
				<td class="scep-example-title"><?php esc_html_e( 'With content:', 'shortcode-exec-php' ); ?></td>
				<td class="scep-example-explanation"><code>[my_shortcode]content[/my_shortcode]</code></td>
			</tr>
		</table>
	</div>

	<div style="clear: both;"></div>
</div>

<!-- Hidden form for delete actions -->
<form id="delete-shortcode-form" method="post" style="display: none;">
	<?php wp_nonce_field( 'scep_admin_action', 'scep_admin_nonce' ); ?>
	<input type="hidden" name="scep_action" value="delete_shortcode" />
	<input type="hidden" name="scep_shortcode_name" id="delete-shortcode-name" />
</form>

<!-- Test Results Modal -->
<div id="test-results-dialog" title="<?php esc_attr_e( 'Test Results', 'shortcode-exec-php' ); ?>" style="display: none;">
	<h4><?php esc_html_e( 'WYSIWYG View', 'shortcode-exec-php' ); ?></h4>
	<div id="test-results-wysiwyg" style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;"></div>
	
	<h4><?php esc_html_e( 'HTML Source', 'shortcode-exec-php' ); ?></h4>
	<pre id="test-results-html" style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; overflow: auto; max-height: 200px;"></pre>
</div>