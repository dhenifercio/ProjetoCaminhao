<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/conexao.php';

// Dashboard mostra dados globais
$nome_usuario = '';

$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : date('Y-m-01');
$data_final = isset($_GET['data_final']) ? $_GET['data_final'] : date('Y-m-t');

// CÁLCULOS TOTAIS
$sql_faturamento = "SELECT SUM(valor) AS total_faturamento FROM lviagens WHERE data_viagem BETWEEN ? AND ?";
$stmt_fat = $conn->prepare($sql_faturamento);
$stmt_fat->bind_param("ss", $data_inicial, $data_final);
$stmt_fat->execute();
$stmt_fat->bind_result($total_faturamento);
$stmt_fat->fetch();
$stmt_fat->close();
$total_faturamento = $total_faturamento ? $total_faturamento : 0; 

$total_custo = 0;
$sql_pedagios = "SELECT SUM(valor_total) AS total_pedagios FROM pedagios WHERE data_pagamento BETWEEN ? AND ?";
$stmt_pedagios = $conn->prepare($sql_pedagios);
$stmt_pedagios->bind_param("ss", $data_inicial, $data_final);
$stmt_pedagios->execute();
$stmt_pedagios->bind_result($custo_pedagios);
$stmt_pedagios->fetch();
$stmt_pedagios->close();
$total_custo += ($custo_pedagios ? $custo_pedagios : 0); 

$sql_abastecimentos = "SELECT SUM(valor) AS total_abastecimentos FROM abastecimentos WHERE data_abastecimento BETWEEN ? AND ?";
$stmt_abastecimentos = $conn->prepare($sql_abastecimentos);
$stmt_abastecimentos->bind_param("ss", $data_inicial, $data_final);
$stmt_abastecimentos->execute();
$stmt_abastecimentos->bind_result($custo_abastecimentos);
$stmt_abastecimentos->fetch();
$stmt_abastecimentos->close();
$total_custo += ($custo_abastecimentos ? $custo_abastecimentos : 0); 

$sql_manutencoes = "SELECT SUM(valor_total) AS total_manutencoes FROM manutencoes WHERE data_servico BETWEEN ? AND ?";
$stmt_manutencoes = $conn->prepare($sql_manutencoes);
$stmt_manutencoes->bind_param("ss", $data_inicial, $data_final);
$stmt_manutencoes->execute();
$stmt_manutencoes->bind_result($custo_manutencoes);
$stmt_manutencoes->fetch();
$stmt_manutencoes->close();
$total_custo += ($custo_manutencoes ? $custo_manutencoes : 0); 

$total_lucro = $total_faturamento - $total_custo;

// DADOS DIÁRIOS PARA O GRÁFICO
$sql_receitas_dia = "SELECT DATE_FORMAT(data_viagem, '%d/%m') AS dia, SUM(valor) AS valor FROM lviagens WHERE data_viagem BETWEEN ? AND ? GROUP BY data_viagem ORDER BY data_viagem ASC";
$stmt_rec_dia = $conn->prepare($sql_receitas_dia);
$stmt_rec_dia->bind_param("ss", $data_inicial, $data_final);
$stmt_rec_dia->execute();
$stmt_rec_dia->bind_result($dia_rec, $valor_rec);
$receitas_diarias = array();
while ($stmt_rec_dia->fetch()) {
    $receitas_diarias[$dia_rec] = $valor_rec;
}
$stmt_rec_dia->close();

