/**
 * Gutenberg Blocks for Shortcode Exec PHP
 * 
 * Modern WordPress Block Editor integration with real-time preview,
 * parameter support, and comprehensive shortcode management.
 *
 * @package WordPress
 * @subpackage Shortcode_Exec_PHP
 * @since 1.53
 */

( function() {
	'use strict';
	
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { 
		PanelBody, 
		SelectControl, 
		TextControl, 
		TextareaControl, 
		Button,
		Notice,
		Spinner,
		Card,
		CardBody
	} = wp.components;
	const { useState, useEffect } = wp.element;
	const { __ } = wp.i18n;
	const { apiFetch } = wp;
	
	/**
	 * Shortcode Selector Block
	 * 
	 * Interactive block for selecting and configuring PHP shortcodes
	 * with live preview and parameter management.
	 */
	registerBlockType( 'shortcode-exec-php/shortcode-selector', {
		title: __( 'PHP Shortcode', 'shortcode-exec-php' ),
		description: __( 'Insert and configure PHP shortcodes with live preview', 'shortcode-exec-php' ),
		icon: {
			src: 'editor-code',
			background: '#1e73be',
			foreground: '#ffffff'
		},
		category: 'widgets',
		keywords: [
			__( 'php', 'shortcode-exec-php' ),
			__( 'shortcode', 'shortcode-exec-php' ),
			__( 'code', 'shortcode-exec-php' ),
			__( 'dynamic', 'shortcode-exec-php' )
		],
		supports: {
			html: false,
			align: true,
			alignWide: false,
		},
		attributes: {
			shortcodeName: {
				type: 'string',
				default: ''
			},
			parameters: {
				type: 'object',
				default: {}
			},
			content: {
				type: 'string',
				default: ''
			},
			hasPreview: {
				type: 'boolean',
				default: false
			}
		},
		
		edit: function( props ) {
			const { attributes, setAttributes } = props;
			const { shortcodeName, parameters, content } = attributes;
			const blockProps = useBlockProps();
			
			// State management
			const [ shortcodes, setShortcodes ] = useState( [] );
			const [ loading, setLoading ] = useState( true );
			const [ preview, setPreview ] = useState( '' );
			const [ previewLoading, setPreviewLoading ] = useState( false );
			const [ error, setError ] = useState( null );
			
			// Load available shortcodes on mount
			useEffect( () => {
				loadShortcodes();
			}, [] );
			
			// Update preview when shortcode changes
			useEffect( () => {
				if ( shortcodeName ) {
					updatePreview();
				}
			}, [ shortcodeName, parameters, content ] );
			
			/**
			 * Load available shortcodes from server
			 */
			const loadShortcodes = () => {
				setLoading( true );
				setError( null );
				
				const formData = new FormData();
				formData.append( 'action', 'scep_get_shortcodes' );
				formData.append( 'nonce', shortcodeExecPHP.nonce );
				
				fetch( shortcodeExecPHP.ajaxUrl, {
					method: 'POST',
					body: formData
				} )
				.then( response => response.json() )
				.then( data => {
					if ( data.success ) {
						setShortcodes( data.data || [] );
					} else {
						setError( data.data || __( 'Failed to load shortcodes', 'shortcode-exec-php' ) );
					}
				} )
				.catch( err => {
					setError( __( 'Network error loading shortcodes', 'shortcode-exec-php' ) );
					console.error( 'Shortcode loading error:', err );
				} )
				.finally( () => {
					setLoading( false );
				} );
			};
			
			/**
			 * Update shortcode preview
			 */
			const updatePreview = () => {
				if ( ! shortcodeName ) {
					setPreview( '' );
					return;
				}
				
				setPreviewLoading( true );
				
				// Build shortcode string
				let shortcodeString = `[${shortcodeName}`;
				
				// Add parameters
				if ( parameters && Object.keys( parameters ).length > 0 ) {
					Object.entries( parameters ).forEach( ( [ key, value ] ) => {
						if ( key && value ) {
							shortcodeString += ` ${key}="${value}"`;
						}
					} );
				}
				
				// Add content
				if ( content ) {
					shortcodeString += `]${content}[/${shortcodeName}]`;
				} else {
					shortcodeString += ']';
				}
				
				const formData = new FormData();
				formData.append( 'action', 'scep_preview_shortcode' );
				formData.append( 'nonce', shortcodeExecPHP.nonce );
				formData.append( 'shortcode', shortcodeString );
				
				fetch( shortcodeExecPHP.ajaxUrl, {
					method: 'POST',
					body: formData
				} )
				.then( response => response.json() )
				.then( data => {
					if ( data.success ) {
						setPreview( data.data.preview || '' );
						setError( null );
					} else {
						setError( data.data || __( 'Preview failed', 'shortcode-exec-php' ) );
						setPreview( '' );
					}
				} )
				.catch( err => {
					setError( __( 'Preview network error', 'shortcode-exec-php' ) );
					setPreview( '' );
					console.error( 'Preview error:', err );
				} )
				.finally( () => {
					setPreviewLoading( false );
				} );
			};
			
			/**
			 * Handle shortcode selection change
			 */
			const handleShortcodeChange = ( newShortcodeName ) => {
				setAttributes( {
					shortcodeName: newShortcodeName,
					parameters: {},
					content: '',
					hasPreview: !!newShortcodeName
				} );
			};
			
			/**
			 * Handle parameter changes
			 */
			const handleParameterChange = ( key, value ) => {
				const newParameters = { ...parameters };
				if ( value ) {
					newParameters[ key ] = value;
				} else {
					delete newParameters[ key ];
				}
				setAttributes( { parameters: newParameters } );
			};
			
			/**
			 * Add new parameter field
			 */
			const addParameter = () => {
				const newKey = `param_${Object.keys( parameters ).length + 1}`;
				handleParameterChange( newKey, '' );
			};
			
			/**
			 * Remove parameter field
			 */
			const removeParameter = ( key ) => {
				const newParameters = { ...parameters };
				delete newParameters[ key ];
				setAttributes( { parameters: newParameters } );
			};
			
			// Render loading state
			if ( loading ) {
				return (
					<div { ...blockProps }>
						<Card>
							<CardBody>
								<div style={ { textAlign: 'center', padding: '20px' } }>
									<Spinner />
									<p>{ __( 'Loading shortcodes...', 'shortcode-exec-php' ) }</p>
								</div>
							</CardBody>
						</Card>
					</div>
				);
			}
			
			// Render error state
			if ( error && shortcodes.length === 0 ) {
				return (
					<div { ...blockProps }>
						<Notice status="error" isDismissible={ false }>
							<p>{ error }</p>
							<Button isSecondary onClick={ loadShortcodes }>
								{ __( 'Retry', 'shortcode-exec-php' ) }
							</Button>
						</Notice>
					</div>
				);
			}
			
			// Create shortcode options for SelectControl
			const shortcodeOptions = [
				{ label: __( 'Select a shortcode...', 'shortcode-exec-php' ), value: '' },
				...shortcodes.map( shortcode => ( {
					label: shortcode.label || `[${shortcode.name}]`,
					value: shortcode.name
				} ) )
			];
			
			return (
				<div { ...blockProps }>
					<InspectorControls>
						<PanelBody title={ __( 'Shortcode Settings', 'shortcode-exec-php' ) }>
							<SelectControl
								label={ __( 'Shortcode', 'shortcode-exec-php' ) }
								value={ shortcodeName }
								options={ shortcodeOptions }
								onChange={ handleShortcodeChange }
							/>
							
							{ shortcodeName && (
								<>
									<hr />
									<h4>{ __( 'Parameters', 'shortcode-exec-php' ) }</h4>
									{ Object.entries( parameters ).map( ( [ key, value ] ) => (
										<div key={ key } style={ { marginBottom: '10px' } }>
											<div style={ { display: 'flex', gap: '5px' } }>
												<TextControl
													label={ __( 'Key', 'shortcode-exec-php' ) }
													value={ key }
													onChange={ ( newKey ) => {
														if ( newKey !== key ) {
															const newParams = { ...parameters };
															delete newParams[ key ];
															newParams[ newKey ] = value;
															setAttributes( { parameters: newParams } );
														}
													} }
													style={ { flex: 1 } }
												/>
												<TextControl
													label={ __( 'Value', 'shortcode-exec-php' ) }
													value={ value }
													onChange={ ( newValue ) => handleParameterChange( key, newValue ) }
													style={ { flex: 2 } }
												/>
												<Button
													isDestructive
													isSmall
													onClick={ () => removeParameter( key ) }
													style={ { marginTop: '25px' } }
												>
													{ __( '×', 'shortcode-exec-php' ) }
												</Button>
											</div>
										</div>
									) ) }
									
									<Button isSecondary onClick={ addParameter }>
										{ __( 'Add Parameter', 'shortcode-exec-php' ) }
									</Button>
									
									<hr />
									
									<TextareaControl
										label={ __( 'Content', 'shortcode-exec-php' ) }
										value={ content }
										onChange={ ( newContent ) => setAttributes( { content: newContent } ) }
										placeholder={ __( 'Optional shortcode content...', 'shortcode-exec-php' ) }
										rows={ 3 }
									/>
								</>
							) }
						</PanelBody>
						
						{ shortcodeName && (
							<PanelBody title={ __( 'Preview', 'shortcode-exec-php' ) } initialOpen={ false }>
								{ previewLoading ? (
									<div style={ { textAlign: 'center' } }>
										<Spinner />
										<p>{ __( 'Generating preview...', 'shortcode-exec-php' ) }</p>
									</div>
								) : error ? (
									<Notice status="warning" isDismissible={ false }>
										{ error }
									</Notice>
								) : preview ? (
									<div
										style={ {
											border: '1px solid #ddd',
											padding: '10px',
											backgroundColor: '#f9f9f9',
											fontFamily: 'monospace',
											fontSize: '12px',
											whiteSpace: 'pre-wrap'
										} }
										dangerouslySetInnerHTML={ { __html: preview } }
									/>
								) : (
									<p>{ __( 'No preview available', 'shortcode-exec-php' ) }</p>
								) }
							</PanelBody>
						) }
					</InspectorControls>
					
					<Card>
						<CardBody>
							{ ! shortcodeName ? (
								<div style={ { textAlign: 'center', padding: '20px' } }>
									<div style={ { fontSize: '48px', marginBottom: '10px' } }>⚡</div>
									<h3>{ __( 'PHP Shortcode', 'shortcode-exec-php' ) }</h3>
									<p>{ __( 'Select a shortcode from the settings panel to get started.', 'shortcode-exec-php' ) }</p>
									{ shortcodes.length === 0 && (
										<Notice status="info" isDismissible={ false }>
											{ __( 'No shortcodes available. Create one in the admin panel first.', 'shortcode-exec-php' ) }
										</Notice>
									) }
								</div>
							) : (
								<div>
									<h4 style={ { margin: '0 0 10px 0' } }>
										[{ shortcodeName }]
									</h4>
									{ Object.keys( parameters ).length > 0 && (
										<div style={ { marginBottom: '10px' } }>
											<strong>{ __( 'Parameters:', 'shortcode-exec-php' ) }</strong>
											<ul style={ { margin: '5px 0 0 20px' } }>
												{ Object.entries( parameters ).map( ( [ key, value ] ) => (
													<li key={ key }>
														<code>{ key }</code>: <code>{ value }</code>
													</li>
												) ) }
											</ul>
										</div>
									) }
									{ content && (
										<div style={ { marginBottom: '10px' } }>
											<strong>{ __( 'Content:', 'shortcode-exec-php' ) }</strong>
											<div style={ { 
												padding: '5px 10px', 
												backgroundColor: '#f0f0f0',
												fontFamily: 'monospace',
												fontSize: '12px',
												marginTop: '5px'
											} }>
												{ content }
											</div>
										</div>
									) }
									{ previewLoading && (
										<div style={ { textAlign: 'center', padding: '10px' } }>
											<Spinner />
											<p>{ __( 'Loading preview...', 'shortcode-exec-php' ) }</p>
										</div>
									) }
									{ preview && ! previewLoading && (
										<div>
											<strong>{ __( 'Preview:', 'shortcode-exec-php' ) }</strong>
											<div style={ {
												border: '1px solid #ddd',
												padding: '10px',
												marginTop: '5px',
												backgroundColor: '#ffffff',
												minHeight: '40px'
											} }>
												<div dangerouslySetInnerHTML={ { __html: preview } } />
											</div>
										</div>
									) }
								</div>
							) }
						</CardBody>
					</Card>
				</div>
			);
		},
		
		save: function() {
			// Return null to use dynamic rendering (render_callback)
			return null;
		}
	} );
	
	/**
	 * Simple Shortcode Insert Block
	 * 
	 * Lightweight block for quick shortcode insertion without parameters
	 */
	registerBlockType( 'shortcode-exec-php/simple-shortcode', {
		title: __( 'Simple PHP Shortcode', 'shortcode-exec-php' ),
		description: __( 'Quick insert for PHP shortcodes without parameters', 'shortcode-exec-php' ),
		icon: {
			src: 'editor-code',
			background: '#2271b1',
			foreground: '#ffffff'
		},
		category: 'text',
		keywords: [
			__( 'php', 'shortcode-exec-php' ),
			__( 'shortcode', 'shortcode-exec-php' ),
			__( 'simple', 'shortcode-exec-php' )
		],
		supports: {
			html: false,
			align: false,
		},
		attributes: {
			shortcodeName: {
				type: 'string',
				default: ''
			}
		},
		
		edit: function( props ) {
			const { attributes, setAttributes } = props;
			const { shortcodeName } = attributes;
			const blockProps = useBlockProps();
			
			const [ shortcodes, setShortcodes ] = useState( [] );
			const [ loading, setLoading ] = useState( true );
			
			useEffect( () => {
				// Load shortcodes
				const formData = new FormData();
				formData.append( 'action', 'scep_get_shortcodes' );
				formData.append( 'nonce', shortcodeExecPHP.nonce );
				
				fetch( shortcodeExecPHP.ajaxUrl, {
					method: 'POST',
					body: formData
				} )
				.then( response => response.json() )
				.then( data => {
					if ( data.success ) {
						setShortcodes( data.data || [] );
					}
				} )
				.finally( () => {
					setLoading( false );
				} );
			}, [] );
			
			if ( loading ) {
				return (
					<div { ...blockProps }>
						<Spinner />
					</div>
				);
			}
			
			const shortcodeOptions = [
				{ label: __( 'Select shortcode...', 'shortcode-exec-php' ), value: '' },
				...shortcodes.map( shortcode => ( {
					label: shortcode.name,
					value: shortcode.name
				} ) )
			];
			
			return (
				<div { ...blockProps }>
					<SelectControl
						label={ __( 'PHP Shortcode', 'shortcode-exec-php' ) }
						value={ shortcodeName }
						options={ shortcodeOptions }
						onChange={ ( value ) => setAttributes( { shortcodeName: value } ) }
					/>
					{ shortcodeName && (
						<div style={ { 
							padding: '10px', 
							backgroundColor: '#f0f0f0',
							fontFamily: 'monospace',
							marginTop: '10px'
						} }>
							[{ shortcodeName }]
						</div>
					) }
				</div>
			);
		},
		
		save: function( props ) {
			const { shortcodeName } = props.attributes;
			return shortcodeName ? `[${shortcodeName}]` : '';
		}
	} );
	
} )();