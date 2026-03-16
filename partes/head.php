<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>Sistema de Gerenciamento da Cia E Eqp Mnt</title>

  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta charset="UTF-8">
  <link rel="icon" href="assets/img/kaiadmin/favicon.png" type="image/x-icon" />

  <!-- WebFonts -->
  <script src="assets/js/plugin/webfont/webfont.min.js"></script>
  <script>
    WebFont.load({
      google: { families: ["Inter:300,400,500,600,700", "Public Sans:300,400,500,600,700"] },
      custom: {
        families: [
          "Font Awesome 5 Solid",
          "Font Awesome 5 Regular",
          "Font Awesome 5 Brands",
          "simple-line-icons"
        ],
        urls: ["assets/css/fonts.min.css"],
      },
      active: () => sessionStorage.fonts = true
    });
  </script>

  <!-- CSS Base -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/plugins.min.css" />
  <link rel="stylesheet" href="assets/css/kaiadmin.min.css" />
  <link rel="stylesheet" href="assets/js/plugin/datatables/datatables.min.css" />

  <!-- Melhorias no layout claro -->
  <style>
      .pagination-wrapper .pagination {
  margin: 0;
}

/* Força paginação SEMPRE na horizontal */
.pagination-wrapper .pagination {
  display: flex !important;
  flex-direction: row !important;
  flex-wrap: wrap !important;      /* quebra linha se faltar espaço */
  align-items: center !important;
  justify-content: center !important;
  gap: 6px;                        /* espaço entre botões */
  padding-left: 0 !important;
  margin: 0 !important;
}

.pagination-wrapper .pagination .page-item {
  display: inline-flex !important; /* evita virar bloco */
  margin: 0 !important;
}

.pagination-wrapper .pagination .page-link {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  min-width: 36px;
  padding: 6px 10px;
  line-height: 1.1;
}

@media (max-width: 576px) {
  .pagination-wrapper .page-link {
    min-width: 34px;
    padding: 6px 8px;
    font-size: 13px;
  }
}
    /* ============================================
       Refinamento Visual (Tema Claro)
       ============================================ */

    body {
      font-family: "Inter", "Public Sans", sans-serif;
      background: #f5f7fa;
      color: #333;
    }

    /* Títulos mais limpos */
    h1, h2, h3, h4, h5, h6 {
      font-weight: 600;
      color: #2d2f33;
    }

    /* Links discretos */
    a {
      color: #0d6efd;
      text-decoration: none !important;
    }
    a:hover {
      color: #0b5ed7;
    }

    /* Cards mais elegantes */
    .card {
      border-radius: 10px;
      border: 1px solid #e5e7eb;
    }

    .card-header {
      background: #fff;
      border-bottom: 1px solid #e5e7eb;
    }

    /* Botões modernos */
    .btn-primary {
      border-radius: 6px;
      font-weight: 500;
      padding: 0.45rem 1.1rem;
    }

    /* DataTables integrado ao tema claro */
    table.dataTable thead th {
      font-weight: 600;
      color: #2d2f33 !important;
      border-bottom: 2px solid #dee2e6 !important;
    }

    table.dataTable tbody tr:hover {
      background: rgba(13, 110, 253, 0.05) !important;
    }

    /* Paginação suave */
    .pagination-wrapper {
      display: flex;
      justify-content: center;
      margin: 1rem 0;
    }

    .pagination {
      display: flex;
      list-style: none;
      gap: 0.5rem;
      padding-left: 0;
    }

    .pagination .page-item .page-link {
      min-width: 38px;
      height: 38px;
      display: flex;
      justify-content: center;
      align-items: center;
      background: #fff;
      border: 1px solid #dee2e6;
      color: #555;
      border-radius: 6px;
      transition: 0.25s;
    }

    .pagination .page-item .page-link:hover {
      background: #f0f2f5;
      transform: translateY(-2px);
    }

    .pagination .page-item.active .page-link {
      background: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
      font-weight: 600;
      box-shadow: 0 2px 6px rgba(13,110,253,0.3);
    }
  </style>
</head>
