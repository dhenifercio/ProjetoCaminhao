<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
include 'includes/conexao.php';
$exibir_relatorio = false;
$nome_motorista = '';
$data_inicial = '';
$data_final = '';
$dados_relatorio = [];
$sql_motoristas = "SELECT id, nome_completo, cpf FROM usuarios WHERE tipo_usuario = 'motorista' ORDER BY nome_completo ASC";
$result_motoristas = $conn->query($sql_motoristas);

// Processamento do Formulário (GET)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['motorista_id']) && isset($_GET['data_inicial']) && isset($_GET['data_final']) && !empty($_GET['motorista_id'])) {
    $exibir_relatorio = true;
    $motorista_id = $_GET['motorista_id'];
    $data_inicial = $_GET['data_inicial'];
    $data_final = $_GET['data_final'];
    
    // Preparação do nome do motorista
    $sql_nome = "SELECT nome_completo FROM usuarios WHERE id = ?";
    $stmt_nome = $conn->prepare($sql_nome);
    $stmt_nome->bind_param("i", $motorista_id);
    $stmt_nome->execute();
    $result_nome = $stmt_nome->get_result();
    $nome_motorista = $result_nome->fetch_assoc()['nome_completo'];
    
    // Preparação do relatório
    $sql_relatorio = "SELECT 
                        COUNT(id) AS n_viagens,
                        SUM(km_total) AS km_total,
                        SUM(faturamento) AS faturamento,
                        SUM(pedagio) AS pedagio,
                        SUM(abastecimento) AS abastecimento,
                        SUM(manutencao) AS manutencao,
                        SUM(faturamento - pedagio - abastecimento - manutencao) AS lucro
                      FROM viagens
                      WHERE id_motorista = ? AND data_viagem BETWEEN ? AND ?";
    $stmt_relatorio = $conn->prepare($sql_relatorio);
    $stmt_relatorio->bind_param("iss", $motorista_id, $data_inicial, $data_final);
    $stmt_relatorio->execute();
    $dados_relatorio = $stmt_relatorio->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Visualizar Fechamento</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        /* Estilos básicos para o contêiner de seleção */
        .selecao-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .selecao-box {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex: 1; /* Distribui o espaço entre as caixas */
        }
        .campos-data {
            max-width: 300px;
            display: flex;
            flex-direction: column;
        }
        .campos-data input {
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .campos-data label {
            font-weight: bold;
            margin-top: 10px;
        }
        
        /* Estilos da Tabela de Motoristas */
        .tabela-motoristas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 0.9em;
        }
        .tabela-motoristas th, .tabela-motoristas td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .tabela-motoristas th {
            background-color: #f2f2f2;
        }
        .tabela-motoristas tbody tr {
            cursor: pointer;
            transition: background-color 0.1s;
        }
        .tabela-motoristas tbody tr:hover {
            background-color: #f0f0f0;
        }
        
        /* Estilos de Seleção */
        .tabela-motoristas .selecionado {
            background-color: #d1e7dd; /* Fundo verde claro para indicar seleção */
        }
        .icone-selecao {
            color: #ccc; /* Cinza claro: não selecionado (caixa vazia visual) */
            font-size: 1.2em;
            transition: color 0.1s;
        }
        .tabela-motoristas .selecionado .icone-selecao {
            color: #2ecc71; /* Verde: selecionado (caixa cheia visual) */
        }
        
        /* Estilos do Formulário de Ação */
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
            transition: background-color 0.3s;
        }
        .botoes-form button[type="submit"] {
            background-color: #3498db;
            color: white;
        }
        .botoes-form button[type="button"] {
            background-color: #95a5a6;
            color: white;
        }
        .relatorio-tabela { /* Reutiliza estilos de tabela para o relatório */
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
    </style>
    
    <script>
    function selecionarMotorista(elemento) {
        const tabela = elemento.closest('table');
        const linhas = tabela.querySelectorAll('tr');
        const motoristaIdInput = document.getElementById('motorista_id');
        const idMotorista = elemento.getAttribute('data-id');
        
        // 1. Verifica se a linha clicada JÁ ESTÁ selecionada
        const jaSelecionado = elemento.classList.contains('selecionado');
        
        // Remove a seleção de TODAS as linhas e reseta os ícones e input
        linhas.forEach(linha => {
            linha.classList.remove('selecionado');
            
            // Pega o ícone de cada linha
            const icone = linha.querySelector('.icone-selecao');
            if (icone) {
                // Remove o ícone de check (preenchido) e adiciona o de quadrado (vazio)
                icone.classList.remove('fa-solid', 'fa-square-check');
                icone.classList.add('fa-regular', 'fa-square');
            }
        });

        if (jaSelecionado) {
            // Se já estava selecionado, desmarca (toggle)
            motoristaIdInput.value = ''; // Limpa o ID
            // O ícone já foi resetado no loop acima
        } else {
            // Se não estava selecionado, marca esta linha
            elemento.classList.add('selecionado');
            motoristaIdInput.value = idMotorista; // Define o novo ID
            
            // ATUALIZA O ÍCONE DA LINHA CLICADA PARA O SINAL DE CHECK VERDE
            const icone = elemento.querySelector('.icone-selecao');
            if (icone) {
                icone.classList.remove('fa-regular', 'fa-square');
                icone.classList.add('fa-solid', 'fa-square-check');
            }
        }
    }
</script>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">
            Home > Visualizar Fechamento
        </div>
        
        <h1>VISUALIZAR FECHAMENTO</h1>
        
        <?php if (!$exibir_relatorio): ?>
            
            <form action="visualizar_fechamento.php" method="GET">
                <div class="selecao-container">
                    
                    <div class="selecao-box">
                        <h3>SELECIONE O MOTORISTA</h3>
                        <table class="tabela-motoristas">
                            <thead>
                                <tr>
                                    <th></th> <th>ID</th>
                                    <th>NOME</th>
                                    <th>CPF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_motoristas->num_rows > 0): ?>
                                    <?php while($row = $result_motoristas->fetch_assoc()): ?>
                                        <tr data-id="<?php echo $row['id']; ?>" onclick="selecionarMotorista(this)">
                                            <td><i class="fa-regular fa-square icone-selecao"></i></td>
                                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nome_completo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['cpf']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">Nenhum motorista cadastrado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <input type="hidden" id="motorista_id" name="motorista_id" required>
                    </div>
                    
                    <div class="selecao-box campos-data">
                        <h3>SELECIONE O PERÍODO</h3>
                        <label for="data_inicial">DATA INICIAL</label>
                        <input type="date" id="data_inicial" name="data_inicial" required>
                        <label for="data_final">DATA FINAL</label>
                        <input type="date" id="data_final" name="data_final" required>
                    </div>
                </div>
                
                <div class="botoes-form">
                    <button type="button" onclick="history.back()">VOLTAR</button>
                    <button type="submit">VISUALIZAR</button>
                </div>
            </form>
            
        <?php else: ?>
            <div class="relatorio-header">
                <p>MOTORISTA: **<?php echo htmlspecialchars($nome_motorista); ?>**</p>
                <p>PERÍODO: **<?php echo date('d/m/Y', strtotime($data_inicial)); ?> - <?php echo date('d/m/Y', strtotime($data_final)); ?>**</p>
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
                        <td><?php echo htmlspecialchars(isset($dados_relatorio['n_viagens']) ? $dados_relatorio['n_viagens'] : 0); ?></td>
                        <td><?php echo htmlspecialchars(isset($dados_relatorio['km_total']) ? $dados_relatorio['km_total'] : 0); ?>KM</td>
                        <td>R$ <?php echo number_format(isset($dados_relatorio['faturamento']) ? $dados_relatorio['faturamento'] : 0, 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format(isset($dados_relatorio['pedagio']) ? $dados_relatorio['pedagio'] : 0, 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format(isset($dados_relatorio['abastecimento']) ? $dados_relatorio['abastecimento'] : 0, 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format(isset($dados_relatorio['manutencao']) ? $dados_relatorio['manutencao'] : 0, 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format(isset($dados_relatorio['lucro']) ? $dados_relatorio['lucro'] : 0, 2, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
            <div class="botoes-form">
                <button type="button" onclick="window.location.href='visualizar_fechamento.php'">VOLTAR</button>
            </div>
        <?php endif; ?>
        
    </div>
</div>
</body>
</html>