$custos_diarios = array();
$sql_custo_pedagio_dia = "SELECT DATE_FORMAT(data_pagamento, '%d/%m') AS dia, SUM(valor_total) AS valor FROM pedagios WHERE data_pagamento BETWEEN ? AND ? GROUP BY data_pagamento ORDER BY data_pagamento ASC";
$stmt_ped_dia = $conn->prepare($sql_custo_pedagio_dia);
$stmt_ped_dia->bind_param("ss", $data_inicial, $data_final);
$stmt_ped_dia->execute();
$stmt_ped_dia->bind_result($dia_ped, $valor_ped);
while ($stmt_ped_dia->fetch()) {
    $custos_diarios[$dia_ped] = (isset($custos_diarios[$dia_ped]) ? $custos_diarios[$dia_ped] : 0) + $valor_ped;
}
$stmt_ped_dia->close();
$sql_custo_abastecimento_dia = "SELECT DATE_FORMAT(data_abastecimento, '%d/%m') AS dia, SUM(valor) AS valor FROM abastecimentos WHERE data_abastecimento BETWEEN ? AND ? GROUP BY data_abastecimento ORDER BY data_abastecimento ASC";
$stmt_abs_dia = $conn->prepare($sql_custo_abastecimento_dia);
$stmt_abs_dia->bind_param("ss", $data_inicial, $data_final);
$stmt_abs_dia->execute();
$stmt_abs_dia->bind_result($dia_abs, $valor_abs);
while ($stmt_abs_dia->fetch()) {
    $custos_diarios[$dia_abs] = (isset($custos_diarios[$dia_abs]) ? $custos_diarios[$dia_abs] : 0) + $valor_abs;
}
$stmt_abs_dia->close();
$sql_custo_manutencao_dia = "SELECT DATE_FORMAT(data_servico, '%d/%m') AS dia, SUM(valor_total) AS valor FROM manutencoes WHERE data_servico BETWEEN ? AND ? GROUP BY data_servico ORDER BY data_servico ASC";
$stmt_man_dia = $conn->prepare($sql_custo_manutencao_dia);
$stmt_man_dia->bind_param("ss", $data_inicial, $data_final);
$stmt_man_dia->execute();
$stmt_man_dia->bind_result($dia_man, $valor_man);
while ($stmt_man_dia->fetch()) {
    $custos_diarios[$dia_man] = (isset($custos_diarios[$dia_man]) ? $custos_diarios[$dia_man] : 0) + $valor_man;
}
$stmt_man_dia->close();

$conn->close();

$labels = array();
$dados_faturamento = array();
$dados_custo = array();

$todas_chaves = array_unique(array_merge(array_keys($receitas_diarias), array_keys($custos_diarios)));
usort($todas_chaves, function($a, $b) {
    $a_data = DateTime::createFromFormat('d/m', $a);
    $b_data = DateTime::createFromFormat('d/m', $b);
    if ($a_data == $b_data) return 0;
    return ($a_data < $b_data) ? -1 : 1;
});

