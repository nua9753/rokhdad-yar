/* رخدادیار — ماژول متن (عنوان، چکیده، نامک) و تاریخچه. به هستهٔ admin.js وابسته است. */
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
	function badges() {
		return R.badges.apply( null, arguments );
	}
	function close() {
		return R.close.apply( null, arguments );
	}
	function el() {
		return R.el.apply( null, arguments );
	}
	function kwField() {
		return R.kwField.apply( null, arguments );
	}
	function kwValue() {
		return R.kwValue.apply( null, arguments );
	}
	function liveContent() {
		return R.liveContent.apply( null, arguments );
	}
	function markMade() {
		return R.markMade.apply( null, arguments );
	}
	function option() {
		return R.option.apply( null, arguments );
	}
	function part() {
		return R.part.apply( null, arguments );
	}
	function place() {
		return R.place.apply( null, arguments );
	}
	function promptValue() {
		return R.promptValue.apply( null, arguments );
	}
	function retryBtn() {
		return R.retryBtn.apply( null, arguments );
	}
	function revealExcerptBox() {
		return R.revealExcerptBox.apply( null, arguments );
	}
	function runImage() {
		return R.runImage.apply( null, arguments );
	}
	function runTerms() {
		return R.runTerms.apply( null, arguments );
	}
	function setField() {
		return R.setField.apply( null, arguments );
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
	function usageText() {
		return R.usageText.apply( null, arguments );
	}

	function showAsk( task ) {
		shell( task );
		var b = part( 'body' );
		var go = function ( withKw ) {
			run( R.state.btn, false, withKw ? kwValue() : '', true );
		};
		b.appendChild( el( 'p', 'chk-pop__note', t.kwAsk ) );
		b.appendChild( kwField( '', function () {
			go( true );
		} ) );
		b.appendChild( el( 'p', 'chk-pop__note chk-kw__hint', t.kwHint ) );
		b.appendChild( ownBox( task ) );
		var f = part( 'foot' );
		var skip = el( 'button', 'button', t.skipKw );
		skip.type = 'button';
		skip.addEventListener( 'click', function () {
			go( false );
		} );
		var ok = el( 'button', 'button button-primary', t.go );
		ok.type = 'button';
		ok.addEventListener( 'click', function () {
			go( true );
		} );
		f.appendChild( skip );
		f.appendChild( ok );
		place();
		R.pop.querySelector( '.chk-kw__input' ).focus();
	}

	function showResult( res ) {
		var task = res.task;
		shell( task );
		var src = res.source || {};
		part( 'meta' ).textContent = [
			src.model,
			src.own ? t.ownMeta : ( src.digested ? t.digest : t.fullText ),
			res.cached ? t.cached : usageText( src.usage ),
		].filter( Boolean ).join( ' · ' );

		var b = part( 'body' );
		var f = part( 'foot' );
		f.appendChild( retryBtn() );

		// کلیدواژه‌ای که سرور واقعاً به کار برده روی همهٔ دکمه‌های همین نوشته می‌نشیند.
		R.state.kw = src.own ? R.state.kw : src.keywords || '';
		if ( ! src.own ) {
			document.querySelectorAll( '[data-chk-post="' + R.state.postId + '"]' ).forEach( function ( n ) {
				n.setAttribute( 'data-chk-kw', R.state.kw );
			} );
		}
		if ( ! src.own ) {
			b.appendChild( kwField( R.state.kw, function () {
				run( R.state.btn, true, kwValue(), true );
			} ) );
		}
		b.appendChild( ownBox( task ) );

		var kw = src.keyword || '';
		if ( task !== 'excerpt' ) {
			if ( task === 'slug' && R.state.btn.getAttribute( 'data-chk-pub' ) === '1' ) {
				b.appendChild( el( 'p', 'chk-pop__warn', t.slugWarn ) );
			}
			b.appendChild( el( 'p', 'chk-pop__note', t.pick ) );
			res.items.forEach( function ( item, i ) {
				b.appendChild( option( task, item, kw, apply, ( res.labels || [] )[ i ] ) );
			} );
		} else {
			var ta = el( 'textarea', 'chk-pop__text' );
			ta.rows = 4;
			ta.dir = 'auto';
			ta.setAttribute( 'aria-label', t.excerpt );
			var meta = el( 'div', 'chk-pop__count' );
			var sync = function () {
				meta.textContent = '';
				meta.appendChild( badges( task, ta.value, kw ) );
			};
			var pick = function ( item, node ) {
				b.querySelectorAll( '.chk-opt' ).forEach( function ( o ) {
					o.classList.toggle( 'is-picked', o === node );
					o.setAttribute( 'aria-pressed', o === node ? 'true' : 'false' );
				} );
				ta.value = item;
				sync();
			};
			if ( res.items.length > 1 ) {
				b.appendChild( el( 'p', 'chk-pop__note', t.pick ) );
				res.items.forEach( function ( item, i ) {
					var o = option( task, item, kw, pick );
					o.classList.add( 'chk-opt--excerpt' );
					b.appendChild( o );
					if ( i === 0 ) {
						o.classList.add( 'is-picked' );
						o.setAttribute( 'aria-pressed', 'true' );
					}
				} );
				b.appendChild( el( 'p', 'chk-pop__note chk-pop__note--edit', t.edit ) );
			}
			ta.value = res.items[ 0 ];
			ta.addEventListener( 'input', sync );
			sync();
			b.appendChild( ta );
			b.appendChild( meta );
			var ok = el( 'button', 'button button-primary', t.apply );
			ok.type = 'button';
			ok.addEventListener( 'click', function () {
				apply( ta.value.trim() );
			} );
			f.appendChild( ok );
		}
		place();
		var first = R.pop.querySelector( '.chk-opt, .chk-pop__text' );
		if ( first ) {
			first.focus();
		}
	}

	function done( task, value ) {
		var btn = R.state.btn;
		// حالا چیزی برای برگرداندن هست: دکمهٔ تاریخچهٔ همین نوشته پیدا می‌شود.
		document.querySelectorAll( '.chk-btn--hidden[data-chk-task="history"][data-chk-post="' + R.state.postId + '"]' ).forEach( function ( h ) {
			h.classList.remove( 'chk-btn--hidden' );
		} );
		if ( inEditor && task === 'slug' ) {
			// نامک در پایگاه‌داده ثبت شده؛ فیلدهای خودِ ویرایشگر هم هم‌گام می‌شوند تا ذخیرهٔ بعدی آن را برنگرداند.
			var hidden = document.getElementById( 'post_name' );
			if ( hidden ) {
				hidden.value = value;
			}
			[ 'editable-post-name', 'editable-post-name-full' ].forEach( function ( id ) {
				var n = document.getElementById( id );
				if ( n ) {
					n.textContent = value;
				}
			} );
		}
		if ( ! inEditor ) {
			var row = btn.closest( 'tr' );
			if ( row && task === 'title' ) {
				var link = row.querySelector( '.row-title' );
				if ( link ) {
					link.textContent = value;
				}
				var inline = row.querySelector( '.hidden .post_title' );
				if ( inline ) {
					inline.textContent = value;
				}
			}
			if ( task !== 'title' ) {
				btn.classList.remove( 'chk-btn--missing' );
			}
		}
		close();
		btn.classList.add( 'is-done' );
		btn.focus();
		setTimeout( function () {
			btn.classList.remove( 'is-done' );
		}, 1600 );
	}

	function apply( value ) {
		if ( ! value || ! R.state ) {
			return;
		}
		var task = R.state.task;
		// نامکِ نوشتهٔ منتشرشده: اول هشدار جدیِ محافظ نامک.
		if ( task === 'slug' && window.RokhdadyarGuardAsk && ! R.state.guardOk && R.state.btn.getAttribute( 'data-chk-pub' ) === '1' ) {
			var st = R.state;
			window.RokhdadyarGuardAsk( function () {
				st.guardOk = true;
				if ( R.state === st ) {
					apply( value );
				}
			} );
			return;
		}
		if ( inEditor && task !== 'slug' ) {
			var target = task === 'title' ? titleField : document.getElementById( 'excerpt' );
			if ( target && target.value && target.value !== value ) {
				// مقدار قبلی برای «تاریخچه و برگشت» به سرور سپرده می‌شود.
				api( 'history/add', { post_id: R.state.postId, field: task, old: target.value } ).catch( function () {} );
			}
			if ( target ) {
				setField( target, value );
			}
			done( task, value );
			if ( task === 'excerpt' ) {
				revealExcerptBox();
			}
			return;
		}
		if ( R.pop ) {
			R.pop.classList.add( 'is-saving' );
		}
		api( 'apply', { post_id: R.state.postId, task: task, value: value } ).then( function ( r ) {
			done( task, r.value );
		} ).catch( function ( e ) {
			showError( task, e.message );
		} );
	}


	/* ---------- تاریخچه و برگشت ---------- */
	function runHistory( btn ) {
		var postId = parseInt( btn.getAttribute( 'data-chk-post' ), 10 );
		if ( R.state && R.state.btn !== btn ) {
			close();
		}
		R.state = { btn: btn, task: 'history', postId: postId };
		btn.classList.add( 'is-open' );
		showLoading( 'history', ' ' );
		api( 'history', { post_id: postId } ).then( function ( res ) {
			if ( R.state && R.state.btn === btn ) {
				showHistory( res.items || [] );
			}
		} ).catch( function ( e ) {
			if ( R.state && R.state.btn === btn ) {
				showError( 'history', e.message );
			}
		} );
	}

	function showHistory( items ) {
		shell( 'history' );
		var b = part( 'body' );
		if ( ! items.length ) {
			b.appendChild( el( 'p', 'chk-pop__note', t.hEmpty ) );
		}
		var names = { title: t.fTitle, excerpt: t.fExcerpt, slug: t.fSlug };
		items.forEach( function ( it ) {
			var row = el( 'div', 'chk-hist' );
			var head = el( 'div', 'chk-hist__head' );
			head.appendChild( el( 'span', 'chk-badge is-style', names[ it.f ] ) );
			head.appendChild( el( 'span', 'chk-hist__meta', [ it.date, it.user ].filter( Boolean ).join( ' · ' ) ) );
			row.appendChild( head );
			var val = el( 'p', 'chk-hist__val', it.v );
			if ( it.f === 'slug' ) {
				val.dir = 'auto';
			}
			row.appendChild( val );
			// در ویرایشگر، «فعلی» همان چیزی است که الان در فیلد نوشته شده، نه آنچه آخرین بار ذخیره شده.
			var live = it.current;
			if ( inEditor ) {
				var fld = document.getElementById( it.f === 'title' ? 'title' : ( it.f === 'excerpt' ? 'excerpt' : 'post_name' ) );
				live = fld ? fld.value === it.v : it.current;
			}
			if ( live ) {
				row.appendChild( el( 'span', 'chk-hist__meta', t.hNow ) );
			} else {
				var bt = el( 'button', 'button', t.hRestore );
				bt.type = 'button';
				bt.addEventListener( 'click', function () {
					R.state.task = it.f; // از اینجا مثل ثبتِ یک پیشنهاد رفتار می‌کند؛ مقدار فعلی هم به تاریخچه می‌رود.
					apply( it.v );
				} );
				row.appendChild( bt );
			}
			b.appendChild( row );
		} );
		place();
	}


	/* «پرامپت خودم»: به‌جای متن نوشته و تنظیمات افزونه، فقط همین چند خط فرستاده می‌شود. */
	function ownBox( task ) {
		var d = el( 'details', 'chk-own' );
		var cur = R.state.own || '';
		if ( cur ) {
			d.open = true;
		}
		d.appendChild( el( 'summary', '', t.ownToggle ) );
		var ta = el( 'textarea', 'chk-img__prompt chk-own__text' );
		ta.rows = 3;
		ta.dir = 'auto';
		ta.value = cur;
		ta.placeholder = t.ownPh;
		var go = function () {
			if ( ta.value.trim() ) {
				run( R.state.btn, true, '', true, ta.value.trim() );
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
		d.appendChild( ta );
		d.appendChild( el( 'p', 'chk-pop__note', t.ownHint ) );
		var b = el( 'button', 'button', t.ownGo );
		b.type = 'button';
		b.addEventListener( 'click', go );
		d.appendChild( b );
		d.addEventListener( 'toggle', function () {
			place();
			if ( d.open ) {
				ta.focus();
			}
		} );
		return d;
	}

	function run( btn, fresh, kw, asked, own ) {
		var task = btn.getAttribute( 'data-chk-task' );
		if ( task === 'history' ) {
			runHistory( btn );
			return;
		}
		if ( task === 'terms' ) {
			runTerms( btn, !! fresh );
			return;
		}
		if ( task === 'links' ) {
			R.runLinks( btn, !! fresh );
			return;
		}
		if ( task === 'image' ) {
			// کلیک روی دکمه: اول پرسیدن پرامپت. «دوباره بساز»: با همان پرامپتی که الان در فیلد است.
			runImage( btn, asked ? promptValue() : '', asked );
			return;
		}
		var postId = parseInt( btn.getAttribute( 'data-chk-post' ), 10 );
		if ( R.state && R.state.btn !== btn ) {
			close();
		}
		if ( kw === undefined ) {
			kw = btn.getAttribute( 'data-chk-kw' ) || '';
		}
		R.state = { btn: btn, task: task, postId: postId, kw: kw, own: own || '' };
		btn.classList.add( 'is-open' );

		if ( ! cfg.ready ) {
			showError( task, t.notReady, true );
			return;
		}

		if ( ! own && ! kw && ! asked && cfg.askKeyword && ! cfg.direct ) {
			showAsk( task );
			return;
		}

		var data = { post_id: postId, task: task, fresh: !! fresh, keyword: kw };
		if ( own ) {
			data.prompt = own;
		} else if ( inEditor ) {
			data.content = liveContent();
			data.title = titleField.value;
		}
		btn.classList.add( 'is-busy' );
		showLoading( task );

		api( 'generate', data ).then( function ( res ) {
			markMade( postId, task );
			if ( ! R.state || R.state.btn !== btn ) {
				return; // کاربر در این فاصله بسته یا سراغ نوشتهٔ دیگری رفته است.
			}
			btn.classList.remove( 'is-busy' );
			// نامکِ نوشتهٔ منتشرشده هیچ‌وقت بدون پیش‌نمایش عوض نمی‌شود.
			if ( cfg.direct && ! own && ! ( task === 'slug' && btn.getAttribute( 'data-chk-pub' ) === '1' ) ) {
				apply( res.items[ 0 ] );
			} else {
				showResult( res );
			}
		} ).catch( function ( e ) {
			if ( R.state && R.state.btn === btn ) {
				btn.classList.remove( 'is-busy' );
				showError( task, e.message );
			}
		} );
	}


	R.apply = apply;
	R.done = done;
	R.run = run;
	R.runHistory = runHistory;
	R.showAsk = showAsk;
	R.showHistory = showHistory;
	R.showResult = showResult;
}() );
