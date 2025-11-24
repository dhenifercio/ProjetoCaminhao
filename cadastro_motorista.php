<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
include 'includes/conexao.php';

// Variáveis de mensagem para exibir na tela
$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Coleta e sanitização dos dados
    $nome_completo = trim($_POST['nome_completo']);
    $salario = filter_var($_POST['salario'], FILTER_VALIDATE_FLOAT);
    $rg = trim($_POST['rg']);
    $cpf = trim($_POST['cpf']);
    $cep = trim($_POST['cep']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $rua = trim($_POST['rua']);
    $numero = trim($_POST['numero']);
    $email = trim($_POST['login']); // Using 'login' as the email field
    $senha_bruta = $_POST['senha'];
    $senha_hash = sha1($senha_bruta); // Hashing com SHA1 (compatível com PHP antigo)
    
    // 2. Verifica se a coleta foi bem-sucedida
    if ($salario === false || empty($nome_completo) || empty($email)) {
        $erro = "Erro de validação: Verifique se todos os campos obrigatórios foram preenchidos corretamente.";
    } else {
        // 3. Inserção no banco de dados
        $sql = "INSERT INTO usuarios (nome_completo, salario, rg, cpf, cep, cidade, estado, rua, numero, email, senha, tipo_usuario)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'motorista')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdsssssssss", $nome_completo, $salario, $rg, $cpf, $cep, $cidade, $estado, $rua, $numero, $email, $senha_hash);

        if ($stmt->execute()) {
            $mensagem = "Motorista cadastrado com sucesso!";
        } else {
            if ($conn->errno == 1062) {
                $erro = "Erro: Este e-mail ou CPF já está cadastrado no sistema.";
            } else {
                $erro = "Erro: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Motorista - Dashboard</title>
    
    <style>
        /* Estilo do Card Branco */
        .formulario-container {
            background-color: #ffffff; /* Fundo Branco */
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); /* Sombra para destacar */
            max-width: 800px;
            margin: 20px auto; /* Centraliza na tela */
        }

        /* Estilos básicos para o layout de coluna única */
        .formulario-container label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
        }
        
        .formulario-container input[type="text"],
        .formulario-container input[type="number"],
        .formulario-container input[type="email"],
        .formulario-container input[type="password"],
        .formulario-container select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* Outros estilos do seu sistema (que talvez estivessem no style.css) */
        .botoes-form {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        /* Adicione aqui qualquer outro CSS necessário para header, container-geral, etc. */
    </style>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> 

    <script>
        function limpaCamposEndereco() {
            document.getElementById('rua').value = "";
            document.getElementById('cidade').value = "";
            document.getElementById('estado').value = "";
        }
        function buscarCep(cep) {
            cep = cep.replace(/\D/g, '');
            if (cep.length != 8) {
                limpaCamposEndereco();
                return;
            }
            fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(response => response.json())
            .then(data => {
                if (data.erro) {
                    limpaCamposEndereco();
                } else {
                    document.getElementById('rua').value = data.logradouro || "";
                    document.getElementById('cidade').value = data.localidade || "";
                    document.getElementById('estado').value = data.uf || "";
                }
            })
            .catch(() => limpaCamposEndereco());
        }
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('cep').addEventListener('blur', function() {
                buscarCep(this.value);
            });
        });
    </script>
</head>
<body>
    <div class="container-geral"> 
        <?php include 'includes/header.php'; ?>
        <div class="conteudo-principal">
            <div class="caminho-navegacao">Home > Cadastro de Motorista</div>
            <h1>Cadastro de Motorista</h1>

            <?php if (isset($mensagem)) { echo "<p class='mensagem-sucesso'>$mensagem</p>"; } ?>
            <?php if (isset($erro)) { echo "<p class='mensagem-erro'>$erro</p>"; } ?>

            <div class="formulario-container"> 
                <form action="cadastro_motorista.php" method="POST">
                    
                    <h2>DADOS PESSOAIS E CONTA</h2>
                    
                    <label for="nome_completo">Nome Completo:</label>
                    <input type="text" id="nome_completo" name="nome_completo" required>

                    <label for="salario">Salário (R$):</label>
                    <input type="number" id="salario" name="salario" step="0.01" min="0" required>
                    
                    <label for="rg">RG:</label>
                    <input type="text" id="rg" name="rg" required>
                    
                    <label for="cpf">CPF:</label>
                    <input type="text" id="cpf" name="cpf" required>
                    
                    <label for="login">Email (Login):</label>
                    <input type="email" id="login" name="login" required>
                    
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required>

                    <h2>ENDEREÇO</h2>

                    <label for="cep">CEP:</label>
                    <input type="text" id="cep" name="cep" required maxlength="9" pattern="\d{5}-?\d{3}" title="Informe o CEP. Exemplo: 12345-678">

                    <label for="rua">Rua:</label>
                    <input type="text" id="rua" name="rua" required>

                    <label for="numero">Número:</label>
                    <input type="text" id="numero" name="numero" required>
                    
                    <label for="cidade">Cidade:</label>
                    <input type="text" id="cidade" name="cidade" required>
                    
                    <label for="estado">Estado:</label>
                    <input type="text" id="estado" name="estado" maxlength="2" required>

                    <div class="botoes-form">
                        <button type="button" onclick="window.location.href='dashboard.php'">
                            VOLTAR
                        </button>
                        <button type="button" onclick="window.location.href='lista_motoristas.php'">
                            MOTORISTAS
                        </button>
                        <button type="submit" class="btn-principal">CADASTRAR MOTORISTA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>