/**
 * TinyMCE plugin for Shortcode Exec PHP.
 *
 * Provides a button in the TinyMCE editor for inserting shortcodes.
 * Updated for modern WordPress and TinyMCE versions.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

( function() {
	'use strict';

	// Check if TinyMCE is available.
	if ( typeof tinymce === 'undefined' ) {
		return;
	}

	tinymce.create( 'tinymce.plugins.ShortcodeExecPHP', {

		/**
		 * Initialize the plugin.
		 *
		 * @param {tinymce.Editor} editor Editor instance.
		 * @param {string}         url    Plugin URL.
		 */
		init: function( editor, url ) {
			const self = this;

			// Register command.
			editor.addCommand( 'mceShortcodeExecPHP', function() {
				self.openDialog( editor );
			} );

			// Register button.
			editor.addButton( 'ShortcodeExecPHP', {
				title: 'Insert Shortcode Exec PHP',
				cmd: 'mceShortcodeExecPHP',
				icon: 'code',
				classes: 'widget btn',
			} );
		},

		/**
		 * Open the shortcode selection dialog.
		 *
		 * @param {tinymce.Editor} editor Editor instance.
		 */
		openDialog: function( editor ) {
			// Use WordPress's ThickBox for the modal.
			const dialogUrl = ajaxurl + '?action=scep_ajax&scep_action=tinymce&TB_iframe=true&width=400&height=300';

			// Open ThickBox.
			tb_show( 'Insert Shortcode', dialogUrl );
		},

		/**
		 * Get plugin information.
		 *
		 * @return {Object} Plugin information.
		 */
		getInfo: function() {
			return {
				longname: 'Shortcode Exec PHP Plugin',
				author: 'WordPress Community',
				authorurl: 'https://wordpress.org/',
				version: '1.53',
			};
		},
	} );

	// Register plugin.
	tinymce.PluginManager.add( 'ShortcodeExecPHP', tinymce.plugins.ShortcodeExecPHP );

} )();