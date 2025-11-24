<?php
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Nova Manutenção"; 
$page_icon = "fas fa-wrench"; 

$mensagem_sucesso = "";
$mensagem_erro = "";
$tipo_manutencao = $descricao = $valor_total = $forma_pagamento = $vinculado_viagem_id = $hodometro = null; 

// Flag para controle da tela de Confirmação
$is_confirmation_step = false;

// Função utilitária para formatar valores opcionais na tela de confirmação
function format_optional_value($value, $prefix = '', $suffix = '', $empty_text = 'Não informado') {
    // Trata se o valor está vazio. Aceita '0' e '0,00' como valores preenchidos.
    if (empty($value) && $value !== '0' && $value !== '0,00' && $value !== 0) {
        return $empty_text;
    }
    return $prefix . htmlspecialchars($value) . $suffix;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Coleta e sanitização (Mantendo as strings para reexibição/confirmação)
    $descricao = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_STRING); 
    $tipo_manutencao = filter_input(INPUT_POST, 'tipo_manutencao', FILTER_SANITIZE_STRING); 
    $hodometro_str = filter_input(INPUT_POST, 'hodometro', FILTER_SANITIZE_STRING);
    $valor_total_str = filter_input(INPUT_POST, 'valor_total', FILTER_SANITIZE_STRING);
    $forma_pagamento = filter_input(INPUT_POST, 'forma_pagamento', FILTER_SANITIZE_STRING);
    $vinculado_viagem_id_str = filter_input(INPUT_POST, 'vinculado_viagem_id', FILTER_SANITIZE_STRING); 

    // Checa a ação do formulário
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    // Validação Mínima
    $valor_para_validacao = empty($valor_total_str) ? 0.00 : (float)str_replace(',', '.', $valor_total_str);

    if (empty($descricao) || $valor_para_validacao <= 0) {
        $mensagem_erro = "Os campos **O Que Foi Feito?** e **Valor** são obrigatórios e o valor deve ser maior que zero.";
    } else {
        
        // Se a ação for SALVAR DE VERDADE
        if ($action == 'salvar_final') {
            
            $usuario_id = $_SESSION['usuario_id'];
            $data_servico = date('Y-m-d'); 
            
            // Conversão para float e int para o banco de dados
            $valor_total = $valor_para_validacao;
            $hodometro = empty($hodometro_str) ? 0 : (int)str_replace(',', '.', $hodometro_str);
            
            // CORREÇÃO CRÍTICA: Define como NULL (valor PHP) se a string estiver vazia
            $vinculado_viagem_id_db = empty($vinculado_viagem_id_str) ? null : (int)$vinculado_viagem_id_str; 

            // QUERY: Usa colunas usuario_id, data_servico, tipo_manutencao, descricao, hodometro, valor_total, forma_pagamento, vinculado_viagem_id
            $sql = "INSERT INTO manutencoes (usuario_id, data_servico, tipo_manutencao, descricao, hodometro, valor_total, forma_pagamento, vinculado_viagem_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            try {
                $stmt = $conn->prepare($sql);
                
                if ($stmt === FALSE) {
                    $mensagem_erro = "Erro ao preparar a query: " . $conn->error;
                } else {
                    
                    // --- INÍCIO DA LÓGICA DE BINDING DINÂMICO ---

                    // Prepara os parâmetros (valores)
                    $params = [
                        $usuario_id, 
                        $data_servico, 
                        $tipo_manutencao, 
                        $descricao, 
                        $hodometro, 
                        $valor_total, 
                        $forma_pagamento, 
                        $vinculado_viagem_id_db // Este valor pode ser NULL
                    ];
                    
                    // Prepara a string de tipos. 'd' é float, 'i' é int, 's' é string.
                    $types = "isssids"; 
                    
                    // Define o tipo para o ID da Viagem
                    if ($vinculado_viagem_id_db === null) {
                        // Se for NULL, usamos 's' (string) para que o MySQLi entenda que é um NULL do DB
                        $types .= "s";
                        // Mantém o valor como NULL no array
                    } else {
                         // Se for um número, usa 'i' (integer)
                        $types .= "i";
                    }

                    // Prepara o array de referências para o bind_param
                    $bind_names[] = $types;
                    for ($i=0; $i<count($params); $i++) {
                        $bind_name = 'bind' . $i;
                        $$bind_name = $params[$i];
                        $bind_names[] = &$$bind_name;
                    }
                    
                    // Executa o bind usando call_user_func_array
                    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
                    
                    // --- FIM DA LÓGICA DE BINDING DINÂMICO ---


                    if ($stmt->execute()) {
                        $mensagem_sucesso = "Manutenção (**{$descricao}**) salva com sucesso!";
                        $tipo_manutencao = $descricao = $valor_total = $forma_pagamento = $vinculado_viagem_id = $hodometro = null; // Limpa formulário
                    } else {
                        $mensagem_erro = "Erro ao salvar no banco de dados: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $mensagem_erro = "Erro de conexão: " . $e->getMessage();
            }
        
        } else {
            // Se a ação for apenas ENVIAR (botão SALVAR na tela inicial)
            if (!$mensagem_erro) {
                $is_confirmation_step = true;
            }
        }
    }
    
    // Mantém as strings originais para preencher o formulário ou a tela de confirmação
    $valor_total = $valor_total_str;
    $vinculado_viagem_id = $vinculado_viagem_id_str;
    $hodometro = $hodometro_str;
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
        .action-button-form { padding: 12px 10px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; transition: background-color 0.3s; font-size: 1em; width: 50%; }
        .btn-left { background-color: #5d7e9b; color: white; }
        .btn-left:hover { background-color: #4c6d8d; }
        .btn-right { background-color: #63b1e3; color: white; }
        .btn-right:hover { background-color: #529fcc; }
        .bottom-nav { background-color: #f0f0f0; padding: 10px 0; display: flex; justify-content: space-around; width: 100%; box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1); position: fixed; bottom: 0; z-index: 1000; }
        .nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #5d7e9b; font-size: 0.75em; font-weight: bold; transition: color 0.3s; }
        .nav-item i { font-size: 1.8em; margin-bottom: 3px; }
        .nav-item.active { color: #63b1e3; }
        .nav-item:hover { color: #333; }
        .msg-container { width: 100%; max-width: 400px; margin-bottom: 15px; padding: 10px; border-radius: 5px; text-align: left; font-weight: bold; }
        .msg-sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* NOVO CSS para a Tela de Confirmação */
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
            width: 150px; /* Aumentado para acomodar labels maiores */
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
            <h2><i class="fas fa-exclamation-triangle"></i> Confirmar Manutenção</h2>

            <p><strong>Data do Serviço:</strong> <?php echo date('d/m/Y'); ?></p>
            <p><strong>Tipo:</strong> <?php echo format_optional_value($tipo_manutencao, '', '', 'Outros/Não definido'); ?></p>
            <p><strong>O Que Foi Feito:</strong> <?php echo htmlspecialchars($descricao); ?></p>
            
            <p><strong>Hodômetro:</strong> 
                <?php echo format_optional_value($hodometro, '', ' KM', 'Não informado'); ?>
            </p> 
            <p><strong>Valor Total:</strong> 
                <?php echo format_optional_value($valor_total, 'R$ ', '', 'R$ 0,00'); ?>
            </p>
            <p><strong>Pagamento:</strong> 
                <?php echo format_optional_value($forma_pagamento, '', '', 'Não informado'); ?>
            </p>
            <p><strong>ID Viagem:</strong> 
                <?php echo format_optional_value($vinculado_viagem_id, '#', '', 'Não vinculado'); ?>
            </p>

            <p style="text-align: center; font-weight: bold; margin-top: 20px;">
                Os dados estão corretos?
            </p>

            <form method="POST" action="manutencao.php">
                <input type="hidden" name="descricao" value="<?php echo htmlspecialchars($descricao); ?>">
                <input type="hidden" name="tipo_manutencao" value="<?php echo htmlspecialchars($tipo_manutencao); ?>">
                <input type="hidden" name="hodometro" value="<?php echo htmlspecialchars($hodometro); ?>">
                <input type="hidden" name="valor_total" value="<?php echo htmlspecialchars($valor_total); ?>">
                <input type="hidden" name="forma_pagamento" value="<?php echo htmlspecialchars($forma_pagamento); ?>">
                <input type="hidden" name="vinculado_viagem_id" value="<?php echo htmlspecialchars($vinculado_viagem_id); ?>">
                
                <input type="hidden" name="action" value="salvar_final">
                
                <div class="button-group-form">
                    <button type="button" class="action-button-form btn-left" 
                            onclick="window.location.href='manutencao.php'">
                        <i class="fas fa-edit"></i> CORRIGIR
                    </button>
                    
                    <button type="submit" class="action-button-form btn-right">
                        <i class="fas fa-check"></i> CONFIRMAR E SALVAR
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>

        <form method="POST" action="manutencao.php" class="form-card">
            
            <input type="hidden" name="action" value="confirmar"> 

            <div class="input-group">
                <label for="descricao">O QUE FOI FEITO?</label>
                <input type="text" id="descricao" name="descricao" 
                        value="<?php echo htmlspecialchars(isset($descricao) ? $descricao : ''); ?>" 
                        required>
            </div>
            <div class="input-group">
                <label for="tipo_manutencao">TIPO MANUTENÇÃO (O Que Comprou?)</label>
                <select id="tipo_manutencao" name="tipo_manutencao">
                    <option value="PREVENTIVA" <?php echo (isset($tipo_manutencao) && $tipo_manutencao == 'PREVENTIVA') ? 'selected' : ''; ?>>PREVENTIVA</option>
                    <option value="CORRETIVA" <?php echo (isset($tipo_manutencao) && $tipo_manutencao == 'CORRETIVA') ? 'selected' : ''; ?>>CORRETIVA</option>
                    <option value="PNEUS" <?php echo (isset($tipo_manutencao) && $tipo_manutencao == 'PNEUS') ? 'selected' : ''; ?>>PNEUS</option>
                    <option value="OLEO" <?php echo (isset($tipo_manutencao) && $tipo_manutencao == 'OLEO') ? 'selected' : ''; ?>>ÓLEO</option>
                    <option value="" <?php echo (empty($tipo_manutencao) || $tipo_manutencao == null) ? 'selected' : ''; ?>>Outros...</option>
                </select>
            </div>
            <div class="input-group">
                <label for="hodometro">HODÔMETRO (KM)</label>
                <input type="text" id="hodometro" name="hodometro" 
                        value="<?php echo htmlspecialchars(isset($hodometro) ? $hodometro : ''); ?>">
            </div>
            <div class="input-group">
                <label for="valor_total">VALOR</label>
                <input type="text" id="valor_total" name="valor_total" 
                        value="<?php echo htmlspecialchars(isset($valor_total) ? $valor_total : ''); ?>"
                        required>
            </div>
            <div class="input-group">
                <label for="forma_pagamento">FORMA DE PAGAMENTO (Opcional)</label>
                <input type="text" id="forma_pagamento" name="forma_pagamento" 
                        value="<?php echo htmlspecialchars(isset($forma_pagamento) ? $forma_pagamento : ''); ?>">
            </div>
            <div class="input-group">
                <label for="vinculado_viagem_id">ID VIAGEM (Opcional)</label>
                <input type="text" id="vinculado_viagem_id" name="vinculado_viagem_id" 
                        value="<?php echo htmlspecialchars(isset($vinculado_viagem_id) ? $vinculado_viagem_id : ''); ?>">
            </div>
            <div class="button-group-form">
                <button type="button" class="action-button-form btn-left" 
                        onclick="window.location.href='lista_manutencao.php'">
                    MANUTENÇÕES
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
    <a href="manutencao.php" class="nav-item active">
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
    <a href="pedagio.php" class="nav-item">
        <i class="fas fa-tags"></i>
        <span>Pedágio</span>
    </a>
</div>
</body>
</html>