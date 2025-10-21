<style>
    #wpsdtrk-notice-area {
        position: fixed;
        top: 0px;
        left: 0;
        width: 100%;
        z-index: 9999;
        display: flex;
        justify-content: center;
        pointer-events: none;
    }

    #wpsdtrk-notice-area .wpsdtrk-notice {
        width: 100%;
        max-width: 100%;
        margin: 0;
        border-radius: 0;
        pointer-events: auto;
        padding: 5px 10px 5px 10px;
        text-align: center;

        background-image: linear-gradient(90deg, rgba(255, 255, 255, 0.09) 0%, rgba(255, 255, 255, 0.27) 1%, rgba(255, 255, 255, 0.15) 10%, rgba(255, 255, 255, 0.05) 19%, rgba(255, 255, 255, 0) 32%, rgba(255, 255, 255, 0.09) 100%);
        border-width: 1px 1px 3px 2px;
        border-color: rgba(255, 255, 255, 0.15) rgba(255, 255, 255, 0.15) rgba(255, 255, 255, 0.15) rgba(255, 255, 255, 0.27);
        backdrop-filter: blur(8.5px);
        box-shadow: 6px 6px 18px 0px rgba(0, 0, 0, 0.3);
    }

    #wpsdtrk-notice-area .wpsdtrk-notice p {
        color: #fff;
        font-weight: 600;
        font-size: 18px;
    }

    #wpsdtrk-notice-area .wpsdtrk-notice-success {
        background-color: #20ff2080;
    }

    #wpsdtrk-notice-area .wpsdtrk-notice-error {
        background-color: #ff202080;
    }
</style>
<div id="wpsdtrk-notice-area"></div>
<script>
    function wpsdtrk_show_notice(message, type = 'success') {
        const area = document.getElementById('wpsdtrk-notice-area');
        const className = type === 'success' ? 'wpsdtrk-notice wpsdtrk-notice-success' : 'wpsdtrk-notice wpsdtrk-notice-error';

        const notice = document.createElement('div');
        notice.className = className;
        notice.innerHTML = `<p>${message}</p>`;

        area.innerHTML = '';
        area.appendChild(notice);

        setTimeout(() => {
            notice.style.opacity = '0';
            notice.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                if (notice.parentNode) {
                    notice.parentNode.removeChild(notice);
                }
            }, 500);
        }, 1500);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const successMessage = urlParams.get('sdtrk_success');
        const errorMessage = urlParams.get('sdtrk_error');

        if (successMessage) {
            wpsdtrk_show_notice(decodeURIComponent(successMessage), 'success');

            // Parameter aus URL entfernen
            urlParams.delete('sdtrk_success');
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.replaceState({}, document.title, newUrl);
        }

        if (errorMessage) {
            wpsdtrk_show_notice(decodeURIComponent(errorMessage), 'error');

            // Parameter aus URL entfernen  
            urlParams.delete('sdtrk_error');
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.replaceState({}, document.title, newUrl);
        }
    });
</script>