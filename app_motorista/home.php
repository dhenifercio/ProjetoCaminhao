<?php
session_start();
// Correção do caminho: Volta para a pasta raiz (..) e entra em includes/
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// GARANTINDO O NOME DO USUÁRIO (Correção para PHP 5.x)
// Você deve garantir que 'usuario_nome' está sendo setado na sessão durante o login.
$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Dashboard</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="../css/style.css"> 
    
    <style>
        /* Estilos ESPECÍFICOS para o Dashboard/Home */
        
        body {
            /* Fundo azul claro/cinza, como na imagem de fundo */
            background-color: #7998b6; /* Tom ajustado para a cor da imagem */
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* HEADER SUPERIOR (Azul escuro no topo - base da imagem) */
        .top-header {
            background-color: #5d7e9b; /* Cor de fundo azul escura */
            color: white;
            padding: 15px 20px;
            display: flex;
            /* ALINHAMENTO CORRIGIDO: Alinha à esquerda e remove o 'Home >' */
            justify-content: flex-start; 
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            font-size: 1.1em;
            font-weight: bold;
        }

        .user-info-top {
            display: flex;
            align-items: center;
        }
        
        .user-icon-top {
            font-size: 24px;
            margin-right: 15px; /* Mais espaço entre ícone e texto */
        }
        
        /* Estilos dos Botões Principais */
        .dashboard-container {
            padding: 20px; 
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            /* Centraliza verticalmente o bloco de botões */
            justify-content: center; 
        }

        .button-list {
            display: flex;
            flex-direction: column;
            gap: 15px; /* Espaçamento entre os botões */
            width: 100%;
            max-width: 450px; /* Largura ligeiramente maior para celular/tablet */
        }

        .action-button {
            display: flex;
            flex-direction: row; /* Mantém a organização em linha principal */
            align-items: center;
            justify-content: flex-start; /* Alinha o conteúdo dos botões à esquerda */
            padding: 15px 25px; /* Padding interno */
            background-color: #b0c0cf; /* Cor cinza/azul dos botões na imagem */
            color: #333;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2em;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-decoration: none;
        }

        /* NOVO ESTILO: Garante que o conteúdo do botão se alinhe verticalmente */
        .action-button-content {
            display: flex;
            flex-direction: column; /* Coloca o ícone e o texto em coluna */
            align-items: center; /* Centraliza horizontalmente o ícone e o texto */
            text-align: center;
            width: 100%; /* Ocupa a largura restante do botão */
        }

        .action-button i {
            font-size: 2.5em; /* Ícone maior */
            margin-bottom: 5px; /* Espaço entre ícone e texto */
            margin-right: 0; /* Remove a margem lateral */
            color: #333; 
        }

        .action-button span {
            font-size: 0.8em; /* Texto um pouco menor */
        }
    </style>
</head>
<body>

<div class="top-header">
    <div class="user-info-top">
        <i class="fas fa-user user-icon-top"></i>
        <span>USUÁRIO: <?php echo htmlspecialchars($nome_usuario); ?></span>
    </div>
    </div>

<div class="dashboard-container">
    <div class="button-list">
        
        <a href="nova_viagem.php" class="action-button">
             <div class="action-button-content">
                <i class="fas fa-map-marked-alt"></i>
                <span>NOVA VIAGEM</span>
            </div>
        </a>

        <a href="manutencao.php" class="action-button">
            <div class="action-button-content">
                <i class="fas fa-wrench"></i>
                <span>MANUTENÇÃO</span>
            </div>
        </a>
        
        <a href="abastecimento.php" class="action-button">
            <div class="action-button-content">
                <i class="fas fa-gas-pump"></i>
                <span>ABASTECIMENTO</span>
            </div>
        </a>

        <a href="pedagio.php" class="action-button">
            <div class="action-button-content">
                <i class="fas fa-truck-moving"></i> <span>PEDÁGIO</span>
            </div>
        </a>
    </div>
</div>

</body>
</html>