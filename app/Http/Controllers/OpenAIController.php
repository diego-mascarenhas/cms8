<?php
namespace App\Http\Controllers;

//Importamos las clases que necesitamos de Laravel
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class OpenAIController extends Controller
{
    private $apiKey;
    private $elevenApiKey;

    private $vicGptModel = "gpt-3.5-turbo-0301"; // Modelo GPT para chat
    private $vicGptName = "YOUR NAME IS Simplicity. Remember that you are a VERY kind, respectful, understanding and ALWAYS positive technical support employee. Don't be repetitive."; //Primera linea de la personalidad de vicgpt. También le hacemos saber cómo se llama
    private $vicGptSystem = "Use VERY SHORT answers. Don't be ironic, acidic, unbearable, rude. HABLA EN ESPAÑOL."; // Segunda linea de la personalidad de vicgpt
    private $vicGptVoice = "ErXwobaYiN019PkySvjV"; //ID de la voz de elevenlabs
    
    public function __construct(Request $request)
    {
         // Verificar si las claves de API están definidas en el archivo .env
        $this->apiKey = env('OPENAI_API_KEY');
        $this->elevenApiKey = env('ELEVENS_API_KEY');
        
        // Si alguna no está definida usamos las del dialogbox (request)
        if(!$this->apiKey)
            $this->apiKey = $request->apiKey;
        if(!$this->elevenApiKey)
            $this->elevenApiKey = $request->elevenApiKey;
    }

    public function index(Request $request): JsonResponse
    {
        // Obtener el historial de mensajes enviado desde el cliente
        $chatHistory = $request->input('chatHistory', []);

        // Agregar el mensaje del sistema al inicio del historial de mensajes
        $chatHistory[] = ['role' => 'system', 'content' => $this->vicGptName];
        $chatHistory[] = ['role' => 'system', 'content' => $this->vicGptSystem];

        // Agregar el mensaje del usuario actual al historial de mensajes
        $userMessage = $request->post('msg');
        $chatHistory[] = ['role' => 'user', 'content' => $userMessage];

        // Hacer la solicitud al API de OpenAI
        $data = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey
        ])
        ->post("https://api.openai.com/v1/chat/completions", [
            "model" => $this->vicGptModel,  // Modelo de GPT utilizado para generar respuestas
            'messages' => $chatHistory, // Pasar el historial de mensajes completo
            'temperature' => 1, // Aleatoriedad de las respuestas generadas
            "top_p" => 1, // Parámetro de probabilidad superior para filtrar las opciones de siguiente palabra
            "frequency_penalty" => 2, // Penalización de frecuencia para reducir la repetición de palabras
            "presence_penalty" => 2, // Penalización de presencia para equilibrar la generación entre palabras comunes y raras
            "stop" => ["\n"],
        ])
        ->json();

        // Obtener la respuesta generada del API
        $response = $data['choices'][0]['message']['content'];

        // Agregar la respuesta generada al historial de mensajes
        $chatHistory[] = ['role' => 'assistant', 'content' => $response];

        // Recordar a vicgpt que siga siendo insoportable
        $chatHistory[] = ['role' => 'system', 'content' => $this->vicGptName];
        $chatHistory[] = ['role' => 'system', 'content' => $this->vicGptSystem];
        
        // Crear el archivo de voz y obtener la ruta temporal
        $voiceFilePath = $this->crea_el_archivo_de_voz($response, $this->vicGptVoice, $this->elevenApiKey, $request);
        
        // Devolver el JSON
        return response()->json([
            'response' => $response, // Devolver el mensaje
            'audioFile' => $voiceFilePath, // Devolver el archivo de audio (puede ser false)
            'chatHistory' => $chatHistory, // Devolver el historial de mensajes actualizado
        ], 200, [], JSON_PRETTY_PRINT);
    }
    private function crea_el_archivo_de_voz($prompt, $selected_voice_id, $eleven_api_key, Request $request)
    {
        $response_text = $prompt; // Texto que se va a convertir a voz

        $url = "https://api.elevenlabs.io/v1/text-to-speech/" . $selected_voice_id; // URL de la API de Eleven Labs para convertir texto a voz
        $headers = [
            "Accept" => "audio/mpeg", // Especifica que se espera recibir un archivo de audio en formato MPEG
            "Content-Type" => "application/json", // Tipo de contenido de la solicitud, en este caso, JSON
            "xi-api-key" => $eleven_api_key // Clave de API de Eleven Labs
        ];
        
        $data = [
            "text" => $response_text, // El texto que se va a convertir a voz
            "model_id" => "eleven_multilingual_v1", // ID del modelo de voz a utilizar
            "voice_settings" => [
                "stability" => 1, // Estabilidad del tono de voz (1 indica estabilidad completa)
                "similarity_boost" => 1, // Impulso de similitud del tono de voz (1 indica similitud completa)
            ]
        ];
        
        $response = Http::withHeaders($headers)->post($url, $data); // Realizar la solicitud HTTP a la API de Eleven Labs

        // Verificar si la solicitud fue exitosa
        if ($response->successful()) {
            // Guardar el archivo de audio en la ubicación deseada
            $filename = 'audio/' . uniqid() . '.mp3'; // Generar un nombre de archivo único
            Storage::disk('public')->put($filename, $response->body());

            // Devolver la URL absoluta del archivo guardado
            return asset('storage/' . $filename);
        } else {
            // Se produjo un error en la generación del archivo de voz
            return false;
        }
    }
}