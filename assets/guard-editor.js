/* رخدادیار — محافظ نامک در ویرایشگر بلوکی: هر تغییر نامکِ نوشتهٔ منتشرشده، از هر جا (پنل آدرس، افزونه، …)، اول هشدار جدی می‌گیرد. */
( function ( wp ) {
	'use strict';

	var g = window.RokhdadyarGuard;
	if ( ! g || g.mode === 'off' || ! wp || ! wp.plugins || ! wp.data ) {
		return;
	}
	var h = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var C = wp.components;
	var store = function () {
		return wp.data.select( 'core/editor' );
	};

	function Guard() {
		var s = useState( null ); // نامکِ تازه‌ای که منتظر تصمیم است
		var pending = s[ 0 ];
		var setPending = s[ 1 ];
		var a = useState( false );
		var ack = a[ 0 ];
		var setAck = a[ 1 ];
		var o = useState( false ); // در همین جلسه یک بار تأیید شده
		var allowed = o[ 0 ];
		var setAllowed = o[ 1 ];

		useEffect( function () {
			return wp.data.subscribe( function () {
				var ed = store();
				if ( ! ed || ! ed.getCurrentPost ) {
					return;
				}
				var saved = ed.getCurrentPost();
				if ( ! saved || saved.status !== 'publish' || ! saved.slug ) {
					return;
				}
				var now = ed.getEditedPostAttribute( 'slug' );
				if ( now === undefined || now === saved.slug || allowed ) {
					return;
				}
				if ( ! g.canEdit ) {
					// قفل: فوراً برمی‌گردد.
					wp.data.dispatch( 'core/editor' ).editPost( { slug: saved.slug } );
					wp.data.dispatch( 'core/notices' ).createNotice( 'error', g.locked, { id: 'chk-slug-locked', isDismissible: true } );
					return;
				}
				if ( pending === null ) {
					setPending( now );
				}
			} );
		}, [ pending, allowed ] );

		if ( pending === null ) {
			return null;
		}
		var keep = function () {
			var saved = store().getCurrentPost();
			wp.data.dispatch( 'core/editor' ).editPost( { slug: saved.slug } );
			setPending( null );
			setAck( false );
		};
		return h( C.Modal, {
			title: '⚠ ' + g.title,
			onRequestClose: keep,
			shouldCloseOnClickOutside: false,
			className: 'chk-guard-modal',
		},
		h( 'p', { style: { fontWeight: 600, margin: '0 0 6px' } }, g.lead ),
		h( 'ul', { style: { listStyle: 'disc', paddingInlineStart: '20px', margin: '0 0 10px' } }, g.risks.map( function ( r, i ) {
			return h( 'li', { key: i }, r );
		} ) ),
		h( 'p', { style: { margin: '0 0 12px', padding: '8px 10px', borderRadius: '6px', background: '#fcf0f1', color: '#8a2424', fontSize: '12px' } }, g.note ),
		h( C.CheckboxControl, { label: g.ack, checked: ack, onChange: setAck, __nextHasNoMarginBottom: true } ),
		h( 'div', { style: { display: 'flex', gap: '8px', justifyContent: 'space-between', marginTop: '16px', flexWrap: 'wrap' } },
			h( C.Button, { variant: 'primary', onClick: keep }, g.no ),
			h( C.Button, {
				variant: 'secondary',
				isDestructive: true,
				disabled: ! ack,
				onClick: function () {
					setAllowed( true );
					setPending( null );
				},
			}, g.yes )
		) );
	}

	wp.plugins.registerPlugin( 'rokhdadyar-slug-guard', { render: Guard } );
}( window.wp ) );
