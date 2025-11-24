<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
include 'includes/conexao.php';
$mensagem = '';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $modelo = $_POST['modelo'];
    $placa = $_POST['placa'];
    $renavam = $_POST['renavam'];
    $ano = $_POST['ano'];
    $observacao = $_POST['observacao'];
    
    // Insere na tabela de carretas
    $sql = "INSERT INTO caminhoes_carreta (modelo, placa, renavam, ano, observacao)
             VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $modelo, $placa, $renavam, $ano, $observacao);
    
    if ($stmt->execute()) {
        $mensagem = "Carreta cadastrada com sucesso!";
    } else {
        $erro = "Erro ao cadastrar carreta: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Carreta</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        /* Adicionando um estilo básico para o novo botão, se 'style.css' não tiver */
        .botoes-form .btn-lista-carreta {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-right: 10px; /* Para separar do botão VOLTAR */
        }
        .botoes-form .btn-lista-carreta:hover {
            background-color: #2980b9;
        }
        .botoes-form {
            display: flex;
            justify-content: flex-end; /* Alinha os botões à direita */
            align-items: center;
            margin-top: 20px;
        }
        .botoes-form button {
            /* Garantindo que o VOLTAR e CADASTRAR não percam estilo */
            min-width: 120px;
        }
    </style>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">
            Home > Cadastro de Caminhão > Cadastro de Carreta
        </div>
        <div class="formulario-container">
            <h1>Cadastro de Carreta</h1>
            
            <?php if (!empty($mensagem)): ?>
                <p class="mensagem-sucesso"><?php echo $mensagem; ?></p>
            <?php endif; ?>
            
            <?php if (!empty($erro)): ?>
                <p class="mensagem-erro"><?php echo $erro; ?></p>
            <?php endif; ?>
            
            <form action="cadastro_carreta.php" method="POST">
                <div class="grupo-campos">
                    <div class="campo">
                        <label for="modelo">MODELO</label>
                        <input type="text" id="modelo" name="modelo" required>
                    </div>
                    <div class="campo">
                        <label for="placa">PLACA</label>
                        <input type="text" id="placa" name="placa" required>
                    </div>
                </div>
                <div class="grupo-campos">
                    <div class="campo">
                        <label for="renavam">RENAVAM</label>
                        <input type="text" id="renavam" name="renavam">
                    </div>
                    <div class="campo">
                        <label for="ano">ANO</label>
                        <input type="text" id="ano" name="ano" required>
                    </div>
                </div>
                <div class="campo-observacao">
                    <label for="observacao">OBSERVAÇÃO</label>
                    <textarea id="observacao" name="observacao"></textarea>
                </div>
                
                <div class="botoes-form">
                    <button 
                        type="button" 
                        class="btn-lista-carreta"
                        onclick="window.location.href='lista_carreta.php'"
                    >
                        <i class="fas fa-list-ul"></i> LISTA DE CARRETAS
                    </button>
                    <button type="button" onclick="window.location.href='dashboard.php'">
                        VOLTAR
                    </button>
                    <button type="submit" class="btn-principal">CADASTRAR</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>