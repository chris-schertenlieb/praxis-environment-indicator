/* global praxisEIFavicon */
( function () {
	'use strict';

	var config = window.praxisEIFavicon;
	if ( ! config || ! document.createElement( 'canvas' ).getContext ) {
		return;
	}

	var SIZE = 64; // Canvas px; the browser downsamples to 16/32.
	var ICON_SCALE = 0.69; // Icon size relative to the coloured square.
	var RADIUS = 14;

	/**
	 * The icon link the browser would use: the largest declared "icon", or the
	 * last one if none declare sizes.
	 */
	function currentIconHref() {
		var links = document.querySelectorAll( 'link[rel~="icon"]' );
		var best = null;
		var bestSize = -1;

		links.forEach( function ( link ) {
			var size = parseInt( ( link.getAttribute( 'sizes' ) || '0' ).split( 'x' )[ 0 ], 10 ) || 0;
			if ( size >= bestSize ) {
				best = link;
				bestSize = size;
			}
		} );

		return best ? best.href : config.fallback;
	}

	function roundedSquare( ctx ) {
		ctx.beginPath();
		ctx.moveTo( RADIUS, 0 );
		ctx.arcTo( SIZE, 0, SIZE, SIZE, RADIUS );
		ctx.arcTo( SIZE, SIZE, 0, SIZE, RADIUS );
		ctx.arcTo( 0, SIZE, 0, 0, RADIUS );
		ctx.arcTo( 0, 0, SIZE, 0, RADIUS );
		ctx.closePath();
		ctx.fillStyle = config.color;
		ctx.fill();
	}

	function draw( img ) {
		var canvas = document.createElement( 'canvas' );
		canvas.width = SIZE;
		canvas.height = SIZE;
		var ctx = canvas.getContext( '2d' );

		roundedSquare( ctx );

		if ( img ) {
			var inner = Math.round( SIZE * ICON_SCALE );
			var offset = Math.round( ( SIZE - inner ) / 2 );
			ctx.drawImage( img, offset, offset, inner, inner );
		} else {
			// No usable icon: show the environment's initial instead.
			ctx.fillStyle = '#fff';
			ctx.font = 'bold 44px system-ui, -apple-system, "Segoe UI", sans-serif';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillText( config.letter, SIZE / 2, SIZE / 2 + 3 );
		}

		// Throws if a cross-origin icon without CORS tainted the canvas.
		return canvas.toDataURL( 'image/png' );
	}

	function apply( dataUrl ) {
		document.querySelectorAll( 'link[rel~="icon"]' ).forEach( function ( link ) {
			link.parentNode.removeChild( link );
		} );

		var link = document.createElement( 'link' );
		link.rel = 'icon';
		link.type = 'image/png';
		link.href = dataUrl;
		document.head.appendChild( link );
	}

	function fallback() {
		apply( draw( null ) );
	}

	var img = new Image();
	img.crossOrigin = 'anonymous';
	// SVG icons need explicit dimensions to rasterise at a sensible size.
	img.width = SIZE;
	img.height = SIZE;
	img.onload = function () {
		try {
			apply( draw( img ) );
		} catch ( e ) {
			fallback();
		}
	};
	img.onerror = fallback;
	img.src = currentIconHref();
} )();