foreach ($todas_chaves as $dia) {
    $labels[] = $dia;
    $dados_faturamento[] = isset($receitas_diarias[$dia]) ? $receitas_diarias[$dia] : 0; 
    $dados_custo[] = isset($custos_diarios[$dia]) ? $custos_diarios[$dia] : 0;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta charset="UTF-8">
    <title>Dashboard Global</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dados-resumo {
            display: flex;
            justify-content: space-around;
            gap: 20px;
            margin-bottom: 20px;
        }
        .dados-resumo > div {
            flex-grow: 1;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            color: white;
            font-size: 1.1em;
            transition: transform 0.3s;
        }
        .dados-resumo > div:hover {
            transform: translateY(-5px);
        }
        .card-faturamento { background-color: #155a32ff; }
        .card-custo { background-color: #762d25ff; }
        .card-lucro { background-color: #264458ff; }
        .dados-resumo h3 { margin: 10px 0 0; font-size: 2em; }
        .grafico-container {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 100%;
            height: 400px;
        }
        /* Tons de cinza APENAS no filtro de datas */
        .filtros-dashboard {
            margin-bottom: 24px;
            padding: 18px;
            background: linear-gradient(90deg,#eeeeee 92%,#bdbdbd 100%);
            border-radius: 10px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            box-shadow: 0 2px 10px rgba(120,120,120,0.08);
        }
        .filtros-dashboard form {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: end;
            width: 100%;
        }
        .filtro-data-wrapper {
            display: flex;
            flex-direction: column;
            min-width: 120px;
        }
        .filtro-data-wrapper label {
            font-weight: bold;
            font-size: 0.97em;
            color: #424242;
            margin-bottom: 4px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filtro-data-wrapper input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #bdbdbd;
            border-radius: 6px;
            font-size: 1em;
            background: #f5f5f5;
            outline: none;
            transition: border 0.2s;
            color: #424242;
        }
        .filtro-data-wrapper input[type="date"]:focus {
            border-color: #9e9e9e;
            background: #e0e0e0;
        }
        .btn-filtro {
            display: flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(90deg,#757575 70%,#616161 100%);
            color: white;
            font-weight: bold;
            padding: 11px 24px;
            font-size: 1em;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            box-shadow: 0 3px 12px rgba(97,97,97,0.12);
            transition: background 0.2s,box-shadow 0.2s;
        }
        .btn-filtro:hover {
            background: linear-gradient(90deg,#616161 80%,#212121 100%);
            box-shadow: 0 4px 18px rgba(51,51,51,0.18);
        }
    </style>
</head>
<body>
    <div class="container-geral">
        <?php include 'includes/header.php'; ?>
        <div class="conteudo-principal">
            <div class="caminho-navegacao"> Home > Dashboard Global </div>
            <!-- FILTRO DE DATAS MODERNO EM TONS DE CINZA -->
            <div class="filtros-dashboard">
                <form action="dashboard.php" method="GET">
                    <div class="filtro-data-wrapper">
                        <label for="data_inicial">
                            <i class="fa-solid fa-calendar-days"></i> INICIAL
                        </label>
                        <input type="date" id="data_inicial" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial); ?>">
                    </div>
                    <div class="filtro-data-wrapper">
                        <label for="data_final">
                            <i class="fa-solid fa-calendar-days"></i> FINAL
                        </label>
                        <input type="date" id="data_final" name="data_final" value="<?php echo htmlspecialchars($data_final); ?>">
                    </div>
                    <button type="submit" class="btn-filtro">
                        <i class="fa-solid fa-filter"></i> FILTRAR
                    </button>
                </form>
            </div>
            <div class="dados-resumo">
                <div class="card-faturamento">
                    <p>FATURAMENTO (RECEITA)</p>
                    <h3>R$ <?php echo number_format($total_faturamento, 2, ',', '.'); ?></h3>
                </div>
                <div class="card-custo">
                    <p>CUSTO (DESPESA)</p>
                    <h3>R$ <?php echo number_format($total_custo, 2, ',', '.'); ?></h3>
                </div>
                 <div class="card-lucro">
                    <p>LUCRO (RESULTADO)</p>
                    <h3>R$ <?php echo number_format($total_lucro, 2, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="grafico-container"> 
                <canvas id="meuGrafico"></canvas> 
            </div>
        </div>
    </div>
    <script>
        const labels = <?php echo json_encode($labels); ?>;
        const dadosCusto = <?php echo json_encode($dados_custo); ?>;
        const dadosFaturamento = <?php echo json_encode($dados_faturamento); ?>;
        const ctx = document.getElementById('meuGrafico').getContext('2d');
        const meuGrafico = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Custo Total',
                    data: dadosCusto,
                    borderColor: 'rgba(142, 56, 46, 1)', // vermelho
                    backgroundColor: 'rgba(231, 76, 60, 0.2)',
                    tension: 0.4,
                    pointRadius: 4,
                    borderWidth: 3
                }, {
                    label: 'Faturamento Total',
                    data: dadosFaturamento,
                    borderColor: 'rgba(24, 93, 52, 1)', // verde
                    backgroundColor: 'rgba(39, 174, 96, 0.2)',
                    tension: 0.4,
                    pointRadius: 4,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Valor (R$)' } },
                    x: { title: { display: true, text: 'Dia do Mês' } }
                },
                plugins: {
                    legend: { display: true, position: 'top' },
                    title: { display: true, text: 'Comparativo Diário de Custo vs. Faturamento (Visão Global)' }
                }
            }
        });
    </script>
</body>
</html>