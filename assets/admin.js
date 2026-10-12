/* global praxisEI */
( function () {
	'use strict';

	var rows = document.getElementById( 'praxis-ei-rows' );
	var template = document.getElementById( 'praxis-ei-row-template' );
	var fieldset = document.getElementById( 'praxis-ei-redirect-envs' );
	var defaultSelect = document.getElementById( 'praxis-ei-default' );

	if ( ! rows || ! template || ! fieldset || ! defaultSelect ) {
		return;
	}

	var nextIndex = rows.querySelectorAll( '.praxis-ei-row' ).length;

	// Remember ticked environments even while they temporarily drop out of the
	// list (e.g. the only staging row is switched to development and back).
	var checked = {};
	try {
		JSON.parse( fieldset.getAttribute( 'data-checked' ) || '[]' ).forEach( function ( key ) {
			checked[ key ] = true;
		} );
	} catch ( e ) {}

	function addRow( domain, env ) {
		var html = template.innerHTML.replace( /__INDEX__/g, String( nextIndex++ ) );
		var tbody = document.createElement( 'tbody' );
		tbody.innerHTML = html.trim();
		var row = tbody.firstElementChild;
		if ( domain ) {
			row.querySelector( '.praxis-ei-domain' ).value = domain;
		}
		if ( env ) {
			row.querySelector( '.praxis-ei-env' ).value = env;
		}
		rows.appendChild( row );
		row.querySelector( '.praxis-ei-domain' ).focus();
		renderChecks();
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function renderChecks() {
		var domains = {};

		rows.querySelectorAll( '.praxis-ei-row' ).forEach( function ( row ) {
			var domain = row.querySelector( '.praxis-ei-domain' ).value.trim();
			var env = row.querySelector( '.praxis-ei-env' ).value;
			if ( ! domain ) {
				return;
			}
			( domains[ env ] = domains[ env ] || [] ).push( domain );
		} );
		( domains[ defaultSelect.value ] = domains[ defaultSelect.value ] || [] ).push( praxisEI.i18n['default'] );

		fieldset.textContent = '';

		Object.keys( praxisEI.types ).forEach( function ( key ) {
			if ( ! domains[ key ] ) {
				return;
			}
			var isProd = 'production' === key;
			var label = el( 'label', 'praxis-ei-check' + ( isProd ? ' is-disabled' : '' ) );
			var input = el( 'input' );
			input.type = 'checkbox';
			input.name = 'redirect_envs[]';
			input.value = key;
			input.disabled = isProd;
			input.checked = ! isProd && !! checked[ key ];
			input.addEventListener( 'change', function () {
				checked[ key ] = input.checked;
			} );

			label.appendChild( input );
			label.appendChild( document.createTextNode( ' ' ) );
			label.appendChild( el( 'strong', '', praxisEI.types[ key ] ) );
			label.appendChild( document.createTextNode( ' ' ) );
			label.appendChild( el( 'span', 'praxis-ei-domains', domains[ key ].join( ', ' ) ) );
			if ( isProd ) {
				label.appendChild( document.createTextNode( ' ' ) );
				label.appendChild( el( 'span', 'description', praxisEI.i18n.productionNote ) );
			}
			fieldset.appendChild( label );
		} );
	}

	document.getElementById( 'praxis-ei-add' ).addEventListener( 'click', function () {
		addRow();
	} );

	var addCurrent = document.getElementById( 'praxis-ei-add-current' );
	if ( addCurrent ) {
		addCurrent.addEventListener( 'click', function () {
			addRow( addCurrent.getAttribute( 'data-domain' ) );
		} );
	}

	rows.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.praxis-ei-remove' );
		if ( button ) {
			button.closest( '.praxis-ei-row' ).remove();
			renderChecks();
		}
	} );

	// Keep checkbox state in sync with the server-rendered list on load.
	fieldset.addEventListener( 'change', function ( event ) {
		if ( event.target.name === 'redirect_envs[]' ) {
			checked[ event.target.value ] = event.target.checked;
		}
	} );

	rows.addEventListener( 'input', renderChecks );
	rows.addEventListener( 'change', renderChecks );
	defaultSelect.addEventListener( 'change', renderChecks );

	if ( ! rows.querySelector( '.praxis-ei-row' ) ) {
		addRow();
	}
} )();
