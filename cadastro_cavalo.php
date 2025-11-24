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
    
    // Insere na tabela de cavalos
    $sql = "INSERT INTO caminhoes_cavalo (modelo, placa, renavam, ano, observacao)
             VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $modelo, $placa, $renavam, $ano, $observacao);
    
    if ($stmt->execute()) {
        $mensagem = "Cavalo cadastrado com sucesso!";
    } else {
        $erro = "Erro ao cadastrar cavalo: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Cavalo</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/style.css">

    <style>
        .botoes-form .btn-lista-cavalo {
            background-color: #3498db; /* Azul */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-right: 10px; 
            min-width: 120px;
        }
        .botoes-form .btn-lista-cavalo:hover {
            background-color: #2980b9;
        }
        .botoes-form {
            display: flex;
            justify-content: flex-end; /* Alinha os botões à direita */
            align-items: center;
            margin-top: 20px;
        }
        .botoes-form button {
            min-width: 120px; /* Garante que todos tenham largura parecida */
        }
    </style>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">
            Home > Cadastro de Caminhão > Cadastro de Cavalo
        </div>
        <div class="formulario-container">
            <h1>Cadastro de Cavalo</h1>
            
            <?php if (!empty($mensagem)): ?>
                <p class="mensagem-sucesso"><?php echo $mensagem; ?></p>
            <?php endif; ?>
            
            <?php if (!empty($erro)): ?>
                <p class="mensagem-erro"><?php echo $erro; ?></p>
            <?php endif; ?>
            
            <form action="cadastro_cavalo.php" method="POST">
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
                        class="btn-lista-cavalo"
                        onclick="window.location.href='lista_cavalo.php'"
                    >
                        <i class="fas fa-list-ul"></i> LISTA DE CAVALOS
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