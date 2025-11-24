<?php
session_start();

// Para evitar HTML inesperado em respostas AJAX, não exibir erros no navegador.
// Em ambiente de desenvolvimento você pode ligar display_errors, mas atenção ao JSON.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

include __DIR__ . '/includes/conexao.php';

if ($conn->connect_error) {
    // Se for acesso normal (não AJAX), morre com mensagem simples.
    if (!(isset($_GET['action']) && in_array($_GET['action'], ['delete','edit']))) {
        die("Erro de conexão: " . $conn->connect_error);
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco.']);
        exit();
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
// HANDLER: DELETE via AJAX - lista_motoristas.php?action=delete
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents("php://input"), true);
    $motorista_id = isset($data['id']) ? $data['id'] : null;

    if (empty($motorista_id) || !is_numeric($motorista_id)) {
        return_json(['success' => false, 'message' => 'ID de motorista inválido ou ausente.'], 400);
    }

    $sql_delete = "DELETE FROM usuarios WHERE id = ? AND tipo_usuario = 'motorista'";

    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("i", $motorista_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return_json(['success' => true, 'message' => 'Motorista deletado com sucesso!']);
            } else {
                return_json(['success' => false, 'message' => 'Motorista não encontrado ou já deletado.'], 404);
            }
        } else {
            return_json(['success' => false, 'message' => 'Erro ao executar a deleção: ' . $stmt->error], 500);
        }
    } else {
        return_json(['success' => false, 'message' => 'Erro ao preparar a query: ' . $conn->error], 500);
    }
}

