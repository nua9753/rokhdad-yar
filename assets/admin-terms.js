/* رخدادیار — ماژول دسته و برچسب. به هستهٔ admin.js وابسته است. */
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
	function usageText() {
		return R.usageText.apply( null, arguments );
	}

	/* ---------- دسته و برچسب: مسیر جدا ---------- */
	function classicCats() {
		var boxes = document.querySelectorAll( '#categorychecklist input[type="checkbox"]' );
		if ( ! inEditor || ! boxes.length ) {
			return null;
		}
		return Array.prototype.filter.call( boxes, function ( c ) {
			return c.checked;
		} ).map( function ( c ) {
			return parseInt( c.value, 10 );
		} );
	}

	function runTerms( btn, fresh ) {
		var postId = parseInt( btn.getAttribute( 'data-chk-post' ), 10 );
		if ( R.state && R.state.btn !== btn ) {
			close();
		}
		R.state = { btn: btn, task: 'terms', postId: postId };
		btn.classList.add( 'is-open' );
		if ( ! cfg.ready ) {
			showError( 'terms', t.notReady, true );
			return;
		}
		btn.classList.add( 'is-busy' );
		showLoading( 'terms' );
		var data = { post_id: postId, fresh: fresh };
		if ( inEditor ) {
			data.content = liveContent();
			data.title = titleField.value;
			var cur = classicCats();
			if ( cur ) {
				data.current_cats = cur;
			}
		}
		api( 'terms', data ).then( function ( res ) {
			markMade( postId, 'terms' );
			if ( ! R.state || R.state.btn !== btn ) {
				return;
			}
			btn.classList.remove( 'is-busy' );
			showTerms( res );
		} ).catch( function ( e ) {
			if ( R.state && R.state.btn === btn ) {
				btn.classList.remove( 'is-busy' );
				showError( 'terms', e.message );
			}
		} );
	}

	function chipGroup( title, items, key ) {
		var wrap = el( 'fieldset', 'chk-terms' );
		var legend = el( 'legend', 'chk-kw__label', title );
		// برچسب‌ها را می‌شود تکی انتخاب کرد یا همه را یک‌جا تیک زد / برداشت.
		if ( key !== 'cats' && items.length > 1 ) {
			[ [ t.tAll, true ], [ t.tClear, false ] ].forEach( function ( def ) {
				var bt = el( 'button', 'button-link chk-terms__all', def[ 0 ] );
				bt.type = 'button';
				bt.addEventListener( 'click', function () {
					wrap.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( c ) {
						c.checked = def[ 1 ];
					} );
				} );
				legend.appendChild( bt );
			} );
		}
		wrap.appendChild( legend );
		items.forEach( function ( it ) {
			var lab = el( 'label', 'chk-term' + ( it.suggested && ! it.current ? ' is-new' : '' ) );
			var c = el( 'input' );
			c.type = 'checkbox';
			c.checked = it.checked !== false;
			c.value = it.id != null ? it.id : it.name;
			c.setAttribute( 'data-k', key );
			lab.appendChild( c );
			lab.appendChild( el( 'span', '', it.name ) );
			if ( it.current ) {
				lab.appendChild( el( 'em', '', t.tCurrent ) );
			}
			wrap.appendChild( lab );
		} );
		return wrap;
	}

	function showTerms( res ) {
		shell( 'terms' );
		var src = res.source || {};
		part( 'meta' ).textContent = [
			src.model,
			src.tags_total ? t.tSent.replace( '%1$s', src.tags_sent ).replace( '%2$s', src.tags_total ) : '',
			res.cached ? t.cached : usageText( src.usage ),
		].filter( Boolean ).join( ' · ' );

		var b = part( 'body' );
		var any = false;
		[ 'cats', 'tags' ].forEach( function ( k ) {
			if ( res[ k ] && res[ k ].length ) {
				any = true;
				b.appendChild( chipGroup( t[ k ], res[ k ], k ) );
			}
		} );
		if ( res.new && res.new.length ) {
			any = true;
			b.appendChild( chipGroup( t.newTags, res.new.map( function ( n ) {
				return { name: n, suggested: true };
			} ), 'new' ) );
		}
		if ( ! any ) {
			b.appendChild( el( 'p', 'chk-pop__note', t.tNone ) );
		}

		var f = part( 'foot' );
		f.appendChild( retryBtn() );
		if ( any ) {
			var ok = el( 'button', 'button button-primary', t.apply );
			ok.type = 'button';
			ok.addEventListener( 'click', function () {
				applyTerms( res );
			} );
			f.appendChild( ok );
		}
		place();
	}

	function applyTerms( res ) {
		var picked = { cats: [], tags: [], new: [] };
		R.pop.querySelectorAll( '.chk-term input' ).forEach( function ( c ) {
			if ( c.checked ) {
				var k = c.getAttribute( 'data-k' );
				picked[ k ].push( k === 'new' ? c.value : parseInt( c.value, 10 ) );
			}
		} );
		var data = { post_id: R.state.postId, new: picked.new, save: ! inEditor };
		if ( res.cats ) {
			data.cats = picked.cats;
		}
		if ( res.tags ) {
			data.tags = picked.tags;
		}
		R.pop.classList.add( 'is-saving' );
		api( 'terms/apply', data ).then( function ( r ) {
			if ( inEditor ) {
				syncClassicTerms( res, picked, r.created || [] );
			}
			done( 'terms' );
		} ).catch( function ( e ) {
			showError( 'terms', e.message );
		} );
	}

	/* ویرایشگر کلاسیک: جعبه‌های خودِ وردپرس به‌روز می‌شوند و با ذخیرهٔ نوشته ثبت می‌شوند. */
	function syncClassicTerms( res, picked, created ) {
		if ( res.cats ) {
			document.querySelectorAll( '#categorychecklist input[type="checkbox"], #categorychecklist-pop input[type="checkbox"]' ).forEach( function ( c ) {
				c.checked = picked.cats.indexOf( parseInt( c.value, 10 ) ) !== -1;
			} );
		}
		var area = document.querySelector( '#tagsdiv-post_tag .the-tags' );
		if ( res.tags && area ) {
			var names = res.tags.filter( function ( x ) {
				return picked.tags.indexOf( x.id ) !== -1;
			} ).map( function ( x ) {
				return x.name;
			} ).concat( created.map( function ( x ) {
				return x.name;
			} ) );
			area.value = names.join( ',' );
			if ( window.tagBox && window.tagBox.quickClicks ) {
				window.tagBox.quickClicks( document.getElementById( 'post_tag' ) || document.querySelector( '#tagsdiv-post_tag .tagsdiv' ) );
			}
		}
	}


	R.applyTerms = applyTerms;
	R.chipGroup = chipGroup;
	R.classicCats = classicCats;
	R.runTerms = runTerms;
	R.showTerms = showTerms;
	R.syncClassicTerms = syncClassicTerms;
}() );
