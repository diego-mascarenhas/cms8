@extends('layouts/layoutMaster')

@section('title', 'Create Claude AI Prompt')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Create New Claude AI Prompt</h5>
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
                
                <form action="{{ route('claude.prompts.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Prompt Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               placeholder="e.g., customer_service, legal_assistant" required
                               value="{{ old('name') }}">
                        <small class="form-text text-muted">
                            Use a descriptive name without spaces (use underscores instead).
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Prompt Content</label>
                        <textarea class="form-control" id="content" name="content" rows="15" required
                                  placeholder="Enter the system prompt instructions for Claude...">{{ old('content') }}</textarea>
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
                    
                    <div class="mb-3">
                        <h6>Example Prompt Template:</h6>
                        <pre class="bg-light p-3 rounded">You are a customer service AI assistant for [COMPANY NAME].

Your responsibilities:
1. Answer customer questions about our products and services
2. Help with troubleshooting common issues
3. Direct customers to resources when needed

Guidelines:
- Keep your responses friendly, helpful, and concise
- Responses should be under 3 paragraphs for readability on mobile
- If you don't know an answer, be honest and offer to connect the customer with a human agent
- Don't make up information about our products or pricing

For WhatsApp, ensure messages are formatted well for mobile screens.
                        </pre>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Prompt</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection 