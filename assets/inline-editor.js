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
	var useEffect = wp.element.useEffect;
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

	function num( n ) {
		return Number( n ).toLocaleString( document.documentElement.lang || undefined );
	}

	/* «۱۴۰ نویسه · ۴۲٪ کوتاه‌تر» */
	function delta( len, base ) {
		var out = t.chars.replace( '%s', num( len ) );
		if ( base > 0 ) {
			var d = Math.round( 100 * ( len - base ) / base );
			if ( d !== 0 ) {
				out += ' · ' + ( d < 0 ? t.shorter : t.longer ).replace( '%s', num( Math.abs( d ) ) );
			}
		}
		return out;
	}

	function Skeleton( props ) {
		return h( 'div', { className: 'chk-rw__wait', 'aria-live': 'polite' },
			h( 'p', { className: 'chk-rw__busy' }, h( 'span', { className: 'chk-rw__pulse' } ), t.working, props.secs > 0 ? h( 'b', null, t.secs.replace( '%s', num( props.secs ) ) ) : null ),
			[ 0, 1 ].map( function ( i ) {
				return h( 'div', { key: i, className: 'chk-rw__skel' }, h( 'i' ), h( 'i' ), h( 'i' ) );
			} )
		);
	}

	function Rewriter( props ) {
		var s = useState( null ); // { mode, status, items, msg, own, meta }
		var st = s[ 0 ];
		var set = s[ 1 ];
		var html = asHTML( props.attributes.content );
		var tick = useState( 0 );

		// شمارندهٔ ثانیه در حین انتظار.
		var busy = !! st && st.status === 'busy';
		useEffect( function () {
			if ( ! busy ) {
				return undefined;
			}
			tick[ 1 ]( 0 );
			var t0 = Date.now();
			var id = setInterval( function () {
				tick[ 1 ]( Math.floor( ( Date.now() - t0 ) / 1000 ) );
			}, 500 );
			return function () {
				clearInterval( id );
			};
		}, [ busy ] );

		// کلید ۱ و ۲ برای انتخاب سریع.
		var ready = !! st && st.status === 'result' ? st.items : null;
		useEffect( function () {
			if ( ! ready ) {
				return undefined;
			}
			function onKey( e ) {
				var tag = ( e.target && e.target.tagName ) || '';
				if ( tag === 'TEXTAREA' || tag === 'INPUT' || e.ctrlKey || e.metaKey || e.altKey ) {
					return;
				}
				var k = { '1': 0, '2': 1, '۱': 0, '۲': 1 }[ e.key ];
				if ( k !== undefined && ready[ k ] ) {
					e.preventDefault();
					pick( ready[ k ] );
				}
			}
			document.addEventListener( 'keydown', onKey, true );
			return function () {
				document.removeEventListener( 'keydown', onKey, true );
			};
		}, [ ready ] );

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
				var meta = [ src.model, res.cached ? t.cached : ( u && u.ms ? t.secs.replace( '%s', num( Math.round( u.ms / 100 ) / 10 ) ) : '' ) ].filter( Boolean ).join( ' · ' );
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
			var base = plainLen( html );
			body.push( h( 'details', { key: 'orig', className: 'chk-rw__orig' },
				h( 'summary', null, t.current, h( 'span', null, t.chars.replace( '%s', num( base ) ) ) ),
				h( RawHTML, { className: 'chk-rw__text' }, html )
			) );
			if ( st.status === 'busy' ) {
				body.push( h( Skeleton, { key: 'busy', secs: tick[ 0 ] } ) );
			}
			if ( st.status === 'error' ) {
				body.push( h( C.Notice, { key: 'err', status: 'error', isDismissible: false }, st.msg ) );
			}
			if ( st.status === 'result' ) {
				body.push( h( 'p', { key: 'hint', className: 'chk-rw__hint' }, t.hint ) );
				body.push( h( 'div', { key: 'opts', className: 'chk-rw__opts' }, st.items.map( function ( it, i ) {
					return h( 'button', {
						key: 'i' + i,
						type: 'button',
						className: 'chk-rw__opt',
						onClick: function () {
							pick( it );
						},
					},
					h( 'span', { className: 'chk-rw__opthead' },
						h( 'span', { className: 'chk-rw__num' }, num( i + 1 ) ),
						h( 'span', { className: 'chk-rw__kind' }, st.items.length > 1 ? ( i ? t.fresh : t.near ) : t.option.replace( /\s*[—-].*$/, '' ).replace( '%s', num( 1 ) ) ),
						h( 'span', { className: 'chk-rw__delta' }, delta( plainLen( it ), base ) ),
						h( 'span', { className: 'chk-rw__use' }, t.use )
					),
					h( RawHTML, { className: 'chk-rw__text' }, it )
					);
				} ) ) );
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
			if ( st.status === 'result' && st.meta ) {
				actions.push( h( 'span', { key: 'meta', className: 'chk-rw__meta' }, st.meta ) );
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
				className: 'chk-rw is-' + st.status,
				overlayClassName: 'chk-rw-overlay',
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