// --------------------------------------------------------------
// HANDLER: EDIT via AJAX - lista_motoristas.php?action=edit
// --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'edit') {
    header('Content-Type: application/json');

    // Checa sessão
    if (!isset($_SESSION['usuario_id'])) {
        return_json(['success' => false, 'message' => 'Sessão expirada ou usuário não autenticado.'], 401);
    }

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!is_array($data)) {
        return_json(['success' => false, 'message' => 'Payload inválido.'], 400);
    }

    $motorista_id = isset($data['id']) ? $data['id'] : null;
    $dados = isset($data['dados']) ? $data['dados'] : [];

    if (empty($motorista_id) || !is_numeric($motorista_id) || empty($dados)) {
        return_json(['success' => false, 'message' => 'Dados de motorista ou ID inválidos.'], 400);
    }

    // Campos válidos e tipos para bind_param (s = string, d = double)
    $campos_validos = [
        'nome_completo' => 's',
        'salario'       => 'd',
        'rg'            => 's',
        'cpf'           => 's',
        'email'         => 's',
        'rua'           => 's',
        'numero'        => 's',
        'cidade'        => 's',
        'estado'        => 's',
        'cep'           => 's'
    ];

    $campos_para_update = [];
    $tipos = '';
    $valores = [];

    foreach ($dados as $campo => $valor) {
        if (array_key_exists($campo, $campos_validos)) {
            $campos_para_update[] = "$campo = ?";
            $tipo = $campos_validos[$campo];
            $tipos .= $tipo;
            if ($campo === 'salario') {
                // garante float (double)
                $valores[] = is_numeric($valor) ? floatval($valor) : 0.0;
            } else {
                $valores[] = $valor;
            }
        }
    }

    if (empty($campos_para_update)) {
        return_json(['success' => true, 'message' => 'Nenhum campo válido para atualização.']); // Alterado para true
    }

    // adiciona tipo inteiro para ID
    $tipos .= 'i';
    $valores[] = (int)$motorista_id;

    $sql_update = "UPDATE usuarios SET " . implode(', ', $campos_para_update) . " WHERE id = ? AND tipo_usuario = 'motorista'";

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
                return_json(['success' => true, 'message' => 'Motorista atualizado com sucesso!']);
            } else {
                // sem alterações (mesmos valores)
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
$sql = "SELECT * FROM usuarios WHERE tipo_usuario = 'motorista' ORDER BY id ASC";
$result_motorista = $conn->query($sql);

if (!$result_motorista) {
    die("Erro na query: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Lista de Motoristas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilo do Card Adicionado */
        .card { 
            padding: 20px; 
            background-color: #fff; 
            border-radius: 8px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        /* Ajuste para garantir que a tabela seja larga */
        .listagem-container { overflow-x: auto; width: 100%; }
        .botoes-rodape { display: flex; justify-content: flex-end; margin-top: 20px; gap: 10px; }
        .botoes-rodape button { padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; color: white; background-color: #95a5a6; }
        .botoes-rodape button:hover { background-color: #7f8c8d; }
        #btn-editar { background-color: #f39c12; }
        #btn-editar:hover { background-color: #e67e22; }
        #btn-salvar { background-color: #27ae60; display: none; }
        #btn-salvar:hover { background-color: #2ecc71; }
        #btn-cancelar { background-color: #e74c3c; display: none; }
        #btn-cancelar:hover { background-color: #c0392b; }
        #btn-deletar { background-color: #c0392b; }
        #btn-deletar:hover { background-color: #942b1f; }
        .card .tabela-lista { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .card .tabela-lista th, .card .tabela-lista td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 0.9em; }
        .card .tabela-lista th { background-color: #f2f2f2; }
        .tabela-lista tbody tr { cursor: pointer; }
        .tabela-lista tbody tr.selecionada { background-color: #b3e5fc !important; font-weight: bold; }
        .editable-field input, .endereco-field input { width: 90%; padding: 5px; border: 1px solid #ccc; box-sizing: border-box; }
        .endereco-field input { margin-bottom: 2px; display: block; }
    </style>
</head>
<body>
<div class="container-geral">
    <?php include 'includes/header.php'; ?>
    <div class="conteudo-principal">
        <div class="caminho-navegacao">Home > Cadastro de Motorista > Lista de Motoristas</div>
        
        <div class="card">
            <h1>LISTA DE MOTORISTAS</h1>
            <div class="listagem-container">
                <table class="tabela-lista">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NOME COMPLETO</th>
                            <th>SALÁRIO</th>
                            <th>RG</th>
                            <th>ENDEREÇO</th>
                            <th>CPF</th>
                            <th>ACESSO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_motorista->num_rows > 0) {
                            while($row = $result_motorista->fetch_assoc()) {
                                $cpf_formatado = $row['cpf'] ? preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", $row['cpf']) : '';
                                $endereco_display = htmlspecialchars($row['rua']) . ", " . htmlspecialchars($row['numero']) . " - " . htmlspecialchars($row['cidade']) . " (" . htmlspecialchars($row['estado']) . "), CEP: " . htmlspecialchars($row['cep']);
                                echo "<tr data-id='" . htmlspecialchars($row['id']) . "'>";
                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td class='editable-field' data-field='nome_completo' data-original-value='" . htmlspecialchars($row['nome_completo']) . "'>" . htmlspecialchars($row['nome_completo']) . "</td>";
                                echo "<td class='editable-field' data-field='salario' data-original-value='" . htmlspecialchars($row['salario']) . "'>R$ " . number_format($row['salario'], 2, ',', '.') . "</td>";
                                echo "<td class='editable-field' data-field='rg' data-original-value='" . htmlspecialchars($row['rg']) . "'>" . htmlspecialchars($row['rg']) . "</td>";
                                echo "<td class='endereco-field' 
                                            data-rua='" . htmlspecialchars($row['rua']) . "' 
                                            data-numero='" . htmlspecialchars($row['numero']) . "' 
                                            data-cidade='" . htmlspecialchars($row['cidade']) . "' 
                                            data-estado='" . htmlspecialchars($row['estado']) . "' 
                                            data-cep='" . htmlspecialchars($row['cep']) . "'
                                            data-original-value='" . $endereco_display . "'>" . $endereco_display . "</td>";
                                echo "<td class='editable-field' data-field='cpf' data-original-value='" . htmlspecialchars($row['cpf']) . "'>" . htmlspecialchars($cpf_formatado) . "</td>";
                                echo "<td class='editable-field' data-field='email' data-original-value='" . htmlspecialchars($row['email']) . "'>" . htmlspecialchars($row['email']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align: center;'>Nenhum motorista cadastrado.</td></tr>";
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
                <button type="button" onclick="window.location.href='cadastro_motorista.php'">VOLTAR</button>
            </div>
        </div>
        </div>
</div>

<script>
    let linhaSelecionada = null;
    let modoEdicao = false;

    const tabela = document.querySelector('.tabela-lista');
    const btnEditar = document.getElementById('btn-editar');
    const btnDeletar = document.getElementById('btn-deletar');
    const btnSalvar = document.getElementById('btn-salvar');
    const btnCancelar = document.getElementById('btn-cancelar');

    btnSalvar.style.display = 'none';
    btnCancelar.style.display = 'none';

    const formatarSalario = (valor, paraInput = false) => {
        const numero = parseFloat(valor);
        if (paraInput) {
            return isNaN(numero) ? '' : numero.toFixed(2);
        } else {
            // Formato R$ 1.234,56
            return 'R$ ' + (isNaN(numero) ? '0,00' : numero.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'));
        }
    };

    const reverterCelula = (cell) => {
        const originalValue = cell.getAttribute('data-original-value');
        const field = cell.getAttribute('data-field');
        const isEditable = cell.classList.contains('editable-field');

        if (!isEditable) {
            // Se não é editable (como o campo Endereço), reverte pelo data-original-value
            if (cell.classList.contains('endereco-field')) {
                cell.innerHTML = cell.getAttribute('data-original-value');
            }
            return;
        }

        if (field === 'salario') {
            cell.innerHTML = formatarSalario(originalValue);
        } else if (field === 'cpf' && originalValue) {
            cell.innerHTML = originalValue.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        } else {
            cell.innerHTML = originalValue;
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

        // Campos editáveis simples
        linhaSelecionada.querySelectorAll('.editable-field').forEach(cell => {
            const field = cell.getAttribute('data-field');
            const originalValue = cell.getAttribute('data-original-value') || '';
            let inputValue = originalValue;
            
            if (field === 'salario') inputValue = formatarSalario(originalValue, true);
            if (field === 'cpf') inputValue = originalValue.replace(/\D/g, ''); // Tira formatação
            
            const inputType = field === 'salario' ? 'number' : (field === 'email' ? 'email' : 'text');
            const input = document.createElement('input');
            input.type = inputType;
            input.value = inputValue;
            if (field === 'salario') input.step = '0.01';
            if (field === 'cpf') input.maxLength = 11;

            cell.innerHTML = '';
            cell.appendChild(input);
        });

        // Endereço (campo complexo)
        const enderecoCell = linhaSelecionada.querySelector('.endereco-field');
        const camposEndereco = ['rua', 'numero', 'cidade', 'estado', 'cep'];
        enderecoCell.innerHTML = '';
        camposEndereco.forEach(campo => {
            const valor = enderecoCell.getAttribute(`data-${campo}`) || '';
            const input = document.createElement('input');
            input.type = 'text';
            input.value = valor;
            input.placeholder = campo.toUpperCase();
            input.setAttribute('data-endereco-field', campo);
            enderecoCell.appendChild(input);
        });
    });

    // 3. Cancelar edição
    btnCancelar.addEventListener('click', function() {
        if (!linhaSelecionada || !modoEdicao) return;

        // Reverte campos editáveis simples
        linhaSelecionada.querySelectorAll('.editable-field').forEach(reverterCelula);
        
        // Reverte campo Endereço
        const enderecoCell = linhaSelecionada.querySelector('.endereco-field');
        enderecoCell.innerHTML = enderecoCell.getAttribute('data-original-value');


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

        const idMotorista = linhaSelecionada.getAttribute('data-id');
        const dadosEditados = {};
        let dadosValidos = true;

        // Coleta dados dos campos simples
        linhaSelecionada.querySelectorAll('.editable-field').forEach(cell => {
            const campo = cell.getAttribute('data-field');
            const input = cell.querySelector('input');
            if (input) {
                let valor = input.value.trim();
                
                // Conversão e limpeza dos dados
                if (campo === 'salario') {
                    // Substitui vírgula por ponto para garantir leitura como float
                    dadosEditados[campo] = parseFloat(valor.replace(',', '.')) || 0;
                } else if (campo === 'cpf') {
                    // Remove caracteres não numéricos
                    dadosEditados[campo] = valor.replace(/\D/g, '');
                } else {
                    dadosEditados[campo] = valor;
                }

                // Validação básica (todos os campos simples, exceto RG, devem ser preenchidos)
                if (valor === '' && campo !== 'rg') dadosValidos = false;
            }
        });

        // Coleta dados do campo Endereço
        linhaSelecionada.querySelectorAll('.endereco-field input[data-endereco-field]').forEach(input => {
            const campo = input.getAttribute('data-endereco-field');
            const valor = input.value.trim();
            dadosEditados[campo] = valor;
            
            // Validação de endereço (todos os sub-campos devem ser preenchidos)
            if (valor === '') dadosValidos = false;
        });

        if (!dadosValidos) {
            alert("Por favor, preencha todos os campos obrigatórios (Nome, Salário, CPF, Acesso e Endereço completo).");
            return;
        }

        if (!confirm("Tem certeza que deseja salvar as alterações?")) return;

        fetch('lista_motoristas.php?action=edit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: idMotorista, dados: dadosEditados })
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

    // 5. Deletar motorista (AJAX para o mesmo arquivo ?action=delete)
    btnDeletar.addEventListener('click', function() {
        if (!linhaSelecionada || modoEdicao) return;

        const idMotorista = linhaSelecionada.getAttribute('data-id');
        const nomeMotorista = linhaSelecionada.querySelector('[data-field="nome_completo"]').textContent.trim();

        if (!confirm(`Tem certeza que deseja DELETAR o motorista "${nomeMotorista}" (ID: ${idMotorista})? Essa ação é irreversível!`)) return;

        fetch('lista_motoristas.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: idMotorista })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) window.location.reload();
        })
        .catch(error => {
            console.error('Erro de exclusão:', error);
            alert('Ocorreu um erro inesperado ao deletar o motorista.');
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