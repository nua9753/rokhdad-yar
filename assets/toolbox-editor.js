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
		var val = wp.data.useSelect( function ( select ) {
			var meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			return meta[ cfg.meta ] || 0;
		}, [] );
		var edit = wp.data.useDispatch( 'core/editor' ).editPost;
		return h( Panel, { name: 'rokhdadyar-toolbox', title: cfg.title, className: 'chk-toolbox' },
			h( C.CheckboxControl, {
				label: cfg.pin,
				help: cfg.help,
				checked: !! val,
				onChange: function ( on ) {
					var m = {};
					m[ cfg.meta ] = on ? Math.floor( Date.now() / 1000 ) : 0;
					edit( { meta: m } );
				},
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	wp.plugins.registerPlugin( 'rokhdadyar-toolbox', { render: Toolbox } );
}( window.wp ) );
