# Plan: Chat asistente y contexto

## Fase 1

### Contexto en `agent_conversations`

- Usar solo la tabla actual: `agent_conversations` con `user_id`. Sin migraciones ni columnas nuevas.
- Siempre se trabaja con un `user_id` como dueño de la conversación con el bot.

### Resolver phone/contacto → user_id

- Resolución (reutilizar lógica tipo `ChatController::getUserByPhone`):
  1. Buscar **User** por `phone` o por `email` (users).
  2. Si no hay User por teléfono, buscar **Contact** por teléfono y usar `Contact.user_id` si existe.
  3. Si **no existe User** para ese contacto/teléfono: **crear el User** (datos mínimos) para poder asociar las conversaciones.
- Con ese `user_id` se obtiene o crea la `AgentConversation` y se cargan/guardan mensajes en `agent_conversation_messages`.

### Toggle en el chat

- **Toggle ON**: Interactuar con el bot (contexto de la conversación de ese `user_id`).
- **Toggle OFF**: Hablamos nosotros; mensaje directo al cliente sin pasar por el bot.

### Acciones del bot (admins)

- Admins pueden chatear con el bot para solicitar tareas: crear tarea, contacto, listar por categoría, modificar proyecto, etc. Contexto persistido en `agent_conversations`.

---

## Fase 2: Simular conversación con el cliente

- **Objetivo**: Probar el flujo del bot sin enviar emails ni usar la app de WhatsApp.
- **Implementación**: Área de texto secundaria en la vista de chat para “Simular cliente”.
  - El usuario escribe en esa área como si fuera el cliente.
  - Ese mensaje no se envía por WhatsApp ni email; se trata como mensaje entrante simulado.
  - El bot genera la respuesta y se muestra en la misma vista, permitiendo seguir el hilo de la conversación.
- Permite probar frases, intenciones y respuestas del bot sin tocar canales reales.
