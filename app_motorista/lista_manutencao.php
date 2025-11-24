<?php
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Lista de Manutenções";
$page_icon = "fas fa-wrench";
$usuario_id = $_SESSION['usuario_id'];
$mensagem_erro = ""; 

// Inicialização de variáveis de filtro
$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
$data_final = isset($_GET['data_final']) ? $_GET['data_final'] : '';

$where_clauses = ["usuario_id = ?"];
$params = ["i", &$usuario_id];
$total_manutencoes = 0;
$valor_total_soma = 0.00;
$manutencoes = [];

// Lógica de Filtro de Data
if (!empty($data_inicial)) {
    $data_inicial_sql = date('Y-m-d', strtotime(str_replace('/', '-', $data_inicial)));
    $where_clauses[] = "data_servico >= ?";
    $params[0] .= "s";
    $params[] = &$data_inicial_sql;
}

if (!empty($data_final)) {
    $data_final_sql = date('Y-m-d', strtotime(str_replace('/', '-', $data_final)));
    $where_clauses[] = "data_servico <= ?";
    $params[0] .= "s";
    $params[] = &$data_final_sql;
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Consulta SQL para buscar as manutenções do motorista logado
$sql_manutencoes = "SELECT id, data_servico, descricao, valor_total, forma_pagamento, vinculado_viagem_id 
                    FROM manutencoes 
                    {$where_sql}
                    ORDER BY data_servico DESC, id DESC";

try {
    $stmt = $conn->prepare($sql_manutencoes);
    
    if (count($where_clauses) > 0) {
        call_user_func_array([$stmt, 'bind_param'], $params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $manutencoes[] = $row;
        $valor_total_soma += $row['valor_total'];
        $total_manutencoes++;
    }
    
    $stmt->close();

} catch (Exception $e) {
    $mensagem_erro = "Erro ao carregar manutenções: " . $e->getMessage();
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
        .list-card { background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%; max-width: 600px; text-align: center; }
        
        /* Estilos da Tabela */
        .list-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .list-table th, .list-table td { padding: 10px 5px; text-align: left; border-bottom: 1px solid #ddd; font-size: 0.9em; }
        .list-table th { background-color: #f0f0f0; font-weight: bold; color: #333; }
        .list-table tr:hover { background-color: #f9f9f9; }
        
        /* Ajuste de Largura das Colunas - Manutenção (Foco na coluna VALOR) */
        .list-table th:nth-child(1), .list-table td:nth-child(1) { width: 10%; } /* ID */
        .list-table th:nth-child(2), .list-table td:nth-child(2) { width: 18%; } /* DATA */
        .list-table th:nth-child(3), .list-table td:nth-child(3) { width: 32%; } /* O QUE FOI FEITO? */
        /* CORREÇÃO DO VALOR: Aumentando a largura e garantindo alinhamento */
        .list-table th:nth-child(4), .list-table td:nth-child(4) { 
            width: 18%; 
            text-align: right; 
            white-space: nowrap; /* Evita quebras de linha na célula do valor */
        } 
        .list-table th:nth-child(5), .list-table td:nth-child(5) { width: 22%; } /* FORMA PGTO */

        /* Rodapé da Lista */
        .list-footer { 
            margin-top: 15px; 
            padding-top: 10px; 
            border-top: 1px solid #ccc; 
            display: flex; 
            justify-content: space-between; 
            font-weight: bold; 
            font-size: 1em; 
        }

        .btn-voltar { background-color: #5d7e9b; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-top: 20px; cursor: pointer; transition: background-color 0.3s; font-weight: bold; }
        .btn-voltar:hover { background-color: #4c6d8d; }

        /* Filtros */
        .filter-form { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; width: 100%; max-width: 600px; }
        .filter-form input[type="date"], .filter-form button { padding: 8px; border-radius: 5px; border: 1px solid #ccc; }
        .filter-form button { background-color: #63b1e3; color: white; cursor: pointer; }

        /* Bottom Nav */
        .bottom-nav { background-color: #f0f0f0; padding: 10px 0; display: flex; justify-content: space-around; width: 100%; box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1); position: fixed; bottom: 0; z-index: 1000; }
        .nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #5d7e9b; font-size: 0.75em; font-weight: bold; transition: color 0.3s; }
        .nav-item i { font-size: 1.8em; margin-bottom: 3px; }
        .nav-item.active { color: #63b1e3; }
        .nav-item:hover { color: #333; }
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

    <form method="GET" action="lista_manutencao.php" class="filter-form">
        <input type="date" name="data_inicial" placeholder="Data Inicial" value="<?php echo htmlspecialchars($data_inicial); ?>">
        <input type="date" name="data_final" placeholder="Data Final" value="<?php echo htmlspecialchars($data_final); ?>">
        <button type="submit"><i class="fas fa-filter"></i></button>
        <button type="button" onclick="window.location.href='lista_manutencao.php'"><i class="fas fa-undo"></i></button>
    </form>

    <div class="list-card">
        <h2><?php echo $page_title; ?></h2>

        <?php if (!empty($mensagem_erro)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($mensagem_erro); ?></p>
        <?php endif; ?>

        <?php if ($total_manutencoes > 0): ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>DATA</th>
                        <th>O QUE FOI FEITO?</th>
                        <th style="text-align: right;">VALOR</th> <th>FORMA PGTO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($manutencoes as $manutencao): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($manutencao['id']); ?></td>
                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($manutencao['data_servico']))); ?></td>
                        <td><?php echo htmlspecialchars($manutencao['descricao']); ?></td>
                        <td style="text-align: right;">R$ <?php echo htmlspecialchars(number_format($manutencao['valor_total'], 2, ',', '.')); ?></td>
                        <td><?php echo htmlspecialchars($manutencao['forma_pagamento'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="list-footer">
                <div>TOTAL DE MANUTENÇÕES: <?php echo $total_manutencoes; ?></div>
                <div>VALOR TOTAL: R$ <?php echo htmlspecialchars(number_format($valor_total_soma, 2, ',', '.')); ?></div>
            </div>
        <?php else: ?>
            <p>Nenhuma manutenção encontrada no período.</p>
        <?php endif; ?>

        <button class="btn-voltar" onclick="window.location.href='manutencao.php'">
            VOLTAR
        </button>
    </div>
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