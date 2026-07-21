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
	 * list, and each .bizupkeep-repeater gets a working "+ Add" /
	 * "Remove" repeater (directors, or Annual Return financial years).
	 * All four behaviours are no-ops (the querySelectorAll calls just
	 * return empty lists) on any other page, so this is safe to run
	 * everywhere custom.js loads rather than gating it to the apply
	 * page specifically.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		bizupkeepInitApplicationTypeToggle();
		bizupkeepInitAmendmentTypeToggle();
		bizupkeepInitCompanyModeToggle();
		bizupkeepInitCompanyDirectorToggle();
		bizupkeepInitRepeaters();
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

	/**
	 * The "Which Company?" existing/new radio pair (see
	 * bizupkeep_child_render_company_picker() in functions.php): shows
	 * whichever of the two [data-company-mode-section] blocks matches
	 * the checked radio's data-reveals value. Switching to "new"
	 * (company not registered with us) also hides that section's
	 * existing-directors list, if one happens to be showing from a
	 * previously selected existing company - otherwise it would stay
	 * visible even though no company is selected any more.
	 */
	function bizupkeepInitCompanyModeToggle() {
		var toggles = document.querySelectorAll( '.bizupkeep-company-mode-toggle' );

		if ( ! toggles.length ) {
			return;
		}

		var prefixes = [];

		toggles.forEach( function ( toggle ) {
			var prefix = toggle.getAttribute( 'data-mode-prefix' );

			if ( prefix && -1 === prefixes.indexOf( prefix ) ) {
				prefixes.push( prefix );
			}
		} );

		prefixes.forEach( function ( prefix ) {
			var existingSection = document.querySelector( '[data-company-mode-section="' + prefix + '-mode-existing"]' );
			var newSection = document.querySelector( '[data-company-mode-section="' + prefix + '-mode-new"]' );
			var prefixToggles = document.querySelectorAll( '.bizupkeep-company-mode-toggle[data-mode-prefix="' + prefix + '"]' );

			prefixToggles.forEach( function ( toggle ) {
				toggle.addEventListener( 'change', function () {
					if ( ! toggle.checked ) {
						return;
					}

					var isNew = 'new' === toggle.value;

					if ( existingSection ) {
						existingSection.hidden = isNew;
					}

					if ( newSection ) {
						newSection.hidden = ! isNew;
					}

					if ( isNew ) {
						document.querySelectorAll( '[data-existing-directors-for="' + prefix + '"]' ).forEach( function ( block ) {
							block.hidden = true;
						} );
					}
				} );
			} );
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
	 * Generic "+ Add" / "Remove" repeater, driven entirely by data
	 * attributes so the same code serves the New Registration director
	 * list, the Company Amendment "add new director(s)" list, and the
	 * Annual Return "which financial year(s)" list: data-repeater (a
	 * label, unused by the JS itself), data-template-id (the <template>
	 * holding one blank block, with field names using the literal
	 * placeholder "__INDEX__" - see bizupkeep_child_render_director_fields()/
	 * bizupkeep_child_render_filing_fields() in functions.php),
	 * data-max (upper bound on blocks). The remove handler looks for
	 * the clicked button's closest direct child of the blocks
	 * container rather than a specific per-type block class, so it
	 * works for whatever kind of row a given repeater actually holds.
	 */
	function bizupkeepInitRepeaters() {
		var repeaters = document.querySelectorAll( '.bizupkeep-repeater' );

		repeaters.forEach( function ( repeater ) {
			var template = document.getElementById( repeater.getAttribute( 'data-template-id' ) );
			var blocksContainer = repeater.querySelector( '.bizupkeep-repeater-blocks' );
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

				var block = event.target.closest( '.bizupkeep-repeater-blocks > *' );

				if ( block ) {
					block.remove();
				}
			} );
		} );
	}
} )();
