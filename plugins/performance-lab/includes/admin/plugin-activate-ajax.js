/**
 * Handles activation of Performance Features (Plugins) using AJAX.
 */

/* global perflabPluginActivateAjaxData */
document.addEventListener( 'DOMContentLoaded', function () {
	// @ts-ignore
	const { i18n, a11y } = wp;
	const { __, _x } = i18n;

	/**
	 * Adds a click event listener to the document.
	 *
	 * This asynchronous function listens for click events on the document and executes
	 * the provided callback function if triggered.
	 *
	 * @param {MouseEvent} event - The click event object that is triggered when the user clicks on the document.
	 *
	 * @return {Promise<void>} - The asynchronous function returns a promise that resolves to void.
	 */
	document.addEventListener( 'click', async function ( event ) {
		const target = /** @type {HTMLElement} */ ( event.target );

		if ( target.classList.contains( 'perflab-install-active-plugin' ) ) {
			// Prevent the default link behavior.
			event.preventDefault();

			target.classList.add( 'updating-message' );
			target.textContent = __( 'Activating…', 'performance-lab' );

			a11y.speak( __( 'Activating…', 'performance-lab' ) );

			const pluginSlug = target.getAttribute( 'data-plugin-slug' ).trim();

			try {
				const response = await fetch(
					// @ts-ignore
					perflabPluginActivateAjaxData.ajaxUrl,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: new URLSearchParams( {
							action: 'perflab_install_activate_plugin',
							slug: pluginSlug,
							// @ts-ignore
							_ajax_nonce: perflabPluginActivateAjaxData.nonce,
						} ),
					}
				);

				const responseData = await response.json();

				if ( ! responseData.success ) {
					showAdminNotice(
						__(
							'There was an error activating the plugin. Please try again.',
							'performance-lab'
						)
					);

					target.classList.remove( 'updating-message' );
					target.textContent = __( 'Activate', 'performance-lab' );

					return;
				}

				const newButton = document.createElement( 'button' );

				newButton.type = 'button';
				newButton.className = 'button button-disabled';
				newButton.disabled = true;
				newButton.textContent = __( 'Active', 'performance-lab' );

				target.parentNode.replaceChild( newButton, target );

				const pluginSettingsURL = responseData?.data?.pluginSettingsURL;

				const actionButtonList = document.querySelector(
					'.plugin-card-' + pluginSlug + ' .plugin-action-buttons'
				);

				if ( pluginSettingsURL && actionButtonList ) {
					const listItem = document.createElement( 'li' );
					const anchor = document.createElement( 'a' );

					anchor.setAttribute( 'href', pluginSettingsURL );
					anchor.textContent = __( 'Settings', 'performance-lab' );

					listItem.appendChild( anchor );
					actionButtonList.appendChild( listItem );
				}

				showAdminNotice(
					__( 'Feature activated.', 'performance-lab' ),
					'success',
					pluginSettingsURL
				);
			} catch ( error ) {
				showAdminNotice(
					__(
						'There was an error activating the plugin. Please try again.',
						'performance-lab'
					)
				);

				target.classList.remove( 'updating-message' );
				target.textContent = __( 'Activate', 'performance-lab' );
			}
		}
	} );

	function showAdminNotice(
		message,
		type = 'error',
		pluginSettingsURL = undefined
	) {
		// Create the notice container elements.
		const notice = document.createElement( 'div' );
		const para = document.createElement( 'p' );

		notice.className = 'notice is-dismissible notice-' + type;
		para.textContent = message;

		if ( pluginSettingsURL ) {
			para.textContent = `${ para.textContent } ${ __(
				'Review',
				'performance-lab'
			) } `;

			const anchor = document.createElement( 'a' );
			anchor.setAttribute( 'href', pluginSettingsURL );
			anchor.textContent = __( 'settings', 'performance-lab' );

			para.appendChild( anchor );
			para.appendChild(
				document.createTextNode(
					_x( '.', 'Punctuation mark', 'performance-lab' )
				)
			);
		}

		notice.appendChild( para );

		const dismissButton = document.createElement( 'button' );
		const dismissButtonTextWrap = document.createElement( 'span' );

		dismissButton.type = 'button';
		dismissButton.className = 'notice-dismiss';

		dismissButtonTextWrap.className = 'screen-reader-text';
		dismissButtonTextWrap.textContent = __(
			'Dismiss this notice.',
			'performance-lab'
		);

		dismissButton.appendChild( dismissButtonTextWrap );

		// Add event listener to remove the notice when dismissed.
		dismissButton.addEventListener( 'click', () => {
			notice.remove();
		} );

		notice.appendChild( dismissButton );

		// Insert the notice at the top of the admin notices area.
		const noticeContainer =
			document.querySelector( '.wrap.plugin-install-php' ) ||
			document.body;

		if ( ! noticeContainer ) {
			// Fallback append to body if no suitable container is found.
			document.body.prepend( notice );

			return;
		}

		if ( noticeContainer.children.length >= 1 ) {
			// Insert as the second child.
			noticeContainer.insertBefore(
				notice,
				noticeContainer.children[ 1 ]
			);

			return;
		}

		// If there's only one child or none, append as the last child.
		noticeContainer.appendChild( notice );
	}
} );
