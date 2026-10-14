/* رخدادیار — بازنویسی پاراگراف در ویرایشگر بلوکی: دکمه در نوار ابزارِ بلوک پاراگراف (کوتاه‌تر / بلندتر / پرامپت خودم). */
( function ( wp ) {
	'use strict';

	var cfg = window.RokhdadyarInline;
	if ( ! cfg || ! wp || ! wp.hooks || ! wp.compose || ! wp.blockEditor ) {
		return;
	}
	var h = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var RawHTML = wp.element.RawHTML;
	var C = wp.components;
	var BE = wp.blockEditor;
	var t = cfg.i18n;
	var BLOCKS = [ 'core/paragraph' ];

	var icon = h( 'svg', { viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true },
		h( 'path', { fill: 'currentColor', d: 'M12 2l1.9 5.5a3.3 3.3 0 0 0 2.1 2.1L21.5 11.5l-5.5 1.9a3.3 3.3 0 0 0-2.1 2.1L12 21l-1.9-5.5a3.3 3.3 0 0 0-2.1-2.1L2.5 11.5 8 9.6a3.3 3.3 0 0 0 2.1-2.1z' } )
	);

	function asHTML( c ) {
		if ( ! c ) {
			return '';
		}
		if ( typeof c === 'string' ) {
			return c;
		}
		return typeof c.toHTMLString === 'function' ? c.toHTMLString() : String( c );
	}

	function plainLen( html ) {
		var d = document.createElement( 'div' );
		d.innerHTML = html;
		return ( d.textContent || '' ).trim().length;
	}

	function Rewriter( props ) {
		var s = useState( null ); // { mode, status, items, msg, own, meta }
		var st = s[ 0 ];
		var set = s[ 1 ];
		var html = asHTML( props.attributes.content );

		function ask( mode, own, fresh ) {
			set( { mode: mode, status: 'busy', own: own || '' } );
			var ed = wp.data.select( 'core/editor' );
			wp.apiFetch( {
				path: '/rokhdadyar/v1/rewrite',
				method: 'POST',
				data: {
					post_id: ed.getCurrentPostId(),
					html: html,
					mode: mode,
					prompt: own || '',
					title: ed.getEditedPostAttribute( 'title' ) || '',
					fresh: !! fresh,
				},
			} ).then( function ( res ) {
				var src = res.source || {};
				var u = src.usage;
				var meta = [ src.model, res.cached ? t.cached : ( u && u.ms ? t.secs.replace( '%s', ( Math.round( u.ms / 100 ) / 10 ).toLocaleString() ) : '' ) ].filter( Boolean ).join( ' · ' );
				set( { mode: mode, status: 'result', items: res.items || [], own: own || '', meta: meta } );
			} ).catch( function ( e ) {
				set( { mode: mode, status: 'error', msg: ( e && e.message ) || t.error, own: own || '' } );
			} );
		}

		function pick( item ) {
			wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( props.clientId, { content: item } );
			set( null );
		}

		var menu = h( BE.BlockControls, { group: 'other' },
			h( C.ToolbarGroup, null,
				h( C.ToolbarDropdownMenu, {
					icon: icon,
					label: t.label,
					controls: [
						{ title: t.short, onClick: function () {
							ask( 'short' );
						} },
						{ title: t.long, onClick: function () {
							ask( 'long' );
						} },
						{ title: t.own, onClick: function () {
							set( { mode: 'own', status: 'ask', own: '' } );
						} },
					],
				} )
			)
		);

		var modal = null;
		if ( st ) {
			var body = [];
			var title = st.mode === 'short' ? t.short : ( st.mode === 'long' ? t.long : t.own );
			if ( st.mode === 'own' && ( st.status === 'ask' || st.status === 'error' || st.status === 'result' ) ) {
				body.push( h( C.TextareaControl, {
					key: 'own',
					label: t.ownLabel,
					value: st.own,
					rows: 3,
					placeholder: t.ownPh,
					onChange: function ( v ) {
						set( Object.assign( {}, st, { own: v } ) );
					},
					onKeyDown: function ( e ) {
						if ( e.key === 'Enter' && ( e.ctrlKey || e.metaKey ) && st.own.trim() ) {
							e.preventDefault();
							ask( 'own', st.own.trim(), true );
						}
					},
					__nextHasNoMarginBottom: true,
				} ) );
			}
			if ( st.status === 'busy' ) {
				body.push( h( 'p', { key: 'busy', className: 'chk-rw__busy' }, h( C.Spinner ), ' ', t.working ) );
			}
			if ( st.status === 'error' ) {
				body.push( h( C.Notice, { key: 'err', status: 'error', isDismissible: false }, st.msg ) );
			}
			if ( st.status === 'result' ) {
				body.push( h( 'p', { key: 'meta', className: 'chk-rw__meta' }, st.meta ) );
				body.push( h( 'div', { key: 'orig', className: 'chk-rw__orig' },
					h( 'span', { className: 'chk-rw__tag' }, t.current + ' · ' + t.chars.replace( '%s', plainLen( html ).toLocaleString() ) ),
					h( RawHTML, { className: 'chk-rw__text' }, html )
				) );
				st.items.forEach( function ( it, i ) {
					body.push( h( 'button', {
						key: 'i' + i,
						type: 'button',
						className: 'chk-rw__opt',
						onClick: function () {
							pick( it );
						},
					},
					h( 'span', { className: 'chk-rw__tag' }, t.option.replace( '%s', ( i + 1 ).toLocaleString() ) + ' · ' + t.chars.replace( '%s', plainLen( it ).toLocaleString() ) ),
					h( RawHTML, { className: 'chk-rw__text' }, it )
					) );
				} );
			}
			var actions = [];
			if ( st.mode === 'own' && st.status !== 'busy' ) {
				actions.push( h( C.Button, {
					key: 'go',
					variant: 'primary',
					disabled: ! ( st.own || '' ).trim(),
					onClick: function () {
						ask( 'own', st.own.trim(), true );
					},
				}, st.status === 'result' ? t.retry : t.go ) );
			} else if ( st.status === 'result' || st.status === 'error' ) {
				actions.push( h( C.Button, {
					key: 'retry',
					variant: 'secondary',
					onClick: function () {
						ask( st.mode, st.own, true );
					},
				}, t.retry ) );
			}
			actions.push( h( C.Button, {
				key: 'cancel',
				variant: 'tertiary',
				onClick: function () {
					set( null );
				},
			}, t.cancel ) );
			body.push( h( 'div', { key: 'act', className: 'chk-rw__actions' }, actions ) );

			modal = h( C.Modal, {
				title: t.label + ' — ' + title,
				onRequestClose: function () {
					if ( st.status !== 'busy' ) {
						set( null );
					}
				},
				shouldCloseOnClickOutside: false,
				className: 'chk-rw',
			}, body );
		}

		return h( Fragment, null, props.isSelected ? menu : null, modal );
	}

	var withRewrite = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			// برای همهٔ پاراگراف‌ها سوار می‌شود (نه فقط انتخاب‌شده) تا اگر انتخاب در حین کار عوض شد، پنجرهٔ نتیجه از بین نرود.
			if ( BLOCKS.indexOf( props.name ) === -1 ) {
				return h( BlockEdit, props );
			}
			return h( Fragment, null, h( BlockEdit, props ), h( Rewriter, props ) );
		};
	}, 'withRokhdadyarRewrite' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'rokhdadyar/rewrite', withRewrite );
}( window.wp ) );
