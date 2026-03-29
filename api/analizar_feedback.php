<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/openai.php';

function analizarFeedbackConIA(int $respuestaId): bool
{
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.calificacion,
            r.respuesta_2,
            r.comentario,
            r.sucursal,
            r.canal,
            c.nombre AS campana
        FROM fb_respuestas r
        JOIN fb_campanas c ON c.id = r.campana_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$respuestaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'sentimiento_global' => [
                'type' => 'string',
                'enum' => ['positivo', 'negativo', 'neutro']
            ],
            'tema_principal' => [
                'type' => 'string',
                'enum' => ['atencion', 'tiempo_de_espera', 'claridad', 'soporte', 'precio', 'seguimiento', 'otro']
            ],
            'tema_secundario' => [
                'type' => 'string',
                'enum' => ['atencion', 'tiempo_de_espera', 'claridad', 'soporte', 'precio', 'seguimiento', 'otro']
            ],
            'urgencia' => [
                'type' => 'string',
                'enum' => ['baja', 'media', 'alta']
            ],
            'resumen' => [
                'type' => 'string'
            ],
            'accion_sugerida' => [
                'type' => 'string'
            ],
            'fortalezas' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'fragmento' => ['type' => 'string'],
                        'tema'      => ['type' => 'string']
                    ],
                    'required' => ['fragmento', 'tema']
                ]
            ],
            'problemas' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'fragmento' => ['type' => 'string'],
                        'tema'      => ['type' => 'string']
                    ],
                    'required' => ['fragmento', 'tema']
                ]
            ]
        ],
        'required' => [
            'sentimiento_global',
            'tema_principal',
            'tema_secundario',
            'urgencia',
            'resumen',
            'accion_sugerida',
            'fortalezas',
            'problemas'
        ]
    ];

    $input = [
        [
            'role' => 'system',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => implode("\n", [
                        'Eres un analista de feedback.',
                        'Debes analizar comentarios de usuarios.',
                        'Responde solo con JSON válido según el esquema.',
                        'Criterios:',
                        '- sentimiento_global: positivo, negativo o neutro',
                        '- tema_principal y tema_secundario: atencion, tiempo_de_espera, claridad, soporte, precio, seguimiento u otro',
                        '- urgencia: baja, media o alta',
                        '- resumen: máximo 20 palabras',
                        '- accion_sugerida: máximo 1 oración',
                        '- fortalezas: lista de hallazgos positivos concretos',
                        '- problemas: lista de hallazgos negativos concretos',
                        '- Si el comentario mezcla cosas buenas y malas, elige el sentimiento predominante; si no predomina, usa "neutro".',
                        '- Si el tema no encaja, usa "otro".',
                        '- No inventes información.',
                        '- Si no hay fortalezas o problemas claros, devuelve arrays vacíos.'
                    ])
                ]
            ]
        ],
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => implode("\n", [
                        'Analiza este feedback:',
                        'Campaña: ' . (string)$row['campana'],
                        'Calificación: ' . (string)$row['calificacion'],
                        'Sucursal: ' . (string)($row['sucursal'] ?? ''),
                        'Canal: ' . (string)($row['canal'] ?? ''),
                        'Lo mejor: ' . (string)($row['respuesta_2'] ?? ''),
                        'Mejorar: ' . (string)($row['comentario'] ?? '')
                    ])
                ]
            ]
        ]
    ];

    $payload = [
        'model' => OPENAI_MODEL,
        'input' => $input,
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'analisis_feedback',
                'schema' => $schema,
                'strict' => true
            ]
        ]
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 90,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log('OpenAI error HTTP ' . $httpCode . ' - ' . $curlError . ' - ' . (string)$response);
        return false;
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        error_log('Respuesta OpenAI no válida');
        return false;
    }

    $textoSalida = extraerTextoDeResponses($json);
    if ($textoSalida === '') {
        error_log('No se pudo extraer texto de Responses API');
        return false;
    }

    $analisis = json_decode($textoSalida, true);
    if (!is_array($analisis)) {
        error_log('El modelo no devolvió JSON válido: ' . $textoSalida);
        return false;
    }

    $sentimientoGlobal = trim((string)($analisis['sentimiento_global'] ?? ''));
    $temaPrincipal     = trim((string)($analisis['tema_principal'] ?? ''));
    $temaSecundario    = trim((string)($analisis['tema_secundario'] ?? ''));
    $urgencia          = trim((string)($analisis['urgencia'] ?? ''));
    $resumen           = trim((string)($analisis['resumen'] ?? ''));
    $accionSugerida    = trim((string)($analisis['accion_sugerida'] ?? ''));

    $fortalezas = is_array($analisis['fortalezas'] ?? null) ? $analisis['fortalezas'] : [];
    $problemas  = is_array($analisis['problemas'] ?? null) ? $analisis['problemas'] : [];

    try {
        $pdo->beginTransaction();

        // Reproceso seguro
        $stmt = $pdo->prepare("DELETE FROM fb_analisis_ia_detalle WHERE respuesta_id = ?");
        $stmt->execute([$respuestaId]);

        $stmt = $pdo->prepare("DELETE FROM fb_analisis_ia WHERE respuesta_id = ?");
        $stmt->execute([$respuestaId]);

        $stmt = $pdo->prepare("
            INSERT INTO fb_analisis_ia
            (
                respuesta_id,
                sentimiento,
                tema_principal,
                tema_secundario,
                urgencia,
                resumen,
                accion_sugerida,
                fecha_analisis
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $respuestaId,
            $sentimientoGlobal !== '' ? $sentimientoGlobal : null,
            $temaPrincipal !== '' ? $temaPrincipal : null,
            $temaSecundario !== '' ? $temaSecundario : null,
            $urgencia !== '' ? $urgencia : null,
            $resumen !== '' ? $resumen : null,
            $accionSugerida !== '' ? $accionSugerida : null,
        ]);

        $stmtDetalle = $pdo->prepare("
            INSERT INTO fb_analisis_ia_detalle
            (respuesta_id, tipo, fragmento, tema, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        foreach ($fortalezas as $item) {
            $fragmento = trim((string)($item['fragmento'] ?? ''));
            $tema      = trim((string)($item['tema'] ?? ''));

            if ($fragmento !== '') {
                $stmtDetalle->execute([
                    $respuestaId,
                    'positivo',
                    $fragmento,
                    $tema !== '' ? $tema : null
                ]);
            }
        }

        foreach ($problemas as $item) {
            $fragmento = trim((string)($item['fragmento'] ?? ''));
            $tema      = trim((string)($item['tema'] ?? ''));

            if ($fragmento !== '') {
                $stmtDetalle->execute([
                    $respuestaId,
                    'negativo',
                    $fragmento,
                    $tema !== '' ? $tema : null
                ]);
            }
        }

        $stmt = $pdo->prepare("UPDATE fb_respuestas SET analizado_ia = 1 WHERE id = ?");
        $stmt->execute([$respuestaId]);

        $pdo->commit();
        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error guardando análisis IA: ' . $e->getMessage());
        return false;
    }
}

function extraerTextoDeResponses(array $json): string
{
    if (!empty($json['output_text']) && is_string($json['output_text'])) {
        return trim($json['output_text']);
    }

    if (!empty($json['output']) && is_array($json['output'])) {
        foreach ($json['output'] as $item) {
            if (!empty($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? '') === 'output_text' && !empty($content['text'])) {
                        return trim((string)$content['text']);
                    }
                }
            }
        }
    }

    return '';
}
