<?php
/**
 * Script de integração com o feed RSS de Economia do InfoMoney.
 * Obtém o XML de forma segura via cURL, realiza o parse no backend
 * e expõe uma API JSON idêntica ao rss2json para o frontend.
 * Possui mecanismo de cache em arquivo de 10 minutos para velocidade e estabilidade.
 */

header('Content-Type: application/json; charset=utf-8');

// Oculta exibição direta de erros PHP
ini_set('display_errors', 0);
error_reporting(E_ALL);

$cacheFile = __DIR__ . '/news_cache.json';
$cacheDuration = 600; // Tempo de cache: 10 minutos (600 segundos)

// 1. Verifica se existe cache válido e o serve imediatamente para performance máxima
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
    $cachedData = file_get_contents($cacheFile);
    if (!empty($cachedData)) {
        // Valida se o cache é um JSON íntegro e possui itens antes de servir
        $decoded = json_decode($cachedData, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['status']) && $decoded['status'] === 'ok' && !empty($decoded['items'])) {
            echo $cachedData;
            exit;
        }
    }
}

// 2. Caso contrário, busca o feed RSS atualizado
$rssUrl = 'https://g1.globo.com/rss/g1/economia/';
$xmlContent = '';
$httpCode = 0;

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rssUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); // Timeout de 8 segundos
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignora erros de SSL do servidor
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // Permite descompressão automática de Gzip/Deflate pelo curl
    // Configura um User-Agent real de navegador para contornar bloqueios
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    $xmlContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
}

// Fallback robusto usando fopen caso cURL falhe ou não esteja instalado
if ((empty($xmlContent) || $httpCode !== 200) && ini_get('allow_url_fopen')) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                        "Accept-Encoding: gzip, deflate\r\n",
            'timeout' => 8,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $context = stream_context_create($opts);
    $handle = @fopen($rssUrl, 'rb', false, $context);
    
    if ($handle) {
        $meta = stream_get_meta_data($handle);
        $rawContent = @stream_get_contents($handle);
        @fclose($handle);
        
        if ($rawContent !== false && !empty($rawContent)) {
            // Se a resposta vier em gzip (iniciando com os bytes mágicos 1f 8b), faz descompressão manual
            if (strpos($rawContent, "\x1f\x8b") === 0) {
                if (function_exists('gzdecode')) {
                    $rawContent = @gzdecode($rawContent);
                }
            }
            $xmlContent = $rawContent;
            
            $headers = isset($meta['wrapper_data']) ? $meta['wrapper_data'] : [];
            if (isset($headers[0])) {
                preg_match('{HTTP\/\S*\s(\d{3})}', $headers[0], $match);
                $httpCode = isset($match[1]) ? (int)$match[1] : 200;
            } else {
                $httpCode = 200;
            }
        }
    }
}

// 3. Fallback de segurança se a requisição falhar
if ($httpCode !== 200 || empty($xmlContent)) {
    serveFallbackData($cacheFile);
    exit;
}

// 4. Efetua o parse do XML
// Libera o tratamento de erros do XML para capturar falhas silenciosamente
libxml_use_internal_errors(true);
$rss = simplexml_load_string($xmlContent);

if ($rss === false) {
    libxml_clear_errors();
    serveFallbackData($cacheFile);
    exit;
}

// 5. Estrutura os dados no formato idêntico ao rss2json esperado pelo frontend
$response = [
    'status' => 'ok',
    'feed' => [
        'url' => $rssUrl,
        'title' => 'g1 > Economia'
    ],
    'items' => []
];

// Limita em 5 itens (o frontend precisa apenas de 3, filtramos com folga)
$count = 0;
foreach ($rss->channel->item as $item) {
    if ($count >= 5) break;

    $title = (string)$item->title;
    $link = (string)$item->link;
    $pubDate = (string)$item->pubDate;
    $descriptionRaw = (string)$item->description;

    // Tenta extrair a imagem do thumbnail se houver enclosure ou tags de mídia
    $thumbnail = '';
    
    // Tentativa A: Enclosure
    if (isset($item->enclosure) && isset($item->enclosure['url'])) {
        $thumbnail = (string)$item->enclosure['url'];
    }
    
    // Tentativa B: Tag media:content (namespace de mídia)
    if (empty($thumbnail)) {
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->content) && isset($media->content->attributes()->url)) {
            $thumbnail = (string)$media->content->attributes()->url;
        }
    }

    // Tentativa C: Extrair tag <img> de dentro da descrição/conteúdo
    if (empty($thumbnail)) {
        preg_match('/<img[^>]+src="([^">]+)"/i', $descriptionRaw, $imgMatch);
        if (isset($imgMatch[1])) {
            $thumbnail = $imgMatch[1];
        }
    }

    // Limpa tags HTML e higieniza a descrição para retornar texto puro
    $cleanDesc = html_entity_decode(strip_tags($descriptionRaw));
    // Remove quebras de linha extras e espaços em branco duplicados
    $cleanDesc = trim(preg_replace('/\s+/', ' ', $cleanDesc));

    // Trunca o texto em no máximo 150 caracteres sem cortar palavras no meio
    $maxLength = 150;
    if (function_exists('mb_strlen')) {
        if (mb_strlen($cleanDesc, 'UTF-8') > $maxLength) {
            $cleanDesc = mb_substr($cleanDesc, 0, $maxLength - 3, 'UTF-8');
            $lastSpace = mb_strrpos($cleanDesc, ' ', 0, 'UTF-8');
            if ($lastSpace !== false) {
                $cleanDesc = mb_substr($cleanDesc, 0, $lastSpace, 'UTF-8');
            }
            $cleanDesc .= '...';
        }
    } else {
        if (strlen($cleanDesc) > $maxLength) {
            $cleanDesc = substr($cleanDesc, 0, $maxLength - 3);
            $lastSpace = strrpos($cleanDesc, ' ');
            if ($lastSpace !== false) {
                $cleanDesc = substr($cleanDesc, 0, $lastSpace);
            }
            $cleanDesc .= '...';
        }
    }

    $response['items'][] = [
        'title' => $title,
        'link' => $link,
        'pubDate' => $pubDate,
        'description' => $cleanDesc,
        'thumbnail' => $thumbnail
    ];
    $count++;
}

$jsonResponse = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// 6. Grava a resposta no arquivo de cache para as próximas chamadas
// Silencia avisos caso o diretório não tenha permissão de escrita
@file_put_contents($cacheFile, $jsonResponse);

echo $jsonResponse;

/**
 * Função auxiliar que tenta servir os dados em cache mesmo que expirados
 * caso ocorra uma queda de conexão com o InfoMoney.
 */
function serveFallbackData($cacheFile) {
    if (file_exists($cacheFile)) {
        $cachedData = file_get_contents($cacheFile);
        if (!empty($cachedData)) {
            $decoded = json_decode($cachedData, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['status']) && $decoded['status'] === 'ok' && !empty($decoded['items'])) {
                echo $cachedData;
                return;
            }
        }
    }
    
    // Se não houver absolutamente nenhum cache anterior válido, retorna erro HTTP e mensagem JSON vazia
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'Não foi possível buscar as notícias no G1 Economia e nenhum cache válido foi localizado.',
        'items' => []
    ], JSON_UNESCAPED_UNICODE);
}
