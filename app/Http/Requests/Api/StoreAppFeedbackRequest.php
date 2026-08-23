<?php

namespace App\Http\Requests\Api;

use App\Support\AppFeedbackQuestions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $keys = AppFeedbackQuestions::keys();

        return [
            'product' => ['required', 'string', Rule::in([
                'ads',
                'mailer',
                'shop',
                'assistant',
                'projects',
                'affiliates',
            ])],
            'answers' => ['required', 'array', 'size:'.count($keys)],
            'answers.*.key' => ['required', 'string', Rule::in($keys)],
            'answers.*.choice' => ['required', 'string'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product.required' => __('Indicá el producto.'),
            'product.in' => __('El producto no es válido.'),
            'answers.required' => __('Respondé las preguntas.'),
            'answers.size' => __('Respondé todas las preguntas.'),
            'answers.*.key.in' => __('La pregunta no es válida.'),
            'answers.*.choice.required' => __('Elegí una opción.'),
            'comment.max' => __('El comentario no puede superar los 4000 caracteres.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $answers = $this->input('answers', []);
            if (! is_array($answers))
            {
                return;
            }

            $seen = [];
            $choices = AppFeedbackQuestions::choices();

            foreach ($answers as $index => $answer)
            {
                if (! is_array($answer))
                {
                    continue;
                }

                $key = (string) ($answer['key'] ?? '');
                $choice = (string) ($answer['choice'] ?? '');

                if ($key !== '' && isset($seen[$key]))
                {
                    $validator->errors()->add("answers.{$index}.key", __('Cada pregunta se responde una vez.'));
                }

                $seen[$key] = true;

                if ($key !== '' && $choice !== '' && ! in_array($choice, $choices[$key] ?? [], true))
                {
                    $validator->errors()->add("answers.{$index}.choice", __('La opción no es válida.'));
                }
            }
        });
    }
}
