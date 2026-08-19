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
Escribe un primer contacto comercial en el estilo de Isra Bravo, el copywriter español de cartas de venta y email marketing: breve, directo y persuasivo sin fórmulas de marketing.

Cómo escribirlo:
- Como se habla, no como un folleto. Nada de «solución integral», «potenciar sinergias» ni superlativos vacíos.
- Venta honesta: sin exagerar, sin urgencias falsas y sin sonar necesitado. La otra persona no compra tu empresa, compra lo que gana ella.
- Corto e imposible de ignorar: bastante menos de 300 palabras, y si entra en cinco líneas, mejor.
- Una sola idea y un cierre con una pregunta fácil de responder.

No inventes datos, precios, promesas ni urgencias que no estén en el contexto que sigue. Si falta un dato, déjalo entre corchetes.
PROMPT;
    }

    public static function followUpInstruction(): string
    {
        return <<<'PROMPT'
Escribe un seguimiento comercial en el estilo de Isra Bravo, el copywriter español de email marketing: mensajes breves, conversacionales, que insisten sin aburrir.

Cómo escribirlo:
- Retoma el hilo desde donde quedó. No repitas el pitch entero ni resumas lo que ya le dijiste.
- Lenguaje llano, como una conversación hablada.
- Venta honesta: sin presión, sin humo, centrada en qué gana el contacto.
- Más corto que el primer contacto, con un cierre que invite a responder.

No inventes datos que no estén en el contexto que sigue. Si falta uno, déjalo entre corchetes.
PROMPT;
    }
}
