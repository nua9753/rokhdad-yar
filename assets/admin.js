/* رخدادیار — فهرست نوشته‌ها و ویرایشگر کلاسیک: هسته (ابزارها، پنجره، رویدادها). بدون وابستگی.
 * ماژول‌ها: admin.js (هسته) ← admin-text.js ← admin-image.js ← admin-terms.js؛ همه از طریق window.Rokhdadyar به هم وصل‌اند. */
( function () {
	'use strict';

	var cfg = window.RokhdadyarCfg;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;
	var R = window.Rokhdadyar = { cfg: cfg, t: t, pop: null, state: null }; // state: { btn, task, postId, items }

	// از ماژول‌های دیگر (هنگام فراخوانی پیدا می‌شوند).
	function dropImage() {
		return R.dropImage.apply( null, arguments );
	}
	function run() {
		return R.run.apply( null, arguments );
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text != null ) {
			n.textContent = text;
		}
		return n;
	}

	function api( path, data ) {
		// مهلت سمت مرورگر: اگر سرور یا مدل گیر کند، پنجره بی‌انتها منتظر نمی‌ماند.
		var ctrl = window.AbortController ? new AbortController() : null;
		var timer = ctrl ? setTimeout( function () {
			ctrl.abort();
		}, 150000 ) : 0;
		return fetch( cfg.root + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( data ),
			signal: ctrl ? ctrl.signal : undefined,
		} ).catch( function ( e ) {
			throw new Error( e && e.name === 'AbortError' ? t.tooSlow : ( e && e.message ) || t.error );
		} ).then( function ( r ) {
			clearTimeout( timer );
			// پاسخ HTML به‌جای JSON معمولاً یعنی سرور (nginx/PHP) پیش از تمام شدن کار، اتصال را بریده است.
			if ( r.status === 502 || r.status === 504 ) {
				throw new Error( t.tooSlow );
			}
			return r.json().catch( function () {
				return {};
			} ).then( function ( j ) {
				if ( ! r.ok ) {
					throw new Error( j.message || t.error + ' ' + r.status );
				}
				return j;
			} );
		} );
	}

	/* مصرف واقعی همین درخواست، آن‌طور که خودِ سرویس گزارش کرده است. */
	function usageText( u ) {
		if ( ! u ) {
			return '';
		}
		var out = [];
		if ( u.in + u.out ) {
			var tok = t.tokens.replace( '%s', ( u.in + u.out ).toLocaleString() );
			out.push( u.cached ? tok + ' (' + t.tokCached.replace( '%s', u.cached.toLocaleString() ) + ')' : tok );
		}
		if ( u.ms ) {
			// زمانِ خودِ مدل؛ اگر بزرگ است، کندی از سرویس است نه از سایت.
			out.push( t.secs.replace( '%s', ( Math.round( u.ms / 100 ) / 10 ).toLocaleString() ) );
		}
		return out.join( ' · ' );
	}

	/* نشانگرهای سئو: طول در بازهٔ مناسب گوگل و حضور کلیدواژهٔ کانونی. */
	function norm( s ) {
		return String( s ).toLowerCase().replace( /[\u200c\u200d]/g, '' ).replace( /ي/g, 'ی' ).replace( /ك/g, 'ک' ).replace( /\s+/g, ' ' );
	}
	function badges( task, text, keyword ) {
		var wrap = el( 'span', 'chk-badges' );
		var len = text.length;
		var ok = len >= cfg[ task + 'Min' ] && len <= cfg[ task + 'Max' ];
		wrap.appendChild( el( 'span', 'chk-badge ' + ( ok ? 'is-ok' : 'is-warn' ), len + ' ' + t.chars ) );
		// نامکِ انگلیسی را نمی‌شود با کلیدواژهٔ فارسی سنجید.
		if ( keyword && ! ( task === 'slug' && cfg.slugLang !== 'fa' ) ) {
			var flat = function ( x ) {
				return task === 'slug' ? String( x ).replace( /[\s\u200c-]/g, '' ) : x;
			};
			var has = norm( flat( text ) ).indexOf( norm( flat( keyword ) ) ) !== -1;
			wrap.appendChild( el( 'span', 'chk-badge ' + ( has ? 'is-ok' : 'is-warn' ), has ? '✓ ' + t.keyword : t.noKeyword ) );
		}
		return wrap;
	}
	function option( task, item, keyword, onPick, label ) {
		var o = el( 'button', 'chk-opt' );
		o.type = 'button';
		o.appendChild( el( 'span', 'chk-opt__text', item ) );
		if ( task === 'slug' && cfg.slugLang !== 'fa' ) {
			o.firstChild.dir = 'ltr';
			o.firstChild.classList.add( 'chk-ltr' );
		}
		var bs = badges( task, item, keyword );
		if ( label ) {
			bs.insertBefore( el( 'span', 'chk-badge is-style', label ), bs.firstChild );
		}
		o.appendChild( bs );
		o.addEventListener( 'click', function () {
			onPick( item, o );
		} );
		return o;
	}

	/* ویرایشگر کلاسیک: متن و عنوانِ هنوز ذخیره‌نشده را می‌خوانیم و نتیجه را در همان فیلدها می‌گذاریم. */
	var titleField = R.titleField = document.getElementById( 'title' );
	var inEditor = R.inEditor = !! ( titleField && document.getElementById( 'post' ) );

	function liveContent() {
		var ed = window.tinymce && window.tinymce.get( 'content' );
		if ( ed && ! ed.isHidden() ) {
			return ed.getContent();
		}
		var ta = document.getElementById( 'content' );
		return ta ? ta.value : '';
	}

	function setField( node, value ) {
		// اگر فیلد یک ویرایشگر دیداری دارد (مثل «توضیح کوتاه» ووکامرس)، همان را هم به‌روز می‌کنیم.
		var ed = window.tinymce && node.id && window.tinymce.get( node.id );
		if ( ed && ! ed.isHidden() ) {
			ed.setContent( value.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ) );
			ed.save();
		}
		node.value = value;
		node.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		node.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	if ( inEditor ) {
		// جعبهٔ «چکیده» در وردپرس کلاسیک پیش‌فرض پنهان است؛ دکمه را داخلش هم می‌گذاریم تا هر وقت باز شد دمِ دست باشد.
		var sideBtn = document.querySelector( '#rokhdadyar-box [data-chk-task="excerpt"]' );
		var exBox = document.querySelector( '#postexcerpt .inside' );
		if ( sideBtn && exBox ) {
			var holder = el( 'div', 'chk-inline chk-inline--excerpt' );
			var clone = sideBtn.cloneNode( true );
			clone.classList.remove( 'chk-btn--wide' );
			holder.appendChild( clone );
			exBox.insertBefore( holder, exBox.firstChild );
		}
	}

	function revealExcerptBox() {
		var box = document.getElementById( 'postexcerpt' );
		var toggle = document.getElementById( 'postexcerpt-hide' );
		if ( box && toggle && ! toggle.checked ) {
			toggle.click();
		}
		if ( box ) {
			box.classList.remove( 'closed' );
			box.classList.add( 'chk-flash' );
			setTimeout( function () {
				box.classList.remove( 'chk-flash' );
			}, 1800 );
		}
	}

	function close() {
		if ( R.pop ) {
			R.pop.remove();
			R.pop = null;
		}
		if ( R.state && R.state.btn ) {
			R.state.btn.classList.remove( 'is-busy', 'is-open' );
		}
		// پیش‌نمایشِ تأییدنشدهٔ تصویر با بستن پاک نمی‌شود؛ با کلیک دوباره روی همان دکمه برمی‌گردد.
		R.state = null;
	}

	function place() {
		if ( ! R.pop || ! R.state ) {
			return;
		}
		var r = R.state.btn.getBoundingClientRect();
		R.pop.style.maxHeight = '';
		var w = R.pop.offsetWidth;
		var h = R.pop.offsetHeight;
		var left = Math.min( Math.max( 8, r.left + r.width / 2 - w / 2 ), window.innerWidth - w - 8 );
		var below = window.innerHeight - r.bottom - 16;
		var above = r.top - 16;
		var top;
		if ( h <= below ) {
			top = r.bottom + 8;
		} else if ( h <= above ) {
			top = r.top - h - 8;
		} else {
			// هیچ طرف جا ندارد: سمتِ بزرگ‌تر، با ارتفاعِ محدود و اسکرول داخلی؛ پایین پنجره هیچ‌وقت از صفحه بیرون نمی‌زند.
			var room = Math.max( below, above );
			R.pop.style.maxHeight = room + 'px';
			top = below >= above ? r.bottom + 8 : r.top - room - 8;
		}
		R.pop.style.left = left + 'px';
		R.pop.style.top = Math.max( 8, top ) + 'px';
	}

	function shell( task ) {
		if ( R.pop ) {
			R.pop.remove();
		}
		R.pop = el( 'div', 'chk-pop' );
		R.pop.setAttribute( 'role', 'dialog' );
		R.pop.setAttribute( 'aria-label', t[ task ] );
		R.pop.dir = document.documentElement.dir || 'rtl';

		var head = el( 'div', 'chk-pop__head' );
		head.appendChild( el( 'strong', '', t[ task ] ) );
		head.appendChild( el( 'span', 'chk-pop__meta' ) );
		var x = el( 'button', 'chk-pop__x', '×' );
		x.type = 'button';
		x.setAttribute( 'aria-label', t.close );
		x.addEventListener( 'click', close );
		head.appendChild( x );

		R.pop.appendChild( head );
		R.pop.appendChild( el( 'div', 'chk-pop__body' ) );
		R.pop.appendChild( el( 'div', 'chk-pop__foot' ) );
		document.body.appendChild( R.pop );
		return R.pop;
	}

	function part( name ) {
		return R.pop.querySelector( '.chk-pop__' + name );
	}

	function showLoading( task, note ) {
		shell( task );
		var b = part( 'body' );
		var sk = el( 'div', 'chk-skel' );
		sk.setAttribute( 'aria-busy', 'true' );
		for ( var i = 0; i < 3; i++ ) {
			sk.appendChild( el( 'i' ) );
		}
		b.appendChild( sk );
		b.appendChild( el( 'p', 'chk-pop__note', note || t.working ) );
		place();
	}

	function showError( task, msg, notReady ) {
		shell( task );
		part( 'body' ).appendChild( el( 'p', 'chk-pop__error', msg ) );
		var f = part( 'foot' );
		if ( notReady && cfg.settingsUrl ) {
			var a = el( 'a', 'button button-primary', t.openSettings );
			a.href = cfg.settingsUrl;
			f.appendChild( a );
		} else if ( ! notReady ) {
			f.appendChild( retryBtn() );
		}
		place();
	}

	function retryBtn() {
		var b = el( 'button', 'button', t.retry );
		b.type = 'button';
		b.addEventListener( 'click', function () {
			var own = R.state.own || '';
			R.run( R.state.btn, true, own ? '' : kwValue(), true, own );
		} );
		return b;
	}

	/* فیلد کلیدواژه: بالای هر پنجره، تا همان‌جا بشود عوضش کرد و دوباره ساخت. */
	function kwValue() {
		var i = R.pop && R.pop.querySelector( '.chk-kw__input' );
		return i ? i.value.trim() : ( R.state ? R.state.kw : '' );
	}
	function kwField( value, onEnter ) {
		var wrap = el( 'label', 'chk-kw' );
		wrap.appendChild( el( 'span', 'chk-kw__label', t.kwLabel ) );
		var i = el( 'input', 'chk-kw__input' );
		i.type = 'text';
		i.dir = 'auto';
		i.value = value || '';
		i.placeholder = t.kwPlaceholder;
		i.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				onEnter();
			}
		} );
		wrap.appendChild( i );
		return wrap;
	}


	/* ---------- نشانِ «قبلاً ساخته شده» ---------- */
	function markMade( postId, task ) {
		document.querySelectorAll( '.chk-btn[data-chk-task="' + task + '"][data-chk-post="' + postId + '"]' ).forEach( function ( b ) {
			if ( ! b.classList.contains( 'is-made' ) ) {
				b.classList.add( 'is-made' );
				var label = b.getAttribute( 'aria-label' ) + ' — ' + t.made;
				b.setAttribute( 'aria-label', label );
				b.title = label;
			}
		} );
	}


	// جایی که فشار دادنِ ماوس شروع شده؛ کشیدن برای انتخاب متن داخل پنجره که بیرون رها شود، پنجره را نمی‌بندد.
	var downIn = false;
	document.addEventListener( 'pointerdown', function ( e ) {
		downIn = !! ( R.pop && R.pop.contains( e.target ) );
	}, true );
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '[data-chk-task]' ) : null;
		if ( btn ) {
			e.preventDefault();
			if ( R.state && R.state.btn === btn ) {
				close();
			} else {
				run( btn, false );
			}
			return;
		}
		// دکمه‌های داخل پنجره وقتی پنجره را از نو می‌سازند دیگر در صفحه نیستند؛ آن کلیک «بیرون» حساب نمی‌شود.
		// پیش‌نمایش تصویر با کلیک اشتباه بیرون پنجره بسته نمی‌شود؛ فقط با ×، Esc، «ثبت» یا «نمی‌خواهم».
		var keep = R.state && R.state.task === 'image' && R.state.pending;
		if ( R.pop && ! keep && ! downIn && ! R.pop.contains( e.target ) && document.body.contains( e.target ) ) {
			close();
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && R.state ) {
			var b = R.state.btn;
			close();
			b.focus();
		}
	} );
	window.addEventListener( 'resize', place );
	window.addEventListener( 'scroll', place, { passive: true } );

	R.api = api;
	R.badges = badges;
	R.close = close;
	R.el = el;
	R.kwField = kwField;
	R.kwValue = kwValue;
	R.liveContent = liveContent;
	R.markMade = markMade;
	R.norm = norm;
	R.option = option;
	R.part = part;
	R.place = place;
	R.retryBtn = retryBtn;
	R.revealExcerptBox = revealExcerptBox;
	R.setField = setField;
	R.shell = shell;
	R.showError = showError;
	R.showLoading = showLoading;
	R.usageText = usageText;
}() );
