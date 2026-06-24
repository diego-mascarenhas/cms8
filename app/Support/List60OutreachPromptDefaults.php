<?php

namespace App\Support;

/**
 * Default copy instructions for Lista de 60 outreach suggestions.
 * Inspired by the public writing style of Isra Bravo (Spanish copywriter, sales letters and email marketing).
 *
 * @see https://www.rtve.es/noticias/20220523/emprende-isra-bravo-copywriter-ayuda-empresas-vender/2350815.shtml
 * @see https://www.elindependiente.com/tendencias/2023/08/29/isra-bravo-mago-del-copywriting-cuando-asumes-que-la-vida-es-vender-te-va-mucho-mejor/
 */
class List60OutreachPromptDefaults
{
    public static function firstContactInstruction(): string
    {
        return <<<'PROMPT'
Escribe un primer contacto comercial inspirándote en el estilo de Isra Bravo, copywriter español referente en cartas de venta y email marketing (conocido por mensajes breves, directos y persuasivos sin depender de fórmulas de marketing tradicional).

Principios de su enfoque (aplicados al mensaje):
- Humaniza y aterriza el lenguaje: escribe como se habla, no como un folleto corporativo.
- Venta honesta: sin engaño, sin desesperación y sin sonar necesitado; la otra persona no compra tu empresa, compra el beneficio para ella.
- Corto y imposible de ignorar: idealmente menos de 300 palabras; frases claras, empatía y credibilidad.
- Cierra con una pregunta o siguiente paso fácil de responder.

No inventes datos, precios, promesas ni urgencias que no estén en el contexto que sigue.
PROMPT;
    }

    public static function followUpInstruction(): string
    {
        return <<<'PROMPT'
Escribe un seguimiento comercial inspirándote en el estilo de Isra Bravo, copywriter español especialista en email marketing (mensajes breves, conversacionales y repetición natural sin aburrir).

Principios de su enfoque (aplicados al mensaje):
- Retoma el hilo sin repetir el pitch entero; lenguaje llano y humano, como una conversación hablada.
- Venta honesta: sin presión, sin humo y centrada en qué gana el contacto.
- Corto y directo; empatía, credibilidad y un cierre que invite a responder.

No inventes datos que no estén en el contexto que sigue.
PROMPT;
    }
}
