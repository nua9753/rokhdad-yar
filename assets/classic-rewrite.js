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

	function num( n ) {
		return Number( n ).toLocaleString( document.documentElement.lang || undefined );
	}

	function delta( len, base ) {
		var out = t.rwChars.replace( '%s', num( len ) );
		if ( base > 0 ) {
			var d = Math.round( 100 * ( len - base ) / base );
			if ( d !== 0 ) {
				out += ' · ' + ( d < 0 ? t.rwShorter : t.rwLonger ).replace( '%s', num( Math.abs( d ) ) );
			}
		}
		return out;
	}

	/* «پاراگراف فعلی» جمع‌شده، تا جای نسخه‌ها را نگیرد. */
	function original( html ) {
		var d = el( 'details', 'chk-rw__orig' );
		var sm = el( 'summary', '', t.rwCurrent );
		sm.appendChild( el( 'span', '', t.rwChars.replace( '%s', num( plainLen( html ) ) ) ) );
		d.appendChild( sm );
		var tx = el( 'div', 'chk-rw__text' );
		tx.innerHTML = html;
		d.appendChild( tx );
		return d;
	}

	var picks = [];
	function pickKey( e ) {
		var tag = ( e.target && e.target.tagName ) || '';
		if ( tag === 'TEXTAREA' || tag === 'INPUT' || e.ctrlKey || e.metaKey || e.altKey ) {
			return;
		}
		var k = { '1': 0, '2': 1, '۱': 0, '۲': 1 }[ e.key ];
		if ( k !== undefined && picks[ k ] ) {
			e.preventDefault();
			e.stopPropagation();
			picks[ k ]();
		}
	}

	/* پنجره: ساده و مستقل از پوسته؛ کلیک بیرون آن را نمی‌بندد. */
	var wrap = null;
	function close() {
		if ( timer ) {
			clearInterval( timer );
			timer = null;
		}
		document.removeEventListener( 'keydown', pickKey, true );
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
	var timer = null;
	function open( title ) {
		if ( timer ) {
			clearInterval( timer );
			timer = null;
		}
		document.removeEventListener( 'keydown', pickKey, true );
		if ( wrap ) {
			wrap.remove();
		}
		wrap = el( 'div', 'chk-guard chk-rwc' );
		wrap.setAttribute( 'role', 'dialog' );
		wrap.setAttribute( 'aria-modal', 'true' );
		var box = el( 'div', 'chk-guard__box chk-rwc__box' );
		box.dir = document.documentElement.dir || 'rtl';
		var head = el( 'div', 'chk-rwc__head' );
		var h2 = el( 'h2', 'chk-rwc__title' );
		if ( cfg.mark ) {
			var mk = el( 'img', 'chk-rwc__mark' );
			mk.src = cfg.mark;
			mk.alt = '';
			h2.appendChild( mk );
		}
		h2.appendChild( document.createTextNode( title ) );
		head.appendChild( h2 );
		var x = el( 'button', 'chk-rwc__close' );
		x.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"/></svg>';
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
		var foot = el( 'div', 'chk-guard__foot chk-rwc__foot' );
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
		ui.body.appendChild( original( node.html ) );
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
		ui.body.appendChild( original( html ) );
		var wait = el( 'div', 'chk-rw__wait' );
		wait.setAttribute( 'aria-live', 'polite' );
		var line = el( 'p', 'chk-rw__busy' );
		line.appendChild( el( 'span', 'chk-rw__pulse' ) );
		line.appendChild( document.createTextNode( t.working ) );
		var secs = el( 'b' );
		line.appendChild( secs );
		wait.appendChild( line );
		[ 0, 1 ].forEach( function () {
			var sk = el( 'div', 'chk-rw__skel' );
			sk.innerHTML = '<i></i><i></i><i></i>';
			wait.appendChild( sk );
		} );
		ui.body.appendChild( wait );
		var t0 = Date.now();
		timer = setInterval( function () {
			var n = Math.floor( ( Date.now() - t0 ) / 1000 );
			secs.textContent = n > 0 ? t.secs.replace( '%s', num( n ) ) : '';
		}, 500 );
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
		var meta = [ src.model, res.cached ? t.cached : ( u && u.ms ? t.secs.replace( '%s', num( Math.round( u.ms / 100 ) / 10 ) ) : '' ) ].filter( Boolean ).join( ' · ' );
		ui.body.appendChild( original( html ) );
		var base = plainLen( html );
		var items = res.items || [];
		ui.body.appendChild( el( 'p', 'chk-rw__hint', t.rwHint ) );
		var list = el( 'div', 'chk-rw__opts' );
		picks = [];
		items.forEach( function ( it, i ) {
			var b = el( 'button', 'chk-rw__opt' );
			b.type = 'button';
			var hd = el( 'span', 'chk-rw__opthead' );
			hd.appendChild( el( 'span', 'chk-rw__num', num( i + 1 ) ) );
			hd.appendChild( el( 'span', 'chk-rw__kind', items.length > 1 ? ( i ? t.rwFresh : t.rwNear ) : t.rwNear ) );
			hd.appendChild( el( 'span', 'chk-rw__delta', delta( plainLen( it ), base ) ) );
			hd.appendChild( el( 'span', 'chk-rw__use', t.rwUse ) );
			b.appendChild( hd );
			var tx = el( 'div', 'chk-rw__text' );
			tx.innerHTML = it; // از سرور با wp_kses پاک‌سازی شده است.
			b.appendChild( tx );
			var go = function () {
				node.replace( it );
				close();
			};
			picks.push( go );
			b.addEventListener( 'click', go );
			list.appendChild( b );
		} );
		ui.body.appendChild( list );
		document.addEventListener( 'keydown', pickKey, true );
		if ( mode === 'own' ) {
			ui.foot.appendChild( button( t.rwEdit, '', function () {
				askOwn( node, own );
			} ) );
		}
		ui.foot.appendChild( button( t.retry, '', function () {
			request( node, mode, own, true );
		} ) );
		ui.foot.appendChild( button( t.rwCancel, '', close ) );
		if ( meta ) {
			ui.foot.appendChild( el( 'span', 'chk-rw__meta', meta ) );
		}
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
