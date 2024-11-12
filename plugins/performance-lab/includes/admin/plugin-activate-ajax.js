/**
 * Handles activation of Performance Features (Plugins) using AJAX.
 */

/* global perflabPluginActivateAjaxData */
( function () {
	// @ts-ignore
	const { i18n, a11y } = wp;
	const { __ } = i18n;

	/**
	 * Handles click events on elements with the class 'perflab-install-active-plugin'.
	 *
	 * This asynchronous function listens for click events on the document and executes
	 * the provided callback function if triggered.
	 *
	 * @param {MouseEvent} event - The click event object that is triggered when the user clicks on the document.
	 *
	 * @return {Promise<void>} - The asynchronous function returns a promise that resolves to void.
	 */
	async function handlePluginActivationClick( event ) {
		const target = /** @type {HTMLElement} */ ( event.target );

		if ( ! target.classList.contains( 'perflab-install-active-plugin' ) ) {
			return;
		}

		// Prevent the default link behavior.
		event.preventDefault();

		target.classList.add( 'updating-message' );
		target.textContent = __( 'Activating…', 'performance-lab' );

		a11y.speak( __( 'Activating…', 'performance-lab' ) );

		const pluginSlug = target.getAttribute( 'data-plugin-slug' ).trim();

		// Send an AJAX POST request to activate the plugin.
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
				`.plugin-card-${ pluginSlug } .plugin-action-buttons`
			);

			if ( pluginSettingsURL && actionButtonList ) {
				const listItem = document.createElement( 'li' );
				const anchor = document.createElement( 'a' );

				anchor.setAttribute( 'href', pluginSettingsURL );
				anchor.textContent = __( 'Settings', 'performance-lab' );

				listItem.appendChild( anchor );
				actionButtonList.appendChild( listItem );
			}
		} catch ( error ) {
			target.classList.remove( 'updating-message' );
			target.textContent = __( 'Activate', 'performance-lab' );
		}
	}

	// Attach the event listener.
	document.addEventListener( 'click', handlePluginActivationClick );
} )();
