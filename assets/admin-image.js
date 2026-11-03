/* رخدادیار — ماژول تصویر شاخص. به هستهٔ admin.js وابسته است. */
( function () {
	'use strict';

	var R = window.Rokhdadyar;
	if ( ! R ) {
		return;
	}
	var cfg = R.cfg;
	var t = R.t;
	var titleField = R.titleField;
	var inEditor = R.inEditor;

	// از ماژول‌های دیگر (هنگام فراخوانی پیدا می‌شوند).
	function api() {
		return R.api.apply( null, arguments );
	}
	function close() {
		return R.close.apply( null, arguments );
	}
	function done() {
		return R.done.apply( null, arguments );
	}
	function el() {
		return R.el.apply( null, arguments );
	}
	function liveContent() {
		return R.liveContent.apply( null, arguments );
	}
	function markMade() {
		return R.markMade.apply( null, arguments );
	}
	function part() {
		return R.part.apply( null, arguments );
	}
	function place() {
		return R.place.apply( null, arguments );
	}
	function retryBtn() {
		return R.retryBtn.apply( null, arguments );
	}
	function shell() {
		return R.shell.apply( null, arguments );
	}
	function showError() {
		return R.showError.apply( null, arguments );
	}
	function showLoading() {
		return R.showLoading.apply( null, arguments );
	}

	/* ---------- تصویر شاخص: مسیر جدا از عنوان و چکیده ---------- */
	function dropImage( id, postId ) {
		fetch( cfg.root + 'image/discard', {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: true,
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( { post_id: postId, attachment_id: id } ),
		} ).catch( function () {} );
	}

	/* فیلد پرامپت: خالی = خودکار از روی متن؛ پر = همان متن عیناً برای مدل تصویر. */
	function promptField( value, hint ) {
		var wrap = el( 'label', 'chk-kw chk-kw--stack' );
		wrap.appendChild( el( 'span', 'chk-kw__label', t.imgPrompt ) );
		var ta = el( 'textarea', 'chk-pop__text chk-img__prompt' );
		ta.rows = 3;
		ta.dir = 'auto';
		ta.value = value || '';
		ta.placeholder = t.imgPh;
		ta.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ( e.ctrlKey || e.metaKey ) ) {
				e.preventDefault();
				runImage( R.state.btn, ta.value.trim(), true );
			}
		} );
		wrap.appendChild( ta );
		wrap.appendChild( el( 'span', 'chk-pop__note', hint ) );
		return wrap;
	}
	/* تصویرهای مرجع: انتخاب از کتابخانهٔ رسانه (یا آپلود همان‌جا)، حداکثر cfg.refMax تا. */
	function refsState() {
		if ( ! R.state.refs ) {
			R.state.refs = ( cfg.imgRefs || [] ).slice();
			R.state.role = cfg.imgRole || 'subject';
		}
		return R.state.refs;
	}
	function refsBox() {
		var refs = refsState();
		var box = el( 'div', 'chk-refs-box' );
		var head = el( 'div', 'chk-refs-box__head' );
		head.appendChild( el( 'span', '', t.imgRefs ) );
		var sel = el( 'select' );
		[ [ 'subject', t.imgRoleSubject ], [ 'style', t.imgRoleStyle ] ].forEach( function ( o ) {
			var op = el( 'option', '', o[ 1 ] );
			op.value = o[ 0 ];
			sel.appendChild( op );
		} );
		sel.value = R.state.role;
		sel.title = t.imgRole;
		sel.setAttribute( 'aria-label', t.imgRole );
		sel.hidden = ! refs.length;
		sel.addEventListener( 'change', function () {
			R.state.role = sel.value;
		} );
		head.appendChild( sel );
		box.appendChild( head );
		var list = el( 'div', 'chk-refs' );
		function draw() {
			list.textContent = '';
			refs.forEach( function ( r, i ) {
				var it = el( 'span', 'chk-refs__item' );
				var im = el( 'img' );
				im.src = r.url;
				im.alt = '';
				im.addEventListener( 'load', place );
				var x = el( 'button', 'chk-refs__x', '×' );
				x.type = 'button';
				x.setAttribute( 'aria-label', t.imgRefRemove );
				x.addEventListener( 'click', function () {
					refs.splice( i, 1 );
					draw();
				} );
				it.appendChild( im );
				it.appendChild( x );
				list.appendChild( it );
			} );
			if ( refs.length < ( cfg.refMax || 4 ) && window.wp && window.wp.media ) {
				var add = el( 'button', 'chk-refs__add', '+' );
				add.type = 'button';
				add.title = t.imgRefsAdd;
				add.setAttribute( 'aria-label', t.imgRefsPick );
				add.addEventListener( 'click', function () {
					pick( draw );
				} );
				list.appendChild( add );
			}
			sel.hidden = ! refs.length;
			place();
		}
		draw();
		box.appendChild( list );
		box.appendChild( el( 'span', 'chk-pop__note', t.imgRefsHint ) );
		return box;
	}
	function pick( draw ) {
		var refs = R.state.refs;
		var frame = window.wp.media( { title: t.imgRefsPick, library: { type: 'image' }, multiple: true, button: { text: t.imgRefsUse } } );
		R.mediaOpen = true;
		frame.on( 'close', function () {
			setTimeout( function () {
				R.mediaOpen = false;
			}, 0 );
		} );
		frame.on( 'select', function () {
			frame.state().get( 'selection' ).each( function ( m ) {
				var a = m.toJSON();
				if ( refs.length >= ( cfg.refMax || 4 ) || refs.some( function ( r ) {
					return r.id === a.id;
				} ) ) {
					return;
				}
				refs.push( { id: a.id, url: a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url } );
			} );
			draw();
		} );
		frame.open();
		frame.content.mode( 'browse' ); // همیشه کتابخانه، نه زبانهٔ آپلود (آپلود هم همان‌جا هست).
	}
	function refIds() {
		return ( R.state && R.state.refs ? R.state.refs : [] ).map( function ( r ) {
			return r.id;
		} );
	}

	function promptValue() {
		var ta = R.pop && R.pop.querySelector( '.chk-img__prompt' );
		return ta ? ta.value.trim() : ( R.state && R.state.prompt ) || '';
	}

	function showImageAsk() {
		shell( 'image' );
		part( 'body' ).appendChild( promptField( R.state.prompt || '', t.imgHint ) );
		part( 'body' ).appendChild( refsBox() );
		var ok = el( 'button', 'button button-primary', t.go );
		ok.type = 'button';
		ok.addEventListener( 'click', function () {
			runImage( R.state.btn, promptValue(), true );
		} );
		var f = part( 'foot' );
		f.appendChild( el( 'span' ) );
		f.appendChild( ok );
		place();
		R.pop.querySelector( '.chk-img__prompt' ).focus();
	}

	function runImage( btn, prompt, asked ) {
		var postId = parseInt( btn.getAttribute( 'data-chk-post' ), 10 );
		var same = R.state && R.state.btn === btn;
		var old = same ? R.state.pending : 0;
		var refs = same ? R.state.refs : null;
		var role = same ? R.state.role : null;
		if ( R.state && ! same ) {
			close();
		}
		R.state = { btn: btn, task: 'image', postId: postId, pending: 0, prompt: prompt || '', refs: refs, role: role };
		refsState();
		btn.classList.add( 'is-open' );
		if ( ! asked ) {
			// اگر تصویری ساخته شده ولی هنوز ثبت نشده، همان برمی‌گردد (بدون ساخت و هزینهٔ دوباره).
			showLoading( 'image', ' ' );
			api( 'image/pending', { post_id: postId } ).then( function ( res ) {
				if ( ! R.state || R.state.btn !== btn ) {
					return;
				}
				if ( res && res.id ) {
					R.state.pending = res.id;
					if ( res.refs && res.refs.length ) {
						R.state.refs = res.refs;
						R.state.role = res.role || R.state.role;
					}
					showImage( res );
				} else {
					showImageAsk();
				}
			} ).catch( function () {
				if ( R.state && R.state.btn === btn ) {
					showImageAsk();
				}
			} );
			return;
		}
		btn.classList.add( 'is-busy' );
		showLoading( 'image', t.imgWorking );

		var data = { post_id: postId, discard: old || 0, prompt: prompt || '', refs: refIds(), ref_role: R.state.role };
		if ( inEditor ) {
			data.content = liveContent();
			data.title = titleField.value;
		}
		api( 'image', data ).then( function ( res ) {
			markMade( postId, 'image' );
			if ( ! R.state || R.state.btn !== btn ) {
				return; // پنجره بسته شده؛ تصویر به‌عنوان پیش‌نمایش ثبت‌نشده می‌ماند و دفعهٔ بعد نشان داده می‌شود.
			}
			btn.classList.remove( 'is-busy' );
			R.state.pending = res.id;
			showImage( res );
		} ).catch( function ( e ) {
			if ( R.state && R.state.btn === btn ) {
				btn.classList.remove( 'is-busy' );
				showError( 'image', e.message );
			}
		} );
	}

	function showImage( res ) {
		shell( 'image' );
		var b = part( 'body' );
		var img = el( 'img', 'chk-pop__img' );
		img.src = res.url;
		img.alt = res.alt;
		img.addEventListener( 'load', place );
		b.appendChild( img );

		var lab = el( 'label', 'chk-kw chk-kw--stack' );
		lab.appendChild( el( 'span', 'chk-kw__label', t.imgAlt ) );
		var alt = el( 'input', 'chk-kw__input' );
		alt.type = 'text';
		alt.dir = 'auto';
		alt.value = res.alt;
		lab.appendChild( alt );
		b.appendChild( lab );

		part( 'meta' ).textContent = [ res.restored ? t.imgPending : '', res.custom ? t.imgCustom : t.imgAuto, res.refs && res.refs.length ? t.imgWithRefs.replace( '%s', res.refs.length ) : '', res.quota ].filter( Boolean ).join( ' · ' );
		if ( res.note ) {
			b.appendChild( el( 'p', 'chk-pop__warn', res.note ) );
		}
		b.appendChild( promptField( res.prompt, t.imgUsed ) );
		b.appendChild( refsBox() );

		var f = part( 'foot' );
		f.appendChild( retryBtn() );
		var drop = el( 'button', 'button-link button-link-delete chk-img__drop', t.imgDiscard );
		drop.type = 'button';
		drop.addEventListener( 'click', function () {
			dropImage( res.id, R.state.postId );
			R.state.pending = 0;
			close();
		} );
		f.appendChild( drop );
		var ok = el( 'button', 'button button-primary', t.imgApply );
		ok.type = 'button';
		ok.addEventListener( 'click', function () {
			// در ویرایشگر کلاسیک جعبهٔ «تصویر شاخص» خودِ وردپرس به‌روز می‌شود و با ذخیرهٔ نوشته ثبت می‌شود.
			var box = inEditor && window.wp && window.wp.media && window.wp.media.featuredImage;
			R.pop.classList.add( 'is-saving' );
			api( 'image/apply', { post_id: R.state.postId, attachment_id: res.id, alt: alt.value.trim(), set: ! box } ).then( function () {
				if ( box ) {
					box.set( res.id );
				}
				R.state.pending = 0;
				done( 'image' );
			} ).catch( function ( e ) {
				showError( 'image', e.message );
			} );
		} );
		f.appendChild( ok );
		place();
		alt.focus();
	}


	R.dropImage = dropImage;
	R.promptField = promptField;
	R.promptValue = promptValue;
	R.runImage = runImage;
	R.showImage = showImage;
	R.showImageAsk = showImageAsk;
}() );
