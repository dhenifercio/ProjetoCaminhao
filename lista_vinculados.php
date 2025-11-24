<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
include 'includes/conexao.php';

// Use uma variável exclusiva para o resultado!
$sql = "SELECT 
            c.id, 
            carreta.modelo AS modelo_carreta, 
            carreta.placa AS placa_carreta, 
            carreta.ano AS ano_carreta,
            cavalo.modelo AS modelo_cavalo,
            cavalo.placa AS placa_cavalo,
            cavalo.ano AS ano_cavalo
        FROM conjuntos AS c
        INNER JOIN caminhoes_carreta AS carreta ON c.id_carreta = carreta.id
        INNER JOIN caminhoes_cavalo AS cavalo ON c.id_cavalo = cavalo.id
        ORDER BY c.id ASC";
$result_conjuntos = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Lista de Vinculados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilos para o Card e Tabela */
        .card { /* Adicionei estilos básicos para simular o 'card' */
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin: 20px auto; /* Centraliza e adiciona margem */
            max-width: 950px; /* Limite o tamanho do card */
        }

        .listagem-titulo { /* Novo estilo para o título dentro do card */
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .botoes-rodape {
            display: flex;
            justify-content: flex-end; 
            margin-top: 20px;
            gap: 10px;
        }
        .botoes-rodape button {
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            color: white;
            background-color: #95a5a6; 
        }
        .botoes-rodape button:hover {
            background-color: #7f8c8d;
        }
        .card .tabela-lista {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .card .tabela-lista th, .card .tabela-lista td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 0.9em;
        }
        .card .tabela-lista th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">
            Home > Cadastro de Caminhão > Lista de Vinculados
        </div>
        
        <div class="card">
            
            <h1 class="listagem-titulo">LISTA DE CONJUNTOS VINCULADOS</h1>
            
            <div class="listagem-container">
                <table class="tabela-lista">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>MOD. CARRETA</th>
                            <th>PLACA CARRETA</th>
                            <th>ANO CARRETA</th>
                            <th>MOD. CAVALO</th>
                            <th>PLACA CAVALO</th>
                            <th>ANO CAVALO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_conjuntos->num_rows > 0) {
                            while($row = $result_conjuntos->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['modelo_carreta']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['placa_carreta']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['ano_carreta']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['modelo_cavalo']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['placa_cavalo']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['ano_cavalo']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7'>Nenhum conjunto vinculado.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="botoes-rodape">
                <button type="button" onclick="window.location.href='cadastro_conjunto.php'">VOLTAR</button>
            </div>
            
        </div> 
        </div>
</div>
</body>
</html>