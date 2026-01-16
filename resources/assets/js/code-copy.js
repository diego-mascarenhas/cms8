// Code block copy functionality
document.addEventListener('DOMContentLoaded', function() {
    // Find all code blocks
    const codeBlocks = document.querySelectorAll('pre code');

    codeBlocks.forEach(function(codeBlock, index) {
        // Create container div
        const container = document.createElement('div');
        container.className = 'code-block-container';

        // Wrap the pre element
        const preElement = codeBlock.parentElement;
        preElement.parentElement.insertBefore(container, preElement);
        container.appendChild(preElement);

        // Create copy button
        const copyButton = document.createElement('button');
        copyButton.className = 'copy-btn';
        copyButton.innerHTML = '<i class="ti ti-copy"></i>';
        copyButton.title = 'Copy to clipboard';

        // Add click event
        copyButton.addEventListener('click', function() {
            const codeText = codeBlock.textContent || codeBlock.innerText;

            // Copy to clipboard
            navigator.clipboard.writeText(codeText).then(function() {
                // Show success state
                copyButton.classList.add('copied');
                copyButton.innerHTML = '<i class="ti ti-check"></i>';

                // Reset after 2 seconds
                setTimeout(function() {
                    copyButton.classList.remove('copied');
                    copyButton.innerHTML = '<i class="ti ti-copy"></i>';
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = codeText;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    copyButton.classList.add('copied');
                    copyButton.innerHTML = '<i class="ti ti-check"></i>';
                    setTimeout(function() {
                        copyButton.classList.remove('copied');
                        copyButton.innerHTML = '<i class="ti ti-copy"></i>';
                    }, 2000);
                } catch (fallbackErr) {
                    console.error('Fallback copy failed: ', fallbackErr);
                }
                document.body.removeChild(textArea);
            });
        });

        // Add button to container
        container.appendChild(copyButton);
    });
});