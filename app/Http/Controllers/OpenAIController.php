<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class OpenAIController extends Controller
{
    private $apiKey;
    private $elevenApiKey;

    private $chatGptModel = "gpt-3.5-turbo-0301";
    private $chatGptName = "YOUR NAME IS Simplicity. Remember that you are a VERY kind, respectful, understanding and ALWAYS positive technical support employee. Don't be repetitive.";
    private $chatGptSystem = "Use VERY SHORT answers. Don't be ironic, acidic, unbearable, rude. HABLA EN ESPAÑOL.";
    private $chatGptVoice = "ErXwobaYiN019PkySvjV";

    public function __construct(Request $request)
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->elevenApiKey = env('ELEVENLABS_API_KEY');
    }

    public function index(Request $request): JsonResponse
    {
        $chatHistory = $request->input('chatHistory', []);

        $chatHistory[] = ['role' => 'system', 'content' => $this->chatGptName];
        $chatHistory[] = ['role' => 'system', 'content' => $this->chatGptSystem];

        $userMessage = $request->post('msg');
        $chatHistory[] = ['role' => 'user', 'content' => $userMessage];

        $data = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey
        ])
            ->post("https://api.openai.com/v1/chat/completions", [
                "model" => $this->chatGptModel,
                'messages' => $chatHistory,
                'temperature' => 1,
                "top_p" => 1,
                "frequency_penalty" => 2,
                "presence_penalty" => 2,
                "stop" => ["\n"],
            ])
            ->json();

        $response = $data['choices'][0]['message']['content'];

        $chatHistory[] = ['role' => 'assistant', 'content' => $response];

        $chatHistory[] = ['role' => 'system', 'content' => $this->chatGptName];
        $chatHistory[] = ['role' => 'system', 'content' => $this->chatGptSystem];

        $voiceFilePath = $this->crea_el_archivo_de_voz($response, $this->chatGptVoice, $this->elevenApiKey, $request);

        return response()->json([
            'response' => $response,
            'audioFile' => $voiceFilePath,
            'chatHistory' => $chatHistory,
        ], 200, [], JSON_PRETTY_PRINT);
    }

    private function crea_el_archivo_de_voz($prompt, $selected_voice_id, $eleven_api_key, Request $request)
    {
        $response_text = $prompt;

        $url = "https://api.elevenlabs.io/v1/text-to-speech/" . $selected_voice_id;
        $headers = [
            "Accept" => "audio/mpeg",
            "Content-Type" => "application/json",
            "xi-api-key" => $eleven_api_key
        ];

        $data = [
            "text" => $response_text,
            "model_id" => "eleven_multilingual_v1",
            "voice_settings" => [
                "stability" => 1,
                "similarity_boost" => 1,
            ]
        ];

        $response = Http::withHeaders($headers)->post($url, $data);

        if ($response->successful())
        {
            $filename = 'audio/' . uniqid() . '.mp3';
            Storage::disk('public')->put($filename, $response->body());

            return asset('storage/' . $filename);
        }
        else
        {
            return false;
        }
    }
}