<?php
// app/templates/header.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>Método Grisa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/favicon-32x32.png" sizes="32x32" type="image/png">
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/favicon-16x16.png" sizes="16x16" type="image/png">
  <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/img/favicon.ico">

  <!-- PWA Manifest -->
  <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">

  <!-- Apple Touch Icons - Todos os tamanhos oficiais -->
  <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/img/apple-touch-icon.png">
  <link rel="apple-touch-icon" sizes="120x120" href="<?php echo BASE_URL; ?>assets/img/apple-touch-icon.png">
  <link rel="apple-touch-icon" sizes="152x152" href="<?php echo BASE_URL; ?>assets/img/apple-touch-icon.png">
  <link rel="apple-touch-icon" sizes="167x167" href="<?php echo BASE_URL; ?>assets/img/apple-touch-icon.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>assets/img/apple-touch-icon.png">

  <!-- Android/Chrome -->
  <link rel="icon" type="image/png" sizes="192x192" href="<?php echo BASE_URL; ?>assets/img/android-chrome-192x192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="<?php echo BASE_URL; ?>assets/img/android-chrome-512x512.png">
  <meta name="theme-color" content="#ffffff">
  <meta name="mobile-web-app-capable" content="yes">

  <!-- iOS Otimizado -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Método Grisa">
  <meta name="format-detection" content="telephone=no">

  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

  <!-- Trumbowyg (Lightweight HTML Editor - No API key/Security warnings) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trumbowyg@2.28.0/dist/ui/trumbowyg.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.28.0/dist/trumbowyg.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.28.0/dist/langs/pt_br.min.js"></script>

  <!-- Estilos próprios -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/app.css?v=<?php echo time(); ?>">

  <style>
    /* Estilos Críticos para Sidebar - Prioridade Ultra-Alta */
    #main-wrapper {
      display: flex !important;
      flex-direction: row !important;
      width: 100% !important;
      min-height: 100vh;
      align-items: stretch !important;
      overflow-x: hidden;
    }

    #sidebar-wrapper {
      min-width: 260px !important;
      max-width: 260px !important;
      width: 260px !important;
      background-color: #0d0c31 !important;
      color: #fff !important;
      flex-shrink: 0 !important;
      z-index: 1000;
      transition: all 0.3s;
      position: sticky !important;
      top: 0 !important;
      height: 100vh !important;
    }

    #page-content-wrapper {
      flex-grow: 1 !important;
      width: calc(100% - 260px) !important;
      min-width: 0;
      min-height: 100vh;
      background-color: #f5f6fa;
    }

    @media (max-width: 991.98px) {
      #sidebar-wrapper {
        position: fixed !important;
        margin-left: -260px !important;
        height: 100vh;
      }

      #sidebar-wrapper.show {
        margin-left: 0 !important;
      }

      #page-content-wrapper {
        width: 100% !important;
      }
    }
  </style>

  <!-- Core Libraries (Loaded in header to support inline module scripts) -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Máscara de telefone removida: os campos de telefone aceitam números puros.

    // Formatao monetria em tempo real (ex: 10000 -> 10.000,00)
    function formatMoney(val) {
      const digits = (val || '').replace(/\D/g, '');
      if (!digits) return '';
      const number = (parseInt(digits, 10) / 100).toFixed(2);
      const parts = number.split('.');
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return parts.join(',');
    }
    document.addEventListener('input', function (e) {
      const target = e.target;
      if (!target.matches('input[data-mask="money"], input.money-mask')) return;
      target.value = formatMoney(target.value);
    });
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('input[data-mask="money"], input.money-mask').forEach(function (input) {
        if (input.value) {
          input.value = formatMoney(input.value);
        }
      });
    });

    // Formata CPF/CNPJ dinamicamente
    function formatCpfCnpj(val) {
      const digits = (val || '').replace(/\D/g, '').slice(0, 14);
      if (digits.length <= 11) {
        return digits
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d)/, '$1.$2')
          .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      }
      return digits
        .replace(/(\d{2})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1/$2')
        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }

    function validateCpfCnpj(digits) {
      const n = (digits || '').replace(/\D/g, '');
      if (n.length === 11) {
        if (/^(\d)\1+$/.test(n)) return false;
        let sum = 0;
        for (let i = 0; i < 9; i++) sum += parseInt(n[i]) * (10 - i);
        let d1 = (sum * 10) % 11;
        if (d1 === 10) d1 = 0;
        if (d1 !== parseInt(n[9])) return false;
        sum = 0;
        for (let i = 0; i < 10; i++) sum += parseInt(n[i]) * (11 - i);
        let d2 = (sum * 10) % 11;
        if (d2 === 10) d2 = 0;
        return d2 === parseInt(n[10]);
      }
      if (n.length === 14) {
        if (/^(\d)\1+$/.test(n)) return false;
        let t = 0, p = 5;
        for (let i = 0; i < 12; i++) {
          t += parseInt(n[i]) * p--;
          if (p < 2) p = 9;
        }
        let d1 = t % 11;
        d1 = d1 < 2 ? 0 : 11 - d1;
        if (d1 !== parseInt(n[12])) return false;
        t = 0; p = 6;
        for (let i = 0; i < 13; i++) {
          t += parseInt(n[i]) * p--;
          if (p < 2) p = 9;
        }
        let d2 = t % 11;
        d2 = d2 < 2 ? 0 : 11 - d2;
        return d2 === parseInt(n[13]);
      }
      return false;
    }

    document.addEventListener('input', function (e) {
      const target = e.target;
      if (!target.matches('input[data-mask="cpf_cnpj"], input.cpf-cnpj-mask')) return;
      target.value = formatCpfCnpj(target.value);
    });

    // Copiar texto a partir de elementos com data-copy
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-copy]');
      if (!btn) return;
      e.preventDefault();
      const text = btn.getAttribute('data-copy') || '';
      if (!text) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).catch(() => { });
      } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (err) { }
        document.body.removeChild(ta);
      }
    });

    // Validação de CPF/CNPJ em formulários
    document.addEventListener('submit', function (e) {
      const inputs = e.target.querySelectorAll('input[data-validate="cpf_cnpj"], input.cpf-cnpj-validate');
      for (const input of inputs) {
        if (!validateCpfCnpj(input.value)) {
          e.preventDefault();
          alert('CPF/CNPJ inválido.');
          input.focus();
          break;
        }
      }
    });
  </script>
