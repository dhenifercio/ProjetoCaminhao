<?php
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Novo Pedágio"; 
$page_icon = "fas fa-tags"; 

$mensagem_sucesso = "";
$mensagem_erro = "";

// Variáveis de coleta de dados (strings originais)
$total_pedagios_str = $valor_total_str = $vinculado_viagem_id_str = null; 

// Variável de controle da tela
$is_confirmation_step = false;

// Variáveis para re-exibição do formulário (com valores formatados/convertidos)
$total_pedagios = $valor_total = $vinculado_viagem_id = null; 

// Função utilitária para formatar valores opcionais
function format_optional_value($value, $prefix = '', $suffix = '', $empty_text = 'Não informado') {
    // Certifique-se de que a string vazia ou zero seja tratada
    if (empty($value) && $value !== '0' && $value !== '0,00' && $value !== 0) {
        return $empty_text;
    }
    return $prefix . htmlspecialchars($value) . $suffix;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Coleta Ação do Formulário
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    // Coleta e sanitização (strings)
    $total_pedagios_str = filter_input(INPUT_POST, 'total_pedagios', FILTER_SANITIZE_STRING); 
    $valor_total_str = filter_input(INPUT_POST, 'valor_total', FILTER_SANITIZE_STRING);
    $vinculado_viagem_id_str = filter_input(INPUT_POST, 'vinculado_viagem_id', FILTER_SANITIZE_STRING); 
    
    // Conversão para int e float para validação
    $total_pedagios_para_validacao = empty($total_pedagios_str) ? 0 : (int)$total_pedagios_str;
    $valor_total_para_validacao = empty($valor_total_str) ? 0.00 : (float)str_replace(',', '.', $valor_total_str);

    // Validação Mínima
    if ($total_pedagios_para_validacao <= 0 || $valor_total_para_validacao <= 0) {
        $mensagem_erro = "Os campos **Total de Pedágios** e **Valor Total** são obrigatórios e devem ser maiores que zero.";
    } 
    
    // Se não há erros de validação
    if (!$mensagem_erro) {

        // --- LÓGICA DE CONFIRMAÇÃO ---
        if ($action == 'confirmar') {
            $is_confirmation_step = true;
        } 
        
        // --- LÓGICA DE SALVAMENTO FINAL ---
        elseif ($action == 'salvar_final') {

            // 1. Converte para o banco de dados
            $id_motorista = $_SESSION['usuario_id']; 
            $data_pagamento = date('Y-m-d'); 
            $total_pedagios_db = $total_pedagios_para_validacao;
            $valor_total_db = $valor_total_para_validacao;
            
            // Define como NULL para o Foreign Key se a string estiver vazia
            $vinculado_viagem_id_db = empty($vinculado_viagem_id_str) ? null : (int)$vinculado_viagem_id_str; 

            // 2. QUERY com 5 parâmetros
            $sql = "INSERT INTO pedagios (id_motorista, data_pagamento, total_pedagios, valor_total, vinculado_viagem_id) VALUES (?, ?, ?, ?, ?)";
            
            try {
                $stmt = $conn->prepare($sql);
                
                if ($stmt === FALSE) {
                    $mensagem_erro = "Erro ao preparar a query: " . $conn->error;
                } else {
                    
                    // CORREÇÃO FINAL E DEFINITIVA: Tipagem dinâmica para lidar com NULL no último parâmetro
                    
                    // Tipos fixos: id_motorista (i), data_pagamento (s), total_pedagios (i), valor_total (d)
                    $types = "isid"; 
                    
                    // Adiciona o tipo para vinculado_viagem_id. Se for NULL, forçamos 's' para que o MySQLi aceite o NULL.
                    // Se for um valor (int), usamos 'i'.
                    $types .= ($vinculado_viagem_id_db === null) ? "s" : "i"; // <--- CORREÇÃO CRUCIAL
                    
                    // Cria o array de parâmetros com referências, necessário para bind_param quando dinâmico
                    $params_ref = [];
                    // Adiciona a string de tipos como primeiro elemento
                    $params_ref[] = $types; 
                    
                    // Adiciona as referências das variáveis. O & é crucial aqui.
                    $params_ref[] = &$id_motorista;
                    $params_ref[] = &$data_pagamento;
                    $params_ref[] = &$total_pedagios_db;
                    $params_ref[] = &$valor_total_db;
                    $params_ref[] = &$vinculado_viagem_id_db; 

                    // Executa o bind usando call_user_func_array
                    if (!call_user_func_array(array($stmt, 'bind_param'), $params_ref)) {
                         $mensagem_erro = "Erro ao vincular parâmetros (verifique tipos e NULLs): " . $stmt->error;
                    }
                    
                    if (!$mensagem_erro && $stmt->execute()) {
                        $mensagem_sucesso = "Registro de **{$total_pedagios_db}** pedágio(s) salvo com sucesso!";
                        // Limpa formulário
                        $total_pedagios_str = $valor_total_str = $vinculado_viagem_id_str = null; 
                    } elseif (!$mensagem_erro) {
                        $mensagem_erro = "Erro ao salvar no banco de dados: " . $stmt->error;
                    }

                    $stmt->close();
                }
            } catch (Exception $e) {
                $mensagem_erro = "Erro de conexão: " . $e->getMessage();
            }
        } 
        
        // --- LÓGICA DE REVERSÃO DE CONFIRMAÇÃO ---
        elseif ($action == 'revert_confirm') {
            // Volta para a tela de entrada (mantendo is_confirmation_step = false)
        }
    }
    
    // 4. Se houve erro ou reversão, ou se for a tela de confirmação, 
    // garante que as strings originais estejam setadas para re-exibir o formulário ou a tela de confirmação.
    $total_pedagios = $total_pedagios_str;
    $valor_total = $valor_total_str;
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
        .input-group input[type="text"], .input-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        
        .button-group-form { display: flex; justify-content: space-between; margin-top: 20px; gap: 10px; }
        
        .action-button-form { 
            padding: 12px 10px; 
            border: none; 
            border-radius: 5px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: background-color 0.3s; 
            font-size: 0.9em; 
            white-space: nowrap; 
            flex-grow: 1;
        }
        
        /* Ajuste de largura para os botões da tela de entrada */
        .form-card .btn-left { background-color: #5d7e9b; color: white; width: 60%; }
        .form-card .btn-left:hover { background-color: #4c6d8d; }
        .form-card .btn-right { background-color: #63b1e3; color: white; width: 40%; }
        .form-card .btn-right:hover { background-color: #529fcc; }

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
        
        /* Ajuste de largura para os botões da tela de confirmação (50%/50%) */
        .confirmation-card .button-group-form {
            display: flex;
            justify-content: space-between;
            margin-top: 25px; 
            gap: 10px; 
        }
        .confirmation-card .action-button-form {
            width: 50%; /* Define largura de 50% */
            padding: 12px 5px; 
            font-size: 0.9em; 
            box-sizing: border-box; 
        }
        .confirmation-card .btn-left { 
            background-color: #5d7e9b; 
            color: white; 
        }
        .confirmation-card .btn-right { 
            background-color: #63b1e3; 
            color: white; 
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
            <h2><i class="fas fa-exclamation-triangle"></i> Confirmar Pedágio</h2>

            <p><strong>Data:</strong> <?php echo date('d/m/Y'); ?></p>
            <p><strong>Total de Pedágios:</strong> 
                <?php echo format_optional_value($total_pedagios, '', 'x', '0x'); ?>
            </p>
            <p><strong>Valor Total:</strong> 
                <?php echo format_optional_value($valor_total, 'R$ ', '', 'R$ 0,00'); ?>
            </p>
            <p><strong>ID Viagem:</strong> 
                <?php echo format_optional_value($vinculado_viagem_id, '#', '', 'Não vinculado'); ?>
            </p>

            <p style="text-align: center; font-weight: bold; margin-top: 20px;">
                Os dados estão corretos?
            </p>

            <form method="POST" action="pedagio.php">
                <input type="hidden" name="total_pedagios" value="<?php echo htmlspecialchars($total_pedagios_str); ?>">
                <input type="hidden" name="valor_total" value="<?php echo htmlspecialchars($valor_total_str); ?>">
                <input type="hidden" name="vinculado_viagem_id" value="<?php echo htmlspecialchars($vinculado_viagem_id_str); ?>">
                
                <input type="hidden" name="action" value="salvar_final">
                
                <div class="button-group-form">
                    <button type="submit" class="action-button-form btn-left" 
                            name="action" value="revert_confirm">
                        <i class="fas fa-edit"></i> CORRIGIR
                    </button>
                    
                    <button type="submit" class="action-button-form btn-right">
                        <i class="fas fa-check"></i> CONFIRMAR
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>

        <form method="POST" action="pedagio.php" class="form-card">
            
            <input type="hidden" name="action" value="confirmar"> 

            <div class="input-group">
                <label for="total_pedagios">TOTAL DE PEDÁGIOS</label>
                <input type="text" id="total_pedagios" name="total_pedagios" 
                        value="<?php echo htmlspecialchars(isset($total_pedagios) ? $total_pedagios : ''); ?>" 
                        required>
            </div>
            <div class="input-group">
                <label for="valor_total">VALOR TOTAL (R$)</label>
                <input type="text" id="valor_total" name="valor_total" 
                        value="<?php echo htmlspecialchars(isset($valor_total) ? $valor_total : ''); ?>"
                        required>
            </div>
            <div class="input-group">
                <label for="vinculado_viagem_id">ID VIAGEM (Opcional)</label>
                <input type="text" id="vinculado_viagem_id" name="vinculado_viagem_id" 
                        value="<?php echo htmlspecialchars(isset($vinculado_viagem_id) ? $vinculado_viagem_id : ''); ?>">
            </div>
            <div class="button-group-form">
                <button type="button" class="action-button-form btn-left" 
                        onclick="window.location.href='lista_pedagio.php'">
                    PEDÁGIOS
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
    <a href="abastecimento.php" class="nav-item">
        <i class="fas fa-gas-pump"></i>
        <span>Abast.</span>
    </a>
    <a href="pedagio.php" class="nav-item active">
        <i class="fas fa-tags"></i>
        <span>Pedágio</span>
    </a>
</div>
</body>
</html>