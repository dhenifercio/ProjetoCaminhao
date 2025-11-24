<?php
// Define o fuso horário para evitar o Warning e garantir data correta
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
// Caminho para conexão
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Carrega o nome do usuário
$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Nova Viagem"; 
$page_icon = "fas fa-route"; 

// Variáveis de feedback e para manter os valores no formulário
$mensagem_sucesso = "";
$mensagem_erro = "";
$origem = $destino = $distancia = $valor = null;

// NOVO: Flag para controle da tela de Confirmação
$is_confirmation_step = false;

// -----------------------------------------------------------------
// NOVO: Função utilitária para formatar valores opcionais na tela de confirmação
// -----------------------------------------------------------------
function format_optional_value($value, $prefix = '', $suffix = '', $empty_text = 'Não informado') {
    // Trata se o valor está vazio. Aceita '0' e '0,00' como valores preenchidos.
    if (empty($value) && $value !== '0' && $value !== '0,00' && $value !== 0) {
        return $empty_text;
    }
    return $prefix . htmlspecialchars($value) . $suffix;
}


// -----------------------------------------------------------------
// Lógica de Processamento do Formulário (POST)
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Coleta e sanitização dos dados brutos
    $origem = filter_input(INPUT_POST, 'origem', FILTER_SANITIZE_STRING);
    $destino = filter_input(INPUT_POST, 'destino', FILTER_SANITIZE_STRING);
    $distancia_str = filter_input(INPUT_POST, 'distancia', FILTER_SANITIZE_STRING);
    $valor_str = filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_STRING);
    
    // Checa o que o usuário quer fazer: CONFIRMAR ou SALVAR
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    // Validação Mínima
    if (empty($origem) || empty($destino)) {
        $mensagem_erro = "Os campos **Origem** e **Destino** são obrigatórios.";
    } else {
        
        // Se a ação for SALVAR DE VERDADE (botão CONFIRMAR na tela de revisão)
        if ($action == 'salvar_final') {
            
            $usuario_id = $_SESSION['usuario_id']; 
            $data_viagem = date('Y-m-d'); 

            // Tratamento de vírgula e conversão (feito apenas no salvamento)
            $distancia = empty($distancia_str) ? 0.00 : (float)str_replace(',', '.', $distancia_str);
            $valor = empty($valor_str) ? 0.00 : (float)str_replace(',', '.', $valor_str);

            $sql = "INSERT INTO lviagens (usuario_id, data_viagem, origem, destino, km, valor) VALUES (?, ?, ?, ?, ?, ?)";
            
            try {
                $stmt = $conn->prepare($sql);
                
                if ($stmt === FALSE) {
                    $mensagem_erro = "Erro ao preparar a query: " . $conn->error;
                } else {
                    $stmt->bind_param("isssdd", $usuario_id, $data_viagem, $origem, $destino, $distancia, $valor);
                    
                    if ($stmt->execute()) {
                        $mensagem_sucesso = "Viagem de **{$origem} para {$destino}** salva com sucesso!";
                        // Limpa formulário após sucesso
                        $origem = $destino = $distancia = $valor = null; 
                    } else {
                        $mensagem_erro = "Erro ao salvar no banco de dados: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $mensagem_erro = "Erro de conexão: " . $e->getMessage();
            }
            
        } 
        // Se a ação for apenas ENVIAR (botão SALVAR na tela inicial)
        else {
             // Se não houve erro de validação, passa para a tela de confirmação
            if (!$mensagem_erro) {
                $is_confirmation_step = true;
            }
        }
    }
    
    // Se houve erro (em qualquer etapa), tenta manter os valores originais no formulário
    if ($mensagem_erro) {
        // Mantém a string com vírgula para exibir no campo de input
        $distancia = $distancia_str;
        $valor = $valor_str;
    }
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
        /* [SEU CSS ORIGINAL] */
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
            width: 120px;
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
            <h2><i class="fas fa-exclamation-triangle"></i> Confirmar Viagem</h2>

            <p><strong>Data:</strong> <?php echo date('d/m/Y'); ?></p>
            <p><strong>Origem:</strong> <?php echo htmlspecialchars($origem); ?></p>
            <p><strong>Destino:</strong> <?php echo htmlspecialchars($destino); ?></p>
            
            <p><strong>Distância:</strong> 
                <?php echo format_optional_value($distancia_str, '', ' KM', 'Não informado'); ?>
            </p> 
            
            <p><strong>Valor:</strong> 
                <?php echo format_optional_value($valor_str, 'R$ ', '', 'Não informado'); ?>
            </p>
            
            <p style="text-align: center; font-weight: bold; margin-top: 20px;">
                Os dados estão corretos?
            </p>

            <form method="POST" action="nova_viagem.php">
                <input type="hidden" name="origem" value="<?php echo htmlspecialchars($origem); ?>">
                <input type="hidden" name="destino" value="<?php echo htmlspecialchars($destino); ?>">
                <input type="hidden" name="distancia" value="<?php echo htmlspecialchars($distancia_str); ?>">
                <input type="hidden" name="valor" value="<?php echo htmlspecialchars($valor_str); ?>">
                
                <input type="hidden" name="action" value="salvar_final">
                
                <div class="button-group-form">
                    <button type="button" class="action-button-form btn-left" 
                            onclick="window.location.href='nova_viagem.php'">
                        <i class="fas fa-edit"></i> CORRIGIR
                    </button>
                    
                    <button type="submit" class="action-button-form btn-right">
                        <i class="fas fa-check"></i> CONFIRMAR E SALVAR
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>

        <form method="POST" action="nova_viagem.php" class="form-card">
            
            <input type="hidden" name="action" value="confirmar"> 

            <div class="input-group">
                <label for="origem">ORIGEM</label>
                <input type="text" id="origem" name="origem" 
                       value="<?php echo htmlspecialchars(isset($origem) ? $origem : ''); ?>" 
                       required>
            </div>
            
            <div class="input-group">
                <label for="destino">DESTINO</label>
                <input type="text" id="destino" name="destino" 
                       value="<?php echo htmlspecialchars(isset($destino) ? $destino : ''); ?>" 
                       required>
            </div>
            
            <div class="input-group">
                <label for="distancia">DISTÂNCIA (KM)</label>
                <input type="text" id="distancia" name="distancia" 
                       value="<?php echo htmlspecialchars(isset($distancia) ? $distancia : ''); ?>">
            </div>
            
            <div class="input-group">
                <label for="valor">VALOR (R$)</label>
                 <input type="text" id="valor" name="valor" 
                       value="<?php echo htmlspecialchars(isset($valor) ? $valor : ''); ?>">
            </div>
            
            <div class="button-group-form">
                <button type="button" class="action-button-form btn-left" 
                        onclick="window.location.href='lista_viagem.php'">
                    VIAGENS
                </button>
                
                <button type="submit" class="action-button-form btn-right">
                    SALVAR
                </button>
            </div>
            
        </form>

    <?php endif; ?>

</div>

<div class="bottom-nav">
    
    <a href="nova_viagem.php" class="nav-item active">
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

    <a href="pedagio.php" class="nav-item">
        <i class="fas fa-tags"></i>
        <span>Pedágio</span>
    </a>
</div>

</body>
</html>