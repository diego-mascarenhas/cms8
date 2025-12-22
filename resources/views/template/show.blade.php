@extends('layouts/layoutMaster')

@section('title', 'Template Detail')

@section('vendor-style')
<link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
<style>
	.gjs-cv-canvas {
		top: 0;
		width: 100%;
		height: 100%;
	}
	#gjs {
		border: 3px solid #444;
	}
	/* Adjust GrapesJS editor for readonly mode */
	.gjs-editor-cont {
		pointer-events: none;
	}
</style>
@endsection

@section('vendor-script')
<script src="https://unpkg.com/grapesjs"></script>
<script src="https://unpkg.com/grapesjs-preset-webpage"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">
			<span class="text-muted fw-light">Templates/</span> {{ $page->name }}
		</h4>
		<p class="text-muted">View template design</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<!-- Edit Button -->
		<a href="{{ route('template.editor', $page->getHashedId()) }}" class="btn btn-primary waves-effect waves-light">
			<i class="ti ti-edit me-1"></i>Edit in Editor
		</a>
		
		<!-- Back Button -->
		<a href="{{ route('template.index') }}" class="btn btn-outline-secondary waves-effect waves-light">
			<i class="ti ti-arrow-left me-1"></i>Back to List
		</a>
	</div>
</div>

<!-- GrapesJS Editor Container (Read-only) -->
<div class="card">
	<div class="card-body p-0">
		<div id="gjs" style="height: 70vh; overflow: hidden;"></div>
	</div>
</div>
@endsection

@section('page-script')
<script>
	document.addEventListener('DOMContentLoaded', function() {
		// Initialize GrapesJS in read-only mode
		const editor = grapesjs.init({
			container: '#gjs',
			height: '70vh',
			fromElement: false,
			storageManager: false,
			// Load the saved template data
			components: {!! json_encode($page->gjs_data['components'] ?? []) !!},
			style: {!! json_encode($page->gjs_data['styles'] ?? []) !!},
			// Disable panels for read-only view
			panels: { defaults: [] },
			// Load preset for better display
			plugins: ['gjs-preset-webpage'],
			pluginsOpts: {
				'gjs-preset-webpage': {
					modalImportTitle: 'Import Template',
					modalImportLabel: '<div style="margin-bottom: 10px; font-size: 13px;">Paste here your HTML/CSS and click Import</div>',
					modalImportContent: function(editor) {
						return editor.getHtml() + '<style>' + editor.getCss() + '</style>';
					},
				}
			}
		});
		
		// Make canvas read-only
		editor.setDragMode('absolute');
		editor.Canvas.getDocument().body.style.pointerEvents = 'none';
		
		// Disable commands to prevent editing
		editor.Commands.add('core:canvas-clear', { run: () => {} });
	});
</script>
@endsection

