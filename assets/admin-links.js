/* رخدادیار — ماژول لینک داخلی به مطالب مهم. به هستهٔ admin.js وابسته است. */
( function () {
	'use strict';

	var R = window.Rokhdadyar;
	if ( ! R ) {
		return;
	}
	var cfg = R.cfg;
	var t = R.t;
	var inEditor = R.inEditor;
	var titleField = R.titleField;
	function p( name ) {
		return function () {
			return R[ name ].apply( null, arguments );
		};
	}
	var el = p( 'el' );
	var api = p( 'api' );
	var close = p( 'close' );
	var shell = p( 'shell' );
	var part = p( 'part' );
	var place = p( 'place' );
	var showLoading = p( 'showLoading' );
	var showError = p( 'showError' );
	var retryBtn = p( 'retryBtn' );
	var usageText = p( 'usageText' );
	var liveContent = p( 'liveContent' );
	var markMade = p( 'markMade' );
	var done = p( 'done' );

	/**
	 * لینک روی اولین رخدادِ عبارت؛ نه داخل لینک دیگر، تیتر، کد یا توضیح بلوک‌ها.
	 * همان الگوریتمِ سمت سرور (Links::insert)؛ ساختار بلوک‌ها دست نمی‌خورد.
	 */
	function linkify( html, anchor, url ) {
		var parts = html.split( /(<!--[\s\S]*?-->|<[^>]+>)/ );
		var skip = 0;
		var ok = false;
		var open = /^<(a|h[1-6]|code|pre|figcaption|button|script|style)[\s>]/i;
		var shut = /^<\/(a|h[1-6]|code|pre|figcaption|button|script|style)>/i;
		for ( var i = 0; i < parts.length; i++ ) {
			var s = parts[ i ];
			if ( ! s ) {
				continue;
			}
			if ( s.charAt( 0 ) === '<' ) {
				if ( open.test( s ) ) {
					skip++;
				} else if ( shut.test( s ) ) {
					skip = Math.max( 0, skip - 1 );
				}
				continue;
			}
			if ( ok || skip ) {
				continue;
			}
			var pos = s.indexOf( anchor );
			if ( pos > -1 ) {
				var a = document.createElement( 'a' );
				a.href = url;
				parts[ i ] = s.slice( 0, pos ) + '<a href="' + a.href.replace( /"/g, '&quot;' ) + '">' + anchor + '</a>' + s.slice( pos + anchor.length );
				ok = true;
			}
		}
		return { html: parts.join( '' ), ok: ok };
	}
	R.linkify = linkify;

	function runLinks( btn, fresh ) {
		var postId = parseInt( btn.getAttribute( 'data-chk-post' ), 10 );
		if ( R.state && R.state.btn !== btn ) {
			close();
		}
		R.state = { btn: btn, task: 'links', postId: postId };
		btn.classList.add( 'is-open' );
		if ( ! cfg.ready ) {
			showError( 'links', t.notReady, true );
			return;
		}
		btn.classList.add( 'is-busy' );
		showLoading( 'links' );
		var data = { post_id: postId, fresh: !! fresh };
		if ( inEditor ) {
			data.content = liveContent();
			data.title = titleField.value;
		}
		api( 'links', data ).then( function ( res ) {
			markMade( postId, 'links' );
			if ( ! R.state || R.state.btn !== btn ) {
				return;
			}
			btn.classList.remove( 'is-busy' );
			showLinks( res );
		} ).catch( function ( e ) {
			if ( R.state && R.state.btn === btn ) {
				btn.classList.remove( 'is-busy' );
				showError( 'links', e.message );
			}
		} );
	}

	function showLinks( res ) {
		shell( 'links' );
		var src = res.source || {};
		part( 'meta' ).textContent = [
			src.model,
			src.total ? t.lSent.replace( '%1$s', src.sent ).replace( '%2$s', src.total ) : '',
			res.cached ? t.cached : usageText( src.usage ),
		].filter( Boolean ).join( ' · ' );
		var b = part( 'body' );
		var items = res.items || [];
		if ( ! items.length ) {
			b.appendChild( el( 'p', 'chk-pop__note', t.lNone ) );
		} else {
			b.appendChild( el( 'p', 'chk-pop__note', t.lPick ) );
		}
		var boxes = [];
		items.forEach( function ( it ) {
			var row = el( 'label', 'chk-link' );
			var c = el( 'input' );
			c.type = 'checkbox';
			c.checked = true;
			boxes.push( { c: c, it: it } );
			row.appendChild( c );
			var txt = el( 'span', 'chk-link__text' );
			txt.appendChild( el( 'mark', 'chk-link__anchor', it.anchor ) );
			txt.appendChild( document.createTextNode( ' ← ' ) );
			var a = el( 'a', 'chk-link__to', it.title );
			a.href = it.url;
			a.target = '_blank';
			a.rel = 'noopener';
			txt.appendChild( a );
			row.appendChild( txt );
			b.appendChild( row );
		} );

		var f = part( 'foot' );
		f.appendChild( retryBtn() );
		if ( items.length ) {
			var ok = el( 'button', 'button button-primary', t.lApply );
			ok.type = 'button';
			ok.addEventListener( 'click', function () {
				var picked = boxes.filter( function ( x ) {
					return x.c.checked;
				} ).map( function ( x ) {
					return x.it;
				} );
				if ( picked.length ) {
					applyLinks( picked );
				}
			} );
			f.appendChild( ok );
		}
		place();
	}

	/** ویرایشگر کلاسیک: مستقیم در متن (با ذخیرهٔ نوشته ثبت می‌شود). فهرست نوشته‌ها: سمت سرور. */
	function applyLinks( items ) {
		if ( inEditor ) {
			var mce = window.tinymce && window.tinymce.get( 'content' );
			var visual = mce && ! mce.isHidden();
			var area = document.getElementById( 'content' );
			var html = visual ? mce.getContent() : ( area ? area.value : '' );
			items.forEach( function ( it ) {
				html = linkify( html, it.anchor, it.url ).html;
			} );
			if ( visual ) {
				mce.setContent( html );
				mce.fire( 'change' );
			} else if ( area ) {
				area.value = html;
				area.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
			done( 'links' );
			return;
		}
		R.pop.classList.add( 'is-saving' );
		api( 'links/apply', { post_id: R.state.postId, items: items } ).then( function () {
			done( 'links' );
		} ).catch( function ( e ) {
			showError( 'links', e.message );
		} );
	}

	R.runLinks = runLinks;
	R.showLinks = showLinks;
}() );
