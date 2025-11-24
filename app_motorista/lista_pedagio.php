<?php
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Lista de Pedágios"; 
$page_icon = "fas fa-tags"; 
$usuario_id = $_SESSION['usuario_id'];

// -----------------------------------------------------------------
// AJUSTE 1: Lógica de Filtro de Data (Usando GET)
// -----------------------------------------------------------------

// Inicialização de variáveis de filtro (Usando GET para filtros)
$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
$data_final = isset($_GET['data_final']) ? $_GET['data_final'] : '';

$pedagios = []; 
$erro_db = null;

$where_clauses = ["id_motorista = ?"];
$params_types = "i";
$params_values = [&$usuario_id];

// Adiciona filtros de data (no formato YYYY-MM-DD)
if (!empty($data_inicial)) {
    $where_clauses[] = "data_pagamento >= ?";
    $params_types .= "s";
    $params_values[] = &$data_inicial;
}

if (!empty($data_final)) {
    $where_clauses[] = "data_pagamento <= ?";
    $params_types .= "s";
    $params_values[] = &$data_final;
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Consulta SQL com filtros
$sql = "SELECT id, total_pedagios, valor_total, data_pagamento, vinculado_viagem_id 
        FROM pedagios 
        {$where_sql}
        ORDER BY data_pagamento DESC"; 

try {
    $stmt = $conn->prepare($sql);

    // Função auxiliar para referenciar valores (necessário para bind_param dinâmico)
    function refValues($arr){
        if (strnatcmp(phpversion(),'5.3') >= 0) {
            $refs = array();
            foreach($arr as $key => $value)
                $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }
    
    // Bind dinâmico dos parâmetros
    if (count($where_clauses) > 0) {
        call_user_func_array([$stmt, 'bind_param'], array_merge([$params_types], refValues($params_values)));
    }


    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        while ($row = $resultado->fetch_assoc()) {
            $pedagios[] = $row;
        }
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $erro_db = "Erro inesperado ao carregar pedágios: " . $e->getMessage();
}
// -----------------------------------------------------------------
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
        
        /* CONTEÚDO PRINCIPAL (Lista) */
        .main-content { flex-grow: 1; padding: 20px; display: flex; flex-direction: column; align-items: center; padding-bottom: 80px; }
        .list-card { background-color: white; padding: 20px 10px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%; max-width: 450px; overflow-x: auto; text-align: center; }
        .list-title { font-size: 1.4em; font-weight: bold; color: #333; margin-bottom: 15px; text-align: left; padding-left: 10px; }
        
        /* Estilos para a Tabela de Listagem */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9em; margin-bottom: 20px; }
        .data-table th, .data-table td { padding: 8px 5px; text-align: center; border-bottom: 1px solid #ddd; }
        .data-table th { background-color: #f2f2f2; color: #555; font-weight: bold; white-space: nowrap; }
        .data-table tr:hover { background-color: #f9f9f9; cursor: pointer; }

        /* Estilo para Totais (rodape da tabela) */
        .table-footer { display: flex; justify-content: space-between; padding: 10px 5px; font-weight: bold; color: #333; border-top: 2px solid #5d7e9b; }

        /* Botão VOLTAR */
        .btn-voltar { background-color: #5d7e9b; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; transition: background-color 0.3s; font-size: 1.1em; margin-top: 15px; text-decoration: none; display: inline-block; }
        .btn-voltar:hover { background-color: #4c6d8d; }

        /* AJUSTE 2: Estilo do filtro de data (Topo da lista - Adaptado da Manutenção) */
        /* Substitui .date-filter */
        .filter-form { 
            display: flex; 
            justify-content: center; 
            gap: 10px; 
            margin-bottom: 20px; 
            width: 100%; 
            max-width: 450px; 
        }
        .filter-form input[type="date"] { padding: 8px; border-radius: 5px; border: 1px solid #ccc; flex-grow: 1; }
        .filter-form button { padding: 8px; border-radius: 5px; border: 1px solid #ccc; background-color: #63b1e3; color: white; cursor: pointer; }


        /* FOOTER DE NAVEGAÇÃO FIXO (Bottom Menu) */
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
        <a href="pedagio.php">Pedágio</a>
        <i class="fas fa-chevron-right"></i> 
        <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
    </div>
</div>

<div class="main-content">
    
    <form method="GET" action="lista_pedagios.php" class="filter-form">
        <input type="date" name="data_inicial" placeholder="Data Inicial" value="<?php echo htmlspecialchars($data_inicial); ?>">
        <input type="date" name="data_final" placeholder="Data Final" value="<?php echo htmlspecialchars($data_final); ?>">
        <button type="submit" title="Filtrar"><i class="fas fa-filter"></i></button>
        <button type="button" onclick="window.location.href='lista_pedagios.php'" title="Limpar Filtro"><i class="fas fa-undo"></i></button>
    </form>
    
    <div class="list-card">
        <h2 class="list-title">Lista de Pedágios</h2>

        <?php if (isset($erro_db) && $erro_db !== null): ?>
            <p style="color: red; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px;"><?php echo $erro_db; ?></p>
        <?php elseif (empty($pedagios)): ?>
            <p>Nenhum pedágio encontrado para este usuário.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>DATA</th> <th>PEDÁGIOS</th>
                        <th>VALOR</th>
                        <th>ID VIAGEM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_pedagios_count = 0;
                    $total_valor = 0;
                    
                    foreach ($pedagios as $pedagio): 
                        $total_pedagios_count += $pedagio['total_pedagios'];
                        $total_valor += $pedagio['valor_total'];
                    ?>
                    <tr onclick="window.location.href='visualizar_pedagio.php?id=<?php echo $pedagio['id']; ?>'">
                        <td><?php echo $pedagio['id']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($pedagio['data_pagamento'])); ?></td>
                        <td><?php echo number_format($pedagio['total_pedagios'], 0, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($pedagio['valor_total'], 2, ',', '.'); ?></td>
                        <td><?php echo $pedagio['vinculado_viagem_id'] ?: '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="table-footer">
                <span>Total de Pedágios: <?php echo number_format($total_pedagios_count, 0, ',', '.'); ?></span>
                <span>Valor Total: R$ <?php echo number_format($total_valor, 2, ',', '.'); ?></span>
            </div>
            
        <?php endif; ?>
        
        <a href="pedagio.php" class="btn-voltar">VOLTAR</a>
        
    </div>
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

    <a href="abastecimento.php" class="nav-item">
        <i class="fas fa-gas-pump"></i>
        <span>Abast.</span>
    </a>

    <a href="pedagio.php" class="nav-item active">
        <i class="fas fa-tags"></i>
        <span>Pedágio</span>
    </a>
</div>

</body>
</html>