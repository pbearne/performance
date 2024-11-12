/**
 * Handles activation of Performance Features (Plugins) using AJAX.
 */

/* global perflabPluginActivateAjaxData */
( function ( $ ) {
	// @ts-ignore
	const { i18n, a11y } = wp;
	const { __ } = i18n;

	$( document ).on(
		'click',
		'.perflab-install-active-plugin',
		/**
		 * Adds a click event listener to the plugin activate buttons.
		 *
		 * @param {MouseEvent} event - The click event object that is triggered when the user clicks on the document.
		 *
		 * @return {Promise<void>} - The asynchronous function returns a promise.
		 */
		async function ( event ) {
			// Prevent the default link behavior.
			event.preventDefault();

			// Get the clicked element as a jQuery object.
			const target = $( this );

			target
				.addClass( 'updating-message' )
				.text( __( 'Activating…', 'performance-lab' ) );

			a11y.speak( __( 'Activating…', 'performance-lab' ) );

			// Retrieve the plugin slug from the data attribute.
			const pluginSlug = $.trim( target.attr( 'data-plugin-slug' ) );

			// Send an AJAX POST request to activate the plugin.
			$.ajax( {
				// @ts-ignore
				url: perflabPluginActivateAjaxData.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'perflab_install_activate_plugin',
					slug: pluginSlug,
					// @ts-ignore
					_ajax_nonce: perflabPluginActivateAjaxData.nonce,
				},
				success( responseData ) {
					if ( ! responseData.success ) {
						target
							.removeClass( 'updating-message' )
							.text( __( 'Activate', 'performance-lab' ) );

						return;
					}

					// Replace the 'Activate' button with a disabled 'Active' button.
					target.replaceWith(
						$( '<button>', {
							type: 'button',
							class: 'button button-disabled',
							disabled: true,
							text: __( 'Active', 'performance-lab' ),
						} )
					);

					const pluginSettingsURL =
						responseData?.data?.pluginSettingsURL;

					// Select the container for action buttons related to the plugin.
					const actionButtonList = $(
						`.plugin-card-${ pluginSlug } .plugin-action-buttons`
					);

					if ( pluginSettingsURL && actionButtonList ) {
						// Append a 'Settings' link to the action buttons.
						actionButtonList.append(
							$( '<li>' ).append(
								$( '<a>', {
									href: pluginSettingsURL,
									text: __( 'Settings', 'performance-lab' ),
								} )
							)
						);
					}
				},
				error() {
					target
						.removeClass( 'updating-message' )
						.text( __( 'Activate', 'performance-lab' ) );
				},
			} );
		}
	);

	// @ts-ignore
} )( window.jQuery );
