<?php
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
// Habilita a exibição de erros para debug (REMOVER EM PRODUÇÃO)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Novo Abastecimento"; 
$page_icon = "fas fa-gas-pump"; 

$mensagem_sucesso = "";
$mensagem_erro = "";

// Variáveis de coleta de dados (strings originais)
$valor_str = $litros_str = $hodometro_str = $media_str = $vinculado_viagem_id_str = null; 
$posto_nome = $cidade = null; 

// Variáveis para caminhos das fotos (serão usadas no DB e na Confirmação)
$foto1_path = null;
$foto2_path = null;

// Variável de controle da tela
$is_confirmation_step = false;

// Diretório onde as imagens serão salvas
$target_dir = "../uploads/abastecimentos/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Função utilitária para formatar valores opcionais
function format_optional_value($value, $prefix = '', $suffix = '', $empty_text = 'Não informado') {
    if (empty($value) && $value !== '0' && $value !== '0,00' && $value !== 0) {
        return $empty_text;
    }
    return $prefix . htmlspecialchars($value) . $suffix;
}

// Funções de Upload
function handle_upload($file_key, $target_dir, &$upload_erros, $is_confirmation_step) {
    // Se estiver na tela de confirmação, ignora o upload (os caminhos virão do hidden)
    if ($is_confirmation_step) return null;

    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
        $file = $_FILES[$file_key];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $upload_erros[] = "Arquivo '{$file_key}' é inválido. Apenas JPG, PNG e GIF são permitidos.";
            return null;
        }
        
        if ($file['size'] > 5000000) { // Limite de 5MB
            $upload_erros[] = "Arquivo '{$file_key}' é muito grande. Máximo de 5MB.";
            return null;
        }

        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('abast_') . '_' . $file_key . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return $target_file; // Retorna o caminho do arquivo salvo
        } else {
            $upload_erros[] = "Erro ao mover o arquivo '{$file_key}'.";
            return null;
        }
    }
    return null;
}

