<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Inclui o arquivo de conexão (mantém caminho relativo)
include '../includes/conexao.php';

// Inicializa a variável de erro
$erro = null; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Permitir login apenas de admin OU motorista
    $sql = "SELECT id, nome_completo, senha, tipo_usuario FROM usuarios WHERE email = ? AND (tipo_usuario = 'admin' OR tipo_usuario = 'motorista')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $dados = $resultado->fetch_assoc();

        // Verifica a senha (compatível com md5 ou sha1)
        if (md5($senha) === $dados['senha'] || sha1($senha) === $dados['senha']) { 
            // Define as variáveis de sessão:
            $_SESSION['usuario_id'] = $dados['id'];
            $_SESSION['usuario_nome'] = $dados['nome_completo']; // Variável usada em home.php
            $_SESSION['tipo_usuario'] = $dados['tipo_usuario'];
            // Redireciona para a tela home.php após o login
            header("Location: home.php"); 
            exit();
        } else {
            $erro = "Senha incorreta.";
        }
    } else {
        $erro = "Usuário não encontrado ou você não possui perfil de administrador ou motorista.";
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Projeto Caminhões - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 0; 
            background-color: #A9A9A9; 
            font-family: Arial, sans-serif;
        }
        .login-container {
             text-align: center; 
             background-color: white;
             padding: 40px;
             border-radius: 15px;
             box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
             width: 100%;
             max-width: 380px;
             position: relative;
        }
        .perfil-icone-wrapper {
             position: absolute;
             top: -40px; 
             left: 50%;
             transform: translateX(-50%);
             width: 80px; 
             height: 80px; 
             background-color: white; 
             border: 3px solid #63b1e3; 
             border-radius: 50%;
             display: flex;
             justify-content: center; 
             align-items: center;
             box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
        }
        .perfil-icone-wrapper i {
            color: #63b1e3;
            font-size: 2.2em;
        }
        .login-title {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 30px; 
            margin-top: 10px; 
            color: #333;
        }
        .input-icone-wrapper {
            position: relative; 
            margin-bottom: 20px;
            text-align: left;
        }
        .input-icone-wrapper i {
            position: absolute;
            top: 50%; 
            left: 15px; 
            transform: translateY(-50%);
            color: #63b1e3; 
            font-size: 1.1em; 
            pointer-events: none; 
        }
        .login-container input[type="email"],
        .login-container input[type="password"] {
            padding: 15px 15px 15px 45px; 
            box-sizing: border-box; 
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        .link-alinhamento-direita {
            text-align: right;
            margin-top: -10px; 
            margin-bottom: 25px; 
        }
        .link-alinhamento-direita .link-esqueci-senha {
             color: #63b1e3; 
             text-decoration: none;
             font-size: 0.9em;
             font-weight: bold;
             transition: color 0.3s;
        }
        .login-container button[type="submit"] {
            background-color: #63b1e3; 
            color: white;
            padding: 15px;
            border: none;
            border-radius: 5px;
            width: 100%;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .login-container button[type="submit"]:hover {
            background-color: #559ecf; 
        }
        .login-container p {
            color: #c0392b;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
    <link rel="stylesheet" type="text/css" href="../css/style.css"> 
</head>
<body>
    <div class="login-container">
        <div class="perfil-icone-wrapper">
             <i class="fa-solid fa-user"></i> 
        </div>
        <h2 class="login-title"></h2>
        <?php if (isset($erro)) { echo "<p>$erro</p>"; } ?>
        <form action="login.php" method="POST">
            <div class="input-icone-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="E-mail" required>
            </div>
            <div class="input-icone-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="senha" placeholder="Senha" required>
            </div>
            <div class="link-alinhamento-direita">
                 <a href="#" class="link-esqueci-senha">ESQUECI MINHA SENHA</a>
            </div>
            <button type="submit">LOGIN</button>
        </form>
    </div>
</body>
</html>