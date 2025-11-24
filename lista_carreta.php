<?php
session_start();

// Configurações de erro para evitar quebras em AJAX
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

include __DIR__ . '/includes/conexao.php'; // Inclua seu arquivo de conexão

if ($conn->connect_error) {
    // Trata erro de conexão para requisições normais e AJAX
    if (isset($_GET['action']) && in_array($_GET['action'], ['delete','edit'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco.']);
        exit();
    } else {
        die("Erro de conexão: " . $conn->connect_error);
    }
}

/**
 * Função auxiliar para retornar JSON e sair (usada dentro dos handlers AJAX).
 */
function return_json($payload, $code = 200) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($payload);
    exit();
}

// --------------------------------------------------------------
// HANDLER: DELETE via AJAX - lista_carreta.php?action=delete
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents("php://input"), true);
    $carreta_id = isset($data['id']) ? $data['id'] : null;

    if (empty($carreta_id) || !is_numeric($carreta_id)) {
        return_json(['success' => false, 'message' => 'ID de Carreta inválido ou ausente.'], 400);
    }

    $sql_delete = "DELETE FROM caminhoes_carreta WHERE id = ?";

    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("i", $carreta_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return_json(['success' => true, 'message' => 'Carreta deletada com sucesso!']);
            } else {
                return_json(['success' => false, 'message' => 'Carreta não encontrada ou já deletada.'], 404);
            }
        } else {
            return_json(['success' => false, 'message' => 'Erro ao executar a deleção: ' . $stmt->error], 500);
        }
    } else {
        return_json(['success' => false, 'message' => 'Erro ao preparar a query: ' . $conn->error], 500);
    }
}

// --------------------------------------------------------------
// HANDLER: EDIT via AJAX - lista_carreta.php?action=edit
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'edit') {
    header('Content-Type: application/json');

    // Validação de sessão simples
    if (!isset($_SESSION['usuario_id'])) {
        return_json(['success' => false, 'message' => 'Sessão expirada ou usuário não autenticado.'], 401);
    }

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    $carreta_id = isset($data['id']) ? $data['id'] : null;
    $dados = isset($data['dados']) ? $data['dados'] : [];

    if (empty($carreta_id) || !is_numeric($carreta_id) || empty($dados)) {
        return_json(['success' => false, 'message' => 'Dados de Carreta ou ID inválidos.'], 400);
    }

    // Campos válidos e tipos baseados na imagem da tabela (todos 's' = string)
    $campos_validos = [
        'modelo'        => 's',
        'placa'         => 's',
        'renavam'       => 's',
        'ano'           => 's',
        'observacao'    => 's'
    ];

    $campos_para_update = [];
    $tipos = '';
    $valores = [];

    foreach ($dados as $campo => $valor) {
        if (array_key_exists($campo, $campos_validos)) {
            $campos_para_update[] = "$campo = ?";
            $tipos .= $campos_validos[$campo];
            $valores[] = $valor;
        }
    }

    if (empty($campos_para_update)) {
        return_json(['success' => true, 'message' => 'Nenhum campo válido para atualização.']); 
    }

    // adiciona tipo inteiro para ID
    $tipos .= 'i';
    $valores[] = (int)$carreta_id;

    // ATENÇÃO: Mudança da tabela para caminhoes_carreta
    $sql_update = "UPDATE caminhoes_carreta SET " . implode(', ', $campos_para_update) . " WHERE id = ?";

    if ($stmt = $conn->prepare($sql_update)) {
        // bind_param dinâmico
        $bind_params = array_merge([$tipos], $valores);
        $refs = [];
        foreach ($bind_params as $key => $value) {
            $refs[$key] = &$bind_params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $refs);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return_json(['success' => true, 'message' => 'Carreta atualizada com sucesso!']);
            } else {
                return_json(['success' => true, 'message' => 'Nenhuma alteração detectada.']);
            }
        } else {
            return_json(['success' => false, 'message' => 'Erro ao executar a atualização: ' . $stmt->error], 500);
        }

        $stmt->close();
    } else {
        return_json(['success' => false, 'message' => 'Erro ao preparar a query: ' . $conn->error], 500);
    }
}

// --------------------------------------------------------------
// Se chegou aqui, é acesso normal para listar a página HTML
// --------------------------------------------------------------
// ATENÇÃO: Mudança da tabela para caminhoes_carreta
$sql = "SELECT id, modelo, placa, renavam, ano, observacao FROM caminhoes_carreta ORDER BY id ASC";
$result_carreta = $conn->query($sql);

