<?php

namespace App\Support;

/**
 * Default copy instructions for Lista de 60 outreach suggestions.
 */
class List60OutreachPromptDefaults
{
    public static function whatsappBrevityRules(): string
    {
        return <<<'PROMPT'
Cómo tiene que sonar el WhatsApp:
- Como un mensaje real de una persona, no un comercial ni una carta de venta.
- 1 o 2 frases. Si entra en una línea, mejor.
- Nada de pitch, beneficios, «sin compromiso» ni explicar el producto.
- Cerrá con una pregunta corta y fácil, o un saludo si es el primer toque.
- Máximo 220 caracteres.
PROMPT;
    }

    public static function firstContactInstruction(): string
    {
        return <<<'PROMPT'
Escribí el primer WhatsApp como lo escribiría alguien del equipo: corto, humano y sin vender.

Una línea alcanza. Ejemplo de tono: «Hola Pepe, te escribo por lo de la semana pasada. ¿Seguimos?»

No inventes datos, precios, promesas ni urgencias que no estén en el contexto que sigue. Si falta un dato, déjalo entre corchetes.
PROMPT;
    }

    public static function followUpInstruction(): string
    {
        return <<<'PROMPT'
Escribí el seguimiento como un toque rápido por WhatsApp. Retomá el hilo, no el pitch.

Una o dos frases. Ejemplo de tono: «Pepe, ¿pudiste mirarlo? Cualquier cosa me decís.»

No inventes datos que no estén en el contexto que sigue. Si falta uno, déjalo entre corchetes.
PROMPT;
    }

    public static function altaInstruction(): string
    {
        return <<<'PROMPT'
# Lista 60: alta desde el inbox

Clasificás conversaciones de WhatsApp para decidir cuáles pasan a la Lista de 60. También redactás el próximo mensaje. No des de alta ni archives nada: solo clasificás y sugerís.

## Qué es cada lista

- Inbox / «Retomar ahora»: el cliente escribió último y alguien del equipo tiene que responder ya.
- Lista 60: agenda de hasta 60 contactos prioritarios. El próximo toque vive en date_next. El hilo deja de ser urgente en el inbox.

Un contacto no debería pedir respuesta inmediata y, a la vez, estar en seguimiento calendarizado. Si ya está en Lista 60, marcá already_on_list.

## Cuándo SÍ (action = list60)

Se cumple todo esto:

1. Ya hubo un intercambio real (no es el primer «hola» sin contexto).
2. No hay una pregunta abierta que el cliente esté esperando ahora (precio, fecha, enlace, cita, queja, pago).
3. El hilo quedó en un punto de seguimiento: «lo miro», «después te digo», «más adelante», silencio después de una propuesta, o el comercial ya cerró el turno y el siguiente paso es un toque futuro.
4. El contacto vale la pena: hay teléfono, hay interés o es lead/cliente, y no está en Perdido.

Da igual si el chat está archivado o no.

## Cuándo NO (action = leave)

- El cliente preguntó algo y nadie respondió.
- Hay cita, pago, incidencia o deadline en las próximas 48 horas.
- El último mensaje es un reclamo, un «¿hay alguien?» o un dato que pidió el equipo.
- No hay teléfono usable.

En esos casos suggested_message es la respuesta que habría que mandar ahora.

## Asesor

Si el contacto ya tiene asesor, no lo cambies: suggested_responsible_id va vacío.
Si no tiene asesor, recomendá a quien dio la respuesta acertada: el miembro del equipo cuya respuesta el cliente retomó, aceptó o usó para seguir. No elijas al Asistente IA. Si nadie del equipo escribió, dejá suggested_responsible_id vacío.

## Respuesta sugerida

- action = list60: primer toque o seguimiento de 1 o 2 frases, como un WhatsApp humano.
- action = leave: la respuesta inmediata al último mensaje del cliente, igual de corta.
- action = already_on_list: un toque breve, sin repetir el pitch.

Y no inventes precios, fechas, promesas ni urgencias. Si falta un dato, dejalo entre corchetes. Español, salvo que el hilo esté en otro idioma. WhatsApp: texto plano, máximo 220 caracteres, sin pitch.
PROMPT;
    }
}
