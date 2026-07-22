<?php
// app/templates/footer.php
?>
<?php if (($layout ?? '') !== 'clean'): ?>
  </div> <!-- /#page-content-wrapper -->
  </div> <!-- /#main-wrapper -->
<?php endif; ?>
<script>
(function() {
    const csrfToken = '<?php echo function_exists("csrf_token") ? csrf_token() : ""; ?>';
    if (!csrfToken) return;

    // 1. Injeta em todos os formulários POST existentes e futuros
    const injectForms = () => {
        document.querySelectorAll('form[method="post"]').forEach(form => {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = csrfToken;
                form.appendChild(input);
            }
        });
    };

    // 2. Injeta em todos os links GET críticos de exclusão, cópia, etc.
    const injectLinks = () => {
        document.querySelectorAll('a[href]').forEach(link => {
            const href = link.getAttribute('href');
            if (href && (
                href.includes('a=delete') || 
                href.includes('a=copy') || 
                href.includes('a=form_copy') || 
                href.includes('a=complete') || 
                href.includes('a=reschedule') || 
                href.includes('a=complete_action') || 
                href.includes('a=batch_action') || 
                href.includes('a=send_password') ||
                href.includes('a=ajax_delete') ||
                href.includes('a=ajax_send_docs')
            )) {
                if (!href.includes('csrf_token=')) {
                    const separator = href.includes('?') ? '&' : '?';
                    link.setAttribute('href', href + separator + 'csrf_token=' + encodeURIComponent(csrfToken));
                }
            }
        });
    };

    // 3. Configura o jQuery para enviar o header em todas as requisições AJAX
    if (window.jQuery) {
        window.jQuery.ajaxSetup({
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);
            }
        });
    }

    // 4. Intercepta a Fetch API nativa para adicionar o cabeçalho CSRF
    if (window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function(input, init) {
            init = init || {};
            init.headers = init.headers || {};
            if (init.headers instanceof Headers) {
                init.headers.set('X-CSRF-Token', csrfToken);
            } else if (typeof init.headers === 'object') {
                init.headers['X-CSRF-Token'] = csrfToken;
            }
            return originalFetch(input, init);
        };
    }

    // Executa no carregamento da página
    injectForms();
    injectLinks();

    // Observa mudanças estruturais do DOM (AJAX, paginações, modais abertos dinamicamente)
    const observer = new MutationObserver(() => {
        injectForms();
        injectLinks();
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
</script>
</body>

</html>