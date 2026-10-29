/**
 * Editor side of the Bošnjački Insajder blocks + the "Udarna vijest" sidebar panel.
 * Plain script on the wp.* globals — no build step.
 */
( function ( wp ) {
	const el = wp.element.createElement;
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, ToggleControl, RangeControl, Disabled } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const meta = window.binsiderBlocks || {};

	// Post-context blocks can't be previewed via REST inside a Query Loop — show a labelled placeholder.
	const placeholders = {
		'binsider/post-label': 'RUBRIKA',
		'binsider/post-byline': 'Piše: Autor · 26. oktobar 2026. u 14:30 · 3 min čitanja',
		'binsider/post-lead': 'Uvodni pasus (polje „Sažetak")',
		'binsider/featured': '[ Naslovna fotografija sa potpisom ]',
		'binsider/share': 'PODIJELI: Facebook · WhatsApp · Viber · X · Telegram · Kopiraj link',
		'binsider/author-box': 'AUTOR — ime i biografija',
	};

	function controlFor( name, def, value, onChange ) {
		if ( def.enum ) {
			return el( SelectControl, {
				key: name, label: name, value,
				options: def.enum.map( ( v ) => ( { label: v, value: v } ) ),
				onChange,
			} );
		}
		if ( def.type === 'number' ) {
			return el( RangeControl, { key: name, label: name, value, min: 1, max: 30, onChange } );
		}
		if ( def.type === 'boolean' ) {
			return el( ToggleControl, { key: name, label: name, checked: !! value, onChange } );
		}
		return el( TextControl, { key: name, label: name, value: value || '', onChange } );
	}

	Object.keys( meta ).forEach( ( name ) => {
		const attrs = meta[ name ].attributes || {};
		registerBlockType( name, {
			apiVersion: 3,
			edit( { attributes, setAttributes } ) {
				const blockProps = useBlockProps();
				const controls = Object.keys( attrs )
					.filter( ( k ) => ! [ 'className', 'lock', 'metadata' ].includes( k ) )
					.map( ( k ) => controlFor( k, attrs[ k ], attributes[ k ], ( v ) => setAttributes( { [ k ]: v } ) ) );
				const preview = meta[ name ].postContext
					? el( 'div', { className: 'bi-editor-placeholder' }, placeholders[ name ] || name )
					: el( Disabled, null, el( ServerSideRender, { block: name, attributes, skipBlockSupportAttributes: true } ) );
				return el(
					'div',
					blockProps,
					controls.length ? el( InspectorControls, null, el( PanelBody, { title: 'Podešavanja' }, controls ) ) : null,
					preview
				);
			},
			save: () => null,
		} );
	} );

	// "Udarna vijest" panel in the post editor sidebar.
	const editorPkg = wp.editor || {};
	const PluginDocumentSettingPanel = editorPkg.PluginDocumentSettingPanel || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( wp.plugins && PluginDocumentSettingPanel && wp.data ) {
		const { useSelect, useDispatch } = wp.data;
		wp.plugins.registerPlugin( 'binsider-breaking', {
			render() {
				const postType = useSelect( ( s ) => s( 'core/editor' ).getCurrentPostType(), [] );
				const breaking = useSelect( ( s ) => ( s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} )._binsider_breaking, [] );
				const { editPost } = useDispatch( 'core/editor' );
				if ( postType !== 'post' ) {
					return null;
				}
				return el(
					PluginDocumentSettingPanel,
					{ name: 'binsider-breaking', title: 'Bošnjački Insajder', initialOpen: true },
					el( ToggleControl, {
						label: 'Udarna vijest',
						help: 'Prikazuje članak u crvenoj traci „UDARNO" na vrhu sajta (podrazumijevano 24 sata od objave).',
						checked: !! breaking,
						onChange: ( v ) => editPost( { meta: { _binsider_breaking: v } } ),
						__nextHasNoMarginBottom: true,
					} ),
					el( 'p', { style: { marginTop: '12px', color: '#555' } },
						'Glavna vijest na naslovnoj: u panelu „Status" uključite „Zalijepi na vrh bloga". Uvod (lead) upišite u polje „Sažetak".' )
				);
			},
		} );
	}
} )( window.wp );
