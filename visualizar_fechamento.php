<?php
session_start();
// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// 🎯 CORREÇÃO DE FUSO HORÁRIO: Define o fuso horário para o Brasil
date_default_timezone_set('America/Sao_Paulo');

// ⚠️ Assumindo que o caminho 'includes/conexao.php' está correto.
include 'includes/conexao.php'; 

// 1. Verificar se os dados de entrada foram recebidos via GET
if (!isset($_GET['motorista_id']) || !isset($_GET['data_inicial']) || !isset($_GET['data_final']) || empty($_GET['motorista_id'])) {
    // Redireciona de volta para a tela de seleção se faltarem dados
    header("Location: fechamento.php"); // Altere para o nome da sua tela de seleção, se for diferente
    exit();
}

$motorista_id = $_GET['motorista_id'];
$data_inicial = $_GET['data_inicial'];
$data_final = $_GET['data_final'];

$nome_motorista = 'Motorista Desconhecido';

// Inicializa o array de dados com 0.00 (Compatível com PHP 5.x)
$dados_relatorio = [
    'n_viagens' => 0,
    'km_total' => 0,
    'faturamento' => 0.00,
    'pedagio' => 0.00,
    'abastecimento' => 0.00,
    'manutencao' => 0.00,
    'lucro' => 0.00
];

// --- A. Obter Nome do Motorista ---
$sql_nome = "SELECT nome_completo FROM usuarios WHERE id = ?";
$stmt_nome = $conn->prepare($sql_nome);
if ($stmt_nome) {
    $stmt_nome->bind_param("i", $motorista_id);
    $stmt_nome->execute();
    $result_nome = $stmt_nome->get_result();
    if ($result_nome->num_rows > 0) {
        $nome_motorista = $result_nome->fetch_assoc()['nome_completo'];
    }
    $stmt_nome->close();
}

// --- B. Cálculo de Viagens e Faturamento (Tabela 'lviagens') ---
$sql_viagens = "SELECT 
                    COUNT(id) AS n_viagens,
                    SUM(km) AS km_total,
                    SUM(valor) AS faturamento
                FROM lviagens  /* <--- CORREÇÃO AQUI: nome da tabela alterado para lviagens */
                WHERE usuario_id = ? AND data_viagem BETWEEN ? AND ?";
$stmt_viagens = $conn->prepare($sql_viagens);
if ($stmt_viagens) {
    $stmt_viagens->bind_param("iss", $motorista_id, $data_inicial, $data_final);
    $stmt_viagens->execute();
    $res_viagens = $stmt_viagens->get_result()->fetch_assoc();
    
    // CORREÇÃO DE COMPATIBILIDADE: Substitui '??' por isset() + ternário
    $dados_relatorio['n_viagens'] = (isset($res_viagens['n_viagens']) && $res_viagens['n_viagens'] !== null) ? $res_viagens['n_viagens'] : 0;
    $dados_relatorio['km_total'] = (isset($res_viagens['km_total']) && $res_viagens['km_total'] !== null) ? $res_viagens['km_total'] : 0;
    $dados_relatorio['faturamento'] = (isset($res_viagens['faturamento']) && $res_viagens['faturamento'] !== null) ? $res_viagens['faturamento'] : 0.00;
    
    $stmt_viagens->close();
}

// --- C. Cálculo de Pedágio (Tabela 'pedagios') ---
$sql_pedagio = "SELECT SUM(valor_total) AS total_pedagio
                FROM pedagios
                WHERE id_motorista = ? AND data_pagamento BETWEEN ? AND ?";
$stmt_pedagio = $conn->prepare($sql_pedagio);
if ($stmt_pedagio) {
    $stmt_pedagio->bind_param("iss", $motorista_id, $data_inicial, $data_final);
    $stmt_pedagio->execute();
    $res_pedagio = $stmt_pedagio->get_result()->fetch_assoc();
    $dados_relatorio['pedagio'] = (isset($res_pedagio['total_pedagio']) && $res_pedagio['total_pedagio'] !== null) ? $res_pedagio['total_pedagio'] : 0.00;
    $stmt_pedagio->close();
}

