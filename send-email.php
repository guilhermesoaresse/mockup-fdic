<?php
/**
 * Script de processamento seguro de formulário de contato.
 * Recebe os dados do formulário via POST, valida os campos e os envia
 * por e-mail utilizando SMTP autenticado do Microsoft 365.
 */

// Define cabeçalho para retorno sempre em formato JSON
header('Content-Type: application/json; charset=utf-8');

// Configura exibição de erros internos (oculta detalhes sensíveis no cliente, mas registra no log)
ini_set('display_errors', 0);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Verifica se o método de requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método de requisição não permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Recebe e higieniza os inputs
$name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$company = isset($_POST['company']) ? strip_tags(trim($_POST['company'])) : '';
$phone = isset($_POST['phone']) ? strip_tags(trim($_POST['phone'])) : '';
$interest = isset($_POST['interest']) ? strip_tags(trim($_POST['interest'])) : '';
$message = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : '';

// 2. Validações básicas de campos obrigatórios
if (empty($name) || empty($email) || empty($company) || empty($phone) || empty($interest)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Por favor, preencha todos os campos obrigatórios.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Por favor, forneça um endereço de e-mail corporativo válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Carrega o arquivo de configuração de SMTP
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno de configuração. Por favor, crie o arquivo config.php baseando-se no config.php.example.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configFile;

// 4. Inicializa o envio com PHPMailer
require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

$mail = new PHPMailer(true);

try {
    // Configurações do Servidor SMTP
    $mail->isSMTP();
    $mail->Host       = $config['smtp']['host'];
    $mail->SMTPAuth   = $config['smtp']['auth'];
    $mail->Username   = $config['smtp']['username'];
    $mail->Password   = $config['smtp']['password'];
    $mail->Port       = $config['smtp']['port'];
    
    // Define a criptografia apropriada (geralmente TLS)
    if ($config['smtp']['encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($config['smtp']['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    // Configurações Adicionais para evitar falhas de SSL em servidores locais ou mal configurados
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->CharSet = 'UTF-8';

    // Destinatários
    // Microsoft 365 exige que o "From" seja a própria conta autenticada para evitar bloqueios de falsidade (anti-spoofing)
    $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
    $mail->addAddress($config['mail']['to_email'], $config['mail']['to_name']);
    
    // Adiciona o e-mail do cliente como Reply-To para facilitar a resposta direta ao lead
    $mail->addReplyTo($email, $name);

    // Conteúdo do E-mail
    $mail->isHTML(true);
    
    // Traduz o valor do interesse para exibição amigável
    $interestFriendly = '';
    switch ($interest) {
        case 'antecipacao':
            $interestFriendly = 'Antecipação de Recebíveis';
            break;
        case 'fidc':
            $interestFriendly = 'Estruturação de FIDC';
            break;
        case 'investimento':
            $interestFriendly = 'Investimento';
            break;
        default:
            $interestFriendly = $interest;
    }

    $mail->Subject = "Novo Lead: " . $company . " - Contato via Site";
    
    // Corpo HTML formatado de forma elegante
    $mail->Body    = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; color: #333; line-height: 1.6; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
        <h2 style='color: #d32f2f; border-bottom: 2px solid #d32f2f; padding-bottom: 10px; margin-top: 0;'>Novo Lead Recebido - Meta FIDC</h2>
        
        <p style='margin: 15px 0;'><strong>Nome Completo:</strong><br>" . htmlspecialchars($name) . "</p>
        <p style='margin: 15px 0;'><strong>E-mail Corporativo:</strong><br><a href='mailto:" . htmlspecialchars($email) . "' style='color: #d32f2f; text-decoration: none;'>" . htmlspecialchars($email) . "</a></p>
        <p style='margin: 15px 0;'><strong>Empresa / CNPJ:</strong><br>" . htmlspecialchars($company) . "</p>
        <p style='margin: 15px 0;'><strong>WhatsApp / Telefone:</strong><br>" . htmlspecialchars($phone) . "</p>
        <p style='margin: 15px 0;'><strong>Área de Interesse:</strong><br><span style='background: #f5f5f5; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.9em;'>" . htmlspecialchars($interestFriendly) . "</span></p>
        
        <div style='background-color: #fcfcfc; border-left: 4px solid #ccc; padding: 15px; margin-top: 20px;'>
            <strong>Mensagem / Detalhes da Necessidade:</strong><br>
            " . nl2br(htmlspecialchars($message)) . "
        </div>
        
        <footer style='margin-top: 25px; font-size: 0.8em; color: #888; border-top: 1px solid #eee; padding-top: 10px; text-align: center;'>
            Este e-mail foi gerado automaticamente pelo formulário de contato do site Meta FIDC.
        </footer>
    </div>
    ";

    // Versão em texto puro para clientes de e-mail que não renderizam HTML
    $mail->AltBody = "Novo Lead Recebido - Meta FIDC\n\n" .
                     "Nome Completo: " . $name . "\n" .
                     "E-mail Corporativo: " . $email . "\n" .
                     "Empresa / CNPJ: " . $company . "\n" .
                     "WhatsApp: " . $phone . "\n" .
                     "Área de Interesse: " . $interestFriendly . "\n\n" .
                     "Mensagem:\n" . $message;

    // Envia o e-mail
    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Solicitação enviada com sucesso! Em breve um consultor entrará em contato.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Registra o erro no log interno do servidor para auditoria do administrador
    error_log("Erro no PHPMailer: " . $mail->ErrorInfo);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Infelizmente, não foi possível enviar seu e-mail neste momento. Por favor, tente novamente ou entre em contato diretamente por telefone.'
    ], JSON_UNESCAPED_UNICODE);
}
