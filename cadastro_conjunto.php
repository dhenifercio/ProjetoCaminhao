<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
include 'includes/conexao.php';

$mensagem = '';
$erro = '';

// --- LÓGICA DE VINCULAÇÃO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'vincular') {
    $id_carreta = $_POST['id_carreta'];
    $id_cavalo = $_POST['id_cavalo'];

    if (empty($id_carreta) || empty($id_cavalo)) {
        $erro = "Selecione uma carreta e um cavalo para vincular.";
    } else {
        // 1. VERIFICAÇÃO DE VINCULAÇÃO EXISTENTE
        $sql_check_vinculo = "
            SELECT 
                (SELECT COUNT(*) FROM conjuntos WHERE id_carreta = ?) AS carreta_vinculada,
                (SELECT COUNT(*) FROM conjuntos WHERE id_cavalo = ?) AS cavalo_vinculado
        ";
        
        $stmt_check_vinculo = $conn->prepare($sql_check_vinculo);
        $stmt_check_vinculo->bind_param("ii", $id_carreta, $id_cavalo);
        $stmt_check_vinculo->execute();
        $result_vinculo = $stmt_check_vinculo->get_result();
        $vinculo = $result_vinculo->fetch_assoc();
        $stmt_check_vinculo->close();

        if ($vinculo['carreta_vinculada'] > 0 || $vinculo['cavalo_vinculado'] > 0) {
            
            $erro_mensagem = "Falha na vinculação: ";
            $erros_detalhes = [];

            if ($vinculo['carreta_vinculada'] > 0) {
                $erros_detalhes[] = "A Carreta selecionada já está em um conjunto.";
            }
            if ($vinculo['cavalo_vinculado'] > 0) {
                $erros_detalhes[] = "O Cavalo selecionado já está em um conjunto.";
            }

            $erro = $erro_mensagem . implode(" ", $erros_detalhes);

        } else {
            // Se nenhum dos veículos está vinculado, prossegue com a inserção
            $sql_insert = "INSERT INTO conjuntos (id_carreta, id_cavalo) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ii", $id_carreta, $id_cavalo);
            
            if ($stmt_insert->execute()) {
                $mensagem = "Conjunto vinculado com sucesso!";
            } else {
                // Caso ocorra algum erro no banco (ex: chave duplicada por índice único, embora a lógica acima já previna)
                $erro = "Erro ao vincular conjunto: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    }
}
// --- LÓGICA DE DESVINCULAÇÃO (POST) ---
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'desvincular') {
    $id_carreta = $_POST['id_carreta'];
    $id_cavalo = $_POST['id_cavalo'];

    // O código de desvinculação original estava usando uma lógica OR que pode ser perigosa.
    // Vamos simplificar para desvincular o conjunto onde o Cavalo OU a Carreta está.
    // Se ambos forem enviados, ele desvincula o conjunto correspondente.
    // Usaremos WHERE id_carreta = ? OR id_cavalo = ? para desvincular o registro que contém um dos IDs.
    
    $sql_delete = "DELETE FROM conjuntos WHERE id_carreta = ? OR id_cavalo = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    
    // Se o ID for vazio (não selecionado), usa 0, que não deve existir na tabela, garantindo segurança.
    $id_carreta_safe = empty($id_carreta) ? 0 : $id_carreta;
    $id_cavalo_safe = empty($id_cavalo) ? 0 : $id_cavalo;

    // Se a desvinculação é baseada na seleção de apenas um veículo, o outro ID será 0
    // e o `OR` garante que o registro que possui o ID válido seja deletado.
    $stmt_delete->bind_param("ii", $id_carreta_safe, $id_cavalo_safe);

    if ($stmt_delete->execute() && $stmt_delete->affected_rows > 0) {
        $mensagem = "Veículos desvinculados com sucesso!";
    } else {
        $erro = "Erro: Nenhum conjunto encontrado para desvincular com os IDs selecionados. Selecione pelo menos uma Carreta OU um Cavalo.";
    }
    $stmt_delete->close();
}

