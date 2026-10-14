/* رخدادیار — دکمهٔ بازنویسی پاراگراف در نوار ابزار TinyMCE (حالت «دیداری» ویرایشگر کلاسیک). */
( function () {
	'use strict';

	if ( ! window.tinymce ) {
		return;
	}

	window.tinymce.PluginManager.add( 'rokhdadyar', function ( editor ) {
		var cfg = window.RokhdadyarCfg || {};
		var t = cfg.i18n || {};

		function go( mode ) {
			if ( ! window.RokhdadyarRewrite ) {
				return;
			}
			var node = editor.dom.getParent( editor.selection.getStart(), 'p,li', editor.getBody() ) || editor.dom.getParent( editor.selection.getNode(), 'p,li', editor.getBody() );
			window.RokhdadyarRewrite.run( mode, node ? {
				html: node.innerHTML,
				focus: function () {
					editor.focus();
				},
				replace: function ( html ) {
					editor.undoManager.transact( function () {
						editor.dom.setHTML( node, html );
					} );
					editor.fire( 'change' );
					editor.nodeChanged();
				},
			} : null );
		}

		editor.addButton( 'rokhdadyar', {
			type: 'menubutton',
			image: cfg.mark,
			tooltip: t.rwLabel,
			menu: [
				{ text: t.rwShort, onclick: function () {
					go( 'short' );
				} },
				{ text: t.rwLong, onclick: function () {
					go( 'long' );
				} },
				{ text: t.rwOwn, onclick: function () {
					go( 'own' );
				} },
			],
		} );
	} );
}() );
