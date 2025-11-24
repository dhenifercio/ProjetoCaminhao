<?php
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Detalhes do Abastecimento"; 
$page_icon = "fas fa-search"; 
$usuario_id = $_SESSION['usuario_id'];
$abastecimento = null;
$mensagem_erro = "";

// 1. Receber e validar o ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $mensagem_erro = "ID de abastecimento inválido ou não fornecido.";
} else {
    $id_abastecimento = (int)$_GET['id'];
    
    // 2. Consultar os dados do abastecimento
    $sql = "SELECT id, data_abastecimento, valor, litros, hodometro, media, posto_nome, cidade, vinculado_viagem_id, foto1_path, foto2_path 
            FROM abastecimentos 
            WHERE id = ? AND id_motorista = ?"; 
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id_abastecimento, $usuario_id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        if ($resultado->num_rows === 1) {
            $abastecimento = $resultado->fetch_assoc();
        } else {
            $mensagem_erro = "Abastecimento não encontrado ou você não tem permissão para visualizá-lo.";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $mensagem_erro = "Erro ao buscar detalhes no banco de dados: " . $e->getMessage();
    }
}

// 3. Função para formatar exibição (CORRIGIDA)
function format_display($label, $value, $prefix = '') {
    // 1. Trim remove espaços em branco antes da verificação
    $trimmed_value = is_string($value) ? trim($value) : $value;
    
    // Lista de campos de texto opcionais (Posto, Cidade)
    $optional_text_fields = ['Posto', 'Cidade'];
    
    // --- Lógica para campos de texto opcionais (Posto, Cidade) ---
    if (in_array($label, $optional_text_fields)) {
        // Se o valor for estritamente NULL ou uma string vazia (após trim)
        if ($value === null || $trimmed_value === '') {
             return "<p><strong>{$label}:</strong> Não informado</p>";
        }
    }
    
    // --- Lógica para Viagem ID (se 0 ou NULL, exibe "Não vinculado") ---
    if ($label === 'Viagem ID') {
        if ($value === null || $trimmed_value === '' || $trimmed_value == 0) {
             return "<p><strong>{$label}:</strong> Não vinculado</p>";
        }
    }
    
    // Para todos os outros campos (Valores, Litros, Hodômetro, Média, Data)
    return "<p><strong>{$label}:</strong> {$prefix}{$trimmed_value}</p>";
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
        /* Estilos Comuns */
        body { background-color: #7998b6; margin: 0; padding: 0; font-family: Arial, sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
        .top-header { background-color: #5d7e9b; color: white; padding: 15px 20px 5px; display: flex; flex-direction: column; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); }
        .user-info-top { display: flex; align-items: center; font-weight: bold; margin-bottom: 5px; }
        .user-icon-top { font-size: 24px; margin-right: 15px; }
        .breadcrumb { font-size: 0.9em; color: rgba(255, 255, 255, 0.8); margin-top: 5px; padding-bottom: 10px; }
        .breadcrumb a { color: white; text-decoration: none; font-weight: normal; }
        .breadcrumb i { margin: 0 5px; }

        /* Estilo do Card de Detalhes */
        .main-content { flex-grow: 1; padding: 20px; display: flex; flex-direction: column; align-items: center; padding-bottom: 80px; }
        .detail-card { background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; text-align: left; }
        .detail-card h2 { text-align: center; color: #5d7e9b; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .detail-card p { margin: 8px 0; font-size: 1em; }
        .detail-card strong { color: #333; display: inline-block; width: 150px; } 

        /* Estilos de Foto */
        .photo-container { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; }
        .photo-container h3 { font-size: 1.1em; color: #5d7e9b; margin-bottom: 10px; }
        .photo-item { margin-bottom: 15px; text-align: center; }
        .photo-item img { max-width: 100%; height: auto; border-radius: 8px; border: 3px solid #ccc; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .photo-item .no-photo { color: #888; font-style: italic; font-size: 0.9em; padding: 10px; background-color: #f0f0f0; border-radius: 5px; display: block; }
        
        .btn-back { background-color: #5d7e9b; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; transition: background-color 0.3s; font-size: 1.1em; margin-top: 20px; text-decoration: none; display: block; text-align: center; }
        .btn-back:hover { background-color: #4c6d8d; }

        /* FOOTER DE NAVEGAÇÃO FIXO (Bottom Menu) */
        .bottom-nav { background-color: #f0f0f0; padding: 10px 0; display: flex; justify-content: space-around; width: 100%; box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1); position: fixed; bottom: 0; z-index: 1000; }
        .nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #5d7e9b; font-size: 0.75em; font-weight: bold; transition: color 0.3s; }
        .nav-item i { font-size: 1.8em; margin-bottom: 3px; }
        .nav-item.active { color: #63b1e3; }
        .nav-item:hover { color: #333; }
        .msg-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; text-align: center; }
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
        <a href="lista_abastecimento.php">Lista Abastecimento</a>
        <i class="fas fa-chevron-right"></i> 
        <i class="<?php echo $page_icon; ?>"></i> Detalhes #<?php echo isset($id_abastecimento) ? $id_abastecimento : '...'; ?>
    </div>
</div>

<div class="main-content">
    <?php if ($mensagem_erro): ?>
        <div class="detail-card msg-erro">
            <?php echo $mensagem_erro; ?>
            <a href="lista_abastecimento.php" class="btn-back" style="margin-top: 15px; background-color: #721c24;">Voltar para a Lista</a>
        </div>
    <?php elseif ($abastecimento): ?>
        <div class="detail-card">
            <h2>Abastecimento #<?php echo $abastecimento['id']; ?></h2>
            
            <?php
            // Campos de texto e números
            echo format_display('Data', date('d/m/Y', strtotime($abastecimento['data_abastecimento'])));
            echo format_display('Valor', number_format($abastecimento['valor'], 2, ',', '.'), 'R$ ');
            echo format_display('Litros', number_format($abastecimento['litros'], 2, ',', '.'), 'L');
            echo format_display('Hodômetro', number_format($abastecimento['hodometro'], 0, ',', '.'), 'KM ');
            echo format_display('Média', number_format($abastecimento['media'], 2, ',', '.'), 'KM/L ');
            
            // Posto e Cidade (usando a nova função)
            echo format_display('Posto', htmlspecialchars($abastecimento['posto_nome']));
            echo format_display('Cidade', htmlspecialchars($abastecimento['cidade']));
            
            // Viagem ID (usando a nova função)
            echo format_display('Viagem ID', $abastecimento['vinculado_viagem_id']);
            ?>

            <div class="photo-container">
                <h3>Comprovantes (Fotos)</h3>

                <div class="photo-item">
                    <?php 
                    $foto1_path = $abastecimento['foto1_path'];
                    // Ajuste o caminho da imagem se o prefixo "../" for o problema em seu ambiente.
                    // O caminho está correto para ser relativo ao diretório /motorista se a pasta 'uploads' estiver em /
                    if (!empty($foto1_path) && file_exists($foto1_path)): 
                    ?>
                        <p><strong>Foto 1 (Comprovante):</strong></p>
                        <img src="<?php echo htmlspecialchars($foto1_path); ?>" alt="Foto 1 do Abastecimento">
                    <?php else: ?>
                        <span class="no-photo">Foto 1 (Comprovante) não enviada.</span>
                    <?php endif; ?>
                </div>

                <div class="photo-item">
                    <?php 
                    $foto2_path = $abastecimento['foto2_path'];
                    if (!empty($foto2_path) && file_exists($foto2_path)): 
                    ?>
                        <p><strong>Foto 2:</strong></p>
                        <img src="<?php echo htmlspecialchars($foto2_path); ?>" alt="Foto 2 do Abastecimento">
                    <?php else: ?>
                        <span class="no-photo">Foto 2 não enviada.</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="lista_abastecimento.php" class="btn-back">Voltar para a Lista</a>
        </div>
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