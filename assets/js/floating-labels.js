/**
 * Floating Labels for Forms
 *
 * Applies floating-label behaviour to Contact Form 7 and Gravity Forms.
 * No DOM reordering is performed — CSS is class-driven (.flfg-is-active,
 * .flfg-is-filled) so the label element always remains in the DOM,
 * preserving accessibility.
 *
 * data-flfg-style="1|2|3" is set on the <form> element by this script
 * based on the per-form or global setting passed via flfgConfig.
 */

( function () {
	'use strict';

	var cfg          = window.flfgConfig || {};
	var globalStyle  = cfg.globalStyle  || '1';
	var cf7Overrides = cfg.cf7Overrides || {};
	var gfOverrides  = cfg.gfOverrides  || {};

	/* Field types we apply the effect to (checkboxes / radios handled separately) */
	var TEXT_FIELD_SELECTOR = [
		'input[type="text"]',
		'input[type="email"]',
		'input[type="tel"]',
		'input[type="url"]',
		'input[type="number"]',
		'input[type="password"]',
		'input[type="search"]',
		'input[type="date"]',
		'input[type="month"]',
		'input[type="week"]',
		'input[type="time"]',
		'input[type="datetime-local"]',
		'textarea',
		'select',
	].join( ', ' );

	/* GF field types to skip (no meaningful floating label) */
	var GF_SKIP_TYPES = [
		'gfield--type-checkbox',
		'gfield--type-radio',
		'gfield--type-html',
		'gfield--type-section',
		'gfield--type-captcha',
		'gfield--type-consent',
		'gfield--type-page',
		'gfield--type-fileupload',
		// Legacy class names
		'gfield_contains_required', // skip only if also a radio/checkbox? No, keep
	];

	/* -----------------------------------------------------------------------
	   Helpers
	----------------------------------------------------------------------- */

	function getFormStyle( type, formId ) {
		var overrides = type === 'cf7' ? cf7Overrides : gfOverrides;
		var override  = overrides[ formId ];
		if ( override && override !== 'global' ) return override;
		return globalStyle;
	}

	/**
	 * Ensure the input has a non-empty placeholder so :not(:placeholder-shown)
	 * fires in browsers that support it (we still use JS classes, but this
	 * also prevents the browser from showing an empty placeholder box).
	 */
	function ensurePlaceholder( input ) {
		if ( ! input.getAttribute( 'placeholder' ) ) {
			input.setAttribute( 'placeholder', ' ' );
		}
	}

	/**
	 * Attach focus / blur / input / change listeners to toggle
	 * .flfg-is-active and .flfg-is-filled on the wrap element.
	 */
	function bindEvents( input, wrap ) {
		function checkFilled() {
			var filled = false;
			if ( input.tagName === 'SELECT' ) {
				/* A select is "filled" when a real (non-placeholder) option is selected */
				filled = input.value !== '' && input.selectedIndex > 0;
			} else {
				filled = input.value.trim() !== '';
			}
			wrap.classList.toggle( 'flfg-is-filled', filled );

			/* Relay GF validation errors onto the wrap for CSS targeting */
			var isError = input.classList.contains( 'gfield_error' ) ||
			              input.classList.contains( 'wpcf7-not-valid' ) ||
			              wrap.querySelector( '.gfield_error' ) !== null ||
			              wrap.querySelector( '.wpcf7-not-valid-tip' ) !== null;
			wrap.classList.toggle( 'flfg-has-error', isError );
		}

		input.addEventListener( 'focus', function () {
			wrap.classList.add( 'flfg-is-active' );
		} );

		input.addEventListener( 'blur', function () {
			wrap.classList.remove( 'flfg-is-active' );
			checkFilled();
		} );

		input.addEventListener( 'input',  checkFilled );
		input.addEventListener( 'change', checkFilled );

		/* Set initial filled state (e.g. browser auto-fill or server-side value) */
		checkFilled();
	}

	/**
	 * Wrap the plain-text nodes inside a <label> element in a <span> so we can
	 * style and position the label text independently of the control wrap.
	 * Returns the created span (or null if no text was found).
	 */
	function wrapLabelText( labelEl ) {
		var textNodes = [];
		labelEl.childNodes.forEach( function ( node ) {
			if ( node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '' ) {
				textNodes.push( node );
			}
		} );

		if ( ! textNodes.length ) return null;

		var span = document.createElement( 'span' );
		span.className = 'flfg-label';

		/* Insert before the first text node */
		labelEl.insertBefore( span, textNodes[ 0 ] );

		textNodes.forEach( function ( node ) {
			span.appendChild( node );
		} );

		return span;
	}

	/* -----------------------------------------------------------------------
	   Contact Form 7
	----------------------------------------------------------------------- */

	function initCF7() {
		document.querySelectorAll( '.wpcf7' ).forEach( function ( container ) {
			var formId = container.dataset.id || container.getAttribute( 'data-id' );
			var style  = getFormStyle( 'cf7', formId );

			if ( style === 'disabled' ) return;

			var form = container.querySelector( '.wpcf7-form' );
			if ( ! form ) return;

			form.dataset.flfgStyle = style;

			/* Each control wrap holds one input/textarea/select */
			form.querySelectorAll( '.wpcf7-form-control-wrap' ).forEach( function ( controlWrap ) {
				processCF7Field( controlWrap, form );
			} );

			/* Apply fieldset / legend styling */
			initLegends( form );
		} );
	}

	function processCF7Field( controlWrap, form ) {
		if ( controlWrap.dataset.flfgInit ) return;
		controlWrap.dataset.flfgInit = '1';

		var input = controlWrap.querySelector( TEXT_FIELD_SELECTOR );
		if ( ! input ) return;

		ensurePlaceholder( input );
		input.classList.add( 'flfg-field' );

		/*
		 * Two common CF7 HTML patterns:
		 *
		 * Pattern A — label WRAPS the control wrap:
		 *   <label>Your Name <span class="wpcf7-form-control-wrap">...</span></label>
		 *
		 * Pattern B — label uses for="id":
		 *   <label for="your-name">Your Name</label>
		 *   <span class="wpcf7-form-control-wrap">...</span>
		 */

		var parentLabel = controlWrap.closest( 'label' );

		if ( parentLabel ) {
			/* Pattern A */
			parentLabel.classList.add( 'flfg-wrap' );
			wrapLabelText( parentLabel ); /* creates .flfg-label span */
			bindEvents( input, parentLabel );

		} else if ( input.id ) {
			/* Pattern B — find label[for=inputId] */
			var linkedLabel = form.querySelector( 'label[for="' + CSS.escape( input.id ) + '"]' );

			if ( linkedLabel ) {
				/*
				 * Create a shared wrapper div that contains both the label
				 * and the control wrap. The label will be positioned inside it.
				 */
				var wrapper = document.createElement( 'div' );
				wrapper.className = 'flfg-wrap';

				controlWrap.parentNode.insertBefore( wrapper, controlWrap );
				wrapper.appendChild( controlWrap );

				linkedLabel.classList.add( 'flfg-label' );
				wrapper.appendChild( linkedLabel );

				bindEvents( input, wrapper );
			} else {
				/* No label — just flag the control wrap */
				controlWrap.classList.add( 'flfg-wrap' );
				bindEvents( input, controlWrap );
			}
		} else {
			controlWrap.classList.add( 'flfg-wrap' );
			bindEvents( input, controlWrap );
		}
	}

	/* -----------------------------------------------------------------------
	   Gravity Forms
	----------------------------------------------------------------------- */

	function initGF() {
		/*
		 * GF wraps every form in a div.gform_wrapper and the <form> has
		 * id="gform_N". Support both legacy and block-based markup.
		 */
		var forms = document.querySelectorAll( 'form[id^="gform_"]' );

		if ( ! forms.length ) {
			/* Fallback for unusual themes */
			forms = document.querySelectorAll( '.gform_wrapper form' );
		}

		forms.forEach( function ( form ) {
			var formId = ( form.id || '' ).replace( 'gform_', '' );
			var style  = getFormStyle( 'gf', formId );

			if ( style === 'disabled' ) return;

			form.dataset.flfgStyle = style;

			form.querySelectorAll( '.gfield' ).forEach( function ( gfield ) {
				processGFField( gfield );
			} );

			initLegends( form );
		} );
	}

	function processGFField( gfield ) {
		if ( gfield.dataset.flfgInit ) return;

		/* Skip field types that don't suit a floating label */
		var skip = GF_SKIP_TYPES.some( function ( cls ) {
			return gfield.classList.contains( cls );
		} );
		if ( skip ) return;

		/*
		 * GF 2.9+ renders complex fields (Name, Address, Date, Email, Password,
		 * etc.) as <fieldset> containers with a <legend> label. Browsers pin
		 * <legend> to the fieldset border and ignore position:absolute, so we
		 * cannot use the standard floating-label approach. Handle these separately.
		 */
		if ( gfield.tagName.toLowerCase() === 'fieldset' ) {
			processGFComplexField( gfield );
			return;
		}

		var input = gfield.querySelector( TEXT_FIELD_SELECTOR );
		if ( ! input ) return;

		gfield.dataset.flfgInit = '1';

		ensurePlaceholder( input );
		input.classList.add( 'flfg-field' );

		/*
		 * GF structure:
		 *   <li class="gfield">
		 *     <label class="gfield_label" for="input_1_1">Name</label>
		 *     <div class="ginput_container">
		 *       <input id="input_1_1" ...>
		 *     </div>
		 *   </li>
		 *
		 * We make <li> the position:relative wrap and convert gfield_label
		 * into our floating label.
		 */

		gfield.classList.add( 'flfg-wrap', 'flfg-wrap--gf' );

		var label = gfield.querySelector( '.gfield_label' );
		if ( label ) {
			label.classList.add( 'flfg-label' );
		}

		bindEvents( input, gfield );
	}

	/**
	 * GF complex fields (Name, Address, Date, Email, etc.) use a <fieldset>
	 * container with a <legend> group label. Since <legend> ignores
	 * position:absolute, we render the legend as a small permanent group label
	 * and apply individual floating labels to each sub-input span.
	 */
	function processGFComplexField( fieldset ) {
		fieldset.dataset.flfgInit = '1';
		fieldset.classList.add( 'flfg-wrap--gf', 'flfg-wrap--complex' );

		/* Style the <legend> as a static group label above the sub-fields */
		var legend = fieldset.querySelector( ':scope > legend' );
		if ( legend ) {
			legend.classList.add( 'flfg-complex-legend' );
		}

		/*
		 * Each sub-input lives in a <span> inside .ginput_container:
		 *   <span id="input_N_N_first">
		 *     <input type="text" ...>
		 *     <label for="...">First</label>
		 *   </span>
		 *
		 * We make each span a floating-label wrap so the per-sub-input label
		 * floats independently.
		 */
		var ginputContainer = fieldset.querySelector( '.ginput_container' );
		if ( ! ginputContainer ) return;

		ginputContainer.querySelectorAll( ':scope > span' ).forEach( function ( subSpan ) {
			var subInput = subSpan.querySelector( TEXT_FIELD_SELECTOR );
			if ( ! subInput ) return;

			ensurePlaceholder( subInput );
			subInput.classList.add( 'flfg-field' );
			subSpan.classList.add( 'flfg-wrap', 'flfg-sub-wrap' );

			var subLabel = subSpan.querySelector( 'label' );
			if ( subLabel ) {
				subLabel.classList.add( 'flfg-label' );
			}

			bindEvents( subInput, subSpan );
		} );
	}

	/* -----------------------------------------------------------------------
	   Legends / fieldsets
	----------------------------------------------------------------------- */

	function initLegends( context ) {
		context.querySelectorAll( 'fieldset' ).forEach( function ( fieldset ) {
			/* Skip GF complex fields — already handled by processGFComplexField */
			if ( fieldset.classList.contains( 'flfg-wrap--complex' ) ) return;

			fieldset.classList.add( 'flfg-fieldset' );
			var legend = fieldset.querySelector( 'legend' );
			if ( legend ) {
				legend.classList.add( 'flfg-legend' );
			}
		} );
	}

	/* -----------------------------------------------------------------------
	   Watch for GF validation / AJAX re-renders
	----------------------------------------------------------------------- */

	/**
	 * GF fires gform_post_render after AJAX page change or form reset.
	 * Re-initialise any new/unprocessed fields.
	 */
	document.addEventListener( 'gform_post_render', function ( e ) {
		var formId = e.detail && e.detail.formId;
		var form   = formId ? document.getElementById( 'gform_' + formId ) : null;
		if ( ! form ) return;

		form.querySelectorAll( '.gfield' ).forEach( function ( gfield ) {
			/* Clear the init flag so fields can be re-processed if the
			   DOM was rebuilt by GF's AJAX renderer */
			delete gfield.dataset.flfgInit;
			processGFField( gfield );
		} );
		initLegends( form );
	} );

	/**
	 * GF marks invalid fields with .gfield_error on the <li>.
	 * After CF7 validation response, re-check error state on all wraps.
	 */
	document.addEventListener( 'wpcf7invalid', syncCF7ErrorState );
	document.addEventListener( 'wpcf7mailsent', syncCF7ErrorState );

	function syncCF7ErrorState( e ) {
		var container = e.target && e.target.closest ? e.target.closest( '.wpcf7' ) : null;
		if ( ! container ) return;

		container.querySelectorAll( '.flfg-wrap' ).forEach( function ( wrap ) {
			var hasError = wrap.querySelector( '.wpcf7-not-valid' ) !== null ||
			               wrap.querySelector( '.wpcf7-not-valid-tip' ) !== null;
			wrap.classList.toggle( 'flfg-has-error', hasError );
		} );
	}

	/* -----------------------------------------------------------------------
	   Handle browser auto-fill (Chrome fires 'animationstart' on autofill)
	----------------------------------------------------------------------- */

	document.addEventListener( 'animationstart', function ( e ) {
		if ( e.animationName === 'flfg-autofill-detect' ) {
			var input = e.target;
			var wrap  = input.closest( '.flfg-wrap' );
			if ( wrap ) {
				wrap.classList.add( 'flfg-is-filled' );
			}
		}
	} );

	/* -----------------------------------------------------------------------
	   Kick off
	----------------------------------------------------------------------- */

	function init() {
		if ( cfg.hasCF7 ) initCF7();
		if ( cfg.hasGF )  initGF();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