</head>

<body class="<?php echo ($layout ?? '') === 'clean' ? 'layout-clean' : ''; ?>">
  <?php if (($layout ?? '') !== 'clean'): ?>
    <!-- Mobile Header -->
    <div class="mobile-header d-lg-none">
      <div class="d-flex align-items-center">
        <img src="<?php echo BASE_URL; ?>assets/img/logo.png" width="30" height="30" class="me-2">
        <span class="fw-bold">Método Grisa</span>
      </div>
      <button class="btn btn-link text-white p-0" id="sidebarToggle">
        <i class="bi bi-list fs-2"></i>
      </button>
    </div>

    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div id="main-wrapper"
      style="display: flex !important; flex-direction: row !important; width: 100% !important; min-height: 100vh !important; align-items: stretch !important; background: linear-gradient(to right, #0d0c31 260px, #f5f6fa 260px) !important;">
      <?php include __DIR__ . '/topmenu.php'; ?>

      <div id="page-content-wrapper"
        style="flex-grow: 1 !important; min-width: 0 !important; background-color: #f5f6fa !important;">
      <?php endif; ?>

      <script>
        document.addEventListener('DOMContentLoaded', function () {
          const sidebarToggle = document.getElementById('sidebarToggle');
          const sidebarWrapper = document.getElementById('sidebar-wrapper');
          const sidebarOverlay = document.getElementById('sidebarOverlay');

          if (sidebarToggle && sidebarWrapper) {
            sidebarToggle.addEventListener('click', function () {
              sidebarWrapper.classList.toggle('show');
              sidebarOverlay.classList.toggle('show');
            });
          }

          if (sidebarOverlay && sidebarWrapper) {
            sidebarOverlay.addEventListener('click', function () {
              sidebarWrapper.classList.remove('show');
              sidebarOverlay.classList.remove('show');
            });
          }

          // Active link highlighting
          const currentUrl = window.location.href;
          const navLinks = document.querySelectorAll('.sidebar-nav .nav-link, .sidebar-nav .submenu-link');

          navLinks.forEach(link => {
            if (currentUrl.includes(link.getAttribute('href'))) {
              link.classList.add('active');
              // If it's a submenu link, expand the parent accordion
              const accordionCollapse = link.closest('.accordion-collapse');
              if (accordionCollapse) {
                const bsCollapse = new bootstrap.Collapse(accordionCollapse, { toggle: false });
                bsCollapse.show();
                const accordionButton = document.querySelector(`[data-bs-target="#${accordionCollapse.id}"]`);
                if (accordionButton) accordionButton.classList.remove('collapsed');
              }
            }
          });
        });
      </script>