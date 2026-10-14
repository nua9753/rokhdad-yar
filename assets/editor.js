/* رخدادیار — پنل ویرایشگر بلوکی. بدون مرحلهٔ build. */
( function ( wp ) {
	'use strict';

	var cfg = window.RokhdadyarCfg;
	if ( ! wp || ! wp.plugins || ! cfg ) {
		return;
	}
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! Panel ) {
		return;
	}

	var h = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var C = wp.components;
	var t = cfg.i18n;

	var spark = h( 'svg', { viewBox: '0 0 16 16', width: 16, height: 16, 'aria-hidden': true },
		h( 'path', { fill: 'currentColor', d: 'M8 0l1.7 4.9a2.2 2.2 0 0 0 1.4 1.4L16 8l-4.9 1.7a2.2 2.2 0 0 0-1.4 1.4L8 16l-1.7-4.9a2.2 2.2 0 0 0-1.4-1.4L0 8l4.9-1.7a2.2 2.2 0 0 0 1.4-1.4z' } )
	);

	function norm( x ) {
		return String( x ).toLowerCase().replace( /[\u200c\u200d]/g, '' ).replace( /ي/g, 'ی' ).replace( /ك/g, 'ک' ).replace( /\s+/g, ' ' );
	}
	/* نشانگرهای سئو: طول در بازهٔ مناسب گوگل و حضور کلیدواژهٔ کانونی. */
	function badges( task, text, keyword, label ) {
		var len = text.length;
		var ok = len >= cfg[ task + 'Min' ] && len <= cfg[ task + 'Max' ];
		var out = [ label ? h( 'span', { key: 's', className: 'chk-badge is-style' }, label ) : null, h( 'span', { key: 'l', className: 'chk-badge ' + ( ok ? 'is-ok' : 'is-warn' ) }, len + ' ' + t.chars ) ];
		if ( keyword && ! ( task === 'slug' && cfg.slugLang !== 'fa' ) ) {
			var flat = function ( x ) {
				return task === 'slug' ? String( x ).replace( /[\s\u200c-]/g, '' ) : x;
			};
			var has = norm( flat( text ) ).indexOf( norm( flat( keyword ) ) ) !== -1;
			out.push( h( 'span', { key: 'k', className: 'chk-badge ' + ( has ? 'is-ok' : 'is-warn' ) }, has ? '✓ ' + t.keyword : t.noKeyword ) );
		}
		return h( 'span', { className: 'chk-badges' }, out );
	}

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

	function editor() {
		return wp.data.select( 'core/editor' );
	}
	function setPost( patch ) {
		wp.data.dispatch( 'core/editor' ).editPost( patch );
	}

	/* مقدار قبلی برای «تاریخچه و برگشت» به سرور سپرده می‌شود. */
	function keepOld( field, next ) {
		var old = editor().getEditedPostAttribute( field ) || '';
		if ( old && old !== next ) {
			wp.apiFetch( {
				path: '/rokhdadyar/v1/history/add',
				method: 'POST',
				data: { post_id: editor().getCurrentPostId(), field: field, old: old },
			} ).catch( function () {} );
		}
	}

	function HistoryTask() {
		var s = useState( { status: 'idle' } );
		var st = s[ 0 ];
		var set = s[ 1 ];
		var names = { title: t.fTitle, excerpt: t.fExcerpt, slug: t.fSlug };

		function load() {
			set( { status: 'busy' } );
			wp.apiFetch( { path: '/rokhdadyar/v1/history', method: 'POST', data: { post_id: editor().getCurrentPostId() } } ).then( function ( res ) {
				set( { status: 'result', items: res.items || [] } );
			} ).catch( function ( e ) {
				set( { status: 'error', msg: ( e && e.message ) || t.error } );
			} );
		}
		function restore( it ) {
			keepOld( it.f, it.v );
			var patch = {};
			patch[ it.f ] = it.v;
			setPost( patch );
			set( { status: 'done' } );
		}

		var out = [
			h( C.Button, { key: 'go', variant: 'tertiary', className: 'chk-ed__go', isBusy: st.status === 'busy', onClick: load }, t.showHistory ),
		];
		if ( st.status === 'done' ) {
			out.push( h( 'span', { key: 'ok', className: 'chk-ed__ok' }, '✓ ' + t.saved ) );
		}
		if ( st.status === 'error' ) {
			out.push( h( C.Notice, { key: 'err', status: 'error', isDismissible: false }, st.msg ) );
		}
		if ( st.status === 'result' ) {
			if ( ! st.items.length ) {
				out.push( h( 'p', { key: 'none', className: 'chk-ed__meta' }, t.hEmpty ) );
			}
			st.items.forEach( function ( it, i ) {
				var live = ( editor().getEditedPostAttribute( it.f ) || '' ) === it.v;
				out.push( h( 'div', { key: 'h' + i, className: 'chk-hist' },
					h( 'div', { className: 'chk-hist__head' },
						h( 'span', { className: 'chk-badge is-style' }, names[ it.f ] ),
						h( 'span', { className: 'chk-hist__meta' }, [ it.date, it.user ].filter( Boolean ).join( ' · ' ) )
					),
					h( 'p', { className: 'chk-hist__val', dir: 'auto' }, it.v ),
					live
						? h( 'span', { className: 'chk-hist__meta' }, t.hNow )
						: h( C.Button, { variant: 'secondary', size: 'small', onClick: function () {
							restore( it );
						} }, t.hRestore )
				) );
			} );
		}
		return h( 'div', { className: 'chk-ed__task' }, out );
	}

	// «قبلاً ساخته شده»: دکمهٔ هر کاری که دست‌کم یک بار برای این نوشته ساخته شده رنگی می‌ماند.
	function useMade( task ) {
		var m = useState( ( cfg.made || [] ).indexOf( task ) !== -1 );
		return {
			cls: 'chk-ed__go' + ( m[ 0 ] ? ' is-made' : '' ),
			title: m[ 0 ] ? t.made : undefined,
			mark: function () {
				m[ 1 ]( true );
			},
		};
	}

	function Task( props ) {
		var task = props.task;
		var made = useMade( task );
		var s = useState( { status: 'idle' } );
		var st = s[ 0 ];
		var set = s[ 1 ];
		var d = useState( '' );
		var draft = d[ 0 ];
		var setDraft = d[ 1 ];
		// «پرامپت خودم»: به‌جای متن نوشته و تنظیمات افزونه، فقط همین متن فرستاده می‌شود.
		var o = useState( { on: false, text: '' } );
		var own = o[ 0 ];
		var setOwn = o[ 1 ];
		var ownText = own.on ? own.text.trim() : '';

		function apply( value ) {
			keepOld( task, value );
			var patch = {};
			patch[ task ] = value;
			setPost( patch );
			set( { status: 'done' } );
		}

		function run( fresh ) {
			if ( own.on && ! ownText ) {
				set( { status: 'error', msg: t.ownPh } );
				return;
			}
			set( { status: 'busy' } );
			var data = ownText
				? { post_id: editor().getCurrentPostId(), task: task, fresh: !! fresh, prompt: ownText }
				: {
					post_id: editor().getCurrentPostId(),
					keyword: props.kw,
					task: task,
					fresh: !! fresh,
					// وضعیتِ زندهٔ ویرایشگر، نه نسخهٔ ذخیره‌شده.
					content: editor().getEditedPostContent(),
					title: editor().getEditedPostAttribute( 'title' ) || '',
				};
			wp.apiFetch( {
				path: '/rokhdadyar/v1/generate',
				method: 'POST',
				data: data,
			} ).then( function ( res ) {
				made.mark();
				// نامکِ نوشتهٔ منتشرشده هیچ‌وقت بدون پیش‌نمایش عوض نمی‌شود.
				if ( cfg.direct && ! ownText && ! ( task === 'slug' && editor().isCurrentPostPublished() ) ) {
					apply( res.items[ 0 ] );
					return;
				}
				setDraft( res.items[ 0 ] );
				set( { status: 'result', res: res, picked: res.items[ 0 ] } );
			} ).catch( function ( e ) {
				set( { status: 'error', msg: ( e && e.message ) || t.error } );
			} );
		}

		var busy = st.status === 'busy';
		var out = [
			h( C.Button, {
				key: 'go',
				variant: 'secondary',
				className: made.cls,
				title: made.title,
				icon: spark,
				isBusy: busy,
				disabled: busy,
				onClick: function () {
					run( st.status === 'result' );
				},
			}, busy ? t.working : ( st.status === 'result' ? t.retry : props.label ) ),
			h( C.CheckboxControl, {
				key: 'own',
				className: 'chk-ed__own',
				label: t.ownToggle,
				checked: own.on,
				onChange: function ( v ) {
					setOwn( { on: v, text: own.text } );
				},
				__nextHasNoMarginBottom: true,
			} ),
		];
		if ( own.on ) {
			out.push( h( C.TextareaControl, {
				key: 'own-text',
				label: t.ownToggle,
				hideLabelFromVision: true,
				value: own.text,
				rows: 3,
				placeholder: t.ownPh,
				help: t.ownHint,
				onChange: function ( v ) {
					setOwn( { on: true, text: v } );
				},
				onKeyDown: function ( e ) {
					if ( e.key === 'Enter' && ( e.ctrlKey || e.metaKey ) ) {
						e.preventDefault();
						run( true );
					}
				},
				__nextHasNoMarginBottom: true,
			} ) );
		}

		if ( st.status === 'done' ) {
			out.push( h( 'span', { key: 'ok', className: 'chk-ed__ok' }, '✓ ' + t.saved ) );
		}
		if ( st.status === 'error' ) {
			out.push( h( C.Notice, { key: 'err', status: 'error', isDismissible: false }, st.msg ) );
		}
		if ( st.status === 'result' ) {
			var src = st.res.source || {};
			out.push( h( 'p', { key: 'meta', className: 'chk-ed__meta' },
				[ src.model, src.own ? t.ownMeta : ( src.digested ? t.digest : t.fullText ), st.res.cached ? t.cached : usageText( src.usage ) ].filter( Boolean ).join( ' · ' )
			) );
			var kw = src.keyword || '';
			var many = st.res.items.length > 1;
			if ( task === 'slug' && editor().isCurrentPostPublished() ) {
				out.push( h( C.Notice, { key: 'warn', status: 'warning', isDismissible: false }, t.slugWarn ) );
			}
			if ( task !== 'excerpt' || many ) {
				st.res.items.forEach( function ( item, i ) {
					var picked = task === 'excerpt' && item === st.picked;
					out.push( h( 'button', {
						key: 'o' + i,
						type: 'button',
						className: 'chk-opt' + ( task === 'excerpt' ? ' chk-opt--excerpt' : '' ) + ( picked ? ' is-picked' : '' ),
						'aria-pressed': task === 'excerpt' ? picked : undefined,
						onClick: function () {
							if ( task !== 'excerpt' ) {
								apply( item );
							} else {
								setDraft( item );
								set( { status: 'result', res: st.res, picked: item } );
							}
						},
					},
					h( 'span', { className: 'chk-opt__text' + ( task === 'slug' && cfg.slugLang !== 'fa' ? ' chk-ltr' : '' ), dir: task === 'slug' && cfg.slugLang !== 'fa' ? 'ltr' : undefined }, item ),
					badges( task, item, kw, task === 'title' ? ( st.res.labels || [] )[ i ] : '' )
					) );
				} );
			}
			if ( task === 'excerpt' ) {
				out.push( h( C.TextareaControl, {
					key: 'ta',
					label: t.excerpt,
					hideLabelFromVision: true,
					value: draft,
					rows: 5,
					onChange: setDraft,
					__nextHasNoMarginBottom: true,
				} ) );
				out.push( h( 'div', { key: 'tb', className: 'chk-ed__count' }, badges( task, draft, kw ) ) );
				out.push( h( C.Button, {
					key: 'ap',
					variant: 'primary',
					onClick: function () {
						apply( draft.trim() );
					},
				}, t.apply ) );
			}
		}

		return h( 'div', { className: 'chk-ed__task' }, out );
	}

	/* دسته و برچسب: پیشنهاد از میان موارد موجود سایت؛ با تأیید، در خودِ ویرایشگر می‌نشیند و با ذخیرهٔ نوشته ثبت می‌شود. */
	function TermsTask() {
		var made = useMade( 'terms' );
		var s = useState( { status: 'idle' } );
		var st = s[ 0 ];
		var set = s[ 1 ];
		var o = useState( {} );
		var off = o[ 0 ];
		var setOff = o[ 1 ];
		var busy = st.status === 'busy';
		var has = function ( k ) {
			return cfg.terms.indexOf( k ) !== -1;
		};

		function fail( e ) {
			set( { status: 'error', msg: ( e && e.message ) || t.error } );
		}
		function run() {
			var fresh = st.status === 'result';
			set( { status: 'busy' } );
			var data = {
				post_id: editor().getCurrentPostId(),
				fresh: fresh,
				content: editor().getEditedPostContent(),
				title: editor().getEditedPostAttribute( 'title' ) || '',
			};
			if ( has( 'cats' ) ) {
				data.current_cats = editor().getEditedPostAttribute( 'categories' ) || [];
			}
			if ( has( 'tags' ) ) {
				data.current_tags = editor().getEditedPostAttribute( 'tags' ) || [];
			}
			wp.apiFetch( { path: '/rokhdadyar/v1/terms', method: 'POST', data: data } ).then( function ( res ) {
				made.mark();
				var start = {};
				( res.cats || [] ).forEach( function ( x ) {
					if ( x.checked === false ) {
						start[ 'cats' + x.id ] = true;
					}
				} );
				setOff( start );
				set( { status: 'result', res: res } );
			} ).catch( fail );
		}
		function on( key ) {
			return ! off[ key ];
		}
		function apply() {
			var res = st.res;
			var pick = function ( k ) {
				return ( res[ k ] || [] ).filter( function ( x ) {
					return on( k + x.id );
				} ).map( function ( x ) {
					return x.id;
				} );
			};
			var fresh = ( res.new || [] ).filter( function ( n ) {
				return on( 'new' + n );
			} );
			// برچسب تازه باید اول ساخته شود تا شناسه بگیرد؛ خودِ دسته و برچسب‌ها را ویرایشگر ذخیره می‌کند.
			wp.apiFetch( {
				path: '/rokhdadyar/v1/terms/apply',
				method: 'POST',
				data: { post_id: editor().getCurrentPostId(), new: fresh, save: false },
			} ).then( function ( r ) {
				var patch = {};
				if ( res.cats ) {
					patch.categories = pick( 'cats' );
				}
				if ( res.tags ) {
					patch.tags = pick( 'tags' ).concat( ( r.created || [] ).map( function ( x ) {
						return x.id;
					} ) );
				}
				if ( patch.categories && ! patch.categories.length ) {
					delete patch.categories;
				}
				setPost( patch );
				set( { status: 'done' } );
			} ).catch( fail );
		}
		function group( title, items, prefix ) {
			if ( ! items || ! items.length ) {
				return null;
			}
			var keyOf = function ( it ) {
				return prefix + ( it.id != null ? it.id : it.name );
			};
			var setAll = function ( value ) {
				var next = Object.assign( {}, off );
				items.forEach( function ( it ) {
					next[ keyOf( it ) ] = ! value;
				} );
				setOff( next );
			};
			return h( 'fieldset', { key: prefix, className: 'chk-terms' },
				h( 'legend', { className: 'chk-kw__label' }, title,
					prefix !== 'cats' && items.length > 1 ? h( 'button', { type: 'button', className: 'button-link chk-terms__all', onClick: function () {
						setAll( true );
					} }, t.tAll ) : null,
					prefix !== 'cats' && items.length > 1 ? h( 'button', { type: 'button', className: 'button-link chk-terms__all', onClick: function () {
						setAll( false );
					} }, t.tClear ) : null
				),
				items.map( function ( it ) {
					var key = prefix + ( it.id != null ? it.id : it.name );
					return h( 'label', { key: key, className: 'chk-term' + ( it.suggested && ! it.current ? ' is-new' : '' ) },
						h( 'input', {
							type: 'checkbox',
							checked: on( key ),
							onChange: function () {
								var next = Object.assign( {}, off );
								next[ key ] = ! next[ key ];
								setOff( next );
							},
						} ),
						h( 'span', null, it.name ),
						it.current ? h( 'em', null, t.tCurrent ) : null
					);
				} )
			);
		}

		var out = [
			h( C.Button, {
				key: 'go',
				variant: 'secondary',
				className: made.cls,
				title: made.title,
				icon: spark,
				isBusy: busy,
				disabled: busy,
				onClick: run,
			}, busy ? t.working : ( st.status === 'result' ? t.retry : t.makeTerms ) ),
		];
		if ( st.status === 'done' ) {
			out.push( h( 'span', { key: 'ok', className: 'chk-ed__ok' }, '✓ ' + t.saved ) );
		}
		if ( st.status === 'error' ) {
			out.push( h( C.Notice, { key: 'err', status: 'error', isDismissible: false }, st.msg ) );
		}
		if ( st.status === 'result' ) {
			var res = st.res;
			var src = res.source || {};
			out.push( h( 'p', { key: 'meta', className: 'chk-ed__meta' }, [
				src.model,
				src.tags_total ? t.tSent.replace( '%1$s', src.tags_sent ).replace( '%2$s', src.tags_total ) : '',
				res.cached ? t.cached : usageText( src.usage ),
			].filter( Boolean ).join( ' · ' ) ) );
			var groups = [
				group( t.cats, res.cats, 'cats' ),
				group( t.tags, res.tags, 'tags' ),
				group( t.newTags, ( res.new || [] ).map( function ( n ) {
					return { name: n, suggested: true };
				} ), 'new' ),
			].filter( Boolean );
			if ( groups.length ) {
				out = out.concat( groups );
				out.push( h( C.Button, { key: 'ap', variant: 'primary', onClick: apply }, t.apply ) );
			} else {
				out.push( h( 'p', { key: 'none', className: 'chk-ed__meta' }, t.tNone ) );
			}
		}
		return h( 'div', { className: 'chk-ed__task' }, out );
	}

	/* تصویر شاخص: مسیر جدا. پس از تأیید، در خودِ ویرایشگر به‌عنوان تصویر شاخص می‌نشیند و با ذخیرهٔ نوشته ثبت می‌شود. */
	function ImageTask() {
		var made = useMade( 'image' );
		var s = useState( { status: 'idle' } );
		var st = s[ 0 ];
		var set = s[ 1 ];
		var a = useState( '' );
		var alt = a[ 0 ];
		var setAlt = a[ 1 ];
		var pr = useState( '' );
		var prompt = pr[ 0 ];
		var setPrompt = pr[ 1 ];
		var busy = st.status === 'busy';

		function fail( e ) {
			set( { status: 'error', msg: ( e && e.message ) || t.error } );
		}
		// تصویری که قبلاً ساخته شده و هنوز ثبت نشده (مثلاً پیش از رفرش صفحه) دوباره نشان داده می‌شود.
		useEffect( function () {
			wp.apiFetch( { path: '/rokhdadyar/v1/image/pending', method: 'POST', data: { post_id: editor().getCurrentPostId() } } ).then( function ( res ) {
				if ( res && res.id ) {
					setAlt( res.alt );
					setPrompt( res.prompt );
					set( function ( cur ) {
						return cur.status === 'idle' ? { status: 'result', res: res } : cur;
					} );
				}
			} ).catch( function () {} );
		}, [] );
		function run() {
			var old = st.res ? st.res.id : 0;
			set( { status: 'busy' } );
			wp.apiFetch( {
				path: '/rokhdadyar/v1/image',
				method: 'POST',
				data: {
					post_id: editor().getCurrentPostId(),
					discard: old,
					prompt: prompt.trim(), // خالی = خودکار از روی متن؛ پر = عیناً همین.
					content: editor().getEditedPostContent(),
					title: editor().getEditedPostAttribute( 'title' ) || '',
				},
			} ).then( function ( res ) {
				made.mark();
				setAlt( res.alt );
				setPrompt( res.prompt );
				set( { status: 'result', res: res } );
			} ).catch( fail );
		}
		function apply() {
			var id = st.res.id;
			wp.apiFetch( {
				path: '/rokhdadyar/v1/image/apply',
				method: 'POST',
				data: { post_id: editor().getCurrentPostId(), attachment_id: id, alt: alt.trim(), set: false },
			} ).then( function () {
				setPost( { featured_media: id } );
				set( { status: 'done' } );
			} ).catch( fail );
		}
		function discard() {
			wp.apiFetch( {
				path: '/rokhdadyar/v1/image/discard',
				method: 'POST',
				data: { post_id: editor().getCurrentPostId(), attachment_id: st.res.id },
			} ).catch( function () {} );
			set( { status: 'idle' } );
		}

		var out = [
			h( C.TextareaControl, {
				key: 'pr',
				label: t.imgPrompt,
				value: prompt,
				rows: 3,
				onChange: setPrompt,
				placeholder: t.imgPh,
				help: st.status === 'result' ? t.imgUsed : t.imgHint,
				__nextHasNoMarginBottom: true,
			} ),
			h( C.Button, {
				key: 'go',
				variant: 'secondary',
				className: made.cls,
				title: made.title,
				icon: spark,
				isBusy: busy,
				disabled: busy,
				onClick: run,
			}, busy ? t.imgWorking : ( st.status === 'result' ? t.retry : t.makeImage ) ),
		];
		if ( st.status === 'done' ) {
			out.push( h( 'span', { key: 'ok', className: 'chk-ed__ok' }, '✓ ' + t.saved ) );
		}
		if ( st.status === 'error' ) {
			out.push( h( C.Notice, { key: 'err', status: 'error', isDismissible: false }, st.msg ) );
		}
		if ( st.status === 'result' ) {
			out.push( h( 'img', { key: 'img', className: 'chk-pop__img', src: st.res.url, alt: alt } ) );
			out.push( h( 'p', { key: 'quota', className: 'chk-ed__meta' }, st.res.quota ) );
			out.push( h( C.TextControl, {
				key: 'alt',
				label: t.imgAlt,
				value: alt,
				onChange: setAlt,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ) );
			out.push( h( 'div', { key: 'act', className: 'chk-ed__actions' },
				h( C.Button, { variant: 'primary', onClick: apply }, t.imgApply ),
				h( C.Button, { variant: 'tertiary', isDestructive: true, onClick: discard }, t.imgDiscard )
			) );
		}
		return h( 'div', { className: 'chk-ed__task' }, out );
	}

	// عنوان پنل: لوگوی رسمی (نام برای صفحه‌خوان می‌ماند).
	var panelTitle = cfg.logo
		? h( 'span', { className: 'chk-ed__brand' },
			h( 'img', { src: cfg.logo, alt: '', height: 18 } ),
			h( 'span', { className: 'screen-reader-text' }, t.panel )
		)
		: t.panel;

	function App() {
		if ( ! cfg.ready && ! cfg.image ) {
			return h( Panel, { name: 'rokhdadyar', title: panelTitle },
				h( 'p', null, t.notReady ),
				cfg.settingsUrl ? h( C.Button, { variant: 'link', href: cfg.settingsUrl }, t.openSettings ) : null
			);
		}
		var k = useState( cfg.keyword || '' );
		return h( Panel, { name: 'rokhdadyar', title: panelTitle, className: 'chk-ed' },
			h( C.TextControl, {
				label: t.kwLabel,
				value: k[ 0 ],
				onChange: k[ 1 ],
				placeholder: t.kwPlaceholder,
				help: t.kwHint,
				className: 'chk-ed__kw',
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			cfg.ready ? h( Task, { task: 'title', label: t.makeTitle, kw: k[ 0 ].trim() } ) : null,
			cfg.ready && cfg.supportsExcerpt ? h( Task, { task: 'excerpt', label: t.makeExcerpt, kw: k[ 0 ].trim() } ) : null,
			cfg.ready && cfg.slug ? h( Task, { task: 'slug', label: t.makeSlug, kw: k[ 0 ].trim() } ) : null,
			cfg.ready && cfg.terms && cfg.terms.length ? h( TermsTask ) : null,
			cfg.image ? h( ImageTask ) : null,
			cfg.history === undefined ? h( HistoryTask ) : null
		);
	}

	wp.plugins.registerPlugin( 'rokhdadyar', { render: App } );
}( window.wp ) );
