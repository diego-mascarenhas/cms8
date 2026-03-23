<?php

namespace App\Http\Controllers;

use App\Services\AssistantSystemPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

class ClaudePromptController extends Controller
{
    protected $promptsDir;

    public function __construct()
    {
        $this->promptsDir = storage_path('app/claude_prompts');

        if (! File::exists($this->promptsDir))
        {
            File::makeDirectory($this->promptsDir, 0755, true);
        }
    }

    /**
     * Display a list of saved prompts
     */
    public function index()
    {
        $prompts = $this->getPromptsList();

        // Get active prompt from env if available
        $activePrompt = env('CLAUDE_SYSTEM_PROMPT') ? 'Custom (from .env)' : 'Default';

        return view('claude.prompts', compact('prompts', 'activePrompt'));
    }

    /**
     * Show the form to create a new prompt
     */
    public function create()
    {
        return view('claude.create-prompt');
    }

    /**
     * Store a new prompt
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $fileName = $this->sanitizeFileName($request->name).'.txt';
        $filePath = $this->promptsDir.'/'.$fileName;

        // Save the prompt
        File::put($filePath, $request->content);

        return redirect()->route('claude.prompts.index')
            ->with('success', 'Prompt saved successfully.');
    }

    /**
     * Show edit form for a prompt
     */
    public function edit($promptName)
    {
        $fileName = $promptName.'.txt';
        $filePath = $this->promptsDir.'/'.$fileName;

        if (! File::exists($filePath))
        {
            return redirect()->route('claude.prompts.index')
                ->with('error', 'Prompt not found.');
        }

        $content = File::get($filePath);

        return view('claude.edit-prompt', [
            'name' => $promptName,
            'content' => $content,
        ]);
    }

    /**
     * Update a prompt
     */
    public function update(Request $request, $promptName)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $fileName = $promptName.'.txt';
        $filePath = $this->promptsDir.'/'.$fileName;

        if (! File::exists($filePath))
        {
            return redirect()->route('claude.prompts.index')
                ->with('error', 'Prompt not found.');
        }

        // Update the prompt
        File::put($filePath, $request->content);

        return redirect()->route('claude.prompts.index')
            ->with('success', 'Prompt updated successfully.');
    }

    /**
     * Delete a prompt
     */
    public function destroy($promptName)
    {
        $fileName = $promptName.'.txt';
        $filePath = $this->promptsDir.'/'.$fileName;

        if (File::exists($filePath))
        {
            File::delete($filePath);

            return redirect()->route('claude.prompts.index')
                ->with('success', 'Prompt deleted successfully.');
        }

        return redirect()->route('claude.prompts.index')
            ->with('error', 'Prompt not found.');
    }

    /**
     * Activate a prompt (set in memory for current session)
     */
    public function activate(Request $request)
    {
        $request->validate([
            'prompt_name' => 'required|string',
        ]);

        $promptName = $request->prompt_name;

        if ($promptName === 'default')
        {
            AssistantSystemPrompt::reset();

            return redirect()->route('claude.prompts.index')
                ->with('success', 'Default prompt activated.');
        }

        $fileName = $promptName.'.txt';
        $filePath = $this->promptsDir.'/'.$fileName;

        if (! File::exists($filePath))
        {
            return redirect()->route('claude.prompts.index')
                ->with('error', 'Prompt not found.');
        }

        $promptContent = File::get($filePath);
        AssistantSystemPrompt::set($promptContent);

        return redirect()->route('claude.prompts.index')
            ->with('success', "Prompt '{$promptName}' activated for current session.");
    }

    /**
     * Preview a prompt response
     */
    public function preview(Request $request)
    {
        $request->validate([
            'prompt_name' => 'required|string',
            'test_message' => 'required|string',
        ]);

        $promptName = $request->prompt_name;
        $testMessage = $request->test_message;

        if ($promptName === 'default')
        {
            // Use default prompt
            $promptContent = null;
        } else
        {
            $fileName = $promptName.'.txt';
            $filePath = $this->promptsDir.'/'.$fileName;

            if (! File::exists($filePath))
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Prompt not found',
                ]);
            }

            $promptContent = File::get($filePath);
        }

        try
        {
            $instructions = $promptContent ?? AssistantSystemPrompt::get();
            $agent = agent(instructions: $instructions, messages: [], tools: []);
            $response = $agent->prompt($testMessage, [], Lab::Anthropic);
            $text = $response->text ?? '';

            return response()->json([
                'success' => true,
                'response' => $text,
            ]);
        } catch (\Throwable $e)
        {
            \Log::error('Exception in Claude preview: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Exception: '.$e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get a list of all saved prompts
     */
    private function getPromptsList()
    {
        $prompts = [];

        if (File::exists($this->promptsDir))
        {
            $files = File::files($this->promptsDir);

            foreach ($files as $file)
            {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $prompts[] = [
                    'name' => $name,
                    'path' => $file->getPathname(),
                    'size' => File::size($file),
                    'modified' => File::lastModified($file),
                ];
            }
        }

        return $prompts;
    }

    /**
     * Sanitize a filename to avoid security issues
     */
    private function sanitizeFileName($name)
    {
        // Replace spaces with underscores
        $name = str_replace(' ', '_', $name);

        // Remove any unsafe characters
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);

        return $name;
    }
}
