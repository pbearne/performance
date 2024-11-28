<?php
return array(
	'set_up'   => static function ( Test_OD_Optimization $test_case ): void {
		$tag_visitor_registry = new OD_Tag_Visitor_Registry();
		$tag_visitor_registry->register( 'img', static function (): void {} );
		$tag_visitor_registry->register( 'video', static function (): void {} );

		$test_case->populate_url_metrics(
			array(
				array(
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]',
					'isLCP' => true,
				),
			),
			od_compute_current_etag( $tag_visitor_registry ),
			false
		);
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<video width="620" controls poster="https://upload.wikimedia.org/wikipedia/commons/e/e8/Elephants_Dream_s5_both.jpg">
					<source src="https://archive.org/download/ElephantsDream/ed_hd.avi" type="video/avi" />
					<source src="https://archive.org/download/ElephantsDream/ed_1024_512kb.mp4" type="video/mp4" />
				</video>
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
				<video data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]" width="620" controls poster="https://upload.wikimedia.org/wikipedia/commons/e/e8/Elephants_Dream_s5_both.jpg">
					<source src="https://archive.org/download/ElephantsDream/ed_hd.avi" type="video/avi" />
					<source src="https://archive.org/download/ElephantsDream/ed_1024_512kb.mp4" type="video/mp4" />
				</video>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
