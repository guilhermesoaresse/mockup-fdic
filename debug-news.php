<?php
/**
 * Script de diagnóstico de ambiente para carregamento de notícias (G1 RSS).
 * Executa testes de PHP, extensões, permissão de gravação de cache e conectividade HTTP/HTTPS.
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meta FIDC - Diagnóstico de Notícias (RSS)</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #0d1117;
            color: #c9d1d9;
            margin: 0;
            padding: 20px;
            line-height: 1.5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        h1 {
            color: #58a6ff;
            border-bottom: 1px solid #30363d;
            padding-bottom: 10px;
            margin-top: 0;
        }
        h2 {
            color: #e6edf3;
            margin-top: 25px;
            font-size: 1.25rem;
        }
        .status-box {
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
            margin-bottom: 20px;
            display: inline-block;
        }
        .status-ok {
            background-color: rgba(46, 160, 67, 0.15);
            color: #3fb950;
            border: 1px solid rgba(46, 160, 67, 0.4);
        }
        .status-warn {
            background-color: rgba(210, 153, 34, 0.15);
            color: #d29922;
            border: 1px solid rgba(210, 153, 34, 0.4);
        }
        .status-error {
            background-color: rgba(248, 81, 73, 0.15);
            color: #f85149;
            border: 1px solid rgba(248, 81, 73, 0.4);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #21262d;
        }
        th {
            background-color: #21262d;
            color: #f0f6fc;
        }
        .code-block {
            background-color: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 15px;
            font-family: ui-monospace, SFMono-Regular, SF Pro Text, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            color: #8b949e;
        }
        .highlight-title {
            color: #f0f6fc;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Diagnóstico de Ambiente: Feed de Notícias</h1>
    <p>Este utilitário valida as configurações de PHP e conectividade de rede do seu servidor para carregar notícias econômicas do feed RSS da Globo G1.</p>

    <h2>1. Informações Básicas do Sistema</h2>
    <table>
        <tr>
            <th>Parâmetro</th>
            <th>Valor</th>
            <th>Status</th>
        </tr>
        <tr>
            <td class="highlight-title">Versão do PHP</td>
            <td><?php echo PHP_VERSION; ?></td>
            <td>
                <?php if (version_compare(PHP_VERSION, '7.4.0', '>=')): ?>
                    <span class="status-box status-ok" style="padding:2px 8px; margin:0;">OK</span>
                <?php else: ?>
                    <span class="status-box status-warn" style="padding:2px 8px; margin:0;">Recomenda-se PHP >= 7.4</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="highlight-title">Diretório Atual</td>
            <td><?php echo __DIR__; ?></td>
            <td>-</td>
        </tr>
        <tr>
            <td class="highlight-title">Permissão de Escrita (Cache)</td>
            <td>
                <?php
                $cacheDir = __DIR__;
                $isWritable = is_writable($cacheDir);
                echo $isWritable ? 'Permitido (Escrita OK)' : 'Bloqueado (Somente Leitura)';
                ?>
            </td>
            <td>
                <?php if ($isWritable): ?>
                    <span class="status-box status-ok" style="padding:2px 8px; margin:0;">OK</span>
                <?php else: ?>
                    <span class="status-box status-warn" style="padding:2px 8px; margin:0;">Aviso: Sem permissão de escrita</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <h2>2. Extensões PHP Necessárias</h2>
    <table>
        <tr>
            <th>Extensão</th>
            <th>Função Principal</th>
            <th>Status</th>
        </tr>
        <tr>
            <td class="highlight-title">cURL</td>
            <td>Requisição de dados externos (G1 RSS)</td>
            <td>
                <?php if (function_exists('curl_init')): ?>
                    <span class="status-box status-ok" style="padding:2px 8px; margin:0;">Instalado</span>
                <?php else: ?>
                    <span class="status-box status-error" style="padding:2px 8px; margin:0;">Ausente (Necessário fallback)</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="highlight-title">SimpleXML</td>
            <td>Processamento e parsing do XML do RSS</td>
            <td>
                <?php if (function_exists('simplexml_load_string')): ?>
                    <span class="status-box status-ok" style="padding:2px 8px; margin:0;">Instalado</span>
                <?php else: ?>
                    <span class="status-box status-error" style="padding:2px 8px; margin:0;">Ausente (Crítico)</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="highlight-title">Zlib (gzdecode)</td>
            <td>Descompressão do feed (GZIP)</td>
            <td>
                <?php if (function_exists('gzdecode')): ?>
                    <span class="status-box status-ok" style="padding:2px 8px; margin:0;">Instalado</span>
                <?php else: ?>
                    <span class="status-box status-warn" style="padding:2px 8px; margin:0;">Ausente (Pode falhar sem cURL)</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="highlight-title">Allow URL Fopen</td>
            <td>Fallback de leitura caso cURL falhe</td>
            <td>
                <?php if (ini_get('allow_url_fopen')): ?>
                    <span class="status-box status-ok" style="padding:2px 8px; margin:0;">Habilitado (ON)</span>
                <?php else: ?>
                    <span class="status-box status-warn" style="padding:2px 8px; margin:0;">Desabilitado (OFF)</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <h2>3. Teste de Conectividade via cURL</h2>
    <?php
    if (function_exists('curl_init')) {
        $rssUrl = 'https://g1.globo.com/rss/g1/economia/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $rssUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Auto-decode GZIP
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        $xmlContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        // curl_close não é mais necessário no PHP 8.0+ e é obsoleto no PHP 8.5+
        if (PHP_VERSION_ID < 80000 && is_resource($ch)) {
            @curl_close($ch);
        }

        echo "<table>";
        echo "<tr><td>HTTP Status Code</td><td><strong>$httpCode</strong></td></tr>";
        echo "<tr><td>Erro do cURL</td><td>" . ($curlError ? $curlError : 'Nenhum') . "</td></tr>";
        echo "<tr><td>Tamanho da Resposta</td><td>" . strlen($xmlContent) . " bytes</td></tr>";
        echo "</table>";

        if ($httpCode == 200 && !empty($xmlContent)) {
            libxml_use_internal_errors(true);
            $rss = @simplexml_load_string($xmlContent);
            if ($rss !== false) {
                $count = count($rss->channel->item);
                echo '<div class="status-box status-ok">cURL OK! XML parseado com sucesso. Encontrados ' . $count . ' itens no feed.</div>';
            } else {
                echo '<div class="status-box status-error">Erro de parser do XML. Veja as primeiras linhas da resposta abaixo.</div>';
                echo '<div class="code-block">' . htmlspecialchars(substr($xmlContent, 0, 500)) . '</div>';
            }
        } else {
            echo '<div class="status-box status-error">O cURL não conseguiu carregar o feed. Verifique se o servidor Locaweb bloqueia conexões externas de saída.</div>';
        }
    } else {
        echo '<div class="status-box status-warn">cURL desabilitado neste servidor. Ignorando teste de cURL.</div>';
    }
    ?>

    <h2>4. Teste de Conectividade via fopen/stream (Fallback)</h2>
    <?php
    if (ini_get('allow_url_fopen')) {
        $rssUrl = 'https://g1.globo.com/rss/g1/economia/';
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                            "Accept-Encoding: gzip, deflate\r\n",
                'timeout' => 6,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context = stream_context_create($opts);
        $handle = @fopen($rssUrl, 'rb', false, $context);
        $rawContent = false;
        $http_code_fallback = 'Desconhecido';
        
        if ($handle) {
            $meta = stream_get_meta_data($handle);
            $rawContent = @stream_get_contents($handle);
            @fclose($handle);
            
            $headers = isset($meta['wrapper_data']) ? $meta['wrapper_data'] : [];
            if (isset($headers[0])) {
                preg_match('{HTTP\/\S*\s(\d{3})}', $headers[0], $match);
                $http_code_fallback = isset($match[1]) ? (int)$match[1] : 200;
            } else {
                $http_code_fallback = 200;
            }
        }

        echo "<table>";
        echo "<tr><td>HTTP Status Code</td><td><strong>$http_code_fallback</strong></td></tr>";
        echo "<tr><td>Tamanho Bruto</td><td>" . ($rawContent ? strlen($rawContent) : 0) . " bytes</td></tr>";
        echo "</table>";

        if ($rawContent !== false && !empty($rawContent)) {
            $isGzipped = (strpos($rawContent, "\x1f\x8b") === 0);
            echo "<table>";
            echo "<tr><td>Resposta Compactada (GZIP)</td><td>" . ($isGzipped ? 'Sim (Detectado byte mágico)' : 'Não') . "</td></tr>";
            
            if ($isGzipped) {
                if (function_exists('gzdecode')) {
                    $rawContent = @gzdecode($rawContent);
                    echo "<tr><td>Tamanho Descompactado</td><td>" . strlen($rawContent) . " bytes</td></tr>";
                } else {
                    echo "<tr><td>Descompactação</td><td><span class='status-box status-error' style='padding:2px 8px; margin:0;'>Falhou (gzdecode indisponível)</span></td></tr>";
                }
            }
            echo "</table>";

            libxml_use_internal_errors(true);
            $rss = @simplexml_load_string($rawContent);
            if ($rss !== false) {
                $count = count($rss->channel->item);
                echo '<div class="status-box status-ok">fopen/stream OK! XML parseado com sucesso. Encontrados ' . $count . ' itens no feed.</div>';
            } else {
                echo '<div class="status-box status-error">Erro de parser do XML obtido via fopen/stream. Primeiras linhas:</div>';
                echo '<div class="code-block">' . htmlspecialchars(substr($rawContent, 0, 500)) . '</div>';
            }
        } else {
            echo '<div class="status-box status-error">O fopen/stream falhou ao abrir a URL. Verifique se o seu servidor bloqueia conexões externas.</div>';
        }
    } else {
        echo '<div class="status-box status-warn">allow_url_fopen está OFF. Ignorando teste de fallback.</div>';
    }
    ?>
</div>
</body>
</html>
