@extends('layouts/layoutMaster')

@section('title', 'Claude AI Prompts')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
@endsection

@section('page-style')
<style>
    .prompt-preview {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
        white-space: pre-wrap;
        font-family: monospace;
    }
    .loader {
        border: 3px solid #f3f3f3;
        border-radius: 50%;
        border-top: 3px solid #3498db;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Manage Claude AI Prompts</h5>
                <a href="{{ route('claude.prompts.create') }}" class="btn btn-primary">Create New Prompt</a>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                
                <div class="alert alert-info">
                    <strong>Active prompt:</strong> {{ $activePrompt }}
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped" id="prompts-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Last Modified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Default</td>
                                <td>System</td>
                                <td>-</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-primary preview-prompt" data-prompt="default">Preview</button>
                                        <form action="{{ route('claude.prompts.activate') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="prompt_name" value="default">
                                            <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @foreach($prompts as $prompt)
                            <tr>
                                <td>{{ $prompt['name'] }}</td>
                                <td>{{ round($prompt['size'] / 1024, 2) }} KB</td>
                                <td>{{ date('Y-m-d H:i', $prompt['modified']) }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('claude.prompts.edit', $prompt['name']) }}" 
                                           class="btn btn-sm btn-info">Edit</a>
                                        <button type="button" 
                                               class="btn btn-sm btn-primary preview-prompt" 
                                               data-prompt="{{ $prompt['name'] }}">Preview</button>
                                        <form action="{{ route('claude.prompts.activate') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="prompt_name" value="{{ $prompt['name'] }}">
                                            <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                        </form>
                                        <form action="{{ route('claude.prompts.destroy', $prompt['name']) }}" 
                                              method="POST" 
                                              class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this prompt?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Preview Prompt Response</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="previewForm">
                    <input type="hidden" id="promptName" name="prompt_name">
                    <div class="mb-3">
                        <label for="testMessage" class="form-label">Test Message</label>
                        <textarea class="form-control" id="testMessage" name="test_message" rows="3" 
                                 placeholder="Enter a test message to see how Claude would respond..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
                
                <div id="responseContainer" class="mt-3" style="display: none;">
                    <h6>Claude's Response:</h6>
                    <div id="responseLoader" style="display: none;">
                        <span class="loader"></span> Processing...
                    </div>
                    <div id="responseContent" class="prompt-preview"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#prompts-table').DataTable({
        "order": [[2, "desc"]]
    });
    
    // Handle preview button clicks
    const previewButtons = document.querySelectorAll('.preview-prompt');
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    
    previewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const promptName = this.getAttribute('data-prompt');
            document.getElementById('promptName').value = promptName;
            document.getElementById('testMessage').value = '';
            document.getElementById('responseContainer').style.display = 'none';
            previewModal.show();
        });
    });
    
    // Handle preview form submission
    const previewForm = document.getElementById('previewForm');
    previewForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const promptName = document.getElementById('promptName').value;
        const testMessage = document.getElementById('testMessage').value;
        
        if (!testMessage.trim()) {
            alert('Please enter a test message');
            return;
        }
        
        // Show loading indicator
        document.getElementById('responseContainer').style.display = 'block';
        document.getElementById('responseLoader').style.display = 'block';
        document.getElementById('responseContent').textContent = '';
        
        // Send request to preview endpoint
        fetch('{{ route("claude.prompts.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                prompt_name: promptName,
                test_message: testMessage
            })
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading indicator
            document.getElementById('responseLoader').style.display = 'none';
            
            if (data.success) {
                document.getElementById('responseContent').textContent = data.response;
            } else {
                document.getElementById('responseContent').textContent = 'Error: ' + (data.message || 'Failed to get response');
            }
        })
        .catch(error => {
            // Hide loading indicator
            document.getElementById('responseLoader').style.display = 'none';
            document.getElementById('responseContent').textContent = 'Error: ' + error.message;
        });
    });
});
</script>
@endsection 