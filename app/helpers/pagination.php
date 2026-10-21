<?php
// app/helpers/pagination.php

/**
 * Standardized Pagination Helper for administrative lists.
 */
class Pagination
{
    public static function render(int $currentPage, int $totalPages, string $baseUrl, array $params = []): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav aria-label="Navegação de página"><ul class="pagination pagination-sm justify-content-center">';

        // Links build helper
        $buildUrl = function ($page) use ($baseUrl, $params) {
            $params['page'] = $page;
            return $baseUrl . '?' . http_build_query($params);
        };

        // First and Previous
        $html .= '<li class="page-item ' . ($currentPage <= 1 ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . ($currentPage > 1 ? $buildUrl(1) : '#') . '">«</a></li>';
        $html .= '<li class="page-item ' . ($currentPage <= 1 ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . ($currentPage > 1 ? $buildUrl($currentPage - 1) : '#') . '"><</a></li>';

        // Pages
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        if ($start > 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item ' . ($i === $currentPage ? 'active' : '') . '">';
            $html .= '<a class="page-link" href="' . $buildUrl($i) . '">' . $i . '</a></li>';
        }

        if ($end < $totalPages) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        // Next and Last
        $html .= '<li class="page-item ' . ($currentPage >= $totalPages ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . ($currentPage < $totalPages ? $buildUrl($currentPage + 1) : '#') . '">></a></li>';
        $html .= '<li class="page-item ' . ($currentPage >= $totalPages ? 'disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . ($currentPage < $totalPages ? $buildUrl($totalPages) : '#') . '">»</a></li>';

        $html .= '</ul></nav>';
        return $html;
    }
}
