/* رخدادیار — بازنویسی پاراگراف در ویرایشگر کلاسیک: پنجرهٔ مشترک برای حالت «دیداری» (TinyMCE) و «متن» (Quicktags). */
( function () {
	'use strict';

	var cfg = window.RokhdadyarCfg || {};
	var t = cfg.i18n || {};
	if ( ! cfg.root ) {
		return;
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text ) {
			n.textContent = text;
		}
		return n;
	}

	function plainLen( html ) {
		var d = document.createElement( 'div' );
		d.innerHTML = html;
		return ( d.textContent || '' ).trim().length;
	}

	/* پنجره: ساده و مستقل از پوسته؛ کلیک بیرون آن را نمی‌بندد. */
	var wrap = null;
	function close() {
		if ( wrap ) {
			wrap.remove();
			wrap = null;
		}
		document.removeEventListener( 'keydown', esc, true );
		if ( cur && cur.focus ) {
			cur.focus();
		}
	}
	function esc( e ) {
		if ( e.key === 'Escape' && wrap && ! wrap.classList.contains( 'is-busy' ) ) {
			e.preventDefault();
			e.stopPropagation();
			close();
		}
	}
	function open( title ) {
		if ( wrap ) {
			wrap.remove();
		}
		wrap = el( 'div', 'chk-guard chk-rwc' );
		wrap.setAttribute( 'role', 'dialog' );
		wrap.setAttribute( 'aria-modal', 'true' );
		var box = el( 'div', 'chk-guard__box chk-rwc__box' );
		box.dir = document.documentElement.dir || 'rtl';
		var head = el( 'div', 'chk-rwc__head' );
		head.appendChild( el( 'h2', 'chk-rwc__title', title ) );
		var x = el( 'button', 'chk-pop__close', '×' );
		x.type = 'button';
		x.setAttribute( 'aria-label', t.close || '×' );
		x.addEventListener( 'click', function () {
			if ( ! wrap.classList.contains( 'is-busy' ) ) {
				close();
			}
		} );
		head.appendChild( x );
		box.appendChild( head );
		var body = el( 'div', 'chk-rwc__body' );
		box.appendChild( body );
		var foot = el( 'div', 'chk-guard__foot' );
		box.appendChild( foot );
		wrap.appendChild( box );
		wrap.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
		} );
		document.body.appendChild( wrap );
		document.addEventListener( 'keydown', esc, true );
		return { body: body, foot: foot };
	}

	function button( text, cls, fn ) {
		var b = el( 'button', 'button ' + ( cls || '' ), text );
		b.type = 'button';
		b.addEventListener( 'click', fn );
		return b;
	}

	var cur = null; // { html, replace(html), focus() }

	function run( mode, adapter ) {
		cur = adapter;
		var node = adapter;
		if ( ! adapter || plainLen( adapter.html ) < 20 ) {
			window.alert( t.rwNoPara );
			return;
		}
		if ( mode === 'own' ) {
			askOwn( node, '' );
			return;
		}
		request( node, mode, '', false );
	}

	function titleOf( mode ) {
		return ( t.rwLabel || '' ) + ' — ' + ( mode === 'short' ? t.rwShort : ( mode === 'long' ? t.rwLong : t.rwOwn ) );
	}

	function askOwn( node, value ) {
		var ui = open( titleOf( 'own' ) );
		var lab = el( 'label', 'chk-rwc__label', t.rwOwnLabel );
		var ta = el( 'textarea', 'chk-img__prompt' );
		ta.rows = 3;
		ta.dir = 'auto';
		ta.placeholder = t.rwOwnPh;
		ta.value = value || '';
		lab.appendChild( ta );
		ui.body.appendChild( lab );
		var go = function () {
			if ( ta.value.trim() ) {
				request( node, 'own', ta.value.trim(), true );
			} else {
				ta.focus();
			}
		};
		ta.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ( e.ctrlKey || e.metaKey ) ) {
				e.preventDefault();
				go();
			}
		} );
		ui.foot.appendChild( button( t.rwGo, 'button-primary', go ) );
		ui.foot.appendChild( button( t.rwCancel, '', close ) );
		ta.focus();
	}

	function request( node, mode, own, fresh ) {
		var html = node.html;
		var ui = open( titleOf( mode ) );
		wrap.classList.add( 'is-busy' );
		ui.body.appendChild( el( 'p', 'chk-rw__busy', t.working ) );
		var title = document.getElementById( 'title' );
		var idEl = document.getElementById( 'post_ID' );
		fetch( cfg.root + 'rewrite', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( { post_id: idEl ? parseInt( idEl.value, 10 ) : 0, html: html, mode: mode, prompt: own, title: title ? title.value : '', fresh: !! fresh } ),
		} ).then( function ( r ) {
			return r.json().catch( function () {
				return {};
			} ).then( function ( j ) {
				if ( ! r.ok ) {
					throw new Error( j.message || t.error );
				}
				return j;
			} );
		} ).then( function ( res ) {
			show( node, html, mode, own, res );
		} ).catch( function ( e ) {
			var u = open( titleOf( mode ) );
			u.body.appendChild( el( 'p', 'chk-pop__error', e.message || t.error ) );
			u.foot.appendChild( button( t.retry, '', function () {
				request( node, mode, own, true );
			} ) );
			u.foot.appendChild( button( t.rwCancel, '', close ) );
		} );
	}

	function show( node, html, mode, own, res ) {
		var ui = open( titleOf( mode ) );
		var src = res.source || {};
		var u = src.usage;
		ui.body.appendChild( el( 'p', 'chk-rw__meta', [ src.model, res.cached ? t.cached : ( u && u.ms ? t.secs.replace( '%s', ( Math.round( u.ms / 100 ) / 10 ).toLocaleString() ) : '' ) ].filter( Boolean ).join( ' · ' ) ) );
		var orig = el( 'div', 'chk-rw__orig' );
		orig.appendChild( el( 'span', 'chk-rw__tag', t.rwCurrent + ' · ' + t.rwChars.replace( '%s', plainLen( html ).toLocaleString() ) ) );
		var ot = el( 'div', 'chk-rw__text' );
		ot.innerHTML = html;
		orig.appendChild( ot );
		ui.body.appendChild( orig );
		( res.items || [] ).forEach( function ( it, i ) {
			var b = el( 'button', 'chk-rw__opt' );
			b.type = 'button';
			b.appendChild( el( 'span', 'chk-rw__tag', t.rwOption.replace( '%s', ( i + 1 ).toLocaleString() ) + ' · ' + t.rwChars.replace( '%s', plainLen( it ).toLocaleString() ) ) );
			var tx = el( 'div', 'chk-rw__text' );
			tx.innerHTML = it; // از سرور با wp_kses پاک‌سازی شده است.
			b.appendChild( tx );
			b.addEventListener( 'click', function () {
				node.replace( it );
				close();
			} );
			ui.body.appendChild( b );
		} );
		if ( mode === 'own' ) {
			ui.foot.appendChild( button( t.rwEdit, '', function () {
				askOwn( node, own );
			} ) );
		}
		ui.foot.appendChild( button( t.retry, '', function () {
			request( node, mode, own, true );
		} ) );
		ui.foot.appendChild( button( t.rwCancel, '', close ) );
	}


	window.RokhdadyarRewrite = { run: run };

	/* حالت «متن»: دکمه در نوار Quicktags؛ متنِ انتخاب‌شده بازنویسی می‌شود. */
	if ( window.QTags ) {
		window.QTags.addButton( 'rokhdadyar', t.rwLabel, function ( btn, area ) {
			var s = area.selectionStart;
			var e = area.selectionEnd;
			if ( s === e ) {
				window.alert( t.rwSelect );
				return;
			}
			var adapter = {
				html: area.value.slice( s, e ),
				focus: function () {
					area.focus();
				},
				replace: function ( html ) {
					area.value = area.value.slice( 0, s ) + html + area.value.slice( e );
					area.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				},
			};
			// انتخاب نوع بازنویسی در همان پنجره.
			cur = adapter;
			var ui = open( t.rwLabel );
			[ [ 'short', t.rwShort ], [ 'long', t.rwLong ], [ 'own', t.rwOwn ] ].forEach( function ( m ) {
				ui.foot.appendChild( button( m[ 1 ], 'short' === m[ 0 ] ? 'button-primary' : '', function () {
					run( m[ 0 ], adapter );
				} ) );
			} );
			ui.body.appendChild( el( 'p', 'chk-rw__meta', t.rwPickMode ) );
		}, '', '', t.rwLabel, 200, 'content' );
	}
}() );