if (!$result_carreta) {
    die("Erro na query: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Lista de Carretas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilos para o card e botões */
        .listagem-container { overflow-x: auto; width: 100%; }
        .botoes-rodape { display: flex; justify-content: flex-end; margin-top: 20px; gap: 10px; }
        .botoes-rodape button { padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; color: white; background-color: #95a5a6; }
        .botoes-rodape button:hover { background-color: #7f8c8d; }
        #btn-editar { background-color: #f39c12; } #btn-editar:hover { background-color: #e67e22; }
        #btn-salvar { background-color: #27ae60; display: none; } #btn-salvar:hover { background-color: #2ecc71; }
        #btn-cancelar { background-color: #e74c3c; display: none; } #btn-cancelar:hover { background-color: #c0392b; }
        #btn-deletar { background-color: #c0392b; } #btn-deletar:hover { background-color: #942b1f; }
        
        /* Estilo para o novo botão 'CAVALOS' (Invertendo a cor do botão da outra lista) */
        .btn-cavalos { background-color: #3498db !important; } .btn-cavalos:hover { background-color: #2980b9 !important; }
        
        /* Estilos da tabela dentro do card */
        .card .tabela-lista { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .card .tabela-lista th, .card .tabela-lista td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 0.9em; }
        .card .tabela-lista th { background-color: #f2f2f2; }
        .tabela-lista tbody tr { cursor: pointer; }
        .tabela-lista tbody tr.selecionada { background-color: #b3e5fc !important; font-weight: bold; }
        .editable-field input, .editable-field textarea { width: 95%; padding: 5px; border: 1px solid #ccc; box-sizing: border-box; }
        textarea.edit-observacao { min-height: 60px; width: 95%; }
        
        /* Estilo do Card (Copiado da tela anterior) */
        .card { 
            padding: 20px; 
            background-color: #fff; 
            border-radius: 8px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-top: 20px; /* Adicionado um espaço superior se o card não for o primeiro elemento */
        }
    </style>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">Home > Cadastro de Carreta > Lista de Carretas</div>
        
        <div class="card"> 
            <h1>LISTA DE CARRETAS</h1>
            <div class="listagem-container">
                <table class="tabela-lista">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>MODELO</th>
                            <th>PLACA</th>
                            <th>RENAVAM</th>
                            <th>ANO</th>
                            <th>OBSERVAÇÃO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_carreta->num_rows > 0) {
                            while($row = $result_carreta->fetch_assoc()) {
                                
                                // Correção PHP 5.x: Substituindo ?? por operador ternário
                                $obs_preview = htmlspecialchars(isset($row['observacao']) ? $row['observacao'] : '');
                                $renavam_val = isset($row['renavam']) ? $row['renavam'] : '';

                                echo "<tr data-id='" . htmlspecialchars($row['id']) . "'>";
                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td class='editable-field' data-field='modelo' data-original-value='" . htmlspecialchars($row['modelo']) . "'>" . htmlspecialchars($row['modelo']) . "</td>";
                                echo "<td class='editable-field' data-field='placa' data-original-value='" . htmlspecialchars($row['placa']) . "'>" . htmlspecialchars($row['placa']) . "</td>";
                                echo "<td class='editable-field' data-field='renavam' data-original-value='" . htmlspecialchars($renavam_val) . "'>" . htmlspecialchars($renavam_val) . "</td>";
                                echo "<td class='editable-field' data-field='ano' data-original-value='" . htmlspecialchars($row['ano']) . "'>" . htmlspecialchars($row['ano']) . "</td>";
                                echo "<td class='editable-field' data-field='observacao' data-original-value='" . $obs_preview . "'>" . nl2br($obs_preview) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align: center;'>Nenhuma carreta cadastrada.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="botoes-rodape">
                <button type="button" id="btn-deletar" disabled><i class="fas fa-trash"></i> DELETAR</button>
                <button type="button" id="btn-editar" disabled><i class="fas fa-edit"></i> EDITAR</button>
                <button type="button" id="btn-cancelar"><i class="fas fa-times"></i> CANCELAR</button>
                <button type="button" id="btn-salvar"><i class="fas fa-save"></i> SALVAR</button>

                <button 
                    type="button" 
                    onclick="window.location.href='lista_cavalo.php'"
                    class="btn-cavalos"
                >
                    <i class="fas fa-truck"></i> CAVALOS
                </button>

                <button type="button" onclick="window.location.href='cadastro_carreta.php'">CADASTRAR</button>
                <button type="button" onclick="window.location.href='cadastro_carreta.php'">VOLTAR</button>
            </div>
        </div> </div>
</div>

<script>
    let linhaSelecionada = null;
    let modoEdicao = false;

    const tabela = document.querySelector('.tabela-lista');
    const btnEditar = document.getElementById('btn-editar');
    const btnDeletar = document.getElementById('btn-deletar');
    const btnSalvar = document.getElementById('btn-salvar');
    const btnCancelar = document.getElementById('btn-cancelar');

    // Estado inicial
    btnSalvar.style.display = 'none';
    btnCancelar.style.display = 'none';

    const reverterCelula = (cell) => {
        const original = cell.getAttribute('data-original-value') || '';
        if (cell.getAttribute('data-field') === 'observacao') {
            // Reverte textarea para o texto com quebras de linha (<br>)
            cell.innerHTML = original ? original.replace(/\n/g, '<br>') : '';
        } else {
            // Reverte input para o texto simples
            cell.innerHTML = original;
        }
    };

    // 1. Seleção de linhas
    tabela.addEventListener('click', function(e) {
        let linha = e.target.closest('tr');
        if (!linha || linha.parentElement.tagName !== 'TBODY' || modoEdicao) return;

        if (linhaSelecionada) {
            linhaSelecionada.classList.remove('selecionada');
        }

        if (linhaSelecionada !== linha) {
            linha.classList.add('selecionada');
            linhaSelecionada = linha;
            btnEditar.disabled = false;
            btnDeletar.disabled = false;
        } else {
            linhaSelecionada = null;
            btnEditar.disabled = true;
            btnDeletar.disabled = true;
        }
    });

    // 2. Habilitar edição
    btnEditar.addEventListener('click', function() {
        if (!linhaSelecionada || modoEdicao) return;

        modoEdicao = true;
        btnEditar.style.display = 'none';
        btnDeletar.style.display = 'none';
        btnSalvar.style.display = 'inline-block';
        btnCancelar.style.display = 'inline-block';

        document.querySelector('.tabela-lista').style.pointerEvents = 'none';
        linhaSelecionada.style.pointerEvents = 'auto';

        linhaSelecionada.querySelectorAll('.editable-field').forEach(cell => {
            const field = cell.getAttribute('data-field');
            const originalValue = cell.getAttribute('data-original-value') || '';

            if (field === 'observacao') {
                const ta = document.createElement('textarea');
                ta.className = 'edit-observacao';
                ta.value = originalValue;
                cell.innerHTML = '';
                cell.appendChild(ta);
            } else {
                const input = document.createElement('input');
                // 'ano' é um campo YEAR, mas usaremos 'text' para maior compatibilidade.
                input.type = (field === 'ano' ? 'text' : 'text'); 
                input.value = originalValue;
                input.style.width = '95%';
                cell.innerHTML = '';
                cell.appendChild(input);
            }
        });
    });

    // 3. Cancelar edição
    btnCancelar.addEventListener('click', function() {
        if (!linhaSelecionada || !modoEdicao) return;

        linhaSelecionada.querySelectorAll('.editable-field').forEach(reverterCelula);

        modoEdicao = false;
        btnEditar.style.display = 'inline-block';
        btnDeletar.style.display = 'inline-block';
        btnSalvar.style.display = 'none';
        btnCancelar.style.display = 'none';

        document.querySelector('.tabela-lista').style.pointerEvents = 'auto';
        linhaSelecionada.style.pointerEvents = 'auto';
    });

    // 4. Salvar edição (AJAX para o mesmo arquivo ?action=edit)
    btnSalvar.addEventListener('click', function() {
        if (!linhaSelecionada || !modoEdicao) return;

        const idCarreta = linhaSelecionada.getAttribute('data-id');
        const dadosEditados = {};
        let dadosValidos = true;

        linhaSelecionada.querySelectorAll('.editable-field').forEach(cell => {
            const campo = cell.getAttribute('data-field');
            const input = cell.querySelector('input, textarea');
            
            if (input) {
                let valor = input.value.trim();
                dadosEditados[campo] = valor;
                
                // Validação: 'modelo', 'placa' e 'ano' são obrigatórios (NOT NULL no DB)
                if ((campo === 'modelo' || campo === 'placa' || campo === 'ano') && valor === '') {
                    dadosValidos = false;
                }
            }
        });


        if (!dadosValidos) {
            alert("Por favor, preencha os campos obrigatórios: Modelo, Placa e Ano.");
            return;
        }

        if (!confirm("Tem certeza que deseja salvar as alterações?")) return;

        fetch('lista_carreta.php?action=edit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: idCarreta, dados: dadosEditados })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.message || 'Erro ao processar a requisição.'); });
            }
            return response.json();
        })
        .then(result => {
            alert(result.message);
            if (result.success) {
                document.querySelector('.tabela-lista').style.pointerEvents = 'auto';
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Erro de edição:', error);
            alert(`Falha ao salvar: ${error.message}`);
        });
    });

    // 5. Deletar carreta (AJAX para o mesmo arquivo ?action=delete)
    btnDeletar.addEventListener('click', function() {
        if (!linhaSelecionada || modoEdicao) return;

        const idCarreta = linhaSelecionada.getAttribute('data-id');
        const modeloCarreta = linhaSelecionada.querySelector('[data-field="modelo"]').textContent.trim();

        if (!confirm(`Tem certeza que deseja DELETAR a Carreta "${modeloCarreta}" (ID: ${idCarreta})? Essa ação é irreversível!`)) return;

        fetch('lista_carreta.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: idCarreta })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) window.location.reload();
        })
        .catch(error => {
            console.error('Erro de exclusão:', error);
            alert('Ocorreu um erro inesperado ao deletar a Carreta.');
        });
    });
</script>

</body>
</html>

<?php
if ($conn->ping()) {
    $conn->close();
}
?>