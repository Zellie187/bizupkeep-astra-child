/**
 * BizUpKeep - custom front-end scripts.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.getElementById( 'bizupkeep-menu-toggle' );
		var nav = document.getElementById( 'bizupkeep-primary-nav' );

		if ( ! toggle || ! nav ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );
	} );

	/**
	 * Client Portal dropdown: :hover opens it on desktop (see
	 * custom.css), but touch devices have no hover state, so on those
	 * (or narrow viewports, matching custom.css's 768px breakpoint) a
	 * tap on the "Client Portal" parent link toggles .is-open instead
	 * of following the link straight to the dashboard - a second tap
	 * (or tapping elsewhere) opens/closes it. Desktop mouse clicks are
	 * left alone entirely, since hover already shows the dropdown
	 * there and intercepting the first click would make the parent
	 * link itself require two clicks to follow.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var noHover = window.matchMedia( '(hover: none), (max-width: 768px)' );

		if ( ! noHover.matches ) {
			return;
		}

		var parents = document.querySelectorAll( '.bizupkeep-portal-menu > li.menu-item-has-children' );

		parents.forEach( function ( parent ) {
			var link = parent.querySelector( ':scope > a' );

			if ( ! link ) {
				return;
			}

			link.addEventListener( 'click', function ( event ) {
				if ( parent.classList.contains( 'is-open' ) ) {
					return;
				}

				event.preventDefault();
				parents.forEach( function ( other ) {
					other.classList.remove( 'is-open' );
				} );
				parent.classList.add( 'is-open' );
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			parents.forEach( function ( parent ) {
				if ( ! parent.contains( event.target ) ) {
					parent.classList.remove( 'is-open' );
				}
			} );
		} );
	} );

	/**
	 * Apply form (page-templates/template-apply.php): the
	 * application_type radio buttons show/hide the matching
	 * .bizupkeep-apply-section, the amendment_types[] checkboxes
	 * show/hide their matching .bizupkeep-amendment-subsection, the
	 * company picker shows the matching company's existing-directors
	 * list, and each .bizupkeep-director-repeater gets a working
	 * "+ Add Director" / "Remove" repeater. All four behaviours are
	 * no-ops (the querySelectorAll calls just return empty lists) on
	 * any other page, so this is safe to run everywhere custom.js
	 * loads rather than gating it to the apply page specifically.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		bizupkeepInitApplicationTypeToggle();
		bizupkeepInitAmendmentTypeToggle();
		bizupkeepInitCompanyDirectorToggle();
		bizupkeepInitDirectorRepeaters();
	} );

	function bizupkeepInitApplicationTypeToggle() {
		var radios = document.querySelectorAll( 'input[name="application_type"]' );
		var sections = document.querySelectorAll( '.bizupkeep-apply-section[data-application-type]' );

		if ( ! radios.length || ! sections.length ) {
			return;
		}

		function applyType( type ) {
			sections.forEach( function ( section ) {
				section.hidden = section.getAttribute( 'data-application-type' ) !== type;
			} );
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( radio.checked ) {
					applyType( radio.value );
				}
			} );

			if ( radio.checked ) {
				applyType( radio.value );
			}
		} );
	}

	function bizupkeepInitAmendmentTypeToggle() {
		var toggles = document.querySelectorAll( '.bizupkeep-amendment-type-toggle' );

		if ( ! toggles.length ) {
			return;
		}

		toggles.forEach( function ( toggle ) {
			var target = document.querySelector( '[data-amendment-subsection="' + toggle.getAttribute( 'data-reveals' ) + '"]' );

			if ( ! target ) {
				return;
			}

			toggle.addEventListener( 'change', function () {
				target.hidden = ! toggle.checked;
			} );

			target.hidden = ! toggle.checked;
		} );
	}

	function bizupkeepInitCompanyDirectorToggle() {
		var pickers = document.querySelectorAll( '.bizupkeep-company-picker[data-existing-directors-target]' );

		if ( ! pickers.length ) {
			return;
		}

		pickers.forEach( function ( picker ) {
			var group = picker.getAttribute( 'data-existing-directors-target' );
			var blocks = document.querySelectorAll( '[data-existing-directors-for="' + group + '"]' );

			picker.addEventListener( 'change', function () {
				blocks.forEach( function ( block ) {
					block.hidden = block.getAttribute( 'data-company-uuid' ) !== picker.value;
				} );
			} );
		} );
	}

	/**
	 * Generic "+ Add Director" / "Remove" repeater, driven entirely by
	 * data attributes so the same code serves both the New Registration
	 * director list and the Company Amendment "add new director(s)"
	 * list: data-repeater (a label, unused by the JS itself),
	 * data-template-id (the <template> holding one blank block, with
	 * field names using the literal placeholder "__INDEX__" - see
	 * bizupkeep_child_render_director_fields() in functions.php),
	 * data-max (upper bound on blocks).
	 */
	function bizupkeepInitDirectorRepeaters() {
		var repeaters = document.querySelectorAll( '.bizupkeep-director-repeater' );

		repeaters.forEach( function ( repeater ) {
			var template = document.getElementById( repeater.getAttribute( 'data-template-id' ) );
			var blocksContainer = repeater.querySelector( '.bizupkeep-director-blocks' );
			var addButton = repeater.querySelector( '.bizupkeep-repeater-add' );
			var max = parseInt( repeater.getAttribute( 'data-max' ), 10 ) || 10;
			var nextIndex = blocksContainer ? blocksContainer.children.length : 0;

			if ( ! template || ! blocksContainer || ! addButton ) {
				return;
			}

			addButton.addEventListener( 'click', function () {
				if ( blocksContainer.children.length >= max ) {
					return;
				}

				var html = template.innerHTML.split( '__INDEX__' ).join( String( nextIndex ) );
				var wrapper = document.createElement( 'div' );
				wrapper.innerHTML = html.trim();

				if ( wrapper.firstElementChild ) {
					blocksContainer.appendChild( wrapper.firstElementChild );
					nextIndex++;
				}
			} );

			blocksContainer.addEventListener( 'click', function ( event ) {
				if ( ! event.target.classList.contains( 'bizupkeep-repeater-remove' ) ) {
					return;
				}

				var block = event.target.closest( '.bizupkeep-director-block' );

				if ( block ) {
					block.remove();
				}
			} );
		} );
	}
} )();
