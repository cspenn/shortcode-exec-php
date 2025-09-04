/**
 * Modern TinyMCE Plugin for Shortcode Exec PHP
 * 
 * Updated TinyMCE integration with WordPress core modals,
 * modern JavaScript patterns, and enhanced security.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

( function() {
	'use strict';
	
	// Ensure TinyMCE is available
	if ( typeof tinymce === 'undefined' ) {
		return;
	}
	
	tinymce.create( 'tinymce.plugins.ShortcodeExecPHP', {
		
		/**
		 * Initialize the TinyMCE plugin
		 *
		 * @param {tinymce.Editor} ed  The TinyMCE editor instance
		 * @param {string}         url Plugin URL
		 */
		init: function( ed, url ) {
			const self = this;
			this.editor = ed;
			this.url = url;
			
			// Register the shortcode insertion command
			ed.addCommand( 'mceShortcodeExecPHP', function() {
				self.openShortcodeModal();
			} );
			
			// Register the toolbar button
			ed.addButton( 'shortcodeExecPHP', {
				title: window.shortcodeExecPHP?.modalTitle || 'Insert PHP Shortcode',
				cmd: 'mceShortcodeExecPHP',
				classes: 'widget btn shortcode-exec-php-btn',
				image: url + '/icon-shortcode.svg'
			} );
			
			// Add CSS for button styling
			ed.on( 'init', function() {
				ed.dom.loadCSS( url.replace( '/js/', '/css/' ) + '/tinymce-button.css' );
			} );
		},
		
		/**
		 * Open shortcode selection modal using WordPress core modal system
		 */
		openShortcodeModal: function() {
			const self = this;
			
			// Check if WordPress modal system is available
			if ( ! window.wp || ! window.wp.media || ! window.wp.media.view.Modal ) {
				// Fallback to simple prompt for older WordPress versions
				this.showSimpleDialog();
				return;
			}
			
			// Load available shortcodes first
			this.loadShortcodes().then( function( shortcodes ) {
				if ( ! shortcodes || shortcodes.length === 0 ) {
					alert( 'No shortcodes available. Please create shortcodes in the admin panel first.' );
					return;
				}
				
				// Create modal with WordPress core modal system
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
				
				// Create and set modal content
				const content = self.createModalContent( shortcodes, modal );
				modal.content( content );
				
				// Open the modal
				modal.open();
				
			} ).catch( function( error ) {
				console.error( 'Failed to load shortcodes:', error );
				alert( 'Failed to load shortcodes. Please try again.' );
			} );
		},
		
		/**
		 * Create modal content view
		 *
		 * @param {Array}  shortcodes Available shortcodes
		 * @param {Object} modal      Modal instance
		 * @return {wp.media.View} Modal content view
		 */
		createModalContent: function( shortcodes, modal ) {
			const self = this;
			
			const ShortcodeModalContent = wp.media.View.extend( {
				
				tagName: 'div',
				className: 'shortcode-exec-php-modal-content',
				
				events: {
					'change #shortcode-select': 'onShortcodeChange',
					'click #add-parameter': 'addParameter',
					'click .remove-parameter': 'removeParameter',
					'click #preview-shortcode': 'previewShortcode',
					'click #insert-shortcode': 'insertShortcode',
					'click #cancel-shortcode': 'cancelModal'
				},
				
				initialize: function() {
					this.shortcodes = shortcodes;
					this.selectedShortcode = '';
					this.parameters = {};
					this.content = '';
				},
				
				render: function() {
					this.$el.html( this.getTemplate() );
					this.populateShortcodes();
					return this;
				},
				
				/**
				 * Get the modal template HTML
				 */
				getTemplate: function() {
					return `
						<div class="shortcode-modal-container">
							<div class="shortcode-selection">
								<label for="shortcode-select">
									<strong>Select Shortcode:</strong>
								</label>
								<select id="shortcode-select" class="widefat">
									<option value="">Choose a shortcode...</option>
								</select>
							</div>
							
							<div id="shortcode-config" style="display: none;">
								<div class="shortcode-parameters">
									<h4>Parameters <button type="button" id="add-parameter" class="button button-small">Add Parameter</button></h4>
									<div id="parameters-container"></div>
								</div>
								
								<div class="shortcode-content">
									<label for="shortcode-content">
										<strong>Content (optional):</strong>
									</label>
									<textarea id="shortcode-content" class="widefat" rows="3" placeholder="Optional shortcode content..."></textarea>
								</div>
								
								<div class="shortcode-preview">
									<h4>Preview:</h4>
									<div id="preview-container" class="preview-box">
										<em>Select a shortcode to see preview</em>
									</div>
									<button type="button" id="preview-shortcode" class="button">Update Preview</button>
								</div>
							</div>
							
							<div class="modal-actions">
								<button type="button" id="insert-shortcode" class="button button-primary" disabled>Insert Shortcode</button>
								<button type="button" id="cancel-shortcode" class="button">Cancel</button>
							</div>
						</div>
					`;
				},
				
				/**
				 * Populate shortcode dropdown
				 */
				populateShortcodes: function() {
					const select = this.$el.find( '#shortcode-select' );
					
					this.shortcodes.forEach( function( shortcode ) {
						select.append( 
							'<option value="' + shortcode.name + '">' + 
							shortcode.label + 
							'</option>' 
						);
					} );
				},
				
				/**
				 * Handle shortcode selection change
				 */
				onShortcodeChange: function( e ) {
					const shortcodeName = e.target.value;
					this.selectedShortcode = shortcodeName;
					
					if ( shortcodeName ) {
						this.$el.find( '#shortcode-config' ).show();
						this.$el.find( '#insert-shortcode' ).prop( 'disabled', false );
						this.updatePreview();
					} else {
						this.$el.find( '#shortcode-config' ).hide();
						this.$el.find( '#insert-shortcode' ).prop( 'disabled', true );
					}
				},
				
				/**
				 * Add parameter input fields
				 */
				addParameter: function() {
					const container = this.$el.find( '#parameters-container' );
					const paramIndex = container.find( '.parameter-row' ).length;
					
					const paramRow = `
						<div class="parameter-row" style="margin-bottom: 10px; display: flex; gap: 5px;">
							<input type="text" placeholder="Parameter name" class="param-key" style="flex: 1;" />
							<input type="text" placeholder="Parameter value" class="param-value" style="flex: 2;" />
							<button type="button" class="remove-parameter button button-small">Remove</button>
						</div>
					`;
					
					container.append( paramRow );
				},
				
				/**
				 * Remove parameter row
				 */
				removeParameter: function( e ) {
					$( e.target ).closest( '.parameter-row' ).remove();
					this.updatePreview();
				},
				
				/**
				 * Update shortcode preview
				 */
				updatePreview: function() {
					if ( ! this.selectedShortcode ) {
						return;
					}
					
					const shortcodeString = this.buildShortcodeString();
					const previewContainer = this.$el.find( '#preview-container' );
					
					previewContainer.html( '<em>Loading preview...</em>' );
					
					// Make AJAX request for preview
					const data = new FormData();
					data.append( 'action', 'scep_preview_shortcode' );
					data.append( 'nonce', window.shortcodeExecPHP?.nonce || '' );
					data.append( 'shortcode', shortcodeString );
					
					fetch( window.shortcodeExecPHP?.ajaxUrl || ajaxurl, {
						method: 'POST',
						body: data
					} )
					.then( response => response.json() )
					.then( result => {
						if ( result.success ) {
							previewContainer.html( result.data.preview || '<em>No output</em>' );
						} else {
							previewContainer.html( '<em style="color: #d63638;">Preview error: ' + ( result.data || 'Unknown error' ) + '</em>' );
						}
					} )
					.catch( error => {
						console.error( 'Preview error:', error );
						previewContainer.html( '<em style="color: #d63638;">Network error</em>' );
					} );
				},
				
				/**
				 * Preview shortcode (button handler)
				 */
				previewShortcode: function() {
					this.updatePreview();
				},
				
				/**
				 * Build shortcode string from form inputs
				 */
				buildShortcodeString: function() {
					let shortcodeString = '[' + this.selectedShortcode;
					
					// Add parameters
					const self = this;
					this.$el.find( '.parameter-row' ).each( function() {
						const key = $( this ).find( '.param-key' ).val().trim();
						const value = $( this ).find( '.param-value' ).val().trim();
						
						if ( key && value ) {
							shortcodeString += ' ' + key + '="' + value.replace( /"/g, '&quot;' ) + '"';
						}
					} );
					
					// Add content
					const content = this.$el.find( '#shortcode-content' ).val().trim();
					if ( content ) {
						shortcodeString += ']' + content + '[/' + this.selectedShortcode + ']';
					} else {
						shortcodeString += ']';
					}
					
					return shortcodeString;
				},
				
				/**
				 * Insert shortcode into editor
				 */
				insertShortcode: function() {
					if ( ! this.selectedShortcode ) {
						return;
					}
					
					const shortcodeString = this.buildShortcodeString();
					
					// Insert into TinyMCE editor
					self.editor.insertContent( shortcodeString );
					
					// Close modal
					modal.close();
				},
				
				/**
				 * Cancel modal
				 */
				cancelModal: function() {
					modal.close();
				}
			} );
			
			return new ShortcodeModalContent();
		},
		
		/**
		 * Load available shortcodes via AJAX
		 *
		 * @return {Promise} Promise resolving to shortcodes array
		 */
		loadShortcodes: function() {
			return new Promise( function( resolve, reject ) {
				const data = new FormData();
				data.append( 'action', 'scep_get_shortcodes' );
				data.append( 'nonce', window.shortcodeExecPHP?.nonce || '' );
				
				fetch( window.shortcodeExecPHP?.ajaxUrl || ajaxurl, {
					method: 'POST',
					body: data
				} )
				.then( response => response.json() )
				.then( result => {
					if ( result.success ) {
						resolve( result.data || [] );
					} else {
						reject( new Error( result.data || 'Failed to load shortcodes' ) );
					}
				} )
				.catch( reject );
			} );
		},
		
		/**
		 * Fallback simple dialog for older WordPress versions
		 */
		showSimpleDialog: function() {
			const self = this;
			
			this.loadShortcodes().then( function( shortcodes ) {
				if ( ! shortcodes || shortcodes.length === 0 ) {
					alert( 'No shortcodes available.' );
					return;
				}
				
				let options = 'Available shortcodes:\n';
				shortcodes.forEach( function( shortcode, index ) {
					options += ( index + 1 ) + '. ' + shortcode.name + ' - ' + shortcode.description + '\n';
				} );
				
				const shortcodeName = prompt( 
					options + '\nEnter shortcode name to insert:' 
				);
				
				if ( shortcodeName ) {
					// Validate shortcode exists
					const exists = shortcodes.some( function( sc ) {
						return sc.name === shortcodeName;
					} );
					
					if ( exists ) {
						self.editor.insertContent( '[' + shortcodeName + ']' );
					} else {
						alert( 'Invalid shortcode name.' );
					}
				}
			} ).catch( function( error ) {
				console.error( 'Error loading shortcodes:', error );
				alert( 'Failed to load shortcodes.' );
			} );
		},
		
		/**
		 * Get plugin information
		 *
		 * @return {Object} Plugin info
		 */
		getInfo: function() {
			return {
				longname: 'Shortcode Exec PHP Plugin (Modern)',
				author: 'WordPress Community',
				authorurl: 'https://wordpress.org/',
				version: '1.53'
			};
		}
		
	} );
	
	// Register the plugin with TinyMCE
	tinymce.PluginManager.add( 'shortcodeExecPHP', tinymce.plugins.ShortcodeExecPHP );
	
} )();