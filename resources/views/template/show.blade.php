<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ $page->name }} - Preview</title>

	<style>
		{!! $page->gjs_data['css'] ?? '' !!}
	</style>
</head>
<body>
	{!! $page->gjs_data['html'] ?? '' !!}
</body>
</html>

