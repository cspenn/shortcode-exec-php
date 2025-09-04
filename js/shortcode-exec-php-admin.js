/**
 * Admin JavaScript for Shortcode Exec PHP plugin.
 *
 * Handles the admin interface functionality including shortcode testing,
 * editing, and management. Uses modern JavaScript standards and proper
 * WordPress coding practices.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

( function( $ ) {
	'use strict';

	/**
	 * Initialize admin functionality when document is ready.
	 */
	$( document ).ready( function() {
		initializeCodeEditor();
		initializeEventHandlers();
		initializeTestDialog();
	} );

	/**
	 * Initialize the code editor with WordPress CodeMirror.
	 */
	function initializeCodeEditor() {
		const textarea = document.getElementById( 'scep-phpcode' );
		if ( ! textarea ) {
			return;
		}

		// Initialize WordPress CodeMirror.
		if ( typeof wp !== 'undefined' && wp.codeEditor ) {
			const editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
			editorSettings.codemirror = _.extend(
				{},
				editorSettings.codemirror,
				{
					mode: 'php',
					lineNumbers: true,
					lineWrapping: true,
					theme: 'default',
					indentUnit: 4,
					indentWithTabs: true,
					autoCloseBrackets: true,
					matchBrackets: true,
					lint: true,
					gutters: [ 'CodeMirror-lint-markers', 'CodeMirror-linenumbers', 'CodeMirror-foldgutter' ],
				}
			);

			// Initialize the editor.
			wp.codeEditor.initialize( textarea, editorSettings );
		}
	}

	/**
	 * Initialize event handlers for admin interface.
	 */
	function initializeEventHandlers() {
		// Test shortcode button.
		$( '#test-shortcode' ).on( 'click', handleTestShortcode );

		// Edit shortcode buttons.
		$( '.edit-shortcode' ).on( 'click', handleEditShortcode );

		// Test shortcode buttons in list.
		$( '.test-shortcode' ).on( 'click', function( e ) {
			e.preventDefault();
			const shortcodeName = $( this ).data( 'shortcode' );
			testExistingShortcode( shortcodeName );
		} );

		// Delete shortcode buttons.
		$( '.delete-shortcode' ).on( 'click', handleDeleteShortcode );

		// Form validation.
		$( 'form' ).on( 'submit', validateForm );
	}

	/**
	 * Initialize test results dialog.
	 */
	function initializeTestDialog() {
		if ( $( '#test-results-dialog' ).length > 0 ) {
			$( '#test-results-dialog' ).dialog( {
				autoOpen: false,
				modal: true,
				width: 600,
				height: 400,
				resizable: true,
				position: { my: 'center', at: 'center', of: window },
			} );
		}
	}

	/**
	 * Handle test shortcode button click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleTestShortcode( e ) {
		e.preventDefault();

		const shortcodeName = $( '#scep-shortcode-name' ).val().trim();
		if ( ! shortcodeName ) {
			alert( scepAdmin.strings.testFailed + ' ' + 'Please enter a shortcode name.' );
			return;
		}

		// Validate shortcode name format.
		if ( ! /^[a-z0-9_-]+$/i.test( shortcodeName ) ) {
			alert( 'Invalid shortcode name format. Use only letters, numbers, underscores, and hyphens.' );
			return;
		}

		const phpCode = getEditorContent();
		if ( ! phpCode.trim() ) {
			alert( 'Please enter PHP code to test.' );
			return;
		}

		// Test the shortcode.
		testShortcode( shortcodeName, phpCode );
	}

	/**
	 * Handle edit shortcode button click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleEditShortcode( e ) {
		e.preventDefault();
		const shortcodeName = $( this ).data( 'shortcode' );

		// Load shortcode data via AJAX.
		$.ajax( {
			url: scepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'scep_ajax',
				scep_action: 'load_shortcode',
				shortcode: shortcodeName,
				_wpnonce: scepAdmin.nonce,
			},
			beforeSend: function() {
				$( this ).prop( 'disabled', true ).text( 'Loading...' );
			},
			success: function( response ) {
				if ( response.success ) {
					populateEditForm( response.data );
				} else {
					alert( 'Failed to load shortcode data: ' + response.data );
				}
			},
			error: function() {
				alert( scepAdmin.strings.testFailed );
			},
			complete: function() {
				$( this ).prop( 'disabled', false ).text( 'Edit' );
			},
		} );
	}

	/**
	 * Handle delete shortcode button click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleDeleteShortcode( e ) {
		e.preventDefault();
		const shortcodeName = $( this ).data( 'shortcode' );

		if ( ! confirm( scepAdmin.strings.confirmDelete ) ) {
			return;
		}

		// Set the shortcode name in the hidden delete form and submit.
		$( '#delete-shortcode-name' ).val( shortcodeName );
		$( '#delete-shortcode-form' ).submit();
	}

	/**
	 * Test an existing shortcode.
	 *
	 * @param {string} shortcodeName The name of the shortcode to test.
	 */
	function testExistingShortcode( shortcodeName ) {
		$.ajax( {
			url: scepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'scep_ajax',
				scep_action: 'test_shortcode',
				shortcode: shortcodeName,
				_wpnonce: scepAdmin.testNonce,
			},
			beforeSend: function() {
				$( '.test-shortcode[data-shortcode="' + shortcodeName + '"]' )
					.prop( 'disabled', true )
					.text( 'Testing...' );
			},
			success: function( response ) {
				displayTestResults( shortcodeName, response );
			},
			error: function() {
				alert( scepAdmin.strings.testFailed );
			},
			complete: function() {
				$( '.test-shortcode[data-shortcode="' + shortcodeName + '"]' )
					.prop( 'disabled', false )
					.text( 'Test' );
			},
		} );
	}

	/**
	 * Test a shortcode with provided code.
	 *
	 * @param {string} shortcodeName The shortcode name.
	 * @param {string} phpCode       The PHP code to test.
	 */
	function testShortcode( shortcodeName, phpCode ) {
		$.ajax( {
			url: scepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'scep_ajax',
				scep_action: 'test_code',
				shortcode: shortcodeName,
				phpcode: phpCode,
				_wpnonce: scepAdmin.testNonce,
			},
			beforeSend: function() {
				$( '#test-shortcode' ).prop( 'disabled', true ).text( 'Testing...' );
			},
			success: function( response ) {
				displayTestResults( shortcodeName, response );
			},
			error: function() {
				alert( scepAdmin.strings.testFailed );
			},
			complete: function() {
				$( '#test-shortcode' ).prop( 'disabled', false ).text( 'Test Shortcode' );
			},
		} );
	}

	/**
	 * Display test results in a modal dialog.
	 *
	 * @param {string} shortcodeName The shortcode name.
	 * @param {string} response      The test response.
	 */
	function displayTestResults( shortcodeName, response ) {
		// Parse the response.
		let result = '';
		if ( response.startsWith( 'OK=' ) ) {
			result = response.substring( 3 );
		} else {
			result = response;
		}

		// Populate the dialog content.
		$( '#test-results-wysiwyg' ).html( result );
		$( '#test-results-html' ).text( result );

		// Set dialog title.
		$( '#test-results-dialog' ).dialog( 'option', 'title', 'Test Results: [' + shortcodeName + ']' );

		// Open the dialog.
		$( '#test-results-dialog' ).dialog( 'open' );
	}

	/**
	 * Populate the edit form with shortcode data.
	 *
	 * @param {Object} data The shortcode data.
	 */
	function populateEditForm( data ) {
		$( '#scep-shortcode-name' ).val( data.name );
		$( '#scep-description' ).val( data.description );
		$( '#scep-enabled' ).prop( 'checked', data.enabled );
		$( '#scep-buffer' ).prop( 'checked', data.buffer );

		// Set code editor content.
		setEditorContent( data.code );

		// Scroll to top of form.
		$( 'html, body' ).animate( {
			scrollTop: $( '#scep-admin-panel' ).offset().top,
		}, 500 );
	}

	/**
	 * Get content from the code editor.
	 *
	 * @return {string} The editor content.
	 */
	function getEditorContent() {
		const textarea = document.getElementById( 'scep-phpcode' );
		if ( ! textarea ) {
			return '';
		}

		// Check if CodeMirror is active.
		if ( textarea.nextSibling && textarea.nextSibling.classList && textarea.nextSibling.classList.contains( 'CodeMirror' ) ) {
			const cm = textarea.nextSibling.CodeMirror;
			return cm ? cm.getValue() : textarea.value;
		}

		return textarea.value;
	}

	/**
	 * Set content in the code editor.
	 *
	 * @param {string} content The content to set.
	 */
	function setEditorContent( content ) {
		const textarea = document.getElementById( 'scep-phpcode' );
		if ( ! textarea ) {
			return;
		}

		// Check if CodeMirror is active.
		if ( textarea.nextSibling && textarea.nextSibling.classList && textarea.nextSibling.classList.contains( 'CodeMirror' ) ) {
			const cm = textarea.nextSibling.CodeMirror;
			if ( cm ) {
				cm.setValue( content );
				return;
			}
		}

		textarea.value = content;
	}

	/**
	 * Validate form before submission.
	 *
	 * @param {Event} e The submit event.
	 * @return {boolean} True to allow submission, false to prevent.
	 */
	function validateForm( e ) {
		const form = $( this );
		const action = form.find( 'input[name="scep_action"]' ).val();

		if ( 'save_shortcode' === action ) {
			return validateShortcodeForm( form );
		}

		return true;
	}

	/**
	 * Validate shortcode form data.
	 *
	 * @param {jQuery} form The form element.
	 * @return {boolean} True if valid, false otherwise.
	 */
	function validateShortcodeForm( form ) {
		const shortcodeName = form.find( '#scep-shortcode-name' ).val().trim();
		const phpCode = getEditorContent().trim();

		// Validate shortcode name.
		if ( ! shortcodeName ) {
			alert( 'Please enter a shortcode name.' );
			form.find( '#scep-shortcode-name' ).focus();
			return false;
		}

		if ( ! /^[a-z0-9_-]+$/i.test( shortcodeName ) ) {
			alert( 'Invalid shortcode name format. Use only letters, numbers, underscores, and hyphens.' );
			form.find( '#scep-shortcode-name' ).focus();
			return false;
		}

		if ( shortcodeName.length > 50 ) {
			alert( 'Shortcode name is too long (maximum 50 characters).' );
			form.find( '#scep-shortcode-name' ).focus();
			return false;
		}

		// Validate PHP code.
		if ( ! phpCode ) {
			alert( 'Please enter PHP code for the shortcode.' );
			document.getElementById( 'scep-phpcode' ).focus();
			return false;
		}

		if ( phpCode.length > 10000 ) {
			alert( 'PHP code is too long (maximum 10KB).' );
			document.getElementById( 'scep-phpcode' ).focus();
			return false;
		}

		return true;
	}

	/**
	 * Escape HTML for safe display.
	 *
	 * @param {string} text The text to escape.
	 * @return {string} The escaped text.
	 */
	function escapeHTML( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Show success message.
	 *
	 * @param {string} message The success message.
	 */
	function showSuccessMessage( message ) {
		const notice = $( '<div class="notice notice-success is-dismissible"><p>' + escapeHTML( message ) + '</p></div>' );
		$( '.wrap h1' ).after( notice );
		$( 'html, body' ).animate( { scrollTop: 0 }, 500 );
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message The error message.
	 */
	function showErrorMessage( message ) {
		const notice = $( '<div class="notice notice-error is-dismissible"><p>' + escapeHTML( message ) + '</p></div>' );
		$( '.wrap h1' ).after( notice );
		$( 'html, body' ).animate( { scrollTop: 0 }, 500 );
	}

} )( jQuery );