$sql_carretas = "SELECT id, modelo, placa, ano FROM caminhoes_carreta ORDER BY modelo";
$result_carretas = $conn->query($sql_carretas);
$sql_cavalos = "SELECT id, modelo, placa, ano FROM caminhoes_cavalo ORDER BY modelo";
$result_cavalos = $conn->query($sql_cavalos);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Conjunto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        function selecionarItem(elemento, nomeCampo) {
            const tabela = elemento.closest('table');
            const linhas = tabela.querySelectorAll('tbody tr');
            linhas.forEach(linha => {
                linha.classList.remove('selecionado');
                const icone = linha.querySelector('.icone-selecao');
                if (icone) {
                    icone.classList.remove('fa-square-check');
                    icone.classList.add('fa-square');
                }
            });
            elemento.classList.add('selecionado');
            const iconeSelecionado = elemento.querySelector('.icone-selecao');
            if (iconeSelecionado) {
                iconeSelecionado.classList.remove('fa-square');
                iconeSelecionado.classList.add('fa-square-check');
            }
            document.getElementById(nomeCampo).value = elemento.getAttribute('data-id');
        }
        function submeterAcao(acao) {
            const form = document.getElementById('form-conjunto');
            const id_carreta = document.getElementById('id_carreta').value;
            const id_cavalo = document.getElementById('id_cavalo').value;
            if (acao === 'vincular') {
                if (!id_carreta || !id_cavalo) {
                    alert("Para VINCULAR, por favor, selecione uma Carreta e um Cavalo.");
                    return false;
                }
                form.action.value = 'vincular';
                form.submit();
            } else if (acao === 'desvincular') {
                if (!id_carreta && !id_cavalo) {
                    alert("Para DESVINCULAR, selecione a Carreta OU o Cavalo que deseja remover do conjunto.");
                    return false;
                }
                if (confirm("Tem certeza que deseja desvincular os veículos selecionados?")) {
                    form.action.value = 'desvincular';
                    form.submit();
                }
            }
        }
    </script>
    <style>
        /* CSS Adicional para padronizar com a nova estrutura de Card, se necessário */
        .formulario-container {
            padding: 20px; 
            background-color: #fff; 
            border-radius: 8px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .tabelas-selecao {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }
        .tabela-container {
            flex: 1;
            max-height: 400px; /* Limita a altura para scroll */
            overflow-y: auto; /* Permite scroll vertical */
            padding-right: 10px; /* Espaço para a barra de scroll */
        }
        .tabela-selecao {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .tabela-selecao th, .tabela-selecao td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .tabela-selecao th {
            background-color: #f2f2f2;
            position: sticky; /* Fixa o cabeçalho ao rolar */
            top: 0;
            z-index: 10;
        }
        .tabela-selecao tbody tr {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .tabela-selecao tbody tr:hover {
            background-color: #f9f9f9;
        }
        .tabela-selecao tbody tr.selecionado {
            background-color: #d1ecf1 !important;
            font-weight: bold;
        }
        .icone-selecao {
            color: #3498db;
        }
        .botoes-form {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
            gap: 10px;
        }
        .btn-principal, .btn-navegacao, .btn-desvincular {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            color: white;
        }
        .btn-principal { background-color: #2ecc71; }
        .btn-principal:hover { background-color: #27ae60; }
        .btn-navegacao { background-color: #95a5a6; }
        .btn-navegacao:hover { background-color: #7f8c8d; }
        .btn-desvincular { background-color: #e74c3c; }
        .btn-desvincular:hover { background-color: #c0392b; }
        .mensagem-sucesso { color: #27ae60; background: #e6f7ee; border: 1px solid #27ae60; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .mensagem-erro { color: #e74c3c; background: #fbebeb; border: 1px solid #e74c3c; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">
            Home > Cadastro de Caminhão > Cadastro de Conjunto
        </div>
        <div class="formulario-container">
            <h1>Cadastro de Conjunto</h1>
            <?php if (!empty($mensagem)): ?>
                <p class="mensagem-sucesso"><i class="fas fa-check-circle"></i> <?php echo $mensagem; ?></p>
            <?php endif; ?>
            <?php if (!empty($erro)): ?>
                <p class="mensagem-erro"><i class="fas fa-exclamation-triangle"></i> <?php echo $erro; ?></p>
            <?php endif; ?>
            <form id="form-conjunto" action="cadastro_conjunto.php" method="POST">
                <input type="hidden" id="action" name="action" value="vincular">
                <div class="tabelas-selecao">
                    <div class="tabela-container">
                        <h3>SELECIONE A CARRETA</h3>
                        <table class="tabela-selecao">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>MODELO</th>
                                    <th>PLACA</th>
                                    <th>ANO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_carretas->num_rows > 0): ?>
                                    <?php while($row = $result_carretas->fetch_assoc()): ?>
                                        <tr data-id="<?php echo $row['id']; ?>" onclick="selecionarItem(this, 'id_carreta')">
                                            <td><i class="far fa-square icone-selecao"></i></td>
                                            <td><?php echo htmlspecialchars($row['modelo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['placa']); ?></td>
                                            <td><?php echo htmlspecialchars($row['ano']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">Nenhuma carreta cadastrada.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tabela-container">
                        <h3>SELECIONE O CAVALO</h3>
                        <table class="tabela-selecao">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>MODELO</th>
                                    <th>PLACA</th>
                                    <th>ANO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_cavalos->num_rows > 0): ?>
                                    <?php while($row = $result_cavalos->fetch_assoc()): ?>
                                        <tr data-id="<?php echo $row['id']; ?>" onclick="selecionarItem(this, 'id_cavalo')">
                                            <td><i class="far fa-square icone-selecao"></i></td>
                                            <td><?php echo htmlspecialchars($row['modelo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['placa']); ?></td>
                                            <td><?php echo htmlspecialchars($row['ano']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">Nenhum cavalo cadastrada.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <input type="hidden" id="id_carreta" name="id_carreta">
                <input type="hidden" id="id_cavalo" name="id_cavalo">
                <div class="botoes-form">
                    <button type="button" class="btn-navegacao" onclick="window.location.href='lista_vinculados.php'">
                        LISTA DE VINCULADOS
                    </button>
                    <button type="button" class="btn-desvincular" onclick="submeterAcao('desvincular')">
                        DESVINCULAR
                    </button>
                    <button type="button" class="btn-navegacao" onclick="window.location.href='dashboard.php'">
                        VOLTAR
                    </button>
                    <button type="button" class="btn-principal" onclick="submeterAcao('vincular')">
                        VINCULAR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>