// --- D. Cálculo de Abastecimento (Tabela 'abastecimentos') ---
$sql_abastecimento = "SELECT SUM(valor) AS total_abastecimento
                      FROM abastecimentos
                      WHERE id_motorista = ? AND data_abastecimento BETWEEN ? AND ?";
$stmt_abastecimento = $conn->prepare($sql_abastecimento);
if ($stmt_abastecimento) {
    $stmt_abastecimento->bind_param("iss", $motorista_id, $data_inicial, $data_final);
    $stmt_abastecimento->execute();
    $res_abastecimento = $stmt_abastecimento->get_result()->fetch_assoc();
    $dados_relatorio['abastecimento'] = (isset($res_abastecimento['total_abastecimento']) && $res_abastecimento['total_abastecimento'] !== null) ? $res_abastecimento['total_abastecimento'] : 0.00;
    $stmt_abastecimento->close();
}

// --- E. Cálculo de Manutenção (Tabela 'manutencoes') ---
$sql_manutencao = "SELECT SUM(valor_total) AS total_manutencao
                   FROM manutencoes
                   WHERE usuario_id = ? AND data_servico BETWEEN ? AND ?";
$stmt_manutencao = $conn->prepare($sql_manutencao);
if ($stmt_manutencao) {
    $stmt_manutencao->bind_param("iss", $motorista_id, $data_inicial, $data_final);
    $stmt_manutencao->execute();
    $res_manutencao = $stmt_manutencao->get_result()->fetch_assoc();
    $dados_relatorio['manutencao'] = (isset($res_manutencao['total_manutencao']) && $res_manutencao['total_manutencao'] !== null) ? $res_manutencao['total_manutencao'] : 0.00;
    $stmt_manutencao->close();
}

// --- F. Cálculo do Lucro (Faturamento - Custos) ---
$faturamento = $dados_relatorio['faturamento'];
$custos = $dados_relatorio['pedagio'] + $dados_relatorio['abastecimento'] + $dados_relatorio['manutencao'];
$dados_relatorio['lucro'] = $faturamento - $custos;

$conn->close(); // Fecha a conexão com o banco de dados
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Fechamento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> 
    <style>
        /* Estilos de layout básico, mantenha-os aqui ou em seu style.css */
        .relatorio-tabela { 
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .relatorio-tabela th, .relatorio-tabela td {
             border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .relatorio-tabela th {
            background-color: #f2f2f2;
        }
        .relatorio-header {
            background-color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            font-size: 1.1em;
            font-weight: bold;
        }
        .botoes-form {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }
        .botoes-form button {
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            background-color: #3498db;
            color: white;
            transition: background-color 0.3s;
        }
    </style>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; // Inclui o cabeçalho e menu ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">
            Home > Visualizar Fechamento > Visualizar
        </div>
        
        <h1>RELATÓRIO DE FECHAMENTO</h1>

        <div class="relatorio-header">
            <p>MOTORISTA: <strong><?php echo htmlspecialchars($nome_motorista); ?></strong></p>
            <p>PERÍODO: <strong><?php echo date('d/m/Y', strtotime($data_inicial)); ?> - <?php echo date('d/m/Y', strtotime($data_final)); ?></strong></p>
        </div>
        
        <table class="relatorio-tabela">
            <thead>
                <tr>
                    <th>N° VIAGENS</th>
                    <th>KM TOTAL</th>
                    <th>FATURAMENTO</th>
                    <th>PEDÁGIO</th>
                    <th>ABASTECIMENTO</th>
                    <th>MANUTENÇÃO</th>
                    <th>LUCRO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($dados_relatorio['n_viagens']); ?></td>
                    <td><?php echo htmlspecialchars($dados_relatorio['km_total']); ?>KM</td>
                    <td>R$ <?php echo number_format($dados_relatorio['faturamento'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($dados_relatorio['pedagio'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($dados_relatorio['abastecimento'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($dados_relatorio['manutencao'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($dados_relatorio['lucro'], 2, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="botoes-form">
            <button type="button" onclick="window.location.href='fechamento.php'">VOLTAR</button>
        </div>

    </div>
</div>
</body>
</html>