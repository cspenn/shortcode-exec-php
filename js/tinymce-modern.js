/**
 * Modern TinyMCE Support for Shortcode Exec PHP
 * 
 * Provides enhanced TinyMCE integration with WordPress core modal system,
 * better user experience, and modern JavaScript patterns.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

( function( $ ) {
	'use strict';
	
	// Ensure jQuery and WordPress are available
	if ( typeof $ === 'undefined' || typeof wp === 'undefined' ) {
		return;
	}
	
	/**
	 * TinyMCE Modern Support Object
	 */
	window.ShortcodeExecPHPTinyMCE = {
		
		/**
		 * Initialize TinyMCE support
		 */
		init: function() {
			this.addStyles();
			this.setupEventListeners();
			
			// Ensure TinyMCE plugin is loaded when TinyMCE initializes
			if ( typeof tinymce !== 'undefined' ) {
				tinymce.on( 'AddEditor', this.onEditorAdd.bind( this ) );
			}
		},
		
		/**
		 * Add custom styles for TinyMCE integration
		 */
		addStyles: function() {
			if ( $( '#shortcode-exec-php-tinymce-styles' ).length ) {
				return;
			}
			
			const styles = `
				<style id="shortcode-exec-php-tinymce-styles">
					.shortcode-exec-php-modal-content {
						padding: 20px;
						max-width: 600px;
						min-height: 400px;
					}
					
					.shortcode-selection {
						margin-bottom: 20px;
					}
					
					.shortcode-selection label {
						display: block;
						margin-bottom: 5px;
						font-weight: 600;
					}
					
					.shortcode-selection select {
						width: 100%;
						padding: 8px;
						border: 1px solid #ddd;
						border-radius: 4px;
					}
					
					.shortcode-parameters h4 {
						margin: 20px 0 10px 0;
						display: flex;
						align-items: center;
						gap: 10px;
					}
					
					.parameter-row {
						margin-bottom: 10px;
						display: flex;
						gap: 8px;
						align-items: center;
					}
					
					.parameter-row input {
						padding: 6px;
						border: 1px solid #ddd;
						border-radius: 4px;
					}
					
					.param-key {
						flex: 1;
						min-width: 100px;
					}
					
					.param-value {
						flex: 2;
						min-width: 150px;
					}
					
					.shortcode-content {
						margin: 20px 0;
					}
					
					.shortcode-content label {
						display: block;
						margin-bottom: 5px;
						font-weight: 600;
					}
					
					.shortcode-content textarea {
						width: 100%;
						padding: 8px;
						border: 1px solid #ddd;
						border-radius: 4px;
						resize: vertical;
					}
					
					.shortcode-preview {
						margin: 20px 0;
					}
					
					.shortcode-preview h4 {
						margin: 0 0 10px 0;
					}
					
					.preview-box {
						border: 1px solid #ddd;
						padding: 15px;
						background-color: #f9f9f9;
						border-radius: 4px;
						min-height: 50px;
						margin-bottom: 10px;
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
					}
					
					.preview-box em {
						color: #666;
						font-style: italic;
					}
					
					.modal-actions {
						margin-top: 30px;
						text-align: right;
						border-top: 1px solid #ddd;
						padding-top: 20px;
					}
					
					.modal-actions .button {
						margin-left: 10px;
					}
					
					.shortcode-exec-php-btn.mce-widget button {
						background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTMgNEgxN1YxNkgzVjRaTTEzIDZIOVY4SDEzVjZaTTcgMTBIMTNWMTJIN1YxMFpNNyAxNEgxMVYxNkg3VjE0WiIgZmlsbD0iIzU1NTU1NSIvPgo8L3N2Zz4K');
						background-repeat: no-repeat;
						background-position: center;
						background-size: 16px 16px;
					}
					
					@media (max-width: 782px) {
						.shortcode-exec-php-modal-content {
							padding: 15px;
						}
						
						.parameter-row {
							flex-direction: column;
							align-items: stretch;
							gap: 5px;
						}
						
						.param-key,
						.param-value {
							flex: none;
							width: 100%;
						}
					}
				</style>
			`;
			
			$( 'head' ).append( styles );
		},
		
		/**
		 * Setup event listeners
		 */
		setupEventListeners: function() {
			// Listen for TinyMCE initialization
			$( document ).on( 'tinymce-editor-init', this.onTinyMCEInit.bind( this ) );
			
			// Handle shortcode button clicks (fallback)
			$( document ).on( 'click', '.shortcode-exec-php-insert', this.handleShortcodeInsert.bind( this ) );
		},
		
		/**
		 * Handle TinyMCE editor initialization
		 *
		 * @param {Event}  event  The initialization event
		 * @param {Object} editor The TinyMCE editor instance
		 */
		onTinyMCEInit: function( event, editor ) {
			// Ensure our plugin is loaded
			if ( editor && editor.plugins && editor.plugins.shortcodeExecPHP ) {
				this.enhanceEditor( editor );
			}
		},
		
		/**
		 * Handle editor addition
		 *
		 * @param {Object} event TinyMCE event object
		 */
		onEditorAdd: function( event ) {
			const editor = event.editor;
			
			// Wait for editor to be fully initialized
			editor.on( 'init', function() {
				this.enhanceEditor( editor );
			}.bind( this ) );
		},
		
		/**
		 * Enhance TinyMCE editor with additional features
		 *
		 * @param {Object} editor TinyMCE editor instance
		 */
		enhanceEditor: function( editor ) {
			// Add keyboard shortcut for shortcode insertion
			editor.addShortcut( 'ctrl+shift+s', 'Insert PHP Shortcode', function() {
				editor.execCommand( 'mceShortcodeExecPHP' );
			} );
			
			// Add context menu item
			editor.on( 'contextmenu', function( e ) {
				// Add shortcode option to context menu
				// This would require extending TinyMCE's context menu system
			} );
			
			// Enhance shortcode detection and highlighting
			this.addShortcodeHighlighting( editor );
		},
		
		/**
		 * Add visual highlighting for shortcodes in the editor
		 *
		 * @param {Object} editor TinyMCE editor instance
		 */
		addShortcodeHighlighting: function( editor ) {
			// Add CSS for shortcode highlighting
			editor.on( 'init', function() {
				editor.dom.addStyle( `
					.mce-shortcode-highlight {
						background-color: #e8f4fd;
						border: 1px solid #0073aa;
						border-radius: 3px;
						padding: 2px 4px;
						margin: 0 2px;
						font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
						font-size: 12px;
						color: #0073aa;
						cursor: pointer;
					}
					
					.mce-shortcode-highlight:hover {
						background-color: #cce4f0;
					}
				` );
			} );
			
			// Highlight shortcodes on content change
			editor.on( 'setcontent keyup', function() {
				this.highlightShortcodes( editor );
			}.bind( this ) );
		},
		
		/**
		 * Highlight shortcodes in editor content
		 *
		 * @param {Object} editor TinyMCE editor instance
		 */
		highlightShortcodes: function( editor ) {
			const content = editor.getContent();
			const shortcodeRegex = /\[([a-zA-Z0-9_-]+)(\s[^\]]*)?]/g;
			
			// Only process if we have shortcodes from our plugin
			if ( window.shortcodeExecPHP && window.shortcodeExecPHP.shortcodes ) {
				const availableShortcodes = window.shortcodeExecPHP.shortcodes.map( sc => sc.name );
				
				const highlightedContent = content.replace( shortcodeRegex, function( match, shortcodeName, params ) {
					if ( availableShortcodes.includes( shortcodeName ) ) {
						return `<span class="mce-shortcode-highlight" data-shortcode="${shortcodeName}" title="PHP Shortcode: ${shortcodeName}">${match}</span>`;
					}
					return match;
				} );
				
				if ( highlightedContent !== content ) {
					editor.setContent( highlightedContent );
				}
			}
		},
		
		/**
		 * Handle shortcode insertion (fallback method)
		 *
		 * @param {Event} event Click event
		 */
		handleShortcodeInsert: function( event ) {
			event.preventDefault();
			
			const shortcodeName = $( event.currentTarget ).data( 'shortcode' );
			
			if ( shortcodeName && typeof tinymce !== 'undefined' ) {
				const activeEditor = tinymce.activeEditor;
				if ( activeEditor ) {
					activeEditor.insertContent( `[${shortcodeName}]` );
				}
			}
		},
		
		/**
		 * Create shortcode insertion modal (WordPress core modal)
		 *
		 * @param {Array} shortcodes Available shortcodes
		 * @return {Object} Modal instance
		 */
		createModal: function( shortcodes ) {
			if ( ! wp.media || ! wp.media.view.Modal ) {
				return null;
			}
			
			const modal = new wp.media.view.Modal( {
				controller: {
					trigger: function() {},
					close: function() {
						modal.close();
					}
				}
			} );
			
			// Set modal title
			modal.$el.find( '.media-modal-title h1' ).text( 
				window.shortcodeExecPHP?.modalTitle || 'Insert PHP Shortcode'
			);
			
			return modal;
		},
		
		/**
		 * Get available shortcodes via AJAX
		 *
		 * @return {Promise} Promise resolving to shortcodes array
		 */
		getShortcodes: function() {
			return new Promise( function( resolve, reject ) {
				$.ajax( {
					url: window.shortcodeExecPHP?.ajaxUrl || ajaxurl,
					type: 'POST',
					data: {
						action: 'scep_get_shortcodes',
						nonce: window.shortcodeExecPHP?.nonce || ''
					},
					success: function( response ) {
						if ( response.success ) {
							resolve( response.data || [] );
						} else {
							reject( new Error( response.data || 'Failed to load shortcodes' ) );
						}
					},
					error: function( xhr, status, error ) {
						reject( new Error( 'Network error: ' + error ) );
					}
				} );
			} );
		},
		
		/**
		 * Show notification message
		 *
		 * @param {string} message Message text
		 * @param {string} type    Message type (success, error, warning)
		 */
		showNotice: function( message, type ) {
			type = type || 'info';
			
			// Use WordPress admin notices if available
			if ( wp.a11y && wp.a11y.speak ) {
				wp.a11y.speak( message );
			}
			
			// Also show a simple alert for immediate feedback
			if ( type === 'error' ) {
				alert( 'Error: ' + message );
			} else if ( type === 'success' ) {
				// Could implement a success notification system
				console.log( 'Success: ' + message );
			}
		}
		
	};
	
	// Initialize when document is ready
	$( document ).ready( function() {
		window.ShortcodeExecPHPTinyMCE.init();
	} );
	
} )( jQuery );