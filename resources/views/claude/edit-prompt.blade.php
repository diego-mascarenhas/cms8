@extends('layouts/layoutMaster')

@section('title', 'Edit Claude AI Prompt')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Claude AI Prompt: {{ $name }}</h5>
                <a href="{{ route('claude.prompts.index') }}" class="btn btn-secondary">Back to Prompts</a>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <form action="{{ route('claude.prompts.update', $name) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Prompt Content</label>
                        <textarea class="form-control" id="content" name="content" rows="15" required>{{ $content }}</textarea>
                        <small class="form-text text-muted">
                            Write clear instructions that define Claude's behavior, personality, and constraints.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Prompt Tips:</h6>
                        <ul>
                            <li>Start with a clear role definition, e.g., "You are a customer service specialist for a software company."</li>
                            <li>Specify the tone you want Claude to use, e.g., professional, friendly, concise.</li>
                            <li>Include specific constraints or rules Claude should follow.</li>
                            <li>Consider including examples of good responses if applicable.</li>
                            <li>For WhatsApp, keep responses concise as they're being read on mobile devices.</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Prompt</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection 