// # table-comments.js
const OUTLINE_DEFAULT = '1px solid grey';
const OUTLINE_PENDING = '1px solid orange';
const OUTLINE_SUCCESS = '1px solid green';
const OUTLINE_ERROR = '1px solid red';
const FEEDBACK_DURATION = 1000;
const SAVE_DELAY = 500;

// Save comment via AJAX
function saveComment(target) {
    fetch(fpAjax.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'fptc_save_comments',
            plugin: target.dataset.pluginFile,
            comments: target.value,
            nonce: fpAjax.nonce
        })
    })
    .then(response => response.json())
    .then(result => {
        target.style.outline = result.success ? OUTLINE_SUCCESS : OUTLINE_PENDING;
    })
    .catch(() => {
        target.style.outline = OUTLINE_ERROR;
    })
    .finally(() => {
        setTimeout(() => target.style.outline = OUTLINE_DEFAULT, FEEDBACK_DURATION);
    });
}

// Initialize textareas
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.fp-plugin-comments').forEach(function(textarea) {
        let timeout;
        textarea.style.outline = OUTLINE_DEFAULT;
        
        textarea.addEventListener('input', function(event) {
            clearTimeout(timeout);
            event.target.style.outline = OUTLINE_PENDING;
            timeout = setTimeout(function() {
                saveComment(event.target);
            }, SAVE_DELAY);
        });
    });
});
