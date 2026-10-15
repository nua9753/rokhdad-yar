/* رخدادیار — مرکز کنترل. بدون وابستگی. */
( function () {
	'use strict';

	var cfg = window.RokhdadyarCfg || {};
	var t = cfg.i18n || {};
	var form = document.querySelector( '.chk-form' );
	if ( ! form ) {
		return;
	}

	/* زبانه‌ها */
	var tabs = form.querySelectorAll( '[data-tab]' );
	var panels = form.querySelectorAll( '[data-panel]' );
	function show( name ) {
		var found = false;
		panels.forEach( function ( p ) {
			var on = p.getAttribute( 'data-panel' ) === name;
			p.classList.toggle( 'is-active', on );
			found = found || on;
		} );
		if ( ! found ) {
			return show( 'models' );
		}
		tabs.forEach( function ( b ) {
			var on = b.getAttribute( 'data-tab' ) === name;
			b.classList.toggle( 'is-active', on );
			b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
	}
	tabs.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var name = b.getAttribute( 'data-tab' );
			show( name );
			try {
				history.replaceState( null, '', '#' + name );
			} catch ( e ) {}
		} );
	} );
	show( ( location.hash || '#models' ).slice( 1 ) );

	/* پروفایل مدل‌ها */
	var list = form.querySelector( '[data-profiles]' );
	var tpl = form.querySelector( '[data-profile-tpl]' );
	var routes = form.querySelectorAll( '[data-route]' );
	var seq = list.children.length;

	function field( card, name ) {
		return card.querySelector( '[data-f="' + name + '"]' );
	}

	function syncRoutes() {
		var cards = list.querySelectorAll( '[data-profile]' );
		routes.forEach( function ( sel ) {
			var current = sel.value || sel.getAttribute( 'data-current' ) || '';
			sel.textContent = '';
			cards.forEach( function ( c ) {
				var o = document.createElement( 'option' );
				o.value = field( c, 'id' ).value;
				o.textContent = field( c, 'label' ).value || field( c, 'model' ).value || t.newModel;
				sel.appendChild( o );
			} );
			sel.value = current;
			if ( sel.selectedIndex < 0 && sel.options.length ) {
				sel.selectedIndex = 0;
			}
			sel.disabled = ! sel.options.length;
		} );
	}

	function addProfile() {
		var key = form.querySelector( '[data-preset]' ).value;
		var ps = ( cfg.presets || {} )[ key ] || [ '', 'openai', '', '' ];
		var wrap = document.createElement( 'div' );
		wrap.innerHTML = tpl.innerHTML.replace( /__i__/g, 'n' + ( seq++ ) );
		var card = wrap.querySelector( '[data-profile]' );
		field( card, 'id' ).value = 'p' + Math.random().toString( 36 ).slice( 2, 10 );
		field( card, 'label' ).value = ps[ 0 ];
		field( card, 'format' ).value = ps[ 1 ];
		field( card, 'base_url' ).value = ps[ 2 ];
		field( card, 'model' ).value = ps[ 3 ];
		list.appendChild( card );
		syncRoutes();
		field( card, key === 'custom' ? 'base_url' : 'api_key' ).focus();
	}

	function call( path, body ) {
		return fetch( cfg.root + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body ),
		} ).then( function ( r ) {
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

	function values( card ) {
		var body = {};
		[ 'id', 'format', 'base_url', 'model', 'api_key', 'temperature', 'max_tokens' ].forEach( function ( k ) {
			var f = field( card, k );
			body[ k ] = f ? f.value : '';
		} );
		return body;
	}

	/* فهرست مدل‌ها مستقیم از خودِ سرویس گرفته می‌شود تا نام مدل هیچ‌وقت قدیمی نباشد. */
	function fetchModels( card, btn ) {
		var out = card.querySelector( '[data-models-out]' );
		var dl = card.querySelector( '[data-model-list]' );
		var input = field( card, 'model' );
		out.className = 'chk-models-out';
		out.textContent = t.loadingList;
		btn.disabled = true;
		call( 'models', values( card ) ).then( function ( j ) {
			dl.id = dl.id || 'chk-dl-' + field( card, 'id' ).value;
			dl.textContent = '';
			j.models.forEach( function ( m ) {
				var o = document.createElement( 'option' );
				o.value = m;
				dl.appendChild( o );
			} );
			input.setAttribute( 'list', dl.id );
			out.textContent = t.modelsFound.replace( '%d', j.models.length );
			out.classList.add( 'is-ok' );
			input.focus();
		} ).catch( function ( e ) {
			out.textContent = e.message;
			out.classList.add( 'is-bad' );
		} ).then( function () {
			btn.disabled = false;
		} );
	}

	function testProfile( card, btn ) {
		var out = card.querySelector( '[data-test-out]' );
		out.className = 'chk-test';
		out.textContent = t.testing;
		btn.disabled = true;
		call( 'test', values( card ) ).then( function ( j ) {
			out.className = 'chk-test is-ok';
			out.textContent = t.testOk + ' · ' + j.ms + ' ' + t.ms;
		} ).catch( function ( e ) {
			out.className = 'chk-test is-bad';
			out.textContent = e.message;
		} ).then( function () {
			btn.disabled = false;
		} );
	}

	function health( btn ) {
		var box = form.querySelector( '[data-health]' );
		var out = box.querySelector( '[data-health-out]' );
		out.textContent = '';
		var li = document.createElement( 'li' );
		li.textContent = t.healthRunning;
		out.appendChild( li );
		btn.disabled = true;
		call( 'health', {} ).then( function ( j ) {
			out.textContent = '';
			j.items.forEach( function ( it ) {
				var row = document.createElement( 'li' );
				row.className = 'is-' + it.status;
				var b = document.createElement( 'strong' );
				b.textContent = it.label;
				var d = document.createElement( 'span' );
				d.textContent = it.detail;
				row.appendChild( b );
				row.appendChild( d );
				out.appendChild( row );
			} );
			box.querySelector( '[data-health-fix]' ).hidden = ! j.fixable;
		} ).catch( function ( e ) {
			out.textContent = e.message;
		} ).then( function () {
			btn.disabled = false;
		} );
	}

	form.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b ) {
			return;
		}
		if ( b.hasAttribute( 'data-add' ) ) {
			addProfile();
		} else if ( b.hasAttribute( 'data-remove' ) ) {
			if ( window.confirm( t.confirmDel ) ) {
				b.closest( '[data-profile]' ).remove();
				syncRoutes();
			}
		} else if ( b.hasAttribute( 'data-models' ) ) {
			e.preventDefault();
			fetchModels( b.closest( '[data-profile]' ), b );
		} else if ( b.hasAttribute( 'data-terms-refresh' ) ) {
			var st = form.querySelector( '[data-terms-status]' );
			st.textContent = t.refreshing + ' ';
			b.disabled = true;
			call( 'terms/refresh', {} ).then( function ( j ) {
				st.textContent = [ 'cats', 'tags' ].map( function ( k ) {
					return ( k === 'cats' ? t.cats : t.tags ) + ': ' + ( j[ k ] ? j[ k ].count : 0 );
				} ).join( ' · ' ) + ' — ' + t.refreshed + '. ';
			} ).catch( function ( e ) {
				st.textContent = e.message + ' ';
			} ).then( function () {
				b.disabled = false;
			} );
		} else if ( b.hasAttribute( 'data-health-run' ) ) {
			health( b );
		} else if ( b.hasAttribute( 'data-health-repair' ) ) {
			if ( window.confirm( t.healthConfirm ) ) {
				b.disabled = true;
				call( 'health/fix', {} ).then( function ( j ) {
					window.alert( ( j.done.length ? t.healthFixed + ' ' + j.done.join( '، ' ) : '' ) + ( j.failed.length ? '\n' + j.failed.join( '\n' ) : '' ) || t.healthNothing );
					health( form.querySelector( '[data-health-run]' ) );
				} ).catch( function ( e ) {
					window.alert( e.message );
				} ).then( function () {
					b.disabled = false;
				} );
			}
		} else if ( b.hasAttribute( 'data-test' ) ) {
			testProfile( b.closest( '[data-profile]' ), b );
		} else if ( b.hasAttribute( 'data-reset' ) ) {
			var key = b.getAttribute( 'data-reset' );
			form.querySelector( '[data-prompt="' + key + '"]' ).value = cfg.defaults[ key ];
		} else if ( b.hasAttribute( 'data-var' ) ) {
			insertVar( b.getAttribute( 'data-var' ) );
		}
	} );
	list.addEventListener( 'input', function ( e ) {
		var f = e.target.getAttribute( 'data-f' );
		if ( f === 'label' || f === 'model' ) {
			syncRoutes();
		}
	} );
	syncRoutes();

	/* فیلد «اندازهٔ سفارشی» فقط وقتی وضوح «سفارشی» است فعال می‌شود. */
	var tier = form.querySelector( '[data-tier]' );
	var custom = form.querySelector( '[data-custom-size] input' );
	if ( tier && custom ) {
		var syncTier = function () {
			custom.readOnly = tier.value !== 'custom';
			custom.closest( '[data-custom-size]' ).style.opacity = tier.value === 'custom' ? '' : '.5';
		};
		tier.addEventListener( 'change', syncTier );
		syncTier();
	}

	/* درج متغیر در آخرین پرامپتِ فعال */
	var lastPrompt = form.querySelector( '[data-prompt="title"]' );
	form.querySelectorAll( '[data-prompt]' ).forEach( function ( ta ) {
		ta.addEventListener( 'focus', function () {
			lastPrompt = ta;
		} );
	} );
	function insertVar( v ) {
		var ta = lastPrompt;
		var s = ta.selectionStart;
		ta.value = ta.value.slice( 0, s ) + v + ta.value.slice( ta.selectionEnd );
		ta.focus();
		ta.selectionStart = ta.selectionEnd = s + v.length;
	}

	// جعبه ابزار: پیش‌نمایش زندهٔ نشان پین.
	var pinPrev = document.querySelector( '[data-pin-preview]' );
	if ( pinPrev ) {
		var pl = document.querySelector( '[data-pin-label]' );
		var pc = document.querySelector( '[data-pin-color]' );
		var sync = function () {
			pinPrev.textContent = pl.value || pl.defaultValue;
			pinPrev.style.background = pc.value;
		};
		pl.addEventListener( 'input', sync );
		pc.addEventListener( 'input', sync );
	}
}() );
