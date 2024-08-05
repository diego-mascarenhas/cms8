$(document).ready(function() {
    localStorage.clear();

    var chatMessages = $('#chat-messages');
    var chatInput = $('#chat-input');
    var chatForm = $('#chat-form');
    var chatHistory = [];

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

        chatHistory.push({ role: 'user', content: message });

        var botMessageElement = createBotMessageElement('Writing...');
        chatMessages.append(botMessageElement);
        chatMessages.scrollTop(chatMessages[0].scrollHeight);

        $.ajax({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            url: '/open-ai',
            method: 'POST',
            data: {
                msg: message,
                chatHistory: chatHistory,
            },
            success: function(response) {
                chatHistory.push({ role: 'assistant', content: response.response });

                botMessageElement.find('.chat-message-text img').remove();
                botMessageElement.find('.chat-message-text p').html(response.response);

                if (response.audioFile) {
                    fetch(response.audioFile)
                        .then(response => response.blob())
                        .then(blob => {
                            var audioUrl = URL.createObjectURL(blob);
                            var audioElement = document.createElement("audio");
                            audioElement.src = audioUrl;
                            audioElement.autoplay = true;
                            botMessageElement.find('.chat-message-text').append(audioElement);
                        })
                        .catch(error => {
                            console.error("Error getting audio file: ", error);
                        });
                }
                chatMessages.scrollTop(chatMessages[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                botMessageElement.find('.chat-message-text img').remove();
                botMessageElement.find('.chat-message-text p').html("Error: Either you have not entered your API key or GPT is not working at this time.");
                chatMessages.scrollTop(chatMessages[0].scrollHeight);
            }
        });
    }

    function createMessageElement(sender, message) {
        var isUser = sender.toLowerCase() === 'usuario';
        var senderClass = isUser ? 'chat-message chat-message-right' : 'chat-message';
        var avatarUrl = isUser ? null : '/assets/img/avatars/guru-meditating.jpg';
        var messageElement = $('<li class="' + senderClass + '">');

        var userAvatar = avatarUrl ? `
            <div class="user-avatar flex-shrink-0 me-3">
                <div class="avatar avatar-sm">
                    <img src="${avatarUrl}" alt="Avatar" class="rounded-circle">
                </div>
            </div>` : '';

        var userAvatarRight = avatarUrl ? `
            <div class="user-avatar flex-shrink-0 ms-3">
                <div class="avatar avatar-sm">
                    <img src="${avatarUrl}" alt="Avatar" class="rounded-circle">
                </div>
            </div>` : '';

        var messageContent = `
            <div class="d-flex overflow-hidden">
                ${isUser ? '' : userAvatar}
                <div class="chat-message-wrapper flex-grow-1">
                    <div class="chat-message-text">
                        <p class="mb-0">${message}</p>
                    </div>
                    <div class="${isUser ? 'text-end ' : ''}text-muted mt-1">
                        ${isUser ? "<i class='ti ti-checks ti-xs me-1 text-success'></i>" : ''}
                        <small>${new Date().toLocaleTimeString()}</small>
                    </div>
                </div>
                ${isUser ? userAvatarRight : ''}
            </div>
        `;

        messageElement.html(messageContent);
        return messageElement;
    }

    function createBotMessageElement(message) {
        var botMessageElement = $('<li class="chat-message">');
        var avatarUrl = '/assets/img/avatars/guru-meditating.jpg';
        var preloaderImage = '<img src="/assets/img/preloaders/preloader.gif" width="32" alt="Preloader" />';

        var messageContent = `
            <div class="d-flex overflow-hidden">
                <div class="user-avatar flex-shrink-0 me-3">
                    <div class="avatar avatar-sm">
                        <img src="${avatarUrl}" alt="Avatar" class="rounded-circle">
                    </div>
                </div>
                <div class="chat-message-wrapper flex-grow-1">
                    <div class="chat-message-text">
                        ${preloaderImage}
                        <p class="mb-0">${message}</p>
                    </div>
                    <div class="text-muted mt-1">
                        <small>${new Date().toLocaleTimeString()}</small>
                    </div>
                </div>
            </div>
        `;

        botMessageElement.html(messageContent);
        return botMessageElement;
    }
});