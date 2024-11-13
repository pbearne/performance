/**
 * Handles activation of Performance Features (Plugins) using AJAX.
 */

( function () {
	// @ts-ignore
	const { i18n, a11y, apiFetch } = wp;
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

		if (
			target.classList.contains( 'updating-message' ) ||
			target.classList.contains( 'disabled' )
		) {
			return;
		}

		target.classList.add( 'updating-message' );
		target.textContent = __( 'Activating…', 'performance-lab' );

		a11y.speak( __( 'Activating…', 'performance-lab' ) );

		const pluginSlug = target.dataset.pluginSlug;

		try {
			// Activate the plugin via the REST API.
			await apiFetch( {
				path: '/performance-lab/v1/activate-plugin',
				method: 'POST',
				data: { slug: pluginSlug },
			} );

			// Fetch the plugin settings URL via the REST API.
			const settingsResponse = await apiFetch( {
				path: '/performance-lab/v1/plugin-settings-url',
				method: 'POST',
				data: { slug: pluginSlug },
			} );

			a11y.speak( __( 'Plugin activated.', 'performance-lab' ) );

			target.textContent = __( 'Active', 'performance-lab' );
			target.classList.remove( 'updating-message' );
			target.classList.add( 'disabled' );

			const actionButtonList = document.querySelector(
				`.plugin-card-${ pluginSlug } .plugin-action-buttons`
			);

			if ( settingsResponse?.pluginSettingsURL && actionButtonList ) {
				const listItem = document.createElement( 'li' );
				const anchor = document.createElement( 'a' );

				anchor.href = settingsResponse?.pluginSettingsURL;
				anchor.textContent = __( 'Settings', 'performance-lab' );

				listItem.appendChild( anchor );
				actionButtonList.appendChild( listItem );
			}
		} catch ( error ) {
			a11y.speak( __( 'Plugin failed to activate.', 'performance-lab' ) );

			target.classList.remove( 'updating-message' );
			target.textContent = __( 'Activate', 'performance-lab' );
		}
	}

	// Attach the event listener.
	document.addEventListener( 'click', handlePluginActivationClick );
} )();
