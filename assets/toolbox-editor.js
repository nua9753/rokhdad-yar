/* رخدادیار — جعبه ابزار در ویرایشگر بلوکی (پنل جدا). */
( function ( wp ) {
	'use strict';

	var cfg = window.RokhdadyarToolbox;
	if ( ! cfg || ! wp || ! wp.plugins ) {
		return;
	}
	var h = wp.element.createElement;
	var C = wp.components;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! Panel ) {
		return;
	}

	function Toolbox() {
		var meta = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var edit = wp.data.useDispatch( 'core/editor' ).editPost;
		return h( Panel, { name: 'rokhdadyar-toolbox', title: cfg.title, className: 'chk-toolbox' },
			cfg.tools.map( function ( tool ) {
				return h( C.CheckboxControl, {
					key: tool.meta,
					label: tool.label,
					help: tool.help,
					checked: !! meta[ tool.meta ],
					onChange: function ( on ) {
						var m = {};
						m[ tool.meta ] = on ? Math.floor( Date.now() / 1000 ) : 0;
						edit( { meta: m } );
					},
					__nextHasNoMarginBottom: true,
				} );
			} )
		);
	}

	wp.plugins.registerPlugin( 'rokhdadyar-toolbox', { render: Toolbox } );
}( window.wp ) );
