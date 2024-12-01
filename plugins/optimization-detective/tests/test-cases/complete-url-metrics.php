<?php
return array(
	'set_up'   => static function ( Test_OD_Optimization $test_case ): void {
		ini_set( 'default_mimetype', 'text/html; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$tag_visitor_registry = new OD_Tag_Visitor_Registry();
		$tag_visitor_registry->register( 'img', static function (): void {} );
		$tag_visitor_registry->register( 'video', static function (): void {} );

		$test_case->populate_url_metrics(
			array(
				array(
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP' => true,
				),
			),
			od_get_current_etag( $tag_visitor_registry )
		);
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
			</body>
		</html>
	',
);
