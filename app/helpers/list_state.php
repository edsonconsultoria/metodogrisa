<?php
// app/helpers/list_state.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Chaves que nunca devem ser propagadas automaticamente.
 */
function list_state_default_exclude(): array
{
    return ['m', 'a', 'return', 'id', 'confirm', 'csrf', '_csrf', 'token', 'submit', 'action'];
}

/**
 * Limpa $_GET e remove chaves indevidas, preservando apenas filtros relevantes.
 */
function list_state_clean_params(array $excludeKeys = []): array
{
    $excludeKeys = array_merge(list_state_default_exclude(), $excludeKeys);
    $exclude = array_flip($excludeKeys);
    $params = [];
    foreach ($_GET as $key => $value) {
        if ($key === '' || isset($exclude[$key])) {
            continue;
        }
        $params[$key] = $value;
    }
    return $params;
}

/**
 * Monta uma query string preservando os filtros atuais.
 */
function list_state_build_query(array $overrides = [], array $excludeKeys = []): string
{
    $params = array_merge(list_state_clean_params($excludeKeys), $overrides);
    return http_build_query($params);
}

/**
 * Gera o parâmetro "&return=" com os filtros preservados.
 */
function list_state_build_return_param(array $overrides = [], array $excludeKeys = []): string
{
    $query = list_state_build_query($overrides, array_merge($excludeKeys, ['return']));
    if ($query === '') {
        return '';
    }
    return '&return=' . urlencode($query);
}

/**
 * Decodifica o parâmetro "return" enviado pelas ações.
 */
function list_state_decode_return_params(): array
{
    $encoded = $_REQUEST['return'] ?? '';
    $encoded = trim((string) $encoded);
    if ($encoded === '') {
        return [];
    }
    $decoded = [];
    parse_str(trim(urldecode($encoded)), $decoded);
    return $decoded;
}

/**
 * Monta a URL de retorno para uma listagem específica.
 */
function list_state_return_destination(string $module, string $action, array $fallback = []): string
{
    $route = array_merge($fallback, ['m' => $module, 'a' => $action]);
    $decoded = list_state_decode_return_params();
    $returnRoute = $route;
    if (!empty($decoded['m']) && !empty($decoded['a'])) {
        $returnRoute = array_merge($fallback, ['m' => $decoded['m'], 'a' => $decoded['a']]);
    }
    unset($decoded['m'], $decoded['a'], $decoded['return']);
    $params = array_merge($returnRoute, $decoded);
    return BASE_URL . 'index.php?' . http_build_query($params);
}

/**
 * Monta um link para ações da listagem com os parâmetros atuais preservados.
 */
function list_state_action_url(
    string $module,
    string $action,
    array $params = [],
    ?string $listModule = null,
    string $listAction = 'list',
    array $excludeKeys = []
): string {
    $baseParams = array_merge(['m' => $module, 'a' => $action], $params);
    $query = http_build_query($baseParams);
    $returnModule = $listModule ?? $module;
    $returnToken = list_state_build_return_param(['m' => $returnModule, 'a' => $listAction], $excludeKeys);
    return BASE_URL . 'index.php?' . $query . $returnToken;
}

if (!function_exists('financeiro_build_return_param')) {
    function financeiro_build_return_param(array $overrides = [], array $excludeKeys = []): string
    {
        return list_state_build_return_param($overrides, $excludeKeys);
    }
}

if (!function_exists('financeiro_return_destination')) {
    function financeiro_return_destination(string $module, string $action, array $fallback = []): string
    {
        return list_state_return_destination($module, $action, $fallback);
    }
}
