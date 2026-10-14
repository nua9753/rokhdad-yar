/* رخدادیار — محافظ نامک در ویرایشگر کلاسیک و فهرست نوشته‌ها (ویرایش سریع). بدون وابستگی. */
( function () {
	'use strict';

	var g = window.RokhdadyarGuard;
	if ( ! g || g.mode === 'off' ) {
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

	/**
	 * پنجرهٔ هشدار. دکمهٔ تغییر فقط بعد از تیکِ «می‌دانم…» فعال می‌شود.
	 * در حالت قفل (برای غیرمدیر) فقط پیام قفل نشان داده می‌شود.
	 */
	function ask( onYes, onNo ) {
		var prev = document.activeElement;
		var wrap = el( 'div', 'chk-guard' );
		wrap.setAttribute( 'role', 'alertdialog' );
		wrap.setAttribute( 'aria-modal', 'true' );
		var box = el( 'div', 'chk-guard__box' );
		box.dir = document.documentElement.dir || 'rtl';
		var title = el( 'h2', 'chk-guard__title', '⚠ ' + g.title );
		title.id = 'chk-guard-title';
		wrap.setAttribute( 'aria-labelledby', title.id );
		box.appendChild( title );

		var foot = el( 'div', 'chk-guard__foot' );
		var finish = function ( yes ) {
			wrap.remove();
			document.removeEventListener( 'keydown', esc, true );
			if ( prev && prev.focus ) {
				prev.focus();
			}
			( yes ? onYes : onNo || function () {} )();
		};
		var esc = function ( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				e.stopPropagation();
				finish( false );
			}
		};

		if ( ! g.canEdit ) {
			box.appendChild( el( 'p', 'chk-guard__lead', g.locked ) );
			var okb = el( 'button', 'button button-primary', g.no );
			okb.type = 'button';
			okb.addEventListener( 'click', function () {
				finish( false );
			} );
			foot.appendChild( okb );
		} else {
			box.appendChild( el( 'p', 'chk-guard__lead', g.lead ) );
			var ul = el( 'ul', 'chk-guard__risks' );
			g.risks.forEach( function ( r ) {
				ul.appendChild( el( 'li', '', r ) );
			} );
			box.appendChild( ul );
			box.appendChild( el( 'p', 'chk-guard__note', g.note ) );
			var lab = el( 'label', 'chk-guard__ack' );
			var cb = el( 'input' );
			cb.type = 'checkbox';
			lab.appendChild( cb );
			lab.appendChild( el( 'span', '', g.ack ) );
			box.appendChild( lab );

			var no = el( 'button', 'button button-primary', g.no );
			no.type = 'button';
			no.addEventListener( 'click', function () {
				finish( false );
			} );
			var yes = el( 'button', 'button chk-guard__yes', g.yes );
			yes.type = 'button';
			yes.disabled = true;
			cb.addEventListener( 'change', function () {
				yes.disabled = ! cb.checked;
			} );
			yes.addEventListener( 'click', function () {
				if ( cb.checked ) {
					finish( true );
				}
			} );
			foot.appendChild( no );
			foot.appendChild( yes );
		}
		box.appendChild( foot );
		wrap.appendChild( box );
		wrap.addEventListener( 'click', function ( e ) {
			e.stopPropagation(); // کلیک روی پس‌زمینه پنجره را نمی‌بندد؛ تصمیم باید صریح باشد.
		} );
		document.body.appendChild( wrap );
		document.addEventListener( 'keydown', esc, true );
		foot.querySelector( '.button-primary' ).focus();
	}
	window.RokhdadyarGuardAsk = ask;

	/* ---------- ویرایشگر کلاسیک: دکمهٔ «ویرایش» کنار پیوند یکتا و جعبهٔ «نامک» ---------- */
	var status = document.getElementById( 'original_post_status' );
	var published = !! ( status && status.value === 'publish' );
	var passed = false;

	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest ? e.target.closest( '#edit-slug-box .edit-slug' ) : null;
		if ( ! b || ! published || passed ) {
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		ask( function () {
			passed = true;
			b.click();
		} );
	}, true );

	/* جعبهٔ «نامک» و فیلد نامکِ «ویرایش سریع» در فهرست */
	var okFor = {};
	document.addEventListener( 'focusin', function ( e ) {
		var input = e.target;
		if ( ! input || input.name !== 'post_name' ) {
			return;
		}
		var id = 0;
		var pub = false;
		var row = input.closest( '.inline-edit-row' );
		if ( row ) {
			id = parseInt( ( row.id || '' ).replace( 'edit-', '' ), 10 ) || 0;
			var st = document.querySelector( '#inline_' + id + ' ._status' );
			pub = !! ( st && st.textContent.trim() === 'publish' );
		} else if ( input.id === 'post_name' ) {
			id = -1;
			pub = published && ! passed;
		}
		if ( ! pub || okFor[ id ] ) {
			return;
		}
		input.blur();
		var orig = input.value;
		ask( function () {
			okFor[ id ] = true;
			if ( id === -1 ) {
				passed = true;
			}
			input.focus();
		}, function () {
			input.value = orig;
		} );
	}, true );
}() );
