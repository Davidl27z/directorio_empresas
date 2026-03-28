<?php

require_once __DIR__ . '/empresa_contacto.php';

function pedido_contact_method_label(string $method): string
{
    $labels = empresa_contact_method_labels();

    return $labels[$method] ?? $method;
}

function pedido_build_contact_details(array $usuario, string $metodo, string $celular, string $ubicacion, array $input): string
{
    $payload = [
        'version' => 2,
        'cliente' => [
            'nombre' => trim((string) ($usuario['nombre'] ?? '')),
            'email' => trim((string) ($usuario['email'] ?? '')),
            'celular' => trim($celular),
            'ubicacion' => trim($ubicacion),
        ],
        'metodo' => $metodo,
        'detalle' => [],
    ];

    switch ($metodo) {
        case 'whatsapp':
            $payload['detalle'] = [
                'whatsapp' => trim((string) ($input['telefono_whatsapp'] ?? '')),
            ];
            break;

        case 'formulario':
            $payload['detalle'] = [
                'telefono' => trim((string) ($input['telefono_formulario'] ?? '')),
                'email' => trim((string) ($input['email_formulario'] ?? '')),
            ];
            break;

        case 'correo':
            $payload['detalle'] = [
                'email' => trim((string) ($input['email_correo'] ?? '')),
            ];
            break;
    }

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function pedido_decode_contact_details(?string $rawDetails): array
{
    $rawDetails = (string) $rawDetails;
    if ($rawDetails === '') {
        return [];
    }

    $decoded = json_decode($rawDetails, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    $segments = [];
    foreach (explode(';', $rawDetails) as $segment) {
        $parts = explode(':', $segment, 2);
        if (count($parts) === 2) {
            $segments[trim($parts[0])] = trim($parts[1]);
        }
    }

    $clienteNombre = '';
    $clienteEmail = '';
    if (!empty($segments['Cliente']) && preg_match('/^(.*)\(([^)]+)\)$/', $segments['Cliente'], $matches)) {
        $clienteNombre = trim($matches[1]);
        $clienteEmail = trim($matches[2]);
    }

    $metodo = $segments['Metodo'] ?? ($segments['Método'] ?? '');
    $detalleRaw = $segments['Detalles'] ?? '';
    $detalle = [];

    if ($metodo === 'whatsapp') {
        $detalle['whatsapp'] = $detalleRaw;
    } elseif ($metodo === 'correo') {
        $detalle['email'] = $detalleRaw;
    } elseif ($metodo === 'formulario') {
        $parts = array_map('trim', explode('|', $detalleRaw));
        $detalle['telefono'] = $parts[0] ?? '';
        $detalle['email'] = $parts[1] ?? '';
    }

    return [
        'version' => 1,
        'cliente' => [
            'nombre' => $clienteNombre,
            'email' => $clienteEmail,
            'celular' => $segments['Celular'] ?? '',
            'ubicacion' => $segments['Ubicacion'] ?? ($segments['Ubicación'] ?? ''),
        ],
        'metodo' => $metodo,
        'detalle' => $detalle,
        'legacy_raw' => $rawDetails,
    ];
}

function pedido_contact_detail_text(array $details): string
{
    $metodo = (string) ($details['metodo'] ?? '');
    $detalle = $details['detalle'] ?? [];

    if ($metodo === 'whatsapp') {
        return (string) ($detalle['whatsapp'] ?? '');
    }

    if ($metodo === 'correo') {
        return (string) ($detalle['email'] ?? '');
    }

    if ($metodo === 'formulario') {
        $parts = [];
        if (!empty($detalle['telefono'])) {
            $parts[] = 'Telefono: ' . $detalle['telefono'];
        }
        if (!empty($detalle['email'])) {
            $parts[] = 'Email: ' . $detalle['email'];
        }

        return implode(' | ', $parts);
    }

    return (string) ($details['legacy_raw'] ?? '');
}
