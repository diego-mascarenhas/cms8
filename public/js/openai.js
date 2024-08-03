$(document).ready(function() {
    // Borrar los datos del almacenamiento local al recargar la página
    localStorage.clear();

    // No pedir claves, asumir que están configuradas en el backend y no se necesitan en el frontend
    // var apiKey = prompt('Ingresa tu clave de API Open AI.');
    // var elevenApiKey = prompt('Ingresa tu clave de Eleven API. (Si quieres que el bot hable. Si no déjala en blanco.)');

    // Almacenar las claves en el almacenamiento local
    // Esto asume que las claves se configurarán correctamente en el backend y no se necesitarán aquí
    // localStorage.setItem('apiKey', apiKey);
    // localStorage.setItem('elevenApiKey', elevenApiKey);

    var chatMessages = $('#chat-messages'); // Elemento HTML para mostrar los mensajes del chat
    var chatInput = $('#chat-input'); // Elemento HTML para el campo de entrada del chat
    var chatForm = $('#chat-form'); // Elemento HTML para el formulario del chat
    var chatHistory = []; // Variable para almacenar el historial de mensajes

    chatForm.submit(function(event) {
        event.preventDefault();
        var message = chatInput.val().trim();
    
        if (message !== '') {
            sendMessage(message);
            chatInput.val('');
        }
    });
    
    function sendMessage(message) {
        var userMessageElement = createMessageElement('Usuario', message);
        chatMessages.append(userMessageElement);

        // Actualizar el historial de mensajes con el mensaje del usuario
        chatHistory.push({ role: 'user', content: message });
    
        // Mostrar el preloader en el globo del bot
        var botMessageElement = createBotMessageElement('');
        chatMessages.append(botMessageElement);
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    
        $.ajax({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            url: '/open-ai',
            method: 'POST',
            data: {
                msg: message,
                chatHistory: chatHistory, // Pasar el historial de mensajes al servidor
                // Asumimos que las claves están configuradas en el servidor y no las pasamos aquí
                // apiKey: apiKey,
                // elevenApiKey: elevenApiKey
            },
            success: function(response) {
                // Actualizar el historial de mensajes con el mensaje del asistente
                chatHistory.push({ role: 'assistant', content: response.response });

                // Actualizar el mensaje en el mismo globo del bot
                botMessageElement.text(response.response);
                if (response.audioFile) {
                    fetch(response.audioFile) // Realiza una solicitud para obtener el archivo de audio
                        .then(response => response.blob()) // Convierte la respuesta en un objeto Blob
                        .then(blob => {
                            var audioUrl = URL.createObjectURL(blob); // Crea una URL para el objeto Blob obtenido
                                
                            var audioElement = document.createElement("audio"); // Crea un elemento de audio
                            audioElement.src = audioUrl; // Establece la fuente del audio como la URL creada
                            audioElement.autoplay = true; // Habilita la reproducción automática del audio
                                
                            botMessageElement.append(audioElement); // Agrega el elemento de audio al elemento de mensaje de bot
                        })
                        .catch(error => {
                            console.error("Error al obtener el archivo de audio:", error); // Muestra un mensaje de error en caso de fallo
                        });
                }
                chatMessages.scrollTop(chatMessages[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                // Actualizar el mensaje de error en el mismo globo del bot
                botMessageElement.text("ERROR: O no has puesto tu clave de API o GPT no funciona en este momento.");
                chatMessages.scrollTop(chatMessages[0].scrollHeight);
            }
        });
    }
    
    // Crea un elemento de mensaje en función del remitente y el mensaje proporcionados.
    function createMessageElement(sender, message) {
        var senderClass = sender.toLowerCase() === 'usuario' ? 'user-message' : 'bot-message';
        var messageElement = $('<div class="' + senderClass + '">'); // Crea un elemento div con la clase del remitente
        messageElement.text(message); // Establece el texto del mensaje en el elemento
        return messageElement; // Retorna el elemento de mensaje creado
    }
    
    //crea un elemento de mensaje de bot. 
    function createBotMessageElement(message) {
        var botMessageElement = $('<div class="bot-message">'); // Crea un elemento div con la clase "bot-message"
        var preloaderImage = $('<img src="assets/img/preloaders/preloader.gif" width="32" alt="Preloader" />');
        botMessageElement.append(preloaderImage); // Agrega la imagen de carga al elemento de mensaje de bot
        botMessageElement.append('<span>' + message + '</span>'); // Agrega el mensaje al elemento de mensaje de bot
        return botMessageElement; // Retorna el elemento de mensaje de bot creado
    }
});