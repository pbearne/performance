/**
 * Handles activation of Performance Features (Plugins) using AJAX.
 */

/* global perflabPluginActivateAjaxData */
( function ( $ ) {
	// @ts-ignore
	const { i18n, a11y } = wp;
	const { __, _x } = i18n;

	/**
	 * Adds a click event listener to the plugin activate buttons.
	 *
	 * @param {MouseEvent} event - The click event object that is triggered when the user clicks on the document.
	 *
	 * @return {Promise<void>} - The asynchronous function returns a promise.
	 */
	$( document ).on(
		'click',
		'.perflab-install-active-plugin',
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
						showAdminNotice(
							__(
								'There was an error activating the plugin. Please try again.',
								'performance-lab'
							)
						);

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

					showAdminNotice(
						__( 'Feature activated.', 'performance-lab' ),
						'success',
						pluginSettingsURL
					);
				},
				error() {
					showAdminNotice(
						__(
							'There was an error activating the plugin. Please try again.',
							'performance-lab'
						)
					);

					target
						.removeClass( 'updating-message' )
						.text( __( 'Activate', 'performance-lab' ) );
				},
			} );
		}
	);

	/**
	 * Displays an admin notice with the given message and type.
	 *
	 * @param {string} message             - The message to display in the notice.
	 * @param {string} [type='error']      - The type of notice ('error', 'success', etc.).
	 * @param {string} [pluginSettingsURL] - Optional URL for the plugin settings.
	 */
	function showAdminNotice(
		message,
		type = 'error',
		pluginSettingsURL = undefined
	) {
		a11y.speak( message );

		// Create the notice container elements.
		const notice = $( '<div>', {
			class: `notice is-dismissible notice-${ type }`,
		} );

		const messageWrap = $( '<p>' ).text( message );

		// If a plugin settings URL is provided, append a 'Review settings.' link.
		if ( pluginSettingsURL ) {
			messageWrap
				.append( ` ${ __( 'Review', 'performance-lab' ) } ` )
				.append(
					$( '<a>', {
						href: pluginSettingsURL,
						text: __( 'settings', 'performance-lab' ),
					} )
				)
				.append( _x( '.', 'Punctuation mark', 'performance-lab' ) );
		}

		const dismissButton = $( '<button>', {
			type: 'button',
			class: 'notice-dismiss',
			click: () => notice.remove(),
		} ).append(
			$( '<span>', {
				class: 'screen-reader-text',
				text: __( 'Dismiss this notice.', 'performance-lab' ),
			} )
		);

		notice.append( messageWrap, dismissButton );

		const noticeContainer = $( '.wrap.plugin-install-php' );

		if ( noticeContainer.length ) {
			// If the container exists, insert the notice after the first child.
			noticeContainer.children().eq( 0 ).after( notice );
		} else {
			$( 'body' ).prepend( notice );
		}
	}

	// @ts-ignore
} )( window.jQuery );
