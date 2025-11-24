<?php
// CORREÇÃO: Define o fuso horário no início
date_default_timezone_set('America/Sao_Paulo'); 
session_start();
// Caminho para conexão
include '../includes/conexao.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Motorista';
$page_title = "Lista de Viagens"; 
$page_icon = "fas fa-list"; 

// CORREÇÃO: Inicialização de variáveis de feedback para evitar "Notice: Undefined variable"
$mensagem_sucesso = ""; 
$mensagem_erro = "";

// -----------------------------------------------------------------
// AJUSTE 1: Lógica de Filtro de Data (Usando GET)
// -----------------------------------------------------------------
// Variáveis para filtros (Datas Inicial e Final) - Usando $_GET
$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
$data_final = isset($_GET['data_final']) ? $_GET['data_final'] : '';

$viagens = [];
$total_viagens = 0;
$valor_total = 0.00;

// Lógica de Busca no Banco de Dados (Tabela lviagens)
$where_clauses = ["usuario_id = ?"];
$params_types = "i";
$params_values = [&$usuario_id];

// Adiciona filtros de data e formata para SQL (YYYY-MM-DD)
if (!empty($data_inicial)) {
    // A variável $data_inicial já está no formato YYYY-MM-DD se vier do input[type="date"]
    $where_clauses[] = "data_viagem >= ?";
    $params_types .= "s";
    $params_values[] = &$data_inicial;
}
if (!empty($data_final)) {
    // A variável $data_final já está no formato YYYY-MM-DD se vier do input[type="date"]
    $where_clauses[] = "data_viagem <= ?";
    $params_types .= "s";
    $params_values[] = &$data_final;
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "SELECT id, origem, destino, km, valor, data_viagem 
        FROM lviagens 
        {$where_sql}
        ORDER BY data_viagem DESC";

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


    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        while ($row = $resultado->fetch_assoc()) {
            // Garante que o valor seja float para somar
            $row['valor'] = (float)$row['valor']; 
            $viagens[] = $row;
            $valor_total += $row['valor'];
        }
        $total_viagens = count($viagens);
    } else {
        $mensagem_erro = "Erro ao buscar viagens: " . $stmt->error;
    }
    $stmt->close();
} catch (Exception $e) {
    $mensagem_erro = "Erro de conexão: " . $e->getMessage();
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
        /* [SEU CSS APROVADO - Adaptado para Lista] */
        body { background-color: #7998b6; margin: 0; padding: 0; font-family: Arial, sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
        .top-header { background-color: #5d7e9b; color: white; padding: 15px 20px 5px; display: flex; flex-direction: column; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); }
        .user-info-top { display: flex; align-items: center; font-weight: bold; margin-bottom: 5px; }
        .user-icon-top { font-size: 24px; margin-right: 15px; }
        .breadcrumb { font-size: 0.9em; color: rgba(255, 255, 255, 0.8); margin-top: 5px; padding-bottom: 10px; }
        .breadcrumb a { color: white; text-decoration: none; font-weight: normal; }
        .breadcrumb i { margin: 0 5px; }

        .main-content { flex-grow: 1; padding: 20px; display: flex; flex-direction: column; align-items: center; padding-bottom: 80px; }
        .list-card { 
            background-color: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }
        .list-title { font-size: 1.4em; font-weight: bold; color: #333; margin-bottom: 10px; text-align: center; }

        /* Estilo para a tabela de lista */
        .list-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.8em; }
        .list-table th, .list-table td { padding: 6px 4px; text-align: left; border-bottom: 1px solid #eee; }
        .list-table th { background-color: #f7f7f7; color: #555; font-weight: bold; }
        .list-table tr:hover { background-color: #f0f8ff; }

        /* Rodapé da lista */
        .list-summary { margin-top: 15px; text-align: right; font-weight: bold; font-size: 1em; padding-top: 10px; border-top: 2px solid #ccc; }

        /* AJUSTE 3: Filtros de Data (CSS da Manutenção) */
        .filter-form { 
            display: flex; 
            justify-content: center; 
            gap: 10px; 
            margin-bottom: 20px; 
            width: 100%; 
            max-width: 450px; /* Ajustado para o tamanho do card */
            padding: 0 15px; /* Adicionado padding para respeitar margem do card */
        }
        .filter-form input[type="date"], .filter-form button { padding: 8px; border-radius: 5px; border: 1px solid #ccc; }
        .filter-form button { background-color: #63b1e3; color: white; cursor: pointer; }
        .filter-form input[type="date"] { flex-grow: 1; }
        
        /* Mensagens */
        .msg-container { width: 100%; max-width: 450px; margin-bottom: 15px; padding: 10px; border-radius: 5px; text-align: left; font-weight: bold; }
        .msg-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Navegação Inferior */
        .bottom-nav { background-color: #f0f0f0; padding: 10px 0; display: flex; justify-content: space-around; width: 100%; box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1); position: fixed; bottom: 0; z-index: 1000; }
        .nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #5d7e9b; font-size: 0.75em; font-weight: bold; transition: color 0.3s; }
        .nav-item i { font-size: 1.8em; margin-bottom: 3px; }
        .nav-item.active { color: #63b1e3; }
        .nav-item:hover { color: #333; }
        .btn-voltar {
            background-color: #5d7e9b;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
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
    
    <?php if ($mensagem_erro): ?>
        <div class="msg-container msg-erro"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <form method="GET" action="lista_viagem.php" class="filter-form">
        <input type="date" name="data_inicial" placeholder="Data Inicial" value="<?php echo htmlspecialchars($data_inicial); ?>">
        <input type="date" name="data_final" placeholder="Data Final" value="<?php echo htmlspecialchars($data_final); ?>">
        <button type="submit" title="Filtrar"><i class="fas fa-filter"></i></button>
        <button type="button" onclick="window.location.href='lista_viagem.php'" title="Limpar Filtro"><i class="fas fa-undo"></i></button>
    </form>
    
    <div class="list-card">
        <h2 class="list-title">Lista de Viagens</h2>

        <?php if (empty($viagens)): ?>
            <p style="text-align: center; color: #555;">Nenhuma viagem encontrada para o período selecionado.</p>
        <?php else: ?>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ORIGEM</th>
                        <th>DESTINO</th>
                        <th>KM</th>
                        <th>VALOR</th>
                        <th>DATA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viagens as $viagem): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($viagem['id']); ?></td>
                            <td><?php echo htmlspecialchars($viagem['origem']); ?></td>
                            <td><?php echo htmlspecialchars($viagem['destino']); ?></td>
                            <td><?php echo number_format($viagem['km'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($viagem['valor'], 2, ',', '.'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($viagem['data_viagem'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="list-summary">
                <p>TOTAL DE VIAGENS: <?php echo $total_viagens; ?></p>
                <p>VALOR TOTAL: R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></p>
            </div>
        <?php endif; ?>
        
        <button onclick="window.location.href='home.php'" class="btn-voltar">VOLTAR</button>
    </div>
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