// Funções de limpeza
function cleanup_files($foto1_path, $foto2_path) {
    if ($foto1_path && file_exists($foto1_path)) unlink($foto1_path);
    if ($foto2_path && file_exists($foto2_path)) unlink($foto2_path);
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Coleta Ação do Formulário
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    // Coleta e sanitização (strings)
    $valor_str = filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_STRING);
    $litros_str = filter_input(INPUT_POST, 'litros', FILTER_SANITIZE_STRING);
    $hodometro_str = filter_input(INPUT_POST, 'hodometro', FILTER_SANITIZE_STRING); 
    $media_str = filter_input(INPUT_POST, 'media', FILTER_SANITIZE_STRING);
    $posto_nome = filter_input(INPUT_POST, 'posto_nome', FILTER_SANITIZE_STRING);
    $cidade = filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_STRING);
    $vinculado_viagem_id_str = filter_input(INPUT_POST, 'vinculado_viagem_id', FILTER_SANITIZE_STRING); 
    
    // Converte para validação
    $valor_para_validacao = empty($valor_str) ? 0.00 : (float)str_replace(',', '.', $valor_str);
    $litros_para_validacao = empty($litros_str) ? 0.00 : (float)str_replace(',', '.', $litros_str);

    // Validação Mínima
    if ($valor_para_validacao <= 0 || $litros_para_validacao <= 0) {
        $mensagem_erro = "Os campos **Valor** e **Quantidade de Litros** são obrigatórios e devem ser maiores que zero.";
    } 
    
    // Se não há erros de validação
    if (!$mensagem_erro) {

        // --- LÓGICA DE CONFIRMAÇÃO ---
        if ($action == 'confirmar') {

            $upload_erros = [];
            
            // 1. Tenta fazer o upload das fotos (apenas nesta primeira etapa)
            $foto1_path = handle_upload('foto1', $target_dir, $upload_erros, false);
            $foto2_path = handle_upload('foto2', $target_dir, $upload_erros, false);
            
            if (!empty($upload_erros)) {
                $mensagem_erro = "Ocorreram erros no upload: <br>" . implode("<br>", $upload_erros);
                // Limpa os arquivos que porventura tenham sido salvos antes do erro
                cleanup_files($foto1_path, $foto2_path);
            } else {
                // Se o upload foi bem-sucedido (ou não havia arquivos para upload), vai para a confirmação
                $is_confirmation_step = true;
            }

        } 
        
        // --- LÓGICA DE SALVAMENTO FINAL (Corrigida) ---
        elseif ($action == 'salvar_final') {

            // 1. Recebe os caminhos das fotos de campos HIDDEN
            $foto1_path = filter_input(INPUT_POST, 'foto1_path', FILTER_SANITIZE_STRING);
            $foto2_path = filter_input(INPUT_POST, 'foto2_path', FILTER_SANITIZE_STRING);

            // 2. Converte para o banco de dados
            $id_motorista = $_SESSION['usuario_id']; 
            $data_abastecimento = date('Y-m-d'); 
            $valor = $valor_para_validacao;
            $litros = $litros_para_validacao;
            $hodometro = empty($hodometro_str) ? 0 : (int)str_replace(',', '.', $hodometro_str);
            
            // CRÍTICO: Conversão para valor que o MySQLi entenda como NULL. Usaremos string vazia "" ou NULL
            // O MySQLi trata NULL de forma mais fácil se for string vazia ("") E se o tipo for 's' ou se for passado como NULL.
            $media_db = empty($media_str) ? null : (float)str_replace(',', '.', $media_str);
            $vinculado_viagem_id_db = empty($vinculado_viagem_id_str) ? null : (int)$vinculado_viagem_id_str; 

            // 3. QUERY com 11 parâmetros
            $sql = "INSERT INTO abastecimentos (id_motorista, data_abastecimento, valor, litros, hodometro, media, posto_nome, cidade, vinculado_viagem_id, foto1_path, foto2_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            try {
                $stmt = $conn->prepare($sql);
                
                if ($stmt === FALSE) {
                    $mensagem_erro = "Erro ao preparar a query: " . $conn->error;
                } else {
                    
                    // Prepara os parâmetros (valores)
                    $params = [
                        $id_motorista, // 1. INT
                        $data_abastecimento, // 2. STRING
                        $valor, // 3. FLOAT
                        $litros, // 4. FLOAT
                        $hodometro, // 5. INT
                        $media_db, // 6. FLOAT ou NULL
                        $posto_nome, // 7. STRING
                        $cidade, // 8. STRING
                        $vinculado_viagem_id_db, // 9. INT ou NULL
                        $foto1_path, // 10. STRING ou NULL
                        $foto2_path // 11. STRING ou NULL
                    ];
                    
                    // Tipos: i, s, d, d, i, d, s, s, i, s, s
                    // O MySQLi exige que valores NULL sejam tratados como STRING ('s') no binding, 
                    // e o valor real do parâmetro deve ser NULL.
                    // Para float (média) e int (id_viagem), vamos usar 's' se forem NULL, ou o tipo correto se tiverem valor.
                    
                    $types = "";
                    $final_params = [];
                    
                    // 1, 2, 3, 4, 5: Fixos
                    $types .= "isddi";
                    $final_params[] = &$params[0]; // id_motorista
                    $final_params[] = &$params[1]; // data_abastecimento
                    $final_params[] = &$params[2]; // valor
                    $final_params[] = &$params[3]; // litros
                    $final_params[] = &$params[4]; // hodometro

                    // 6: media (NULL ou FLOAT)
                    $types .= ($params[5] === null) ? "s" : "d";
                    $final_params[] = &$params[5];

                    // 7, 8: Fixos (STRING)
                    $types .= "ss";
                    $final_params[] = &$params[6]; // posto_nome
                    $final_params[] = &$params[7]; // cidade
                    
                    // 9: vinculado_viagem_id (NULL ou INT)
                    $types .= ($params[8] === null) ? "s" : "i";
                    $final_params[] = &$params[8];

                    // 10, 11: Fixos (STRING ou NULL)
                    $types .= "ss";
                    $final_params[] = &$params[9]; // foto1_path
                    $final_params[] = &$params[10]; // foto2_path

                    // O array final de parâmetros deve ter o tipo na primeira posição, seguido pelos parâmetros por referência
                    array_unshift($final_params, $types);
                    
                    // Executa o bind usando call_user_func_array
                    call_user_func_array(array($stmt, 'bind_param'), $final_params);
                    
                    // LINHA 199 DO SEU ERRO ESTÁ AQUI (APÓS A CORREÇÃO DE LÓGICA, O NÚMERO DEVE BATER)
                    if ($stmt->execute()) {
                        $mensagem_sucesso = "Abastecimento de R$ " . number_format($valor, 2, ',', '.') . " salvo com sucesso!";
                        // Limpa formulário
                        $valor_str = $litros_str = $hodometro_str = $media_str = $vinculado_viagem_id_str = $posto_nome = $cidade = null; 
                    } else {
                        $mensagem_erro = "Erro ao salvar no banco de dados: " . $stmt->error;
                        // Se falhou, remova os arquivos que foram salvos temporariamente
                        cleanup_files($foto1_path, $foto2_path);
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $mensagem_erro = "Erro de conexão: " . $e->getMessage();
                cleanup_files($foto1_path, $foto2_path);
            }
        }
    }
    
    // Se não houve salvamento final (ou se houve erro no primeiro passo), 
    // garante que as strings originais e os paths das fotos (se existirem) estejam setados 
    // para re-exibir o formulário ou a tela de confirmação.
    $valor = $valor_str;
    $litros = $litros_str;
    $hodometro = $hodometro_str;
    $media = $media_str;
    $vinculado_viagem_id = $vinculado_viagem_id_str;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Projeto Caminhões</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css"> 
    <style>
        /* CSS compartilhado */
        body { background-color: #7998b6; margin: 0; padding: 0; font-family: Arial, sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
        .top-header { background-color: #5d7e9b; color: white; padding: 15px 20px 5px; display: flex; flex-direction: column; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); }
        .user-info-top { display: flex; align-items: center; font-weight: bold; margin-bottom: 5px; }
        .user-icon-top { font-size: 24px; margin-right: 15px; }
        .breadcrumb { font-size: 0.9em; color: rgba(255, 255, 255, 0.8); margin-top: 5px; padding-bottom: 10px; }
        .breadcrumb a { color: white; text-decoration: none; font-weight: normal; }
        .breadcrumb i { margin: 0 5px; }
        .main-content { flex-grow: 1; padding: 20px; display: flex; flex-direction: column; align-items: center; padding-bottom: 80px; }
        .form-card { background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; text-align: center; }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; text-align: left; }
        .input-group input[type="text"], .input-group input[type="file"], .input-group select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ccc; 
            border-radius: 5px; 
            box-sizing: border-box; 
            font-size: 1em; 
        }

        /* ESTILO ESPECÍFICO PARA INPUT DE ARQUIVO */
        .input-group input[type="file"] {
            padding: 10px;
            font-size: 0.9em;
            color: #555;
            background-color: #f9f9f9;
        }

        .button-group-form { display: flex; justify-content: space-between; margin-top: 20px; gap: 10px; }
        
        .action-button-form { 
            padding: 12px 5px;
            border: none; 
            border-radius: 5px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: background-color 0.3s; 
            font-size: 0.9em; 
            white-space: nowrap; 
        }

        .btn-left { 
            background-color: #5d7e9b; 
            color: white; 
            width: 60%; 
        }
        .btn-left:hover { background-color: #4c6d8d; }

        .btn-right { 
            background-color: #63b1e3; 
            color: white; 
            width: 40%; 
        }
        .btn-right:hover { background-color: #529fcc; }
        
        .bottom-nav { background-color: #f0f0f0; padding: 10px 0; display: flex; justify-content: space-around; width: 100%; box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1); position: fixed; bottom: 0; z-index: 1000; }
        .nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #5d7e9b; font-size: 0.75em; font-weight: bold; transition: color 0.3s; }
        .nav-item i { font-size: 1.8em; margin-bottom: 3px; }
        .nav-item.active { color: #63b1e3; }
        .nav-item:hover { color: #333; }
        .msg-container { width: 100%; max-width: 400px; margin-bottom: 15px; padding: 10px; border-radius: 5px; text-align: left; font-weight: bold; }
        .msg-sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* CSS para a Tela de Confirmação */
        .confirmation-card {
            background-color: white; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); 
            width: 100%; 
            max-width: 400px; 
            text-align: left;
        }
        .confirmation-card h2 { 
            text-align: center; 
            color: #5d7e9b; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 10px; 
        }
        .confirmation-card p {
            margin: 8px 0;
            font-size: 1.1em;
            border-bottom: 1px dashed #eee;
            padding-bottom: 5px;
        }
        .confirmation-card strong { 
            color: #333; 
            display: inline-block; 
            width: 150px; 
        }
        
        /* NOVO CSS PARA A TELA DE CONFIRMAÇÃO */
        /* Ajusta o grupo de botões */
        .confirmation-card .button-group-form {
            display: flex;
            justify-content: space-between;
            margin-top: 25px; 
            gap: 10px; 
        }

        /* Garante que os botões da confirmação tenham largura igual */
        .confirmation-card .action-button-form {
            width: 50%; /* Define largura de 50% */
            padding: 12px 5px; /* Ajusta o padding */
            font-size: 0.9em; /* Ajusta a fonte para caber */
            white-space: nowrap;
            box-sizing: border-box; /* Crucial para evitar estouro de largura */
        }
        
        /* Sobrescreve as larguras anteriores que eram específicas do formulário de entrada */
        .confirmation-card .btn-left { 
            width: 50%;
        }
        .confirmation-card .btn-right { 
            width: 50%; 
        }
    </style>
</head>
<body>
<div class="top-header">
    <div class="user-info-top">
        <i class="fas fa-user user-icon-top"></i>
        <span>USUÁRIO: <?php echo htmlspecialchars($nome_usuario); ?></span>
    </div>
    <div class="breadcrumb">
        <a href="home.php">Home</a> 
        <i class="fas fa-chevron-right"></i> 
        <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
    </div>
</div>
<div class="main-content">
    <?php if ($mensagem_sucesso): ?>
        <div class="msg-container msg-sucesso"><?php echo $mensagem_sucesso; ?></div>
    <?php endif; ?>
    <?php if ($mensagem_erro): ?>
        <div class="msg-container msg-erro"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <?php if ($is_confirmation_step): ?>

        <div class="confirmation-card">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirmar Abastecimento</h2>

            <p><strong>Data:</strong> <?php echo date('d/m/Y'); ?></p>
            <p><strong>Valor Total:</strong> 
                <?php echo format_optional_value($valor, 'R$ ', '', 'R$ 0,00'); ?>
            </p>
            <p><strong>Litros:</strong> 
                <?php echo format_optional_value($litros, '', ' L', '0 L'); ?>
            </p>
            <p><strong>Hodômetro:</strong> 
                <?php echo format_optional_value($hodometro, '', ' KM', 'Não informado'); ?>
            </p>
            <p><strong>Média:</strong> 
                <?php echo format_optional_value($media, '', ' km/L', 'Não informada'); ?>
            </p>
            <p><strong>Posto:</strong> 
                <?php echo format_optional_value($posto_nome, '', '', 'Não informado'); ?>
            </p>
            <p><strong>Cidade:</strong> 
                <?php echo format_optional_value($cidade, '', '', 'Não informada'); ?>
            </p>
            <p><strong>ID Viagem:</strong> 
                <?php echo format_optional_value($vinculado_viagem_id, '#', '', 'Não vinculado'); ?>
            </p>
            <p><strong>Foto Nota:</strong> 
                <?php echo $foto1_path ? '<span style="color: green;"><i class="fas fa-check"></i> Anexada</span>' : '<span style="color: gray;">Não anexada</span>'; ?>
            </p>
            <p><strong>Foto KM:</strong> 
                <?php echo $foto2_path ? '<span style="color: green;"><i class="fas fa-check"></i> Anexada</span>' : '<span style="color: gray;">Não anexada</span>'; ?>
            </p>

            <p style="text-align: center; font-weight: bold; margin-top: 20px;">
                Os dados estão corretos?
            </p>

            <form method="POST" action="abastecimento.php">
                <input type="hidden" name="valor" value="<?php echo htmlspecialchars($valor_str); ?>">
                <input type="hidden" name="litros" value="<?php echo htmlspecialchars($litros_str); ?>">
                <input type="hidden" name="hodometro" value="<?php echo htmlspecialchars($hodometro_str); ?>">
                <input type="hidden" name="media" value="<?php echo htmlspecialchars($media_str); ?>">
                <input type="hidden" name="posto_nome" value="<?php echo htmlspecialchars($posto_nome); ?>">
                <input type="hidden" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>">
                <input type="hidden" name="vinculado_viagem_id" value="<?php echo htmlspecialchars($vinculado_viagem_id_str); ?>">
                
                <input type="hidden" name="foto1_path" value="<?php echo htmlspecialchars($foto1_path); ?>">
                <input type="hidden" name="foto2_path" value="<?php echo htmlspecialchars($foto2_path); ?>">
                
                <input type="hidden" name="action" value="salvar_final">
                
                <div class="button-group-form">
                    <button type="submit" class="action-button-form btn-left" 
                            name="action" value="revert_confirm">
                        <i class="fas fa-edit"></i> CORRIGIR
                    </button>
                    
                    <button type="submit" class="action-button-form btn-right">
                        <i class="fas fa-check"></i> CONFIRMAR E SALVAR
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>

        <form method="POST" action="abastecimento.php" class="form-card" enctype="multipart/form-data">
            
            <input type="hidden" name="action" value="confirmar"> 

            <div class="input-group">
                <label for="valor">VALOR</label>
                <input type="text" id="valor" name="valor" 
                        value="<?php echo htmlspecialchars(isset($valor) ? $valor : ''); ?>" 
                        required>
            </div>
            <div class="input-group">
                <label for="litros">QUANTIDADE DE LITROS</label>
                <input type="text" id="litros" name="litros" 
                        value="<?php echo htmlspecialchars(isset($litros) ? $litros : ''); ?>" 
                        required>
            </div>
            <div class="input-group">
                <label for="hodometro">DISTÂNCIA/HODÔMETRO</label>
                <input type="text" id="hodometro" name="hodometro" 
                        value="<?php echo htmlspecialchars(isset($hodometro) ? $hodometro : ''); ?>">
            </div>
            <div class="input-group">
                <label for="media">MÉDIA (Opcional)</label>
                <input type="text" id="media" name="media" 
                        value="<?php echo htmlspecialchars(isset($media) ? $media : ''); ?>">
            </div>
            <div class="input-group">
                <label for="posto_nome">POSTO (Opcional)</label>
                <input type="text" id="posto_nome" name="posto_nome" 
                        value="<?php echo htmlspecialchars(isset($posto_nome) ? $posto_nome : ''); ?>">
            </div>
            <div class="input-group">
                <label for="cidade">CIDADE (Opcional)</label>
                <input type="text" id="cidade" name="cidade" 
                        value="<?php echo htmlspecialchars(isset($cidade) ? $cidade : ''); ?>">
            </div>
            <div class="input-group">
                <label for="vinculado_viagem_id">ID VIAGEM (Opcional)</label>
                <input type="text" id="vinculado_viagem_id" name="vinculado_viagem_id" 
                        value="<?php echo htmlspecialchars(isset($vinculado_viagem_id) ? $vinculado_viagem_id : ''); ?>">
            </div>

            <div class="input-group">
                <label for="foto1">FOTO NOTA</label>
                <input type="file" id="foto1" name="foto1" accept="image/jpeg, image/png, image/gif" **capture="camera"**>
            </div>
            <div class="input-group">
                <label for="foto2">FOTO DO KM</label>
                <input type="file" id="foto2" name="foto2" accept="image/jpeg, image/png, image/gif" **capture="camera"**>
            </div>
            <div class="button-group-form">
                <button type="button" class="action-button-form btn-left" 
                        onclick="window.location.href='lista_abastecimento.php'">
                    ABASTECIMENTOS
                </button>
                <button type="submit" class="action-button-form btn-right">
                    SALVAR
                </button>
            </div>
        </form>

    <?php endif; ?>
</div>
<div class="bottom-nav">
    <a href="nova_viagem.php" class="nav-item">
        <i class="fas fa-route"></i>
        <span>Viagem</span>
    </a>
    <a href="manutencao.php" class="nav-item">
        <i class="fas fa-wrench"></i>
        <span>Manut.</span>
    </a>
    <a href="home.php" class="nav-item">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="abastecimento.php" class="nav-item active">
        <i class="fas fa-gas-pump"></i>
        <span>Abast.</span>
    </a>
    <a href="pedagio.php" class="nav-item">
        <i class="fas fa-tags"></i>
        <span>Pedágio</span>
    </a>
</div>
</body>
</html>